<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\GoogleConnection;
use App\Models\IpLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardNotifications
{
    public static function forUser(int $userId): array
    {
        $domainIds = Domain::query()->where('user_id', $userId)->forPaidMarketing()->pluck('id');
        $blockedToday = (int) IpLog::query()
            ->where('is_blocked', true)
            ->whereDate('updated_at', Carbon::today())
            ->sum('hits');

        $paidVisitsToday = 0;
        $invalidToday = 0;
        $today = Carbon::today()->toDateString();

        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $paidVisitsToday = (int) DB::table('google_ads_campaign_daily_metrics')
                ->whereIn('domain_id', $domainIds)
                ->whereDate('metric_date', $today)
                ->sum('clicks');
        }

        if (Schema::hasTable('visits')) {
            $invalidQuery = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->where('is_invalid_traffic', true)
                ->whereDate('visited_at', Carbon::today());
            GoogleClickAttribution::applyHasClickIdFilter($invalidQuery);
            $invalidToday = (int) $invalidQuery->count();
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
                    ? number_format($paidVisitsToday) . ' Google Ads click(s) synced for today.'
                    : 'No Google Ads clicks synced yet today — connect and sync your ad account.',
            ],
        ];

        if ($invalidToday > 0) {
            $items[] = [
                'type' => 'security',
                'title' => 'Invalid visits today',
                'body' => number_format($invalidToday) . ' invalid paid click visit(s) detected today.',
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
