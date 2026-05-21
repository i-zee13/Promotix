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
        $blockedToday = IpLog::query()
            ->where('is_blocked', true)
            ->whereDate('updated_at', Carbon::today())
            ->sum('hits');

        $paidVisitsToday = 0;
        $invalidToday = 0;
        $countriesToday = 0;

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
            $countriesToday = (int) DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereDate('visited_at', Carbon::today())
                ->whereNotNull('country')
                ->distinct()
                ->count('country');
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
            $countriesToday = (int) PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereDate('last_click_at', Carbon::today())
                ->whereNotNull('country')
                ->distinct()
                ->count('country');
        }

        $googleConnected = GoogleConnection::query()->where('user_id', $userId)->exists();
        $pendingDomains = Domain::query()
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->where('tag_connected', false)->orWhere('status', 'pending'))
            ->count();
        $liveCampaigns = 0;
        if (Schema::hasTable('visits')) {
            $liveCampaigns = (int) DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->where('is_paid_traffic', true)
                ->whereNotNull('utm_campaign')
                ->where('visited_at', '>=', Carbon::now()->subDays(7))
                ->distinct()
                ->count('utm_campaign');
        }

        $items = [
            [
                'type' => 'traffic',
                'title' => 'Paid traffic today',
                'body' => $paidVisitsToday > 0
                    ? number_format($paidVisitsToday) . ' paid visit(s) recorded today.'
                    : 'No paid visits yet today — install the tracking tag on your domain.',
            ],
            [
                'type' => 'security',
                'title' => 'Blocked hits today',
                'body' => $blockedToday > 0
                    ? number_format((int) $blockedToday) . ' blocked hit(s) today.'
                    : 'No blocked IPs yet today.',
            ],
            [
                'type' => 'geo',
                'title' => 'Countries reviewed',
                'body' => $countriesToday > 0
                    ? $countriesToday . ' countr' . ($countriesToday === 1 ? 'y' : 'ies') . ' seen in traffic today.'
                    : 'Waiting for geo data from live visits.',
            ],
            [
                'type' => 'integration',
                'title' => 'Google Ads',
                'body' => $googleConnected
                    ? 'Google account connected — sync accounts under Platform Integrate.'
                    : 'Connect Google Ads to link campaigns to your domains.',
            ],
            [
                'type' => 'campaigns',
                'title' => 'Campaigns',
                'body' => $liveCampaigns > 0
                    ? $liveCampaigns . ' active campaign(s) with traffic in the last 7 days.'
                    : ($invalidToday > 0
                        ? number_format($invalidToday) . ' invalid visit(s) detected today.'
                        : 'No campaign traffic yet — check tag + UTM/gclid on ad URLs.'),
            ],
        ];

        if ($pendingDomains > 0) {
            $items[] = [
                'type' => 'domains',
                'title' => 'Domains pending setup',
                'body' => $pendingDomains . ' domain(s) need tag installation or first ping.',
            ];
        }

        return $items;
    }
}
