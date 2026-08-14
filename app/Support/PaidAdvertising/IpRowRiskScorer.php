<?php

namespace App\Support\PaidAdvertising;

/**
 * Dashboard IP-row risk level/score for Recent Paid Traffic.
 *
 * Prefer real detection points (visit threat_score / intel) over a flat
 * "invalid share ≥ 50% → High 70" floor, which made every 1/1 invalid IP identical.
 */
class IpRowRiskScorer
{
    /**
     * @param  array{
     *   invalid?: int,
     *   total?: int,
     *   threat_score?: int|float|null,
     *   intel_score?: int|float|null,
     *   top_threat?: string|null,
     *   vpn_hits?: int,
     *   data_center_hits?: int,
     *   malicious_hits?: int,
     * }  $input
     * @return array{risk_level: string, risk_score: ?int}
     */
    public static function score(array $input): array
    {
        $invalid = max(0, (int) ($input['invalid'] ?? 0));
        $total = max(0, (int) ($input['total'] ?? 0));
        $topThreat = strtolower(trim((string) ($input['top_threat'] ?? '')));

        if ($topThreat === '' || $topThreat === '0') {
            if ((int) ($input['malicious_hits'] ?? 0) > 0) {
                $topThreat = 'malicious';
            } elseif ((int) ($input['data_center_hits'] ?? 0) > 0) {
                $topThreat = 'data_center';
            } elseif ((int) ($input['vpn_hits'] ?? 0) > 0) {
                $topThreat = 'vpn';
            }
        }

        $candidates = [];

        $threatScore = self::normalizePercent($input['threat_score'] ?? null);
        if ($threatScore !== null && $threatScore > 0) {
            $candidates[] = $threatScore;
        }

        $intelScore = self::normalizePercent($input['intel_score'] ?? null);
        if ($intelScore !== null) {
            $candidates[] = $intelScore;
        }

        if ($invalid > 0) {
            $candidates[] = self::threatGroupFloor($topThreat);

            // Repeat volume can raise score without flattening every single-click IP to 70.
            if ($invalid >= 5) {
                $candidates[] = min(95, 55 + ($invalid * 4));
            } elseif ($invalid >= 3) {
                $candidates[] = 65;
            } elseif ($invalid >= 2) {
                $candidates[] = 58;
            }
        }

        $scorePct = $candidates !== [] ? max($candidates) : null;
        if ($scorePct === null && $invalid > 0 && $total > 0) {
            // Soft fallback when we have no intel/detection points — use share but do not floor at 70.
            $scorePct = (int) min(85, max(35, round(($invalid / max(1, $total)) * 70)));
        }

        if ($scorePct !== null) {
            $scorePct = (int) min(99, max(0, $scorePct));
            $riskLevel = $scorePct >= 70 ? 'High' : ($scorePct >= 40 ? 'Medium' : 'Low');
        } else {
            $riskLevel = 'Low';
        }

        // Invalid paid traffic should not look "Low" just because a vendor score is soft.
        if ($invalid > 0 && $riskLevel === 'Low') {
            $riskLevel = 'Medium';
            $scorePct = max($scorePct ?? 0, 40);
        }

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $scorePct,
        ];
    }

    private static function threatGroupFloor(string $topThreat): int
    {
        return match ($topThreat) {
            'malicious', 'blocked' => 90,
            'bot', 'automation', 'service_outage' => 85,
            'data_center', 'datacenter' => 80,
            'vpn', 'proxy', 'tor' => 75,
            'abnormal_rate_limit', 'repeated', 'manual_invalid' => 70,
            'out_of_geo', 'geo', 'audience' => 55,
            default => $topThreat !== '' ? 52 : 45,
        };
    }

    private static function normalizePercent(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $score = (float) $value;

        return $score <= 1.0
            ? (int) round($score * 100)
            : (int) round($score);
    }
}
