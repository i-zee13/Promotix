<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$hostname = $argv[1] ?? 'fibreopticnet.com';
$date = $argv[2] ?? now()->toDateString();

$domain = DB::table('domains')->where('hostname', 'like', '%' . preg_replace('/^www\./', '', $hostname) . '%')->first();
if (! $domain) {
    echo "Domain not found: {$hostname}\n";
    exit(1);
}

echo "=== Domain #{$domain->id} {$domain->hostname} ===\n";
echo "last_seen_at: {$domain->last_seen_at}\n";
echo "tag_connected: {$domain->tag_connected}\n";
echo "paid_marketing_connected: {$domain->paid_marketing_connected}\n";
echo "Date: {$date}\n\n";

$paidFilter = function ($q) {
    $q->where(function ($g) {
        $g->whereNotNull('gclid')->where('gclid', '!=', '')
            ->orWhereNotNull('gbraid')->where('gbraid', '!=', '')
            ->orWhereNotNull('wbraid')->where('wbraid', '!=', '');
    });
};

$googleClicks = 0;
if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
    $googleClicks = (int) DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $domain->id)
        ->whereDate('metric_date', $date)
        ->sum('clicks');
}

$tagPaid = DB::table('visits')->where('domain_id', $domain->id)->whereDate('visited_at', $date)->where($paidFilter)->count();
$visitsAll = DB::table('visits')->where('domain_id', $domain->id)->whereDate('visited_at', $date)->count();

echo "--- {$date} ---\n";
echo "Google clicks (DB): {$googleClicks}\n";
echo "Tag paid visits: {$tagPaid}\n";
echo "Tag visits all: {$visitsAll}\n\n";

echo "--- Google by day (last 14) ---\n";
if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
    $rows = DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $domain->id)
        ->where('metric_date', '>=', now()->subDays(14)->toDateString())
        ->selectRaw('metric_date, SUM(clicks) as clicks, GROUP_CONCAT(DISTINCT campaign_name) as campaigns')
        ->groupBy('metric_date')
        ->orderByDesc('metric_date')
        ->get();
    foreach ($rows as $r) {
        echo "{$r->metric_date}: {$r->clicks} clicks | " . substr((string) $r->campaigns, 0, 80) . "\n";
    }
}

echo "\n--- Recent visits (last 15) ---\n";
$visits = DB::table('visits')->where('domain_id', $domain->id)->orderByDesc('visited_at')->limit(15)->get();
foreach ($visits as $v) {
    echo "{$v->visited_at} | ip={$v->ip} | paid={$v->is_paid_traffic} | gclid=" . substr((string) ($v->gclid ?? ''), 0, 15)
        . " | {$v->browser}/{$v->os} | " . substr((string) ($v->url ?? ''), 0, 90) . "\n";
}
if ($visits->isEmpty()) {
    echo "(no visits ever)\n";
}
