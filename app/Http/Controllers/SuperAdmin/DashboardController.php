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
            'total_events' => Schema::hasTable('visits') ? DB::table('visits')->count() : 0,
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

        $usageByProduct = [
            ['label' => 'Domains', 'value' => Schema::hasTable('domains') ? Domain::count() : 0],
            ['label' => 'Visits', 'value' => Schema::hasTable('visits') ? DB::table('visits')->count() : 0],
            ['label' => 'Detections', 'value' => Schema::hasTable('detection_logs') ? DB::table('detection_logs')->count() : 0],
            ['label' => 'Integrations', 'value' => Schema::hasTable('google_connections') ? DB::table('google_connections')->count() : 0],
        ];

        $queueDepth = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;

        return view('super-admin.dashboard', [
            'kpis' => $kpis,
            'revenueTrend' => $revenueTrend,
            'userGrowth' => $userGrowth,
            'usageByProduct' => $usageByProduct,
            'systemHealth' => [
                'database' => 'Operational',
                'queue' => $queueDepth === 0 ? 'No backlog' : "{$queueDepth} queued",
                'api' => 'Routes loaded',
            ],
        ]);
    }
}
