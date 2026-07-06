<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$domain = Domain::query()->where('hostname', 'like', '%onpoint%')->first();
if (! $domain) {
    fwrite(STDERR, "Domain not found\n");
    exit(1);
}

echo "Domain: {$domain->hostname} (#{$domain->id})\n";
$domain->load('googleAdsAccount');
echo 'Google account: ' . ($domain->googleAdsAccount?->account_name ?? 'none');
echo ' | TZ: ' . ($domain->googleAdsAccount?->time_zone ?? 'n/a') . "\n\n";

$date = $argv[1] ?? '2026-07-06';
$tz = 'UTC';

echo "=== Visits on {$date} ({$tz}) with click ID ===\n";
if (Schema::hasTable('visits')) {
    $q = DB::table('visits')->where('domain_id', $domain->id);
    UserTimezone::applyCalendarDateRangeFilter($q, 'visited_at', $date, $date, null, $tz);
    GoogleClickAttribution::applyHasClickIdFilter($q);

    $visits = (clone $q)->orderBy('visited_at')->get();
    echo 'Paid visit count: ' . $visits->count() . "\n\n";

    foreach ($visits as $v) {
        echo "--- Visit #{$v->id} ---\n";
        echo "  visited_at (UTC): {$v->visited_at}\n";
        echo "  ip: {$v->ip}\n";
        echo "  url: " . ($v->url ?? 'n/a') . "\n";
        echo "  gclid: " . ($v->gclid ?? '') . "\n";
        echo "  gbraid: " . ($v->gbraid ?? '') . "\n";
        echo "  wbraid: " . ($v->wbraid ?? '') . "\n";
        echo "  utm_source: " . ($v->utm_source ?? '') . "\n";
        echo "  utm_medium: " . ($v->utm_medium ?? '') . "\n";
        echo "  utm_campaign: " . ($v->utm_campaign ?? '') . "\n";
        echo "  campaign_name: " . ($v->campaign_name ?? '') . "\n";
        echo "  referrer: " . ($v->referrer ?? '') . "\n";
        echo "  is_invalid_traffic: " . ($v->is_invalid_traffic ?? '') . "\n";
    }

    echo "\n=== All visits on {$date} (no filter) ===\n";
    $all = DB::table('visits')->where('domain_id', $domain->id);
    UserTimezone::applyCalendarDateRangeFilter($all, 'visited_at', $date, $date, null, $tz);
    echo 'Total visits: ' . $all->count() . "\n";
    foreach ($all->orderBy('visited_at')->get() as $v) {
        $hasGclid = filled($v->gclid ?? '') || filled($v->gbraid ?? '') || filled($v->wbraid ?? '');
        echo "  #{$v->id} {$v->visited_at} ip={$v->ip} paid=" . ($hasGclid ? 'yes' : 'no');
        if ($hasGclid) {
            echo ' gclid=' . substr((string) ($v->gclid ?? $v->gbraid ?? $v->wbraid), 0, 40);
        }
        echo "\n";
    }
}

if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
    echo "\n=== Google stored metrics for {$date} ===\n";
    $metrics = DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $domain->id)
        ->where('metric_date', $date)
        ->get(['campaign_name', 'clicks', 'impressions', 'cost', 'metric_date']);
    if ($metrics->isEmpty()) {
        echo "No rows for {$date}\n";
        $near = DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domain->id)
            ->orderByDesc('metric_date')
            ->limit(5)
            ->get(['metric_date', 'campaign_name', 'clicks']);
        echo "Latest stored dates:\n";
        foreach ($near as $m) {
            echo "  {$m->metric_date} | {$m->campaign_name} | clicks={$m->clicks}\n";
        }
    } else {
        foreach ($metrics as $m) {
            echo "  {$m->campaign_name}: clicks={$m->clicks}, cost={$m->cost}\n";
        }
    }
}

$domain->load('user');
$user = $domain->user;
echo "\n=== Owner user ===\n";
echo "email: {$user->email}\n";
echo 'reporting_timezone: ' . ($user->reporting_timezone ?? 'profile') . "\n";
echo 'profile timezone: ' . ($user->timezone ?? 'n/a') . "\n";

foreach (['UTC', 'Asia/Karachi', 'America/New_York'] as $testTz) {
    $q = DB::table('visits')->where('domain_id', $domain->id);
    UserTimezone::applyCalendarDateRangeFilter($q, 'visited_at', $date, $date, $user, $testTz);
    GoogleClickAttribution::applyHasClickIdFilter($q);
    echo "Paid count ({$testTz}): " . $q->count() . "\n";
}

if (Schema::hasTable('paid_marketing_clicks')) {
    echo "\n=== paid_marketing_clicks on {$date} ===\n";
    $pm = DB::table('paid_marketing_clicks as pc')
        ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
        ->where('pv.domain_id', $domain->id);
    UserTimezone::applyCalendarDateRangeFilter($pm, 'pc.clicked_at', $date, $date, null, $tz);
    GoogleClickAttribution::applyPaidClickIdFilter($pm, 'pc.paid_id');
    echo 'Count: ' . (clone $pm)->count() . "\n";
    foreach ((clone $pm)->select('pc.*')->orderBy('pc.clicked_at')->get() as $c) {
        echo "  {$c->clicked_at} ip={$c->ip} paid_id=" . substr((string) $c->paid_id, 0, 50) . " path={$c->path}\n";
    }
}
