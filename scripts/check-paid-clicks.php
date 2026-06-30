<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$hostname = $argv[1] ?? 'insuranceforme.online';
$date = $argv[2] ?? '2026-05-29';

$domain = \App\Models\Domain::query()->where('hostname', 'like', '%' . str_replace('.online', '', $hostname) . '%')->first();
if (! $domain) {
    echo "Domain not found for: {$hostname}\n";
    exit(1);
}

echo "=== Domain #{$domain->id} {$domain->hostname} ===\n";
echo "last_seen_at: {$domain->last_seen_at}\n";
echo "tag_connected: " . (int) $domain->tag_connected . "\n";
echo "Date filter: {$date}\n\n";

$paidFilter = function ($q) {
    $q->where(function ($g) {
        $g->whereNotNull('gclid')->where('gclid', '!=', '')
            ->orWhereNotNull('gbraid')->where('gbraid', '!=', '')
            ->orWhereNotNull('wbraid')->where('wbraid', '!=', '');
    });
};

$visitsPaid = DB::table('visits')->where('domain_id', $domain->id)->whereDate('visited_at', $date)->where($paidFilter)->count();
$visitsAll = DB::table('visits')->where('domain_id', $domain->id)->whereDate('visited_at', $date)->count();
$uniqueIps = DB::table('visits')->where('domain_id', $domain->id)->whereDate('visited_at', $date)->where($paidFilter)->distinct()->count('ip');
$invalid = DB::table('visits')->where('domain_id', $domain->id)->whereDate('visited_at', $date)->where($paidFilter)->where('is_invalid_traffic', 1)->count();

$pmClicks = DB::table('paid_marketing_clicks as pc')
    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
    ->where('pv.domain_id', $domain->id)
    ->whereDate('pc.clicked_at', $date)
    ->count();

$googleClicks = 0;
if (\Illuminate\Support\Facades\Schema::hasTable('google_ads_campaign_daily_metrics')) {
    $googleClicks = (int) DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $domain->id)
        ->where('metric_date', $date)
        ->sum('clicks');
}

echo "--- Counts ---\n";
echo "Google Ads API (google_ads_campaign_daily_metrics): {$googleClicks}\n";
echo "Tag visits with gclid (visits table): {$visitsPaid}\n";
echo "Tag visits all (visits table): {$visitsAll}\n";
echo "Unique IPs (paid visits): {$uniqueIps}\n";
echo "Invalid paid visits: {$invalid}\n";
echo "paid_marketing_clicks rows: {$pmClicks}\n";
echo "Dashboard Paid Traffic card uses: tag visits = {$tagPaid} (Google reference = {$googleClicks})\n\n";

echo "--- IPs on {$date} (paid visits) ---\n";
$ips = DB::table('visits')
    ->where('domain_id', $domain->id)
    ->whereDate('visited_at', $date)
    ->where($paidFilter)
    ->select('ip', DB::raw('COUNT(*) as total'), DB::raw('SUM(is_invalid_traffic) as invalid'))
    ->groupBy('ip')
    ->orderByDesc('total')
    ->get();

if ($ips->isEmpty()) {
    echo "(none)\n";
} else {
    foreach ($ips as $row) {
        echo "  {$row->ip}  total={$row->total}  invalid={$row->invalid}\n";
    }
}

echo "\n--- Google campaigns on {$date} ---\n";
if (\Illuminate\Support\Facades\Schema::hasTable('google_ads_campaign_daily_metrics')) {
    $campaigns = DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $domain->id)
        ->where('metric_date', $date)
        ->select('campaign_id', 'campaign_name', 'clicks', 'impressions')
        ->orderByDesc('clicks')
        ->get();
    foreach ($campaigns as $c) {
        echo "  [{$c->campaign_id}] {$c->campaign_name}: {$c->clicks} clicks\n";
    }
}

echo "\n--- Google clicks by day (last 7 days) ---\n";
if (\Illuminate\Support\Facades\Schema::hasTable('google_ads_campaign_daily_metrics')) {
    $from = date('Y-m-d', strtotime($date . ' -6 days'));
    $days = DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $domain->id)
        ->whereBetween('metric_date', [$from, $date])
        ->selectRaw('metric_date, SUM(clicks) as clicks')
        ->groupBy('metric_date')
        ->orderBy('metric_date')
        ->get();
    $sum = 0;
    foreach ($days as $d) {
        echo "  {$d->metric_date}: {$d->clicks}\n";
        $sum += (int) $d->clicks;
    }
    echo "  RANGE TOTAL ({$from} to {$date}): {$sum}\n";
}

echo "\n--- Tag paid visits by day (last 7 days) ---\n";
$from = date('Y-m-d', strtotime($date . ' -6 days'));
$tagDays = DB::table('visits')
    ->where('domain_id', $domain->id)
    ->whereBetween(DB::raw('DATE(visited_at)'), [$from, $date])
    ->where($paidFilter)
    ->selectRaw('DATE(visited_at) as day, COUNT(*) as total, COUNT(DISTINCT ip) as ips')
    ->groupBy('day')
    ->orderBy('day')
    ->get();
if ($tagDays->isEmpty()) {
    echo "  (none — tag did not capture paid visits these days)\n";
} else {
    foreach ($tagDays as $d) {
        echo "  {$d->day}: {$d->total} visits, {$d->ips} unique IP(s)\n";
    }
}

echo "\n--- Recent tag visits (last 10 ever) ---\n";
$recent = DB::table('visits')
    ->where('domain_id', $domain->id)
    ->where($paidFilter)
    ->orderByDesc('visited_at')
    ->limit(10)
    ->get(['visited_at', 'ip', 'gclid', 'is_invalid_traffic']);
foreach ($recent as $r) {
    echo "  {$r->visited_at}  {$r->ip}  invalid={$r->is_invalid_traffic}  gclid=" . substr((string) $r->gclid, 0, 20) . "\n";
}
