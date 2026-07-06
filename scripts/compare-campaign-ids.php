<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\CampaignAttributionResolver;
use Illuminate\Support\Facades\DB;

foreach (['23924617616', '23965408733'] as $cid) {
    echo "=== Campaign {$cid} ===\n";
    $visits = DB::table('visits')->where('url', 'like', "%gad_campaignid={$cid}%")
        ->selectRaw('DATE(visited_at) as d, COUNT(*) c')->groupBy('d')->orderBy('d')->get();
    foreach ($visits as $r) {
        $g = DB::table('google_ads_campaign_daily_metrics')->where('campaign_id', $cid)->where('metric_date', $r->d)->sum('clicks');
        echo "  {$r->d}: {$r->c} tag visits | Google clicks that day: {$g}\n";
    }
    echo "\n";
}

echo "=== Visits WITHOUT gad_campaignid (onpoint, paid) ===\n";
$noGad = DB::table('visits')->where('domain_id', 16)
    ->where(fn ($q) => $q->whereNotNull('gclid')->where('gclid', '!=', ''))
    ->get(['url', 'google_campaign_id', 'visited_at', 'ip']);
$count = 0;
foreach ($noGad as $v) {
    $gad = CampaignAttributionResolver::extractGoogleCampaignId(['url' => $v->url]);
    if ($gad === '') {
        $count++;
        if ($count <= 5) {
            echo "  {$v->visited_at} ip={$v->ip} stored_camp={$v->google_campaign_id}\n    " . substr($v->url, 0, 100) . "\n";
        }
    }
}
echo "  Total without gad_campaignid: {$count}\n";
