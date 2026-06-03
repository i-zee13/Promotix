<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Services\GoogleAdsDomainMetricsSync;
use Illuminate\Console\Command;

class SyncDomainGoogleAdsMetrics extends Command
{
    protected $signature = 'google-ads:sync-domain-metrics {domain_id : Domain ID} {--days=30}';

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

        $days = max(1, (int) $this->option('days'));
        $to = now()->endOfDay();
        $from = now()->subDays($days)->startOfDay();

        $result = $sync->syncDomain($domain, $from, $to);

        $this->info('Table: google_ads_campaign_daily_metrics');
        $this->info('Rows saved this run: ' . ($result['saved'] ?? 0));
        $this->info('Total rows for domain: ' . GoogleAdsCampaignDailyMetric::query()->where('domain_id', $domain->id)->count());

        if (! empty($result['message'])) {
            $this->warn((string) $result['message']);
        }

        $apiErr = app(\App\Services\GoogleAdsMetricsService::class)->lastApiError;
        if ($apiErr) {
            $this->error('Google API: ' . $apiErr);
        }

        $this->line('Linked account: ' . ($domain->googleAdsAccount?->displayLabel() ?? 'none'));
        $this->line('Customer ID: ' . ($domain->googleAdsAccount?->customer_id ?? 'none'));
        $this->line('Manager ID: ' . ($domain->googleAdsAccount?->manager_customer_id ?? 'none'));

        return ($result['saved'] ?? 0) > 0 ? self::SUCCESS : self::FAILURE;
    }
}
