<?php

namespace App\Support\Clickronix;

/**
 * Pure Clickronix scoring engine (manual §4 + §15).
 *
 * - UNKNOWN/ERROR/SUPPRESSED/EXPIRED contribute 0
 * - Confidence floor for triggered rules: 0.50 (below → treated as UNKNOWN)
 * - Recurrence: 1.0 / 1.10 / 1.25
 * - Correlation bonus: +5 / +10 when independent categories confirm
 * - Category caps + automation-group dedupe
 * - Correlated/supporting never alone hard-block; standalone may
 */
final class ScoringEngine
{
    public const MODEL_VERSION = 'clickronix-score-1.0';

    /**
     * @param  list<TriggeredSignal>  $signals
     * @return array{
     *   threat_score: int,
     *   threat_group: ?string,
     *   action_taken: string,
     *   reasons: list<string>,
     *   risk_level: string,
     *   block_scope: string,
     *   duration_hint: string,
     *   category_scores: array<string, int>,
     *   trust_deduction: int,
     *   detections: list<array<string, mixed>>,
     *   correlation_satisfied: bool,
     *   standalone_fired: bool,
     *   ruleset_version: string,
     *   model_version: string,
     * }
     */
    public function score(array $signals): array
    {
        $detections = [];
        $categoryBuckets = [];
        $dedupeGroupTotals = [];
        $triggeredByCategory = [];
        $standaloneEnforce = false;
        $standaloneAction = 'block';
        $standaloneReasons = [];
        $legacyReasons = [];
        $primaryGroup = null;
        $primaryWeight = -1;
        $trustPoints = 0.0;

        foreach ($signals as $signal) {
            if (! $signal instanceof TriggeredSignal) {
                continue;
            }

            $rule = RuleCatalog::get($signal->ruleCode);
            if ($rule === null) {
                continue;
            }

            $state = $signal->state;
            $confidence = $signal->confidence;

            // Triggered below confidence floor → UNKNOWN (manual §4).
            if ($state === SignalState::TRIGGERED && $confidence < 0.50) {
                $state = SignalState::UNKNOWN;
            }

            if (! in_array($state, [SignalState::TRIGGERED, SignalState::PASS], true)) {
                $detections[] = $this->detectionRow(
                    $rule,
                    $signal,
                    $state,
                    0.0,
                    0.0,
                    false,
                    'not_required'
                );
                if ($signal->legacyReason) {
                    $legacyReasons[] = $signal->legacyReason;
                }

                continue;
            }

            $recurrence = $this->recurrenceMultiplier($signal->recurrenceCount);
            $rawContribution = $rule->basePoints * $confidence * $recurrence;

            if ($rule->mode === DecisionMode::TRUST || $rule->basePoints < 0) {
                // Trust maxPoints is the most-negative allowed deduction (e.g. base -15, max -30).
                $contribution = max((float) $rule->maxPoints, min(0.0, $rawContribution));
                if ($contribution < 0) {
                    $trustPoints += abs($contribution);
                }

                $detections[] = $this->detectionRow(
                    $rule,
                    $signal,
                    $state,
                    $rawContribution,
                    $contribution,
                    false,
                    'not_required'
                );
                if ($signal->legacyReason) {
                    $legacyReasons[] = $signal->legacyReason;
                }

                continue;
            }

            // Cap per-rule to maxPoints.
            $contribution = min((float) $rule->maxPoints, max(0.0, $rawContribution));

            if ($rule->mode === DecisionMode::STANDALONE && $state === SignalState::TRIGGERED) {
                $standaloneEnforce = true;
                $standaloneAction = $this->resolveStandaloneAction($rule, $signal);
                $standaloneReasons[] = $signal->legacyReason ?: $rule->code;
                if ($rule->legacyGroup && ($contribution > $primaryWeight || $primaryGroup === null)) {
                    $primaryGroup = $rule->legacyGroup;
                    $primaryWeight = $contribution;
                }
            }

            if (in_array($rule->mode, [DecisionMode::CORRELATED, DecisionMode::SUPPORTING, DecisionMode::STANDALONE], true)
                && $contribution > 0
            ) {
                $triggeredByCategory[$rule->category] = true;
            }

            $category = $rule->category;
            $categoryBuckets[$category] ??= [];
            $key = $rule->dedupeGroup ? 'g:'.$rule->dedupeGroup : 'r:'.$rule->code;

            if ($rule->dedupeGroup) {
                $prev = $dedupeGroupTotals[$rule->dedupeGroup] ?? 0.0;
                $dedupeGroupTotals[$rule->dedupeGroup] = max($prev, $contribution);
                $categoryBuckets[$category][$key] = $dedupeGroupTotals[$rule->dedupeGroup];
            } else {
                    $categoryBuckets[$category][$key] = max(
                        $categoryBuckets[$category][$key] ?? 0.0,
                        $contribution
                    );
            }

            if ($rule->legacyGroup && ($contribution > $primaryWeight || $primaryGroup === null)) {
                $primaryGroup = $rule->legacyGroup;
                $primaryWeight = $contribution;
            }

            if ($signal->legacyReason) {
                $legacyReasons[] = $signal->legacyReason;
            } else {
                $legacyReasons[] = $rule->code;
            }

            $detections[] = $this->detectionRow(
                $rule,
                $signal,
                $state,
                $rawContribution,
                $contribution,
                $rule->canEnforceAlone(),
                $rule->mode === DecisionMode::CORRELATED ? 'pending' : 'not_required'
            );
        }

        // Correlation bonus across independent risk categories (excluding trust-only).
        $independentCount = count($triggeredByCategory);
        $correlationBonus = 0.0;
        $correlationSatisfied = $independentCount >= 2;
        if ($independentCount >= 3) {
            $correlationBonus = 10.0;
        } elseif ($independentCount >= 2) {
            $correlationBonus = 5.0;
        }

        if ($correlationBonus > 0.0 && $triggeredByCategory !== []) {
            // Apply bonus to the strongest category bucket.
            $bestCat = array_key_first($triggeredByCategory);
            $bestSum = -1.0;
            foreach ($triggeredByCategory as $cat => $_) {
                $sum = array_sum($categoryBuckets[$cat] ?? []);
                if ($sum > $bestSum) {
                    $bestSum = $sum;
                    $bestCat = $cat;
                }
            }
            $categoryBuckets[$bestCat]['correlation_bonus'] = $correlationBonus;
        }

        $categoryScores = [];
        $total = 0.0;
        foreach (Category::riskCategories() as $cat) {
            $raw = array_sum($categoryBuckets[$cat] ?? []);
            $cap = Category::CAPS[$cat] ?? 30;
            $capped = (int) min($cap, (int) round($raw));
            $categoryScores[$cat] = max(0, $capped);
            $total += $categoryScores[$cat];
        }

        $trustDeduction = (int) min(40, (int) round($trustPoints));
        $final = (int) min(100, max(0, (int) round($total) - $trustDeduction));

        // Standalone decisive: enforcement ignores soft aggregate, but exposed score
        // must still reflect the rule strength (manual §6 standalone row).
        if ($standaloneEnforce && $primaryWeight > $final) {
            $final = (int) min(100, max($final, (int) round($primaryWeight)));
        }

        // Mark correlation on correlated detections.
        foreach ($detections as $i => $row) {
            if (($row['decision_mode'] ?? null) === DecisionMode::CORRELATED) {
                $detections[$i]['correlation_satisfied'] = $correlationSatisfied
                    ? true
                    : false;
            }
        }

        // Supporting-only: never hard-block from score alone if every risk signal is supporting
        // and no standalone fired — final action from matrix, but correlate gate softens blocks.
        $enforcement = EnforcementMatrix::forScore($final, $standaloneEnforce, $standaloneAction);

        if (! $standaloneEnforce) {
            $enforcement = $this->applyCorrelationGate(
                $enforcement,
                $signals,
                $correlationSatisfied,
                $final
            );
        }

        // Prefer legacy RAPID / policy reason codes when present.
        $reasons = array_values(array_unique(array_filter($legacyReasons)));
        if ($standaloneEnforce && $standaloneReasons !== []) {
            $reasons = array_values(array_unique(array_merge($standaloneReasons, $reasons)));
        }

        if ($reasons === [] && $final === 0) {
            return [
                'threat_score' => 0,
                'threat_group' => null,
                'action_taken' => 'allow',
                'reasons' => [],
                'risk_level' => EnforcementMatrix::LEVEL_TRUSTED,
                'block_scope' => 'none',
                'duration_hint' => 'none',
                'category_scores' => $categoryScores,
                'trust_deduction' => $trustDeduction,
                'detections' => $detections,
                'correlation_satisfied' => false,
                'standalone_fired' => false,
                'ruleset_version' => RuleCatalog::RULESET_VERSION,
                'model_version' => self::MODEL_VERSION,
            ];
        }

        return [
            'threat_score' => $final,
            'threat_group' => $primaryGroup,
            'action_taken' => $enforcement['action'],
            'reasons' => $reasons,
            'risk_level' => $enforcement['level'],
            'block_scope' => $enforcement['block_scope'],
            'duration_hint' => $enforcement['duration_hint'],
            'category_scores' => $categoryScores,
            'trust_deduction' => $trustDeduction,
            'detections' => $detections,
            'correlation_satisfied' => $correlationSatisfied,
            'standalone_fired' => $standaloneEnforce,
            'ruleset_version' => RuleCatalog::RULESET_VERSION,
            'model_version' => self::MODEL_VERSION,
        ];
    }

    /**
     * Correlated/supporting signals alone: soft-enforce at most (flag), never permanent block
     * unless final score is critical AND correlation satisfied.
     *
     * @param  array{level: string, action: string, duration_hint: string, block_scope: string}  $enforcement
     * @param  list<TriggeredSignal>  $signals
     * @return array{level: string, action: string, duration_hint: string, block_scope: string}
     */
    private function applyCorrelationGate(array $enforcement, array $signals, bool $correlationSatisfied, int $final): array
    {
        $modes = [];
        foreach ($signals as $signal) {
            if (! $signal instanceof TriggeredSignal || $signal->state !== SignalState::TRIGGERED) {
                continue;
            }
            if ($signal->confidence < 0.50) {
                continue;
            }
            $rule = RuleCatalog::get($signal->ruleCode);
            if ($rule === null || $rule->basePoints < 0 || $rule->mode === DecisionMode::TRUST) {
                continue;
            }
            $modes[$rule->mode] = true;
        }

        $onlySupporting = isset($modes[DecisionMode::SUPPORTING])
            && ! isset($modes[DecisionMode::CORRELATED])
            && ! isset($modes[DecisionMode::STANDALONE]);

        if ($onlySupporting) {
            // Manual §15.3 / Example A — supporting alone never hard-blocks.
            $enforcement['action'] = 'allow';
            $enforcement['block_scope'] = 'none';
            $enforcement['duration_hint'] = 'none';
            if ($final >= 20) {
                $enforcement['level'] = EnforcementMatrix::LEVEL_LOW;
            }

            return $enforcement;
        }

        if (isset($modes[DecisionMode::CORRELATED]) && ! $correlationSatisfied) {
            // One correlated family alone: challenge/flag at most, never block.
            if ($enforcement['action'] === 'block') {
                $enforcement['action'] = 'flag';
                $enforcement['duration_hint'] = 'challenge_or_rate_limit';
                $enforcement['block_scope'] = 'session';
                if ($final >= 75) {
                    $enforcement['level'] = EnforcementMatrix::LEVEL_HIGH;
                }
            }
        }

        return $enforcement;
    }

    private function recurrenceMultiplier(int $count): float
    {
        if ($count >= 5) {
            return 1.25;
        }
        if ($count >= 2) {
            return 1.10;
        }

        return 1.0;
    }

    private function resolveStandaloneAction(RuleDefinition $rule, TriggeredSignal $signal): string
    {
        if ($signal->customerPreferredAction && in_array($signal->customerPreferredAction, ['allow', 'flag', 'block'], true)) {
            return $signal->customerPreferredAction;
        }

        return in_array($rule->defaultAction, ['allow', 'flag', 'block'], true)
            ? $rule->defaultAction
            : 'block';
    }

    /**
     * @return array<string, mixed>
     */
    private function detectionRow(
        RuleDefinition $rule,
        TriggeredSignal $signal,
        string $state,
        float $baseContribution,
        float $finalContribution,
        bool $canEnforceAlone,
        string|bool $correlationSatisfied,
    ): array {
        return [
            'rule_code' => $rule->code,
            'rule_version' => $rule->version,
            'decision_mode' => $rule->mode,
            'can_enforce_alone' => $canEnforceAlone,
            'category' => $rule->category,
            'entity_scope' => $rule->entityScope,
            'state' => $state,
            'confidence' => (int) round($signal->confidence * 100),
            'base_points' => $rule->basePoints,
            'final_points' => (int) round($finalContribution),
            'raw_contribution' => round($baseContribution, 2),
            'recurrence_count' => $signal->recurrenceCount,
            'correlation_satisfied' => $correlationSatisfied,
            'raw_fields_present' => $signal->rawFieldsPresent,
            'raw_fields_missing' => $signal->rawFieldsMissing,
            'evidence' => $signal->evidence,
            'legacy_reason' => $signal->legacyReason,
            'action_scope' => $rule->entityScope,
        ];
    }
}
