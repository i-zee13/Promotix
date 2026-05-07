<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\IpLog;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function insights(Request $request): JsonResponse
    {
        $user = $request->user();
        $domainIds = Domain::query()->where('user_id', $user->id)->pluck('id');

        if (Schema::hasTable('visits')) {
            $totalClicks = DB::table('visits')->whereIn('domain_id', $domainIds)->count();
            $suspiciousVisits = DB::table('visits')->whereIn('domain_id', $domainIds)->where('is_invalid_traffic', true)->count();
            $topCampaign = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereNotNull('utm_campaign')
                ->select('utm_campaign as campaign', DB::raw('COUNT(*) as total'))
                ->groupBy('utm_campaign')
                ->orderByDesc('total')
                ->first();
        } else {
            $totalClicks = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
                ->count();
            $suspiciousVisits = PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereNotNull('threat_group')
                ->count();
            $topCampaign = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
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
        $user = $request->user();
        $campaign = trim((string) $request->query('campaign', ''));
        $path = trim((string) $request->query('path', ''));

        if (Schema::hasTable('visits')) {
            $query = DB::table('visits')->whereIn('domain_id', Domain::query()->where('user_id', $user->id)->pluck('id'));

            if ($campaign !== '') {
                $query->where('utm_campaign', $campaign);
            }
            if ($path !== '') {
                $query->where('url', 'like', '%' . $path . '%');
            }

            $rows = $query
                ->selectRaw('DATE(visited_at) as day, COUNT(*) as total')
                ->where('is_invalid_traffic', true)
                ->where('visited_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        } else {
            $query = PaidMarketingClick::query()
                ->whereHas('visit.domain', fn ($q) => $q->where('user_id', $user->id));

            if ($campaign !== '') {
                $query->where('campaign', $campaign);
            }
            if ($path !== '') {
                $query->where('path', 'like', '%' . $path . '%');
            }

            $rows = $query
                ->selectRaw('DATE(clicked_at) as day, COUNT(*) as total')
                ->whereNotNull('clicked_at')
                ->where('clicked_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        }

        $indexed = $rows->pluck('total', 'day')->all();
        $labels = [];
        $values = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);
            $dateKey = $day->toDateString();
            $labels[] = $day->format('M d');
            $values[] = (int) ($indexed[$dateKey] ?? 0);
        }

        return response()->json([
            'labels' => $labels,
            'values' => $values,
        ]);
    }

    public function threats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (Schema::hasTable('detection_logs')) {
            $domainIds = Domain::query()->where('user_id', $user->id)->pluck('id');
            $rows = DB::table('detection_logs')
                ->whereIn('domain_id', $domainIds)
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->whereNotNull('threat_group')
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();
        } else {
            $rows = PaidMarketingVisit::query()
                ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id))
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
        $user = $request->user();

        $blockedToday = IpLog::query()
            ->where('is_blocked', true)
            ->whereDate('updated_at', Carbon::today())
            ->sum('hits');
        $newDomains = Domain::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->count();
        $newThreats = Schema::hasTable('detection_logs')
            ? DB::table('detection_logs')->whereIn('domain_id', Domain::query()->where('user_id', $user->id)->pluck('id'))->whereDate('detected_at', Carbon::today())->count()
            : PaidMarketingVisit::query()
                ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotNull('threat_group')
                ->whereDate('updated_at', Carbon::today())
                ->count();

        return response()->json([
            ['type' => 'security', 'title' => 'Blocked hits today', 'body' => $blockedToday . ' suspicious hits blocked.'],
            ['type' => 'domains', 'title' => 'New domains', 'body' => $newDomains . ' domain(s) added today.'],
            ['type' => 'threats', 'title' => 'Threat signals', 'body' => $newThreats . ' threat-tagged visit(s) detected today.'],
        ]);
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
        if (Schema::hasTable('visits')) {
            $rows = Domain::query()
                ->where('user_id', $user->id)
                ->leftJoin('visits', 'domains.id', '=', 'visits.domain_id')
                ->select('domains.hostname', DB::raw('COUNT(visits.id) as visits_count'), DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as threat_visits_count'))
                ->groupBy('domains.id', 'domains.hostname')
                ->orderByDesc('visits_count')
                ->limit(10)
                ->get()
                ->map(fn ($d) => [
                    'domain' => $d->hostname,
                    'visits' => (int) $d->visits_count,
                    'threats' => (int) $d->threat_visits_count,
                ]);
        } else {
            $rows = Domain::query()
                ->where('user_id', $user->id)
                ->withCount([
                    'paidMarketingVisits as visits_count',
                    'paidMarketingVisits as threat_visits_count' => fn ($q) => $q->whereNotNull('threat_group'),
                ])
                ->orderByDesc('visits_count')
                ->limit(10)
                ->get()
                ->map(fn ($d) => [
                    'domain' => $d->hostname,
                    'visits' => (int) $d->visits_count,
                    'threats' => (int) $d->threat_visits_count,
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
        $domainIds = Domain::query()->where('user_id', $user->id)->pluck('id');

        $paidVisits = Schema::hasTable('visits')
            ? DB::table('visits')->whereIn('domain_id', $domainIds)->count()
            : PaidMarketingVisit::query()->whereIn('domain_id', $domainIds)->sum('visits');
        $protectedHits = IpLog::query()->where('is_blocked', true)->sum('hits');
        $activeDomains = $domainIds->count();
        $campaignCount = Schema::hasTable('visits')
            ? DB::table('visits')->whereIn('domain_id', $domainIds)->whereNotNull('utm_campaign')->distinct()->count('utm_campaign')
            : PaidMarketingClick::query()
                ->whereHas('visit.domain', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotNull('campaign')
                ->distinct()
                ->count('campaign');

        $blockedToday = IpLog::query()
            ->where('is_blocked', true)
            ->whereDate('updated_at', Carbon::today())
            ->sum('hits');
        $newDomains = Domain::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->count();
        $newThreats = Schema::hasTable('detection_logs')
            ? DB::table('detection_logs')->whereIn('domain_id', $domainIds)->whereDate('detected_at', Carbon::today())->count()
            : PaidMarketingVisit::query()
                ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotNull('threat_group')
                ->whereDate('updated_at', Carbon::today())
                ->count();

        return [
            'paidAdvertising' => [
                'visits' => (int) $paidVisits,
                'campaigns' => (int) $campaignCount,
            ],
            'botProtection' => [
                'blockedHits' => (int) $protectedHits,
                'domainsProtected' => (int) $activeDomains,
            ],
            'notifications' => [
                ['type' => 'security', 'title' => 'Blocked hits today', 'body' => $blockedToday . ' suspicious hits blocked.'],
                ['type' => 'domains', 'title' => 'New domains', 'body' => $newDomains . ' domain(s) added today.'],
                ['type' => 'threats', 'title' => 'Threat signals', 'body' => $newThreats . ' threat-tagged visit(s) detected today.'],
            ],
            'ts' => now()->toIso8601String(),
        ];
    }
}
