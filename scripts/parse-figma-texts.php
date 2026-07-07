<?php

$j = json_decode(file_get_contents(__DIR__ . '/../storage/figma-admin-full.json'), true);

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

function texts(array $n, array &$out = []): array
{
    if (($n['type'] ?? '') === 'TEXT') {
        $t = trim($n['characters'] ?? '');
        if ($t !== '' && strlen($t) < 80) {
            $out[] = $t;
        }
    }
    foreach ($n['children'] ?? [] as $c) {
        texts($c, $out);
    }

    return $out;
}

foreach (['1:8296' => 'integration', '1:7204' => 'Automation', '1:8740' => 'Traffic'] as $id => $label) {
    $f = findById($j['document'], $id);
    $t = array_values(array_unique(texts($f)));
    echo "=== {$label} ===\n";
    echo implode("\n", array_slice($t, 0, 30));
    echo "\n\n";
}
