<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use App\Services\GoogleAdsDomainMetricsSync;
use App\Support\GoogleClickAttribution;
use App\Support\GoogleVerifiedPaidTraffic;
use App\Support\UserTimezone;
use Illuminate\Support\Facades\DB;

$domainId = 16;
$date = '2026-07-07';

echo "=== onpointmortgagepro.com (#{$domainId}) comparison for {$date} ===\n\n";

$metrics = DB::table('google_ads_campaign_daily_metrics')
    ->where('domain_id', $domainId)
    ->whereBetween('metric_date', ['2026-07-05', '2026-07-08'])
    ->orderBy('metric_date')
    ->get();

echo "Google Ads stored metrics (after live sync):\n";
foreach ($metrics as $m) {
    echo "  {$m->metric_date} | camp {$m->campaign_id} | clicks {$m->clicks} | {$m->campaign_name}\n";
}

foreach (['2026-07-06', '2026-07-07', '2026-07-08'] as $d) {
    $sum = $metrics->where('metric_date', $d)->sum('clicks');
    echo "  SUM Google clicks on {$d}: {$sum}\n";
}
echo "\n";

foreach (['UTC', 'America/Los_Angeles', 'America/New_York', 'Asia/Karachi'] as $tz) {
    $dayExpr = UserTimezone::localDateSql('visited_at', null, $tz);
    $paidQ = DB::table('visits')
        ->where('domain_id', $domainId)
        ->whereRaw("{$dayExpr} = ?", [$date]);
    GoogleClickAttribution::applyHasClickIdFilter($paidQ);
    $withClick = (clone $paidQ)->count();
    $invalidPaid = (clone $paidQ)->where('is_invalid_traffic', 1)->count();
    $validPaid = $withClick - $invalidPaid;
    $uniqueIps = (clone $paidQ)->distinct()->count('ip');
    $uniqueGclid = (clone $paidQ)->distinct()->count('gclid');

    $organic = DB::table('visits')
        ->where('domain_id', $domainId)
        ->whereRaw("{$dayExpr} = ?", [$date]);
    GoogleClickAttribution::excludeClickIds($organic);
    $organicAll = (clone $organic)->count();
    $organicInvalid = (clone $organic)->where('is_invalid_traffic', 1)->count();

    echo "TZ {$tz}:\n";
    echo "  Paid (gclid/gbraid/wbraid): {$withClick} | valid: {$validPaid} | invalid: {$invalidPaid}\n";
    echo "  Unique paid IPs: {$uniqueIps} | unique gclid values: {$uniqueGclid}\n";
    echo "  Bot protection funnel (no click id): {$organicAll} visits | invalid: {$organicInvalid}\n\n";
}

echo "Paid invalid threat groups (UTC day {$date}):\n";
$groups = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereRaw(UserTimezone::localDateSql('visited_at', null, 'UTC') . ' = ?', [$date])
    ->where('is_invalid_traffic', 1);
GoogleClickAttribution::applyHasClickIdFilter($groups);
$groups = $groups->select('threat_group', DB::raw('count(*) as c'))->groupBy('threat_group')->orderByDesc('c')->get();
foreach ($groups as $g) {
    echo "  " . ($g->threat_group ?: '(null)') . ": {$g->c}\n";
}

echo "\nCross-check: paid visits with is_invalid=1 that have NO click id (should be 0):\n";
$leak = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereRaw(UserTimezone::localDateSql('visited_at', null, 'UTC') . ' = ?', [$date])
    ->where('is_invalid_traffic', 1);
GoogleClickAttribution::excludeClickIds($leak);
echo "  Organic invalid (bot protection): " . $leak->count() . "\n";

$leakPaid = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereRaw(UserTimezone::localDateSql('visited_at', null, 'UTC') . ' = ?', [$date])
    ->where('is_invalid_traffic', 1);
GoogleClickAttribution::applyHasClickIdFilter($leakPaid);
echo "  Paid invalid: " . $leakPaid->count() . "\n";

echo "\nLA timezone day {$date} vs Google metric_date {$date}:\n";
$laExpr = UserTimezone::localDateSql('visited_at', null, 'America/Los_Angeles');
$laPaid = DB::table('visits')->where('domain_id', $domainId)->whereRaw("{$laExpr} = ?", [$date]);
GoogleClickAttribution::applyHasClickIdFilter($laPaid);
echo "  Tag paid visits (LA day): " . (clone $laPaid)->count() . "\n";
echo "  Tag valid paid (LA day): " . (clone $laPaid)->where('is_invalid_traffic', 0)->count() . "\n";
echo "  Google clicks metric_date {$date}: " . $metrics->where('metric_date', $date)->sum('clicks') . "\n";

// Dashboard-equivalent numbers (GoogleVerifiedPaidTraffic)
$domain = Domain::with('googleAdsAccount')->find($domainId);
$user = $domain?->user;
$reportingTz = UserTimezone::reportingTimezoneForUser($user, $domain?->googleAdsAccount?->time_zone);
$fromDate = $toDate = $date;

echo "\n=== Dashboard simulation (single day {$date}, reporting TZ: {$reportingTz}) ===\n";

$dayExpr = UserTimezone::localDateSql('visited_at', null, $reportingTz);
$base = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereRaw("{$dayExpr} >= ?", [$fromDate])
    ->whereRaw("{$dayExpr} <= ?", [$toDate]);
GoogleClickAttribution::applyHasClickIdFilter($base);

$tagPaid = (clone $base)->count();
$invalid = (clone $base)->where('is_invalid_traffic', true)->count();
$visitRows = (clone $base)->get(['domain_id', 'url', 'google_campaign_id', 'visited_at', 'is_invalid_traffic']);

$domains = collect([$domain]);
$domainIds = collect([$domainId]);
$lookup = app(GoogleVerifiedPaidTraffic::class)->buildLookup(
    $domainIds,
    $fromDate,
    $toDate,
    $user,
    $reportingTz,
    $domains,
);
$verification = app(GoogleVerifiedPaidTraffic::class)->countRows($visitRows, $lookup, $reportingTz);

$googleAds = app(GoogleAdsDomainMetricsSync::class)
    ->clickTotalsForDomainsReporting($domainIds, $fromDate, $toDate, $reportingTz, $domains);
$googleClicks = (int) ($googleAds['clicks'] ?? 0);

echo "  Paid traffic (verified valid): " . ($verification['verified_valid'] ?? 0) . "\n";
echo "  Invalid paid: {$invalid}\n";
echo "  Total click count (Google): {$googleClicks}\n";
echo "  Tag paid (all): {$tagPaid}\n";
echo "  Verified paid (incl invalid): " . ($verification['verified'] ?? 0) . "\n";
echo "  Unverified paid: " . ($verification['unverified'] ?? 0) . "\n";
echo "  Gap (Google - verified valid): " . ($googleClicks - ($verification['verified_valid'] ?? 0)) . "\n";
echo "  Tag over-count vs Google (tag - google): " . ($tagPaid - $googleClicks) . "\n";

// Bot protection same day
$botQ = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereRaw("{$dayExpr} >= ?", [$fromDate])
    ->whereRaw("{$dayExpr} <= ?", [$toDate]);
GoogleClickAttribution::excludeClickIds($botQ);
echo "\nBot protection (organic only, same reporting day):\n";
echo "  Total visits: " . (clone $botQ)->count() . "\n";
echo "  Invalid/bot flagged: " . (clone $botQ)->where('is_invalid_traffic', 1)->count() . "\n";

// Check paid visits wrongly in bot protection (should be 0)
$paidInBot = DB::table('visits')
    ->where('domain_id', $domainId)
    ->whereRaw("{$dayExpr} >= ?", [$fromDate])
    ->whereRaw("{$dayExpr} <= ?", [$toDate]);
GoogleClickAttribution::applyHasClickIdFilter($paidInBot);
echo "  Paid with click-id (NOT in bot protection): " . $paidInBot->count() . "\n";

foreach (['UTC', 'America/Los_Angeles'] as $simTz) {
    echo "\n=== Dashboard sim {$date} with forced reporting TZ: {$simTz} ===\n";
    $dayExpr2 = UserTimezone::localDateSql('visited_at', null, $simTz);
    $base2 = DB::table('visits')->where('domain_id', $domainId)
        ->whereRaw("{$dayExpr2} = ?", [$date]);
    GoogleClickAttribution::applyHasClickIdFilter($base2);
    $rows2 = (clone $base2)->get(['domain_id', 'url', 'google_campaign_id', 'visited_at', 'is_invalid_traffic']);
    $lookup2 = app(GoogleVerifiedPaidTraffic::class)->buildLookup($domainIds, $date, $date, $user, $simTz, $domains);
    $v2 = app(GoogleVerifiedPaidTraffic::class)->countRows($rows2, $lookup2, $simTz);
    $g2 = app(GoogleAdsDomainMetricsSync::class)->clickTotalsForDomainsReporting($domainIds, $date, $date, $simTz, $domains);
    echo "  Paid (verified valid): " . ($v2['verified_valid'] ?? 0) . "\n";
    echo "  Invalid: " . (clone $base2)->where('is_invalid_traffic', 1)->count() . "\n";
    echo "  Google clicks: " . (int) ($g2['clicks'] ?? 0) . "\n";
    echo "  Unverified: " . ($v2['unverified'] ?? 0) . "\n";
}

// IP gap: unique IPs on verified valid paid vs Google
echo "\n=== IP catch gap (UTC {$date}) ===\n";
$utcExpr = UserTimezone::localDateSql('visited_at', null, 'UTC');
$paidRows = DB::table('visits')->where('domain_id', $domainId)
    ->whereRaw("{$utcExpr} = ?", [$date])
    ->whereNotNull('ip')->where('ip', '!=', '');
GoogleClickAttribution::applyHasClickIdFilter($paidRows);
$allPaidIps = (clone $paidRows)->distinct()->pluck('ip')->count();
$invalidPaidIps = (clone $paidRows)->where('is_invalid_traffic', 1)->distinct()->pluck('ip')->count();
$validPaidIps = (clone $paidRows)->where('is_invalid_traffic', 0)->distinct()->pluck('ip')->count();
echo "  Unique IPs (all paid tag): {$allPaidIps}\n";
echo "  Unique IPs (valid paid): {$validPaidIps}\n";
echo "  Unique IPs (invalid paid): {$invalidPaidIps}\n";
echo "  Google clicks: 155\n";
echo "  Possible missed IPs (Google - valid unique IPs): " . max(0, 155 - $validPaidIps) . "\n";
echo "  Extra tag hits beyond Google (tag paid - google): " . (193 - 155) . "\n";

$unverifiedSample = DB::table('visits')->where('domain_id', $domainId)
    ->whereRaw("{$utcExpr} = ?", [$date]);
GoogleClickAttribution::applyHasClickIdFilter($unverifiedSample);
$unverifiedSample = $unverifiedSample->limit(5)->get(['ip', 'gclid', 'google_campaign_id', 'utm_campaign', 'is_invalid_traffic', 'visited_at']);
echo "\nSample paid visits (first 5 UTC day):\n";
foreach ($unverifiedSample as $r) {
    echo "  {$r->visited_at} | {$r->ip} | invalid=" . ($r->is_invalid_traffic ? 'Y' : 'N') . " | camp={$r->google_campaign_id}\n";
}
