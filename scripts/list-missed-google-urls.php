<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\GoogleClickAttribution;
use Illuminate\Support\Facades\DB;

$domainId = (int) ($argv[1] ?? 16);
$from = $argv[2] ?? '2026-07-07';
$to = $argv[3] ?? '2026-07-07';

$domain = DB::table('domains')->where('id', $domainId)->first(['id', 'hostname']);
if (! $domain) {
    echo "Domain not found\n";
    exit(1);
}

$googleClicks = (int) DB::table('google_ads_campaign_daily_metrics')
    ->where('domain_id', $domainId)
    ->whereBetween('metric_date', [$from, $to])
    ->sum('clicks');

echo "=== {$domain->hostname} (#{$domainId}) ===\n";
echo "Date range: {$from} → {$to}\n";
echo "Google clicks (stored): {$googleClicks}\n\n";

$rows = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
    ->orderBy('visited_at')
    ->get([
        'id', 'visited_at', 'ip', 'url', 'referrer',
        'gclid', 'gbraid', 'wbraid',
        'utm_source', 'utm_medium', 'utm_campaign',
        'google_campaign_id', 'is_invalid_traffic',
    ]);

$noClickId = $rows->filter(function ($r) {
    return ! filled($r->gclid) && ! filled($r->gbraid) && ! filled($r->wbraid);
});

function googleAdSignals(string $url, $row): array
{
    $signals = [];
    $q = (string) parse_url($url, PHP_URL_QUERY);
    parse_str($q, $params);

    foreach (['gad_source', 'gad_campaignid', 'gclsrc', 'gad_source', 'dclid'] as $k) {
        if (! empty($params[$k])) {
            $signals[] = "{$k}={$params[$k]}";
        }
    }
    if (str_contains(strtolower($url), 'gad_')) {
        $signals[] = 'gad_* in URL';
    }
    if (str_contains(strtolower($url), 'gclid=')) {
        $signals[] = 'gclid= in URL but column empty';
    }
    $src = strtolower((string) ($row->utm_source ?? ''));
    $med = strtolower((string) ($row->utm_medium ?? ''));
    if (in_array($src, ['google', 'adwords', 'googleads'], true) || $med === 'cpc') {
        $signals[] = "utm: {$row->utm_source}/{$row->utm_medium}";
    }
    if (filled($row->google_campaign_id)) {
        $signals[] = "stored_campaign_id={$row->google_campaign_id}";
    }
    $ref = strtolower((string) ($row->referrer ?? ''));
    if (str_contains($ref, 'google.') || str_contains($ref, 'googlesyndication')) {
        $signals[] = 'referrer=google';
    }

    return array_unique($signals);
}

$likelyGoogle = $noClickId->filter(function ($r) {
    return googleAdSignals((string) $r->url, $r) !== [];
});

$organicNoClick = $noClickId->reject(function ($r) {
    return googleAdSignals((string) $r->url, $r) !== [];
});

echo "--- LIKELY GOOGLE CLICKS WE MISSED (no gclid/gbraid/wbraid, but Google ad signals in URL/referrer) ---\n";
echo "Count: {$likelyGoogle->count()}\n\n";

foreach ($likelyGoogle as $i => $r) {
    $signals = implode(' | ', googleAdSignals((string) $r->url, $r));
    $inv = $r->is_invalid_traffic ? ' [INVALID]' : '';
    echo ($i + 1) . ". {$r->visited_at} | IP: {$r->ip}{$inv}\n";
    echo "   URL: {$r->url}\n";
    if (filled($r->referrer)) {
        echo "   Referrer: {$r->referrer}\n";
    }
    echo "   Signals: {$signals}\n\n";
}

echo "--- ALL visits WITHOUT gclid/gbraid/wbraid (organic / other) ---\n";
echo "Count: {$noClickId->count()} (likely Google missed above: {$likelyGoogle->count()}, clearly organic/other: {$organicNoClick->count()})\n\n";

foreach ($organicNoClick as $i => $r) {
    $inv = $r->is_invalid_traffic ? ' [INVALID]' : '';
    echo ($i + 1) . ". {$r->visited_at} | IP: {$r->ip}{$inv}\n";
    echo "   URL: {$r->url}\n";
    if (filled($r->referrer)) {
        echo "   Referrer: " . substr((string) $r->referrer, 0, 100) . "\n";
    }
    echo "\n";
}

echo "--- GAP ESTIMATE ---\n";
$paidUniqueGclid = $rows->filter(fn ($r) => filled($r->gclid) || filled($r->gbraid) || filled($r->wbraid))
    ->pluck('gclid')->filter()->unique()->count();
$paidUniqueIps = $rows->filter(fn ($r) => filled($r->gclid) || filled($r->gbraid) || filled($r->wbraid))
    ->pluck('ip')->filter()->unique()->count();
echo "Google clicks: {$googleClicks}\n";
echo "Unique gclid captured: {$paidUniqueGclid}\n";
echo "Unique paid IPs captured: {$paidUniqueIps}\n";
echo "Suspected missed (Google signals, no click ID): {$likelyGoogle->count()}\n";
echo "Uncaptured click estimate (Google - unique gclid): " . max(0, $googleClicks - $paidUniqueGclid) . "\n";
echo "Note: Google may also have counted clicks where our tag never fired (no row at all) — those URLs won't appear here.\n";
