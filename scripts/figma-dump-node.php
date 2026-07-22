<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = config('services.figma.token') ?? getenv('FIGMA_ACCESS_TOKEN');
if (!$token) {
    $mcp = json_decode(file_get_contents(__DIR__.'/../.cursor/mcp.json'), true);
    $token = $mcp['mcpServers']['figma']['env']['FIGMA_ACCESS_TOKEN'] ?? null;
}
$fileKey = 'S4xxveSTWNNpSly0NPzNLK';
$nodeId = $argv[1] ?? '1:1231';

$ch = curl_init("https://api.figma.com/v1/files/{$fileKey}/nodes?ids=".urlencode($nodeId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["X-Figma-Token: {$token}"],
]);
$resp = curl_exec($ch);
curl_close($ch);
$data = json_decode($resp, true);
$node = $data['nodes'][$nodeId]['document'] ?? null;
if (!$node) {
    echo "Node not found\n";
    print_r($data);
    exit(1);
}

function dumpNode(array $n, int $depth = 0): void {
    $pad = str_repeat('  ', $depth);
    $name = $n['name'] ?? '';
    $type = $n['type'] ?? '';
    $chars = $n['characters'] ?? '';
    $bb = $n['absoluteBoundingBox'] ?? [];
    $w = isset($bb['width']) ? round($bb['width']) : '?';
    $h = isset($bb['height']) ? round($bb['height']) : '?';
    $fill = '';
    if (!empty($n['fills'][0]['color'])) {
        $c = $n['fills'][0]['color'];
        $fill = sprintf(' fill=#%02x%02x%02x', (int)round($c['r']*255), (int)round($c['g']*255), (int)round($c['b']*255));
    }
    $line = "{$pad}{$type} | {$name} | {$w}x{$h}{$fill}";
    if ($chars !== '') {
        $line .= ' | "'.str_replace("\n", ' ', substr($chars, 0, 80)).'"';
    }
    echo $line.PHP_EOL;
    foreach ($n['children'] ?? [] as $child) {
        if (is_array($child)) dumpNode($child, $depth + 1);
    }
}

echo "Node {$nodeId}: {$node['name']}\n\n";
dumpNode($node);
