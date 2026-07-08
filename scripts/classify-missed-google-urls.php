<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$domainId = (int) ($argv[1] ?? 16);
$from = $argv[2] ?? '2026-07-01';
$to = $argv[3] ?? '2026-07-08';

function hasClickId($r): bool
{
    return filled($r->gclid) || filled($r->gbraid) || filled($r->wbraid);
}

function classifyUrl($r): array
{
    $url = (string) $r->url;
    $q = (string) parse_url($url, PHP_URL_QUERY);
    parse_str($q, $p);
    $ref = strtolower((string) ($r->referrer ?? ''));

    $tier = 'none';
    $reasons = [];

    // Tier A: Strong Google Ads auto-tagging without click ID (Google algorithm adds these on ad clicks)
    if (! empty($p['gad_campaignid']) || ! empty($p['gad_source'])) {
        $tier = 'A_strong';
        $reasons[] = 'gad_auto_tagging (Google Performance Max / Search ads)';
    }
    if (! empty($p['gclsrc'])) {
        $tier = 'A_strong';
        $reasons[] = 'gclsrc present';
    }
    if (! empty($p['dclid'])) {
        $tier = 'A_strong';
        $reasons[] = 'dclid (display click id)';
    }

    // Tier B: Manual UTM typical of Google Ads
    $src = strtolower((string) ($p['utm_source'] ?? $r->utm_source ?? ''));
    $med = strtolower((string) ($p['utm_medium'] ?? $r->utm_medium ?? ''));
    if (in_array($src, ['google', 'adwords', 'googleads'], true) && in_array($med, ['cpc', 'ppc', 'paid'], true)) {
        if ($tier === 'none') {
            $tier = 'B_likely';
        }
        $reasons[] = "utm {$src}/{$med}";
    }

    // Tier C: Weak signal — google referrer only (could be organic SEO)
    if ($tier === 'none' && (str_contains($ref, 'google.') || str_contains($ref, 'googlesyndication'))) {
        $tier = 'C_weak';
        $reasons[] = 'google referrer only (may be organic)';
    }

    // Tier D: Internal navigation after ad landing — params stripped
    if ($tier === 'none' && str_contains($ref, 'onpointmortgagepro.com')) {
        $tier = 'D_internal';
        $reasons[] = 'internal nav, params stripped';
    }

    return [$tier, $reasons, $p];
}

$domain = DB::table('domains')->find($domainId);
$rows = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereBetween('visited_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
    ->orderBy('visited_at')
    ->get();

$missed = $rows->filter(fn ($r) => ! hasClickId($r));

$byTier = ['A_strong' => [], 'B_likely' => [], 'C_weak' => [], 'D_internal' => [], 'none' => []];
foreach ($missed as $r) {
    [$tier, $reasons, $p] = classifyUrl($r);
    $byTier[$tier][] = ['row' => $r, 'reasons' => $reasons, 'params' => $p];
}

echo "Domain: {$domain->hostname} (#{$domainId})\n";
echo "Range: {$from} → {$to}\n";
echo "Total visits: {$rows->count()} | Without gclid/gbraid/wbraid: {$missed->count()}\n\n";

foreach (['A_strong' => 'HIGH confidence — Google ad click we missed', 'B_likely' => 'MEDIUM — UTM says Google Ads', 'C_weak' => 'LOW — Google referrer only (organic possible)', 'D_internal' => 'NOT Google click — internal page, params lost'] as $tier => $label) {
    $items = $byTier[$tier];
    echo "=== {$label} ({$tier}) — " . count($items) . " ===\n";
    foreach ($items as $i => $item) {
        $r = $item['row'];
        $inv = $r->is_invalid_traffic ? ' INVALID' : '';
        echo ($i + 1) . ". {$r->visited_at} | {$r->ip}{$inv}\n";
        echo "   URL: {$r->url}\n";
        if (filled($r->referrer)) {
            echo "   Ref: " . substr((string) $r->referrer, 0, 120) . "\n";
        }
        echo "   Why: " . implode('; ', $item['reasons']) . "\n\n";
    }
}

// Dedupe unique URLs for A+B tiers
$strongUrls = collect($byTier['A_strong'])->merge($byTier['B_likely'])
    ->map(fn ($i) => strtok((string) $i['row']->url, '#'))
    ->unique()
    ->values();
echo "=== UNIQUE URLs (high + medium confidence missed paid) ===\n";
foreach ($strongUrls as $u) {
    echo "  {$u}\n";
}
