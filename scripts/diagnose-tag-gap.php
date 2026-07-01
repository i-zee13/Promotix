<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\GoogleClickAttribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$domainId = isset($argv[1]) ? (int) $argv[1] : 0;
$from = $argv[2] ?? date('Y-m-d', strtotime('-6 days'));
$to = $argv[3] ?? date('Y-m-d');

$domains = DB::table('domains')->when($domainId > 0, fn ($q) => $q->where('id', $domainId))->get(['id', 'hostname', 'last_seen_at', 'tag_connected', 'google_ads_account_id']);

echo "=== Tag vs Google gap diagnosis ===\n";
echo "Range: {$from} → {$to}\n\n";

foreach ($domains as $d) {
    $google = Schema::hasTable('google_ads_campaign_daily_metrics')
        ? (int) DB::table('google_ads_campaign_daily_metrics')->where('domain_id', $d->id)->whereBetween('metric_date', [$from, $to])->sum('clicks')
        : 0;

    $allVisits = DB::table('visits')->where('domain_id', $d->id)->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    $allCount = (clone $allVisits)->count();

    $paidQ = DB::table('visits')->where('domain_id', $d->id)->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    GoogleClickAttribution::applyHasClickIdFilter($paidQ);
    $paidCount = $paidQ->count();

    $noGclid = DB::table('visits')
        ->where('domain_id', $d->id)
        ->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
        ->where(function ($q) {
            $q->whereNull('gclid')->orWhere('gclid', '');
        })
        ->when(Schema::hasColumn('visits', 'gbraid'), fn ($q) => $q->where(function ($g) {
            $g->whereNull('gbraid')->orWhere('gbraid', '');
        }))
        ->when(Schema::hasColumn('visits', 'wbraid'), fn ($q) => $q->where(function ($g) {
            $g->whereNull('wbraid')->orWhere('wbraid', '');
        }))
        ->count();

    $isPaidFlag = DB::table('visits')
        ->where('domain_id', $d->id)
        ->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
        ->where('is_paid_traffic', 1)
        ->count();

    $invalidPaid = DB::table('visits')->where('domain_id', $d->id)->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    GoogleClickAttribution::applyHasClickIdFilter($invalidPaid);
    $invalidPaid = (clone $invalidPaid)->where('is_invalid_traffic', 1)->count();

    $uniqueIpsPaid = DB::table('visits')->where('domain_id', $d->id)->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    GoogleClickAttribution::applyHasClickIdFilter($uniqueIpsPaid);
    $uniqueIpsPaid = $uniqueIpsPaid->distinct()->count('ip');

    $pmClicks = DB::table('paid_marketing_clicks as pc')
        ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
        ->where('pv.domain_id', $d->id)
        ->whereBetween('pc.clicked_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
        ->count();

    if ($google === 0 && $paidCount === 0 && $allCount === 0) {
        continue;
    }

    echo "#{$d->id} {$d->hostname}\n";
    echo "  last_seen_at: {$d->last_seen_at} | tag_connected: {$d->tag_connected} | google_linked: " . ($d->google_ads_account_id ? 'yes' : 'no') . "\n";
    echo "  Google clicks (API stored):     {$google}\n";
    echo "  Tag paid (gclid/gbraid/wbraid): {$paidCount}\n";
    echo "  Tag all visits:                 {$allCount}\n";
    echo "  Visits WITHOUT click ID:        {$noGclid}\n";
    echo "  is_paid_traffic flag=1:         {$isPaidFlag}\n";
    echo "  Invalid paid visits:            {$invalidPaid}\n";
    echo "  Unique IPs (paid):              {$uniqueIpsPaid}\n";
    echo "  paid_marketing_clicks rows:     {$pmClicks}\n";
    echo "  CAPTURE RATE:                   " . ($google > 0 ? round(($paidCount / $google) * 100, 1) . '%' : 'n/a') . "\n";

    // Sample visits without gclid but might be from ads (gad_* only)
    $gadOnly = DB::table('visits')
        ->where('domain_id', $d->id)
        ->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
        ->where(function ($q) {
            $q->whereNull('gclid')->orWhere('gclid', '');
        })
        ->where('url', 'like', '%gad_%')
        ->count();
    if ($gadOnly > 0) {
        echo "  ⚠ Visits with gad_* in URL but NO gclid: {$gadOnly}\n";
    }

    echo "\n";
}

echo "--- Recent visits ALL domains (last 15) ---\n";
$recent = DB::table('visits as v')
    ->join('domains as d', 'd.id', '=', 'v.domain_id')
    ->orderByDesc('v.visited_at')
    ->limit(15)
    ->get(['d.hostname', 'v.visited_at', 'v.ip', 'v.gclid', 'v.is_paid_traffic', 'v.is_invalid_traffic', 'v.url']);

foreach ($recent as $r) {
    $g = $r->gclid ? substr($r->gclid, 0, 20) . '…' : '(none)';
    $paid = $r->is_paid_traffic ? 'PAID' : 'org';
    $inv = $r->is_invalid_traffic ? ' INV' : '';
    echo "  {$r->visited_at} {$r->hostname} {$r->ip} {$paid}{$inv} gclid={$g}\n";
}
