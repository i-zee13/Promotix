<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\GoogleConnection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardNotifications
{
    public static function forUser(int $userId): array
    {
        $domainIds = Domain::query()->where('user_id', $userId)->pluck('id');
        $user = User::query()->find($userId);
        $tz = UserTimezone::forUser($user);
        $today = Carbon::now($tz)->toDateString();
        $todayStart = Carbon::parse($today, $tz)->startOfDay()->utc();
        $todayEnd = Carbon::parse($today, $tz)->endOfDay()->utc();

        $blockedToday = 0;
        $paidVisitsToday = 0;
        $invalidToday = 0;

        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $blockedQuery = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$todayStart, $todayEnd]);
            if (Schema::hasColumn('visits', 'action_taken')) {
                $blockedQuery->where('action_taken', 'block');
            } else {
                $blockedQuery->where('is_invalid_traffic', true);
            }
            $blockedToday = (int) $blockedQuery->count();

            $invalidQuery = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->where('is_invalid_traffic', true)
                ->whereBetween('visited_at', [$todayStart, $todayEnd]);
            $invalidToday = (int) $invalidQuery->count();

            $paidQuery = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$todayStart, $todayEnd]);
            GoogleClickAttribution::applyHasClickIdFilter($paidQuery);
            $paidVisitsToday = (int) $paidQuery->count();
        }

        if ($paidVisitsToday === 0 && Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $paidVisitsToday = (int) DB::table('google_ads_campaign_daily_metrics')
                ->whereIn('domain_id', $domainIds)
                ->whereDate('metric_date', $today)
                ->sum('clicks');
        }

        $manualDomains = Domain::query()->where('user_id', $userId)->forBotProtection()->get();
        $paidDomains = Domain::query()->where('user_id', $userId)->forPaidMarketing()->get();

        $botReady = $manualDomains->contains(
            fn (Domain $d) => $d->tag_connected && $d->bot_mitigation_connected
        );
        $paidReady = $paidDomains->isNotEmpty();
        $platformReady = $botReady && $paidReady;

        $pendingDomains = 0;
        if (! $botReady) {
            $pendingDomains += $manualDomains->filter(
                fn (Domain $d) => ! $d->tag_connected || ! $d->bot_mitigation_connected
            )->count();
        }
        if (! $paidReady) {
            $pendingDomains++;
        }

        $googleOAuthConnected = GoogleConnection::query()->where('user_id', $userId)->exists();

        $items = [
            [
                'type' => 'traffic',
                'title' => 'Paid traffic today',
                'body' => $paidVisitsToday > 0
                    ? number_format($paidVisitsToday) . ' Google Ads click(s) tracked for today.'
                    : 'No Google Ads clicks tracked yet today — connect Ads or wait for tagged clicks.',
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
