<?php

namespace App\Support\PaidAdvertising;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Google IP exclusion eligibility gate (Manual flowchart end).
 * Identity block can happen without this; platform IP push must pass safety checks.
 *
 * Meta / Microsoft excluded from product scope for now.
 */
class IpExclusionEligibilityGate
{
    /**
     * @param  array<string, mixed>  $detection
     * @return array{
     *   eligible: bool,
     *   status: 'not_needed'|'queued'|'suppressed',
     *   reasons: list<string>
     * }
     */
    public function evaluate(
        int $domainId,
        string $ip,
        ResolvedPaidIdentity $identity,
        array $detection,
    ): array {
        $statusReasons = [];
        $trafficStatus = (string) ($detection['traffic_status'] ?? '');
        $action = (string) ($detection['action_taken'] ?? 'allow');
        $score = (int) ($detection['paid_risk_score'] ?? $detection['threat_score'] ?? 0);
        $ads = is_array($detection['ads_detections'] ?? null) ? $detection['ads_detections'] : [];
        $decisive = collect($ads)->contains(fn ($r) => (bool) ($r['can_block_alone'] ?? false));

        if ($action !== 'block' && $trafficStatus !== 'invalid') {
            return [
                'eligible' => false,
                'status' => 'not_needed',
                'reasons' => ['traffic_not_invalid'],
            ];
        }

        if ($score < 85 && ! $decisive) {
            $statusReasons[] = 'score_below_85_without_decisive_rule';
        }

        if ($identity->confidence < 0.85 && ! $decisive) {
            $statusReasons[] = 'identity_confidence_below_0_85';
        }

        $shared = $this->sharedIpProbability($domainId, $ip);
        if ($shared >= 0.55) {
            $statusReasons[] = 'shared_ip_probability_high';
        }

        if ($statusReasons !== []) {
            return [
                'eligible' => false,
                'status' => 'suppressed',
                'reasons' => $statusReasons,
            ];
        }

        return [
            'eligible' => true,
            'status' => 'queued',
            'reasons' => ['passed_safety_gate'],
        ];
    }

    /**
     * Rough shared-network score: more distinct devices on same IP → higher probability.
     */
    public function sharedIpProbability(int $domainId, string $ip): float
    {
        if ($ip === '' || ! $this->tableReady('visits')) {
            return 0.0;
        }

        try {
            $since = now()->subDay();
            $q = DB::table('visits')
                ->where('domain_id', $domainId)
                ->where('ip', $ip)
                ->where('is_paid_traffic', true)
                ->where('visited_at', '>=', $since);

            $deviceCount = 1;
            if ($this->columnReady('visits', 'device_id')) {
                $deviceCount = max(1, (int) (clone $q)->whereNotNull('device_id')->distinct()->count('device_id'));
            } elseif ($this->columnReady('visits', 'browser_id')) {
                $deviceCount = max(1, (int) (clone $q)->whereNotNull('browser_id')->distinct()->count('browser_id'));
            }

            if ($deviceCount >= 6) {
                return 0.85;
            }
            if ($deviceCount >= 3) {
                return 0.60;
            }
            if ($deviceCount >= 2) {
                return 0.35;
            }

            return 0.10;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnReady(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
