<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$d = \App\Models\Domain::find(10);
if ($d) {
    echo "hostname: {$d->hostname}\n";
    echo "domain_key: {$d->domain_key}\n";
    echo "status: {$d->status}\n";
    echo "tag_connected: {$d->tag_connected}\n";
    echo "last_seen_at: {$d->last_seen_at}\n";
    echo "expected tag: " . url('/tag/' . $d->domain_key . '.js') . "\n";
}
