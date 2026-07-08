<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\GoogleClickAttribution;
use Illuminate\Support\Facades\DB;

$domainId = 16;
$date = '2026-07-07';

$rows = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereBetween('visited_at', ["{$date} 00:00:00", "{$date} 23:59:59"])
    ->get(['url', 'gclid', 'gbraid', 'wbraid', 'google_campaign_id', 'utm_campaign', 'is_paid_traffic']);

$gadNoClick = $rows->filter(function ($r) {
    $url = (string) $r->url;
    $hasGad = str_contains($url, 'gad_') || str_contains($url, 'gclsrc');
    $paid = filled($r->gclid) || filled($r->gbraid) || filled($r->wbraid);
    return $hasGad && ! $paid;
});

echo "=== Visits with gad_* / gclsrc but NO click ID (likely missed paid) ===\n";
echo "Count: {$gadNoClick->count()}\n";
foreach ($gadNoClick->take(8) as $r) {
    echo "  URL: " . substr((string) $r->url, 0, 120) . "\n";
}

$noClick = $rows->filter(fn ($r) => ! filled($r->gclid) && ! filled($r->gbraid) && ! filled($r->wbraid));
echo "\n=== All visits WITHOUT gclid/gbraid/wbraid ({$noClick->count()}) ===\n";
foreach ($noClick->take(10) as $r) {
    $u = substr((string) $r->url, 0, 100);
    echo "  {$u}\n";
}

$paid = $rows->filter(fn ($r) => filled($r->gclid) || filled($r->gbraid) || filled($r->wbraid));
$gclidCounts = $paid->groupBy('gclid')->map->count()->sortDesc();
$multi = $gclidCounts->filter(fn ($c) => $c > 1);
echo "\n=== Same gclid multiple pageviews ===\n";
echo "Unique gclids: " . $paid->pluck('gclid')->filter()->unique()->count() . "\n";
echo "Gclids with 2+ hits: " . $multi->count() . " (extra pageviews: " . ($multi->sum() - $multi->count()) . ")\n";

echo "\n=== FHA campaign hits but Google had 0 FHA clicks Jul 7 ===\n";
$fha = $paid->where('google_campaign_id', '23965408733');
echo "FHA tag hits: {$fha->count()}, unique gclid: " . $fha->pluck('gclid')->filter()->unique()->count() . "\n";
echo "Sample FHA URLs:\n";
foreach ($fha->take(3) as $r) {
    echo "  " . substr((string) $r->url, 0, 130) . "\n";
}
