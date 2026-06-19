<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesGoogleAdsSyncDateRange;
use App\Models\Domain;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Services\GoogleAdsDomainMetricsSync;
use Illuminate\Console\Command;

class SyncDomainGoogleAdsMetrics extends Command
{
    use ResolvesGoogleAdsSyncDateRange;

    protected $signature = 'google-ads:sync-domain-metrics
        {domain_id : Domain ID}
        {--days=30 : Days back when --from/--to not set}
        {--from= : Start date YYYY-MM-DD}
        {--to= : End date YYYY-MM-DD}
        {--purge-all : Delete ALL stored metrics for this domain before sync}';

    protected $description = 'Pull Google Ads campaign metrics into google_ads_campaign_daily_metrics for a domain';

    public function handle(GoogleAdsDomainMetricsSync $sync): int
    {
        $domain = Domain::query()->find($this->argument('domain_id'));
        if (! $domain) {
            $this->error('Domain not found.');

            return self::FAILURE;
        }

        if (! $domain->google_ads_account_id) {
            $this->error('Domain has no google_ads_account_id. Connect Google Ads first.');

            return self::FAILURE;
        }

        $range = $this->resolveSyncDateRange();
        if ($range === null) {
            return self::FAILURE;
        }

        [$from, $to] = $range;
        $purgeAll = (bool) $this->option('purge-all');

        $this->line(sprintf('Range: %s → %s', $from->toDateString(), $to->toDateString()));

        if ($purgeAll) {
            $deleted = $sync->purgeAllMetrics($domain);
            $this->warn("Purged {$deleted} old row(s) for domain #{$domain->id}.");
        }

        $result = $sync->syncDomain($domain, $from->toDateString(), $to->toDateString());

        $this->info('Table: google_ads_campaign_daily_metrics');
        $this->info('Rows saved this run: ' . ($result['saved'] ?? 0));
        $this->info('Total rows for domain: ' . GoogleAdsCampaignDailyMetric::query()->where('domain_id', $domain->id)->count());

        $totalClicks = (int) GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->sum('clicks');
        $this->info("Total clicks in range: {$totalClicks}");

        if (! empty($result['api_error'])) {
            $this->error('Google API: ' . $result['api_error']);
            $this->warn('Reconnect Google in Integrations if the token expired, then run this command again.');
        } elseif (! empty($result['message'])) {
            $this->warn((string) $result['message']);
        }

        $this->line('Linked account: ' . ($domain->googleAdsAccount?->displayLabel() ?? 'none'));
        $this->line('Customer ID: ' . ($domain->googleAdsAccount?->customer_id ?? 'none'));
        $this->line('Manager ID: ' . ($domain->googleAdsAccount?->manager_customer_id ?? 'none'));

        return ($result['saved'] ?? 0) > 0 ? self::SUCCESS : self::FAILURE;
    }
}
