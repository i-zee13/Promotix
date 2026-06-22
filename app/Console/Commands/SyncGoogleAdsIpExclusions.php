<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\GoogleAdsIpExclusionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncGoogleAdsIpExclusions extends Command
{
    protected $signature = 'google-ads:sync-ip-exclusions
        {--domain= : Only this domain ID}
        {--limit=100 : Max rows to process per run}
        {--retry-failed : Include failed rows (reset to pending first)}
        {--list : List queued IPs only, do not push}';

    protected $description = 'Push blocked IP exclusions to Google Ads campaign exclusion lists';

    public function handle(GoogleAdsIpExclusionSyncService $sync): int
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            $this->warn('google_ads_ip_exclusions table not found. Run: php artisan migrate');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $domainId = (int) $this->option('domain');

        if ((bool) $this->option('retry-failed')) {
            $retryQuery = DB::table('google_ads_ip_exclusions')->where('sync_status', 'failed');
            if ($domainId > 0) {
                $retryQuery->where('domain_id', $domainId);
            }
            $retried = $retryQuery->update([
                'sync_status' => 'pending',
                'sync_error' => null,
                'updated_at' => now(),
            ]);
            if ($retried > 0) {
                $this->line("Reset {$retried} failed row(s) to pending.");
            }
        }

        $listQuery = DB::table('google_ads_ip_exclusions as e')
            ->join('domains as d', 'd.id', '=', 'e.domain_id')
            ->orderByDesc('e.updated_at')
            ->select([
                'e.id',
                'e.domain_id',
                'd.hostname',
                'e.ip',
                'e.threat_group',
                'e.sync_status',
                'e.sync_error',
                'e.synced_at',
            ]);

        if ($domainId > 0) {
            $listQuery->where('e.domain_id', $domainId);
        }

        if ((bool) $this->option('list')) {
            $rows = $listQuery->limit($limit)->get();
            if ($rows->isEmpty()) {
                $this->info('No IP exclusions in queue.');

                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'Domain', 'IP', 'Threat', 'Status', 'Synced at', 'Error'],
                $rows->map(fn ($row) => [
                    $row->id,
                    $row->hostname,
                    $row->ip,
                    $row->threat_group ?: '—',
                    $row->sync_status,
                    $row->synced_at ?: '—',
                    $row->sync_error ? mb_strimwidth((string) $row->sync_error, 0, 60, '…') : '—',
                ])->all()
            );

            $this->newLine();
            $this->line('Push pending: php artisan google-ads:sync-ip-exclusions');
            $this->line('Retry failed: php artisan google-ads:sync-ip-exclusions --retry-failed');

            return self::SUCCESS;
        }

        $pendingQuery = DB::table('google_ads_ip_exclusions')
            ->where('sync_status', 'pending')
            ->orderBy('id');

        if ($domainId > 0) {
            $pendingQuery->where('domain_id', $domainId);
        }

        $rows = $pendingQuery->limit($limit)->get(['domain_id', 'ip']);
        if ($rows->isEmpty()) {
            $this->info('No pending IP exclusions.');
            $this->line('List all: php artisan google-ads:sync-ip-exclusions --list');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $domain = Domain::query()->find($row->domain_id);
            if (! $domain) {
                $this->warn("Domain #{$row->domain_id} missing — skip {$row->ip}");
                $failed++;

                continue;
            }

            if ($sync->syncRow($domain, (string) $row->ip)) {
                $synced++;
                $this->line("✓ {$domain->hostname} → {$row->ip}");
            } else {
                $failed++;
                $status = DB::table('google_ads_ip_exclusions')
                    ->where('domain_id', $domain->id)
                    ->where('ip', $row->ip)
                    ->value('sync_error');
                $this->error("✗ {$domain->hostname} → {$row->ip}: " . ($status ?: 'push failed'));
            }
        }

        $this->newLine();
        $this->info("Done. Synced: {$synced}, failed: {$failed}, processed: {$rows->count()}.");

        return $failed > 0 && $synced === 0 ? self::FAILURE : self::SUCCESS;
    }
}
