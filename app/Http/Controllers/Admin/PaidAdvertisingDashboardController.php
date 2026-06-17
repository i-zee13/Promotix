<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAdsConnectionService;
use App\Services\GoogleAdsDomainMetricsSync;
use App\Services\GoogleAdsMetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaidAdvertisingDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->orderBy('hostname')
            ->get(['id', 'hostname', 'paid_marketing_connected', 'source', 'google_ads_account_id']);

        $countryGetStarted = $domains->isEmpty()
            || ! $domains->contains(fn (Domain $d) => $d->hasPaidAdvertisingFromAds());

        return view('paid-marketing.dashboard', [
            'domains' => $domains,
            'countryGetStarted' => $countryGetStarted,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        if ($request->boolean('force_google_sync')) {
            $this->forceGoogleSyncForDomains($request, $domainIds, $from, $to);
        }

        $tagPaid = 0;
        $invalid = 0;
        $blocked = 0;
        $flagged = 0;
        $uniqueIps = 0;

        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $base = $this->scopedVisitsQuery($request, $domainIds, $from, $to);
            $tagPaid = (clone $base)->count();
            $invalid = (clone $base)->where('is_invalid_traffic', true)->count();
            $uniqueIps = (clone $base)->distinct()->count('ip');

            if (Schema::hasColumn('visits', 'action_taken')) {
                $blocked = (clone $base)->where('action_taken', 'block')->count();
                $flagged = (clone $base)->where('action_taken', 'flag')->count();
            }
        }

        $googleAds = null;
        $googleClicks = 0;
        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $googleAds = app(GoogleAdsDomainMetricsSync::class)
                ->clickTotalsForDomains($domainIds, $from, $to);
            $googleClicks = (int) ($googleAds['clicks'] ?? 0);
        }

        $paid = $this->displayPaidTrafficCount($tagPaid, $googleClicks);

        return response()->json([
            'paid_visits' => $paid,
            'tag_paid_visits' => $tagPaid,
            'google_clicks' => $googleClicks,
            'invalid_paid_visits' => $invalid,
            'blocked_paid_visits' => $blocked,
            'flagged_paid_visits' => $flagged,
            'unique_ips' => $uniqueIps,
            'valid_paid_visits' => max(0, $paid - $invalid),
            'google_ads' => $googleAds,
            'window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        if ($request->boolean('force_google_sync')) {
            $this->forceGoogleSyncForDomains($request, $domainIds, $from, $to);
        }

        $fetchRows = function (Carbon $rangeFrom, Carbon $rangeTo) use ($request, $domainIds) {
            if (! Schema::hasTable('visits') || $domainIds->isEmpty()) {
                return collect();
            }

            return $this->scopedVisitsQuery($request, $domainIds, $rangeFrom, $rangeTo)
                ->selectRaw('DATE(visited_at) as day, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        };

        $rows = $fetchRows($from, $to);

        $googleByDay = null;
        $chartFrom = $from;
        $chartTo = $to;

        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $sync = app(GoogleAdsDomainMetricsSync::class);
            if ($domainIds->count() === 1) {
                $domainId = (int) $domainIds->first();
                $range = $sync->effectiveMetricRange($domainId, $from, $to);
                $chartFrom = $range['from'];
                $chartTo = $range['to'];
            }
            $googleByDay = $sync->dailyClicksByDateForDomains($domainIds, $from, $to);
        }

        $buildSeries = function (Carbon $rangeFrom, Carbon $rangeTo, $dayRows, $googleDays) use ($domainIds): array {
            $paid = [];
            $invalid = [];
            $period = $rangeFrom->copy();
            while ($period->lte($rangeTo)) {
                $key = $period->toDateString();
                $row = $dayRows->firstWhere('day', $key);
                $visitPaid = (int) ($row->total ?? 0);
                $googlePaid = (int) ($googleDays?->get($key)?->clicks ?? 0);
                $paid[] = $this->displayPaidTrafficCount($visitPaid, $googlePaid);
                $invalid[] = (int) ($row->invalid ?? 0);
                $period->addDay();
            }

            return ['paid' => $paid, 'invalid' => $invalid];
        };

        $labels = [];
        $period = $chartFrom->copy();
        while ($period->lte($chartTo)) {
            $labels[] = $period->format('D');
            $period->addDay();
        }

        $current = $buildSeries($chartFrom, $chartTo, $rows, $googleByDay);
        $paidSeries = $current['paid'];
        $invalidSeries = $current['invalid'];

        $days = max(1, $chartFrom->diffInDays($chartTo) + 1);
        $prevFrom = $chartFrom->copy()->subDays($days);
        $prevTo = $chartFrom->copy()->subSecond();
        $prevRows = $fetchRows($prevFrom, $prevTo);

        $googlePrev = null;
        if ($googleByDay !== null && $domainIds->isNotEmpty()) {
            $googlePrev = app(GoogleAdsDomainMetricsSync::class)
                ->dailyClicksByDateForDomains($domainIds, $prevFrom, $prevTo);
        }

        $previous = $buildSeries($prevFrom, $prevTo, $prevRows, $googlePrev);
        $lastWeekSeries = $previous['paid'];

        while (count($lastWeekSeries) < count($paidSeries)) {
            array_unshift($lastWeekSeries, 0);
        }
        $lastWeekSeries = array_slice($lastWeekSeries, 0, count($paidSeries));

        return response()->json([
            'labels' => $labels,
            'invalid_daily' => $invalidSeries,
            'datasets' => [
                ['name' => 'This Week', 'values' => $paidSeries, 'color' => '#FFFFFF'],
                ['name' => 'Last Week', 'values' => $lastWeekSeries, 'color' => '#FF4BC1', 'dashed' => true],
            ],
        ]);
    }

    public function blockingActivity(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visits') || ! Schema::hasColumn('visits', 'action_taken')) {
            return response()->json(['labels' => [], 'datasets' => []]);
        }

        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->whereIn('action_taken', ['block', 'flag'])
            ->selectRaw('DATE(visited_at) as day, action_taken, COUNT(*) as total')
            ->groupBy('day', 'action_taken')
            ->orderBy('day')
            ->get();

        $period = $from->copy();
        $labels = [];
        $blockSeries = [];
        $flagSeries = [];
        while ($period->lt($to)) {
            $key = $period->toDateString();
            $labels[] = $period->format('M d');
            $blockSeries[] = (int) ($rows->where('day', $key)->where('action_taken', 'block')->first()->total ?? 0);
            $flagSeries[] = (int) ($rows->where('day', $key)->where('action_taken', 'flag')->first()->total ?? 0);
            $period->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['name' => 'Blocked', 'values' => $blockSeries],
                ['name' => 'Flagged', 'values' => $flagSeries],
            ],
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $domainId = (int) $request->query('domain_id', 0);
        $forceGoogleSync = $request->boolean('force_google_sync');

        if ($domainId > 0) {
            $domain = Domain::query()
                ->where('user_id', $request->user()->id)
                ->forPaidMarketing()
                ->where('id', $domainId)
                ->with('googleAdsAccount.connection')
                ->first();

            $googleRows = $this->resolveGoogleCampaignRowsForDomain($domain, $from, $to, $forceGoogleSync);
            if ($googleRows !== []) {
                return response()->json($googleRows);
            }
        } else {
            $allGoogle = [];
            $domains = Domain::query()
                ->where('user_id', $request->user()->id)
                ->forPaidMarketing()
                ->with('googleAdsAccount.connection')
                ->get();

            foreach ($domains as $domain) {
                foreach ($this->resolveGoogleCampaignRowsForDomain($domain, $from, $to, $forceGoogleSync) as $row) {
                    $allGoogle[] = $row;
                }
            }
            if ($allGoogle !== []) {
                return response()->json(collect($allGoogle)->sortByDesc('clicks')->values());
            }
        }

        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        $domainIds = $this->scopedDomainIds($request);
        $rows = $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->whereNotNull('utm_campaign')
            ->select(
                'utm_campaign',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
            )
            ->groupBy('utm_campaign')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'campaign' => $r->utm_campaign,
            'total' => (int) $r->total,
            'invalid' => (int) $r->invalid,
            'valid' => max(0, (int) $r->total - (int) $r->invalid),
            'source' => 'visits',
        ])->values());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveGoogleCampaignRowsForDomain(?Domain $domain, Carbon $from, Carbon $to, bool $forceGoogleSync = false): array
    {
        if (! $domain?->googleAdsAccount || $domain->googleAdsAccount->is_manager) {
            return [];
        }

        $sync = app(GoogleAdsDomainMetricsSync::class);

        if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
            if ($forceGoogleSync || $sync->shouldRefresh($domain, $from, $to)) {
                $sync->syncDomain($domain->fresh(), $from, $to)['saved'] ?? 0;
            }

            $dbRows = $sync->aggregatedCampaignRowsWithFallback($domain->id, $from, $to);
            if ($dbRows !== []) {
                return $dbRows;
            }
        }

        $liveRows = $this->googleAdsCampaignRows($domain->googleAdsAccount, $from, $to, $domain->hostname);
        if ($liveRows !== [] && Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $sync->syncDomain($domain->fresh(), $from, $to)['saved'] ?? 0;
            $dbRows = $sync->aggregatedCampaignRowsWithFallback($domain->id, $from, $to);

            return $dbRows !== [] ? $dbRows : $liveRows;
        }

        return $liveRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function googleAdsCampaignRows(GoogleAdsAccount $account, Carbon $from, Carbon $to, ?string $hostname = null): array
    {
        $connection = $account->connection;
        if (! $connection) {
            return [];
        }

        $api = app(GoogleAdsConnectionService::class);
        $headers = $api->apiHeaders($connection);
        if (! $headers) {
            return [];
        }

        $loginId = $account->manager_customer_id ?: $api->loginCustomerId();
        if ($loginId !== '') {
            $headers['login-customer-id'] = $loginId;
        }

        $version = $api->apiVersions()[0] ?? 'v24';

        return app(GoogleAdsMetricsService::class)->campaignMetrics(
            $account,
            $version,
            $headers,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $hostname
        );
    }

    public function keywords(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->whereNotNull('utm_term')
            ->select(
                'utm_term',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
            )
            ->groupBy('utm_term')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'keyword' => $r->utm_term,
            'total' => (int) $r->total,
            'invalid' => (int) $r->invalid,
        ])->values());
    }

    public function countries(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->whereNotNull('country')
            ->select(
                'country',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
            )
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'country' => $r->country,
            'total' => (int) $r->total,
            'invalid' => (int) $r->invalid,
        ])->values());
    }

    public function ips(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        if ($domainIds->isEmpty()) {
            return response()->json([]);
        }

        $rows = $this->resolveIpRows($request, $domainIds, $from, $to);

        return response()->json($rows->take(50)->values());
    }

    public function exportIpsCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $filename = 'paid-marketing-ips-' . now()->format('YmdHis') . '.csv';

        return response()->streamDownload(function () use ($request, $domainIds, $from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['IP Address', 'Country', 'Invalid', 'Total', 'Bot Detect', 'VPN Hits', 'Data Center Hits', 'Malicious Hits', 'Last Click']);

            if ($domainIds->isEmpty()
                || (! Schema::hasTable('visits') && ! Schema::hasTable('paid_marketing_visits'))) {
                fclose($handle);

                return;
            }

            $rows = $this->resolveIpRows($request, $domainIds, $from, $to);

            foreach ($rows->take(5000) as $r) {
                fputcsv($handle, [
                    $r['ip'],
                    $r['country'],
                    $r['invalid'],
                    $r['total'],
                    $r['top_threat'],
                    $r['vpn_hits'],
                    $r['data_center_hits'],
                    $r['malicious_hits'],
                    $r['last_seen'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function heatmap(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visits')) {
            return response()->json(['matrix' => [], 'days' => [], 'hours' => []]);
        }

        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->selectRaw('DAYOFWEEK(visited_at) as dow, HOUR(visited_at) as hr, COUNT(*) as total')
            ->groupBy('dow', 'hr')
            ->get();

        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $hours = range(0, 23);
        $matrix = [];

        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $matrix[$d][$h] = 0;
            }
        }

        foreach ($rows as $r) {
            $d = ((int) $r->dow) - 1;
            $h = (int) $r->hr;
            if ($d >= 0 && $d < 7) {
                $matrix[$d][$h] = (int) $r->total;
            }
        }

        return response()->json([
            'days' => $days,
            'hours' => $hours,
            'matrix' => $matrix,
        ]);
    }

    private function forceGoogleSyncForDomains(Request $request, $domainIds, Carbon $from, Carbon $to): void
    {
        if (! Schema::hasTable('google_ads_campaign_daily_metrics') || $domainIds->isEmpty()) {
            return;
        }

        $sync = app(GoogleAdsDomainMetricsSync::class);
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->whereIn('id', $domainIds)
            ->with('googleAdsAccount')
            ->get();

        foreach ($domains as $domain) {
            if (! $domain->googleAdsAccount || $domain->googleAdsAccount->is_manager) {
                continue;
            }
            $sync->syncDomain($domain, $from, $to);
        }
    }

    private function scopedDomainIds(Request $request)
    {
        $userDomainIds = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->pluck('id');

        if ($id = (int) $request->query('domain_id', 0)) {
            return $userDomainIds->filter(fn ($v) => (int) $v === $id)->values();
        }

        return $userDomainIds;
    }

    private function scopedVisitsQuery(Request $request, $domainIds, Carbon $from, Carbon $to)
    {
        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to]);

        $query->where(function ($paid): void {
            $paid->where('is_paid_traffic', true);

            if (Schema::hasColumn('visits', 'gclid')) {
                $paid->orWhere(function ($gclid): void {
                    $gclid->whereNotNull('gclid')->where('gclid', '!=', '');
                });
            }
            if (Schema::hasColumn('visits', 'gbraid')) {
                $paid->orWhere(function ($gbraid): void {
                    $gbraid->whereNotNull('gbraid')->where('gbraid', '!=', '');
                });
            }
            if (Schema::hasColumn('visits', 'wbraid')) {
                $paid->orWhere(function ($wbraid): void {
                    $wbraid->whereNotNull('wbraid')->where('wbraid', '!=', '');
                });
            }
        });

        $path = trim((string) $request->query('path', ''));
        if ($path !== '') {
            $query->where('url', 'like', '%' . $path . '%');
        }

        if ($this->hasCampaignFilter($request)) {
            $gclidColumn = Schema::hasColumn('visits', 'gclid') ? 'gclid' : null;
            $this->applyCampaignAttributionFilter(
                $query,
                $request,
                $domainIds,
                $from,
                $to,
                'utm_campaign',
                'ip',
                $gclidColumn,
                'visited_at',
            );
        }

        return $query;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ipRowsFromVisits(Request $request, $domainIds, Carbon $from, Carbon $to)
    {
        if (! Schema::hasTable('visits')) {
            return collect();
        }

        return $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->select(
                'ip',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'),
                DB::raw('MAX(country) as country'),
                DB::raw('MAX(visited_at) as last_seen'),
                DB::raw("SUM(CASE WHEN threat_group = 'vpn' THEN 1 ELSE 0 END) as vpn_hits"),
                DB::raw("SUM(CASE WHEN threat_group = 'data_center' THEN 1 ELSE 0 END) as data_center_hits"),
                DB::raw("SUM(CASE WHEN threat_group = 'malicious' THEN 1 ELSE 0 END) as malicious_hits"),
                DB::raw('MAX(threat_group) as top_threat')
            )
            ->groupBy('ip')
            ->orderByDesc('total')
            ->limit(500)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ipRowsFromPaidMarketing(Request $request, $domainIds, Carbon $from, Carbon $to)
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits')) {
            return collect();
        }

        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereIn('pv.domain_id', $domainIds)
            ->whereBetween('pc.clicked_at', [$from, $to]);

        $this->applyPaidTrafficOnlyFilter($query, 'pc');

        if ($this->hasCampaignFilter($request)) {
            $this->applyCampaignAttributionFilter(
                $query,
                $request,
                $domainIds,
                $from,
                $to,
                'pc.campaign',
                'pc.ip',
                'pc.paid_id',
                'pc.clicked_at',
            );
        }

        return $query
            ->select(
                'pc.ip as ip',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != '' THEN 1 ELSE 0 END) as invalid"),
                DB::raw('MAX(pc.country) as country'),
                DB::raw('MAX(pc.clicked_at) as last_seen'),
                DB::raw("SUM(CASE WHEN pc.threat_group = 'vpn' THEN 1 ELSE 0 END) as vpn_hits"),
                DB::raw("SUM(CASE WHEN pc.threat_group = 'data_center' THEN 1 ELSE 0 END) as data_center_hits"),
                DB::raw("SUM(CASE WHEN pc.threat_group = 'malicious' THEN 1 ELSE 0 END) as malicious_hits"),
                DB::raw('MAX(pc.threat_group) as top_threat')
            )
            ->groupBy('pc.ip')
            ->orderByDesc('total')
            ->limit(500)
            ->get();
    }

    /**
     * Paid IPs only (is_paid_traffic = 1): visits table first, legacy clicks fallback.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function resolveIpRows(Request $request, $domainIds, Carbon $from, Carbon $to)
    {
        $rows = $this->ipRowsFromVisits($request, $domainIds, $from, $to);
        if ($rows->isEmpty()) {
            $rows = $this->ipRowsFromPaidMarketing($request, $domainIds, $from, $to);
        }

        return $this->formatIpRows($rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function formatIpRows($rows)
    {
        return $rows->map(fn ($row) => [
            'ip' => (string) ($row->ip ?? ''),
            'country' => $row->country ?? null,
            'total' => (int) ($row->total ?? 0),
            'invalid' => (int) ($row->invalid ?? 0),
            'valid' => max(0, (int) ($row->total ?? 0) - (int) ($row->invalid ?? 0)),
            'last_seen' => $row->last_seen ?? null,
            'vpn_hits' => (int) ($row->vpn_hits ?? 0),
            'data_center_hits' => (int) ($row->data_center_hits ?? 0),
            'malicious_hits' => (int) ($row->malicious_hits ?? 0),
            'top_threat' => $row->top_threat ?? null,
        ])->values();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyPaidTrafficOnlyFilter($query, string $clickAlias = 'pc'): void
    {
        $paidIdColumn = "{$clickAlias}.paid_id";
        $campaignColumn = "{$clickAlias}.campaign";

        $query->where(function ($paid) use ($paidIdColumn, $campaignColumn): void {
            $paid->where(function ($gclid) use ($paidIdColumn): void {
                $gclid->whereNotNull($paidIdColumn)->where($paidIdColumn, '!=', '');
            })->orWhere(function ($utm) use ($campaignColumn): void {
                $utm->whereNotNull($campaignColumn)->where($campaignColumn, '!=', '');
            });
        });
    }

    private function applyCampaignNameFilter($query, string $column, string $campaign): void
    {
        $campaign = trim($campaign);
        if ($campaign === '') {
            return;
        }

        $query->where(function ($inner) use ($column, $campaign): void {
            $inner->where($column, $campaign)
                ->orWhere($column, 'like', '%' . $campaign . '%')
                ->orWhereRaw('? LIKE CONCAT("%", ' . $column . ', "%")', [$campaign]);
        });
    }

    private function applySmartCampaignFilter($query, string $column, Request $request): void
    {
        $campaign = trim((string) $request->query('campaign', ''));
        if ($campaign === '') {
            return;
        }

        $query->where(function ($inner) use ($column, $request): void {
            $this->applySmartCampaignFilterConditions($inner, $column, $request);
        });
    }

    private function applySmartCampaignFilterConditions($query, string $column, Request $request): void
    {
        $campaign = trim((string) $request->query('campaign', ''));
        if ($campaign === '') {
            return;
        }

        $domainId = (int) $request->query('domain_id', 0);
        $aliases = $domainId > 0 ? $this->resolveTrackedCampaignAliases($domainId, $campaign) : [];
        $tokens = $this->campaignMatchTokens($campaign);

        if ($aliases !== []) {
            $query->whereIn($column, $aliases);
        }

        $query->orWhere($column, $campaign)
            ->orWhere($column, 'like', '%' . $campaign . '%')
            ->orWhereRaw('? LIKE CONCAT("%", ' . $column . ', "%")', [$campaign]);

        foreach ($tokens as $token) {
            if (strlen($token) >= 2) {
                $query->orWhere($column, 'like', '%' . $token . '%');
            }
        }
    }

    private function hasCampaignFilter(Request $request): bool
    {
        return trim((string) $request->query('campaign', '')) !== ''
            || preg_replace('/\D+/', '', (string) $request->query('campaign_id', '')) !== '';
    }

    /**
     * Match campaign by utm_campaign and/or Google click gclids (when URL has no utm).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  \Illuminate\Support\Collection<int, int>  $domainIds
     */
    private function applyCampaignAttributionFilter(
        $query,
        Request $request,
        $domainIds,
        Carbon $from,
        Carbon $to,
        string $utmColumn,
        string $ipColumn = 'ip',
        ?string $gclidColumn = 'gclid',
        string $visitedAtColumn = 'visited_at',
    ): void {
        $campaign = trim((string) $request->query('campaign', ''));
        $domainId = $domainIds->count() === 1 ? (int) $domainIds->first() : 0;
        $campaignId = $domainId > 0 ? $this->resolveCampaignId($request, $domainId) : '';
        $gclids = $this->resolveCampaignGclids($request, $domainIds, $from, $to, $campaignId);
        $activeDates = $domainId > 0 && $campaignId !== ''
            ? $this->resolveCampaignActiveDates($domainId, $campaignId, $from, $to)
            : [];

        $query->where(function ($outer) use (
            $utmColumn,
            $ipColumn,
            $gclidColumn,
            $visitedAtColumn,
            $request,
            $campaign,
            $gclids,
            $activeDates,
            $domainIds,
            $from,
            $to,
        ): void {
            if ($campaign !== '') {
                $outer->where(function ($utm) use ($utmColumn, $request): void {
                    $this->applySmartCampaignFilterConditions($utm, $utmColumn, $request);
                });
            }

            if ($gclids !== [] && $gclidColumn !== null) {
                $outer->orWhereIn($gclidColumn, $gclids);
            }

            if ($gclids !== [] && Schema::hasTable('paid_marketing_clicks') && Schema::hasTable('paid_marketing_visits')) {
                $outer->orWhereExists(function ($sub) use ($gclids, $domainIds, $from, $to, $ipColumn): void {
                    $sub->selectRaw('1')
                        ->from('paid_marketing_clicks as pc_attr')
                        ->join('paid_marketing_visits as pv_attr', 'pv_attr.id', '=', 'pc_attr.paid_marketing_visit_id')
                        ->whereIn('pv_attr.domain_id', $domainIds)
                        ->whereColumn('pv_attr.ip', $ipColumn)
                        ->whereBetween('pc_attr.clicked_at', [$from, $to])
                        ->whereIn('pc_attr.paid_id', $gclids);
                });
            }

            if ($activeDates !== []) {
                $outer->orWhere(function ($dates) use (
                    $utmColumn,
                    $gclidColumn,
                    $visitedAtColumn,
                    $ipColumn,
                    $activeDates,
                    $domainIds,
                    $from,
                    $to,
                ): void {
                    $dates->whereIn(DB::raw('DATE(' . $visitedAtColumn . ')'), $activeDates)
                        ->where(function ($unattrib) use ($utmColumn): void {
                            $unattrib->whereNull($utmColumn)->orWhere($utmColumn, '');
                        });

                    if ($gclidColumn !== null) {
                        $dates->where(function ($paid) use ($gclidColumn, $domainIds, $from, $to, $ipColumn): void {
                            $paid->whereNotNull($gclidColumn)->where($gclidColumn, '!=', '');

                            if (Schema::hasTable('paid_marketing_clicks') && Schema::hasTable('paid_marketing_visits')) {
                                $paid->orWhereExists(function ($sub) use ($domainIds, $from, $to, $ipColumn): void {
                                    $sub->selectRaw('1')
                                        ->from('paid_marketing_clicks as pc_day')
                                        ->join('paid_marketing_visits as pv_day', 'pv_day.id', '=', 'pc_day.paid_marketing_visit_id')
                                        ->whereIn('pv_day.domain_id', $domainIds)
                                        ->whereColumn('pv_day.ip', $ipColumn)
                                        ->whereBetween('pc_day.clicked_at', [$from, $to])
                                        ->whereNotNull('pc_day.paid_id')
                                        ->where('pc_day.paid_id', '!=', '');
                                });
                            }
                        });
                    }
                });
            }
        });
    }

    private function resolveCampaignId(Request $request, int $domainId): string
    {
        $campaignId = preg_replace('/\D+/', '', (string) $request->query('campaign_id', ''));
        if ($campaignId !== '') {
            return $campaignId;
        }

        $campaignName = trim((string) $request->query('campaign', ''));
        if ($campaignName === '' || ! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return '';
        }

        $id = DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->where('campaign_name', $campaignName)
            ->orderByDesc('metric_date')
            ->value('campaign_id');

        if ($id) {
            return preg_replace('/\D+/', '', (string) $id);
        }

        $tokens = $this->campaignMatchTokens($campaignName);
        if ($tokens === []) {
            return '';
        }

        $row = DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->where(function ($q) use ($tokens): void {
                foreach ($tokens as $token) {
                    $q->orWhere('campaign_name', 'like', '%' . $token . '%');
                }
            })
            ->orderByDesc('metric_date')
            ->value('campaign_id');

        return $row ? preg_replace('/\D+/', '', (string) $row) : '';
    }

    /**
     * @return list<string>
     */
    private function resolveCampaignActiveDates(int $domainId, string $campaignId, Carbon $from, Carbon $to): array
    {
        if ($campaignId === '' || ! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return [];
        }

        return DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->where('campaign_id', $campaignId)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->where('clicks', '>', 0)
            ->distinct()
            ->pluck('metric_date')
            ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $domainIds
     * @return list<string>
     */
    private function resolveCampaignGclids(Request $request, $domainIds, Carbon $from, Carbon $to, ?string $campaignId = null): array
    {
        if ($domainIds->count() !== 1) {
            return [];
        }

        $domainId = (int) $domainIds->first();
        $campaignId = $campaignId ?? $this->resolveCampaignId($request, $domainId);

        if ($campaignId === '') {
            return [];
        }

        $domain = Domain::query()
            ->with('googleAdsAccount.connection')
            ->find($domainId);

        $account = $domain?->googleAdsAccount;
        if (! $account?->connection || $account->is_manager) {
            return [];
        }

        $api = app(GoogleAdsConnectionService::class);
        $headers = $api->apiHeaders($account->connection);
        if (! $headers) {
            return [];
        }

        $loginId = $account->manager_customer_id ?: $api->loginCustomerId();
        if ($loginId !== '') {
            $headers['login-customer-id'] = $loginId;
        }

        return app(GoogleAdsMetricsService::class)->gclidsForCampaign(
            $account,
            $api->apiVersions()[0] ?? 'v24',
            $headers,
            $campaignId,
            $from->toDateString(),
            $to->toDateString(),
        );
    }

    /**
     * @return list<string>
     */
    private function resolveTrackedCampaignAliases(int $domainId, string $googleCampaign): array
    {
        $candidates = collect();

        if (Schema::hasTable('visits')) {
            $candidates = $candidates->merge(
                DB::table('visits')
                    ->where('domain_id', $domainId)
                    ->whereNotNull('utm_campaign')
                    ->where('utm_campaign', '!=', '')
                    ->distinct()
                    ->pluck('utm_campaign')
            );
        }

        if (Schema::hasTable('paid_marketing_visits')) {
            $candidates = $candidates->merge(
                DB::table('paid_marketing_visits')
                    ->where('domain_id', $domainId)
                    ->whereNotNull('campaign')
                    ->where('campaign', '!=', '')
                    ->distinct()
                    ->pluck('campaign')
            );
        }

        if (Schema::hasTable('paid_marketing_clicks') && Schema::hasTable('paid_marketing_visits')) {
            $candidates = $candidates->merge(
                DB::table('paid_marketing_clicks as pc')
                    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
                    ->where('pv.domain_id', $domainId)
                    ->whereNotNull('pc.campaign')
                    ->where('pc.campaign', '!=', '')
                    ->distinct()
                    ->pluck('pc.campaign')
            );
        }

        $googleLower = strtolower($googleCampaign);
        $tokens = $this->campaignMatchTokens($googleCampaign);
        $matches = [];

        foreach ($candidates->unique()->filter() as $candidate) {
            $value = (string) $candidate;
            $lower = strtolower($value);

            if ($lower === $googleLower) {
                $matches[] = $value;
                continue;
            }

            if (str_contains($googleLower, $lower) || str_contains($lower, $googleLower)) {
                $matches[] = $value;
                continue;
            }

            foreach ($tokens as $token) {
                if (strlen($token) >= 3 && str_contains($lower, $token)) {
                    $matches[] = $value;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return list<string>
     */
    private function campaignMatchTokens(string $campaign): array
    {
        $normalized = strtolower($campaign);
        $normalized = preg_replace('/\bdigital\s+promotix\b/i', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        $stopWords = ['mix', 'the', 'and', 'for', 'maximize', 'click', 'calls', 'leads', 'digital', 'promotix'];

        $tokens = array_values(array_filter(
            $parts,
            fn (string $part) => strlen($part) >= 2 && ! in_array($part, $stopWords, true)
        ));

        $primary = strtolower(trim(explode('-', $campaign, 2)[0] ?? ''));
        $primary = preg_replace('/[^a-z0-9]+/', '', $primary) ?? '';
        if ($primary !== '' && strlen($primary) >= 2 && ! in_array($primary, $stopWords, true)) {
            array_unshift($tokens, $primary);
        }

        return array_values(array_unique($tokens));
    }

    private function dateRange(Request $request): array
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->subDays(6)->startOfDay();

        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * Google Ads clicks (API) plus tagged on-site paid visits from the PromoTix tag.
     */
    private function displayPaidTrafficCount(int $tagPaid, int $googleClicks): int
    {
        $tagPaid = max(0, $tagPaid);
        $googleClicks = max(0, $googleClicks);

        if ($googleClicks > 0) {
            return $googleClicks + $tagPaid;
        }

        return $tagPaid;
    }

    private function emptySummary(): array
    {
        return [
            'paid_visits' => 0,
            'invalid_paid_visits' => 0,
            'blocked_paid_visits' => 0,
            'flagged_paid_visits' => 0,
            'valid_paid_visits' => 0,
        ];
    }
}
