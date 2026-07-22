<?php
$j = json_decode(file_get_contents(__DIR__ . '/../storage/app/figma-admin-design.json'), true);

function walk(array $n, array &$out, int $d = 0): void
{
    if ($d > 14) {
        return;
    }
    $name = $n['name'] ?? '';
    if (preg_match('/user|status|team/i', $name)) {
        $out[] = str_repeat('  ', $d) . $name . ' | ' . ($n['id'] ?? '?') . ' | ' . ($n['type'] ?? '?');
    }
    foreach ($n['children'] ?? [] as $c) {
        walk($c, $out, $d + 1);
    }
}

$out = [];
walk($j['document'], $out);
echo implode(PHP_EOL, array_slice($out, 0, 120));
