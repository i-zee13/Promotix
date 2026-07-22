<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use Illuminate\Support\Facades\DB;

foreach (['insuranceforme.online', 'internetpowerdeals.online'] as $host) {
    $d = Domain::where('hostname', 'like', "%{$host}%")->first();
    if (!$d) {
        echo "{$host}: NOT FOUND\n\n";
        continue;
    }
    $visits7 = DB::table('visits')->where('domain_id', $d->id)->where('visited_at', '>=', now()->subDays(7))->count();
    $lastVisit = DB::table('visits')->where('domain_id', $d->id)->max('visited_at');
    echo "=== #{$d->id} {$d->hostname} ===\n";
    echo "  last_seen_at: ".($d->last_seen_at ?? 'null')."\n";
    echo "  tag_connected: ".(int)$d->tag_connected."\n";
    echo "  status: {$d->status}\n";
    echo "  domain_key: {$d->domain_key}\n";
    echo "  visits_7d: {$visits7}\n";
    echo "  last_visit: ".($lastVisit ?? 'null')."\n\n";
}
