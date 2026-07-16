<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use App\Models\IpLog;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "DB: ".config('database.connections.mysql.database')." @ ".config('database.connections.mysql.host').PHP_EOL;
echo "QUEUE: ".config('queue.default').PHP_EOL.PHP_EOL;

foreach (['paid_marketing_clicks', 'paid_marketing_visits', 'visits', 'page_views', 'ip_logs', 'jobs', 'failed_jobs', 'google_ads_campaign_metrics', 'google_ads_metrics', 'google_ads_daily_metrics'] as $t) {
    if (Schema::hasTable($t)) {
        echo "TABLE {$t}: ".implode(', ', Schema::getColumnListing($t)).PHP_EOL.PHP_EOL;
    } else {
        echo "TABLE {$t}: MISSING\n\n";
    }
}

$d = Domain::where('hostname', 'like', '%internetpowerdeals%')->first();
echo "Domain #{$d->id} {$d->hostname}\n";
echo "  tag=".(int)$d->tag_connected." paid=".(int)$d->paid_marketing_connected." bot=".(int)$d->bot_mitigation_connected." gads=".$d->google_ads_account_id." last_seen=".($d->last_seen_at ?? 'null')."\n\n";

$pmVisits = PaidMarketingVisit::where('domain_id', $d->id)->count();
echo "paid_marketing_visits={$pmVisits}\n";

$pmClicks = PaidMarketingClick::query()
    ->whereHas('visit', fn ($q) => $q->where('domain_id', $d->id))
    ->count();
echo "paid_marketing_clicks (via visit)={$pmClicks}\n";

$visits = Schema::hasTable('visits') ? DB::table('visits')->where('domain_id', $d->id)->count() : -1;
echo "visits={$visits}\n";

$pageViews = Schema::hasTable('page_views') ? DB::table('page_views')->where('domain_id', $d->id)->count() : -1;
echo "page_views={$pageViews}\n\n";

echo "Recent paid_marketing_visits:\n";
foreach (PaidMarketingVisit::where('domain_id', $d->id)->orderByDesc('id')->limit(10)->get() as $r) {
    echo "  #{$r->id} ip={$r->ip} threat=".($r->threat_group ?? '-')." at=".($r->visited_at ?? $r->created_at)." gclid=".substr((string)($r->gclid ?? ''),0,24)."\n";
}
if ($pmVisits === 0) echo "  (none)\n";

echo "\nRecent visits table:\n";
if (Schema::hasTable('visits')) {
    $rows = DB::table('visits')->where('domain_id', $d->id)->orderByDesc('id')->limit(10)->get();
    if ($rows->isEmpty()) echo "  (none)\n";
    foreach ($rows as $r) {
        $paid = ($r->is_paid_traffic ?? false) ? 'paid' : 'organic';
        echo "  #{$r->id} ip={$r->ip} {$paid} at={$r->visited_at} gclid=".substr((string)($r->gclid ?? ''),0,24)." threat=".($r->threat_group ?? '-')."\n";
    }
}

// Google metrics tables discovery
echo "\nGoogle-related tables:\n";
foreach (DB::select('SHOW TABLES') as $row) {
    $name = array_values((array)$row)[0];
    if (stripos($name, 'google') !== false || stripos($name, 'metric') !== false || stripos($name, 'campaign') !== false) {
        echo "  {$name}\n";
    }
}

echo "\nQueue:\n";
if (Schema::hasTable('jobs')) {
    echo "  pending=".DB::table('jobs')->count()."\n";
    foreach (DB::table('jobs')->orderByDesc('id')->limit(8)->get() as $j) {
        $p = json_decode($j->payload, true);
        echo "  #{$j->id} ".($p['displayName'] ?? '?')." attempts={$j->attempts} created=".date('Y-m-d H:i:s', $j->created_at)."\n";
    }
}
if (Schema::hasTable('failed_jobs')) {
    echo "  failed=".DB::table('failed_jobs')->count()."\n";
    foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(8)->get() as $j) {
        $p = json_decode($j->payload, true);
        $ex = substr(str_replace("\n", ' ', (string)$j->exception), 0, 160);
        echo "  #{$j->id} ".($p['displayName'] ?? '?')." at={$j->failed_at} {$ex}\n";
    }
}

// EnrichIpIntel specifically in failed
echo "\nEnrichIpIntelJob in failed_jobs:\n";
if (Schema::hasTable('failed_jobs')) {
    $n = 0;
    foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(100)->get() as $j) {
        if (str_contains($j->payload, 'EnrichIpIntelJob')) {
            $n++;
            $ex = substr(str_replace("\n", ' ', (string)$j->exception), 0, 200);
            echo "  #{$j->id} at={$j->failed_at} {$ex}\n";
            if ($n >= 5) break;
        }
    }
    if ($n === 0) echo "  (none in last 100 failed)\n";
}

// Domain plugin keys / tracking
echo "\nDomain keys / tracking:\n";
$cols = Schema::getColumnListing('domains');
foreach (['public_key','plugin_key','tracking_key','site_key','api_key','script_key'] as $c) {
    if (in_array($c, $cols, true)) {
        echo "  {$c}=".substr((string)$d->$c, 0, 40)."\n";
    }
}

// Detection
$settings = DB::table('domain_detection_settings')->where('domain_id', $d->id)->first();
echo "\nDetection settings: ".($settings ? 'FOUND' : 'MISSING')."\n";
if ($settings) {
    foreach (['control_mode','invalid_bot_action','suspicious_enabled','out_of_geo_enabled','google_geo_block_enabled','block_list_enabled','detection_profile'] as $k) {
        if (property_exists($settings, $k)) echo "  {$k}=".json_encode($settings->$k)."\n";
    }
}

// Google ads account
$acc = DB::table('google_ads_accounts')->where('id', $d->google_ads_account_id)->first();
echo "\nGoogle Ads account:\n";
if ($acc) {
    foreach ((array)$acc as $k => $v) {
        if (in_array($k, ['id','customer_id','descriptive_name','name','status','refresh_token','access_token','user_id','time_zone','currency_code','last_synced_at','created_at'], true) || str_contains($k, 'sync') || str_contains($k, 'token')) {
            $show = is_string($v) && strlen($v) > 40 ? substr($v, 0, 20).'...' : $v;
            if (str_contains($k, 'token')) $show = $v ? '[set]' : '[empty]';
            echo "  {$k}={$show}\n";
        }
    }
} else {
    echo "  (account missing)\n";
}

echo "\nDone.\n";
