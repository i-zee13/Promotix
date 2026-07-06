<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use App\Support\GoogleClickAttribution;
use App\Support\GoogleVerifiedPaidTraffic;
use App\Support\UserTimezone;
use Illuminate\Support\Facades\DB;

$domainId = 16;
$date = '2026-07-06';
$tz = 'UTC';

$domain = Domain::with('googleAdsAccount')->find($domainId);
$lookup = app(GoogleVerifiedPaidTraffic::class)->buildLookup(
    collect([$domainId]),
    $date,
    $date,
    null,
    $tz,
    collect([$domain]),
);

echo "=== DASHBOARD logic (visits table, per event) ===\n";
$base = DB::table('visits')->where('domain_id', $domainId);
UserTimezone::applyCalendarDateRangeFilter($base, 'visited_at', $date, $date, null, $tz);
GoogleClickAttribution::applyHasClickIdFilter($base);

$tagPaid = (clone $base)->count();
$invalid = (clone $base)->where('is_invalid_traffic', true)->count();
$rows = (clone $base)->get(['domain_id', 'url', 'google_campaign_id', 'visited_at', 'ip', 'is_invalid_traffic']);
$vc = app(GoogleVerifiedPaidTraffic::class)->countRows($rows, $lookup, $tz);

echo "Tag paid (visit rows): {$tagPaid}\n";
echo "Verified: {$vc['verified']}\n";
echo "Unverified: {$vc['unverified']}\n";
echo "Invalid (is_invalid_traffic): {$invalid}\n";
echo "Unique IPs: " . (clone $base)->distinct()->count('ip') . "\n\n";

echo "=== ADVANCED VIEW logic (paid_marketing_visits by IP) ===\n";
$pm = DB::table('paid_marketing_clicks as pc')
    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
    ->where('pv.domain_id', $domainId);
UserTimezone::applyCalendarDateRangeFilter($pm, 'pc.clicked_at', $date, $date, null, $tz);
GoogleClickAttribution::applyPaidClickIdFilter($pm, 'pc.paid_id');

echo "paid_marketing_clicks rows: " . (clone $pm)->count() . "\n";

$byIp = DB::table('paid_marketing_clicks as pc')
    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
    ->where('pv.domain_id', $domainId);
UserTimezone::applyCalendarDateRangeFilter($byIp, 'pc.clicked_at', $date, $date, null, $tz);
GoogleClickAttribution::applyPaidClickIdFilter($byIp, 'pc.paid_id');
$byIp = $byIp
    ->selectRaw('pv.ip, COUNT(*) as clicks, SUM(CASE WHEN pc.threat_group IS NOT NULL AND pc.threat_group != "" THEN 1 ELSE 0 END) as invalid_clicks')
    ->groupBy('pv.ip')
    ->orderByDesc('clicks')
    ->get();

$sumValid = 0;
$sumVisits = 0;
$sumClicks = 0;
foreach ($byIp as $r) {
    $valid = max((int) $r->clicks - (int) $r->invalid_clicks, 0);
    $sumValid += $valid;
    $sumVisits += (int) $r->clicks;
    $sumClicks += (int) $r->clicks;
    echo "  IP {$r->ip}: clicks={$r->clicks} invalid={$r->invalid_clicks} valid={$valid}\n";
}
echo "Sum VALID column (adv view): {$sumValid}\n";
echo "Unique IP rows (adv view): {$byIp->count()}\n";
echo "Total click rows: {$sumClicks}\n\n";

echo "=== Google stored clicks Jul 6 ===\n";
$g = DB::table('google_ads_campaign_daily_metrics')
    ->where('domain_id', $domainId)
    ->where('metric_date', $date)
    ->sum('clicks');
echo "Google clicks: {$g}\n";
