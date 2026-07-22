<?php
$j = json_decode(file_get_contents(__DIR__ . '/../storage/app/figma-admin-design.json'), true);
$target = '1:3793';

function findPath(array $n, string $id, array $path = []): ?array
{
    $path[] = ($n['name'] ?? '?') . ' (' . ($n['id'] ?? '?') . ') [' . ($n['type'] ?? '?') . ']';
    if (($n['id'] ?? '') === $id) {
        return $path;
    }
    foreach ($n['children'] ?? [] as $c) {
        $r = findPath($c, $id, $path);
        if ($r) {
            return $r;
        }
    }
    return null;
}

$path = findPath($j['document'], $target);
echo $path ? implode("\n", $path) : 'not found';

// dump siblings area - find parent
function findNode(array $n, string $id): ?array
{
    if (($n['id'] ?? '') === $id) {
        return $n;
    }
    foreach ($n['children'] ?? [] as $c) {
        $r = findNode($c, $id);
        if ($r) {
            return $r;
        }
    }
    return null;
}

function dumpTree(array $n, int $d = 0, int $max = 4): void
{
    if ($d > $max) {
        return;
    }
    $fills = '';
    if (!empty($n['fills'][0]['color'])) {
        $c = $n['fills'][0]['color'];
        $fills = sprintf(' fill=#%02x%02x%02x', (int) round($c['r'] * 255), (int) round($c['g'] * 255), (int) round($c['b'] * 255));
    }
    echo str_repeat('  ', $d) . ($n['type'] ?? '?') . ' | ' . ($n['name'] ?? '') . $fills;
    if (isset($n['characters'])) {
        echo ' "' . substr($n['characters'], 0, 40) . '"';
    }
    echo PHP_EOL;
    foreach ($n['children'] ?? [] as $c) {
        dumpTree($c, $d + 1, $max);
    }
}

$node = findNode($j['document'], $target);
if ($node) {
    echo "\n--- node tree ---\n";
    dumpTree($node, 0, 3);
}

// find parent
function findParent(array $n, string $id, ?array $parent = null): ?array
{
    if (($n['id'] ?? '') === $id) {
        return $parent;
    }
    foreach ($n['children'] ?? [] as $c) {
        $r = findParent($c, $id, $n);
        if ($r) {
            return $r;
        }
    }
    return null;
}

$parent = findParent($j['document'], $target);
if ($parent) {
    echo "\n--- parent: " . ($parent['name'] ?? '') . " ---\n";
    dumpTree($parent, 0, 2);
}
