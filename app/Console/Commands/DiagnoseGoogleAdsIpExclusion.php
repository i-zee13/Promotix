<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\GoogleAdsIpExclusionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseGoogleAdsIpExclusion extends Command
{
    protected $signature = 'google-ads:diagnose-ip-exclusion
        {domain : Domain ID}
        {ip : IP address to check or push}
        {--push : Insert pending row and push to Google Ads now}';

    protected $description = 'Diagnose why an IP is or is not on Google Ads campaign exclusion lists';

    public function handle(GoogleAdsIpExclusionSyncService $sync): int
    {
        $domainId = (int) $this->argument('domain');
        $ip = trim((string) $this->argument('ip'));

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->error('Invalid IP address.');

            return self::FAILURE;
        }

        $domain = Domain::with('googleAdsAccount.connection')->find($domainId);
        if (! $domain) {
            $this->error("Domain #{$domainId} not found.");

            return self::FAILURE;
        }

        $this->info("Domain #{$domain->id}: {$domain->hostname}");
        $this->line('Google Ads account: ' . ($domain->google_ads_account_id ? "#{$domain->google_ads_account_id} ({$domain->googleAdsAccount?->customer_id})" : 'NOT LINKED'));

        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            $this->error('Table google_ads_ip_exclusions missing. Run: php artisan migrate --force');

            return self::FAILURE;
        }

        $campaigns = DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->select('campaign_id', 'campaign_name')
            ->distinct()
            ->get();

        $this->line('Campaigns in metrics table: ' . $campaigns->count());
        foreach ($campaigns as $campaign) {
            $this->line("  - {$campaign->campaign_id} ({$campaign->campaign_name})");
        }

        if ($campaigns->isEmpty()) {
            $this->warn('No campaigns synced yet. Run: php artisan google-ads:sync-all --days=7');
        }

        $row = DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domainId)
            ->where('ip', $ip)
            ->first();

        $this->newLine();
        $this->info('Queue row (google_ads_ip_exclusions):');
        if ($row) {
            $this->table(
                ['Field', 'Value'],
                [
                    ['sync_status', $row->sync_status],
                    ['sync_error', $row->sync_error ?: '—'],
                    ['synced_at', $row->synced_at ?: '—'],
                    ['threat_group', $row->threat_group ?: '—'],
                ]
            );
        } else {
            $this->warn('  No row for this IP yet.');
        }

        if ((bool) $this->option('push')) {
            DB::table('google_ads_ip_exclusions')->updateOrInsert(
                ['domain_id' => $domainId, 'ip' => $ip],
                [
                    'threat_group' => 'manual',
                    'exclusion_mode' => 'manual_test',
                    'sync_status' => 'pending',
                    'sync_error' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $this->line('Pushing to Google Ads...');
            $ok = $sync->syncRow($domain, $ip);
            $row = DB::table('google_ads_ip_exclusions')->where('domain_id', $domainId)->where('ip', $ip)->first();
            $this->line($ok ? '<info>syncRow: success</info>' : '<error>syncRow: failed</error>');
            if ($row?->sync_error) {
                $this->error('Error: ' . $row->sync_error);
            }
        }

        $this->newLine();
        $this->info('Live Google Ads check (API):');
        $found = $sync->verifyIpOnCampaigns($domain, $ip);
        if ($found === []) {
            $this->error("IP {$ip} is NOT on any campaign exclusion list for this domain.");
            $this->line('Try: php artisan google-ads:diagnose-ip-exclusion ' . $domainId . ' ' . $ip . ' --push');

            return self::FAILURE;
        }

        $this->table(
            ['Campaign ID', 'Stored as'],
            array_map(fn ($r) => [$r['campaign_id'], $r['ip_address']], $found)
        );
        $this->info('Google stores single IPv4 as x.x.x.x/32 — this is normal.');

        return self::SUCCESS;
    }
}
