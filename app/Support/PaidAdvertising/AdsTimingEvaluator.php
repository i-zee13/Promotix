<?php

namespace App\Support\PaidAdvertising;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Google-only timing / velocity subset for paid identity windows.
 */
class AdsTimingEvaluator
{
    /**
     * @param  array{ip: array<string,int>, browser: array<string,int>, device: array<string,int>, paid_identity: array<string,int>}  $windows
     * @return list<array<string, mixed>>
     */
    public function evaluate(
        int $domainId,
        ResolvedPaidIdentity $identity,
        array $windows,
        ?Carbon $now = null,
    ): array {
        $now = $now ?: now();
        $triggered = [];

        $secondsSincePrevious = $this->secondsSincePreviousPaidClick($domainId, $identity, $now);
        if ($secondsSincePrevious !== null && $secondsSincePrevious < 3) {
            $triggered[] = $this->rule(
                'ADS_ULTRA_RECLICK',
                'correlated',
                false,
                25,
                35,
                'challenge',
                ['seconds_since_previous' => $secondsSincePrevious]
            );
        } elseif ($secondsSincePrevious !== null && $secondsSincePrevious < 10) {
            $triggered[] = $this->rule(
                'ADS_RAPID_RECLICK',
                'supporting',
                false,
                15,
                25,
                'monitor',
                ['seconds_since_previous' => $secondsSincePrevious]
            );
        }

        $oneMinute = max(
            (int) ($windows['paid_identity']['1m'] ?? 0),
            (int) ($windows['device']['1m'] ?? 0),
            (int) ($windows['ip']['1m'] ?? 0),
        ) + 1;

        if ($oneMinute >= 10) {
            $triggered[] = $this->rule(
                'ADS_BURST_10_60S',
                'standalone',
                true,
                100,
                100,
                'block_identity',
                ['clicks_1m' => $oneMinute]
            );
        } elseif ($oneMinute >= 5) {
            $triggered[] = $this->rule(
                'ADS_5_60S',
                'decisive',
                true,
                70,
                85,
                'block_identity',
                ['clicks_1m' => $oneMinute]
            );
        } elseif ($oneMinute >= 3 && $secondsSincePrevious !== null && $secondsSincePrevious <= 30) {
            $triggered[] = $this->rule(
                'ADS_3_30S',
                'correlated',
                false,
                40,
                55,
                'challenge',
                ['clicks_1m' => $oneMinute, 'seconds_since_previous' => $secondsSincePrevious]
            );
        }

        return $triggered;
    }

    private function secondsSincePreviousPaidClick(
        int $domainId,
        ResolvedPaidIdentity $identity,
        Carbon $now,
    ): ?int {
        if (! Schema::hasTable('visits')) {
            return null;
        }

        try {
            $q = DB::table('visits')
                ->where('domain_id', $domainId)
                ->where('is_paid_traffic', true)
                ->where('visited_at', '<', $now);

            if ($identity->deviceId && Schema::hasColumn('visits', 'device_id')) {
                $q->where(function ($inner) use ($identity) {
                    $inner->where('device_id', $identity->deviceId);
                    if ($identity->browserId && Schema::hasColumn('visits', 'browser_id')) {
                        $inner->orWhere('browser_id', $identity->browserId);
                    }
                });
            } elseif ($identity->browserId && Schema::hasColumn('visits', 'browser_id')) {
                $q->where('browser_id', $identity->browserId);
            } else {
                return null;
            }

            $previous = $q->orderByDesc('visited_at')->value('visited_at');
            if (! $previous) {
                return null;
            }

            return max(0, $now->diffInSeconds(Carbon::parse((string) $previous)));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function rule(
        string $code,
        string $decisionType,
        bool $alone,
        int $base,
        int $max,
        string $action,
        array $evidence,
    ): array {
        return [
            'rule_code' => $code,
            'decision_type' => $decisionType,
            'can_block_alone' => $alone,
            'base_points' => $base,
            'max_points' => $max,
            'recommended_action' => $action,
            'evidence' => $evidence,
            'ruleset_version' => 'paid-risk-v4.0-google',
        ];
    }
}
