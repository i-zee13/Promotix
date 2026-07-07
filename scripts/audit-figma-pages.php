<?php

$path = __DIR__ . '/../storage/figma-admin-full.json';
if (! is_file($path)) {
    fwrite(STDERR, "Run Figma fetch first.\n");
    exit(1);
}
$j = json_decode(file_get_contents($path), true);

$frames = [
    '1:1733' => 'admin/dashboard',
    '1:3712' => 'user',
    '1:204' => 'saas-product',
    '1:1733' => 'dashboard',
    '1:13603' => 'payments',
    '1:12418' => 'subscriptions',
    '1:4185' => 'domain-tracker',
    '1:8740' => 'traffic',
    '1:7204' => 'automation',
    '1:8296' => 'integration',
    '1:10173' => 'support',
    '1:2085' => 'analytics',
    '1:5252' => 'security',
    '1:14889' => 'settings',
    '1:18884' => 'settings-full',
];

function findById(array $n, string $id): ?array
{
    if (($n['id'] ?? '') === $id) {
        return $n;
    }
    foreach ($n['children'] ?? [] as $c) {
        $r = findById($c, $id);
        if ($r) {
            return $r;
        }
    }

    return null;
}

function texts(array $n, array &$out = [], int $depth = 0): array
{
    if (($n['type'] ?? '') === 'TEXT') {
        $t = trim(preg_replace('/\s+/', ' ', $n['characters'] ?? ''));
        if ($t !== '' && strlen($t) < 60) {
            $style = $n['style'] ?? [];
            $out[] = [
                'text' => $t,
                'size' => $style['fontSize'] ?? null,
                'weight' => $style['fontWeight'] ?? null,
                'color' => isset($n['fills'][0]['color'])
                    ? sprintf('#%02x%02x%02x', round($n['fills'][0]['color']['r'] * 255), round($n['fills'][0]['color']['g'] * 255), round($n['fills'][0]['color']['b'] * 255))
                    : null,
            ];
        }
    }
    if ($depth < 12) {
        foreach ($n['children'] ?? [] as $c) {
            texts($c, $out, $depth + 1);
        }
    }

    return $out;
}

foreach ($frames as $id => $label) {
    $f = findById($j['document'], $id);
    if (! $f) {
        echo "=== {$label} ({$id}) MISSING ===\n\n";
        continue;
    }
    $t = texts($f);
    $unique = [];
    foreach ($t as $row) {
        $key = $row['text'];
        if (! isset($unique[$key])) {
            $unique[$key] = $row;
        }
    }
    echo "=== {$label} ===\n";
    $i = 0;
    foreach ($unique as $row) {
        if ($i++ >= 35) {
            echo "... +" . (count($unique) - 35) . " more\n";
            break;
        }
        echo "- {$row['text']}\n";
    }
    echo "\n";
}
