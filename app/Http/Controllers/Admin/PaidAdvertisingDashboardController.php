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
use App\Support\PaidAdvertising\IpRowRiskScorer;
use App\Support\PaidMarketing\DashboardResponseCache;
use App\Support\GlobalIpAllowlist;
use App\Support\GoogleClickAttribution;
use App\Support\GoogleInvalidClickReconciler;
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

        $googleAdsAccounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->synced()
            ->orderBy('account_name')
            ->orderBy('customer_id')
            ->get(['id', 'account_name', 'customer_id', 'display_customer_id']);

        $countryGetStarted = $domains->isEmpty()
            || ! $domains->contains(fn (Domain $d) => $d->hasPaidAdvertisingFromAds());

        return view('paid-marketing.dashboard', [
            'domains' => $domains,
            'googleAdsAccounts' => $googleAdsAccounts,
            'domainCatalog' => UserTimezone::domainCatalog($domains),
            'countryGetStarted' => $countryGetStarted,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->rememberPaidDashboardJson(
            $request,
            'summary',
            fn () => $this->summaryPayload($request),
            $request->boolean('force_google_sync'),
        );
    }

    private function summaryPayload(Request $request): array
    {
        [$from, $to] = $this->dateRange($request);
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $reportingTz = $this->reportingTimezone($request, $domainIds);
        $googleTz = $this->resolveGoogleTimezone($request, $domainIds);
        $domains = $this->scopedDomains($request, $domainIds);

        // Dashboard reads the last successful stored metrics. Do not make a
        // Google API call during normal card refreshes: a transient API/token
        // response must never make previously displayed totals disappear.
        $forceGoogleSync = $request->boolean('force_google_sync');
        $googleSyncErrors = $forceGoogleSync
            ? $this->syncGoogleMetricsForDomains(
                $request,
                $domains,
                $metricFrom,
                $metricTo,
                true
            )
            : [];

        $tagPaid = 0;
        $verifiedPaid = 0;
        $verifiedValidPaid = 0;
        $unverifiedPaid = 0;
        $invalid = 0;
        $blocked = 0;
        $blockAttempts = 0;
        $blockEnforced = 0;
        $flagged = 0;
        $uniqueIps = 0;
        $uniqueInvalidPaidClicks = 0;
        $uniquePaidClicks = 0;
        $uniqueValidPaidClicks = 0;

        $invalidReconciliation = [
            'platform_only' => 0,
            'google_only' => 0,
            'overlap' => 0,
            'platform_invalid_total' => 0,
            'google_gap_total' => 0,
        ];

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
            $uniquePaidClicks = GoogleClickAttribution::countDistinctClickIds(clone $base);
            $invalid = (clone $base)->where('is_invalid_traffic', true)->count();
            $uniqueInvalidPaidClicks = GoogleClickAttribution::countDistinctClickIds(
                (clone $base)->where('is_invalid_traffic', true)
            );
            // Valid = distinct click IDs that never appear as invalid (certification: 1 click ID = 1 click).
            $uniqueValidPaidClicks = max(0, $uniquePaidClicks - $uniqueInvalidPaidClicks);
            $uniqueIps = (clone $base)->distinct()->count('ip');

            if (Schema::hasColumn('visits', 'action_taken')) {
                $blockAttempts = (clone $base)->where('action_taken', 'block')->count();
                $blocked = $blockAttempts;
                $flagged = (clone $base)->where('action_taken', 'flag')->count();
                if (Schema::hasColumn('visits', 'block_enforced')) {
                    $blockEnforced = (clone $base)->where('block_enforced', true)->count();
                }
            }

            $visitRows = (clone $base)->get([
                'domain_id',
                'url',
                'google_campaign_id',
                'visited_at',
                'is_invalid_traffic',
                'gclid',
                'gbraid',
                'wbraid',
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

                $invalidReconciliation = app(GoogleInvalidClickReconciler::class)->categorize(
                    $visitRows,
                    $verificationLookup,
                    $reportingTz,
                );
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

        $paid = $this->displayPaidTrafficCount($verifiedValidPaid, $uniqueValidPaidClicks, $googleClicks);
        $validTagPaid = max(0, $uniqueValidPaidClicks);
        // Keep the card useful while Google metrics have not synced yet. Stored
        // Google totals remain authoritative when available.
        $totalClickCount = $googleClicks > 0
            ? $googleClicks
            : max($uniquePaidClicks, $tagPaid);
        // Tracking accuracy = distinct tracked Google click IDs / Google Ads reported clicks.
        $trackingAccuracyPct = $googleClicks > 0
            ? (int) round(min(100, ($uniquePaidClicks / $googleClicks) * 100))
            : ($uniquePaidClicks > 0 ? 100 : 0);
        $tagCapturePct = $googleClicks > 0
            ? (int) round(min(100, ($uniqueValidPaidClicks / $googleClicks) * 100))
            : ($uniqueValidPaidClicks > 0 ? 100 : 0);

        $googleCost = (float) ($googleAds['cost'] ?? 0);
        $invalidForSavings = $uniqueInvalidPaidClicks > 0 ? $uniqueInvalidPaidClicks : $invalid;
        $avgCpc = $googleClicks > 0 ? ($googleCost / $googleClicks) : 0.0;
        $costSaved = round($avgCpc * $invalidForSavings, 2);

        $selectedDomain = $request->filled('domain_id') && $domains->count() === 1
            ? $domains->first()
            : null;

        $googleSyncError = collect($googleSyncErrors)
            ->map(fn ($row) => trim((string) ($row['message'] ?? '')))
            ->first(fn ($message) => $message !== '');
        $selectedDomainId = $selectedDomain?->id ?: (int) $request->query('domain_id', 0);
        // A zero Google total can be a date-range/reporting delay and does not prove
        // that OAuth is broken. Only ask for reconnect after an actual auth error.
        $googleNeedsReconnect = $selectedDomainId > 0
            && $this->googleSyncLooksAuthRelated($googleSyncError);
        $googleReconnectUrl = $this->googleReconnectUrl($selectedDomainId);

        return [
            'paid_visits' => $paid,
            'verified_paid_visits' => $verifiedPaid,
            'verified_valid_paid_visits' => $verifiedValidPaid,
            'unverified_paid_visits' => $unverifiedPaid,
            'tag_paid_visits' => $tagPaid,
            'tracked_clicks' => $uniquePaidClicks,
            'google_clicks' => $googleClicks,
            'total_click_count' => $totalClickCount,
            'tag_capture_pct' => $tagCapturePct,
            'tracking_accuracy_pct' => $trackingAccuracyPct,
            'tag_gap_warning' => $googleClicks > 0 && $uniquePaidClicks < (int) floor($googleClicks * 0.5),
            'google_sync_error' => $googleSyncError,
            'google_needs_reconnect' => $googleNeedsReconnect,
            'google_reconnect_url' => $googleReconnectUrl,
            'invalid_paid_visits' => $uniqueInvalidPaidClicks > 0 ? $uniqueInvalidPaidClicks : $invalid,
            'invalid_paid_events' => $invalid,
            'unique_invalid_paid_clicks' => $uniqueInvalidPaidClicks,
            'blocked_paid_visits' => $blocked,
            'block_attempts' => $blockAttempts,
            'block_enforced' => $blockEnforced,
            'flagged_paid_visits' => $flagged,
            'invalid_reconciliation' => $invalidReconciliation,
            'unique_ips' => $uniqueIps,
            'unique_paid_clicks' => $uniquePaidClicks,
            'unique_valid_paid_clicks' => $uniqueValidPaidClicks,
            'valid_paid_visits' => $validTagPaid,
            'google_ads' => $googleAds,
            'google_cost' => round($googleCost, 2),
            'avg_cpc' => round($avgCpc, 4),
            'cost_saved' => $costSaved,
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
        ];
    }

    /**
     * Cheap poll target: returns the latest paid-traffic visit id + count for the
     * current filters, with no Google API calls. The dashboard polls this frequently
     * and only runs a full reload() when the watermark actually changes.
     */
    public function watermark(Request $request): JsonResponse
    {
        $meta = $this->dashboardCacheMeta($request);

        return response()->json([
            'last_id' => $meta['last_id'],
            'count' => $meta['count'],
            'version' => $meta['version'],
            'domains_sig' => $meta['domains_sig'],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        return $this->rememberPaidDashboardJson(
            $request,
            'trends',
            fn () => $this->trendsPayload($request),
            $request->boolean('force_google_sync'),
        );
    }

    private function trendsPayload(Request $request): array
    {
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $userTz = $this->reportingTimezone($request, $domainIds);
        $domains = $this->scopedDomains($request, $domainIds);
        $hourly = $metricFrom === $metricTo;

        $this->syncGoogleMetricsForDomains(
            $request,
            $domains,
            $metricFrom,
            $metricTo,
            $request->boolean('force_google_sync')
        );

        $fetchRows = function (string $fromDate, string $toDate, bool $useHourly) use ($request, $domainIds, $userTz) {
            $rows = collect();
            if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
                $bucketExpr = $useHourly
                    ? UserTimezone::localHourBucketSql('visited_at', $request->user(), $userTz)
                    : UserTimezone::localDateSql('visited_at', $request->user(), $userTz);
                $rows = $this->scopedVisitsQuery($request, $domainIds, $fromDate, $toDate)
                    ->selectRaw("{$bucketExpr} as bucket, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid")
                    ->groupBy('bucket')
                    ->orderBy('bucket')
                    ->get()
                    ->map(function ($row) {
                        $row->day = $row->bucket;

                        return $row;
                    });
            }

            if (! $useHourly && $rows->isEmpty() && $domainIds->isNotEmpty()) {
                $rows = $this->paidMarketingDailyTrendRows($request, $domainIds, $fromDate, $toDate)
                    ->map(function ($row) {
                        $row->bucket = $row->day;

                        return $row;
                    });
            }

            return $rows;
        };

        $chartFrom = Carbon::parse($metricFrom, $userTz)->startOfDay();
        $chartTo = Carbon::parse($metricTo, $userTz)->endOfDay();
        $rows = $fetchRows($metricFrom, $metricTo, $hourly);

        $googleByDay = null;
        if (! $hourly && Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $sync = app(GoogleAdsDomainMetricsSync::class);
            $googleByDay = $sync->dailyClicksByDateForDomainsReporting($domainIds, $metricFrom, $metricTo, $userTz, $domains);
        }

        $buildSeries = function (string $rangeFromDate, string $rangeToDate, $dayRows, $googleDays, bool $useHourly) use ($userTz): array {
            $paid = [];
            $invalid = [];
            if ($useHourly) {
                $period = Carbon::parse($rangeFromDate, $userTz)->startOfDay();
                $end = Carbon::parse($rangeToDate, $userTz)->endOfDay()->startOfHour();
                while ($period->lte($end)) {
                    $key = $period->format('Y-m-d H:00:00');
                    $row = $dayRows->firstWhere('day', $key) ?? $dayRows->firstWhere('bucket', $key);
                    $visitPaid = (int) ($row->total ?? 0);
                    $invalidDay = (int) ($row->invalid ?? 0);
                    $paid[] = $visitPaid;
                    $invalid[] = $invalidDay;
                    $period->addHour();
                }

                return ['paid' => $paid, 'invalid' => $invalid];
            }

            $period = Carbon::parse($rangeFromDate, $userTz)->startOfDay();
            $end = Carbon::parse($rangeToDate, $userTz)->startOfDay();
            while ($period->lte($end)) {
                $key = $period->toDateString();
                $row = $dayRows->firstWhere('day', $key) ?? $dayRows->firstWhere('bucket', $key);
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
        if ($hourly) {
            $period = $chartFrom->copy()->startOfDay();
            $endHour = $chartTo->copy()->startOfHour();
            while ($period->lte($endHour)) {
                $labels[] = $period->format('g A');
                $period->addHour();
            }
        } else {
            $spanDays = $chartFrom->copy()->startOfDay()->diffInDays($chartTo->copy()->startOfDay()) + 1;
            $period = $chartFrom->copy()->startOfDay();
            $endDay = $chartTo->copy()->startOfDay();
            while ($period->lte($endDay)) {
                $labels[] = $spanDays <= 14
                    ? $period->format('D n/j')
                    : $period->format('M j');
                $period->addDay();
            }
        }

        $current = $buildSeries($metricFrom, $metricTo, $rows, $googleByDay, $hourly);
        $paidSeries = $current['paid'];
        $invalidSeries = $current['invalid'];

        $lastWeekSeries = array_fill(0, count($paidSeries), 0);
        if (! $hourly) {
            $days = max(1, $chartFrom->diffInDays($chartTo->copy()->startOfDay()) + 1);
            $prevMetricFrom = Carbon::parse($metricFrom, $userTz)->subDays($days)->toDateString();
            $prevMetricTo = Carbon::parse($metricFrom, $userTz)->subDay()->toDateString();
            $prevRows = $fetchRows($prevMetricFrom, $prevMetricTo, false);

            $googlePrev = null;
            if ($googleByDay !== null && $domainIds->isNotEmpty()) {
                $googlePrev = app(GoogleAdsDomainMetricsSync::class)
                    ->dailyClicksByDateForDomains($domainIds, $prevMetricFrom, $prevMetricTo);
            }

            $previous = $buildSeries($prevMetricFrom, $prevMetricTo, $prevRows, $googlePrev, false);
            $lastWeekSeries = $previous['paid'];
            while (count($lastWeekSeries) < count($paidSeries)) {
                array_unshift($lastWeekSeries, 0);
            }
            $lastWeekSeries = array_slice($lastWeekSeries, 0, count($paidSeries));
        }

        $datasets = [
            [
                'name' => $hourly ? 'Today' : 'This Period',
                'values' => $paidSeries,
                'color' => '#FFFFFF',
            ],
        ];
        if (! $hourly) {
            $datasets[] = [
                'name' => 'Previous Period',
                'values' => $lastWeekSeries,
                'color' => '#FF4BC1',
                'dashed' => true,
            ];
        }

        return [
            'labels' => $labels,
            'invalid_daily' => $invalidSeries,
            'granularity' => $hourly ? 'hourly' : 'daily',
            'granularity_label' => $hourly
                ? 'Hourly · '.Carbon::parse($metricFrom, $userTz)->format('M j')
                : 'Daily · '.Carbon::parse($metricFrom, $userTz)->format('M j').' – '.Carbon::parse($metricTo, $userTz)->format('M j'),
            'datasets' => $datasets,
        ];
    }

    public function blockingActivity(Request $request): JsonResponse
    {
        return $this->rememberPaidDashboardJson(
            $request,
            'blocking',
            fn () => $this->blockingActivityPayload($request),
        );
    }

    private function blockingActivityPayload(Request $request): array
    {
        if (! Schema::hasTable('visits') || ! Schema::hasColumn('visits', 'action_taken')) {
            return ['labels' => [], 'datasets' => []];
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

        $engine = $this->protectionEngineState($request, $domainIds);

        return [
            'labels' => $labels,
            'datasets' => [
                ['name' => 'Blocked', 'values' => $blockSeries],
                ['name' => 'Flagged', 'values' => $flagSeries],
            ],
            'rules' => $engine['rules'],
            'engine' => $engine,
        ];
    }

    public function campaigns(Request $request): JsonResponse
    {
        return $this->rememberPaidDashboardJson(
            $request,
            'campaigns',
            fn () => $this->campaignsPayload($request),
            $request->boolean('force_google_sync'),
        );
    }

    private function campaignsPayload(Request $request): array
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
        $scopedDomains = $this->scopedDomains($request, $domainIds);
        $avgCpc = $this->avgGoogleCpc($request, $domainIds, $metricFrom, $metricTo, $scopedDomains);
        $merged = $merged
            ->merge($this->visitCampaignRows($request, $domainIds, $metricFrom, $metricTo))
            ->merge($this->paidMarketingCampaignRows($domainIds, $metricFrom, $metricTo, $request->user(), $this->reportingTimezone($request, $domainIds)));

        $rows = $merged
            ->filter(fn ($row) => filled(is_array($row) ? ($row['campaign'] ?? null) : null))
            ->groupBy(fn ($row) => (string) $row['campaign'])
            ->map(function ($group, $campaign) use ($avgCpc) {
                $best = collect($group)->sortByDesc(fn ($row) => (int) ($row['total'] ?? $row['clicks'] ?? 0))->first();
                $total = (int) ($best['total'] ?? $best['clicks'] ?? 0);
                $invalid = (int) ($best['invalid'] ?? 0);

                return [
                    'campaign' => $campaign,
                    'campaign_id' => $best['campaign_id'] ?? null,
                    'total' => $total,
                    'invalid' => $invalid,
                    'valid' => (int) ($best['valid'] ?? max(0, $total - $invalid)),
                    'invalid_pct' => $total > 0 ? round(($invalid / $total) * 100, 1) : 0,
                    'cost_saved' => round($avgCpc * $invalid, 2),
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

        return [
            'campaigns' => $rows,
            'untagged_domains' => $untaggedDomains,
        ];
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
        return $this->rememberPaidDashboardJson(
            $request,
            'keywords',
            fn () => $this->keywordsPayload($request),
        );
    }

    private function keywordsPayload(Request $request): array
    {
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        $merged = collect();

        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $visitRows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
                ->whereNotNull('utm_term')
                ->where('utm_term', '!=', '')
                ->whereRaw("LOWER(utm_term) NOT IN ('null', 'undefined', '{keyword}')")
                ->select(
                    'utm_term as keyword',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
                )
                ->groupBy('utm_term')
                ->orderByDesc('total')
                ->limit(40)
                ->get();

            $merged = $merged->merge($visitRows);

            // Also pull keywords stored in Google click meta when utm_term was not captured.
            // JSON null becomes the literal string "null" after JSON_UNQUOTE — exclude those.
            if (Schema::hasColumn('visits', 'ad_click_meta')) {
                $metaKeyword = "JSON_UNQUOTE(JSON_EXTRACT(ad_click_meta, '$.keyword'))";
                $metaRows = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
                    ->whereNotNull('ad_click_meta')
                    ->whereRaw("{$metaKeyword} IS NOT NULL")
                    ->whereRaw("{$metaKeyword} != ''")
                    ->whereRaw("LOWER({$metaKeyword}) NOT IN ('null', 'undefined', '{keyword}')")
                    ->selectRaw("{$metaKeyword} as keyword, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid")
                    ->groupByRaw($metaKeyword)
                    ->orderByDesc('total')
                    ->limit(40)
                    ->get();

                $merged = $merged->merge($metaRows);
            }

            // Recover keywords from landing URL query when columns were empty historically.
            if (Schema::hasColumn('visits', 'url')) {
                $urlVisits = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo)
                    ->where(function ($query) {
                        $query->where('url', 'like', '%keyword=%')
                            ->orWhere('url', 'like', '%utm_term=%');
                    })
                    ->limit(2000)
                    ->get(['url', 'is_invalid_traffic']);

                $fromUrl = [];
                foreach ($urlVisits as $visit) {
                    $keyword = $this->keywordFromLandingUrl((string) ($visit->url ?? ''));
                    if ($keyword === null) {
                        continue;
                    }
                    $key = mb_strtolower($keyword);
                    if (! isset($fromUrl[$key])) {
                        $fromUrl[$key] = (object) ['keyword' => $keyword, 'total' => 0, 'invalid' => 0];
                    }
                    $fromUrl[$key]->total++;
                    if ((int) ($visit->is_invalid_traffic ?? 0) === 1) {
                        $fromUrl[$key]->invalid++;
                    }
                }

                $merged = $merged->merge(array_values($fromUrl));
            }
        }

        if (Schema::hasTable('paid_marketing_clicks')
            && Schema::hasTable('paid_marketing_visits')
            && Schema::hasColumn('paid_marketing_clicks', 'keyword')
            && $domainIds->isNotEmpty()) {
            $clickQuery = DB::table('paid_marketing_clicks as pc')
                ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
                ->whereIn('pv.domain_id', $domainIds)
                ->whereNotNull('pc.keyword')
                ->where('pc.keyword', '!=', '')
                ->whereRaw("LOWER(pc.keyword) NOT IN ('null', 'undefined', '{keyword}')");

            UserTimezone::applyCalendarDateRangeFilter(
                $clickQuery,
                'pc.clicked_at',
                $metricFrom,
                $metricTo,
                $request->user(),
                $this->reportingTimezone($request, $domainIds),
            );

            $this->applyPaidTrafficOnlyFilter($clickQuery, 'pc');

            if ($this->hasCampaignFilter($request)) {
                if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                    $this->applyDirectCampaignFilter($clickQuery, $request, 'pc.campaign_name', 'pc.google_campaign_id', 'pc.campaign');
                } else {
                    $this->applyDirectCampaignFilter($clickQuery, $request, 'pc.campaign', 'pc.google_campaign_id');
                }
            }

            $clickRows = $clickQuery
                ->select(
                    'pc.keyword as keyword',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != '' THEN 1 ELSE 0 END) as invalid")
                )
                ->groupBy('pc.keyword')
                ->orderByDesc('total')
                ->limit(40)
                ->get();

            $merged = $merged->merge($clickRows);
        }

        return $merged
            ->filter(function ($row) {
                $keyword = mb_strtolower(trim((string) ($row->keyword ?? '')));

                return $keyword !== ''
                    && ! in_array($keyword, ['null', 'undefined', '{keyword}'], true);
            })
            ->groupBy(fn ($row) => mb_strtolower(trim((string) $row->keyword)))
            ->map(function ($group) {
                $keyword = trim((string) ($group->first()->keyword ?? ''));
                $total = (int) $group->sum(fn ($r) => (int) ($r->total ?? 0));
                $invalid = (int) $group->sum(fn ($r) => (int) ($r->invalid ?? 0));

                return [
                    'keyword' => $keyword,
                    'total' => $total,
                    'invalid' => $invalid,
                    'valid' => max(0, $total - $invalid),
                    'invalid_pct' => $total > 0 ? round(($invalid / $total) * 100, 1) : 0.0,
                    'risk' => $total > 0 ? round(($invalid / $total) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->take(20)
            ->values()
            ->all();
    }

    public function countries(Request $request): JsonResponse
    {
        return $this->rememberPaidDashboardJson(
            $request,
            'countries',
            fn () => $this->countriesPayload($request),
        );
    }

    private function countriesPayload(Request $request): array
    {
        if (! Schema::hasTable('visits')) {
            return [];
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

        return $rows->map(fn ($r) => [
            'country' => $r->country,
            'total' => (int) $r->total,
            'invalid' => (int) $r->invalid,
        ])->values()->all();
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
        return $this->rememberPaidDashboardJson(
            $request,
            'ips',
            fn () => $this->ipsPayload($request),
        );
    }

    private function ipsPayload(Request $request): array
    {
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        if ($domainIds->isEmpty()) {
            return [];
        }

        $rows = $this->resolveIpRows($request, $domainIds, $metricFrom, $metricTo);

        return $rows->take(50)->values()->all();
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
        $deviceId = trim((string) $request->query('device_id', ''));
        $paidIdentityId = trim((string) $request->query('paid_identity_id', ''));
        $visitorId = trim((string) $request->query('visitor_id', ''));

        if ($domainIds->isEmpty() || ! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        $campaignExpr = Schema::hasColumn('visits', 'campaign_name')
            ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), ''))"
            : "NULLIF(TRIM(utm_campaign), '')";
        $paidIdExpr = Schema::hasColumn('visits', 'gclid')
            ? "COALESCE(NULLIF(TRIM(gclid), ''), NULLIF(TRIM(gbraid), ''), NULLIF(TRIM(wbraid), ''))"
            : "NULL";

        $select = [
            'ip',
            'visited_at',
            'url',
            'country',
            'browser',
            'os',
            'device',
            'is_invalid_traffic',
            'threat_group',
            'action_taken',
            'detection_reasons',
            DB::raw("{$campaignExpr} as campaign"),
            DB::raw("{$paidIdExpr} as paid_id"),
            DB::raw(Schema::hasColumn('visits', 'gclid') ? 'gclid' : 'NULL as gclid'),
            DB::raw(Schema::hasColumn('visits', 'gbraid') ? 'gbraid' : 'NULL as gbraid'),
            DB::raw(Schema::hasColumn('visits', 'wbraid') ? 'wbraid' : 'NULL as wbraid'),
            DB::raw(Schema::hasColumn('visits', 'utm_term') ? 'utm_term as keyword' : 'NULL as keyword'),
            DB::raw(Schema::hasColumn('visits', 'threat_type') ? 'threat_type' : 'NULL as threat_type'),
        ];
        foreach ([
            'device_id',
            'browser_id',
            'visitor_id',
            'fingerprint_id',
            'paid_identity_id',
            'identity_confidence',
            'ads_detections',
            'browser_version',
        ] as $col) {
            if (Schema::hasColumn('visits', $col)) {
                $select[] = $col;
            }
        }

        // Modal must keep the dashboard date range for this IP (do not reuse search's
        // "skip dates when ip= is set" behavior from scopedVisitsQuery).
        // Related Device/PID clicks use a rolling 24h window so Clicks 60m / ADS_REPEAT
        // evidence is visible even when those hits used different IPs.
        $query = DB::table('visits')->whereIn('domain_id', $domainIds);
        GoogleClickAttribution::applyHasClickIdFilter($query);

        $reportingTz = $this->reportingTimezone($request, $domainIds);
        $identityLookback = Carbon::now('UTC')->subDay();
        $query->where(function ($match) use ($ip, $deviceId, $paidIdentityId, $visitorId, $identityLookback, $metricFrom, $metricTo, $user, $reportingTz): void {
            $match->where(function ($thisIp) use ($ip, $metricFrom, $metricTo, $user, $reportingTz): void {
                $thisIp->where('ip', $ip);
                UserTimezone::applyCalendarDateRangeFilter(
                    $thisIp,
                    'visited_at',
                    $metricFrom,
                    $metricTo,
                    $user,
                    $reportingTz,
                );
            });

            $match->orWhere(function ($related) use ($deviceId, $paidIdentityId, $visitorId, $identityLookback): void {
                $related->where('visited_at', '>=', $identityLookback);
                $related->where(function ($ids) use ($deviceId, $paidIdentityId, $visitorId): void {
                    $added = false;
                    if ($deviceId !== '' && Schema::hasColumn('visits', 'device_id')) {
                        $ids->orWhere('device_id', $deviceId);
                        $added = true;
                    }
                    if ($paidIdentityId !== '' && Schema::hasColumn('visits', 'paid_identity_id')) {
                        $ids->orWhere('paid_identity_id', $paidIdentityId);
                        $added = true;
                    }
                    if ($visitorId !== '' && Schema::hasColumn('visits', 'visitor_id')) {
                        $ids->orWhere('visitor_id', $visitorId);
                        $added = true;
                    }
                    if (! $added) {
                        $ids->whereRaw('0 = 1');
                    }
                });
            });
        });

        if ($this->hasCampaignFilter($request)) {
            if (Schema::hasColumn('visits', 'campaign_name')) {
                $this->applyDirectCampaignFilter($query, $request, 'campaign_name', 'google_campaign_id', 'utm_campaign');
            } else {
                $this->applyDirectCampaignFilter($query, $request, 'utm_campaign', 'google_campaign_id');
            }
        }

        $rows = $query
            ->orderBy('visited_at')
            ->limit(100)
            ->get($select);

        if ($rows->isEmpty()) {
            return response()->json($this->ipClicksFromPaidMarketing($request, $domainIds, $metricFrom, $metricTo, $ip));
        }

        return response()->json($rows->map(function ($row) use ($user, $ip) {
            $reasons = [];
            if (! empty($row->detection_reasons)) {
                $decoded = json_decode((string) $row->detection_reasons, true);
                $reasons = is_array($decoded) ? $decoded : [];
            }

            $ads = $row->ads_detections ?? null;
            if (is_string($ads)) {
                $ads = json_decode($ads, true);
            }
            $ads = is_array($ads) ? $ads : [];
            foreach ($ads as $rule) {
                if (! is_array($rule)) {
                    continue;
                }
                $code = (string) ($rule['rule_code'] ?? $rule['code'] ?? '');
                if ($code !== '' && ! in_array($code, $reasons, true)) {
                    $reasons[] = $code;
                }
            }

            $confidence = isset($row->identity_confidence) && is_numeric($row->identity_confidence)
                ? (float) $row->identity_confidence
                : null;

            $campaign = trim((string) ($row->campaign ?? ''));
            if ($campaign === '' && filled($row->url ?? null)) {
                $queryString = parse_url((string) $row->url, PHP_URL_QUERY);
                if (is_string($queryString) && $queryString !== '') {
                    parse_str($queryString, $params);
                    foreach (['utm_campaign', 'campaign', 'gad_campaignid', 'campaign_id'] as $key) {
                        $value = trim((string) ($params[$key] ?? ''));
                        if ($value !== '') {
                            $campaign = $value;
                            break;
                        }
                    }
                }
            }

            $keyword = trim((string) ($row->keyword ?? ''));
            if ($keyword === '' && filled($row->url ?? null)) {
                $queryString = parse_url((string) $row->url, PHP_URL_QUERY);
                if (is_string($queryString) && $queryString !== '') {
                    parse_str($queryString, $params);
                    foreach (['utm_term', 'keyword'] as $key) {
                        $value = trim((string) ($params[$key] ?? ''));
                        if ($value !== '') {
                            $keyword = $value;
                            break;
                        }
                    }
                }
            }

            $rowIp = trim((string) ($row->ip ?? $ip));
            $isRelated = $rowIp !== '' && $rowIp !== $ip;

            return [
                'clicked_at' => UserTimezone::isoForUser(
                    ! empty($row->visited_at) ? Carbon::parse((string) $row->visited_at, 'UTC') : null,
                    $user
                ),
                'last_click_at' => UserTimezone::isoForUser(
                    ! empty($row->visited_at) ? Carbon::parse((string) $row->visited_at, 'UTC') : null,
                    $user
                ),
                'ip' => $rowIp !== '' ? $rowIp : $ip,
                'is_related' => $isRelated,
                'country' => $row->country,
                'campaign' => $campaign !== '' ? $campaign : null,
                'path' => $row->url,
                'paid_id' => $row->paid_id,
                'gclid' => $row->gclid ?? null,
                'gbraid' => $row->gbraid ?? null,
                'wbraid' => $row->wbraid ?? null,
                'keyword' => $keyword !== '' ? $keyword : null,
                'browser_name' => $row->browser,
                'browser_version' => $row->browser_version ?? null,
                'os' => $row->os,
                'device' => $row->device ?? null,
                'device_id' => filled($row->device_id ?? null) ? (string) $row->device_id : null,
                'browser_id' => filled($row->browser_id ?? null) ? (string) $row->browser_id : null,
                'visitor_id' => filled($row->visitor_id ?? null) ? (string) $row->visitor_id : null,
                'fingerprint_id' => filled($row->fingerprint_id ?? null) ? (string) $row->fingerprint_id : null,
                'paid_identity_id' => filled($row->paid_identity_id ?? null) ? (string) $row->paid_identity_id : null,
                'identity_confidence' => $confidence,
                'identity_confidence_label' => $this->identityConfidenceLabel($confidence),
                'ads_detections' => $ads,
                'threat_group' => $row->threat_group,
                'threat_type' => Schema::hasColumn('visits', 'threat_type')
                    ? ($row->threat_type ?? null)
                    : ($row->action_taken ?? null),
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
                    'gclid' => $row->paid_id,
                    'gbraid' => null,
                    'wbraid' => null,
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
            if (function_exists('set_time_limit')) {
                @set_time_limit(120);
            }
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'IP Address',
                'Device ID',
                'Paid Identity',
                'Identity Confidence',
                'Clicks 60m',
                'Primary Detection',
                'Country',
                'Campaign',
                'Device',
                'Fingerprint',
                'Browser',
                'Risk',
                'Risk %',
                'Action',
                'IP Exclusion',
                'Invalid',
                'Valid',
                'Total',
                'Bot Detect',
                'First Seen',
                'Evidence Time',
            ]);

            if ($domainIds->isEmpty()
                || (! Schema::hasTable('visits') && ! Schema::hasTable('paid_marketing_visits'))) {
                fclose($handle);

                return;
            }

            $rows = $this->resolveIpRows($request, $domainIds, $metricFrom, $metricTo);

            foreach ($rows->take(5000) as $r) {
                fputcsv($handle, [
                    $r['ip'],
                    $r['device_id'] ?? '',
                    $r['paid_identity_id'] ?? '',
                    $r['identity_confidence_label'] ?? '',
                    $r['clicks_60m'] ?? '',
                    $r['primary_detection'] ?? '',
                    $r['country'],
                    $r['campaign'],
                    $r['device'] ?? '',
                    $r['device_fingerprint'] ?? '',
                    $r['browser'] ?? '',
                    $r['risk_level'] ?? '',
                    $r['risk_score'] ?? '',
                    $r['action'] ?? '',
                    $r['ip_exclusion'] ?? '',
                    $r['invalid'],
                    $r['valid'] ?? max(0, (int) ($r['total'] ?? 0) - (int) ($r['invalid'] ?? 0)),
                    $r['total'],
                    $r['top_threat'],
                    $r['first_seen'] ?? '',
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
        return $this->rememberPaidDashboardJson(
            $request,
            'heatmap',
            fn () => $this->heatmapPayload($request),
        );
    }

    private function heatmapPayload(Request $request): array
    {
        if (! Schema::hasTable('visits')
            && (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits'))) {
            return ['matrix' => [], 'days' => [], 'hours' => []];
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

        return [
            'days' => $days,
            'hours' => $hours,
            'matrix' => $matrix,
        ];
    }

    /**
     * @return list<array{domain_id: int, message: string}>
     */
    private function syncGoogleMetricsForDomains(Request $request, $domains, string $fromDate, string $toDate, bool $force = false): array
    {
        if (! Schema::hasTable('google_ads_campaign_daily_metrics') || $domains->isEmpty()) {
            return [];
        }

        $sync = app(GoogleAdsDomainMetricsSync::class);
        $reportingTz = $this->reportingTimezone($request, $domains->pluck('id'));
        $errors = [];

        foreach ($domains as $domain) {
            if (! $domain->googleAdsAccount || $domain->googleAdsAccount->is_manager) {
                continue;
            }

            $googleTz = UserTimezone::isValid($domain->googleAdsAccount->time_zone)
                ? $domain->googleAdsAccount->time_zone
                : $reportingTz;
            [$syncFrom, $syncTo] = UserTimezone::googleMetricDateBounds($fromDate, $toDate, $reportingTz, $googleTz);

            if (! ($force || $sync->shouldRefresh($domain, $syncFrom, $syncTo))) {
                continue;
            }

            $result = $sync->syncDomain($domain, $syncFrom, $syncTo);
            $message = trim((string) ($result['api_error'] ?? $result['message'] ?? ''));
            if (($result['saved'] ?? 0) === 0 && $message !== '') {
                $errors[] = [
                    'domain_id' => (int) $domain->id,
                    'message' => $message,
                ];
            }
        }

        return $errors;
    }

    private function googleSyncLooksAuthRelated(?string $message): bool
    {
        $message = strtolower(trim((string) $message));
        if ($message === '') {
            return false;
        }

        foreach ([
            '401',
            'unauthenticated',
            'invalid_grant',
            'refresh token',
            'oauth token',
            'token has been expired or revoked',
            'no refresh token',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function googleReconnectUrl(int $domainId = 0): string
    {
        if ($domainId > 0) {
            return route('integrations.google.redirect', [
                'domain_id' => $domainId,
                'context' => 'paid_domain',
            ]);
        }

        return route('integrations.google.redirect');
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

        if ($accountId = (int) $request->query('google_ads_account_id', 0)) {
            return Domain::query()
                ->where('user_id', $request->user()->id)
                ->forPaidMarketing()
                ->where('google_ads_account_id', $accountId)
                ->pluck('id')
                ->values();
        }

        return $userDomainIds;
    }

    private function scopedVisitsQuery(Request $request, $domainIds, string $fromDate, string $toDate)
    {
        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds);

        $search = trim((string) $request->query('ip', ''));
        // IP / GCLID / Device ID search: do not hide rows just because the date chip is narrow.
        if ($search === '') {
            UserTimezone::applyCalendarDateRangeFilter(
                $query,
                'visited_at',
                $fromDate,
                $toDate,
                $request->user(),
                $this->reportingTimezone($request, $domainIds),
            );
        }

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

        $this->applyAdvancedSearchFilter($query, $request, 'visits');

        return $query;
    }

    /**
     * Advanced View / dashboard search box: IP, GCLID/GBRAID/WBRAID, or Device ID.
     * Applied on visits queries and paid_marketing click inventory.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  'visits'|'paid_marketing'  $source
     */
    private function applyAdvancedSearchFilter($query, Request $request, string $source = 'visits'): void
    {
        $term = trim((string) $request->query('ip', ''));
        if ($term === '') {
            return;
        }

        $compact = trim(preg_replace('/\s+/', '', $term) ?? $term);
        $isDeviceId = (bool) preg_match('/^DEV_[A-Za-z0-9]+$/i', $compact);
        $isClickId = ! $isDeviceId
            && ! filter_var($compact, FILTER_VALIDATE_IP)
            && strlen($compact) >= 12
            && (bool) preg_match('/^[A-Za-z0-9_-]+$/', $compact);

        // Device IDs must not fall through to ip LIKE — that returns unrelated IPs
        // and then the UI hydrates the latest (often different) device on each IP.
        if ($isDeviceId) {
            if ($source === 'paid_marketing') {
                if (Schema::hasColumn('paid_marketing_clicks', 'device_id')) {
                    $query->where(function ($match) use ($compact): void {
                        $match->where('pc.device_id', $compact)
                            ->orWhere('pc.device_id', 'like', $compact.'%');
                    });
                } else {
                    $query->whereRaw('0 = 1');
                }

                return;
            }

            if (Schema::hasColumn('visits', 'device_id')) {
                $query->where(function ($match) use ($compact): void {
                    $match->where('device_id', $compact)
                        ->orWhere('device_id', 'like', $compact.'%');
                });
            } else {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        if ($source === 'paid_marketing') {
            $query->where(function ($match) use ($term, $compact, $isClickId): void {
                $match->where('pc.ip', 'like', '%'.$term.'%');

                if ($isClickId) {
                    foreach (['paid_id', 'gclid', 'gbraid', 'wbraid'] as $col) {
                        if (Schema::hasColumn('paid_marketing_clicks', $col)) {
                            $match->orWhere("pc.{$col}", $compact)
                                ->orWhere("pc.{$col}", 'like', '%'.$compact.'%');
                        }
                    }
                }
            });

            return;
        }

        $query->where(function ($match) use ($term, $compact, $isClickId): void {
            $match->where('ip', 'like', '%'.$term.'%');

            if ($isClickId) {
                foreach (['gclid', 'gbraid', 'wbraid'] as $col) {
                    if (Schema::hasColumn('visits', $col)) {
                        $match->orWhere($col, $compact)
                            ->orWhere($col, 'like', '%'.$compact.'%');
                    }
                }
            }
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ipRowsFromVisits(Request $request, $domainIds, string $fromDate, string $toDate, int $limit = 5000)
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
                DB::raw('MIN(visited_at) as first_seen'),
                DB::raw('MAX(visited_at) as last_seen'),
                DB::raw(Schema::hasColumn('visits', 'campaign_name')
                    ? 'MAX(COALESCE(campaign_name, utm_campaign)) as campaign'
                    : 'MAX(utm_campaign) as campaign'),
                DB::raw(Schema::hasColumn('visits', 'device') ? 'MAX(device) as device' : 'NULL as device'),
                DB::raw(Schema::hasColumn('visits', 'browser') ? 'MAX(browser) as browser' : 'NULL as browser'),
                DB::raw(Schema::hasColumn('visits', 'action_taken') ? 'MAX(action_taken) as action' : 'NULL as action'),
                DB::raw("SUM(CASE WHEN threat_group = 'vpn' THEN 1 ELSE 0 END) as vpn_hits"),
                DB::raw("SUM(CASE WHEN threat_group = 'data_center' THEN 1 ELSE 0 END) as data_center_hits"),
                DB::raw("SUM(CASE WHEN threat_group = 'malicious' THEN 1 ELSE 0 END) as malicious_hits"),
                DB::raw('MAX(threat_group) as top_threat'),
                DB::raw(Schema::hasColumn('visits', 'threat_score')
                    ? 'MAX(threat_score) as threat_score'
                    : 'NULL as threat_score')
            )
            ->groupBy('ip')
            ->orderByDesc('total')
            ->limit(max(1, min($limit, 5000)))
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ipRowsFromPaidMarketing(Request $request, $domainIds, string $fromDate, string $toDate, int $limit = 5000)
    {
        if (! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits')) {
            return collect();
        }

        $query = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->whereIn('pv.domain_id', $domainIds);

        $search = trim((string) $request->query('ip', ''));
        if ($search === '') {
            UserTimezone::applyCalendarDateRangeFilter(
                $query,
                'pc.clicked_at',
                $fromDate,
                $toDate,
                $request->user(),
                $this->reportingTimezone($request, $domainIds),
            );
        }

        $this->applyPaidTrafficOnlyFilter($query, 'pc');

        if ($this->hasCampaignFilter($request)) {
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign_name', 'pc.google_campaign_id', 'pc.campaign');
            } else {
                $this->applyDirectCampaignFilter($query, $request, 'pc.campaign', 'pc.google_campaign_id');
            }
        }

        $this->applyAdvancedSearchFilter($query, $request, 'paid_marketing');

        return $query
            ->select(
                'pc.ip as ip',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != '' THEN 1 ELSE 0 END) as invalid"),
                DB::raw('MAX(pc.country) as country'),
                DB::raw('MIN(pc.clicked_at) as first_seen'),
                DB::raw('MAX(pc.clicked_at) as last_seen'),
                DB::raw(Schema::hasColumn('paid_marketing_clicks', 'campaign_name')
                    ? 'MAX(COALESCE(pc.campaign_name, pc.campaign, pv.campaign_name, pv.campaign)) as campaign'
                    : 'MAX(COALESCE(pc.campaign, pv.campaign)) as campaign'),
                DB::raw('MAX(pc.os) as device'),
                DB::raw('MAX(pc.browser_name) as browser'),
                DB::raw("MAX(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != '' THEN 'block' ELSE 'allow' END) as action"),
                DB::raw("SUM(CASE WHEN pc.threat_group = 'vpn' THEN 1 ELSE 0 END) as vpn_hits"),
                DB::raw("SUM(CASE WHEN pc.threat_group = 'data_center' THEN 1 ELSE 0 END) as data_center_hits"),
                DB::raw("SUM(CASE WHEN pc.threat_group = 'malicious' THEN 1 ELSE 0 END) as malicious_hits"),
                DB::raw('MAX(pc.threat_group) as top_threat'),
                DB::raw('NULL as threat_score')
            )
            ->groupBy('pc.ip')
            ->orderByDesc('total')
            ->limit(max(1, min($limit, 5000)))
            ->get();
    }

    /**
     * Paid IPs only (is_paid_traffic = 1): visits table first, legacy clicks fallback.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function ipInventory(Request $request, int $limit = 5000)
    {
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);
        $domainIds = $this->scopedDomainIds($request);
        if ($domainIds->isEmpty()) {
            return collect();
        }

        return $this->resolveIpRows($request, $domainIds, $metricFrom, $metricTo, $limit);
    }

    /**
     * Paid IPs only (is_paid_traffic = 1): visits table first, legacy clicks fallback.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function resolveIpRows(Request $request, $domainIds, string $fromDate, string $toDate, int $limit = 5000)
    {
        $cap = max(1, min($limit, 5000));
        $rows = $this->mergeIpRowSources(
            $this->ipRowsFromVisits($request, $domainIds, $fromDate, $toDate, $cap),
            $this->ipRowsFromPaidMarketing($request, $domainIds, $fromDate, $toDate, $cap),
        );

        $formatted = $this->formatIpRows($rows, $request->user(), $this->resolveActiveAllowListIps($request));

        return $this->attachPaidIdentityMeta(
            $this->attachDeviceFingerprints($formatted, $domainIds),
            $domainIds,
        )->take($cap)->values();
    }

    /**
     * Attach paid identity fields (Device ID / PID / confidence / 60m clicks / primary ADS rule).
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $domainIds
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function attachPaidIdentityMeta($rows, $domainIds)
    {
        $domainIdList = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->values();
        $ips = $rows->pluck('ip')->map(fn ($ip) => (string) $ip)->filter()->unique()->values();
        if ($ips->isEmpty() || $domainIdList->isEmpty() || ! Schema::hasTable('visits')) {
            return $rows->map(fn (array $row) => $this->withPaidIdentityDefaults($row));
        }

        $select = ['ip', 'visited_at'];
        foreach ([
            'device_id',
            'browser_id',
            'visitor_id',
            'fingerprint_id',
            'paid_identity_id',
            'identity_confidence',
            'ads_detections',
            'action_taken',
        ] as $col) {
            if (Schema::hasColumn('visits', $col)) {
                $select[] = $col;
            }
        }

        $metaByIp = [];
        $visitRows = DB::table('visits')
            ->whereIn('domain_id', $domainIdList->all())
            ->whereIn('ip', $ips->all())
            ->when(
                Schema::hasColumn('visits', 'is_paid_traffic'),
                fn ($query) => $query->where('is_paid_traffic', true)
            )
            ->orderByDesc('visited_at')
            ->get($select);

        foreach ($visitRows as $visit) {
            $ip = (string) ($visit->ip ?? '');
            if ($ip === '') {
                continue;
            }

            if (! isset($metaByIp[$ip])) {
                $ads = $visit->ads_detections ?? null;
                if (is_string($ads)) {
                    $ads = json_decode($ads, true);
                }
                $ads = is_array($ads) ? $ads : [];
                $primary = null;
                $primaryPoints = -1;
                foreach ($ads as $rule) {
                    if (! is_array($rule)) {
                        continue;
                    }
                    $code = (string) ($rule['rule_code'] ?? $rule['code'] ?? '');
                    if ($code === '') {
                        continue;
                    }
                    $points = (int) ($rule['base_points'] ?? $rule['points'] ?? 0);
                    if ($points >= $primaryPoints) {
                        $primaryPoints = $points;
                        $primary = $code;
                    }
                }

                $confidence = isset($visit->identity_confidence) && is_numeric($visit->identity_confidence)
                    ? (float) $visit->identity_confidence
                    : null;

                $metaByIp[$ip] = [
                    'device_id' => filled($visit->device_id ?? null) ? (string) $visit->device_id : null,
                    'browser_id' => filled($visit->browser_id ?? null) ? (string) $visit->browser_id : null,
                    'visitor_id' => filled($visit->visitor_id ?? null) ? (string) $visit->visitor_id : null,
                    'fingerprint_id' => filled($visit->fingerprint_id ?? null) ? (string) $visit->fingerprint_id : null,
                    'paid_identity_id' => filled($visit->paid_identity_id ?? null) ? (string) $visit->paid_identity_id : null,
                    'identity_confidence' => $confidence,
                    'identity_confidence_label' => $this->identityConfidenceLabel($confidence),
                    'primary_detection' => $primary,
                    'triggered_rules_count' => count($ads),
                    'latest_action_taken' => filled($visit->action_taken ?? null) ? (string) $visit->action_taken : null,
                    '_devices' => [],
                    '_browsers' => [],
                    '_visitors' => [],
                    '_fingerprints' => [],
                    '_pids' => [],
                ];
            }

            foreach ([
                '_devices' => 'device_id',
                '_browsers' => 'browser_id',
                '_visitors' => 'visitor_id',
                '_fingerprints' => 'fingerprint_id',
                '_pids' => 'paid_identity_id',
            ] as $bag => $col) {
                $val = trim((string) ($visit->{$col} ?? ''));
                if ($val !== '') {
                    $metaByIp[$ip][$bag][$val] = true;
                }
            }
        }

        foreach ($metaByIp as $ip => &$meta) {
            $deviceCount = count($meta['_devices']);
            $browserCount = count($meta['_browsers']);
            $visitorCount = count($meta['_visitors']);
            $fpCount = count($meta['_fingerprints']);
            $pidCount = count($meta['_pids']);
            unset($meta['_devices'], $meta['_browsers'], $meta['_visitors'], $meta['_fingerprints'], $meta['_pids']);

            $meta['distinct_device_count'] = $deviceCount;
            $meta['distinct_browser_count'] = $browserCount;
            $meta['distinct_visitor_count'] = $visitorCount;
            $meta['distinct_fingerprint_count'] = $fpCount;
            $meta['distinct_paid_identity_count'] = $pidCount;
            $meta['multi_identity'] = $deviceCount > 1 || $fpCount > 1 || $visitorCount > 1 || $browserCount > 1;

            // IP row is not a single-device story — do not show "High" identity when IDs churn.
            if ($meta['multi_identity']) {
                $meta['identity_confidence'] = min((float) ($meta['identity_confidence'] ?? 0.55), 0.55);
                $meta['identity_confidence_label'] = 'Shared IP · '.$deviceCount.' devices';
                if ($deviceCount > 1 && filled($meta['device_id'])) {
                    $meta['device_id_label'] = $meta['device_id'].' +'.($deviceCount - 1);
                } else {
                    $meta['device_id_label'] = $meta['device_id'];
                }
            } else {
                $meta['device_id_label'] = $meta['device_id'];
            }
        }
        unset($meta);

        $clicks60ByIp = [];
        $clicks60ByPid = [];
        if (Schema::hasTable('click_windows')) {
            $windowRows = DB::table('click_windows')
                ->whereIn('domain_id', $domainIdList->all())
                ->where('window_key', '60m')
                ->where(function ($q) use ($ips, $metaByIp): void {
                    $q->where(function ($inner) use ($ips): void {
                        $inner->where('entity_type', 'ip')->whereIn('entity_id', $ips->all());
                    });
                    $pids = collect($metaByIp)->pluck('paid_identity_id')->filter()->unique()->values();
                    if ($pids->isNotEmpty()) {
                        $q->orWhere(function ($inner) use ($pids): void {
                            $inner->where('entity_type', 'paid_identity')->whereIn('entity_id', $pids->all());
                        });
                    }
                })
                ->get(['entity_type', 'entity_id', 'click_count']);

            foreach ($windowRows as $win) {
                $count = (int) ($win->click_count ?? 0);
                if (($win->entity_type ?? '') === 'ip') {
                    $clicks60ByIp[(string) $win->entity_id] = max($clicks60ByIp[(string) $win->entity_id] ?? 0, $count);
                }
                if (($win->entity_type ?? '') === 'paid_identity') {
                    $clicks60ByPid[(string) $win->entity_id] = max($clicks60ByPid[(string) $win->entity_id] ?? 0, $count);
                }
            }
        }

        return $rows->map(function (array $row) use ($metaByIp, $clicks60ByIp, $clicks60ByPid, $visitRows) {
            $ip = (string) ($row['ip'] ?? '');
            $meta = $metaByIp[$ip] ?? [];
            $pid = $meta['paid_identity_id'] ?? null;
            $windowEnd = ! empty($row['last_seen'])
                ? Carbon::parse((string) $row['last_seen'], 'UTC')
                : null;
            $derivedIp60 = $windowEnd
                ? $visitRows
                    ->filter(function ($visit) use ($ip, $windowEnd): bool {
                        if ((string) ($visit->ip ?? '') !== $ip || empty($visit->visited_at)) {
                            return false;
                        }
                        $at = Carbon::parse((string) $visit->visited_at, 'UTC');

                        return $at->lte($windowEnd) && $at->gte($windowEnd->copy()->subMinutes(60));
                    })
                    ->count()
                : 0;
            $clicks60 = max(
                (int) ($clicks60ByIp[$ip] ?? 0),
                $pid ? (int) ($clicks60ByPid[$pid] ?? 0) : 0,
                $derivedIp60,
            );

            $actionHint = (string) ($meta['latest_action_taken'] ?? $row['action'] ?? '');
            $multi = (bool) ($meta['multi_identity'] ?? false);

            return array_merge($this->withPaidIdentityDefaults($row), $meta, [
                'clicks_60m' => $clicks60 > 0 ? $clicks60 : (int) ($row['total'] ?? 0),
                'ip_exclusion' => $this->ipExclusionLabel(
                    $actionHint,
                    (string) ($meta['primary_detection'] ?? ''),
                    $multi,
                ),
                // Shared IP with many devices → do not imply a single device was blocked.
                'block_scope' => $multi
                    ? 'IP (multi-device)'
                    : (in_array(strtolower($actionHint), ['block', 'blocked'], true) ? 'Device' : null),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withPaidIdentityDefaults(array $row): array
    {
        $row['device_id'] = $row['device_id'] ?? null;
        $row['browser_id'] = $row['browser_id'] ?? null;
        $row['visitor_id'] = $row['visitor_id'] ?? null;
        $row['fingerprint_id'] = $row['fingerprint_id'] ?? null;
        $row['paid_identity_id'] = $row['paid_identity_id'] ?? null;
        $row['identity_confidence'] = $row['identity_confidence'] ?? null;
        $row['identity_confidence_label'] = $row['identity_confidence_label']
            ?? $this->identityConfidenceLabel(
                is_numeric($row['identity_confidence'] ?? null) ? (float) $row['identity_confidence'] : null
            );
        $row['primary_detection'] = $row['primary_detection'] ?? null;
        $row['triggered_rules_count'] = $row['triggered_rules_count'] ?? 0;
        $row['clicks_60m'] = $row['clicks_60m'] ?? (int) ($row['total'] ?? 0);
        $row['ip_exclusion'] = $row['ip_exclusion'] ?? 'Not needed';
        $row['block_scope'] = $row['block_scope'] ?? null;
        $row['distinct_device_count'] = $row['distinct_device_count'] ?? null;
        $row['distinct_browser_count'] = $row['distinct_browser_count'] ?? null;
        $row['distinct_visitor_count'] = $row['distinct_visitor_count'] ?? null;
        $row['distinct_fingerprint_count'] = $row['distinct_fingerprint_count'] ?? null;
        $row['multi_identity'] = $row['multi_identity'] ?? false;
        $row['device_id_label'] = $row['device_id_label'] ?? $row['device_id'];

        return $row;
    }

    private function identityConfidenceLabel(?float $confidence): string
    {
        if ($confidence === null) {
            return 'Unknown';
        }
        if ($confidence >= 0.95) {
            return 'Very High';
        }
        if ($confidence >= 0.85) {
            return 'High';
        }
        if ($confidence >= 0.70) {
            return 'Medium';
        }
        if ($confidence >= 0.40) {
            return 'Low';
        }

        return 'Unknown';
    }

    private function ipExclusionLabel(string $action, string $primaryDetection = '', bool $multiIdentity = false): string
    {
        // Multi-device shared IP must never look like a clean Google IP exclusion queue.
        if ($multiIdentity) {
            return 'Suppressed (shared IP)';
        }

        $primary = strtoupper(trim($primaryDetection));
        // Correlated / supporting attribution rules must not claim exclusion queue.
        if ($primary !== '' && (
            str_contains($primary, 'GCLID_DUP')
            || str_contains($primary, 'CLICKID_MISSING')
            || str_contains($primary, 'ADS_IP_')
        )) {
            return 'Not needed';
        }

        if ($primary !== '' && (
            str_starts_with($primary, 'ADS_REPEAT')
            || str_contains($primary, 'KNOWN_FRAUD')
        )) {
            return 'Queued';
        }

        $action = strtolower(trim($action));
        if (in_array($action, ['block', 'blocked'], true)) {
            return 'Not needed';
        }

        return 'Not needed';
    }

    /**
     * Attach Advanced View fingerprint (behavior_fingerprint, else UA hash) per IP.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $domainIds
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function attachDeviceFingerprints($rows, $domainIds)
    {
        $domainIdList = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->values();
        $ips = $rows->pluck('ip')->map(fn ($ip) => (string) $ip)->filter()->unique()->values();
        if ($ips->isEmpty() || $domainIdList->isEmpty()) {
            return $rows->map(function (array $row) {
                $row['device_fingerprint'] = $row['device_fingerprint'] ?? null;

                return $row;
            });
        }

        $fpByIp = [];

        if (Schema::hasTable('visit_session_recordings')
            && Schema::hasColumn('visit_session_recordings', 'behavior_fingerprint')) {
            $recordingRows = DB::table('visit_session_recordings')
                ->whereIn('domain_id', $domainIdList->all())
                ->whereIn('ip', $ips->all())
                ->whereNotNull('behavior_fingerprint')
                ->where('behavior_fingerprint', '!=', '')
                ->orderByDesc('id')
                ->get(['ip', 'behavior_fingerprint']);

            foreach ($recordingRows as $recording) {
                $ip = (string) ($recording->ip ?? '');
                if ($ip === '' || isset($fpByIp[$ip])) {
                    continue;
                }
                $fpByIp[$ip] = (string) $recording->behavior_fingerprint;
            }
        }

        $missing = $ips->filter(fn ($ip) => ! isset($fpByIp[$ip]))->values();
        if ($missing->isNotEmpty()
            && Schema::hasTable('visits')
            && Schema::hasColumn('visits', 'user_agent')) {
            $uaRows = DB::table('visits')
                ->whereIn('domain_id', $domainIdList->all())
                ->whereIn('ip', $missing->all())
                ->whereNotNull('user_agent')
                ->where('user_agent', '!=', '')
                ->orderByDesc('visited_at')
                ->get(['ip', 'user_agent']);

            foreach ($uaRows as $uaRow) {
                $ip = (string) ($uaRow->ip ?? '');
                if ($ip === '' || isset($fpByIp[$ip])) {
                    continue;
                }
                $fpByIp[$ip] = substr(hash('sha256', (string) $uaRow->user_agent), 0, 16);
            }
        }

        return $rows->map(function (array $row) use ($fpByIp) {
            $ip = (string) ($row['ip'] ?? '');
            $row['device_fingerprint'] = $fpByIp[$ip] ?? ($row['device_fingerprint'] ?? null);

            return $row;
        });
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
            $firstCandidates = array_values(array_filter([
                (string) ($existing->first_seen ?? ''),
                (string) ($row->first_seen ?? ''),
            ], fn ($v) => $v !== ''));
            $byIp[$ip] = (object) [
                'ip' => $ip,
                'total' => max((int) ($existing->total ?? 0), (int) ($row->total ?? 0)),
                'invalid' => max((int) ($existing->invalid ?? 0), (int) ($row->invalid ?? 0)),
                'country' => $existing->country ?? $row->country ?? null,
                'first_seen' => $firstCandidates !== [] ? min($firstCandidates) : null,
                'last_seen' => max(
                    (string) ($existing->last_seen ?? ''),
                    (string) ($row->last_seen ?? '')
                ) ?: null,
                'campaign' => $existing->campaign ?? $row->campaign ?? null,
                'device' => $existing->device ?? $row->device ?? null,
                'browser' => $existing->browser ?? $row->browser ?? null,
                'action' => $existing->action ?? $row->action ?? null,
                'vpn_hits' => max((int) ($existing->vpn_hits ?? 0), (int) ($row->vpn_hits ?? 0)),
                'data_center_hits' => max((int) ($existing->data_center_hits ?? 0), (int) ($row->data_center_hits ?? 0)),
                'malicious_hits' => max((int) ($existing->malicious_hits ?? 0), (int) ($row->malicious_hits ?? 0)),
                'top_threat' => $existing->top_threat ?? $row->top_threat ?? null,
                'threat_score' => max((int) ($existing->threat_score ?? 0), (int) ($row->threat_score ?? 0)) ?: null,
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
        $ips = $rows->map(fn ($row) => (string) ($row->ip ?? ''))->filter()->unique()->values();
        $intelByIp = collect();
        if ($ips->isNotEmpty() && Schema::hasTable('ip_logs')) {
            $intelByIp = DB::table('ip_logs')
                ->whereIn('ip', $ips->all())
                ->get(['ip', 'intel_isp', 'abuse_confidence_score', 'ipdetails_abuser_score', 'ipdetails_raw'])
                ->keyBy('ip');
        }

        return $rows->map(function ($row) use ($user, $allowListIps, $intelByIp) {
            $ip = (string) ($row->ip ?? '');
            $intel = $intelByIp->get($ip);
            $raw = [];
            if (! empty($intel?->ipdetails_raw)) {
                $decoded = is_string($intel->ipdetails_raw)
                    ? json_decode($intel->ipdetails_raw, true)
                    : (array) $intel->ipdetails_raw;
                $raw = is_array($decoded) ? $decoded : [];
            }
            $invalid = (int) ($row->invalid ?? 0);
            $totalClicks = (int) ($row->total ?? 0);
            $risk = IpRowRiskScorer::score([
                'invalid' => $invalid,
                'total' => $totalClicks,
                'threat_score' => $row->threat_score ?? null,
                'intel_score' => $intel?->ipdetails_abuser_score ?? $intel?->abuse_confidence_score,
                'top_threat' => $row->top_threat ?? null,
                'vpn_hits' => (int) ($row->vpn_hits ?? 0),
                'data_center_hits' => (int) ($row->data_center_hits ?? 0),
                'malicious_hits' => (int) ($row->malicious_hits ?? 0),
            ]);
            $riskLevel = $risk['risk_level'];
            $scorePct = $risk['risk_score'];

            $actionRaw = strtolower(trim((string) data_get($row, 'action', '')));
            $isAllowlisted = GlobalIpAllowlist::matches($ip, [
                'isp' => $intel?->intel_isp,
                'org' => $raw['company'] ?? $raw['org'] ?? $intel?->intel_isp,
                'asn' => $raw['ASN'] ?? $raw['asn'] ?? $raw['as_number'] ?? data_get($raw, 'connection.asn'),
                'raw' => $raw,
            ]) || IpFraudEvaluator::isIpAllowListed($ip, implode("\n", $allowListIps));
            if ($isAllowlisted) {
                $actionLabel = 'Whitelisted';
                $actionTone = 'allow';
            } elseif (in_array($actionRaw, ['block', 'blocked', 'deny'], true) || ($invalid > 0 && $riskLevel === 'High')) {
                $actionLabel = 'Blocked';
                $actionTone = 'block';
            } elseif (in_array($actionRaw, ['flag', 'flagged', 'monitor', 'monitored', 'challenge'], true) || $invalid > 0) {
                $actionLabel = 'Monitored';
                $actionTone = 'monitor';
            } elseif ($actionRaw !== '') {
                $actionLabel = ucfirst($actionRaw);
                $actionTone = 'monitor';
            } else {
                $actionLabel = 'Allow';
                $actionTone = 'allow';
            }

            $threatLabels = [];
            if ((int) ($row->vpn_hits ?? 0) > 0) {
                $threatLabels[] = 'VPN';
            }
            if ((int) ($row->data_center_hits ?? 0) > 0) {
                $threatLabels[] = 'Datacenter';
            }
            if ((int) ($row->malicious_hits ?? 0) > 0) {
                $threatLabels[] = 'Malicious';
            }
            $topThreat = strtolower(trim((string) ($row->top_threat ?? '')));
            $topThreatMap = [
                'vpn' => 'VPN',
                'proxy' => 'Proxy',
                'data_center' => 'Datacenter',
                'datacenter' => 'Datacenter',
                'malicious' => 'Malicious',
                'abnormal_rate_limit' => 'Abnormal Rate',
                'bot' => 'Bot Behavior',
                'repeated' => 'Repeated',
            ];
            if ($topThreat !== '' && isset($topThreatMap[$topThreat])) {
                $threatLabels[] = $topThreatMap[$topThreat];
            } elseif ($topThreat !== '') {
                $threatLabels[] = ucwords(str_replace('_', ' ', $topThreat));
            }
            if ($invalid >= 3 && ! in_array('Repeated', $threatLabels, true)) {
                $threatLabels[] = 'Repeated';
            }
            $threatLabels = array_values(array_unique($threatLabels));

            return [
                'ip' => $ip,
                'country' => $row->country ?? null,
                'total' => (int) ($row->total ?? 0),
                'invalid' => $invalid,
                'valid' => max(0, (int) ($row->total ?? 0) - $invalid),
                'first_seen' => UserTimezone::isoForUser(
                    ! empty($row->first_seen) ? Carbon::parse((string) $row->first_seen, 'UTC') : null,
                    $user
                ),
                'last_seen' => UserTimezone::isoForUser(
                    ! empty($row->last_seen) ? Carbon::parse((string) $row->last_seen, 'UTC') : null,
                    $user
                ),
                'campaign' => $row->campaign ?? null,
                'device' => $row->device ?? null,
                'browser' => $row->browser ?? null,
                'action' => $actionLabel,
                'action_tone' => $actionTone,
                'vpn_hits' => (int) ($row->vpn_hits ?? 0),
                'data_center_hits' => (int) ($row->data_center_hits ?? 0),
                'malicious_hits' => (int) ($row->malicious_hits ?? 0),
                'top_threat' => $row->top_threat ?? null,
                'threats' => $threatLabels,
                'threats_label' => $threatLabels !== [] ? implode(', ', $threatLabels) : '—',
                'risk_level' => $riskLevel,
                'risk_score' => $scorePct,
                'isp' => $intel?->intel_isp ?? ($raw['isp'] ?? $raw['company'] ?? null),
                'asn' => $raw['asn'] ?? null,
                'is_allowlisted' => $isAllowlisted,
            ];
        })->values();
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
     * Average Google Ads CPC for scoped domains/date range (used for Cost Saved).
     *
     * @param  \Illuminate\Support\Collection<int, int>  $domainIds
     * @param  \Illuminate\Support\Collection<int, Domain>|null  $domains
     */
    private function avgGoogleCpc(Request $request, $domainIds, string $metricFrom, string $metricTo, $domains = null): float
    {
        if (collect($domainIds)->isEmpty() || ! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return 0.0;
        }

        $reportingTz = $this->reportingTimezone($request, $domainIds);
        $domains ??= $this->scopedDomains($request, $domainIds);
        $googleAds = app(\App\Services\GoogleAdsDomainMetricsSync::class)
            ->clickTotalsForDomainsReporting($domainIds, $metricFrom, $metricTo, $reportingTz, $domains);
        $clicks = (int) ($googleAds['clicks'] ?? 0);
        $cost = (float) ($googleAds['cost'] ?? 0);

        return $clicks > 0 ? ($cost / $clicks) : 0.0;
    }

    /**
     * Active detection rules shown above the Protection Engine chart.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $domainIds
     * @return list<array{label: string, action: string, tone: string}>
     */
    /**
     * Protection Engine panel payload (detection rules + action lanes).
     *
     * @return array{
     *     active: bool,
     *     detection_rules: list<array{key: string, label: string, on: bool}>,
     *     protection_actions: list<array{key: string, label: string, desc: string, active: bool, tone: string}>,
     *     rules: list<array{label: string, action: string, tone: string}>
     * }
     */
    private function protectionEngineState(Request $request, $domainIds): array
    {
        $defaultDetection = [
            ['key' => 'vpn', 'label' => 'VPN Detection', 'on' => true],
            ['key' => 'proxy', 'label' => 'Proxy Detection', 'on' => true],
            ['key' => 'datacenter', 'label' => 'Datacenter Detection', 'on' => true],
            ['key' => 'repeated', 'label' => 'Repeated Click Detection', 'on' => true],
            ['key' => 'bot', 'label' => 'Bot Detection', 'on' => true],
            ['key' => 'abnormal', 'label' => 'Abnormal Behavior Detection', 'on' => true],
        ];

        $protectionActions = [
            ['key' => 'monitor', 'label' => 'Monitor', 'desc' => 'Low Risk Traffic', 'active' => true, 'tone' => 'low'],
            ['key' => 'challenge', 'label' => 'Challenge', 'desc' => 'Medium Risk Traffic', 'active' => true, 'tone' => 'medium'],
            ['key' => 'block', 'label' => 'Block', 'desc' => 'High Risk Traffic', 'active' => true, 'tone' => 'high'],
        ];

        $legacyRules = [
            ['label' => 'Bot traffic', 'action' => 'Monitor', 'tone' => 'monitor'],
            ['label' => 'Malicious', 'action' => 'Monitor', 'tone' => 'monitor'],
            ['label' => 'Suspicious', 'action' => 'Off', 'tone' => 'off'],
        ];

        if (collect($domainIds)->isEmpty() || ! Schema::hasTable('domain_detection_settings')) {
            return [
                'active' => true,
                'detection_rules' => $defaultDetection,
                'protection_actions' => $protectionActions,
                'rules' => $legacyRules,
            ];
        }

        $domainId = (int) $request->query('domain_id', 0);
        $query = DomainDetectionSetting::query()->whereIn('domain_id', collect($domainIds));
        if ($domainId > 0) {
            $query->where('domain_id', $domainId);
        }

        $settings = $query->get();
        if ($settings->isEmpty()) {
            return [
                'active' => false,
                'detection_rules' => array_map(fn ($rule) => [...$rule, 'on' => false], $defaultDetection),
                'protection_actions' => array_map(fn ($action) => [...$action, 'active' => false], $protectionActions),
                'rules' => [
                    ['label' => 'Bot traffic', 'action' => 'Not configured', 'tone' => 'off'],
                    ['label' => 'Malicious', 'action' => 'Not configured', 'tone' => 'off'],
                    ['label' => 'Suspicious', 'action' => 'Off', 'tone' => 'off'],
                ],
            ];
        }

        $first = $settings->first();
        $matrix = is_array($first->suspicious_matrix ?? null) ? $first->suspicious_matrix : [];
        $suspiciousOn = $settings->contains(fn ($row) => (bool) ($row->suspicious_enabled ?? false));
        $botRaw = strtolower((string) ($first->invalid_bot_action ?? 'monitor'));
        $maliciousRaw = strtolower((string) ($first->invalid_malicious_action ?? 'block'));
        $botAction = $this->normalizeProtectionAction($botRaw);
        $maliciousAction = $this->normalizeProtectionAction($maliciousRaw);
        $frequencyOn = (bool) ($first->frequency_capping ?? false);
        $matrixOn = function (string $key, string $default = 'block') use ($matrix, $suspiciousOn): bool {
            if (! $suspiciousOn) {
                return false;
            }
            $value = strtolower((string) ($matrix[$key] ?? $default));

            return ! in_array($value, ['allow', 'off', 'disabled', ''], true);
        };

        $detectionRules = [
            ['key' => 'vpn', 'label' => 'VPN Detection', 'on' => $matrixOn('vpn', 'allow')],
            ['key' => 'proxy', 'label' => 'Proxy Detection', 'on' => $matrixOn('proxy', 'block')],
            ['key' => 'datacenter', 'label' => 'Datacenter Detection', 'on' => $matrixOn('data_center', 'block')],
            ['key' => 'repeated', 'label' => 'Repeated Click Detection', 'on' => $frequencyOn || $matrixOn('abnormal_rate_limit', 'block')],
            ['key' => 'bot', 'label' => 'Bot Detection', 'on' => ! in_array($botRaw, ['allow', 'off', ''], true)],
            ['key' => 'abnormal', 'label' => 'Abnormal Behavior Detection', 'on' => $matrixOn('abnormal_rate_limit', 'block') || ! in_array($maliciousRaw, ['allow', 'off', ''], true)],
        ];

        $hasChallenge = $suspiciousOn;
        $hasBlock = in_array($botRaw, ['block', 'blocked', 'deny'], true)
            || in_array($maliciousRaw, ['block', 'blocked', 'deny'], true)
            || collect($matrix)->contains(fn ($v) => in_array(strtolower((string) $v), ['block', 'blocked', 'deny'], true));

        $protectionActions = [
            ['key' => 'monitor', 'label' => 'Monitor', 'desc' => 'Low Risk Traffic', 'active' => true, 'tone' => 'low'],
            ['key' => 'challenge', 'label' => 'Challenge', 'desc' => 'Medium Risk Traffic', 'active' => $hasChallenge, 'tone' => 'medium'],
            ['key' => 'block', 'label' => 'Block', 'desc' => 'High Risk Traffic', 'active' => $hasBlock, 'tone' => 'high'],
        ];

        $legacyRules = [
            ['label' => 'Bot traffic', 'action' => $botAction['label'], 'tone' => $botAction['tone']],
            ['label' => 'Malicious', 'action' => $maliciousAction['label'], 'tone' => $maliciousAction['tone']],
            [
                'label' => 'Suspicious',
                'action' => $suspiciousOn ? 'Challenge' : 'Off',
                'tone' => $suspiciousOn ? 'challenge' : 'off',
            ],
        ];

        $anyOn = collect($detectionRules)->contains(fn ($rule) => (bool) ($rule['on'] ?? false));

        return [
            'active' => $anyOn || $hasBlock || $hasChallenge,
            'detection_rules' => $detectionRules,
            'protection_actions' => $protectionActions,
            'rules' => $legacyRules,
        ];
    }

    /**
     * @return array{label: string, tone: string}
     */
    private function normalizeProtectionAction(string $raw): array
    {
        $action = strtolower(trim($raw));

        return match (true) {
            in_array($action, ['block', 'blocked', 'deny'], true) => ['label' => 'Block', 'tone' => 'block'],
            in_array($action, ['challenge', 'captcha', 'flag'], true) => ['label' => 'Challenge', 'tone' => 'challenge'],
            in_array($action, ['monitor', 'log', 'allow', 'observe'], true) => ['label' => 'Monitor', 'tone' => 'monitor'],
            default => ['label' => $raw !== '' ? ucfirst($action) : 'Monitor', 'tone' => 'monitor'],
        };
    }

    private function keywordFromLandingUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $raw = trim((string) ($params['utm_term'] ?? $params['keyword'] ?? ''));
        $lower = mb_strtolower($raw);

        if ($raw === '' || in_array($lower, ['null', 'undefined', '{keyword}'], true)) {
            return null;
        }

        return $raw;
    }

    /**
     * Paid traffic headline = Google-verified visits that are not invalid/bot traffic.
     */
    private function displayPaidTrafficCount(int $verifiedValidPaid, int $uniqueValidPaidClicks, int $googleClicks): int
    {
        if ($verifiedValidPaid > 0) {
            return max(0, $verifiedValidPaid);
        }

        return max(0, $uniqueValidPaidClicks);
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

    /**
     * @param  \Closure():mixed  $builder
     */
    private function rememberPaidDashboardJson(
        Request $request,
        string $bucket,
        \Closure $builder,
        bool $bypass = false,
    ): JsonResponse {
        /** @var DashboardResponseCache $cache */
        $cache = app(DashboardResponseCache::class);
        $version = $this->dashboardCacheMeta($request)['version'];
        $payload = $cache->remember($request, $bucket, $version, $builder, $bypass);

        return response()->json($payload)->header('X-PM-Cache', $cache->lastStatus());
    }

    /**
     * Cheap change detector used both for response-cache keys and the /watermark poll.
     * New visits/IPs, domain changes, and detection-setting edits all bump the version.
     *
     * @return array{last_id:int,count:int,domains_sig:string,version:string}
     */
    private function dashboardCacheMeta(Request $request): array
    {
        $memoKey = '_pm_dash_cache_meta';
        if ($request->attributes->has($memoKey)) {
            return $request->attributes->get($memoKey);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        $domainIds = $this->scopedDomainIds($request);
        [$metricFrom, $metricTo] = $this->calendarDateRange($request);

        $lastId = 0;
        $count = 0;
        $visitsUpdated = '';

        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $query = $this->scopedVisitsQuery($request, $domainIds, $metricFrom, $metricTo);
            $select = 'MAX(id) as last_id, COUNT(*) as cnt';
            if (Schema::hasColumn('visits', 'updated_at')) {
                $select .= ', MAX(updated_at) as max_updated';
            }
            $stats = (clone $query)->selectRaw($select)->first();
            $lastId = (int) ($stats->last_id ?? 0);
            $count = (int) ($stats->cnt ?? 0);
            $visitsUpdated = (string) ($stats->max_updated ?? '');
        }

        $domainsSig = '0';
        if ($userId > 0 && Schema::hasTable('domains')) {
            $domainRow = Domain::query()
                ->where('user_id', $userId)
                ->forPaidMarketing()
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as max_updated')
                ->first();
            $domainsSig = ((int) ($domainRow->total ?? 0)).'|'.(string) ($domainRow->max_updated ?? '');
        }

        $settingsSig = '0';
        if ($domainIds->isNotEmpty() && Schema::hasTable('domain_detection_settings')) {
            $settingsRow = DomainDetectionSetting::query()
                ->whereIn('domain_id', $domainIds->all())
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as max_updated')
                ->first();
            $settingsSig = ((int) ($settingsRow->total ?? 0)).'|'.(string) ($settingsRow->max_updated ?? '');
        }

        $googleMetricsSig = '0';
        if ($domainIds->isNotEmpty() && Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $googleMetricsRow = DB::table('google_ads_campaign_daily_metrics')
                ->whereIn('domain_id', $domainIds->all())
                ->whereBetween('metric_date', [$metricFrom, $metricTo])
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as max_updated, COALESCE(SUM(clicks), 0) as clicks')
                ->first();
            $googleMetricsSig = implode('|', [
                (int) ($googleMetricsRow->total ?? 0),
                (string) ($googleMetricsRow->max_updated ?? ''),
                (int) ($googleMetricsRow->clicks ?? 0),
            ]);
        }

        $allowlistSig = implode('|', \App\Support\GlobalIpAllowlist::patterns());
        if (Schema::hasTable('global_ip_allowlist_entries')) {
            $allowlistRow = DB::table('global_ip_allowlist_entries')
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as max_updated, SUM(enabled) as enabled_n')
                ->first();
            $allowlistSig .= '|'.implode('|', [
                (int) ($allowlistRow->total ?? 0),
                (string) ($allowlistRow->max_updated ?? ''),
                (int) ($allowlistRow->enabled_n ?? 0),
            ]);
        }

        $version = substr(hash('sha256', implode('|', [
            (string) $userId,
            (string) $lastId,
            (string) $count,
            $visitsUpdated,
            $domainsSig,
            $settingsSig,
            $googleMetricsSig,
            $allowlistSig,
            (string) $metricFrom,
            (string) $metricTo,
        ])), 0, 24);

        $meta = [
            'last_id' => $lastId,
            'count' => $count,
            'domains_sig' => substr(hash('sha256', $domainsSig), 0, 16),
            'version' => $version,
        ];
        $request->attributes->set($memoKey, $meta);

        return $meta;
    }
}
