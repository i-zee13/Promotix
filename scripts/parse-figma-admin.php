<?php

$path = __DIR__ . '/../storage/figma-admin-full.json';
$j = json_decode(file_get_contents($path), true);

function walk(array $n, int $d = 0, array &$out = []): array
{
    $t = $n['type'] ?? '';
    $name = $n['name'] ?? '';

    if (in_array($t, ['FRAME', 'COMPONENT', 'COMPONENT_SET', 'SECTION', 'CANVAS'], true) && $d <= 4) {
        $bb = $n['absoluteBoundingBox'] ?? null;
        $out[] = [
            'depth' => $d,
            'type' => $t,
            'name' => $name,
            'id' => $n['id'] ?? '',
            'w' => $bb['width'] ?? null,
            'h' => $bb['height'] ?? null,
        ];
    }

    foreach ($n['children'] ?? [] as $c) {
        walk($c, $d + 1, $out);
    }

    return $out;
}

$out = [];
foreach ($j['document']['children'] ?? [] as $page) {
    walk($page, 0, $out);
}

echo "=== PAGES / FRAMES (depth <= 4) ===\n";
foreach ($out as $r) {
    $indent = str_repeat('  ', $r['depth']);
    $size = ($r['w'] && $r['h']) ? sprintf(' (%.0fx%.0f)', $r['w'], $r['h']) : '';
    echo "{$indent}[{$r['type']}] {$r['name']}{$size}\n";
}

echo "\n=== STYLES ===\n";
foreach ($j['styles'] ?? [] as $id => $s) {
    echo "{$id} | {$s['name']} | {$s['styleType']}\n";
}

echo "\n=== COMPONENTS (first 40) ===\n";
$i = 0;
foreach ($j['components'] ?? [] as $id => $c) {
    if ($i++ >= 40) {
        break;
    }
    echo "{$c['name']} | {$id}\n";
}

echo "\nTotal components: " . count($j['components'] ?? []) . "\n";
