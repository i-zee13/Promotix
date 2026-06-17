<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DomainDataPurger
{
    /** @var list<string> */
    private array $domainScopedTables = [
        'detection_logs',
        'visits',
        'ip_sessions',
        'analytics_hourly',
        'google_ads_campaign_daily_metrics',
        'domain_google_ads_mappings',
        'domain_detection_settings',
        'tracking_scripts',
        'domain_settings',
        'paid_marketing_visits',
    ];

    public function purge(Domain $domain): void
    {
        $domainId = (int) $domain->id;
        $ips = $this->collectIps($domainId);

        DB::transaction(function () use ($domainId): void {
            $this->deletePaidMarketingClicks($domainId);

            foreach ($this->domainScopedTables as $table) {
                $this->deleteFromTable($table, $domainId);
            }
        });

        $this->purgeOrphanIpLogs($ips);
    }

    /**
     * @return list<string>
     */
    private function collectIps(int $domainId): array
    {
        $ips = collect();

        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'ip')) {
            $ips = $ips->merge(
                DB::table('visits')->where('domain_id', $domainId)->distinct()->pluck('ip')
            );
        }

        if (Schema::hasTable('paid_marketing_visits') && Schema::hasColumn('paid_marketing_visits', 'ip')) {
            $ips = $ips->merge(
                DB::table('paid_marketing_visits')->where('domain_id', $domainId)->distinct()->pluck('ip')
            );
        }

        if (Schema::hasTable('ip_sessions') && Schema::hasColumn('ip_sessions', 'ip')) {
            $ips = $ips->merge(
                DB::table('ip_sessions')->where('domain_id', $domainId)->distinct()->pluck('ip')
            );
        }

        return $ips
            ->map(fn ($ip) => trim((string) $ip))
            ->filter(fn (string $ip) => $ip !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function deletePaidMarketingClicks(int $domainId): void
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits')) {
            return;
        }

        /** @var Collection<int, int|string> $visitIds */
        $visitIds = DB::table('paid_marketing_visits')
            ->where('domain_id', $domainId)
            ->pluck('id');

        if ($visitIds->isEmpty()) {
            return;
        }

        DB::table('paid_marketing_clicks')
            ->whereIn('paid_marketing_visit_id', $visitIds->all())
            ->delete();
    }

    private function deleteFromTable(string $table, int $domainId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'domain_id')) {
            return;
        }

        DB::table($table)->where('domain_id', $domainId)->delete();
    }

    /**
     * @param  list<string>  $ips
     */
    private function purgeOrphanIpLogs(array $ips): void
    {
        if (! Schema::hasTable('ip_logs') || $ips === []) {
            return;
        }

        foreach ($ips as $ip) {
            if ($this->ipStillTracked($ip)) {
                continue;
            }

            DB::table('ip_logs')->where('ip', $ip)->delete();
        }
    }

    private function ipStillTracked(string $ip): bool
    {
        if (Schema::hasTable('visits') && DB::table('visits')->where('ip', $ip)->exists()) {
            return true;
        }

        if (Schema::hasTable('paid_marketing_visits') && DB::table('paid_marketing_visits')->where('ip', $ip)->exists()) {
            return true;
        }

        return Schema::hasTable('ip_sessions') && DB::table('ip_sessions')->where('ip', $ip)->exists();
    }
}
