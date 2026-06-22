<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\GoogleAdsIpExclusionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncGoogleAdsIpExclusions extends Command
{
    protected $signature = 'google-ads:sync-ip-exclusions {--domain= : Domain id} {--limit=50 : Max pending rows per run}';

    protected $description = 'Push pending IP exclusions to linked Google Ads accounts';

    public function handle(GoogleAdsIpExclusionSyncService $sync): int
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            $this->warn('google_ads_ip_exclusions table not found.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $domainId = (int) $this->option('domain');

        $query = DB::table('google_ads_ip_exclusions')
            ->where('sync_status', 'pending')
            ->orderBy('id');

        if ($domainId > 0) {
            $query->where('domain_id', $domainId);
        }

        $rows = $query->limit($limit)->get(['domain_id', 'ip']);
        if ($rows->isEmpty()) {
            $this->info('No pending IP exclusions.');

            return self::SUCCESS;
        }

        $synced = 0;
        foreach ($rows as $row) {
            $domain = Domain::query()->find($row->domain_id);
            if (! $domain) {
                continue;
            }

            if ($sync->syncRow($domain, (string) $row->ip)) {
                $synced++;
            }
        }

        $this->info("Processed {$rows->count()} pending row(s); {$synced} synced.");

        return self::SUCCESS;
    }
}
