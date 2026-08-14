<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use App\Models\IpLog;
use App\Services\IpIntel\AllowListMatcher;
use App\Services\IpIntel\IpIntelService;
use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class BotProtectionController extends Controller
{
    public function dashboard(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forBotProtection()
            ->orderBy('hostname')
            ->get(['id', 'hostname']);

        $googleAdsAccounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->synced()
            ->orderBy('account_name')
            ->get();

        return view('bot-protection.dashboard', [
            'domains' => $domains,
            'googleAdsAccounts' => $googleAdsAccounts,
            'useDemo' => app()->environment('local'),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json($this->emptySummary());
        }

        $current = $this->visitClassificationStats($domainIds, $from, $to, $request);
        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();
        $previous = $this->visitClassificationStats($domainIds, $prevFrom, $prevTo, $request);

        $pctDelta = static function (int|float $cur, int|float $prev): float {
            $cur = (float) $cur;
            $prev = (float) $prev;
            if ($prev == 0.0) {
                return $cur > 0 ? 100.0 : 0.0;
            }

            return round((($cur - $prev) / $prev) * 100, 1);
        };

        $sparklines = $this->visitSparklineSeries($domainIds, $from, $to, $request);

        return response()->json([
            'total_visits' => $current['total_visits'],
            'valid_visits' => $current['valid_visits'],
            'invalid_bot_visits' => $current['invalid_bot_visits'],
            'invalid_malicious_visits' => $current['invalid_malicious_visits'],
            'invalid_traffic' => $current['invalid_traffic'],
            'known_crawlers' => $current['known_crawlers'],
            'bots_detected' => $current['bots_detected'],
            'deltas' => [
                'valid_visits' => $pctDelta($current['valid_visits'], $previous['valid_visits']),
                'invalid_bot_visits' => $pctDelta($current['invalid_bot_visits'], $previous['invalid_bot_visits']),
                'known_crawlers' => $pctDelta($current['known_crawlers'], $previous['known_crawlers']),
                'invalid_traffic' => $pctDelta($current['invalid_traffic'], $previous['invalid_traffic']),
                'invalid_malicious_visits' => $pctDelta($current['invalid_malicious_visits'], $previous['invalid_malicious_visits']),
                'bot_impact' => round($current['paid']['bot_impact'] - $previous['paid']['bot_impact'], 1),
            ],
            'paid' => $current['paid'],
            'actions' => $current['actions'],
            'signals' => $current['signals'],
            'sparklines' => $sparklines,
            'window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'previous_from' => $prevFrom->toIso8601String(),
                'previous_to' => $prevTo->toIso8601String(),
            ],
        ]);
    }

    public function trafficBreakdown(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json(['labels' => [], 'datasets' => []]);
        }

        $crawlerSql = Schema::hasColumn('visits', 'is_crawler')
            ? 'SUM(CASE WHEN is_crawler = 1 THEN 1 ELSE 0 END) as crawlers'
            : '0 as crawlers';

        $rows = $this->baseVisitsQuery($domainIds, $from, $to, $request)
            ->selectRaw("DATE(visited_at) as day, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid, SUM(CASE WHEN is_invalid_traffic = 1 AND threat_group IN ('data_center','vpn','abnormal_rate_limit') THEN 1 ELSE 0 END) as bad_bots, {$crawlerSql}")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $labels = [];
        $totalSeries = [];
        $validSeries = [];
        $invalidSeries = [];
        $badBotSeries = [];
        $crawlerSeries = [];

        $period = Carbon::parse($from)->copy()->startOfDay();
        $endDay = $to->copy()->startOfDay();
        while ($period->lte($endDay)) {
            $key = $period->toDateString();
            $row = $rows->firstWhere('day', $key);
            $labels[] = $period->format('M d');
            $total = (int) ($row->total ?? 0);
            $invalid = (int) ($row->invalid ?? 0);
            $badBots = (int) ($row->bad_bots ?? 0);
            $crawlers = (int) ($row->crawlers ?? 0);
            $totalSeries[] = $total;
            $invalidSeries[] = $invalid;
            $badBotSeries[] = $badBots;
            $crawlerSeries[] = $crawlers;
            $validSeries[] = max(0, $total - $invalid);
            $period->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['name' => 'Valid Visits', 'values' => $validSeries, 'color' => '#FFFFFF'],
                ['name' => 'Bad Bots', 'values' => $badBotSeries, 'color' => '#0D0D0D'],
                ['name' => 'Crawler', 'values' => $crawlerSeries, 'color' => '#6625F8'],
                ['name' => 'Invalid', 'values' => $invalidSeries, 'color' => '#FF4BC1'],
                ['name' => 'Total Visits', 'values' => $totalSeries, 'color' => '#B893D8', 'line' => true],
            ],
        ]);
    }

    public function invalidTrafficTrends(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json(['labels' => [], 'datasets' => [], 'stats' => ['pageloads' => 0, 'interactions' => 0]]);
        }

        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $hourly = $days <= 1;

        [$labels, $bucketKeys] = $this->invalidTrendBuckets($from, $to, $hourly);

        $pageloads = $this->invalidTrendSeries($domainIds, $request, $from, $to, $bucketKeys, $hourly, 'pageloads');
        $interactions = $this->invalidTrendSeries($domainIds, $request, $from, $to, $bucketKeys, $hourly, 'interactions');

        $pageloadTotal = array_sum($pageloads);
        $interactionTotal = array_sum($interactions);

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['name' => 'Invalid Pageloads', 'values' => $pageloads, 'color' => '#6625F8'],
                ['name' => 'Invalid Site Interaction', 'values' => $interactions, 'color' => '#FF4BC1', 'dashed' => true],
            ],
            'stats' => [
                'pageloads' => $pageloadTotal,
                'interactions' => $interactionTotal,
            ],
        ]);
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function invalidTrendBuckets(Carbon $from, Carbon $to, bool $hourly): array
    {
        $labels = [];
        $keys = [];

        if ($hourly) {
            $period = $from->copy()->startOfHour();
            $end = $to->copy()->startOfHour();
            if ($period->gt($end)) {
                $end = $from->copy();
            }
            while ($period->lte($end)) {
                $keys[] = $period->format('Y-m-d H:00:00');
                $labels[] = $period->format('g A');
                $period->addHour();
            }

            return [$labels, $keys];
        }

        $period = $from->copy()->startOfDay();
        $endDay = $to->copy()->startOfDay();
        while ($period->lte($endDay)) {
            $keys[] = $period->toDateString();
            $labels[] = $period->format('D');
            $period->addDay();
        }

        return [$labels, $keys];
    }

    /** @param list<string> $bucketKeys */
    private function invalidTrendSeries(
        $domainIds,
        Request $request,
        Carbon $from,
        Carbon $to,
        array $bucketKeys,
        bool $hourly,
        string $metric
    ): array {
        if ($bucketKeys === []) {
            return [];
        }

        $invalidExpr = 'SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END)';
        $interactionExpr = "SUM(CASE WHEN is_invalid_traffic = 1 AND threat_group = 'malicious' THEN 1 ELSE 0 END)";
        $valueExpr = $metric === 'interactions' ? $interactionExpr : $invalidExpr;

        $bucketExpr = $hourly
            ? "DATE_FORMAT(visited_at, '%Y-%m-%d %H:00:00')"
            : 'DATE(visited_at)';

        $rows = $this->baseVisitsQuery($domainIds, $from, $to, $request)
            ->selectRaw("{$bucketExpr} as bucket, {$valueExpr} as total")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        return array_map(
            fn (string $key) => (int) ($rows->get($key)?->total ?? 0),
            $bucketKeys
        );
    }

    public function threatGroups(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (Schema::hasTable('detection_logs')) {
            $rows = DB::table('detection_logs')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('detected_at', [$from, $to])
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->whereNotNull('threat_group')
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();

            if ($rows->isNotEmpty()) {
                return response()->json([
                    'labels' => $rows->pluck('threat_group')->values(),
                    'values' => $rows->pluck('total')->map(fn ($n) => (int) $n)->values(),
                ]);
            }
        }

        if (! Schema::hasTable('visits')) {
            return response()->json(['labels' => [], 'values' => []]);
        }

        $rows = $this->applyPathFilter(
            $this->baseVisitsQuery($domainIds, $from, $to, $request)
                ->where('is_invalid_traffic', true)
                ->whereNotNull('threat_group'),
            $request
        )
            ->select('threat_group', DB::raw('COUNT(*) as total'))
            ->groupBy('threat_group')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'labels' => $rows->pluck('threat_group')->values(),
            'values' => $rows->pluck('total')->map(fn ($n) => (int) $n)->values(),
        ]);
    }

    public function invalidBreakdown(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        $empty = [
            'invalid_bot' => ['labels' => [], 'values' => []],
            'invalid_malicious' => ['labels' => [], 'values' => []],
            'reasons' => ['labels' => [], 'values' => []],
            'malicious_reasons' => ['labels' => [], 'values' => []],
        ];

        if (! Schema::hasTable('detection_logs') && ! Schema::hasTable('visits')) {
            return response()->json($empty);
        }

        $bot = collect();
        $malicious = collect();

        if (Schema::hasTable('detection_logs')) {
            $base = DB::table('detection_logs')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('detected_at', [$from, $to]);

            $bot = (clone $base)
                ->whereIn('threat_group', ['data_center', 'vpn', 'abnormal_rate_limit', 'proxy', 'automation'])
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();

            $malicious = (clone $base)
                ->where('threat_group', 'malicious')
                ->select('action_taken as label', DB::raw('COUNT(*) as total'))
                ->groupBy('action_taken')
                ->orderByDesc('total')
                ->get();
        }

        if ($bot->isEmpty() && Schema::hasTable('visits')) {
            $bot = $this->applyPathFilter(
                $this->baseVisitsQuery($domainIds, $from, $to, $request)
                    ->where('is_invalid_traffic', true)
                    ->whereIn('threat_group', ['data_center', 'vpn', 'abnormal_rate_limit', 'proxy', 'automation']),
                $request
            )
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();
        }

        $reasons = $this->aggregateDetectionReasons($domainIds, $from, $to, false);
        $maliciousReasons = $this->aggregateDetectionReasons($domainIds, $from, $to, true);

        return response()->json([
            'invalid_bot' => [
                'labels' => $bot->pluck('threat_group')->values(),
                'values' => $bot->pluck('total')->map(fn ($n) => (int) $n)->values(),
            ],
            'invalid_malicious' => [
                'labels' => $malicious->pluck('label')->values(),
                'values' => $malicious->pluck('total')->map(fn ($n) => (int) $n)->values(),
            ],
            'reasons' => $reasons,
            'malicious_reasons' => $maliciousReasons,
        ]);
    }

    public function countries(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        $rows = $this->baseVisitsQuery($domainIds, $from, $to, $request)
            ->select('country', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $mapped = $rows->map(fn ($r) => [
            'country' => $r->country,
            'total' => (int) $r->total,
            'invalid' => (int) $r->invalid,
        ])->values();

        $invalidSum = $mapped->sum('invalid') ?: 1;

        return response()->json($mapped->map(fn ($r) => [
            ...$r,
            'percent' => round(($r['invalid'] / $invalidSum) * 100, 1),
        ])->values());
    }

    public function countryIps(Request $request): JsonResponse
    {
        $country = strtoupper(trim((string) $request->query('country', '')));
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if ($country === '' || ! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        $rows = $this->baseVisitsQuery($domainIds, $from, $to, $request)
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

    public function domainsSummary(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        $crawlerExpr = Schema::hasColumn('visits', 'is_crawler')
            ? 'SUM(CASE WHEN visits.is_crawler = 1 THEN 1 ELSE 0 END)'
            : '0';
        $botExpr = 'SUM(CASE WHEN visits.is_invalid_traffic = 1 AND visits.threat_group IN (\'data_center\',\'vpn\',\'proxy\',\'abnormal_rate_limit\',\'malicious\') THEN 1 ELSE 0 END)';
        if (! Schema::hasColumn('visits', 'threat_group')) {
            $botExpr = 'SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END)';
        }

        $selectDomainCols = ['domains.id', 'domains.hostname', 'domains.status'];
        $groupBy = ['domains.id', 'domains.hostname', 'domains.status'];
        if (Schema::hasColumn('domains', 'bot_mitigation_connected')) {
            $selectDomainCols[] = 'domains.bot_mitigation_connected';
            $groupBy[] = 'domains.bot_mitigation_connected';
        }
        if (Schema::hasColumn('domains', 'monitoring_only_mode')) {
            $selectDomainCols[] = 'domains.monitoring_only_mode';
            $groupBy[] = 'domains.monitoring_only_mode';
        }

        $buildRows = function (Carbon $rangeFrom, Carbon $rangeTo) use ($request, $domainIds, $crawlerExpr, $botExpr, $selectDomainCols, $groupBy) {
            return Domain::query()
                ->where('domains.user_id', $request->user()->id)
                ->whereIn('domains.id', $domainIds)
                ->leftJoin('visits', function ($join) use ($rangeFrom, $rangeTo): void {
                    $join->on('domains.id', '=', 'visits.domain_id')
                        ->whereBetween('visits.visited_at', [$rangeFrom, $rangeTo]);
                    GoogleClickAttribution::excludeClickIds($join, 'visits');
                })
                ->select(array_merge($selectDomainCols, [
                    DB::raw('COUNT(visits.id) as total_visits'),
                    DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid_visits'),
                    DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 0 OR visits.is_invalid_traffic IS NULL THEN 1 ELSE 0 END) as valid_visits'),
                    DB::raw("{$crawlerExpr} as known_crawlers"),
                    DB::raw("{$botExpr} as bots_detected"),
                ]))
                ->groupBy($groupBy)
                ->get()
                ->keyBy('id');
        };

        $current = $buildRows($from, $to);
        $previous = $buildRows($prevFrom, $prevTo);

        $pctDelta = static function (int|float $cur, int|float $prev): float {
            $cur = (float) $cur;
            $prev = (float) $prev;
            if ($prev == 0.0) {
                return $cur > 0 ? 100.0 : 0.0;
            }

            return round((($cur - $prev) / $prev) * 100, 1);
        };

        $rows = $current
            ->sortByDesc(fn ($d) => (int) $d->total_visits)
            ->take(20)
            ->values()
            ->map(function ($d) use ($previous, $pctDelta) {
                $total = (int) $d->total_visits;
                $invalid = (int) $d->invalid_visits;
                $crawlers = (int) $d->known_crawlers;
                $bots = (int) $d->bots_detected;
                $human = max(0, $total - $bots - $crawlers);
                $invalidPct = $total > 0 ? round(($invalid / $total) * 100, 2) : 0.0;
                $risk = match (true) {
                    $invalidPct >= 15.0 => 'High',
                    $invalidPct >= 5.0 => 'Medium',
                    default => 'Low',
                };

                $protected = (bool) ($d->bot_mitigation_connected ?? false);
                $monitoringOnly = (bool) ($d->monitoring_only_mode ?? false);
                $protectionStatus = match (true) {
                    $protected && ! $monitoringOnly => 'Active',
                    $protected && $monitoringOnly => 'Monitoring',
                    default => 'Inactive',
                };

                $prevTotal = (int) ($previous->get($d->id)->total_visits ?? 0);
                $trend = $pctDelta($total, $prevTotal);

                return [
                    'id' => (int) $d->id,
                    'hostname' => $d->hostname,
                    'status' => $d->status,
                    'total_visits' => $total,
                    'human_traffic' => $human,
                    'bots' => $bots,
                    'valid_visits' => (int) $d->valid_visits,
                    'invalid_visits' => $invalid,
                    'invalid_pct' => $invalidPct,
                    'known_crawlers' => $crawlers,
                    'risk_score' => $risk,
                    'protection_status' => $protectionStatus,
                    'trend_pct' => $trend,
                ];
            });

        return response()->json($rows);
    }

    public function advancedView(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forBotProtection()
            ->orderBy('hostname')
            ->get(['id', 'hostname']);

        $googleAdsAccounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->synced()
            ->orderBy('account_name')
            ->get();

        return view('bot-protection.advanced', [
            'domains' => $domains,
            'googleAdsAccounts' => $googleAdsAccounts,
        ]);
    }

    public function botStats(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json([
                'blocked' => 0,
                'invalid_traffic' => 0,
                'paid_traffic' => 0,
                'bot_detection' => 0,
                'country' => 0,
                'overall' => 0,
                'charts' => $this->emptyAdvancedCharts(),
            ]);
        }

        $base = $this->baseVisitsQuery($domainIds, $from, $to, $request);

        $total = max(1, (clone $base)->count());
        $blocked = (clone $base)->where('action_taken', 'block')->count();
        $invalid = (clone $base)->where('is_invalid_traffic', true)->count();
        $bot = (clone $base)->whereIn('threat_group', ['data_center', 'vpn', 'abnormal_rate_limit'])->count();
        $withCountry = (clone $base)->whereNotNull('country')->where('country', '!=', '')->count();
        $valid = max(0, (clone $base)->count() - $invalid);

        // Same IP-aggregate grain as Advanced table / Dashboard country IPs.
        $chartRows = $this->buildAdvancedIpAggregateQuery($request, $domainIds, $from, $to)
            ->orderByDesc('total')
            ->limit(500)
            ->get();

        $ipLogs = IpLog::query()
            ->whereIn('ip', $chartRows->pluck('ip')->unique()->filter()->values())
            ->get(['ip', 'is_blocked', 'ipdetails_abuser_score', 'abuse_confidence_score'])
            ->keyBy('ip');

        return response()->json([
            'blocked' => (int) round(($blocked / $total) * 100),
            'invalid_traffic' => (int) round(($invalid / $total) * 100),
            'paid_traffic' => 0,
            'bot_detection' => (int) round(($bot / $total) * 100),
            'country' => (int) round(($withCountry / $total) * 100),
            'overall' => (int) round(($valid / $total) * 100),
            'charts' => $this->computeAdvancedCharts($chartRows, $ipLogs),
        ]);
    }

    public function visits(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json(['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => 25]]);
        }

        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $page = max(1, (int) $request->query('page', 1));

        $total = $this->countAdvancedUniqueIps($request, $domainIds, $from, $to);
        $rows = $this->buildAdvancedIpAggregateQuery($request, $domainIds, $from, $to)
            ->orderByDesc('total')
            ->orderByDesc('visited_at')
            ->forPage($page, $perPage)
            ->get();

        $ipLogs = IpLog::query()
            ->whereIn('ip', $rows->pluck('ip')->unique()->filter()->values())
            ->get()
            ->keyBy('ip');

        $recordings = collect();
        $behaviorCounts = collect();
        if (Schema::hasTable('visit_session_recordings') && $rows->isNotEmpty()) {
            $ips = $rows->pluck('ip')->unique()->filter()->values();
            $recordings = DB::table('visit_session_recordings')
                ->whereIn('ip', $ips)
                ->whereIn('domain_id', $domainIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('ip')
                ->map->first();

            if (Schema::hasColumn('visit_session_recordings', 'cta_clicks')) {
                $behaviorCounts = DB::table('visit_session_recordings')
                    ->select([
                        'ip',
                        DB::raw('COALESCE(SUM(cta_clicks), 0) as cta_clicks'),
                        DB::raw('COALESCE(SUM(tel_clicks), 0) as tel_clicks'),
                        DB::raw('COALESCE(SUM(page_changes), 0) as page_changes'),
                    ])
                    ->whereIn('ip', $ips)
                    ->whereIn('domain_id', $domainIds)
                    ->groupBy('ip')
                    ->get()
                    ->keyBy('ip');
            }
        }

        $domainsById = Domain::query()
            ->whereIn('id', $rows->pluck('domain_id')->unique()->filter()->values())
            ->get(['id', 'user_id', 'hostname', 'monitoring_only_mode'])
            ->keyBy('id');

        return response()->json([
            'data' => $rows->map(fn ($v) => $this->formatVisit(
                $v,
                $ipLogs->get($v->ip),
                $request->user(),
                $recordings->get($v->ip),
                $domainsById->get($v->domain_id),
                $behaviorCounts->get($v->ip),
            ))->values(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        $filename = 'bot-protection-' . now()->format('YmdHis') . '.csv';

        return response()->streamDownload(function () use ($request, $domainIds, $from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'IP Address',
                'Visits',
                'Invalid',
                'Valid',
                'Domain',
                'Path',
                'Last Seen',
                'Threat Group',
                'Threat Type',
                'Action',
                'Country',
                'Browser',
                'OS',
                'Status',
                'UTM Campaign',
                'Risk Score',
            ]);

            if (! Schema::hasTable('visits') || $domainIds->isEmpty()) {
                fclose($handle);

                return;
            }

            $rows = $this->buildAdvancedIpAggregateQuery($request, $domainIds, $from, $to)
                ->orderByDesc('total')
                ->orderByDesc('visited_at')
                ->limit(5000)
                ->get();

            $ipLogs = IpLog::query()
                ->whereIn('ip', $rows->pluck('ip')->unique()->filter()->values())
                ->get()
                ->keyBy('ip');

            $domainsById = Domain::query()
                ->whereIn('id', $rows->pluck('domain_id')->unique()->filter()->values())
                ->get(['id', 'user_id', 'hostname', 'monitoring_only_mode'])
                ->keyBy('id');

            foreach ($rows as $v) {
                $row = $this->formatVisit(
                    $v,
                    $ipLogs->get($v->ip),
                    $request->user(),
                    null,
                    $domainsById->get($v->domain_id),
                );
                fputcsv($handle, [
                    $row['ip'] ?? '',
                    $row['visits'] ?? 0,
                    $row['invalid_visits'] ?? 0,
                    $row['valid_visits'] ?? 0,
                    $row['domain'] ?? '',
                    $row['path'] ?? '',
                    $row['last_seen_label'] ?? '',
                    $row['threat_group'] ?? '',
                    $row['threat_type'] ?? '',
                    $row['action_taken'] ?? '',
                    $row['country'] ?? '',
                    $row['browser'] ?? '',
                    $row['os'] ?? '',
                    $row['status'] ?? '',
                    $row['utm_campaign'] ?? '',
                    $row['intel_risk_score'] ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * IP-level aggregates for Advanced table/CSV (same grain as Dashboard countryIps).
     */
    private function buildAdvancedIpAggregateQuery(Request $request, $domainIds, Carbon $from, Carbon $to)
    {
        $query = DB::table('visits')
            ->leftJoin('domains', 'domains.id', '=', 'visits.domain_id')
            ->whereIn('visits.domain_id', $domainIds)
            ->whereBetween('visits.visited_at', [$from, $to]);
        GoogleClickAttribution::excludeClickIds($query, 'visits');
        $this->applyAdvancedVisitFilters($query, $request);

        return $query
            ->select(
                'visits.domain_id',
                'visits.ip',
                'domains.hostname',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'),
                DB::raw('MAX(visits.visited_at) as visited_at'),
                DB::raw('MAX(visits.country) as country'),
                DB::raw('MAX(visits.browser) as browser'),
                DB::raw('MAX(visits.os) as os'),
                DB::raw('MAX(visits.url) as url'),
                DB::raw('MAX(visits.referrer) as referrer'),
                DB::raw('MAX(visits.utm_source) as utm_source'),
                DB::raw('MAX(visits.utm_medium) as utm_medium'),
                DB::raw('MAX(visits.utm_campaign) as utm_campaign'),
                DB::raw('MAX(visits.action_taken) as action_taken'),
                DB::raw('MAX(visits.threat_group) as threat_group'),
                DB::raw('MAX(visits.threat_score) as threat_score'),
                DB::raw('MAX(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as is_invalid_traffic'),
                DB::raw('MAX(CASE WHEN visits.is_paid_traffic = 1 THEN 1 ELSE 0 END) as is_paid_traffic'),
                DB::raw('MAX(visits.id) as id'),
            )
            ->groupBy('visits.domain_id', 'visits.ip', 'domains.hostname');
    }

    private function countAdvancedUniqueIps(Request $request, $domainIds, Carbon $from, Carbon $to): int
    {
        $query = DB::table('visits')
            ->whereIn('visits.domain_id', $domainIds)
            ->whereBetween('visits.visited_at', [$from, $to]);
        GoogleClickAttribution::excludeClickIds($query, 'visits');
        $this->applyAdvancedVisitFilters($query, $request);

        return (int) $query
            ->selectRaw('COUNT(DISTINCT CONCAT(visits.domain_id, "|", visits.ip)) as aggregate_count')
            ->value('aggregate_count');
    }

    private function applyAdvancedVisitFilters($query, Request $request): void
    {
        if ($ip = trim((string) $request->query('ip', ''))) {
            $query->where('visits.ip', 'like', '%'.$ip.'%');
        }
        if ($country = trim((string) $request->query('country', ''))) {
            $query->where('visits.country', strtoupper($country));
        }
        if ($action = trim((string) $request->query('action', ''))) {
            $query->where('visits.action_taken', $action);
        }
        if ($group = trim((string) $request->query('threat_group', ''))) {
            $query->where('visits.threat_group', $group);
        }
        if ($request->boolean('only_invalid')) {
            $query->where('visits.is_invalid_traffic', true);
        }
        if ($request->boolean('only_paid')) {
            $query->where('visits.is_paid_traffic', true);
        }
        if ($path = trim((string) $request->query('path', ''))) {
            $query->where('visits.url', 'like', '%'.$path.'%');
        }
        if ($campaign = trim((string) $request->query('campaign', ''))) {
            $query->where('visits.utm_campaign', $campaign);
        }
    }

    private function buildAdvancedQuery(Request $request, $domainIds, Carbon $from, Carbon $to)
    {
        $query = DB::table('visits')
            ->leftJoin('domains', 'domains.id', '=', 'visits.domain_id')
            ->whereIn('visits.domain_id', $domainIds)
            ->whereBetween('visits.visited_at', [$from, $to]);
        GoogleClickAttribution::excludeClickIds($query, 'visits');
        $this->applyAdvancedVisitFilters($query, $request);

        return $query->select(
            'visits.id',
            'visits.domain_id',
            'domains.hostname',
            'visits.ip',
            'visits.country',
            'visits.browser',
            'visits.os',
            'visits.url',
            'visits.referrer',
            'visits.utm_source',
            'visits.utm_medium',
            'visits.utm_campaign',
            'visits.action_taken',
            'visits.threat_group',
            'visits.threat_score',
            'visits.is_invalid_traffic',
            'visits.is_paid_traffic',
            'visits.visited_at'
        );
    }

    private function formatVisit(object $v, ?IpLog $ipLog = null, ?\App\Models\User $user = null, ?object $recording = null, ?Domain $domain = null, ?object $behaviorCounts = null): array
    {
        $visitedAt = ! empty($v->visited_at) ? Carbon::parse((string) $v->visited_at, 'UTC') : null;
        $hasAggregate = property_exists($v, 'total') || isset($v->total);
        $total = $hasAggregate ? (int) ($v->total ?? 0) : 1;
        $invalid = $hasAggregate
            ? (int) ($v->invalid ?? 0)
            : (((bool) $v->is_invalid_traffic) ? 1 : 0);
        $isInvalid = $invalid > 0 || (bool) ($v->is_invalid_traffic ?? false);
        $isAllowListed = $domain !== null
            && $ipLog !== null
            && AllowListMatcher::isAllowListed($domain, $ipLog->ip);

        $rowId = (int) ($v->id ?? 0);
        if ($rowId <= 0) {
            $rowId = (int) sprintf('%u', crc32((int) ($v->domain_id ?? 0).'|'.(string) ($v->ip ?? '')));
        }

        return [
            'id' => $rowId,
            'domain_id' => (int) ($v->domain_id ?? 0),
            'hostname' => $v->hostname,
            'domain' => $v->hostname,
            'ip' => $v->ip,
            'visits' => $total,
            'path' => $v->url,
            'country' => $v->country,
            'country_label' => $this->countryLabel($v->country),
            'browser' => $v->browser,
            'os' => $v->os,
            'url' => $v->url,
            'domain_url' => $v->url ?: ($v->hostname ?? ''),
            'referrer' => $v->referrer,
            'utm_source' => $v->utm_source,
            'utm_medium' => $v->utm_medium,
            'utm_campaign' => $v->utm_campaign,
            'action_taken' => $v->action_taken ?? 'allow',
            'threat_group' => $v->threat_group,
            'threat_group_label' => $this->threatGroupLabel($v->threat_group, $isInvalid),
            'threat_type' => $this->threatTypeLabel($v->threat_group),
            'threat_type_label' => $this->threatTypeLabel($v->threat_group),
            'threat_score' => (int) ($v->threat_score ?? 0),
            'is_invalid_traffic' => $isInvalid,
            'is_paid_traffic' => (bool) $v->is_paid_traffic,
            'invalid_visits' => $invalid,
            'valid_visits' => max(0, $total - $invalid),
            'cta_clicks' => (int) ($behaviorCounts->cta_clicks ?? 0),
            'tel_clicks' => (int) ($behaviorCounts->tel_clicks ?? 0),
            'page_changes' => (int) ($behaviorCounts->page_changes ?? 0),
            'ip_is_blocked' => $isAllowListed ? false : (bool) ($ipLog?->is_blocked ?? false),
            'is_allowlisted' => $isAllowListed,
            'has_session_recording' => $recording !== null,
            'session_recording_id' => $recording ? (int) $recording->id : null,
            'last_seen_label' => UserTimezone::formatForUser($visitedAt, $user, 'm/d/y H:i') ?? '—',
            'visited_at' => UserTimezone::formatForUser($visitedAt, $user, 'm/d/Y H:i') ?? '',
            ...$this->intelFieldsForVisit($v, $ipLog, $user, $domain),
        ];
    }

    /** @return array<string, mixed> */
    private function intelFieldsForVisit(object $visit, ?IpLog $ipLog, ?\App\Models\User $user = null, ?Domain $domain = null): array
    {
        $raw = (array) ($ipLog?->ipdetails_raw ?? []);
        $abuser = $ipLog?->ipdetails_abuser_score;
        $riskLevel = null;

        if (is_numeric($abuser)) {
            $score = (float) $abuser;
            $riskLevel = $score >= 0.7 ? 'High' : ($score >= 0.2 ? 'Medium' : 'Low');
        } elseif (is_int($ipLog?->abuse_confidence_score)) {
            $riskLevel = $ipLog->abuse_confidence_score >= 50 ? 'High' : 'Low';
        }

        $threatGroup = strtolower((string) ($visit->threat_group ?? ''));
        $isVpn = $threatGroup === 'vpn';
        $isDc = in_array($threatGroup, ['data_center', 'datacenter'], true);
        $isTor = (bool) ($ipLog?->abuse_is_tor ?? false);
        $isHosting = $ipLog ? app(IpIntelService::class)->isHostingType($ipLog) : false;
        $isProxy = $ipLog ? app(IpIntelService::class)->isProxySuspect($ipLog) : false;

        $status = 'Valid';
        $isAllowListed = $domain !== null
            && $ipLog !== null
            && AllowListMatcher::isAllowListed($domain, $ipLog->ip);

        if ($isAllowListed) {
            $status = 'Valid';
        } elseif ($ipLog?->is_blocked) {
            $status = 'Blocked';
        } elseif ((bool) ($visit->is_invalid_traffic ?? false)) {
            $status = 'Invalid';
        }

        return [
            'status' => $status,
            'is_allowlisted' => $isAllowListed,
            'intel_region' => $ipLog?->intel_region ?? $raw['region'] ?? $raw['region_code'] ?? $raw['state'] ?? null,
            'intel_city' => $ipLog?->intel_city ?? $raw['city'] ?? null,
            'intel_latitude' => $raw['latitude'] ?? null,
            'intel_longitude' => $raw['longitude'] ?? null,
            'intel_asn' => $raw['asn'] ?? null,
            'intel_asn_org' => $raw['company'] ?? $raw['org'] ?? $ipLog?->intel_isp,
            'intel_isp' => $ipLog?->intel_isp ?? null,
            'intel_network_range' => $raw['network'] ?? $raw['network_range'] ?? null,
            'intel_routed_prefix' => $raw['prefix'] ?? $raw['routed_prefix'] ?? null,
            'intel_allocated_range' => $raw['allocated'] ?? $raw['allocated_range'] ?? null,
            'intel_range_note' => $raw['range_note'] ?? null,
            'intel_vpn' => $isVpn ? 'Yes' : 'No',
            'intel_proxy' => $isProxy ? 'Yes' : 'No',
            'intel_tor' => $isTor ? 'Yes' : 'No',
            'intel_datacenter' => ($isDc || $isHosting) ? 'Yes' : 'No',
            'intel_risk_score' => $abuser ?? $ipLog?->abuse_confidence_score,
            'intel_risk_level' => $riskLevel,
            'intel_confidence' => $ipLog?->abuse_confidence_score,
            'intel_evidence' => $ipLog?->abuse_total_reports ? ($ipLog->abuse_total_reports . ' reports') : null,
            'intel_checked_at' => UserTimezone::formatForUser($ipLog?->intel_checked_at, $user, 'm/d/y H:i'),
            'intel_error' => $ipLog?->intel_status === 'error' ? 'Yes' : null,
            'intel_ip_need_blockation' => ($isAllowListed || ! $ipLog?->is_blocked) ? 'No' : 'Yes',
            'intel_blockation_type' => is_array($ipLog?->iphub_proxy_type)
                ? implode(', ', $ipLog->iphub_proxy_type)
                : ($ipLog?->iphub_proxy_type ?? null),
            'intel_block_reason' => $ipLog?->iphub_block_reason ?? null,
            'intel_device_action' => $visit->action_taken ?? null,
            'intel_provider_type' => $raw['type'] ?? null,
            'intel_matched_provider' => $raw['provider'] ?? $raw['abuse_name'] ?? null,
            'intel_matched_dataset' => $raw['dataset'] ?? null,
            'intel_cloud_provider' => $raw['cloud_provider'] ?? null,
        ];
    }

    private function threatGroupLabel(?string $group, bool $invalid): string
    {
        if ($invalid) {
            return match ($group) {
                'malicious' => 'Invalid Malicious',
                'data_center', 'vpn', 'abnormal_rate_limit' => 'Invalid Bot',
                default => 'Invalid Suspicious',
            };
        }

        return 'Valid';
    }

    private function threatTypeLabel(?string $group): string
    {
        return match ($group) {
            'data_center' => 'Data Center',
            'vpn' => 'VPN',
            'malicious' => 'Malicious',
            'abnormal_rate_limit' => 'Abnormal Rate Limit',
            'out_of_geo' => 'Out of Geo',
            default => $group ? ucwords(str_replace('_', ' ', $group)) : '—',
        };
    }

    private function countryLabel(?string $code): string
    {
        $map = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'PK' => 'Pakistan',
            'AE' => 'United Arab Emirates',
            'CA' => 'Canada',
            'IN' => 'India',
        ];

        $code = strtoupper((string) $code);

        return $map[$code] ?? ($code ?: '—');
    }

    private function baseVisitsQuery($domainIds, Carbon $from, Carbon $to, Request $request)
    {
        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to]);

        GoogleClickAttribution::excludeClickIds($query);

        return $this->applyPathFilter($query, $request);
    }

    /**
     * Google Ads sessions live outside the bot-protection base query, which strips click-ID traffic.
     *
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $domainIds
     */
    private function paidVisitsQuery($domainIds, Carbon $from, Carbon $to, Request $request)
    {
        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to]);

        if (Schema::hasColumn('visits', 'is_paid_traffic')) {
            $query->where(function ($group) {
                $group->where('is_paid_traffic', true)
                    ->orWhere(function ($inner): void {
                        GoogleClickAttribution::applyHasClickIdFilter($inner);
                    });
            });
        } else {
            GoogleClickAttribution::applyHasClickIdFilter($query);
        }

        return $this->applyPathFilter($query, $request);
    }

    private function applyPathFilter($query, Request $request, string $column = 'url')
    {
        $path = trim((string) $request->query('path', ''));
        if ($path !== '' && Schema::hasColumn('visits', 'url')) {
            $query->where($column, 'like', '%' . $path . '%');
        }

        $campaign = trim((string) $request->query('campaign', ''));
        if ($campaign !== '' && Schema::hasColumn('visits', 'utm_campaign')) {
            $campaignCol = str_contains($column, '.') ? 'visits.utm_campaign' : 'utm_campaign';
            $query->where($campaignCol, $campaign);
        }

        return $query;
    }

    private function scopedDomainIds(Request $request)
    {
        $query = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forBotProtection();

        if ($id = (int) $request->query('domain_id', 0)) {
            $query->where('id', $id);
        }

        if ($accountId = (int) $request->query('google_ads_account_id', 0)) {
            $query->where('google_ads_account_id', $accountId);
        }

        return $query->pluck('id');
    }

    private function dateRange(Request $request): array
    {
        return UserTimezone::dateRangeFromRequest($request, $request->user());
    }

    private function emptySummary(): array
    {
        return [
            'total_visits' => 0,
            'valid_visits' => 0,
            'invalid_bot_visits' => 0,
            'invalid_malicious_visits' => 0,
            'invalid_traffic' => 0,
            'known_crawlers' => 0,
            'bots_detected' => 0,
            'deltas' => [
                'valid_visits' => 0,
                'invalid_bot_visits' => 0,
                'known_crawlers' => 0,
                'invalid_traffic' => 0,
                'invalid_malicious_visits' => 0,
                'bot_impact' => 0,
            ],
            'paid' => [
                'total' => 0,
                'valid' => 0,
                'invalid' => 0,
                'bot_impact' => 0.0,
                'share_pct' => 0.0,
            ],
            'actions' => [
                'block' => 0,
                'challenge' => 0,
                'allow' => 0,
            ],
            'signals' => [
                ['key' => 'headless', 'label' => 'Headless Browser', 'active' => false],
                ['key' => 'automation', 'label' => 'Automation Tool', 'active' => false],
                ['key' => 'missing_events', 'label' => 'Missing Browser Events', 'active' => false],
                ['key' => 'abnormal_rate', 'label' => 'Abnormal Request Rate', 'active' => false],
            ],
            'sparklines' => [
                'valid' => [],
                'automated' => [],
                'crawlers' => [],
                'invalid' => [],
                'bot_impact' => [],
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $domainIds
     * @return array<string, mixed>
     */
    private function visitClassificationStats($domainIds, Carbon $from, Carbon $to, Request $request): array
    {
        $base = $this->baseVisitsQuery($domainIds, $from, $to, $request);

        $total = (clone $base)->count();
        $invalidBot = (clone $base)->where('is_invalid_traffic', true)->where(function ($q): void {
            $q->where('threat_group', 'data_center')
                ->orWhere('threat_group', 'vpn')
                ->orWhere('threat_group', 'abnormal_rate_limit')
                ->orWhere('threat_group', 'proxy')
                ->orWhere('threat_group', 'automation');
        })->count();
        $invalidMalicious = (clone $base)->where('is_invalid_traffic', true)->where('threat_group', 'malicious')->count();
        $invalidTraffic = (clone $base)->where('is_invalid_traffic', true)->count();

        if (Schema::hasColumn('visits', 'is_crawler')) {
            $knownCrawlers = (clone $base)->where('is_crawler', true)->count();
        } else {
            $knownCrawlers = (clone $base)->where(function ($q): void {
                foreach ($this->crawlerBrowserList() as $name) {
                    $q->orWhere('user_agent', 'like', '%' . $name . '%');
                }
            })->count();
        }

        $valid = max(0, $total - $invalidBot - $invalidMalicious - $knownCrawlers);
        $botsDetected = $invalidBot + $invalidMalicious;

        $paidBase = $this->paidVisitsQuery($domainIds, $from, $to, $request);
        $paidTotal = (clone $paidBase)->count();
        $paidInvalid = (clone $paidBase)->where('is_invalid_traffic', true)->count();
        $paidValid = max(0, $paidTotal - $paidInvalid);
        $botImpact = $paidTotal > 0 ? round(($paidInvalid / $paidTotal) * 100, 1) : 0.0;
        $allSessions = $total + $paidTotal;
        $paidSharePct = $allSessions > 0 ? round(($paidTotal / $allSessions) * 100, 1) : 0.0;

        $actions = ['block' => 0, 'challenge' => 0, 'allow' => 0];
        if (Schema::hasColumn('visits', 'action_taken')) {
            $actionRows = (clone $base)
                ->where(function ($q): void {
                    $q->where('is_invalid_traffic', true)->orWhereNotNull('threat_group');
                })
                ->select('action_taken', DB::raw('COUNT(*) as total'))
                ->groupBy('action_taken')
                ->get();
            foreach ($actionRows as $row) {
                $key = strtolower((string) ($row->action_taken ?? 'allow'));
                if ($key === 'block') {
                    $actions['block'] += (int) $row->total;
                } elseif (in_array($key, ['challenge', 'flag', 'captcha'], true)) {
                    $actions['challenge'] += (int) $row->total;
                } else {
                    $actions['allow'] += (int) $row->total;
                }
            }
        }

        return [
            'total_visits' => $total,
            'valid_visits' => $valid,
            'invalid_bot_visits' => $invalidBot,
            'invalid_malicious_visits' => $invalidMalicious,
            'invalid_traffic' => $invalidTraffic,
            'known_crawlers' => $knownCrawlers,
            'bots_detected' => $botsDetected,
            'paid' => [
                'total' => $paidTotal,
                'valid' => $paidValid,
                'invalid' => $paidInvalid,
                'bot_impact' => $botImpact,
                'share_pct' => $paidSharePct,
            ],
            'actions' => $actions,
            'signals' => $this->detectionSignalFlags($domainIds, $from, $to),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $domainIds
     * @return array<string, list<int|float>>
     */
    private function visitSparklineSeries($domainIds, Carbon $from, Carbon $to, Request $request): array
    {
        $empty = [
            'valid' => [],
            'automated' => [],
            'crawlers' => [],
            'invalid' => [],
            'bot_impact' => [],
        ];

        if (! Schema::hasTable('visits')) {
            return $empty;
        }

        $crawlerSql = Schema::hasColumn('visits', 'is_crawler')
            ? 'SUM(CASE WHEN is_crawler = 1 THEN 1 ELSE 0 END) as crawlers'
            : '0 as crawlers';
        $rows = $this->baseVisitsQuery($domainIds, $from, $to, $request)
            ->selectRaw("DATE(visited_at) as day, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid, SUM(CASE WHEN is_invalid_traffic = 1 AND threat_group IN ('data_center','vpn','abnormal_rate_limit','proxy','automation') THEN 1 ELSE 0 END) as bad_bots, {$crawlerSql}")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $paidRows = $this->paidVisitsQuery($domainIds, $from, $to, $request)
            ->selectRaw('DATE(visited_at) as day, COUNT(*) as paid_total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as paid_invalid')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $valid = [];
        $automated = [];
        $crawlers = [];
        $invalid = [];
        $botImpact = [];

        $period = $from->copy()->startOfDay();
        $endDay = $to->copy()->startOfDay();
        while ($period->lte($endDay)) {
            $key = $period->toDateString();
            $row = $rows->get($key);
            $total = (int) ($row->total ?? 0);
            $inv = (int) ($row->invalid ?? 0);
            $bots = (int) ($row->bad_bots ?? 0);
            $crawl = (int) ($row->crawlers ?? 0);
            $paidRow = $paidRows->get($key);
            $paidTotal = (int) ($paidRow->paid_total ?? 0);
            $paidInvalid = (int) ($paidRow->paid_invalid ?? 0);

            $valid[] = max(0, $total - $inv);
            $automated[] = $bots;
            $crawlers[] = $crawl;
            $invalid[] = $inv;
            $botImpact[] = $paidTotal > 0 ? round(($paidInvalid / $paidTotal) * 100, 1) : 0;

            $period->addDay();
        }

        return [
            'valid' => $valid,
            'automated' => $automated,
            'crawlers' => $crawlers,
            'invalid' => $invalid,
            'bot_impact' => $botImpact,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $domainIds
     * @return list<array{key: string, label: string, active: bool}>
     */
    private function detectionSignalFlags($domainIds, Carbon $from, Carbon $to): array
    {
        $signals = [
            'headless' => ['key' => 'headless', 'label' => 'Headless Browser', 'active' => false, 'needles' => ['headless', 'crawler_ua']],
            'automation' => ['key' => 'automation', 'label' => 'Automation Tool', 'active' => false, 'needles' => ['automation', 'scrap']],
            'missing_events' => ['key' => 'missing_events', 'label' => 'Missing Browser Events', 'active' => false, 'needles' => ['missing_event', 'no_browser', 'browser_event']],
            'abnormal_rate' => ['key' => 'abnormal_rate', 'label' => 'Abnormal Request Rate', 'active' => false, 'needles' => ['rapid', 'rate_limit', 'session_rate', 'ip_rate']],
        ];

        $blobs = [];
        if (Schema::hasTable('detection_logs') && Schema::hasColumn('detection_logs', 'reasons')) {
            $blobs = DB::table('detection_logs')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('detected_at', [$from, $to])
                ->whereNotNull('reasons')
                ->orderByDesc('detected_at')
                ->limit(800)
                ->pluck('reasons')
                ->all();
        } elseif (Schema::hasTable('visits') && Schema::hasColumn('visits', 'detection_reasons')) {
            $blobs = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to])
                ->whereNotNull('detection_reasons')
                ->orderByDesc('visited_at')
                ->limit(800)
                ->pluck('detection_reasons')
                ->all();
        }

        $haystack = strtolower(implode(' ', array_map(
            static fn ($raw) => is_string($raw) ? $raw : json_encode($raw),
            $blobs
        )));

        foreach ($signals as &$signal) {
            foreach ($signal['needles'] as $needle) {
                if ($haystack !== '' && str_contains($haystack, strtolower($needle))) {
                    $signal['active'] = true;
                    break;
                }
            }
            unset($signal['needles']);
        }
        unset($signal);

        // Fallback: rate-limit threat group implies abnormal rate signal.
        if (! $signals['abnormal_rate']['active'] && Schema::hasTable('visits')) {
            $hasRate = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to])
                ->where('threat_group', 'abnormal_rate_limit')
                ->exists();
            $signals['abnormal_rate']['active'] = $hasRate;
        }

        return array_values($signals);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $domainIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function aggregateDetectionReasons($domainIds, Carbon $from, Carbon $to, bool $maliciousOnly): array
    {
        $counts = [];

        if (Schema::hasTable('detection_logs') && Schema::hasColumn('detection_logs', 'reasons')) {
            $q = DB::table('detection_logs')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('detected_at', [$from, $to])
                ->whereNotNull('reasons');
            if ($maliciousOnly) {
                $q->where('threat_group', 'malicious');
            } else {
                $q->where(function ($inner): void {
                    $inner->whereNull('threat_group')->orWhere('threat_group', '!=', 'malicious');
                });
            }
            $rows = $q->orderByDesc('detected_at')->limit(1500)->pluck('reasons');
            foreach ($rows as $raw) {
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                if (! is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $reason) {
                    $key = $this->friendlyDetectionReason((string) $reason);
                    if ($key === '') {
                        continue;
                    }
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
        }

        if ($counts === [] && Schema::hasTable('visits')) {
            $q = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to])
                ->where('is_invalid_traffic', true)
                ->whereNotNull('threat_group');
            if ($maliciousOnly) {
                $q->where('threat_group', 'malicious');
            } else {
                $q->where('threat_group', '!=', 'malicious');
            }
            $groups = $q->select('threat_group', DB::raw('COUNT(*) as total'))
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->limit(6)
                ->get();
            foreach ($groups as $row) {
                $label = $this->friendlyThreatGroupLabel((string) $row->threat_group);
                $counts[$label] = (int) $row->total;
            }
        }

        arsort($counts);
        $counts = array_slice($counts, 0, 6, true);

        return [
            'labels' => array_keys($counts),
            'values' => array_map('intval', array_values($counts)),
        ];
    }

    private function friendlyDetectionReason(string $reason): string
    {
        $key = strtolower(trim($reason));
        if ($key === '') {
            return '';
        }

        $map = [
            'headless' => 'Headless Browser',
            'crawler_ua' => 'Headless Browser',
            'rapid_page_opens' => 'Rapid Requests',
            'session_rate_limit' => 'Rapid Requests',
            'ip_rate_limit' => 'Rapid Requests',
            'rapid_repeat' => 'Repeated Click Pattern',
            'rapid_repeat_block' => 'Repeated Click Pattern',
            'ipdetails_hosting' => 'Datacenter Traffic',
            'vpn_isp_match' => 'VPN / Proxy',
            'proxy_isp_match' => 'VPN / Proxy',
            'abuse_tor' => 'VPN / Proxy',
            'abuse_confidence' => 'Suspicious Behavior',
            'ipdetails_abuser_high' => 'Suspicious Behavior',
            'ipdetails_abuser_medium' => 'Suspicious Behavior',
            'out_of_geo' => 'Suspicious Navigation',
            'blocked_country' => 'Suspicious Navigation',
            'paid_daily_click_limit' => 'Same Device Multi Sessions',
            'previously_blocked' => 'Unknown Automation',
            'block_list' => 'Unknown Automation',
            'automation' => 'Unknown Automation',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        foreach ($map as $needle => $label) {
            if (str_contains($key, $needle)) {
                return $label;
            }
        }

        return ucwords(str_replace('_', ' ', $key));
    }

    private function friendlyThreatGroupLabel(string $group): string
    {
        return match (strtolower($group)) {
            'data_center', 'datacenter' => 'Datacenter Traffic',
            'vpn' => 'VPN',
            'proxy' => 'Proxy',
            'abnormal_rate_limit' => 'Rapid Requests',
            'automation' => 'Unknown Automation',
            'malicious' => 'Malicious Behavior',
            default => ucwords(str_replace('_', ' ', $group)),
        };
    }

    /** @return array<string, mixed> */
    private function emptyAdvancedCharts(): array
    {
        return [
            'updated_at' => now()->toIso8601String(),
            'threat' => [
                'total' => 0,
                'total_label' => '0',
                'center_label' => 'Invalid Clicks',
                'gradient' => 'conic-gradient(rgba(100,0,178,0.25) 0 100%)',
                'items' => [],
            ],
            'risk' => [
                'total' => 0,
                'total_label' => '0',
                'center_label' => 'Unique IPs',
                'gradient' => 'conic-gradient(rgba(100,0,178,0.25) 0 100%)',
                'items' => [
                    ['label' => 'High Risk', 'color' => '#F43F5E', 'pct' => 0, 'count' => 0, 'count_label' => '0'],
                    ['label' => 'Medium Risk', 'color' => '#F59E0B', 'pct' => 0, 'count' => 0, 'count_label' => '0'],
                    ['label' => 'Low Risk', 'color' => '#22C55E', 'pct' => 0, 'count' => 0, 'count_label' => '0'],
                ],
            ],
            'countries' => [],
            'high_risk_ips' => [],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  \Illuminate\Support\Collection<string, IpLog>  $ipLogs
     * @return array<string, mixed>
     */
    private function computeAdvancedCharts($rows, $ipLogs): array
    {
        if ($rows->isEmpty()) {
            return $this->emptyAdvancedCharts();
        }

        $threatBuckets = [
            'vpn' => ['label' => 'VPN / Proxy', 'color' => '#A855F7', 'count' => 0],
            'datacenter' => ['label' => 'Data Center', 'color' => '#3B82F6', 'count' => 0],
            'geo' => ['label' => 'Geo Mismatch', 'color' => '#D6B27C', 'count' => 0],
            'device' => ['label' => 'Invalid Device', 'color' => '#C084FC', 'count' => 0],
            'bot' => ['label' => 'Bot Behavior', 'color' => '#22D3EE', 'count' => 0],
            'repeat' => ['label' => 'Repeated Clicks', 'color' => '#14B8A6', 'count' => 0],
        ];

        $riskBuckets = [
            'high' => ['label' => 'High Risk', 'color' => '#F43F5E', 'count' => 0],
            'medium' => ['label' => 'Medium Risk', 'color' => '#F59E0B', 'count' => 0],
            'low' => ['label' => 'Low Risk', 'color' => '#22C55E', 'count' => 0],
        ];

        $countryInvalid = [];
        $riskSeenIps = [];
        $highRiskCards = [];

        foreach ($rows as $visit) {
            $aggInvalid = isset($visit->invalid) ? (int) $visit->invalid : null;
            $aggTotal = isset($visit->total) ? (int) $visit->total : null;
            $isInvalid = ($aggInvalid !== null ? $aggInvalid > 0 : false)
                || (bool) ($visit->is_invalid_traffic ?? false)
                || filled($visit->threat_group)
                || ($visit->action_taken ?? '') === 'block';

            $group = strtolower(trim((string) ($visit->threat_group ?? '')));
            $weight = $aggInvalid !== null ? $aggInvalid : ($isInvalid ? 1 : 0);

            if ($weight > 0) {
                if (in_array($group, ['vpn', 'proxy'], true)) {
                    $threatBuckets['vpn']['count'] += $weight;
                } elseif (in_array($group, ['data_center', 'datacenter'], true)) {
                    $threatBuckets['datacenter']['count'] += $weight;
                } elseif (in_array($group, ['out_of_geo', 'geo', 'geo_mismatch'], true)) {
                    $threatBuckets['geo']['count'] += $weight;
                } elseif (str_contains($group, 'device') || $group === 'malicious') {
                    $threatBuckets['device']['count'] += $weight;
                } elseif ($group === 'abnormal_rate_limit' || str_contains($group, 'repeat') || ($aggTotal !== null && $aggTotal > 1)) {
                    $threatBuckets['repeat']['count'] += $weight;
                } else {
                    $threatBuckets['bot']['count'] += $weight;
                }

                $countryCode = strtoupper(trim((string) ($visit->country ?? '')));
                if ($countryCode !== '') {
                    $name = $this->countryLabel($countryCode);
                    $countryInvalid[$name] = ($countryInvalid[$name] ?? 0) + $weight;
                }
            }

            $ip = (string) ($visit->ip ?? '');
            if ($ip === '' || isset($riskSeenIps[$ip])) {
                // skip duplicate IP for risk distribution
            } else {
                $riskSeenIps[$ip] = true;
                $ipLog = $ipLogs->get($ip);
                $score = $visit->threat_score ?? null;
                if (! is_numeric($score) || (float) $score <= 0) {
                    $score = $ipLog?->ipdetails_abuser_score;
                    if (is_numeric($score) && (float) $score <= 1) {
                        $score = (float) $score * 100;
                    } elseif (! is_numeric($score)) {
                        $score = $ipLog?->abuse_confidence_score;
                    }
                }
                $score = is_numeric($score) ? (float) $score : null;
                if ($score === null) {
                    if (($visit->action_taken ?? '') === 'block' || $isInvalid) {
                        $level = 'medium';
                        $score = 55;
                    } else {
                        $level = 'low';
                        $score = 20;
                    }
                } else {
                    $level = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
                }
                $riskBuckets[$level]['count'] += 1;

                if ($score >= 55 || $level === 'high') {
                    $category = 'High Risk';
                    $dot = '#F43F5E';
                    if (in_array($group, ['data_center', 'datacenter'], true)) {
                        $category = 'Datacenter';
                    } elseif (in_array($group, ['vpn', 'proxy'], true)) {
                        $category = 'VPN / Proxy';
                    } elseif ($group === 'abnormal_rate_limit') {
                        $category = 'Repeated Clicks';
                    } elseif (in_array($group, ['out_of_geo', 'geo'], true)) {
                        $category = 'Geo Mismatch';
                        $dot = '#D6B27C';
                    } elseif ($group !== '') {
                        $category = 'Bot Behavior';
                        $dot = '#F59E0B';
                    }

                    $ago = '—';
                    $sortAt = 0;
                    if (! empty($visit->visited_at)) {
                        try {
                            $parsed = Carbon::parse((string) $visit->visited_at);
                            $sortAt = $parsed->getTimestamp();
                            $ago = $parsed->diffForHumans(null, true);
                            $ago = str_replace(
                                [' seconds', ' second', ' minutes', ' minute', ' hours', ' hour', ' days', ' day'],
                                ['s', 's', ' mins', ' min', 'h', 'h', 'd', 'd'],
                                $ago
                            );
                            $ago = $ago === '0s' ? 'Just now' : ($ago.' ago');
                        } catch (\Throwable) {
                        }
                    }

                    $invalidCount = max(1, $aggInvalid ?? ($isInvalid ? 1 : 0));
                    $highRiskCards[] = [
                        'id' => (int) ($visit->id ?? 0),
                        'ip' => $ip,
                        'risk' => (int) round($score),
                        'risk_tone' => $score >= 75 ? 'high' : 'medium',
                        'category' => $category,
                        'dot' => $dot,
                        'invalid_clicks' => $invalidCount,
                        'invalid_label' => $invalidCount.' invalid visit'.($invalidCount === 1 ? '' : 's'),
                        'ago' => $ago,
                        '_sort_at' => $sortAt,
                    ];
                }
            }
        }

        $threatSum = (int) array_sum(array_column($threatBuckets, 'count'));
        $threatTotal = max(1, $threatSum);
        $threatItems = [];
        $threatGradientStops = [];
        $cursor = 0.0;
        foreach ($threatBuckets as $bucket) {
            $pct = $threatSum > 0 ? round(($bucket['count'] / $threatTotal) * 100, 1) : 0.0;
            $threatItems[] = [
                'label' => $bucket['label'],
                'color' => $bucket['color'],
                'pct' => $pct,
                'count' => $bucket['count'],
                'count_label' => number_format($bucket['count']),
            ];
            if ($bucket['count'] <= 0) {
                continue;
            }
            $next = $cursor + $pct;
            $threatGradientStops[] = $bucket['color'].' '.$cursor.'% '.$next.'%';
            $cursor = $next;
        }
        if ($threatGradientStops === []) {
            $threatGradientStops[] = 'rgba(100,0,178,0.25) 0% 100%';
        }

        $uniqueIps = count($riskSeenIps);
        $riskTotal = max(1, array_sum(array_column($riskBuckets, 'count')));
        $riskItems = [];
        $riskGradientStops = [];
        $cursor = 0.0;
        foreach ($riskBuckets as $bucket) {
            $pct = round(($bucket['count'] / $riskTotal) * 100, 1);
            $riskItems[] = [
                'label' => $bucket['label'],
                'color' => $bucket['color'],
                'pct' => $pct,
                'count' => $bucket['count'],
                'count_label' => number_format($bucket['count']),
            ];
            if ($bucket['count'] <= 0) {
                continue;
            }
            $next = $cursor + max($pct, 0);
            $riskGradientStops[] = $bucket['color'].' '.$cursor.'% '.$next.'%';
            $cursor = $next;
        }
        if ($riskGradientStops === []) {
            $riskGradientStops[] = 'rgba(100,0,178,0.25) 0% 100%';
        }

        arsort($countryInvalid);
        $topCountriesRaw = array_slice($countryInvalid, 0, 5, true);
        $countryMax = max(1, (int) (reset($topCountriesRaw) ?: 1));
        $countryInvalidTotal = max(1, array_sum($countryInvalid));
        $topCountries = [];
        foreach ($topCountriesRaw as $name => $count) {
            $topCountries[] = [
                'name' => $name,
                'count' => $count,
                'count_label' => number_format($count),
                'pct' => round(($count / $countryInvalidTotal) * 100, 1),
                'bar' => round(($count / $countryMax) * 100, 1),
                'flag' => $this->countryFlagEmoji((string) $name),
            ];
        }

        usort($highRiskCards, fn ($a, $b) => ($b['_sort_at'] ?? 0) <=> ($a['_sort_at'] ?? 0));
        $highRiskCards = array_slice($highRiskCards, 0, 12);
        foreach ($highRiskCards as &$card) {
            unset($card['_sort_at']);
        }
        unset($card);

        return [
            'updated_at' => now()->toIso8601String(),
            'threat' => [
                'total' => $threatSum,
                'total_label' => number_format($threatSum),
                'center_label' => 'Invalid Clicks',
                'gradient' => 'conic-gradient('.implode(', ', $threatGradientStops).')',
                'items' => $threatItems,
            ],
            'risk' => [
                'total' => $uniqueIps,
                'total_label' => number_format($uniqueIps),
                'center_label' => 'Unique IPs',
                'gradient' => 'conic-gradient('.implode(', ', $riskGradientStops).')',
                'items' => $riskItems,
            ],
            'countries' => $topCountries,
            'high_risk_ips' => array_values($highRiskCards),
        ];
    }

    private function countryFlagEmoji(string $country): string
    {
        $map = [
            'united states' => '🇺🇸', 'usa' => '🇺🇸', 'us' => '🇺🇸',
            'germany' => '🇩🇪', 'de' => '🇩🇪',
            'india' => '🇮🇳', 'in' => '🇮🇳',
            'singapore' => '🇸🇬', 'sg' => '🇸🇬',
            'russia' => '🇷🇺', 'ru' => '🇷🇺',
            'united kingdom' => '🇬🇧', 'uk' => '🇬🇧', 'gb' => '🇬🇧',
            'canada' => '🇨🇦', 'ca' => '🇨🇦',
            'france' => '🇫🇷', 'fr' => '🇫🇷',
            'brazil' => '🇧🇷', 'br' => '🇧🇷',
            'pakistan' => '🇵🇰', 'pk' => '🇵🇰',
            'china' => '🇨🇳', 'cn' => '🇨🇳',
            'australia' => '🇦🇺', 'au' => '🇦🇺',
            'netherlands' => '🇳🇱', 'nl' => '🇳🇱',
            'japan' => '🇯🇵', 'jp' => '🇯🇵',
            'united arab emirates' => '🇦🇪', 'ae' => '🇦🇪',
        ];

        $key = strtolower(trim($country));
        if (isset($map[$key])) {
            return $map[$key];
        }

        if (strlen($key) === 2) {
            $a = ord($key[0]) - 97;
            $b = ord($key[1]) - 97;
            if ($a >= 0 && $a < 26 && $b >= 0 && $b < 26) {
                return mb_chr(0x1F1E6 + $a).mb_chr(0x1F1E6 + $b);
            }
        }

        return '🌐';
    }

    private function crawlerBrowserList(): array
    {
        return [
            'Googlebot', 'bingbot', 'Slurp', 'DuckDuckBot', 'YandexBot', 'Baiduspider',
            'facebookexternalhit', 'Twitterbot', 'LinkedInBot', 'Applebot', 'AhrefsBot',
            'SemrushBot', 'MJ12bot', 'PetalBot',
        ];
    }
}
