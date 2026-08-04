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

        return view('bot-protection.dashboard', [
            'domains' => $domains,
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

        $base = $this->baseVisitsQuery($domainIds, $from, $to, $request);

        $total = (clone $base)->count();
        $invalidBot = (clone $base)->where('is_invalid_traffic', true)->where(function ($q): void {
            $q->where('threat_group', 'data_center')->orWhere('threat_group', 'vpn')->orWhere('threat_group', 'abnormal_rate_limit');
        })->count();
        $invalidMalicious = (clone $base)->where('is_invalid_traffic', true)->where('threat_group', 'malicious')->count();

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

        return response()->json([
            'total_visits' => $total,
            'valid_visits' => $valid,
            'invalid_bot_visits' => $invalidBot,
            'invalid_malicious_visits' => $invalidMalicious,
            'known_crawlers' => $knownCrawlers,
            'window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
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

        if (! Schema::hasTable('detection_logs')) {
            return response()->json([
                'invalid_bot' => ['labels' => [], 'values' => []],
                'invalid_malicious' => ['labels' => [], 'values' => []],
            ]);
        }

        $base = DB::table('detection_logs')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('detected_at', [$from, $to]);

        $bot = (clone $base)
            ->whereIn('threat_group', ['data_center', 'vpn', 'abnormal_rate_limit'])
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

        return response()->json([
            'invalid_bot' => [
                'labels' => $bot->pluck('threat_group')->values(),
                'values' => $bot->pluck('total')->map(fn ($n) => (int) $n)->values(),
            ],
            'invalid_malicious' => [
                'labels' => $malicious->pluck('label')->values(),
                'values' => $malicious->pluck('total')->map(fn ($n) => (int) $n)->values(),
            ],
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

        $crawlerExpr = Schema::hasColumn('visits', 'is_crawler')
            ? 'SUM(CASE WHEN visits.is_crawler = 1 THEN 1 ELSE 0 END)'
            : '0';

        $rows = Domain::query()
            ->where('domains.user_id', $request->user()->id)
            ->whereIn('domains.id', $domainIds)
            ->leftJoin('visits', function ($join) use ($from, $to): void {
                $join->on('domains.id', '=', 'visits.domain_id')
                    ->whereBetween('visits.visited_at', [$from, $to]);
                GoogleClickAttribution::excludeClickIds($join, 'visits');
            })
            ->select(
                'domains.id',
                'domains.hostname',
                'domains.status',
                DB::raw('COUNT(visits.id) as total_visits'),
                DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid_visits'),
                DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 0 OR visits.is_invalid_traffic IS NULL THEN 1 ELSE 0 END) as valid_visits'),
                DB::raw("{$crawlerExpr} as known_crawlers")
            )
            ->groupBy('domains.id', 'domains.hostname', 'domains.status')
            ->orderByDesc('total_visits')
            ->limit(20)
            ->get()
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'hostname' => $d->hostname,
                'status' => $d->status,
                'total_visits' => (int) $d->total_visits,
                'valid_visits' => (int) $d->valid_visits,
                'invalid_visits' => (int) $d->invalid_visits,
                'known_crawlers' => (int) $d->known_crawlers,
            ]);

        return response()->json($rows);
    }

    public function advancedView(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
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
            ]);
        }

        $base = $this->baseVisitsQuery($domainIds, $from, $to, $request);

        $total = max(1, (clone $base)->count());
        $blocked = (clone $base)->where('action_taken', 'block')->count();
        $invalid = (clone $base)->where('is_invalid_traffic', true)->count();
        $bot = (clone $base)->whereIn('threat_group', ['data_center', 'vpn', 'abnormal_rate_limit'])->count();
        $withCountry = (clone $base)->whereNotNull('country')->where('country', '!=', '')->count();
        $valid = max(0, (clone $base)->count() - $invalid);

        return response()->json([
            'blocked' => (int) round(($blocked / $total) * 100),
            'invalid_traffic' => (int) round(($invalid / $total) * 100),
            'paid_traffic' => 0,
            'bot_detection' => (int) round(($bot / $total) * 100),
            'country' => (int) round(($withCountry / $total) * 100),
            'overall' => (int) round(($valid / $total) * 100),
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

        $query = $this->buildAdvancedQuery($request, $domainIds, $from, $to);

        $total = $query->count();
        $rows = $query
            ->orderByDesc('visited_at')
            ->forPage($page, $perPage)
            ->get();

        $ipLogs = IpLog::query()
            ->whereIn('ip', $rows->pluck('ip')->unique()->filter()->values())
            ->get()
            ->keyBy('ip');

        $recordings = collect();
        if (Schema::hasTable('visit_session_recordings') && $rows->isNotEmpty()) {
            $recordings = DB::table('visit_session_recordings')
                ->whereIn('ip', $rows->pluck('ip')->unique())
                ->whereIn('domain_id', $domainIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('ip')
                ->map->first();
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
            fputcsv($handle, ['Visited At', 'Domain', 'IP', 'Country', 'Browser', 'OS', 'URL', 'Referrer', 'UTM Source', 'UTM Medium', 'UTM Campaign', 'Action', 'Threat Group', 'Threat Score', 'Invalid']);

            if (! Schema::hasTable('visits')) {
                fclose($handle);

                return;
            }

            $this->buildAdvancedQuery($request, $domainIds, $from, $to)
                ->orderByDesc('visits.visited_at')
                ->limit(50000)
                ->cursor()
                ->each(function ($v) use ($handle): void {
                    fputcsv($handle, [
                        (string) ($v->visited_at ?? ''),
                        $v->hostname ?? '',
                        $v->ip,
                        $v->country,
                        $v->browser,
                        $v->os,
                        $v->url,
                        $v->referrer,
                        $v->utm_source,
                        $v->utm_medium,
                        $v->utm_campaign,
                        $v->action_taken ?? 'allow',
                        $v->threat_group,
                        $v->threat_score,
                        ((int) $v->is_invalid_traffic) === 1 ? 'yes' : 'no',
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function buildAdvancedQuery(Request $request, $domainIds, Carbon $from, Carbon $to)
    {
        $query = DB::table('visits')
            ->leftJoin('domains', 'domains.id', '=', 'visits.domain_id')
            ->whereIn('visits.domain_id', $domainIds)
            ->whereBetween('visits.visited_at', [$from, $to]);
        GoogleClickAttribution::excludeClickIds($query, 'visits');
        $query = $this->applyPathFilter($query, $request, 'visits.url')
            ->select(
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

        if ($ip = trim((string) $request->query('ip', ''))) {
            $query->where('visits.ip', 'like', '%' . $ip . '%');
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
            $query->where('visits.url', 'like', '%' . $path . '%');
        }

        if ($campaign = trim((string) $request->query('campaign', ''))) {
            $query->where('visits.utm_campaign', $campaign);
        }

        return $query;
    }

    private function formatVisit(object $v, ?IpLog $ipLog = null, ?\App\Models\User $user = null, ?object $recording = null, ?Domain $domain = null): array
    {
        $visitedAt = ! empty($v->visited_at) ? Carbon::parse((string) $v->visited_at, 'UTC') : null;
        $isInvalid = (bool) $v->is_invalid_traffic;
        $isAllowListed = $domain !== null
            && $ipLog !== null
            && AllowListMatcher::isAllowListed($domain, $ipLog->ip);

        return [
            'id' => (int) $v->id,
            'hostname' => $v->hostname,
            'domain' => $v->hostname,
            'ip' => $v->ip,
            'visits' => 1,
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
            'invalid_visits' => $isInvalid ? 1 : 0,
            'valid_visits' => $isInvalid ? 0 : 1,
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
            'intel_region' => $raw['region'] ?? $raw['state'] ?? null,
            'intel_city' => $raw['city'] ?? null,
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

    private function applyPathFilter($query, Request $request, string $column = 'url')
    {
        $path = trim((string) $request->query('path', ''));
        if ($path !== '' && Schema::hasColumn('visits', 'url')) {
            $query->where($column, 'like', '%' . $path . '%');
        }

        return $query;
    }

    private function scopedDomainIds(Request $request)
    {
        $userDomainIds = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forBotProtection()
            ->pluck('id');

        if ($id = (int) $request->query('domain_id', 0)) {
            return $userDomainIds->filter(fn ($v) => (int) $v === $id)->values();
        }

        return $userDomainIds;
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
            'known_crawlers' => 0,
        ];
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
