<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\IpLog;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Support\DashboardNotifications;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('hostname')
            ->get(['id', 'hostname']);

        return view('dashboard-figma', compact('domains'));
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function insights(Request $request): JsonResponse
    {
        $user = $request->user();
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (Schema::hasTable('visits')) {
            $base = DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to]);
            $totalClicks = (clone $base)->count();
            $suspiciousVisits = (clone $base)->where('is_invalid_traffic', true)->count();
            $topCampaign = (clone $base)
                ->whereNotNull('utm_campaign')
                ->select('utm_campaign as campaign', DB::raw('COUNT(*) as total'))
                ->groupBy('utm_campaign')
                ->orderByDesc('total')
                ->first();
        } else {
            $totalClicks = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
                ->whereBetween('clicked_at', [$from, $to])
                ->count();
            $suspiciousVisits = PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereNotNull('threat_group')
                ->whereBetween('last_click_at', [$from, $to])
                ->count();
            $topCampaign = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
                ->whereBetween('clicked_at', [$from, $to])
                ->whereNotNull('campaign')
                ->select('campaign', DB::raw('COUNT(*) as total'))
                ->groupBy('campaign')
                ->orderByDesc('total')
                ->first();
        }

        return response()->json([
            'totalClicks' => (int) $totalClicks,
            'suspiciousVisits' => (int) $suspiciousVisits,
            'topCampaign' => $topCampaign?->campaign ?? 'N/A',
            'topCampaignClicks' => (int) ($topCampaign?->total ?? 0),
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        $path = trim((string) $request->query('path', ''));
        [$from, $to] = $this->dateRange($request);

        if (Schema::hasTable('visits')) {
            $query = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to]);

            if ($path !== '') {
                $query->where('url', 'like', '%' . $path . '%');
            }

            $rows = $query
                ->selectRaw('DATE(visited_at) as day, COUNT(*) as total')
                ->where('is_invalid_traffic', true)
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        } else {
            $query = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
                ->whereBetween('clicked_at', [$from, $to]);

            if ($path !== '') {
                $query->where('path', 'like', '%' . $path . '%');
            }

            $rows = $query
                ->selectRaw('DATE(clicked_at) as day, COUNT(*) as total')
                ->whereNotNull('clicked_at')
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        }

        $indexed = $rows->pluck('total', 'day')->all();
        $labels = [];
        $values = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $values[] = (int) ($indexed[$dateKey] ?? 0);
            $cursor->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'values' => $values,
        ]);
    }

    public function threats(Request $request): JsonResponse
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
        } elseif (Schema::hasTable('visits')) {
            $rows = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to])
                ->where('is_invalid_traffic', true)
                ->whereNotNull('threat_group')
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();
        } else {
            $rows = PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('last_click_at', [$from, $to])
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->whereNotNull('threat_group')
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();
        }

        return response()->json([
            'labels' => $rows->pluck('threat_group')->map(fn ($v) => (string) $v)->values(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->values(),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(DashboardNotifications::forUser($request->user()->id));
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dark_mode' => ['required', 'boolean'],
        ]);

        $request->session()->put('preferences.dark_mode', $data['dark_mode']);
        $user = $request->user();
        $prefs = (array) ($user->ui_preferences ?? []);
        $prefs['dark_mode'] = (bool) $data['dark_mode'];
        $user->ui_preferences = $prefs;
        $user->save();

        return response()->json([
            'ok' => true,
            'dark_mode' => (bool) $data['dark_mode'],
        ]);
    }

    public function domainPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        [$from, $to] = $this->dateRange($request);
        $search = trim((string) $request->query('search', ''));

        if (Schema::hasTable('visits')) {
            $rows = Domain::query()
                ->where('user_id', $user->id)
                ->when($search !== '', fn ($q) => $q->where('hostname', 'like', '%' . $search . '%'))
                ->leftJoin('visits', function ($join) use ($from, $to): void {
                    $join->on('domains.id', '=', 'visits.domain_id')
                        ->whereBetween('visits.visited_at', [$from, $to]);
                })
                ->select(
                    'domains.hostname',
                    'domains.tag_connected',
                    'domains.status',
                    DB::raw('COUNT(visits.id) as visits_count'),
                    DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as threat_visits_count')
                )
                ->groupBy('domains.id', 'domains.hostname', 'domains.tag_connected', 'domains.status')
                ->orderByDesc('visits_count')
                ->limit(50)
                ->get()
                ->map(fn ($d) => [
                    'domain' => $d->hostname,
                    'visits' => (int) $d->visits_count,
                    'threats' => (int) $d->threat_visits_count,
                    'pending' => ! $d->tag_connected || ($d->status ?? 'pending') === 'pending',
                ]);
        } else {
            $rows = Domain::query()
                ->where('user_id', $user->id)
                ->when($search !== '', fn ($q) => $q->where('hostname', 'like', '%' . $search . '%'))
                ->withCount([
                    'paidMarketingVisits as visits_count' => fn ($q) => $q->whereBetween('last_click_at', [$from, $to]),
                    'paidMarketingVisits as threat_visits_count' => fn ($q) => $q->whereNotNull('threat_group')->whereBetween('last_click_at', [$from, $to]),
                ])
                ->orderByDesc('visits_count')
                ->limit(50)
                ->get()
                ->map(fn ($d) => [
                    'domain' => $d->hostname,
                    'visits' => (int) $d->visits_count,
                    'threats' => (int) $d->threat_visits_count,
                    'pending' => ! $d->tag_connected || ($d->status ?? 'pending') === 'pending',
                ]);
        }

        return response()->json($rows);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $user = $request->user();
        if (Schema::hasTable('visits')) {
            $campaigns = DB::table('visits')
                ->whereIn('domain_id', Domain::query()->where('user_id', $user->id)->pluck('id'))
                ->whereNotNull('utm_campaign')
                ->distinct()
                ->orderBy('utm_campaign')
                ->pluck('utm_campaign')
                ->values();
        } else {
            $campaigns = PaidMarketingClick::query()
                ->whereHas('visit.domain', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotNull('campaign')
                ->distinct()
                ->orderBy('campaign')
                ->pluck('campaign')
                ->values();
        }

        return response()->json($campaigns);
    }

    public function liveSnapshot(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function liveStream(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('implicit_flush', '1');

            for ($i = 0; $i < 15; $i++) {
                if (connection_aborted()) {
                    break;
                }

                $payload = json_encode($this->snapshot($request));
                echo "event: snapshot\n";
                echo "data: {$payload}\n\n";
                @ob_flush();
                @flush();
                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function snapshot(Request $request): array
    {
        $user = $request->user();
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        $visitBase = Schema::hasTable('visits')
            ? DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to])
            : null;

        $paidVisits = $visitBase ? (clone $visitBase)->where('is_paid_traffic', true)->count()
            : (int) PaidMarketingVisit::query()->whereIn('domain_id', $domainIds)->whereBetween('last_click_at', [$from, $to])->sum('visits');
        $protectedHits = IpLog::query()
            ->where('is_blocked', true)
            ->whereBetween('updated_at', [$from, $to])
            ->sum('hits');
        $connectedDomains = Domain::query()
            ->where('user_id', $user->id)
            ->where('tag_connected', true)
            ->count();
        $campaignCount = $visitBase
            ? (clone $visitBase)->whereNotNull('utm_campaign')->distinct()->count('utm_campaign')
            : PaidMarketingClick::query()
                ->whereHas('visit.domain', fn ($q) => $q->where('user_id', $user->id))
                ->whereBetween('clicked_at', [$from, $to])
                ->whereNotNull('campaign')
                ->distinct()
                ->count('campaign');

        $invalidVisits = $visitBase
            ? (int) (clone $visitBase)->where('is_invalid_traffic', true)->count()
            : (int) PaidMarketingVisit::query()->whereIn('domain_id', $domainIds)->whereNotNull('threat_group')->whereBetween('last_click_at', [$from, $to])->count();
        $totalVisits = $visitBase
            ? (int) (clone $visitBase)->count()
            : (int) PaidMarketingVisit::query()->whereIn('domain_id', $domainIds)->whereBetween('last_click_at', [$from, $to])->sum('visits');

        $tagHealthy = $connectedDomains > 0;

        return [
            'paidAdvertising' => [
                'visits' => (int) $paidVisits,
                'campaigns' => (int) $campaignCount,
                'invalidVisits' => $invalidVisits,
                'invalidRate' => $totalVisits > 0 ? round(($invalidVisits / $totalVisits) * 100, 2) : 0,
            ],
            'botProtection' => [
                'blockedHits' => (int) $protectedHits,
                'domainsProtected' => (int) $connectedDomains,
                'invalidRate' => $totalVisits > 0 ? round(($invalidVisits / $totalVisits) * 100, 2) : 0,
            ],
            'connectionStatus' => [
                'tracking' => $tagHealthy ? 'Healthy' : 'Pending setup',
                'ingestion' => $totalVisits > 0 ? 'Online' : 'Waiting for traffic',
                'protection' => $invalidVisits > 0 || $protectedHits > 0 ? 'Active' : 'Monitoring',
            ],
            'notifications' => DashboardNotifications::forUser($user->id),
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'ts' => now()->toIso8601String(),
        ];
    }

    /** @return Collection<int, int> */
    private function scopedDomainIds(Request $request): Collection
    {
        $ids = Domain::query()
            ->where('user_id', $request->user()->id)
            ->pluck('id');

        if ($domainId = (int) $request->query('domain_id', 0)) {
            return $ids->intersect([$domainId]);
        }

        return $ids;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dateRange(Request $request): array
    {
        return UserTimezone::dateRangeFromRequest($request, $request->user());
    }
}
