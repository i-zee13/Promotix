<?php

$path = __DIR__ . '/../storage/figma-admin-full.json';
$j = json_decode(file_get_contents($path), true);

$targets = [
    'admin',
    'Analytics',
    'user',
    'payments',
    'Subscriptions',
    'Saas Product',
    'integration',
    'Domain tracker',
    'Traffic & Bot Logs',
    'Support System',
    'Automation',
    'Security & Logs',
    'System settings',
    'price ',
];

function findFrames(array $n, array $targets, int $depth = 0, array &$found = []): array
{
    $name = trim($n['name'] ?? '');
    $type = $n['type'] ?? '';

    if ($type === 'FRAME' && $depth === 1 && in_array($name, $targets, true)) {
        $found[] = [
            'id' => $n['id'],
            'name' => $name,
            'bb' => $n['absoluteBoundingBox'] ?? null,
            'bg' => $n['backgroundColor'] ?? null,
            'childCount' => count($n['children'] ?? []),
        ];
    }

    foreach ($n['children'] ?? [] as $c) {
        findFrames($c, $targets, $depth + 1, $found);
    }

    return $found;
}

$found = [];
foreach ($j['document']['children'] ?? [] as $page) {
    findFrames($page, $targets, 0, $found);
}

echo "=== TARGET FRAMES ===\n";
foreach ($found as $f) {
    $bb = $f['bb'];
    $bg = $f['bg'] ?? null;
    $bgHex = $bg ? sprintf('#%02x%02x%02x', round($bg['r']*255), round($bg['g']*255), round($bg['b']*255)) : 'none';
    echo "{$f['name']} | id={$f['id']} | {$bb['width']}x{$bb['height']} | bg={$bgHex} | children={$f['childCount']}\n";
}

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

// Extract direct children of first 'admin' frame
$adminId = null;
foreach ($found as $f) {
    if ($f['name'] === 'admin' && !$adminId) {
        $adminId = $f['id'];
        break;
    }
}

if ($adminId) {
    $admin = findById($j['document'], $adminId);
    echo "\n=== ADMIN FRAME CHILDREN ===\n";
    foreach ($admin['children'] ?? [] as $child) {
        $bb = $child['absoluteBoundingBox'] ?? [];
        $name = $child['name'] ?? '';
        $type = $child['type'] ?? '';
        echo "{$type} | {$name} | " . round($bb['x'] ?? 0) . ',' . round($bb['y'] ?? 0) . ' ' . round($bb['width'] ?? 0) . 'x' . round($bb['height'] ?? 0) . "\n";
    }
}
