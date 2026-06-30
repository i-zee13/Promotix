<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeFakeClicks extends Command
{
    protected $signature = 'promotix:purge-fake-clicks
        {--date= : Calendar date (YYYY-MM-DD). Default: today in app timezone}
        {--domain= : Limit to a single domain ID}
        {--dry-run : Show counts only, do not delete}
        {--force : Skip confirmation prompt}';

    protected $description = 'Remove PromoTix test/fake clicks for one calendar day and clean related ingestion rows';

    public function handle(): int
    {
        if (! Schema::hasTable('visits')) {
            $this->error('visits table not found.');

            return self::FAILURE;
        }

        $tz = config('app.timezone', 'UTC');
        $date = $this->option('date') ?: Carbon::now($tz)->toDateString();
        $domainId = $this->option('domain') ? (int) $this->option('domain') : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $dayStart = Carbon::parse($date, $tz)->startOfDay()->utc();
            $dayEnd = Carbon::parse($date, $tz)->endOfDay()->utc();
        } catch (\Throwable) {
            $this->error('Invalid --date. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $fakeVisitIds = $this->fakeVisitsQuery($dayStart, $dayEnd, $domainId)->pluck('id');
        $fakePmClickIds = $this->fakePaidMarketingClicksQuery($dayStart, $dayEnd, $domainId)->pluck('pc.id');
        $fakePmVisitIds = $this->fakePaidMarketingClicksQuery($dayStart, $dayEnd, $domainId)
            ->pluck('pc.paid_marketing_visit_id')
            ->unique()
            ->values();

        $fakeIps = $this->fakeVisitsQuery($dayStart, $dayEnd, $domainId)
            ->pluck('ip')
            ->merge(
                $this->fakePaidMarketingClicksQuery($dayStart, $dayEnd, $domainId)->pluck('pc.ip')
            )
            ->filter()
            ->unique()
            ->values();

        $fakeSessionIds = $this->fakeVisitsQuery($dayStart, $dayEnd, $domainId)
            ->whereNotNull('session_id')
            ->pluck('session_id')
            ->unique()
            ->values();

        $affectedDomainIds = $this->fakeVisitsQuery($dayStart, $dayEnd, $domainId)
            ->pluck('domain_id')
            ->merge(
                DB::table('paid_marketing_clicks as pc')
                    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
                    ->whereIn('pc.id', $fakePmClickIds)
                    ->pluck('pv.domain_id')
            )
            ->unique()
            ->values();

        $counts = [
            'visits' => $fakeVisitIds->count(),
            'paid_marketing_clicks' => $fakePmClickIds->count(),
            'visit_session_recordings' => $this->countSessionRecordings($fakeVisitIds, $dayStart, $dayEnd, $domainId),
            'detection_logs' => $this->countDetectionLogs($fakeVisitIds, $dayStart, $dayEnd, $domainId),
            'google_ads_ip_exclusions' => $this->countIpExclusions($fakeIps, $dayStart, $dayEnd, $domainId),
            'ip_sessions' => $this->countIpSessions($fakeSessionIds, $dayStart, $dayEnd, $domainId),
        ];

        $this->info("Fake click purge for {$date} ({$tz})");
        if ($domainId) {
            $this->line("Domain filter: #{$domainId}");
        }
        $this->newLine();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-30s %s', $table.':', $count));
        }

        if ($counts['visits'] === 0 && $counts['paid_marketing_clicks'] === 0) {
            $this->warn('Nothing to delete for this date.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run only — no rows deleted. Re-run without --dry-run to purge.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete the rows listed above?', false)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use (
            $fakeVisitIds,
            $fakePmClickIds,
            $fakePmVisitIds,
            $fakeIps,
            $fakeSessionIds,
            $affectedDomainIds,
            $dayStart,
            $dayEnd,
            $domainId,
        ): void {
            $this->deleteSessionRecordings($fakeVisitIds, $dayStart, $dayEnd, $domainId);
            $this->deleteDetectionLogs($fakeVisitIds, $dayStart, $dayEnd, $domainId);
            $this->deletePaidMarketingClicks($fakePmClickIds);
            $this->reconcilePaidMarketingVisits($fakePmVisitIds);
            $this->deleteVisits($fakeVisitIds);
            $this->deleteIpExclusions($fakeIps, $dayStart, $dayEnd, $domainId);
            $this->reconcileIpSessions($fakeSessionIds, $dayStart, $dayEnd, $domainId);
            $this->rebuildAnalyticsHourly($affectedDomainIds, $dayStart, $dayEnd);
        });

        $this->newLine();
        $this->info('Fake click data purged.');

        return self::SUCCESS;
    }

    private function fakeVisitsQuery(Carbon $dayStart, Carbon $dayEnd, ?int $domainId): Builder
    {
        $query = DB::table('visits')
            ->whereBetween('visited_at', [$dayStart, $dayEnd]);

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        $this->applyFakeVisitFilter($query);

        return $query;
    }

    private function fakePaidMarketingClicksQuery(Carbon $dayStart, Carbon $dayEnd, ?int $domainId): Builder
    {
        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereBetween('pc.clicked_at', [$dayStart, $dayEnd])
            ->select('pc.id', 'pc.paid_marketing_visit_id', 'pc.ip', 'pc.paid_id', 'pc.path');

        if ($domainId) {
            $query->where('pv.domain_id', $domainId);
        }

        $query->where(function (Builder $q): void {
            $this->applyFakeTokenFilter($q, 'pc.paid_id');
            $this->applyFakeUrlFilter($q, 'pc.path');
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign')) {
                $q->orWhere('pc.campaign', 'like', 'promotix_%');
            }
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $q->orWhere('pc.campaign_name', 'like', 'promotix_%');
            }
        });

        return $query;
    }

    private function applyFakeVisitFilter(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $this->applyFakeUrlFilter($q, 'url');

            if (Schema::hasColumn('visits', 'gclid')) {
                $this->applyFakeTokenFilter($q, 'gclid');
            }
            if (Schema::hasColumn('visits', 'gbraid')) {
                $this->applyFakeTokenFilter($q, 'gbraid');
            }
            if (Schema::hasColumn('visits', 'wbraid')) {
                $this->applyFakeTokenFilter($q, 'wbraid');
            }
            if (Schema::hasColumn('visits', 'utm_campaign')) {
                $q->orWhere('utm_campaign', 'like', 'promotix_%');
            }
            if (Schema::hasColumn('visits', 'detection_reasons')) {
                $q->orWhere('detection_reasons', 'like', '%demo_faker_data%');
            }
        });
    }

    private function applyFakeUrlFilter(Builder $query, string $column): void
    {
        foreach (['promotix_hit', 'promotix_test', 'fibre_hit_', 'fibre_skip_', 'fibre_work_'] as $needle) {
            $query->orWhere($column, 'like', '%'.$needle.'%');
        }
    }

    private function applyFakeTokenFilter(Builder $query, string $column): void
    {
        $query->orWhere($column, 'like', 'promotix_%')
            ->orWhere($column, 'like', 'fibre_%')
            ->orWhere($column, 'test123')
            ->orWhere($column, 'like', 'test%');
    }

    private function countSessionRecordings(Collection $visitIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): int
    {
        if (! Schema::hasTable('visit_session_recordings')) {
            return 0;
        }

        return $this->sessionRecordingsQuery($visitIds, $dayStart, $dayEnd, $domainId)->count();
    }

    private function sessionRecordingsQuery(Collection $visitIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): Builder
    {
        $query = DB::table('visit_session_recordings')
            ->where(function (Builder $q) use ($visitIds, $dayStart, $dayEnd): void {
                if ($visitIds->isNotEmpty()) {
                    $q->whereIn('visit_id', $visitIds);
                }

                $q->orWhere(function (Builder $inner) use ($dayStart, $dayEnd): void {
                    $inner->whereBetween('created_at', [$dayStart, $dayEnd]);
                    $this->applyFakeUrlFilter($inner, 'page_url');
                });
            });

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        return $query;
    }

    private function countDetectionLogs(Collection $visitIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): int
    {
        if (! Schema::hasTable('detection_logs')) {
            return 0;
        }

        return $this->detectionLogsQuery($visitIds, $dayStart, $dayEnd, $domainId)->count();
    }

    private function detectionLogsQuery(Collection $visitIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): Builder
    {
        $query = DB::table('detection_logs');

        if ($visitIds->isNotEmpty()) {
            $query->whereIn('visit_id', $visitIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        return $query->whereBetween('detected_at', [$dayStart, $dayEnd]);
    }

    private function countIpExclusions(Collection $ips, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): int
    {
        if (! Schema::hasTable('google_ads_ip_exclusions') || $ips->isEmpty()) {
            return 0;
        }

        return $this->ipExclusionsQuery($ips, $dayStart, $dayEnd, $domainId)->count();
    }

    private function ipExclusionsQuery(Collection $ips, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): Builder
    {
        $query = DB::table('google_ads_ip_exclusions')
            ->whereIn('ip', $ips)
            ->whereBetween('created_at', [$dayStart, $dayEnd]);

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        if (Schema::hasColumn('google_ads_ip_exclusions', 'sync_status')) {
            $query->whereIn('sync_status', ['pending', 'failed']);
        }

        return $query;
    }

    private function countIpSessions(Collection $sessionIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): int
    {
        if (! Schema::hasTable('ip_sessions') || $sessionIds->isEmpty()) {
            return 0;
        }

        $query = DB::table('ip_sessions')
            ->whereIn('session_id', $sessionIds)
            ->whereBetween('last_seen_at', [$dayStart, $dayEnd]);

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        return $query->count();
    }

    private function deleteSessionRecordings(Collection $visitIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): void
    {
        if (! Schema::hasTable('visit_session_recordings')) {
            return;
        }

        $deleted = $this->sessionRecordingsQuery($visitIds, $dayStart, $dayEnd, $domainId)->delete();
        $this->line("  deleted visit_session_recordings: {$deleted}");
    }

    private function deleteDetectionLogs(Collection $visitIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): void
    {
        if (! Schema::hasTable('detection_logs') || $visitIds->isEmpty()) {
            return;
        }

        $deleted = $this->detectionLogsQuery($visitIds, $dayStart, $dayEnd, $domainId)->delete();
        $this->line("  deleted detection_logs: {$deleted}");
    }

    private function deletePaidMarketingClicks(Collection $clickIds): void
    {
        if (! Schema::hasTable('paid_marketing_clicks') || $clickIds->isEmpty()) {
            return;
        }

        $deleted = DB::table('paid_marketing_clicks')->whereIn('id', $clickIds)->delete();
        $this->line("  deleted paid_marketing_clicks: {$deleted}");
    }

    private function reconcilePaidMarketingVisits(Collection $visitIds): void
    {
        if (! Schema::hasTable('paid_marketing_visits') || $visitIds->isEmpty()) {
            return;
        }

        $removed = 0;
        $updated = 0;

        foreach ($visitIds as $visitId) {
            $remaining = DB::table('paid_marketing_clicks')
                ->where('paid_marketing_visit_id', $visitId)
                ->count();

            if ($remaining === 0) {
                DB::table('paid_marketing_visits')->where('id', $visitId)->delete();
                $removed++;
                continue;
            }

            $lastClickAt = DB::table('paid_marketing_clicks')
                ->where('paid_marketing_visit_id', $visitId)
                ->max('clicked_at');

            DB::table('paid_marketing_visits')
                ->where('id', $visitId)
                ->update([
                    'visits' => $remaining,
                    'last_click_at' => $lastClickAt,
                    'updated_at' => now(),
                ]);
            $updated++;
        }

        $this->line("  paid_marketing_visits removed: {$removed}, updated: {$updated}");
    }

    private function deleteVisits(Collection $visitIds): void
    {
        if ($visitIds->isEmpty()) {
            return;
        }

        $deleted = DB::table('visits')->whereIn('id', $visitIds)->delete();
        $this->line("  deleted visits: {$deleted}");
    }

    private function deleteIpExclusions(Collection $ips, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions') || $ips->isEmpty()) {
            return;
        }

        $deleted = $this->ipExclusionsQuery($ips, $dayStart, $dayEnd, $domainId)->delete();
        $this->line("  deleted google_ads_ip_exclusions (pending/failed today): {$deleted}");
    }

    private function reconcileIpSessions(Collection $sessionIds, Carbon $dayStart, Carbon $dayEnd, ?int $domainId): void
    {
        if (! Schema::hasTable('ip_sessions') || $sessionIds->isEmpty()) {
            return;
        }

        $query = DB::table('ip_sessions')
            ->whereIn('session_id', $sessionIds)
            ->whereBetween('last_seen_at', [$dayStart, $dayEnd]);

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        $removed = 0;
        $updated = 0;

        foreach ($query->get() as $row) {
            $hits = DB::table('visits')
                ->where('domain_id', $row->domain_id)
                ->where('session_id', $row->session_id)
                ->count();

            if ($hits === 0) {
                DB::table('ip_sessions')->where('id', $row->id)->delete();
                $removed++;
                continue;
            }

            $lastSeen = DB::table('visits')
                ->where('domain_id', $row->domain_id)
                ->where('session_id', $row->session_id)
                ->max('visited_at');

            DB::table('ip_sessions')
                ->where('id', $row->id)
                ->update([
                    'hits' => $hits,
                    'last_seen_at' => $lastSeen,
                    'updated_at' => now(),
                ]);
            $updated++;
        }

        $this->line("  ip_sessions removed: {$removed}, updated: {$updated}");
    }

    private function rebuildAnalyticsHourly(Collection $domainIds, Carbon $dayStart, Carbon $dayEnd): void
    {
        if (! Schema::hasTable('analytics_hourly') || $domainIds->isEmpty()) {
            return;
        }

        $bucketExpr = 'DATE_FORMAT(visited_at, "%Y-%m-%d %H:00:00")';
        $rebuilt = 0;

        foreach ($domainIds as $domainId) {
            $buckets = DB::table('visits')
                ->selectRaw("{$bucketExpr} as bucket_hour, COUNT(*) as total_visits, SUM(CASE WHEN is_paid_traffic = 1 THEN 1 ELSE 0 END) as paid_visits, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid_visits")
                ->where('domain_id', $domainId)
                ->whereBetween('visited_at', [$dayStart, $dayEnd])
                ->groupBy('bucket_hour')
                ->get()
                ->keyBy('bucket_hour');

            $existing = DB::table('analytics_hourly')
                ->where('domain_id', $domainId)
                ->whereBetween('bucket_hour', [$dayStart, $dayEnd])
                ->pluck('bucket_hour')
                ->map(fn ($value) => Carbon::parse($value)->format('Y-m-d H:00:00'));

            foreach ($existing as $bucketHour) {
                if (! $buckets->has($bucketHour)) {
                    DB::table('analytics_hourly')
                        ->where('domain_id', $domainId)
                        ->where('bucket_hour', $bucketHour)
                        ->delete();
                    $rebuilt++;
                }
            }

            foreach ($buckets as $row) {
                DB::table('analytics_hourly')->updateOrInsert(
                    ['domain_id' => $domainId, 'bucket_hour' => $row->bucket_hour],
                    [
                        'total_visits' => (int) $row->total_visits,
                        'paid_visits' => (int) $row->paid_visits,
                        'invalid_visits' => (int) $row->invalid_visits,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $rebuilt++;
            }
        }

        $this->line("  analytics_hourly buckets rebuilt: {$rebuilt}");
    }
}
