<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\GoogleAdsDomainMetricsSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAllGoogleAdsMetrics extends Command
{
    protected $signature = 'google-ads:sync-all {--days=7 : How many days back to refresh}';

    protected $description = 'Sync Google Ads daily click metrics for all linked domains';

    public function handle(GoogleAdsDomainMetricsSync $sync): int
    {
        $days = max(1, (int) $this->option('days'));
        $to = now()->endOfDay();
        $from = now()->subDays($days)->startOfDay();

        $domains = Domain::query()
            ->whereNotNull('google_ads_account_id')
            ->with('googleAdsAccount')
            ->get()
            ->filter(fn (Domain $domain) => $domain->googleAdsAccount && ! $domain->googleAdsAccount->is_manager);

        if ($domains->isEmpty()) {
            $this->info('No domains with linked Google Ads accounts.');

            return self::SUCCESS;
        }

        $savedTotal = 0;
        foreach ($domains as $domain) {
            $result = $sync->syncDomain($domain, $from, $to);
            $saved = (int) ($result['saved'] ?? 0);
            $savedTotal += $saved;
            $this->line(sprintf(
                'Domain #%d (%s): %d rows%s',
                $domain->id,
                $domain->hostname,
                $saved,
                ! empty($result['message']) ? ' — ' . $result['message'] : ''
            ));
        }

        Log::info('Google Ads sync-all finished', [
            'domains' => $domains->count(),
            'rows_saved' => $savedTotal,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        $this->info("Done. {$savedTotal} metric rows saved across {$domains->count()} domain(s).");

        return self::SUCCESS;
    }
}
