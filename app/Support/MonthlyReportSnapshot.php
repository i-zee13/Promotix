<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot metrics for Settings PDF / monthly email reports (PDF §7).
 */
class MonthlyReportSnapshot
{
    /**
     * @param  list<int>  $domainIds
     * @return array<string, mixed>
     */
    public static function paid(array $domainIds, Carbon $from, Carbon $to): array
    {
        $empty = [
            'total_clicks' => 0,
            'invalid_clicks' => 0,
            'valid_clicks' => 0,
            'cost_saved' => 0.0,
            'repeat_ips' => 0,
            'repeat_devices' => 0,
            'high_risk_ips' => 0,
            'high_risk_devices' => 0,
        ];

        if ($domainIds === [] || ! Schema::hasTable('visits')) {
            return $empty;
        }

        $fromStr = $from->toDateTimeString();
        $toStr = $to->toDateTimeString();

        $base = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$fromStr, $toStr]);

        $total = (clone $base)->count();
        $invalid = (clone $base)->where(function ($q): void {
            $q->whereNotNull('threat_group')->where('threat_group', '!=', '');
        })->count();
        $valid = max(0, $total - $invalid);

        $repeatIps = (int) DB::table('visits')
            ->select('ip')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$fromStr, $toStr])
            ->groupBy('ip')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $repeatDevices = 0;
        if (Schema::hasColumn('visits', 'device_id')) {
            $repeatDevices = (int) DB::table('visits')
                ->select('device_id')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$fromStr, $toStr])
                ->whereNotNull('device_id')
                ->where('device_id', '!=', '')
                ->groupBy('device_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();
        }

        $highRiskIps = 0;
        $riskColumn = null;
        if (Schema::hasTable('ip_logs')) {
            if (Schema::hasColumn('ip_logs', 'abuse_confidence_score')) {
                $riskColumn = 'abuse_confidence_score';
            } elseif (Schema::hasColumn('ip_logs', 'ipdetails_abuser_score')) {
                $riskColumn = 'ipdetails_abuser_score';
            } elseif (Schema::hasColumn('ip_logs', 'risk_score')) {
                $riskColumn = 'risk_score';
            }
        }
        if ($riskColumn !== null) {
            $ips = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$fromStr, $toStr])
                ->distinct()
                ->pluck('ip')
                ->filter()
                ->values()
                ->all();
            if ($ips !== []) {
                $highRiskIps = (int) DB::table('ip_logs')
                    ->whereIn('ip', $ips)
                    ->where($riskColumn, '>=', 70)
                    ->count();
            }
        }

        $avgCpc = 0.75;
        if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $cpc = DB::table('google_ads_campaign_daily_metrics')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
                ->avg('cpc');
            if (is_numeric($cpc) && (float) $cpc > 0) {
                $avgCpc = (float) $cpc;
            }
        }

        return [
            'total_clicks' => $total,
            'invalid_clicks' => $invalid,
            'valid_clicks' => $valid,
            'cost_saved' => round($avgCpc * $invalid, 2),
            'repeat_ips' => $repeatIps,
            'repeat_devices' => $repeatDevices,
            'high_risk_ips' => $highRiskIps,
            'high_risk_devices' => $repeatDevices,
        ];
    }
}
