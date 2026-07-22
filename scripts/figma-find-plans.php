<?php
$raw = file_get_contents(__DIR__.'/../storage/app/figma-admin-design.json');
$j = json_decode($raw, true);
if (!$j) { echo "JSON parse fail\n"; exit(1); }

function walk(array $n, array &$out): void {
    $name = $n['name'] ?? '';
    $type = $n['type'] ?? '';
    $id = $n['id'] ?? '';
    $lower = strtolower($name);
    if (
        str_contains($lower, 'plan') ||
        str_contains($lower, 'pricing') ||
        str_contains($lower, 'price')
    ) {
        $out[] = [
            'name' => $name,
            'type' => $type,
            'id' => $id,
            'w' => $n['absoluteBoundingBox']['width'] ?? null,
            'h' => $n['absoluteBoundingBox']['height'] ?? null,
        ];
    }
    foreach ($n['children'] ?? [] as $c) {
        if (is_array($c)) walk($c, $out);
    }
}

$out = [];
walk($j['document'] ?? [], $out);
foreach ($out as $r) {
    echo sprintf("%s | %s | %s | %sx%s\n", $r['type'], $r['name'], $r['id'], $r['w'] ?? '?', $r['h'] ?? '?');
}
