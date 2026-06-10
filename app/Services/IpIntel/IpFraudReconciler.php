<?php

namespace App\Services\IpIntel;

use App\Models\Domain;
use App\Models\IpLog;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Support\CountryValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IpFraudReconciler
{
    public function __construct(private readonly IpFraudEvaluator $evaluator)
    {
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
        $rows = DB::table('visits')
            ->where('ip', $ip)
            ->where('visited_at', '>=', $since)
            ->orderBy('id')
            ->get(['id', 'domain_id', 'country']);

        foreach ($rows as $row) {
            $domain = Domain::find($row->domain_id);
            if (! $domain) {
                continue;
            }

            $detection = $this->evaluator->evaluate($domain, $ipLog, $row->country);

            if ($detection['action_taken'] === 'block') {
                $ipLog->is_blocked = true;
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
}
