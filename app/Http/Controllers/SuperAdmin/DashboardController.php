<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $kpis = [
            'total_users' => User::count(),
            'active_subscriptions' => Schema::hasTable('subscriptions') ? Subscription::where('status', 'active')->count() : 0,
            'monthly_revenue_cents' => Schema::hasTable('payments')
                ? Payment::where('status', 'paid')->whereBetween('paid_at', [$monthStart, $now])->sum('amount_cents')
                : 0,
            'failed_payments' => Schema::hasTable('payments') ? Payment::where('status', 'failed')->count() : 0,
            'active_domains' => Schema::hasTable('domains') ? Domain::whereIn('status', ['active', 'connected'])->count() : 0,
            'total_events_today' => Schema::hasTable('visits')
                ? DB::table('visits')->whereDate('created_at', $now->toDateString())->count()
                : 0,
        ];

        $activeSubscriptions = Schema::hasTable('subscriptions')
            ? Subscription::query()
                ->with(['user', 'plan'])
                ->where('status', 'active')
                ->latest('id')
                ->limit(12)
                ->get()
            : collect();

        $failedPayments = Schema::hasTable('payments')
            ? Payment::query()
                ->with(['user', 'plan'])
                ->whereIn('status', ['failed', 'rejected'])
                ->latest('id')
                ->limit(6)
                ->get()
            : collect();

        $kpiProgress = [
            'total_users' => min(100, max(18, (int) round($kpis['total_users'] / 15000 * 100))),
            'active_subscriptions' => min(100, max(18, (int) round($kpis['active_subscriptions'] / 5000 * 100))),
            'monthly_revenue' => min(100, max(18, (int) round(($kpis['monthly_revenue_cents'] / 100) / 30000 * 100))),
            'failed_payments' => min(100, max(15, $kpis['failed_payments'] * 6)),
            'active_domains' => min(100, max(18, (int) round($kpis['active_domains'] / 100 * 100))),
            'total_events_today' => min(100, max(12, (int) round($kpis['total_events_today'] / 1000 * 100))),
        ];

        $months = collect(range(5, 0))->map(fn ($i) => $now->copy()->subMonths($i)->startOfMonth());
        $revenueTrend = $months->map(function (Carbon $month): array {
            $amount = Schema::hasTable('payments')
                ? Payment::where('status', 'paid')->whereBetween('paid_at', [$month, $month->copy()->endOfMonth()])->sum('amount_cents')
                : 0;

            return ['label' => $month->format('M'), 'value' => round($amount / 100, 2)];
        });

        $userGrowth = $months->map(function (Carbon $month): array {
            return [
                'label' => $month->format('M'),
                'value' => User::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count(),
            ];
        });

        return view('super-admin.dashboard', [
            'kpis' => $kpis,
            'kpiProgress' => $kpiProgress,
            'revenueTrend' => $revenueTrend,
            'userGrowth' => $userGrowth,
            'activeSubscriptions' => $activeSubscriptions,
            'failedPayments' => $failedPayments,
        ]);
    }
}
