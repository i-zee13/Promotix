<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
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
            ->orderBy('hostname')
            ->get(['id', 'hostname']);

        return view('bot-protection.dashboard', [
            'domains' => $domains,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json($this->emptySummary());
        }

        $base = DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to]);

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

        $rows = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->selectRaw('DATE(visited_at) as day, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $labels = [];
        $totalSeries = [];
        $validSeries = [];
        $invalidSeries = [];

        $period = Carbon::parse($from)->copy();
        while ($period->lt($to)) {
            $key = $period->toDateString();
            $row = $rows->firstWhere('day', $key);
            $labels[] = $period->format('M d');
            $total = (int) ($row->total ?? 0);
            $invalid = (int) ($row->invalid ?? 0);
            $totalSeries[] = $total;
            $invalidSeries[] = $invalid;
            $validSeries[] = max(0, $total - $invalid);
            $period->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['name' => 'Total', 'values' => $totalSeries],
                ['name' => 'Valid', 'values' => $validSeries],
                ['name' => 'Invalid', 'values' => $invalidSeries],
            ],
        ]);
    }

    public function threatGroups(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('detection_logs')) {
            return response()->json(['labels' => [], 'values' => []]);
        }

        $rows = DB::table('detection_logs')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('detected_at', [$from, $to])
            ->select('threat_group', DB::raw('COUNT(*) as total'))
            ->whereNotNull('threat_group')
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

        $rows = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->select('country', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'))
            ->whereNotNull('country')
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

    public function domainsSummary(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        $rows = Domain::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $domainIds)
            ->leftJoin('visits', function ($join) use ($from, $to): void {
                $join->on('domains.id', '=', 'visits.domain_id')
                    ->whereBetween('visits.visited_at', [$from, $to]);
            })
            ->select(
                'domains.id',
                'domains.hostname',
                'domains.status',
                DB::raw('COUNT(visits.id) as total_visits'),
                DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid_visits')
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
                'invalid_visits' => (int) $d->invalid_visits,
            ]);

        return response()->json($rows);
    }

    public function advancedView(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('hostname')
            ->get(['id', 'hostname']);

        return view('bot-protection.advanced', [
            'domains' => $domains,
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

        return response()->json([
            'data' => $rows->map(fn ($v) => $this->formatVisit($v))->values(),
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
            ->whereBetween('visits.visited_at', [$from, $to])
            ->select(
                'visits.id',
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

        return $query;
    }

    private function formatVisit(object $v): array
    {
        return [
            'id' => (int) $v->id,
            'hostname' => $v->hostname,
            'ip' => $v->ip,
            'country' => $v->country,
            'browser' => $v->browser,
            'os' => $v->os,
            'url' => $v->url,
            'referrer' => $v->referrer,
            'utm_source' => $v->utm_source,
            'utm_medium' => $v->utm_medium,
            'utm_campaign' => $v->utm_campaign,
            'action_taken' => $v->action_taken ?? 'allow',
            'threat_group' => $v->threat_group,
            'threat_score' => (int) ($v->threat_score ?? 0),
            'is_invalid_traffic' => (bool) $v->is_invalid_traffic,
            'is_paid_traffic' => (bool) $v->is_paid_traffic,
            'visited_at' => (string) ($v->visited_at ?? ''),
        ];
    }

    private function scopedDomainIds(Request $request)
    {
        $userDomainIds = Domain::query()->where('user_id', $request->user()->id)->pluck('id');

        if ($id = (int) $request->query('domain_id', 0)) {
            return $userDomainIds->filter(fn ($v) => (int) $v === $id)->values();
        }

        return $userDomainIds;
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
