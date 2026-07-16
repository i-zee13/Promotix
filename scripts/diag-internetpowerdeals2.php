<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$d = Domain::find(19);
echo "Domain columns:\n";
foreach (Schema::getColumnListing('domains') as $c) {
    $v = $d->$c;
    if (is_string($v) && strlen($v) > 60) $v = substr($v, 0, 40).'...';
    if ($v instanceof \DateTimeInterface) $v = $v->format('c');
    if (is_array($v) || is_object($v)) $v = json_encode($v);
    echo "  {$c}=".($v === null ? 'null' : $v)."\n";
}

echo "\nGoogle Ads campaign daily metrics for account 6:\n";
$cols = Schema::getColumnListing('google_ads_campaign_daily_metrics');
echo "cols: ".implode(', ', $cols)."\n";

$q = DB::table('google_ads_campaign_daily_metrics');
if (in_array('google_ads_account_id', $cols, true)) {
    $q->where('google_ads_account_id', 6);
} elseif (in_array('domain_id', $cols, true)) {
    $q->where('domain_id', 19);
}

$sum = (clone $q)->sum('clicks');
$count = (clone $q)->count();
echo "rows={$count} total_clicks={$sum}\n";

$rows = (clone $q)->orderByDesc('date')->limit(20)->get();
foreach ($rows as $r) {
    $date = $r->date ?? '?';
    $clicks = $r->clicks ?? 0;
    $imps = $r->impressions ?? '?';
    $camp = $r->campaign_name ?? $r->campaign_id ?? '?';
    echo "  {$date} clicks={$clicks} imps={$imps} campaign={$camp}\n";
}

echo "\nDomain Google Ads mappings:\n";
foreach (DB::table('domain_google_ads_mappings')->where('domain_id', 19)->orWhere('google_ads_account_id', 6)->get() as $m) {
    echo "  ".json_encode($m)."\n";
}

echo "\nAdvertised hosts:\n";
foreach (DB::table('google_ads_advertised_hosts')->where('google_ads_account_id', 6)->orWhere('host', 'like', '%internetpower%')->get() as $h) {
    echo "  ".json_encode($h)."\n";
}

echo "\nCron / schedule log tail:\n";
$log = storage_path('logs/cron.log');
if (file_exists($log)) {
    $lines = file($log);
    $tail = array_slice($lines, -40);
    echo implode('', $tail);
} else {
    echo "  cron.log missing\n";
}

echo "\nQueue log tail:\n";
$qlog = storage_path('logs/queue.log');
if (file_exists($qlog)) {
    $lines = file($qlog);
    echo implode('', array_slice($lines, -30));
} else {
    echo "  queue.log missing\n";
}

echo "\nLaravel log (EnrichIp / GoogleAds / queue):\n";
$ll = storage_path('logs/laravel.log');
if (file_exists($ll)) {
    $lines = file($ll);
    $matched = [];
    foreach ($lines as $line) {
        if (preg_match('/EnrichIp|IpFraud|google-ads|queue|internetpower/i', $line)) {
            $matched[] = $line;
        }
    }
    echo implode('', array_slice($matched, -40));
    if (!$matched) echo "  (no matches)\n";
} else {
    echo "  laravel.log missing\n";
}

// Check if any visits exist for ANY domain recently - is tracking working at all?
echo "\nGlobal visit activity (last 7 days):\n";
$since = now()->subDays(7)->toDateTimeString();
echo "  visits total last7=".DB::table('visits')->where('visited_at', '>=', $since)->count()."\n";
echo "  paid_marketing_visits last7=".DB::table('paid_marketing_visits')->where('updated_at', '>=', $since)->count()."\n";
echo "  ip_logs intel ok last7=".DB::table('ip_logs')->where('intel_status', 'ok')->where('intel_checked_at', '>=', $since)->count()."\n";
echo "  ip_logs intel pending/error=".DB::table('ip_logs')->whereIn('intel_status', ['pending','error','queued'])->count()."\n";

$byDomain = DB::table('visits')
    ->select('domain_id', DB::raw('count(*) as c'), DB::raw('max(visited_at) as last'))
    ->where('visited_at', '>=', $since)
    ->groupBy('domain_id')
    ->orderByDesc('c')
    ->limit(15)
    ->get();
foreach ($byDomain as $row) {
    $host = Domain::find($row->domain_id)?->hostname ?? '?';
    echo "  domain #{$row->domain_id} {$host}: {$row->c} visits last={$row->last}\n";
}

echo "\nDone.\n";
