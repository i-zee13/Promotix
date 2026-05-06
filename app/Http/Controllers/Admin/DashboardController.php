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

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $domainIds = Domain::query()->where('user_id', $user->id)->pluck('id');

        $paidVisits = PaidMarketingVisit::query()->whereIn('domain_id', $domainIds)->sum('visits');
        $protectedHits = IpLog::query()->where('is_blocked', true)->sum('hits');
        $activeDomains = $domainIds->count();
        $campaignCount = PaidMarketingClick::query()
            ->whereHas('visit.domain', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('campaign')
            ->distinct()
            ->count('campaign');

        return response()->json([
            'paidAdvertising' => [
                'visits' => (int) $paidVisits,
                'campaigns' => (int) $campaignCount,
            ],
            'botProtection' => [
                'blockedHits' => (int) $protectedHits,
                'domainsProtected' => (int) $activeDomains,
            ],
        ]);
    }

    public function insights(Request $request): JsonResponse
    {
        $user = $request->user();
        $domainIds = Domain::query()->where('user_id', $user->id)->pluck('id');

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
        $rows = PaidMarketingVisit::query()
            ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id))
            ->select('threat_group', DB::raw('COUNT(*) as total'))
            ->whereNotNull('threat_group')
            ->groupBy('threat_group')
            ->orderByDesc('total')
            ->get();

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
        $newThreats = PaidMarketingVisit::query()
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

        return response()->json([
            'ok' => true,
            'dark_mode' => (bool) $data['dark_mode'],
        ]);
    }

    public function domainPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
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

        return response()->json($rows);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $user = $request->user();
        $campaigns = PaidMarketingClick::query()
            ->whereHas('visit.domain', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('campaign')
            ->distinct()
            ->orderBy('campaign')
            ->pluck('campaign')
            ->values();

        return response()->json($campaigns);
    }
}
