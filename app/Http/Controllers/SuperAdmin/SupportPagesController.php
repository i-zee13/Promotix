<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\AdminIntegrationCatalog;
use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\FeatureFlag;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportPagesController extends Controller
{
    public function domains(Request $request): View
    {
        $domains = Domain::with('user')
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->string('tracking')->toString(), function ($q, string $tracking) {
                $q->where('tag_connected', $tracking === 'enabled');
            })
            ->when($request->string('search')->toString(), function ($q, string $search) {
                $q->where('hostname', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('super-admin.domains', [
            'domains' => $domains,
        ]);
    }

    public function toggleDomainTracking(Domain $domain): RedirectResponse
    {
        $domain->update(['tag_connected' => ! $domain->tag_connected]);

        return back()->with('status', 'Tracker '.($domain->tag_connected ? 'enabled' : 'disabled').' for '.$domain->hostname.'.');
    }

    public function forceVerifyDomain(Domain $domain): RedirectResponse
    {
        $domain->update(['status' => 'connected', 'last_seen_at' => now()]);

        return back()->with('status', 'Domain '.$domain->hostname.' marked as verified.');
    }

    public function regenerateDomainTracker(Domain $domain): RedirectResponse
    {
        $domain->update([
            'domain_key' => Str::uuid()->toString(),
            'secret_key' => Str::uuid()->toString(),
        ]);

        return back()->with('status', 'Tracker code regenerated for '.$domain->hostname.'.');
    }

    public function destroyDomain(Domain $domain): RedirectResponse
    {
        $hostname = $domain->hostname;
        $domain->delete();

        return back()->with('status', "Domain {$hostname} deleted.");
    }

    public function analytics(): View
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subSecond();
        $hasSubs = Schema::hasTable('subscriptions');
        $hasPayments = Schema::hasTable('payments');

        $monthlyAmount = fn ($sub) => $sub->billing_interval === 'yearly'
            ? (int) round($sub->amount_cents / 12)
            : (int) $sub->amount_cents;

        $mrrForMonth = function (Carbon $monthStart) use ($hasSubs, $monthlyAmount): int {
            if (! $hasSubs) {
                return 0;
            }
            $monthEnd = $monthStart->copy()->endOfMonth();

            return Subscription::query()
                ->where('started_at', '<=', $monthEnd)
                ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $monthStart))
                ->get(['amount_cents', 'billing_interval'])
                ->sum($monthlyAmount);
        };

        $months = collect(range(5, 0))->map(fn ($i) => $now->copy()->subMonths($i)->startOfMonth());
        $mrrTrend = $months->map(fn (Carbon $month) => [
            'label' => $month->format('M'),
            'value' => round($mrrForMonth($month) / 100, 2),
        ]);
        $mrrCurrent = $mrrForMonth($monthStart);
        $mrrPrevious = $mrrForMonth($prevMonthStart);
        $mrrDelta = $mrrPrevious > 0 ? round((($mrrCurrent - $mrrPrevious) / $mrrPrevious) * 100, 1) : 0;

        $activeAtStart = $hasSubs
            ? Subscription::where('started_at', '<', $monthStart)
                ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $monthStart))
                ->count()
            : 0;
        $cancelledThisMonth = $hasSubs
            ? Subscription::whereBetween('cancelled_at', [$monthStart, $now])->count()
            : 0;
        $churnRate = $activeAtStart > 0 ? round(($cancelledThisMonth / $activeAtStart) * 100, 1) : 0;
        $cancelledLastMonth = $hasSubs
            ? Subscription::whereBetween('cancelled_at', [$prevMonthStart, $prevMonthEnd])->count()
            : 0;
        $churnDelta = $cancelledLastMonth > 0
            ? round((($cancelledThisMonth - $cancelledLastMonth) / $cancelledLastMonth) * 100, 1)
            : 0;

        $payingCustomers = $hasPayments ? Payment::where('status', 'paid')->distinct('user_id')->count('user_id') : 0;
        $totalRevenue = $hasPayments ? Payment::where('status', 'paid')->sum('amount_cents') : 0;
        $ltv = $payingCustomers > 0 ? (int) round($totalRevenue / $payingCustomers) : 0;

        $activeSubsCount = $hasSubs ? Subscription::where('status', 'active')->count() : 0;
        $days = collect(range(6, 0))->map(fn ($i) => $now->copy()->subDays($i)->startOfDay());
        $activeSubsTrend = $days->map(fn (Carbon $day) => [
            'label' => $day->format('D'),
            'value' => $hasSubs
                ? Subscription::where('started_at', '<=', $day->copy()->endOfDay())
                    ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $day))
                    ->count()
                : 0,
        ]);
        $activeSubsWeekAgo = (int) ($activeSubsTrend->first()['value'] ?? 0);
        $activeSubsDelta = $activeSubsWeekAgo > 0
            ? round((($activeSubsCount - $activeSubsWeekAgo) / $activeSubsWeekAgo) * 100, 1)
            : 0;

        $hadTrial = $hasSubs ? Subscription::whereNotNull('trial_ends_at')->count() : 0;
        $convertedFromTrial = $hasSubs
            ? Subscription::whereNotNull('trial_ends_at')->where('is_trial', false)->count()
            : 0;
        $conversionRate = $hadTrial > 0 ? round(($convertedFromTrial / $hadTrial) * 100, 1) : 0;

        $newTrialsCount = $hasSubs ? Subscription::where('is_trial', true)->whereBetween('created_at', [$monthStart, $now])->count() : 0;
        $daysElapsedThisMonth = max(1, $monthStart->diffInDays($now) + 1);
        $newTrialsAvgPerDay = round($newTrialsCount / $daysElapsedThisMonth, 1);

        $contractionMrrCents = $hasSubs
            ? Subscription::whereBetween('cancelled_at', [$monthStart, $now])->get(['amount_cents', 'billing_interval'])->sum($monthlyAmount)
            : 0;

        $usageRows = collect(range(5, 0))->map(function ($i) use ($now) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $hasVisits = Schema::hasTable('visits');

            return [
                'date' => $day->format('M j'),
                'active_users' => $hasVisits
                    ? DB::table('visits')->whereBetween('visited_at', [$day, $day->copy()->endOfDay()])->distinct('session_id')->count('session_id')
                    : 0,
                'events_logged' => $hasVisits
                    ? DB::table('visits')->whereBetween('visited_at', [$day, $day->copy()->endOfDay()])->count()
                    : 0,
            ];
        })->reverse()->values();

        return view('super-admin.analytics', [
            'mrrTrend' => $mrrTrend,
            'mrrCurrent' => $mrrCurrent,
            'mrrDelta' => $mrrDelta,
            'churnRate' => $churnRate,
            'churnDelta' => $churnDelta,
            'ltv' => $ltv,
            'activeSubsCount' => $activeSubsCount,
            'activeSubsTrend' => $activeSubsTrend,
            'activeSubsDelta' => $activeSubsDelta,
            'conversionRate' => $conversionRate,
            'churnedCustomersCount' => $cancelledThisMonth,
            'contractionMrrCents' => $contractionMrrCents,
            'newTrialsCount' => $newTrialsCount,
            'newTrialsAvgPerDay' => $newTrialsAvgPerDay,
            'usageRows' => $usageRows,
            'totalCustomers' => User::count(),
        ]);
    }

    public function security(Request $request): View
    {
        $loginRows = \App\Models\LoginHistory::with('user')->latest('id')->limit(150)->get()->map(function ($r) {
            $isSuccess = $r->status === 'success';

            return [
                'type' => 'Login',
                'user_name' => $r->user?->name,
                'user_email' => $r->user?->email,
                'details' => ($isSuccess ? 'Login Success' : 'Suspicious Login'),
                'ip' => $r->ip_address,
                'time' => $r->created_at,
                'status' => $isSuccess ? 'Successful' : 'Suspicious',
                'variant' => $isSuccess ? 'success' : 'suspicious',
            ];
        });

        $detectionRows = Schema::hasTable('detection_logs')
            ? DB::table('detection_logs')->orderByDesc('detected_at')->limit(150)->get()->map(function ($r) {
                $variant = match ($r->action_taken) {
                    'block' => 'banned',
                    'flag', 'challenge' => 'suspicious',
                    default => 'success',
                };

                return [
                    'type' => 'Detection',
                    'user_name' => null,
                    'user_email' => null,
                    'details' => $r->threat_group ? ucwords(str_replace(['_', '-'], ' ', $r->threat_group)) : ucfirst($r->action_taken),
                    'ip' => $r->ip,
                    'time' => Carbon::parse($r->detected_at),
                    'status' => match ($variant) { 'banned' => 'Banned', 'suspicious' => 'Suspicious', default => 'Successful' },
                    'variant' => $variant,
                ];
            })
            : collect();

        $all = $loginRows->concat($detectionRows)->sortByDesc('time')->values();

        if ($search = $request->string('search')->toString()) {
            $all = $all->filter(fn ($row) => str_contains(strtolower((string) $row['ip']), strtolower($search))
                || str_contains(strtolower((string) $row['user_name']), strtolower($search))
                || str_contains(strtolower((string) $row['user_email']), strtolower($search)))->values();
        }

        if ($type = $request->string('type')->toString()) {
            $all = $all->where('type', $type)->values();
        }

        if ($result = $request->string('result')->toString()) {
            $all = $all->where('status', $result)->values();
        }

        $perPage = 10;
        $page = (int) $request->get('page', 1);
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();
        $rows = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('super-admin.security', [
            'rows' => $rows,
        ]);
    }

    public function settings(): View
    {
        $settings = AppSetting::query()->orderBy('group')->orderBy('key')->get();
        $grouped = $settings->groupBy('group');

        return view('super-admin.simple.settings', [
            'featureFlags' => FeatureFlag::orderBy('name')->get(),
            'settingsByGroup' => $grouped,
            'plans' => \App\Models\Plan::where('is_active', true)->orderBy('price_cents')->get(['id', 'slug', 'name']),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $payload = $request->input('settings', []);
        if (! is_array($payload)) {
            return back()->withErrors(['settings' => 'Invalid payload.']);
        }

        foreach ($payload as $key => $value) {
            AppSetting::set($key, $value);
        }
        AppSetting::flushCache();

        return back()->with('status', 'Settings saved.');
    }

    public function storeFeatureFlag(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:feature_flags,key'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        FeatureFlag::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'enabled' => (bool) ($data['enabled'] ?? true),
        ]);

        return back()->with('status', 'Feature flag created.');
    }

    public function toggleFeatureFlag(FeatureFlag $featureFlag): RedirectResponse
    {
        $featureFlag->update(['enabled' => ! $featureFlag->enabled]);

        return back()->with('status', 'Feature flag updated.');
    }

    public function trafficBotLogs(Request $request): View
    {
        $domainIds = Domain::query()->pluck('id');
        $base = Schema::hasTable('visits') && $domainIds->isNotEmpty()
            ? DB::table('visits')->whereIn('domain_id', $domainIds)
            : null;
        $stats = [
            'total_requests' => $base ? (clone $base)->count() : 0,
            'threat_groups' => $base ? (clone $base)->whereNotNull('threat_group')->distinct('threat_group')->count('threat_group') : 0,
            'blocked_traffic' => $base ? (clone $base)->where('action_taken', 'block')->count() : 0,
            'allow_lists' => \App\Models\IpLog::query()->where('is_blocked', false)->count(),
        ];

        return view('super-admin.traffic.index', [
            'stats' => $stats,
            'domains' => Domain::query()->orderBy('hostname')->get(['id', 'hostname']),
        ]);
    }

    public function automation(Request $request): View
    {
        return app(\App\Http\Controllers\Admin\AutomationController::class)->superAdminIndex($request);
    }

    public function integrations(Request $request): View
    {
        $integrations = AdminIntegrationCatalog::listForUser($request->user()->id);
        $cards = collect($integrations)->map(function (array $row) {
            return array_merge($row, AdminIntegrationCatalog::cardMeta($row['name']));
        })->values()->all();

        return view('super-admin.integrations.index', [
            'integrations' => $cards,
        ]);
    }
}
