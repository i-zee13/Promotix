<?php

$path = __DIR__ . '/../storage/figma-admin-full.json';
$j = json_decode(file_get_contents($path), true);

$colors = [];
$fonts = [];
$textStyles = [];

function rgba(array $c): string
{
    $r = round(($c['r'] ?? 0) * 255);
    $g = round(($c['g'] ?? 0) * 255);
    $b = round(($c['b'] ?? 0) * 255);
    $a = $c['a'] ?? 1;

    return $a < 1
        ? sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $a)
        : sprintf('#%02x%02x%02x', $r, $g, $b);
}

function walkTokens(array $n, array &$colors, array &$fonts, array &$textStyles): void
{
    if (($n['type'] ?? '') === 'TEXT') {
        $style = $n['style'] ?? [];
        $font = ($style['fontFamily'] ?? '?') . '|' . ($style['fontWeight'] ?? '?') . '|' . ($style['fontSize'] ?? '?');
        $fonts[$font] = ($fonts[$font] ?? 0) + 1;

        $chars = $n['characters'] ?? '';
        if (strlen($chars) < 80) {
            $key = trim(preg_replace('/\s+/', ' ', $chars));
            if ($key !== '') {
                $textStyles[$key] = [
                    'font' => $style['fontFamily'] ?? null,
                    'weight' => $style['fontWeight'] ?? null,
                    'size' => $style['fontSize'] ?? null,
                    'line' => $style['lineHeightPx'] ?? null,
                    'color' => isset($n['fills'][0]['color']) ? rgba($n['fills'][0]['color']) : null,
                ];
            }
        }
    }

    foreach ($n['fills'] ?? [] as $fill) {
        if (($fill['type'] ?? '') === 'SOLID' && isset($fill['color'])) {
            $hex = rgba($fill['color']);
            $colors[$hex] = ($colors[$hex] ?? 0) + 1;
        }
    }

    foreach ($n['children'] ?? [] as $c) {
        walkTokens($c, $colors, $fonts, $textStyles);
    }
}

foreach ($j['document']['children'] ?? [] as $page) {
    walkTokens($page, $colors, $fonts, $textStyles);
}

arsort($colors);
arsort($fonts);

echo "=== TOP COLORS ===\n";
$i = 0;
foreach ($colors as $hex => $count) {
    if ($i++ >= 25) {
        break;
    }
    echo "$hex ($count)\n";
}

echo "\n=== TOP FONTS ===\n";
$i = 0;
foreach ($fonts as $font => $count) {
    if ($i++ >= 15) {
        break;
    }
    echo "$font ($count)\n";
}

echo "\n=== KEY TEXT LABELS ===\n";
$keys = ['Overview', 'Dashboard', 'Advanced View', 'Domains', 'Billing', 'Analytics', 'Users', 'Paid', 'Bot', 'Integrations', 'Detection'];
foreach ($textStyles as $text => $style) {
    foreach ($keys as $k) {
        if (stripos($text, $k) !== false) {
            echo json_encode(['text' => $text, 'style' => $style], JSON_UNESCAPED_UNICODE) . "\n";
            break;
        }
    }
}

echo "\n=== FIGMA STYLES ===\n";
foreach ($j['styles'] ?? [] as $id => $s) {
    echo "{$s['name']} ({$s['styleType']})\n";
}
