<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Illuminate\Support\Facades\DB;

$domainId = 16;
$date = '2026-07-07';
$expr = UserTimezone::localDateSql('visited_at', null, 'UTC');

$rows = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereRaw("{$expr} = ?", [$date]);
GoogleClickAttribution::applyHasClickIdFilter($rows);

$camps = $rows
    ->select(
        'google_campaign_id',
        DB::raw('COUNT(*) as hits'),
        DB::raw('SUM(CASE WHEN is_invalid_traffic=1 THEN 1 ELSE 0 END) as invalid'),
        DB::raw('COUNT(DISTINCT ip) as unique_ips'),
        DB::raw('COUNT(DISTINCT gclid) as unique_gclid')
    )
    ->groupBy('google_campaign_id')
    ->orderByDesc('hits')
    ->get();

echo "Campaign breakdown UTC {$date}:\n";
foreach ($camps as $c) {
    $cid = $c->google_campaign_id ?: '(empty)';
    echo "  {$cid}: hits={$c->hits} invalid={$c->invalid} unique_ips={$c->unique_ips} unique_gclid={$c->unique_gclid}\n";
}
