<?php

namespace App\Support\Clickronix;

/**
 * Aligns customer matrix "preferred" actions with Clickronix score bands.
 *
 * Correlated rules may soft-escalate to flag; they must not hard-block from
 * preference alone while the score is still in allow/low bands.
 */
final class ActionFloorResolver
{
    /**
     * @param  array<string, mixed>  $result  ScoringEngine output
     * @param  list<TriggeredSignal>  $signals
     * @return array<string, mixed>
     */
    public static function apply(array $result, array $signals): array
    {
        $rank = static fn (string $a): int => match ($a) {
            'block' => 3,
            'flag' => 2,
            default => 1,
        };

        $score = (int) ($result['threat_score'] ?? 0);
        $correlated = (bool) ($result['correlation_satisfied'] ?? false);
        $standalone = (bool) ($result['standalone_fired'] ?? false);

        $floor = 'allow';
        foreach ($signals as $signal) {
            if (! $signal instanceof TriggeredSignal) {
                continue;
            }
            if ($signal->state !== SignalState::TRIGGERED || $signal->confidence < 0.50) {
                continue;
            }

            $rule = RuleCatalog::get($signal->ruleCode);
            if ($rule === null) {
                continue;
            }

            if ($rule->mode === DecisionMode::SUPPORTING || $rule->mode === DecisionMode::TRUST) {
                continue;
            }

            $preferred = $signal->customerPreferredAction;
            if ($preferred === null || ! in_array($preferred, ['allow', 'flag', 'block'], true)) {
                continue;
            }

            if ($rule->mode === DecisionMode::STANDALONE) {
                // Standalone prefers its own action; band may already be block.
            } elseif ($rule->mode === DecisionMode::CORRELATED) {
                $preferred = self::softenCorrelatedPreference($preferred, $score, $correlated);
            }

            if ($rank($preferred) > $rank($floor)) {
                $floor = $preferred;
            }
        }

        $action = (string) ($result['action_taken'] ?? 'allow');
        if ($rank($floor) > $rank($action)) {
            $action = $floor;
        }

        // Never let a soft score look "critical" after a preference bump, and
        // never leave risk_level=low while action=block.
        $result['action_taken'] = $action;
        $result = self::syncRiskMetadata($result, $action, $standalone);

        return $result;
    }

    /**
     * Correlated matrix "block" is a bias toward harder handling — not an
     * instant IP block when the aggregate score is still low/mid.
     */
    public static function softenCorrelatedPreference(string $preferred, int $score, bool $correlationSatisfied): string
    {
        if ($preferred !== 'block') {
            // flag stays flag (e.g. RAPID_REPEAT floor) even at low scores.
            return $preferred;
        }

        if (! $correlationSatisfied) {
            return 'flag';
        }

        // Manual bands: hard block from aggregate starts ~75; 60–74 is challenge/flag.
        if ($score >= 75) {
            return 'block';
        }

        return 'flag';
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private static function syncRiskMetadata(array $result, string $action, bool $standalone): array
    {
        $score = (int) ($result['threat_score'] ?? 0);
        $band = EnforcementMatrix::forScore($score, $standalone && $action === 'block', $action);

        // If we soft-escalated above the pure score band, reflect that in risk_level.
        $actionRank = match ($action) {
            'block' => 3,
            'flag' => 2,
            default => 1,
        };
        $bandRank = match ($band['action']) {
            'block' => 3,
            'flag' => 2,
            default => 1,
        };

        if ($actionRank > $bandRank) {
            $result['risk_level'] = match ($action) {
                'block' => EnforcementMatrix::LEVEL_VERY_HIGH,
                'flag' => $score >= 40 ? EnforcementMatrix::LEVEL_SUSPICIOUS : EnforcementMatrix::LEVEL_LOW,
                default => $band['level'],
            };
            if ($action === 'block') {
                $result['block_scope'] = $result['block_scope'] ?? 'session_device_ip';
                $result['duration_hint'] = $result['duration_hint'] ?? 'preference_escalation';
            } elseif ($action === 'flag') {
                $result['block_scope'] = 'session';
                $result['duration_hint'] = 'challenge_or_rate_limit';
            }
        } else {
            $result['risk_level'] = $band['level'];
            $result['block_scope'] = $band['block_scope'];
            $result['duration_hint'] = $band['duration_hint'];
        }

        if (isset($result['clickronix']) && is_array($result['clickronix'])) {
            $result['clickronix']['risk_level'] = $result['risk_level'];
            $result['clickronix']['block_scope'] = $result['block_scope'] ?? null;
            $result['clickronix']['duration_hint'] = $result['duration_hint'] ?? null;
        }

        return $result;
    }
}
