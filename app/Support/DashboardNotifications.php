<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\GoogleConnection;
use App\Models\IpLog;
use App\Models\PaidMarketingVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardNotifications
{
    public static function forUser(int $userId): array
    {
        $domainIds = Domain::query()->where('user_id', $userId)->pluck('id');
        $blockedToday = (int) IpLog::query()
            ->where('is_blocked', true)
            ->whereDate('updated_at', Carbon::today())
            ->sum('hits');

        $paidVisitsToday = 0;
        $invalidToday = 0;

        if (Schema::hasTable('visits')) {
            $paidVisitsToday = (int) DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->where('is_paid_traffic', true)
                ->whereDate('visited_at', Carbon::today())
                ->count();
            $invalidToday = (int) DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->where('is_invalid_traffic', true)
                ->whereDate('visited_at', Carbon::today())
                ->count();
        } else {
            $paidVisitsToday = (int) PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereDate('last_click_at', Carbon::today())
                ->sum('visits');
            $invalidToday = (int) PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereNotNull('threat_group')
                ->whereDate('updated_at', Carbon::today())
                ->count();
        }

        $domains = Domain::query()->where('user_id', $userId)->get();
        $platformReady = $domains->contains(
            fn (Domain $d) => $d->tag_connected && $d->paid_marketing_connected && $d->bot_mitigation_connected
        );
        $pendingDomains = $domains->filter(
            fn (Domain $d) => ! $d->tag_connected || ! $d->paid_marketing_connected || ! $d->bot_mitigation_connected
        )->count();

        $googleOAuthConnected = GoogleConnection::query()->where('user_id', $userId)->exists();

        $items = [
            [
                'type' => 'traffic',
                'title' => 'Paid traffic today',
                'body' => $paidVisitsToday > 0
                    ? number_format($paidVisitsToday) . ' paid visit(s) recorded today.'
                    : 'No paid visits yet today — install the tracking tag on your domain.',
            ],
        ];

        if ($invalidToday > 0) {
            $items[] = [
                'type' => 'security',
                'title' => 'Invalid visits today',
                'body' => number_format($invalidToday) . ' invalid visit(s) detected today.',
            ];
        }

        if ($blockedToday > 0) {
            $items[] = [
                'type' => 'security',
                'title' => 'Blocked hits today',
                'body' => number_format($blockedToday) . ' blocked hit(s) today.',
            ];
        }

        if (! $platformReady && $pendingDomains > 0) {
            $items[] = [
                'type' => 'domains',
                'title' => 'Platform setup',
                'body' => $pendingDomains . ' domain(s) still need Tag Manager, Paid Marketing, or Bot Protection.',
            ];
        } elseif ($platformReady) {
            $items[] = [
                'type' => 'integration',
                'title' => 'Platform ready',
                'body' => 'Tag, paid marketing, and bot protection are connected on at least one domain.',
            ];
        }

        if (! $googleOAuthConnected && $platformReady) {
            $items[] = [
                'type' => 'integration',
                'title' => 'Google Ads OAuth',
                'body' => 'Connect Google under Platform Integrate to sync ad accounts.',
            ];
        }

        return array_slice($items, 0, 5);
    }
}
