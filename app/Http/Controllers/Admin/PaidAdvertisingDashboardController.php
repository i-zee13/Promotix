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

        $paid = 0;
        $invalid = 0;
        $blocked = 0;
        $flagged = 0;
        $uniqueIps = 0;

        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $base = $this->scopedVisitsQuery($request, $domainIds, $from, $to);
            $paid = (clone $base)->count();
            $invalid = (clone $base)->where('is_invalid_traffic', true)->count();
            $uniqueIps = (clone $base)->distinct()->count('ip');

            if (Schema::hasColumn('visits', 'action_taken')) {
                $blocked = (clone $base)->where('action_taken', 'block')->count();
                $flagged = (clone $base)->where('action_taken', 'flag')->count();
            }
        }

        $googleAds = null;
        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->count() === 1) {
            $googleAds = app(GoogleAdsDomainMetricsSync::class)
                ->clickTotalsForDomain((int) $domainIds->first(), $from, $to);

            if ($paid === 0 && ($googleAds['clicks'] ?? 0) > 0) {
                $paid = (int) $googleAds['clicks'];
            }
        }

        return response()->json([
            'paid_visits' => $paid,
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

        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->count() === 1) {
            $sync = app(GoogleAdsDomainMetricsSync::class);
            $domainId = (int) $domainIds->first();
            $range = $sync->effectiveMetricRange($domainId, $from, $to);
            $chartFrom = $range['from'];
            $chartTo = $range['to'];
            $googleByDay = $sync->dailyClicksByDate($domainId, $from, $to);
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
                $paid[] = $visitPaid > 0 ? $visitPaid : $googlePaid;
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
        if ($googleByDay !== null && $domainIds->count() === 1) {
            $googlePrev = app(GoogleAdsDomainMetricsSync::class)
                ->dailyClicksByDate((int) $domainIds->first(), $prevFrom, $prevTo);
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

        $rows = $this->mergeIpAggregates(
            $this->ipRowsFromVisits($request, $domainIds, $from, $to),
            $this->ipRowsFromPaidMarketing($request, $domainIds, $from, $to),
        );

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

            $rows = $this->mergeIpAggregates(
                $this->ipRowsFromVisits($request, $domainIds, $from, $to),
                $this->ipRowsFromPaidMarketing($request, $domainIds, $from, $to),
            );

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
            ->whereBetween('visited_at', [$from, $to])
            ->where('is_paid_traffic', true);

        $path = trim((string) $request->query('path', ''));
        if ($path !== '') {
            $query->where('url', 'like', '%' . $path . '%');
        }

        $campaign = trim((string) $request->query('campaign', ''));
        if ($campaign !== '') {
            $this->applyCampaignNameFilter($query, 'utm_campaign', $campaign);
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
        if (! Schema::hasTable('paid_marketing_visits')) {
            return collect();
        }

        $query = DB::table('paid_marketing_visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('last_click_at', [$from, $to]);

        $campaign = trim((string) $request->query('campaign', ''));
        if ($campaign !== '') {
            $this->applyCampaignNameFilter($query, 'campaign', $campaign);
        }

        return $query
            ->select(
                'ip',
                DB::raw('SUM(visits) as total'),
                DB::raw("SUM(CASE WHEN threat_group IS NOT NULL AND threat_group != '' THEN visits ELSE 0 END) as invalid"),
                DB::raw('MAX(country) as country'),
                DB::raw('MAX(last_click_at) as last_seen'),
                DB::raw("SUM(CASE WHEN threat_group = 'vpn' THEN visits ELSE 0 END) as vpn_hits"),
                DB::raw("SUM(CASE WHEN threat_group = 'data_center' THEN visits ELSE 0 END) as data_center_hits"),
                DB::raw("SUM(CASE WHEN threat_group = 'malicious' THEN visits ELSE 0 END) as malicious_hits"),
                DB::raw('MAX(threat_group) as top_threat')
            )
            ->groupBy('ip')
            ->orderByDesc('total')
            ->limit(500)
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $fromVisits
     * @param  \Illuminate\Support\Collection<int, object>  $fromPaid
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function mergeIpAggregates($fromVisits, $fromPaid)
    {
        $merged = [];

        foreach ($fromVisits->concat($fromPaid) as $row) {
            $ip = (string) $row->ip;
            if ($ip === '') {
                continue;
            }

            if (! isset($merged[$ip])) {
                $merged[$ip] = [
                    'ip' => $ip,
                    'country' => $row->country,
                    'total' => 0,
                    'invalid' => 0,
                    'last_seen' => '',
                    'vpn_hits' => 0,
                    'data_center_hits' => 0,
                    'malicious_hits' => 0,
                    'top_threat' => null,
                ];
            }

            $merged[$ip]['total'] += (int) $row->total;
            $merged[$ip]['invalid'] += (int) $row->invalid;
            $merged[$ip]['vpn_hits'] += (int) ($row->vpn_hits ?? 0);
            $merged[$ip]['data_center_hits'] += (int) ($row->data_center_hits ?? 0);
            $merged[$ip]['malicious_hits'] += (int) ($row->malicious_hits ?? 0);

            if (! $merged[$ip]['country'] && $row->country) {
                $merged[$ip]['country'] = $row->country;
            }

            $lastSeen = (string) ($row->last_seen ?? '');
            if ($lastSeen !== '' && $lastSeen > $merged[$ip]['last_seen']) {
                $merged[$ip]['last_seen'] = $lastSeen;
            }

            if ($row->top_threat && ! $merged[$ip]['top_threat']) {
                $merged[$ip]['top_threat'] = $row->top_threat;
            }
        }

        return collect($merged)
            ->sortByDesc('total')
            ->values();
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
