<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\CampaignAttributionResolver;
use App\Support\GoogleClickAttribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$domainId = isset($argv[1]) ? (int) $argv[1] : 0;
$limit = isset($argv[2]) ? (int) $argv[2] : 500;

echo "=== Paid click pattern analysis ===\n\n";

// --- 1. Overall paid visits stats ---
$paidQ = DB::table('visits as v')
    ->join('domains as d', 'd.id', '=', 'v.domain_id')
    ->when($domainId > 0, fn ($q) => $q->where('v.domain_id', $domainId));
GoogleClickAttribution::applyHasClickIdFilter($paidQ, 'v');

$totalPaid = (clone $paidQ)->count();
echo "Total paid visits (all time): {$totalPaid}\n";

if ($totalPaid === 0) {
    echo "No paid visits found.\n";
    exit(0);
}

// --- 2. Click ID type breakdown ---
$withGclid = (clone $paidQ)->whereNotNull('v.gclid')->where('v.gclid', '!=', '')->count();
$withGbraid = Schema::hasColumn('visits', 'gbraid')
    ? (clone $paidQ)->whereNotNull('v.gbraid')->where('v.gbraid', '!=', '')->count() : 0;
$withWbraid = Schema::hasColumn('visits', 'wbraid')
    ? (clone $paidQ)->whereNotNull('v.wbraid')->where('v.wbraid', '!=', '')->count() : 0;

echo "\n--- Click ID types ---\n";
echo "  gclid:  {$withGclid}\n";
echo "  gbraid: {$withGbraid}\n";
echo "  wbraid: {$withWbraid}\n";

// --- 3. gad_campaignid in URL ---
$rows = (clone $paidQ)
    ->select('v.id', 'v.domain_id', 'v.url', 'v.gclid', 'v.gbraid', 'v.wbraid', 'v.google_campaign_id', 'v.utm_campaign', 'v.ip', 'v.visited_at', 'd.hostname')
    ->orderByDesc('v.visited_at')
    ->limit($limit)
    ->get();

$hasGadInUrl = 0;
$hasStoredGoogleCampaignId = 0;
$hasUtmCampaign = 0;
$gadIds = [];
$storedIds = [];
$paths = [];
$ipCountries = [];

foreach ($rows as $r) {
    $url = (string) ($r->url ?? '');
    $gad = CampaignAttributionResolver::extractGoogleCampaignId(['url' => $url, 'gad_campaignid' => null]);
    if ($gad !== '') {
        $hasGadInUrl++;
        $gadIds[$gad] = ($gadIds[$gad] ?? 0) + 1;
    }
    $stored = trim((string) ($r->google_campaign_id ?? ''));
    if ($stored !== '') {
        $hasStoredGoogleCampaignId++;
        $storedIds[$stored] = ($storedIds[$stored] ?? 0) + 1;
    }
    $utm = trim((string) ($r->utm_campaign ?? ''));
    if ($utm !== '') {
        $hasUtmCampaign++;
    }

    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $paths[$path] = ($paths[$path] ?? 0) + 1;
}

$sampleN = $rows->count();
echo "\n--- URL keys in last {$sampleN} paid visits ---\n";
echo "  gad_campaignid in URL:     {$hasGadInUrl} (" . pct($hasGadInUrl, $sampleN) . ")\n";
echo "  google_campaign_id stored: {$hasStoredGoogleCampaignId} (" . pct($hasStoredGoogleCampaignId, $sampleN) . ")\n";
echo "  utm_campaign set:          {$hasUtmCampaign} (" . pct($hasUtmCampaign, $sampleN) . ")\n";

// --- 4. Top gad_campaignid values ---
arsort($gadIds);
echo "\n--- Top gad_campaignid in URLs ---\n";
foreach (array_slice($gadIds, 0, 15, true) as $id => $cnt) {
    $name = lookupCampaignNameAny($id);
    $match = $name ? "✓ synced: {$name}" : '✗ NOT in google_ads_campaign_daily_metrics';
    echo "  {$id}: {$cnt} visits | {$match}\n";
}

// --- 5. Match rate: gad_campaignid vs synced Google campaigns ---
echo "\n--- Campaign ID validation (all paid visits) ---\n";
$allPaid = DB::table('visits')
    ->when($domainId > 0, fn ($q) => $q->where('domain_id', $domainId))
    ->whereNotNull('url')
    ->orderByDesc('visited_at')
    ->limit(2000)
    ->get(['id', 'domain_id', 'url', 'visited_at', 'google_campaign_id']);
GoogleClickAttribution::applyHasClickIdFilter(
    DB::table('visits')->when($domainId > 0, fn ($q) => $q->where('domain_id', $domainId))
);
// Re-query properly
$allPaidRows = DB::table('visits')
    ->when($domainId > 0, fn ($q) => $q->where('domain_id', $domainId))
    ->where(function ($q) {
        $q->where(function ($g) {
            $g->whereNotNull('gclid')->where('gclid', '!=', '');
        })->orWhere(function ($g) {
            if (Schema::hasColumn('visits', 'gbraid')) {
                $g->whereNotNull('gbraid')->where('gbraid', '!=', '');
            }
        })->orWhere(function ($g) {
            if (Schema::hasColumn('visits', 'wbraid')) {
                $g->whereNotNull('wbraid')->where('wbraid', '!=', '');
            }
        });
    })
    ->get(['id', 'domain_id', 'url', 'visited_at', 'google_campaign_id', 'ip']);

$matchedSynced = 0;
$matchedActiveDay = 0;
$noGad = 0;
$gadUnknown = 0;
$total = $allPaidRows->count();

foreach ($allPaidRows as $v) {
    $gad = CampaignAttributionResolver::extractGoogleCampaignId(['url' => (string) $v->url]);
    if ($gad === '') {
        $noGad++;
        continue;
    }

    $exists = DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $v->domain_id)
        ->where('campaign_id', $gad)
        ->exists();

    if ($exists) {
        $matchedSynced++;
        $day = substr((string) $v->visited_at, 0, 10);
        $active = DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $v->domain_id)
            ->where('campaign_id', $gad)
            ->where('metric_date', $day)
            ->where('clicks', '>', 0)
            ->exists();
        if ($active) {
            $matchedActiveDay++;
        }
    } else {
        $gadUnknown++;
    }
}

echo "  Analyzed: {$total} paid visits\n";
echo "  No gad_campaignid in URL:           {$noGad} (" . pct($noGad, $total) . ")\n";
echo "  gad_campaignid matches synced camp: {$matchedSynced} (" . pct($matchedSynced, $total) . ")\n";
echo "  + had Google clicks that same day:  {$matchedActiveDay} (" . pct($matchedActiveDay, $total) . ")\n";
echo "  gad_campaignid UNKNOWN (stale?):    {$gadUnknown} (" . pct($gadUnknown, $total) . ")\n";

// --- 6. Path patterns ---
arsort($paths);
echo "\n--- Top landing paths (paid visits sample) ---\n";
foreach (array_slice($paths, 0, 10, true) as $path => $cnt) {
    echo "  {$path}: {$cnt}\n";
}

// --- 7. IP patterns ---
echo "\n--- IP patterns (sample) ---\n";
$ips = (clone $paidQ)
    ->selectRaw('COUNT(DISTINCT v.ip) as unique_ips, COUNT(*) as total')
    ->first();
echo "  Unique IPs (all paid): {$ips->unique_ips} / {$ips->total} visits\n";

$v4 = (clone $paidQ)->where('v.ip', 'not like', '%:%')->count();
$v6 = (clone $paidQ)->where('v.ip', 'like', '%:%')->count();
echo "  IPv4: {$v4} | IPv6: {$v6}\n";

$topIps = DB::table('visits as v')
    ->when($domainId > 0, fn ($q) => $q->where('v.domain_id', $domainId))
    ->where(function ($q) {
        $q->whereNotNull('gclid')->where('gclid', '!=', '');
    })
    ->orWhere(function ($q) use ($domainId) {
        if ($domainId > 0) {
            $q->where('domain_id', $domainId);
        }
        if (Schema::hasColumn('visits', 'wbraid')) {
            $q->whereNotNull('wbraid')->where('wbraid', '!=', '');
        }
    })
    ->selectRaw('ip, COUNT(*) as hits')
    ->groupBy('ip')
    ->orderByDesc('hits')
    ->limit(8)
    ->get();

// Fix query - simpler
$topIps = DB::table('visits')
    ->when($domainId > 0, fn ($q) => $q->where('domain_id', $domainId))
    ->where(function ($q) {
        $q->where(fn ($g) => $g->whereNotNull('gclid')->where('gclid', '!=', ''));
        if (Schema::hasColumn('visits', 'wbraid')) {
            $q->orWhere(fn ($g) => $g->whereNotNull('wbraid')->where('wbraid', '!=', ''));
        }
    })
    ->selectRaw('ip, COUNT(*) as hits, MAX(country) as country')
    ->groupBy('ip')
    ->orderByDesc('hits')
    ->limit(8)
    ->get();

echo "  Top repeat IPs:\n";
foreach ($topIps as $ip) {
    echo "    {$ip->ip} ({$ip->country}) — {$ip->hits} paid hits\n";
}

// --- 8. Sample URLs ---
echo "\n--- Sample paid URLs (5 recent) ---\n";
foreach ($rows->take(5) as $r) {
    $gad = CampaignAttributionResolver::extractGoogleCampaignId(['url' => (string) $r->url]);
    $clickType = filled($r->gclid) ? 'gclid' : (filled($r->wbraid ?? '') ? 'wbraid' : 'gbraid');
    $urlShort = strlen($r->url) > 120 ? substr($r->url, 0, 120) . '…' : $r->url;
    echo "  [{$r->hostname}] {$r->visited_at}\n";
    echo "    IP: {$r->ip} | {$clickType} | gad_campaignid={$gad}\n";
    echo "    URL: {$urlShort}\n\n";
}

// --- 9. Per-domain summary ---
if ($domainId === 0) {
    echo "--- Per-domain paid visit + validation rate ---\n";
    $domains = DB::table('domains')
        ->whereIn('id', function ($q) {
            $q->select('domain_id')->from('visits')->whereNotNull('gclid')->where('gclid', '!=', '');
        })
        ->get(['id', 'hostname']);

    foreach ($domains as $d) {
        $paid = DB::table('visits')->where('domain_id', $d->id)
            ->where(fn ($q) => $q->whereNotNull('gclid')->where('gclid', '!=', ''))
            ->count();
        if ($paid === 0) {
            continue;
        }
        $withGad = 0;
        $validated = 0;
        $visits = DB::table('visits')->where('domain_id', $d->id)
            ->where(fn ($q) => $q->whereNotNull('gclid')->where('gclid', '!=', ''))
            ->limit(500)
            ->get(['url', 'visited_at']);
        foreach ($visits as $v) {
            $gad = CampaignAttributionResolver::extractGoogleCampaignId(['url' => (string) $v->url]);
            if ($gad === '') {
                continue;
            }
            $withGad++;
            $day = substr((string) $v->visited_at, 0, 10);
            if (DB::table('google_ads_campaign_daily_metrics')
                ->where('domain_id', $d->id)
                ->where('campaign_id', $gad)
                ->where('metric_date', $day)
                ->where('clicks', '>', 0)
                ->exists()) {
                $validated++;
            }
        }
        $rate = $withGad > 0 ? round(($validated / $withGad) * 100, 1) : 0;
        echo "  #{$d->id} {$d->hostname}: {$paid} paid | gad+active-day match: {$validated}/{$withGad} ({$rate}%)\n";
    }
}

function pct(int $n, int $total): string
{
    return $total > 0 ? round(($n / $total) * 100, 1) . '%' : '0%';
}

function lookupCampaignNameAny(string $campaignId): ?string
{
    $name = DB::table('google_ads_campaign_daily_metrics')
        ->where('campaign_id', $campaignId)
        ->orderByDesc('metric_date')
        ->value('campaign_name');

    return filled($name) ? (string) $name : null;
}
