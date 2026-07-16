<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$d = Domain::find(19);

echo "=== Google Ads metrics for domain #19 / account 6 ===\n";
$q = DB::table('google_ads_campaign_daily_metrics')->where('google_ads_account_id', 6);
echo "account rows=".$q->count()." clicks=".$q->sum('clicks')."\n";

$qd = DB::table('google_ads_campaign_daily_metrics')->where('domain_id', 19);
echo "domain_id=19 rows=".$qd->count()." clicks=".$qd->sum('clicks')."\n";

echo "\nLast 15 metric days (domain 19):\n";
foreach ((clone $qd)->orderByDesc('metric_date')->limit(15)->get() as $r) {
    echo "  {$r->metric_date} clicks={$r->clicks} imps={$r->impressions} cost={$r->cost} campaign={$r->campaign_name}\n";
}

echo "\nLast 15 metric days (account 6, any domain):\n";
foreach (DB::table('google_ads_campaign_daily_metrics')->where('google_ads_account_id', 6)->orderByDesc('metric_date')->limit(15)->get() as $r) {
    echo "  d={$r->domain_id} {$r->metric_date} clicks={$r->clicks} campaign={$r->campaign_name}\n";
}

echo "\nToday/yesterday clicks domain 19:\n";
foreach (DB::table('google_ads_campaign_daily_metrics')
    ->where('domain_id', 19)
    ->where('metric_date', '>=', now()->subDays(3)->toDateString())
    ->orderByDesc('metric_date')
    ->get() as $r) {
    echo "  {$r->metric_date} clicks={$r->clicks} campaign={$r->campaign_name}\n";
}

echo "\n=== Mappings / hosts ===\n";
foreach (DB::table('domain_google_ads_mappings')->get() as $m) {
    if ((int)$m->domain_id === 19 || (int)$m->google_ads_account_id === 6) {
        echo json_encode($m)."\n";
    }
}
foreach (DB::table('google_ads_advertised_hosts')->where('google_ads_account_id', 6)->get() as $h) {
    echo json_encode($h)."\n";
}

echo "\n=== Global tracking health ===\n";
$since = now()->subDays(7)->toDateTimeString();
echo "visits last7=".DB::table('visits')->where('visited_at', '>=', $since)->count()."\n";
echo "pm_visits last7=".DB::table('paid_marketing_visits')->where('updated_at', '>=', $since)->count()."\n";
echo "ip_logs intel ok last7=".DB::table('ip_logs')->where('intel_status','ok')->where('intel_checked_at','>=',$since)->count()."\n";
echo "ip_logs not ok=".DB::table('ip_logs')->where(function($q){$q->whereNull('intel_status')->orWhere('intel_status','!=','ok');})->count()."\n";
echo "pending jobs=".DB::table('jobs')->count()." failed=".DB::table('failed_jobs')->count()."\n";

$byDomain = DB::table('visits')
    ->select('domain_id', DB::raw('count(*) as c'), DB::raw('max(visited_at) as last'))
    ->where('visited_at', '>=', $since)
    ->groupBy('domain_id')
    ->orderByDesc('c')
    ->limit(15)
    ->get();
echo "visits by domain last7:\n";
foreach ($byDomain as $row) {
    $host = Domain::find($row->domain_id)?->hostname ?? '?';
    echo "  #{$row->domain_id} {$host}: {$row->c} last={$row->last}\n";
}

echo "\nDomains never seen (last_seen null) with ads:\n";
foreach (Domain::whereNull('last_seen_at')->whereNotNull('google_ads_account_id')->get(['id','hostname','tag_connected','ads_synced_at']) as $x) {
    echo "  #{$x->id} {$x->hostname} tag={$x->tag_connected} ads_synced={$x->ads_synced_at}\n";
}

echo "\n=== Schedule / EnrichIpIntel path ===\n";
echo "EnrichIpIntelJob is dispatched from VisitProtectionService when a visit is collected.\n";
echo "Schedule runs queue:work every minute (routes/console.php).\n";
echo "If no visits arrive, the fraud IP job never runs for that domain — by design.\n";

echo "\nDone.\n";
