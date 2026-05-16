<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PaidAdvertisingDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('hostname')
            ->get(['id', 'hostname']);

        return view('paid-marketing.dashboard', [
            'domains' => $domains,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visits')) {
            return response()->json($this->emptySummary());
        }

        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $base = $this->scopedVisitsQuery($request, $domainIds, $from, $to);

        $paid = (clone $base)->count();
        $invalid = (clone $base)->where('is_invalid_traffic', true)->count();
        $blocked = 0;
        $flagged = 0;

        if (Schema::hasColumn('visits', 'action_taken')) {
            $blocked = (clone $base)->where('action_taken', 'block')->count();
            $flagged = (clone $base)->where('action_taken', 'flag')->count();
        }

        return response()->json([
            'paid_visits' => $paid,
            'invalid_paid_visits' => $invalid,
            'blocked_paid_visits' => $blocked,
            'flagged_paid_visits' => $flagged,
            'valid_paid_visits' => max(0, $paid - $invalid),
            'window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visits')) {
            return response()->json(['labels' => [], 'datasets' => []]);
        }

        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->selectRaw('DATE(visited_at) as day, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $labels = [];
        $paidSeries = [];
        $invalidSeries = [];

        $period = $from->copy();
        while ($period->lt($to)) {
            $key = $period->toDateString();
            $row = $rows->firstWhere('day', $key);
            $labels[] = $period->format('M d');
            $paidSeries[] = (int) ($row->total ?? 0);
            $invalidSeries[] = (int) ($row->invalid ?? 0);
            $period->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['name' => 'Paid', 'values' => $paidSeries],
                ['name' => 'Invalid', 'values' => $invalidSeries],
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
        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        [$from, $to] = $this->dateRange($request);
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
        ])->values());
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
        if (! Schema::hasTable('visits')) {
            return response()->json([]);
        }

        [$from, $to] = $this->dateRange($request);
        $domainIds = $this->scopedDomainIds($request);

        $rows = $this->scopedVisitsQuery($request, $domainIds, $from, $to)
            ->select(
                'ip',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'),
                DB::raw('MAX(country) as country'),
                DB::raw('MAX(visited_at) as last_seen')
            )
            ->groupBy('ip')
            ->orderByDesc('total')
            ->limit(50)
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'ip' => $r->ip,
            'country' => $r->country,
            'total' => (int) $r->total,
            'invalid' => (int) $r->invalid,
            'last_seen' => (string) ($r->last_seen ?? ''),
        ])->values());
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

    private function scopedDomainIds(Request $request)
    {
        $userDomainIds = Domain::query()->where('user_id', $request->user()->id)->pluck('id');

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

        return $query;
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
