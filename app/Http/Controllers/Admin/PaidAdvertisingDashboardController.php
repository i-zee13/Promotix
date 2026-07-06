<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAdsConnectionService;
use App\Services\GoogleAdsDomainMetricsSync;
use App\Services\GoogleAdsMetricsService;
use App\Services\IpIntel\IpFraudEvaluator;
use App\Support\GoogleClickAttribution;
use App\Support\GoogleVerifiedPaidTraffic;
use App\Support\UserTimezone;
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
            ->with('googleAdsAccount')
            ->orderBy('hostname')
            ->get(['id', 'hostname', 'paid_marketing_connected', 'source', 'google_ads_account_id']);

        $countryGetStarted = $domains->isEmpty()
            || ! $domains->contains(fn (Domain $d) => $d->hasPaidAdvertisingFromAds());

        return view('paid-marketing.dashboard', [
            'domains' => $domains,
            'domainCatalog' => UserTimezone::domainCatalog($domains),
            'countryGetStarted' => $countryGetStarted,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $reportingTz = $this->reportingTimezone($request, $domainIds);
        $googleTz = $this->resolveGoogleTimezone($request, $domainIds);
        $domains = $this->scopedDomains($request, $domainIds);

        $this->syncGoogleMetricsForDomains(
            $request,
            $domains,
            $metricFrom,
            $metricTo,
            $request->boolean('force_google_sync')
        );

        $tagPaid = 0;
        $verifiedPaid = 0;
        $verifiedValidPaid = 0;
        $unverifiedPaid = 0;
        $invalid = 0;
        $blocked = 0;
        $flagged = 0;
        $uniqueIps = 0;

        $verificationLookup = app(GoogleVerifiedPaidTraffic::class)->buildLookup(
            $domainIds,
            $metricFrom,
            $metricTo,
            $request->user(),
            $reportingTz,
            $domains,
        );

        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $base = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo);
            $tagPaid = (clone $base)->count();
            $invalid = (clone $base)->where('is_invalid_traffic', true)->count();
            $uniqueIps = (clone $base)->distinct()->count('ip');

            if (Schema::hasColumn('visits', 'action_taken')) {
                $blocked = (clone $base)->where('action_taken', 'block')->count();
                $flagged = (clone $base)->where('action_taken', 'flag')->count();
            }

            $visitRows = (clone $base)->get([
                'domain_id',
                'url',
                'google_campaign_id',
                'visited_at',
                'is_invalid_traffic',
            ]);
            if ($visitRows->isNotEmpty()) {
                $verificationCounts = app(GoogleVerifiedPaidTraffic::class)->countRows(
                    $visitRows,
                    $verificationLookup,
                    $reportingTz,
                );
                $verifiedPaid = (int) ($verificationCounts['verified'] ?? 0);
                $verifiedValidPaid = (int) ($verificationCounts['verified_valid'] ?? 0);
                $unverifiedPaid = (int) ($verificationCounts['unverified'] ?? 0);
            }
        }

        if ($domainIds->isNotEmpty()) {
            $legacy = $this->paidMarketingTrafficStats($request, $domainIds, $metricFrom, $metricTo);
            $tagPaid = max($tagPaid, (int) ($legacy['total'] ?? 0));
            if ($invalid === 0) {
                $invalid = (int) ($legacy['invalid'] ?? 0);
            }
            $uniqueIps = max($uniqueIps, (int) ($legacy['unique_ips'] ?? 0));

            if ($verifiedPaid === 0 && $unverifiedPaid === 0 && (int) ($legacy['total'] ?? 0) > 0) {
                $legacyRows = $this->paidMarketingRowsForVerification($request, $domainIds, $metricFrom, $metricTo);
                $verificationCounts = app(GoogleVerifiedPaidTraffic::class)->countRows(
                    $legacyRows,
                    $verificationLookup,
                    $reportingTz,
                );
                $verifiedPaid = (int) ($verificationCounts['verified'] ?? 0);
                $verifiedValidPaid = (int) ($verificationCounts['verified_valid'] ?? 0);
                $unverifiedPaid = (int) ($verificationCounts['unverified'] ?? 0);
            }
        }

        $googleAds = null;
        $googleClicks = 0;
        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $googleAds = app(GoogleAdsDomainMetricsSync::class)
                ->clickTotalsForDomainsReporting($domainIds, $metricFrom, $metricTo, $reportingTz, $domains);
            $googleClicks = (int) ($googleAds['clicks'] ?? 0);
        }

        $paid = $this->displayPaidTrafficCount($verifiedValidPaid, $tagPaid, $googleClicks);
        $validTagPaid = max(0, $tagPaid - $invalid);
        $totalClickCount = $googleClicks;
        $tagCapturePct = $googleClicks > 0
            ? (int) round(min(100, ($verifiedValidPaid / $googleClicks) * 100))
            : ($verifiedValidPaid > 0 ? 100 : 0);

        $selectedDomain = $request->filled('domain_id') && $domains->count() === 1
            ? $domains->first()
            : null;

        return response()->json([
            'paid_visits' => $paid,
            'verified_paid_visits' => $verifiedPaid,
            'verified_valid_paid_visits' => $verifiedValidPaid,
            'unverified_paid_visits' => $unverifiedPaid,
            'tag_paid_visits' => $tagPaid,
            'google_clicks' => $googleClicks,
            'total_click_count' => $totalClickCount,
            'tag_capture_pct' => $tagCapturePct,
            'tag_gap_warning' => $googleClicks > 0 && $verifiedValidPaid < (int) floor($googleClicks * 0.5),
            'invalid_paid_visits' => $invalid,
            'blocked_paid_visits' => $blocked,
            'flagged_paid_visits' => $flagged,
            'unique_ips' => $uniqueIps,
            'valid_paid_visits' => $validTagPaid,
            'google_ads' => $googleAds,
            'timezone_context' => UserTimezone::dashboardContext(
                $request->user(),
                $googleTz,
                $metricFrom,
                $metricTo,
                $selectedDomain,
            ),
            'window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $userTz = $this->reportingTimezone($request, $domainIds);
        $domains = $this->scopedDomains($request, $domainIds);

        $this->syncGoogleMetricsForDomains(
            $request,
            $domains,
            $metricFrom,
            $metricTo,
            $request->boolean('force_google_sync')
        );

        $fetchRows = function (string $fromDate, string $toDate) use ($request, $domainIds, $userTz) {
            $rows = collect();
            if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
                $dayExpr = UserTimezone::localDateSql('visited_at', $request->user(), $userTz);
                $rows = $this->scopedVisitsQuery($request, $domainIds, $fromDate, $toDate)
                    ->selectRaw("{$dayExpr} as day, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid")
                    ->groupBy('day')
                    ->orderBy('day')
                    ->get();
            }

            if ($rows->isEmpty() && $domainIds->isNotEmpty()) {
                $rows = $this->paidMarketingDailyTrendRows($request, $domainIds, $fromDate, $toDate);
            }

            return $rows;
        };

        $chartFrom = Carbon::parse($metricFrom, $userTz)->startOfDay();
        $chartTo = Carbon::parse($metricTo, $userTz)->endOfDay();
        $rows = $fetchRows($metricFrom, $metricTo);

        $googleByDay = null;

        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $sync = app(GoogleAdsDomainMetricsSync::class);
            $googleByDay = $sync->dailyClicksByDateForDomainsReporting($domainIds, $metricFrom, $metricTo, $userTz, $domains);
        }

        $buildSeries = function (string $rangeFromDate, string $rangeToDate, $dayRows, $googleDays) use ($userTz): array {
            $paid = [];
            $invalid = [];
            $period = Carbon::parse($rangeFromDate, $userTz)->startOfDay();
            $end = Carbon::parse($rangeToDate, $userTz)->startOfDay();
            while ($period->lte($end)) {
                $key = $period->toDateString();
                $row = $dayRows->firstWhere('day', $key);
                $visitPaid = (int) ($row->total ?? 0);
                $invalidDay = (int) ($row->invalid ?? 0);
                $googlePaid = (int) ($googleDays?->get($key)?->clicks ?? 0);
                $validVisitPaid = max(0, $visitPaid - $invalidDay);
                $estimatedVerified = $googlePaid > 0 ? $validVisitPaid : 0;
                $paid[] = $this->displayPaidTrafficCount($estimatedVerified, $visitPaid, $googlePaid);
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

        $current = $buildSeries($metricFrom, $metricTo, $rows, $googleByDay);
        $paidSeries = $current['paid'];
        $invalidSeries = $current['invalid'];

        $days = max(1, $chartFrom->diffInDays($chartTo) + 1);
        $prevMetricFrom = Carbon::parse($metricFrom, $userTz)->subDays($days)->toDateString();
        $prevMetricTo = Carbon::parse($metricFrom, $userTz)->subDay()->toDateString();
        $prevRows = $fetchRows($prevMetricFrom, $prevMetricTo);

        $googlePrev = null;
        if ($googleByDay !== null && $domainIds->isNotEmpty()) {
            $googlePrev = app(GoogleAdsDomainMetricsSync::class)
                ->dailyClicksByDateForDomains($domainIds, $prevMetricFrom, $prevMetricTo);
        }

        $previous = $buildSeries($prevMetricFrom, $prevMetricTo, $prevRows, $googlePrev);
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

        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $userTz = $this->reportingTimezone($request, $domainIds);
        $dayExpr = UserTimezone::localDateSql('visited_at', $request->user(), $userTz);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
            ->whereIn('action_taken', ['block', 'flag'])
            ->selectRaw("{$dayExpr} as day, action_taken, COUNT(*) as total")
            ->groupBy('day', 'action_taken')
            ->orderBy('day')
            ->get();

        $period = Carbon::parse($metricFrom, $userTz)->startOfDay();
        $end = Carbon::parse($metricTo, $userTz)->startOfDay();
        $labels = [];
        $blockSeries = [];
        $flagSeries = [];
        while ($period->lte($end)) {
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
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainId = (int) $request->query('domain_id', 0);
        $forceGoogleSync = $request->boolean('force_google_sync');
        $merged = collect();

        if ($domainId > 0) {
            $domain = Domain::query()
                ->where('user_id', $request->user()->id)
                ->forPaidMarketing()
                ->where('id', $domainId)
                ->with('googleAdsAccount.connection')
                ->first();

            $merged = $merged->merge($this->resolveGoogleCampaignRowsForDomain($domain, $metricFrom, $metricTo, $forceGoogleSync));
            $merged = $merged->merge($this->metricCampaignRows($domainId, $metricFrom, $metricTo));
        } else {
            $domains = Domain::query()
                ->where('user_id', $request->user()->id)
                ->forPaidMarketing()
                ->with('googleAdsAccount.connection')
                ->get();

            foreach ($domains as $domain) {
                $merged = $merged->merge($this->resolveGoogleCampaignRowsForDomain($domain, $metricFrom, $metricTo, $forceGoogleSync));
                $merged = $merged->merge($this->metricCampaignRows($domain->id, $metricFrom, $metricTo));
            }
        }

        $domainIds = $this->scopedDomainIds($request);
        $merged = $merged
            ->merge($this->visitCampaignRows($request, $domainIds, $metricFrom, $metricTo))
            ->merge($this->paidMarketingCampaignRows($domainIds, $metricFrom, $metricTo, $request->user(), $this->reportingTimezone($request, $domainIds)));

        $rows = $merged
            ->filter(fn ($row) => filled(is_array($row) ? ($row['campaign'] ?? null) : null))
            ->groupBy(fn ($row) => (string) $row['campaign'])
            ->map(function ($group, $campaign) {
                $best = collect($group)->sortByDesc(fn ($row) => (int) ($row['total'] ?? $row['clicks'] ?? 0))->first();

                return [
                    'campaign' => $campaign,
                    'campaign_id' => $best['campaign_id'] ?? null,
                    'total' => (int) ($best['total'] ?? $best['clicks'] ?? 0),
                    'invalid' => (int) ($best['invalid'] ?? 0),
                    'valid' => (int) ($best['valid'] ?? max(0, (int) ($best['total'] ?? $best['clicks'] ?? 0) - (int) ($best['invalid'] ?? 0))),
                    'source' => $best['source'] ?? 'merged',
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $untaggedDomains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->where('tag_connected', false)
            ->when($domainId > 0, fn ($q) => $q->where('id', $domainId))
            ->orderBy('hostname')
            ->get(['id', 'hostname'])
            ->map(fn (Domain $d) => [
                'id' => $d->id,
                'hostname' => $d->hostname,
                'setup_url' => route('domains.setup', $d),
            ])
            ->values()
            ->all();

        return response()->json([
            'campaigns' => $rows,
            'untagged_domains' => $untaggedDomains,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function metricCampaignRows(int $domainId, string $fromDate, string $toDate): array
    {
        if (! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return [];
        }

        $sync = app(GoogleAdsDomainMetricsSync::class);
        $range = $sync->effectiveMetricRange($domainId, $fromDate, $toDate);

        return DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$range['from'], $range['to']])
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->selectRaw('campaign_id, MAX(campaign_name) as campaign, SUM(clicks) as total')
            ->groupBy('campaign_id')
            ->orderByDesc('total')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'campaign_id' => (string) $row->campaign_id,
                'campaign' => (string) $row->campaign,
                'total' => (int) $row->total,
                'invalid' => 0,
                'valid' => (int) $row->total,
                'source' => 'google_ads_db',
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function visitCampaignRows(Request $request, $domainIds, string $fromDate, string $toDate): array
    {
        if (! Schema::hasTable('visits') || collect($domainIds)->isEmpty()) {
            return [];
        }

        $expr = Schema::hasColumn('visits', 'campaign_name')
            ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), ''))"
            : "NULLIF(TRIM(utm_campaign), '')";

        $inner = $this->scopedVisitsQuery($request, $domainIds, $fromDate, $toDate)
            ->whereRaw("{$expr} IS NOT NULL")
            ->selectRaw("{$expr} as campaign, is_invalid_traffic");

        return DB::query()
            ->fromSub($inner, 'resolved_visits')
            ->selectRaw('campaign, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
            ->groupBy('campaign')
            ->orderByDesc('total')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'campaign' => (string) $row->campaign,
                'total' => (int) $row->total,
                'invalid' => (int) $row->invalid,
                'valid' => max(0, (int) $row->total - (int) $row->invalid),
                'source' => 'visits',
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function paidMarketingCampaignRows($domainIds, string $fromDate, string $toDate, ?\App\Models\User $user = null, ?string $reportingTz = null): array
    {
        if (! Schema::hasTable('paid_marketing_visits') || collect($domainIds)->isEmpty()) {
            return [];
        }

        $reportingTz = $reportingTz && UserTimezone::isValid($reportingTz)
            ? $reportingTz
            : UserTimezone::reportingTimezoneForUser($user);

        $nameExpr = Schema::hasColumn('paid_marketing_visits', 'campaign_name')
            ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(campaign), ''))"
            : "NULLIF(TRIM(campaign), '')";

        $inner = DB::table('paid_marketing_visits')
            ->whereIn('domain_id', $domainIds);
        UserTimezone::applyCalendarDateRangeFilter(
            $inner,
            'last_click_at',
            $fromDate,
            $toDate,
            $user,
            $reportingTz,
        );
        $inner->whereRaw("{$nameExpr} IS NOT NULL")
            ->selectRaw("{$nameExpr} as campaign");

        return DB::query()
            ->fromSub($inner, 'resolved_visits')
            ->selectRaw('campaign, COUNT(*) as total')
            ->groupBy('campaign')
            ->orderByDesc('total')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'campaign' => (string) $row->campaign,
                'total' => (int) $row->total,
                'invalid' => 0,
                'valid' => (int) $row->total,
                'source' => 'paid_marketing_visits',
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveGoogleCampaignRowsForDomain(?Domain $domain, string $fromDate, string $toDate, bool $forceGoogleSync = false): array
    {
        if (! $domain?->googleAdsAccount || $domain->googleAdsAccount->is_manager) {
            return [];
        }

        $sync = app(GoogleAdsDomainMetricsSync::class);

        if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
            if ($forceGoogleSync || $sync->shouldRefresh($domain, $fromDate, $toDate)) {
                $sync->syncDomain($domain->fresh(), $fromDate, $toDate)['saved'] ?? 0;
            }

            $dbRows = $sync->aggregatedCampaignRowsWithFallback($domain->id, $fromDate, $toDate);
            if ($dbRows !== []) {
                return $dbRows;
            }
        }

        $liveRows = $this->googleAdsCampaignRows($domain->googleAdsAccount, $fromDate, $toDate, $domain->hostname);
        if ($liveRows !== [] && Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $sync->syncDomain($domain->fresh(), $fromDate, $toDate)['saved'] ?? 0;
            $dbRows = $sync->aggregatedCampaignRowsWithFallback($domain->id, $fromDate, $toDate);

            return $dbRows !== [] ? $dbRows : $liveRows;
        }

        return $liveRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function googleAdsCampaignRows(GoogleAdsAccount $account, string $fromDate, string $toDate, ?string $hostname = null): array
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
            $fromDate,
            $toDate,
            $hostname
        );
    }

    public function keywords(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
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

        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
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

    public function countryIps(Request $request): JsonResponse
    {
        $country = strtoupper(trim((string) $request->query('country', '')));
        if ($country === '' || ! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
            ->where('country', $country)
            ->select(
                'ip',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'),
                DB::raw('MAX(visited_at) as last_seen')
            )
            ->groupBy('ip')
            ->orderByDesc('total')
            ->limit(100)
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'ip' => $r->ip,
            'total' => (int) $r->total,
            'invalid' => (int) $r->invalid,
            'last_seen' => UserTimezone::isoForUser(
                ! empty($r->last_seen) ? Carbon::parse((string) $r->last_seen, 'UTC') : null,
                $request->user()
            ),
        ])->values());
    }

    public function ips(Request $request): JsonResponse
    {
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        if ($domainIds->isEmpty()) {
            return response()->json([]);
        }

        $rows = $this->resolveIpRows($request, $domainIds, $metricFrom, $metricTo);

        return response()->json($rows->take(50)->values());
    }

    public function ipClicks(Request $request): JsonResponse
    {
        $ip = trim((string) $request->query('ip', ''));
        if ($ip === '') {
            return response()->json([]);
        }

        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $user = $request->user();

        if ($domainIds->isEmpty() || ! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        $campaignExpr = Schema::hasColumn('visits', 'campaign_name')
            ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), ''))"
            : "NULLIF(TRIM(utm_campaign), '')";
        $paidIdExpr = Schema::hasColumn('visits', 'gclid')
            ? "COALESCE(NULLIF(TRIM(gclid), ''), NULLIF(TRIM(gbraid), ''), NULLIF(TRIM(wbraid), ''))"
            : "NULL";

        $rows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
            ->where('ip', $ip)
            ->orderBy('visited_at')
            ->limit(100)
            ->get([
                'visited_at',
                'url',
                'country',
                'browser',
                'os',
                'is_invalid_traffic',
                'threat_group',
                'action_taken',
                'detection_reasons',
                DB::raw("{$campaignExpr} as campaign"),
                DB::raw("{$paidIdExpr} as paid_id"),
                DB::raw(Schema::hasColumn('visits', 'utm_term') ? 'utm_term as keyword' : "NULL as keyword"),
            ]);

        if ($rows->isEmpty()) {
            return response()->json($this->ipClicksFromPaidMarketing($request, $domainIds, $metricFrom, $metricTo, $ip));
        }

        return response()->json($rows->map(function ($row) use ($user, $ip) {
            $reasons = [];
            if (! empty($row->detection_reasons)) {
                $decoded = json_decode((string) $row->detection_reasons, true);
                $reasons = is_array($decoded) ? $decoded : [];
            }

            return [
                'clicked_at' => UserTimezone::isoForUser(
                    ! empty($row->visited_at) ? Carbon::parse((string) $row->visited_at, 'UTC') : null,
                    $user
                ),
                'last_click_at' => UserTimezone::isoForUser(
                    ! empty($row->visited_at) ? Carbon::parse((string) $row->visited_at, 'UTC') : null,
                    $user
                ),
                'ip' => $ip,
                'country' => $row->country,
                'campaign' => $row->campaign,
                'path' => $row->url,
                'paid_id' => $row->paid_id,
                'keyword' => $row->keyword ?? null,
                'browser_name' => $row->browser,
                'browser_version' => null,
                'os' => $row->os,
                'threat_group' => $row->threat_group,
                'is_invalid' => (bool) $row->is_invalid_traffic,
                'action_taken' => $row->action_taken,
                'detection_reasons' => $reasons,
            ];
        })->values());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ipClicksFromPaidMarketing(Request $request, $domainIds, string $fromDate, string $toDate, string $ip): array
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits')) {
            return [];
        }

        $user = $request->user();
        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereIn('pv.domain_id', $domainIds)
            ->where('pc.ip', $ip);

        UserTimezone::applyCalendarDateRangeFilter(
            $query,
            'pc.clicked_at',
            $fromDate,
            $toDate,
            $user,
            $this->reportingTimezone($request, $domainIds),
        );
        $this->applyPaidTrafficOnlyFilter($query, 'pc');

        if ($this->hasCampaignFilter($request)) {
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign_name', 'pc.google_campaign_id', 'pc.campaign');
            } else {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign', 'pc.google_campaign_id');
            }
        }

        return $query
            ->orderBy('pc.clicked_at')
            ->limit(100)
            ->get([
                'pc.clicked_at',
                'pc.path',
                'pc.country',
                'pc.browser_name',
                'pc.browser_version',
                'pc.os',
                'pc.threat_group',
                'pc.campaign',
                'pc.paid_id',
                'pc.keyword',
            ])
            ->map(function ($row) use ($user, $ip) {
                $clickedAt = ! empty($row->clicked_at)
                    ? Carbon::parse((string) $row->clicked_at, 'UTC')
                    : null;

                return [
                    'clicked_at' => UserTimezone::isoForUser($clickedAt, $user),
                    'last_click_at' => UserTimezone::isoForUser($clickedAt, $user),
                    'ip' => $ip,
                    'country' => $row->country,
                    'campaign' => $row->campaign,
                    'path' => $row->path,
                    'paid_id' => $row->paid_id,
                    'keyword' => $row->keyword ?? null,
                    'browser_name' => $row->browser_name,
                    'browser_version' => $row->browser_version,
                    'os' => $row->os,
                    'threat_group' => $row->threat_group,
                    'is_invalid' => filled($row->threat_group),
                    'action_taken' => filled($row->threat_group) ? 'block' : 'allow',
                    'detection_reasons' => [],
                ];
            })
            ->values()
            ->all();
    }

    public function exportIpsCsv(Request $request): StreamedResponse
    {
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $filename = 'paid-marketing-ips-' . now()->format('YmdHis') . '.csv';

        return response()->streamDownload(function () use ($request, $domainIds, $metricFrom, $metricTo): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['IP Address', 'Country', 'Campaign', 'Invalid', 'Total', 'Bot Detect', 'VPN Hits', 'Data Center Hits', 'Malicious Hits', 'Last Click']);

            if ($domainIds->isEmpty()
                || (! Schema::hasTable('visits') && ! Schema::hasTable('paid_marketing_visits'))) {
                fclose($handle);

                return;
            }

            $rows = $this->resolveIpRows($request, $domainIds, $metricFrom, $metricTo);

            foreach ($rows->take(5000) as $r) {
                fputcsv($handle, [
                    $r['ip'],
                    $r['country'],
                    $r['campaign'],
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
        if (! Schema::hasTable('visits')
            && (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits'))) {
            return response()->json(['matrix' => [], 'days' => [], 'hours' => []]);
        }

        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $localVisitedAt = UserTimezone::localDateTimeSql('visited_at', $request->user(), $this->reportingTimezone($request, $domainIds));

        $rows = collect();
        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $rows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
                ->selectRaw("DAYOFWEEK({$localVisitedAt}) as dow, HOUR({$localVisitedAt}) as hr, COUNT(*) as total")
                ->groupBy('dow', 'hr')
                ->get();
        }

        if ($rows->isEmpty() && Schema::hasTable('paid_marketing_clicks') && Schema::hasTable('paid_marketing_visits') && $domainIds->isNotEmpty()) {
            $localClickedAt = UserTimezone::localDateTimeSql('pc.clicked_at', $request->user(), $this->reportingTimezone($request, $domainIds));
            $pmQuery = DB::table('paid_marketing_clicks as pc')
                ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
                ->whereIn('pv.domain_id', $domainIds);
            UserTimezone::applyCalendarDateRangeFilter(
                $pmQuery,
                'pc.clicked_at',
                $metricFrom,
                $metricTo,
                $request->user(),
                $this->reportingTimezone($request, $domainIds),
            );
            $this->applyPaidTrafficOnlyFilter($pmQuery, 'pc');
            $rows = $pmQuery
                ->selectRaw("DAYOFWEEK({$localClickedAt}) as dow, HOUR({$localClickedAt}) as hr, COUNT(*) as total")
                ->groupBy('dow', 'hr')
                ->get();
        }

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

    private function syncGoogleMetricsForDomains(Request $request, $domains, string $fromDate, string $toDate, bool $force = false): void
    {
        if (! Schema::hasTable('google_ads_campaign_daily_metrics') || $domains->isEmpty()) {
            return;
        }

        $sync = app(GoogleAdsDomainMetricsSync::class);
        $reportingTz = $this->reportingTimezone($request, $domains->pluck('id'));

        foreach ($domains as $domain) {
            if (! $domain->googleAdsAccount || $domain->googleAdsAccount->is_manager) {
                continue;
            }

            $googleTz = UserTimezone::isValid($domain->googleAdsAccount->time_zone)
                ? $domain->googleAdsAccount->time_zone
                : $reportingTz;
            [$syncFrom, $syncTo] = UserTimezone::googleMetricDateBounds($fromDate, $toDate, $reportingTz, $googleTz);

            if ($force || $sync->shouldRefresh($domain, $syncFrom, $syncTo)) {
                $sync->syncDomain($domain, $syncFrom, $syncTo);
            }
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

    private function scopedVisitsQuery(Request $request, $domainIds, string $fromDate, string $toDate)
    {
        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds);

        UserTimezone::applyCalendarDateRangeFilter(
            $query,
            'visited_at',
            $fromDate,
            $toDate,
            $request->user(),
            $this->reportingTimezone($request, $domainIds),
        );

        GoogleClickAttribution::applyHasClickIdFilter($query);

        $path = trim((string) $request->query('path', ''));
        if ($path !== '') {
            $query->where('url', 'like', '%' . $path . '%');
        }

        if ($this->hasCampaignFilter($request)) {
            if (Schema::hasColumn('visits', 'campaign_name')) {
                $this->applyDirectCampaignFilter($query, $request, 'campaign_name', 'google_campaign_id', 'utm_campaign');
            } else {
                $this->applyDirectCampaignFilter($query, $request, 'utm_campaign', 'google_campaign_id');
            }
        }

        return $query;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ipRowsFromVisits(Request $request, $domainIds, string $fromDate, string $toDate)
    {
        if (! Schema::hasTable('visits')) {
            return collect();
        }

        return $this->scopedVisitsQuery($request, $domainIds, $fromDate, $toDate)
            ->select(
                'ip',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'),
                DB::raw('MAX(country) as country'),
                DB::raw('MAX(visited_at) as last_seen'),
                DB::raw(Schema::hasColumn('visits', 'campaign_name')
                    ? 'MAX(COALESCE(campaign_name, utm_campaign)) as campaign'
                    : 'MAX(utm_campaign) as campaign'),
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
    private function ipRowsFromPaidMarketing(Request $request, $domainIds, string $fromDate, string $toDate)
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits')) {
            return collect();
        }

        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereIn('pv.domain_id', $domainIds);

        UserTimezone::applyCalendarDateRangeFilter(
            $query,
            'pc.clicked_at',
            $fromDate,
            $toDate,
            $request->user(),
            $this->reportingTimezone($request, $domainIds),
        );

        $this->applyPaidTrafficOnlyFilter($query, 'pc');

        if ($this->hasCampaignFilter($request)) {
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign_name', 'pc.google_campaign_id', 'pc.campaign');
            } else {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign', 'pc.google_campaign_id');
            }
        }

        return $query
            ->select(
                'pc.ip as ip',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != '' THEN 1 ELSE 0 END) as invalid"),
                DB::raw('MAX(pc.country) as country'),
                DB::raw('MAX(pc.clicked_at) as last_seen'),
                DB::raw(Schema::hasColumn('paid_marketing_clicks', 'campaign_name')
                    ? 'MAX(COALESCE(pc.campaign_name, pc.campaign, pv.campaign_name, pv.campaign)) as campaign'
                    : 'MAX(COALESCE(pc.campaign, pv.campaign)) as campaign'),
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
    private function resolveIpRows(Request $request, $domainIds, string $fromDate, string $toDate)
    {
        $rows = $this->mergeIpRowSources(
            $this->ipRowsFromVisits($request, $domainIds, $fromDate, $toDate),
            $this->ipRowsFromPaidMarketing($request, $domainIds, $fromDate, $toDate),
        );

        return $this->formatIpRows($rows, $request->user(), $this->resolveActiveAllowListIps($request));
    }

    /**
     * Prefer visits for threat metadata; use the higher click totals when sources diverge.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $visitRows
     * @param  \Illuminate\Support\Collection<int, object>  $paidRows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mergeIpRowSources($visitRows, $paidRows)
    {
        $byIp = [];

        foreach ($visitRows as $row) {
            $byIp[(string) $row->ip] = $row;
        }

        foreach ($paidRows as $row) {
            $ip = (string) $row->ip;
            if (! isset($byIp[$ip])) {
                $byIp[$ip] = $row;
                continue;
            }

            $existing = $byIp[$ip];
            $byIp[$ip] = (object) [
                'ip' => $ip,
                'total' => max((int) ($existing->total ?? 0), (int) ($row->total ?? 0)),
                'invalid' => max((int) ($existing->invalid ?? 0), (int) ($row->invalid ?? 0)),
                'country' => $existing->country ?? $row->country ?? null,
                'last_seen' => max(
                    (string) ($existing->last_seen ?? ''),
                    (string) ($row->last_seen ?? '')
                ) ?: null,
                'campaign' => $existing->campaign ?? $row->campaign ?? null,
                'vpn_hits' => max((int) ($existing->vpn_hits ?? 0), (int) ($row->vpn_hits ?? 0)),
                'data_center_hits' => max((int) ($existing->data_center_hits ?? 0), (int) ($row->data_center_hits ?? 0)),
                'malicious_hits' => max((int) ($existing->malicious_hits ?? 0), (int) ($row->malicious_hits ?? 0)),
                'top_threat' => $existing->top_threat ?? $row->top_threat ?? null,
            ];
        }

        return collect($byIp)
            ->sortByDesc(fn ($row) => (int) ($row->total ?? 0))
            ->values();
    }

    /**
     * @return list<string>
     */
    private function resolveActiveAllowListIps(Request $request): array
    {
        $domainIds = collect($this->scopedDomainIds($request));
        if ($domainIds->isEmpty()) {
            return [];
        }

        $domainId = (int) $request->query('domain_id', 0);
        $query = DomainDetectionSetting::query()
            ->where('allow_list_enabled', true)
            ->whereNotNull('allow_list_ips')
            ->where('allow_list_ips', '!=', '');

        if ($domainId > 0) {
            $query->where('domain_id', $domainId);
        } else {
            $query->whereIn('domain_id', $domainIds);
        }

        return $query->pluck('allow_list_ips')
            ->flatMap(fn ($list) => preg_split('/[\s,]+/', (string) $list) ?: [])
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Legacy paid-marketing click totals when visits table has no rows in range.
     *
     * @return array{total: int, invalid: int, unique_ips: int}
     */
    private function paidMarketingTrafficStats(Request $request, $domainIds, string $fromDate, string $toDate): array
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits') || collect($domainIds)->isEmpty()) {
            return ['total' => 0, 'invalid' => 0, 'unique_ips' => 0];
        }

        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereIn('pv.domain_id', $domainIds);

        UserTimezone::applyCalendarDateRangeFilter(
            $query,
            'pc.clicked_at',
            $fromDate,
            $toDate,
            $request->user(),
            $this->reportingTimezone($request, $domainIds),
        );

        $this->applyPaidTrafficOnlyFilter($query, 'pc');

        if ($this->hasCampaignFilter($request)) {
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign_name', 'pc.google_campaign_id', 'pc.campaign');
            } else {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign', 'pc.google_campaign_id');
            }
        }

        $row = (clone $query)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != "" THEN 1 ELSE 0 END) as invalid, COUNT(DISTINCT pc.ip) as unique_ips')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'invalid' => (int) ($row->invalid ?? 0),
            'unique_ips' => (int) ($row->unique_ips ?? 0),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{day: string, total: int, invalid: int}>
     */
    private function paidMarketingDailyTrendRows(Request $request, $domainIds, string $fromDate, string $toDate)
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits') || collect($domainIds)->isEmpty()) {
            return collect();
        }

        $dayExpr = UserTimezone::localDateSql('pc.clicked_at', $request->user(), $this->reportingTimezone($request, $domainIds));
        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereIn('pv.domain_id', $domainIds);

        UserTimezone::applyCalendarDateRangeFilter(
            $query,
            'pc.clicked_at',
            $fromDate,
            $toDate,
            $request->user(),
            $this->reportingTimezone($request, $domainIds),
        );

        $this->applyPaidTrafficOnlyFilter($query, 'pc');

        if ($this->hasCampaignFilter($request)) {
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign_name', 'pc.google_campaign_id', 'pc.campaign');
            } else {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign', 'pc.google_campaign_id');
            }
        }

        return $query
            ->selectRaw("{$dayExpr} as day, COUNT(*) as total, SUM(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != '' THEN 1 ELSE 0 END) as invalid")
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function formatIpRows($rows, ?\App\Models\User $user = null, array $allowListIps = [])
    {
        return $rows->map(fn ($row) => [
            'ip' => (string) ($row->ip ?? ''),
            'country' => $row->country ?? null,
            'total' => (int) ($row->total ?? 0),
            'invalid' => (int) ($row->invalid ?? 0),
            'valid' => max(0, (int) ($row->total ?? 0) - (int) ($row->invalid ?? 0)),
            'last_seen' => UserTimezone::isoForUser(
                ! empty($row->last_seen) ? Carbon::parse((string) $row->last_seen, 'UTC') : null,
                $user
            ),
            'campaign' => $row->campaign ?? null,
            'vpn_hits' => (int) ($row->vpn_hits ?? 0),
            'data_center_hits' => (int) ($row->data_center_hits ?? 0),
            'malicious_hits' => (int) ($row->malicious_hits ?? 0),
            'top_threat' => $row->top_threat ?? null,
            'is_allowlisted' => IpFraudEvaluator::isIpAllowListed((string) ($row->ip ?? ''), implode("\n", $allowListIps)),
        ])->values();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyPaidTrafficOnlyFilter($query, string $clickAlias = 'pc'): void
    {
        GoogleClickAttribution::applyPaidClickIdFilter($query, "{$clickAlias}.paid_id");
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

    private function applyDirectCampaignFilter($query, Request $request, string $nameColumn, string $idColumn, ?string $legacyNameColumn = null): void
    {
        if (! $this->hasCampaignFilter($request)) {
            return;
        }

        $campaignName = trim((string) $request->query('campaign', ''));
        $campaignId = preg_replace('/\D+/', '', (string) $request->query('campaign_id', ''));

        if ($campaignName === '' && $campaignId === '') {
            return;
        }

        $query->where(function ($match) use ($campaignName, $campaignId, $nameColumn, $idColumn, $legacyNameColumn): void {
            $started = false;

            if ($campaignId !== '') {
                $match->where($idColumn, $campaignId);
                $started = true;
            }

            if ($campaignName === '') {
                return;
            }

            $nameMatcher = function ($nameQ) use ($campaignName, $nameColumn, $legacyNameColumn): void {
                $nameQ->where($nameColumn, $campaignName);

                if ($legacyNameColumn !== null && $legacyNameColumn !== $nameColumn) {
                    $nameQ->orWhere($legacyNameColumn, $campaignName);
                }

                $nameQ->orWhere($nameColumn, 'like', '%' . $campaignName . '%');

                if ($legacyNameColumn !== null && $legacyNameColumn !== $nameColumn) {
                    $nameQ->orWhere($legacyNameColumn, 'like', '%' . $campaignName . '%');
                }
            };

            if ($started) {
                $match->orWhere($nameMatcher);
            } else {
                $match->where($nameMatcher);
            }
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
        $domainId = (int) $request->query('domain_id', 0);
        if ($domainId <= 0 && $domainIds->count() === 1) {
            $domainId = (int) $domainIds->first();
        }
        $campaignId = $domainId > 0 ? $this->resolveCampaignId($request, $domainId) : '';
        $gclids = $this->resolveCampaignGclids($request, $domainIds, $from, $to, $campaignId);
        $activeDates = $domainId > 0 && $campaignId !== ''
            ? $this->resolveCampaignActiveDates($domainId, $campaignId, $from, $to, $request->user())
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
            $domainId,
            $campaignId,
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

            if (Schema::hasTable('paid_marketing_clicks') && Schema::hasTable('paid_marketing_visits') && $campaign !== '') {
                $outer->orWhereExists(function ($sub) use ($campaign, $domainIds, $from, $to, $ipColumn, $request): void {
                    $sub->selectRaw('1')
                        ->from('paid_marketing_clicks as pc_camp')
                        ->join('paid_marketing_visits as pv_camp', 'pv_camp.id', '=', 'pc_camp.paid_marketing_visit_id')
                        ->whereIn('pv_camp.domain_id', $domainIds)
                        ->whereColumn('pv_camp.ip', $ipColumn)
                        ->whereBetween('pc_camp.clicked_at', [$from, $to])
                        ->where(function ($camp) use ($request): void {
                            $this->applySmartCampaignFilterConditions($camp, 'pc_camp.campaign', $request);
                        });
                });
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
                    $request,
                ): void {
                    $dates->where(function ($unattrib) use ($utmColumn): void {
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

                    $this->applyVisitLocalDayFilter($dates, $visitedAtColumn, $activeDates, $request);
                });
            }

            if (
                $campaignId !== ''
                && $domainId > 0
                && $gclidColumn !== null
                && $this->domainHasSingleGoogleCampaign($domainId, $from, $to, $request->user())
            ) {
                $outer->orWhere(function ($fallback) use ($gclidColumn, $utmColumn, $visitedAtColumn, $from, $to): void {
                    $fallback->whereBetween($visitedAtColumn, [$from, $to])
                        ->whereNotNull($gclidColumn)
                        ->where($gclidColumn, '!=', '')
                        ->where(function ($unattrib) use ($utmColumn): void {
                            $unattrib->whereNull($utmColumn)->orWhere($utmColumn, '');
                        });
                });
            }
        });
    }

    /**
     * Match visit timestamps to campaign-active calendar days in the user's timezone.
     *
     * @param  list<string>  $dates
     */
    private function applyVisitLocalDayFilter($query, string $visitedAtColumn, array $dates, Request $request): void
    {
        if ($dates === []) {
            return;
        }

        $tz = UserTimezone::forUser($request->user());
        $query->where(function ($days) use ($dates, $tz, $visitedAtColumn): void {
            foreach ($dates as $date) {
                $days->orWhereBetween($visitedAtColumn, [
                    Carbon::parse($date, $tz)->startOfDay()->utc(),
                    Carbon::parse($date, $tz)->endOfDay()->utc(),
                ]);
            }
        });
    }

    private function domainHasSingleGoogleCampaign(int $domainId, Carbon $from, Carbon $to, ?\App\Models\User $user = null): bool
    {
        if (! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return false;
        }

        $tz = UserTimezone::forUser($user);

        return DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [
                $from->copy()->timezone($tz)->toDateString(),
                $to->copy()->timezone($tz)->toDateString(),
            ])
            ->where('clicks', '>', 0)
            ->distinct()
            ->count('campaign_id') === 1;
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
    private function resolveCampaignActiveDates(int $domainId, string $campaignId, Carbon $from, Carbon $to, ?\App\Models\User $user = null): array
    {
        if ($campaignId === '' || ! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return [];
        }

        $tz = UserTimezone::forUser($user);

        return DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->where('campaign_id', $campaignId)
            ->whereBetween('metric_date', [
                $from->copy()->timezone($tz)->toDateString(),
                $to->copy()->timezone($tz)->toDateString(),
            ])
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
        $domainId = (int) $request->query('domain_id', 0);
        if ($domainId <= 0 && $domainIds->count() === 1) {
            $domainId = (int) $domainIds->first();
        }

        if ($domainId <= 0) {
            return $this->resolveStoredCampaignGclids($domainIds, $from, $to, $request, $campaignId ?? '');
        }

        $campaignId = $campaignId ?? $this->resolveCampaignId($request, $domainId);
        $remote = $this->resolveRemoteCampaignGclids($domainId, $from, $to, $campaignId);
        $local = $this->resolveStoredCampaignGclids(collect([$domainId]), $from, $to, $request, $campaignId);

        return array_values(array_unique(array_merge($remote, $local)));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $domainIds
     * @return list<string>
     */
    private function resolveStoredCampaignGclids($domainIds, Carbon $from, Carbon $to, Request $request, string $campaignId): array
    {
        $gclids = collect();
        $campaign = trim((string) $request->query('campaign', ''));

        if ($campaign === '' && $campaignId === '') {
            return [];
        }

        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'gclid')) {
            $query = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to])
                ->whereNotNull('gclid')
                ->where('gclid', '!=', '');

            $query->where(function ($outer) use ($request, $campaignId, $domainIds, $from, $to): void {
                $outer->where(function ($utm) use ($request): void {
                    $this->applySmartCampaignFilterConditions($utm, 'utm_campaign', $request);
                });

                $domainId = (int) $request->query('domain_id', 0);
                if ($domainId <= 0 && $domainIds->count() === 1) {
                    $domainId = (int) $domainIds->first();
                }

                if ($campaignId !== '' && $domainId > 0) {
                    $activeDates = $this->resolveCampaignActiveDates($domainId, $campaignId, $from, $to, $request->user());
                    if ($activeDates !== []) {
                        $outer->orWhere(function ($unattrib) use ($request, $activeDates): void {
                            $unattrib->where(function ($utm) {
                                $utm->whereNull('utm_campaign')->orWhere('utm_campaign', '');
                            });
                            $this->applyVisitLocalDayFilter($unattrib, 'visited_at', $activeDates, $request);
                        });
                    }
                }
            });

            $gclids = $gclids->merge($query->pluck('gclid'));
        }

        if (Schema::hasTable('paid_marketing_clicks') && Schema::hasTable('paid_marketing_visits')) {
            $clickQuery = DB::table('paid_marketing_clicks as pc')
                ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
                ->whereIn('pv.domain_id', $domainIds)
                ->whereBetween('pc.clicked_at', [$from, $to])
                ->whereNotNull('pc.paid_id')
                ->where('pc.paid_id', '!=', '');

            $clickQuery->where(function ($camp) use ($request): void {
                $this->applySmartCampaignFilterConditions($camp, 'pc.campaign', $request);
            });

            $gclids = $gclids->merge($clickQuery->pluck('pc.paid_id'));
        }

        return $gclids->map(fn ($value) => (string) $value)->filter()->unique()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function resolveRemoteCampaignGclids(int $domainId, Carbon $from, Carbon $to, string $campaignId): array
    {
        if ($campaignId === '') {
            return [];
        }

        $domain = Domain::query()
            ->with(['googleAdsAccount.connection', 'user'])
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

        $tz = UserTimezone::forUser($domain?->user);

        return app(GoogleAdsMetricsService::class)->gclidsForCampaign(
            $account,
            $api->apiVersions()[0] ?? 'v24',
            $headers,
            $campaignId,
            $from->copy()->timezone($tz)->toDateString(),
            $to->copy()->timezone($tz)->toDateString(),
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
        $domainIds = $this->scopedDomainIds($request);

        return UserTimezone::dateRangeFromRequest(
            $request,
            $request->user(),
            6,
            $this->reportingTimezone($request, $domainIds),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function calendarDateRange(Request $request): array
    {
        $domainIds = $this->scopedDomainIds($request);

        return UserTimezone::calendarDateRangeFromRequest(
            $request,
            $request->user(),
            6,
            $this->reportingTimezone($request, $domainIds),
        );
    }

    private function reportingTimezone(Request $request, $domainIds = null): string
    {
        return UserTimezone::reportingTimezoneForUser(
            $request->user(),
            $this->resolveGoogleTimezone($request, $domainIds),
        );
    }

    private function resolveGoogleTimezone(Request $request, $domainIds = null): ?string
    {
        $domainIds ??= $this->scopedDomainIds($request);
        $domainId = (int) $request->query('domain_id', 0);

        return UserTimezone::resolveGoogleAccountTimezone(
            $request->user(),
            $domainId > 0 ? $domainId : null,
            $domainIds,
        );
    }

    private function scopedDomains(Request $request, $domainIds)
    {
        return Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->whereIn('id', $domainIds)
            ->with('googleAdsAccount')
            ->get();
    }

    /**
     * Paid traffic headline = Google-verified visits that are not invalid/bot traffic.
     */
    private function displayPaidTrafficCount(int $verifiedValidPaid, int $tagPaid, int $googleClicks): int
    {
        return max(0, $verifiedValidPaid);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $domainIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function paidMarketingRowsForVerification(Request $request, $domainIds, string $fromDate, string $toDate)
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits')) {
            return collect();
        }

        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereIn('pv.domain_id', $domainIds);

        UserTimezone::applyCalendarDateRangeFilter(
            $query,
            'pc.clicked_at',
            $fromDate,
            $toDate,
            $request->user(),
            $this->reportingTimezone($request, $domainIds),
        );

        $this->applyPaidTrafficOnlyFilter($query, 'pc');

        $columns = [
            'pv.domain_id',
            'pc.path as url',
            'pc.clicked_at as visited_at',
        ];
        if (Schema::hasColumn('paid_marketing_clicks', 'google_campaign_id')) {
            $columns[] = 'pc.google_campaign_id';
        } elseif (Schema::hasColumn('paid_marketing_visits', 'google_campaign_id')) {
            $columns[] = 'pv.google_campaign_id';
        } else {
            $columns[] = DB::raw('NULL as google_campaign_id');
        }
        if (Schema::hasColumn('paid_marketing_clicks', 'threat_group')) {
            $columns[] = 'pc.threat_group';
        } else {
            $columns[] = DB::raw('NULL as threat_group');
        }

        return $query->get($columns);
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
