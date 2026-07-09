<?php

$j = json_decode(file_get_contents(__DIR__ . '/../storage/figma-admin-full.json'), true);

function walk(array $n, bool $inSaas = false): void
{
    $name = $n['name'] ?? '';
    if ($name === 'Saas Product') {
        $inSaas = true;
    }

    if ($inSaas) {
        $type = $n['type'] ?? '';
        $bb = $n['absoluteBoundingBox'] ?? null;
        $chars = $n['characters'] ?? '';
        $fills = $n['fills'] ?? [];
        $color = '';
        if (! empty($fills[0]['color'])) {
            $c = $fills[0]['color'];
            $color = sprintf('#%02x%02x%02x', (int) round($c['r'] * 255), (int) round($c['g'] * 255), (int) round($c['b'] * 255));
        }

        if ($chars !== '' || in_array($type, ['RECTANGLE', 'FRAME', 'INSTANCE', 'VECTOR', 'ELLIPSE'], true)) {
            $line = $type . ' | ' . substr($name, 0, 60);
            if ($chars !== '') {
                $line .= ' | ' . str_replace(["\n", "\r"], ' ', substr($chars, 0, 100));
            }
            if ($bb) {
                $line .= ' | ' . round($bb['width']) . 'x' . round($bb['height']);
            }
            if ($color !== '') {
                $line .= ' | ' . $color;
            }
            echo $line . PHP_EOL;
        }
    }

    foreach ($n['children'] ?? [] as $child) {
        if (is_array($child)) {
            walk($child, $inSaas);
        }
    }
}

walk($j['document']);
