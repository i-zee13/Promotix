<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "visits campaign cols: ";
foreach (['campaign_name', 'utm_campaign', 'google_campaign_id', 'gclid'] as $c) {
    echo $c.'='.(Schema::hasColumn('visits', $c) ? 'Y' : 'N').' ';
}
echo PHP_EOL.PHP_EOL;

if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
    echo "=== Google Ads synced campaigns by domain ===".PHP_EOL;
    $by = DB::table('google_ads_campaign_daily_metrics as m')
        ->join('domains as d', 'd.id', '=', 'm.domain_id')
        ->selectRaw('d.id, d.hostname, COUNT(DISTINCT m.campaign_id) as camps')
        ->groupBy('d.id', 'd.hostname')
        ->orderByDesc('camps')
        ->limit(20)
        ->get();
    foreach ($by as $r) {
        echo "#{$r->id} {$r->hostname}: {$r->camps} camps".PHP_EOL;
    }
}

echo PHP_EOL."=== Visits named campaigns by domain ===".PHP_EOL;
$expr = Schema::hasColumn('visits', 'campaign_name')
    ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), ''))"
    : "NULLIF(TRIM(utm_campaign), '')";

$v = DB::table('visits')
    ->join('domains', 'domains.id', '=', 'visits.domain_id')
    ->selectRaw("domains.id, domains.hostname,
        COUNT(DISTINCT {$expr}) as named_camps,
        SUM(CASE WHEN {$expr} IS NOT NULL AND {$expr} != '' THEN 1 ELSE 0 END) as named_visits,
        SUM(CASE WHEN google_campaign_id IS NOT NULL AND google_campaign_id != '' THEN 1 ELSE 0 END) as id_visits,
        COUNT(*) as total")
    ->groupBy('domains.id', 'domains.hostname')
    ->orderByDesc('total')
    ->limit(20)
    ->get();

foreach ($v as $r) {
    echo "#{$r->id} {$r->hostname}: total={$r->total} named_camps={$r->named_camps} named_visits={$r->named_visits} id_visits={$r->id_visits}".PHP_EOL;
}
