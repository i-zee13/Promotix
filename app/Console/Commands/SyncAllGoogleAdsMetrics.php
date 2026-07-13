<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesGoogleAdsSyncDateRange;
use App\Models\Domain;
use App\Services\GoogleAdsDomainMetricsSync;
use App\Services\GoogleAdsIpExclusionSyncService;
use App\Services\GoogleAdsLocationExclusionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAllGoogleAdsMetrics extends Command
{
    use ResolvesGoogleAdsSyncDateRange;

    protected $signature = 'google-ads:sync-all
        {--days=30 : Days back when --from/--to not set}
        {--from= : Start date YYYY-MM-DD}
        {--to= : End date YYYY-MM-DD}
        {--domain= : Only sync this domain ID}
        {--purge-all : Delete ALL stored metrics for each domain before sync}';

    protected $description = 'Sync Google Ads daily click metrics for all linked domains';

    public function handle(GoogleAdsDomainMetricsSync $sync, GoogleAdsIpExclusionSyncService $exclusionSync, GoogleAdsLocationExclusionSyncService $locationSync): int
    {
        $range = $this->resolveSyncDateRange();
        if ($range === null) {
            return self::FAILURE;
        }

        [$from, $to] = $range;
        $purgeAll = (bool) $this->option('purge-all');
        $domainFilter = trim((string) $this->option('domain'));

        $query = Domain::query()
            ->whereNotNull('google_ads_account_id')
            ->with('googleAdsAccount')
            ->orderBy('id');

        if ($domainFilter !== '') {
            $query->where('id', (int) $domainFilter);
        }

        $domains = $query->get()->filter(
            fn (Domain $domain) => $domain->googleAdsAccount && ! $domain->googleAdsAccount->is_manager
        );

        if ($domains->isEmpty()) {
            $this->info('No domains with linked Google Ads accounts.');

            return self::SUCCESS;
        }

        $this->line(sprintf('Range: %s → %s', $from->toDateString(), $to->toDateString()));
        if ($purgeAll) {
            $this->warn('Purge-all enabled: deleting all stored metrics before import.');
        }

        $savedTotal = 0;
        $failed = 0;
        $exclusionsSynced = 0;
        $locationsSynced = 0;

        foreach ($domains as $domain) {
            if ($purgeAll) {
                $deleted = $sync->purgeAllMetrics($domain);
                $this->line(sprintf('Domain #%d: purged %d old row(s).', $domain->id, $deleted));
            }

            $result = $sync->syncDomain($domain, $from->toDateString(), $to->toDateString());
            $saved = (int) ($result['saved'] ?? 0);
            $savedTotal += $saved;

            $suffix = '';
            if (! empty($result['api_error'])) {
                $suffix = ' — API: ' . $result['api_error'];
                $failed++;
            } elseif (! empty($result['message'])) {
                $suffix = ' — ' . $result['message'];
                if ($saved === 0) {
                    $failed++;
                }
            }

            $this->line(sprintf(
                'Domain #%d (%s): %d rows%s',
                $domain->id,
                $domain->hostname,
                $saved,
                $suffix
            ));

            $pendingExclusions = $exclusionSync->syncPendingForDomain($domain, 50);
            if ($pendingExclusions > 0) {
                $exclusionsSynced += $pendingExclusions;
                $this->line(sprintf('  → %d pending IP exclusion(s) pushed to Google Ads campaigns.', $pendingExclusions));
            }

            $pendingLocations = $locationSync->syncPendingForDomain($domain, 50);
            if ($pendingLocations > 0) {
                $locationsSynced += $pendingLocations;
                $this->line(sprintf('  → %d pending location exclusion(s) pushed to Google Ads campaigns.', $pendingLocations));
            }
        }

        Log::info('Google Ads sync-all finished', [
            'domains' => $domains->count(),
            'rows_saved' => $savedTotal,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'purge_all' => $purgeAll,
        ]);

        $this->info("Done. {$savedTotal} metric rows saved across {$domains->count()} domain(s).");
        if ($exclusionsSynced > 0) {
            $this->info("{$exclusionsSynced} blocked IP(s) synced to Google Ads campaign exclusion lists.");
        }
        if ($locationsSynced > 0) {
            $this->info("{$locationsSynced} location exclusion(s) synced to Google Ads campaigns.");
        }

        if ($failed > 0) {
            $this->newLine();
            $this->warn('Some domains returned 0 rows. If you see "UNAUTHENTICATED" or token errors, reconnect Google in Integrations, then run this command again.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
