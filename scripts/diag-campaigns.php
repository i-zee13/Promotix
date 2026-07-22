<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$d = Domain::where('hostname', 'like', '%internetpowerdeals%')->first();
if (! $d) {
    echo "Domain not found\n";
    exit(1);
}

echo "Domain #{$d->id} {$d->hostname}\n";
echo "google_ads_account_id=".($d->google_ads_account_id ?? 'null')."\n\n";

echo "=== Distinct campaigns in google_ads_campaign_daily_metrics (domain_id={$d->id}) ===\n";
$rows = DB::table('google_ads_campaign_daily_metrics')
    ->where('domain_id', $d->id)
    ->select('campaign_id', 'campaign_name', DB::raw('SUM(clicks) as clicks'), DB::raw('MIN(metric_date) as first_day'), DB::raw('MAX(metric_date) as last_day'), DB::raw('COUNT(*) as days'))
    ->groupBy('campaign_id', 'campaign_name')
    ->orderByDesc('clicks')
    ->get();

if ($rows->isEmpty()) {
    echo "  (none for domain_id)\n";
} else {
    foreach ($rows as $r) {
        echo "  campaign_id={$r->campaign_id}  name={$r->campaign_name}  clicks={$r->clicks}  {$r->first_day} → {$r->last_day} ({$r->days} days)\n";
    }
}

echo "\n=== Same account campaigns (account_id={$d->google_ads_account_id}) ===\n";
$acc = DB::table('google_ads_campaign_daily_metrics')
    ->where('google_ads_account_id', $d->google_ads_account_id)
    ->select('domain_id', 'campaign_id', 'campaign_name', DB::raw('SUM(clicks) as clicks'))
    ->groupBy('domain_id', 'campaign_id', 'campaign_name')
    ->orderByDesc('clicks')
    ->get();
foreach ($acc as $r) {
    echo "  domain={$r->domain_id} campaign_id={$r->campaign_id} name={$r->campaign_name} clicks={$r->clicks}\n";
}

echo "\n=== paid_marketing_visits / clicks campaign fields for this domain ===\n";
$pm = DB::table('paid_marketing_visits')->where('domain_id', $d->id)
    ->select('google_campaign_id', 'campaign', 'campaign_name', DB::raw('COUNT(*) as n'))
    ->groupBy('google_campaign_id', 'campaign', 'campaign_name')
    ->get();
if ($pm->isEmpty()) {
    echo "  (no paid_marketing_visits)\n";
} else {
    foreach ($pm as $r) {
        echo "  google_campaign_id={$r->google_campaign_id} campaign={$r->campaign} name={$r->campaign_name} n={$r->n}\n";
    }
}

$visits = DB::table('visits')->where('domain_id', $d->id)
    ->select('google_campaign_id', 'campaign_name', 'utm_campaign', DB::raw('COUNT(*) as n'))
    ->groupBy('google_campaign_id', 'campaign_name', 'utm_campaign')
    ->get();
echo "\n=== visits table campaign fields ===\n";
if ($visits->isEmpty()) {
    echo "  (no visits)\n";
} else {
    foreach ($visits as $r) {
        echo "  google_campaign_id={$r->google_campaign_id} campaign_name={$r->campaign_name} utm={$r->utm_campaign} n={$r->n}\n";
    }
}

// Any other campaign tables?
echo "\n=== Other campaign-related tables ===\n";
foreach (DB::select('SHOW TABLES') as $row) {
    $name = array_values((array) $row)[0];
    if (stripos($name, 'campaign') !== false) {
        echo "  {$name}\n";
        if (Schema::hasColumn($name, 'domain_id') || Schema::hasColumn($name, 'campaign_id')) {
            $q = DB::table($name);
            if (Schema::hasColumn($name, 'domain_id')) {
                $q->where('domain_id', $d->id);
            } elseif (Schema::hasColumn($name, 'google_ads_account_id')) {
                $q->where('google_ads_account_id', $d->google_ads_account_id);
            }
            echo "    matching rows=".$q->count()."\n";
        }
    }
}

echo "\nDone.\n";
