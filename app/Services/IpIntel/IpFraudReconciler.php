<?php

namespace App\Services\IpIntel;

use App\Models\Domain;
use App\Models\IpLog;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Services\GoogleAudienceExclusionService;
use App\Support\CountryValue;
use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IpFraudReconciler
{
    public function __construct(
        private readonly IpFraudEvaluator $evaluator,
        private readonly GoogleAudienceExclusionService $googleExclusions,
    ) {
    }

    /**
     * Re-apply fraud rules to recent rows after async intel enrichment.
     */
    public function reconcileForIp(IpLog $ipLog, int $lookbackDays = 7): void
    {
        $ip = $ipLog->ip;
        $since = now()->subDays($lookbackDays);

        if (Schema::hasTable('visits')) {
            $this->reconcileVisits($ipLog, $ip, $since);
        }

        $this->reconcilePaidMarketing($ipLog, $ip);
    }

    private function reconcileVisits(IpLog $ipLog, string $ip, \Illuminate\Support\Carbon $since): void
    {
        $visitColumns = ['id', 'domain_id', 'country', 'session_id', 'visited_at'];
        if (Schema::hasColumn('visits', 'is_paid_traffic')) {
            $visitColumns[] = 'is_paid_traffic';
        }
        foreach (['gclid', 'gbraid', 'wbraid'] as $clickColumn) {
            if (Schema::hasColumn('visits', $clickColumn)) {
                $visitColumns[] = $clickColumn;
            }
        }

        $rows = DB::table('visits')
            ->where('ip', $ip)
            ->where('visited_at', '>=', $since)
            ->orderBy('id')
            ->get($visitColumns);

        foreach ($rows as $row) {
            $domain = Domain::find($row->domain_id);
            if (! $domain) {
                continue;
            }

            $isPaidTraffic = (bool) ($row->is_paid_traffic ?? false)
                || ! empty($row->gclid ?? null)
                || ! empty($row->gbraid ?? null)
                || ! empty($row->wbraid ?? null);

            $visitedAt = $row->visited_at
                ? Carbon::parse($row->visited_at)
                : now();

            $detection = $this->evaluator->evaluate(
                $domain,
                $ipLog,
                $row->country,
                $this->sessionHitsAt($domain, $row->session_id ?? null, $visitedAt),
                $this->ipRecentHitsAt($domain, $ip, $visitedAt),
                false,
                $isPaidTraffic,
                $isPaidTraffic
                    ? $this->paidClicksBeforeVisit($domain, $ip, $visitedAt, (int) $row->id)
                    : 0,
                $this->ipMinuteHitsAt($domain, $ip, $visitedAt),
                $isPaidTraffic
                    ? $this->paidClicksInWindowBeforeVisit($domain, $ip, $visitedAt, (int) $row->id, IpFraudEvaluator::PAID_RAPID_WINDOW_SECONDS)
                    : 0,
            );

            if (
                $detection['action_taken'] === 'block'
                && ! AllowListMatcher::reasonsIndicateAllowList($detection['reasons'])
            ) {
                $ipLog->is_blocked = true;
                $ipLog->save();

                if ($isPaidTraffic && ! AllowListMatcher::isAllowListed($domain, $ip)) {
                    $this->googleExclusions->queueBlockedIpIfEligible(
                        $domain,
                        $ip,
                        $detection['threat_group'] ?? null,
                        isPaidTraffic: true,
                    );
                }
            } elseif (AllowListMatcher::isAllowListed($domain, $ip)) {
                $ipLog->is_blocked = false;
                $ipLog->save();
            }

            $payload = [
                'is_invalid_traffic' => $detection['action_taken'] !== 'allow',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('visits', 'threat_group')) {
                $payload['threat_group'] = $detection['threat_group'];
            }
            if (Schema::hasColumn('visits', 'action_taken')) {
                $payload['action_taken'] = $detection['action_taken'];
            }
            if (Schema::hasColumn('visits', 'threat_score')) {
                $payload['threat_score'] = $detection['threat_score'];
            }
            if (Schema::hasColumn('visits', 'detection_reasons')) {
                $payload['detection_reasons'] = json_encode($detection['reasons']);
            }
            if (Schema::hasColumn('visits', 'country') && $ipLog->intel_country_code) {
                $payload['country'] = CountryValue::forVisitsTable($ipLog);
            }

            DB::table('visits')->where('id', $row->id)->update($payload);
        }
    }

    private function reconcilePaidMarketing(IpLog $ipLog, string $ip): void
    {
        $visits = PaidMarketingVisit::where('ip', $ip)->get();

        foreach ($visits as $visit) {
            $domain = Domain::find($visit->domain_id);
            if (! $domain) {
                continue;
            }

            $detection = $this->evaluator->evaluate($domain, $ipLog, $visit->country);

            $visit->country = $ipLog->intel_country_name ?: $visit->country;
            $visit->threat_group = $detection['threat_group'];
            $visit->threat_type = $detection['action_taken'] === 'allow' ? null : $detection['action_taken'];
            $visit->save();

            PaidMarketingClick::where('paid_marketing_visit_id', $visit->id)
                ->where('ip', $ip)
                ->update([
                    'country' => $ipLog->intel_country_name ?: $visit->country,
                    'threat_group' => $detection['threat_group'],
                ]);
        }
    }

    private function sessionHitsAt(Domain $domain, ?string $sessionId, Carbon $visitedAt): int
    {
        if ($sessionId === null || ! Schema::hasTable('ip_sessions')) {
            return 1;
        }

        return (int) (DB::table('ip_sessions')
            ->where('domain_id', $domain->id)
            ->where('session_id', $sessionId)
            ->where('last_seen_at', '<=', $visitedAt)
            ->value('hits') ?? 0) + 1;
    }

    private function ipRecentHitsAt(Domain $domain, string $ip, Carbon $visitedAt): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        return (int) DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->where('visited_at', '>=', $visitedAt->copy()->subMinutes(5))
            ->where('visited_at', '<', $visitedAt)
            ->count();
    }

    private function ipMinuteHitsAt(Domain $domain, string $ip, Carbon $visitedAt): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        return (int) DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->where('visited_at', '>=', $visitedAt->copy()->subMinute())
            ->where('visited_at', '<', $visitedAt)
            ->count();
    }

    private function paidClicksBeforeVisit(Domain $domain, string $ip, Carbon $visitedAt, int $visitId): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        $domain->loadMissing('user');
        $tz = UserTimezone::forUser($domain->user);
        $day = $visitedAt->copy()->timezone($tz)->toDateString();
        $from = Carbon::parse($day, $tz)->startOfDay()->utc()->toDateTimeString();
        $to = Carbon::parse($day, $tz)->endOfDay()->utc()->toDateTimeString();

        $query = DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->whereBetween('visited_at', [$from, $to])
            ->where('id', '<', $visitId);

        GoogleClickAttribution::applyHasClickIdFilter($query);

        if (Schema::hasColumn('visits', 'is_invalid_traffic')) {
            $query->where('is_invalid_traffic', 0);
        }

        return (int) $query->count();
    }

    private function paidClicksInWindowBeforeVisit(
        Domain $domain,
        string $ip,
        Carbon $visitedAt,
        int $visitId,
        int $windowSeconds,
    ): int {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        $query = DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->where('id', '<', $visitId)
            ->where('visited_at', '>=', $visitedAt->copy()->subSeconds($windowSeconds)->toDateTimeString())
            ->where('visited_at', '<', $visitedAt->toDateTimeString());

        GoogleClickAttribution::applyHasClickIdFilter($query);

        return (int) $query->count();
    }
}
