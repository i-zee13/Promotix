<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$hostname = $argv[1] ?? 'fibreopticnet.com';
$date = $argv[2] ?? '2026-07-06';

$domain = Domain::query()->where('hostname', $hostname)->with('googleAdsAccount')->first();
if (! $domain) {
    echo "Domain not found: {$hostname}\n";
    exit(1);
}

echo "=== Domain #{$domain->id} {$domain->hostname} ===\n";
echo 'Tag connected: ' . ($domain->tag_connected ? 'yes' : 'no') . "\n";
echo 'Paid marketing connected: ' . ($domain->paid_marketing_connected ? 'yes' : 'no') . "\n";
echo 'Google account: ' . ($domain->googleAdsAccount?->displayLabel() ?? 'none') . "\n";
echo 'Google TZ: ' . ($domain->googleAdsAccount?->time_zone ?? 'null') . "\n";
echo "Date filter: {$date}\n\n";

$googleTz = $domain->googleAdsAccount?->time_zone ?: 'UTC';

// Google metrics for Jul 6 reporting in UTC vs Google TZ
foreach (['UTC', 'America/New_York', 'Asia/Karachi'] as $reportingTz) {
    $bounds = UserTimezone::googleMetricDateBounds($date, $date, $reportingTz, $googleTz);
    echo "Reporting {$reportingTz} → Google metric dates: {$bounds[0]} to {$bounds[1]}\n";
}
echo "\n";

if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
    $metrics = DB::table('google_ads_campaign_daily_metrics')
        ->where('domain_id', $domain->id)
        ->whereBetween('metric_date', ['2026-07-05', '2026-07-07'])
        ->orderBy('metric_date')
        ->orderBy('campaign_id')
        ->get();
    echo "=== Google Ads daily metrics (Jul 5-7) ===\n";
    if ($metrics->isEmpty()) {
        echo "  (none)\n";
    } else {
        foreach ($metrics as $m) {
            echo "  {$m->metric_date} | campaign {$m->campaign_id} | clicks {$m->clicks} | {$m->campaign_name}\n";
        }
        echo '  TOTAL clicks: ' . $metrics->sum('clicks') . "\n";
    }
    echo "\n";
}

if (Schema::hasTable('visits')) {
    $dayUtc = UserTimezone::localDateSql('visited_at', null, 'UTC');
    $dayNy = UserTimezone::localDateSql('visited_at', null, 'America/New_York');

    $allJul6Utc = DB::table('visits')
        ->where('domain_id', $domain->id)
        ->whereRaw("{$dayUtc} = ?", [$date])
        ->count();

    $paidJul6Utc = DB::table('visits')
        ->where('domain_id', $domain->id)
        ->whereRaw("{$dayUtc} = ?", [$date])
        ->where(function ($q) {
            foreach (['gclid', 'gbraid', 'wbraid'] as $col) {
                if (Schema::hasColumn('visits', $col)) {
                    $q->orWhere(function ($inner) use ($col) {
                        $inner->whereNotNull($col)->where($col, '!=', '');
                    });
                }
            }
        })
        ->count();

    echo "=== Visits table (calendar day {$date}) ===\n";
    echo "  All visits (UTC day): {$allJul6Utc}\n";
    echo "  With gclid/gbraid/wbraid (UTC day): {$paidJul6Utc}\n";

    $paidVisits = DB::table('visits')
        ->where('domain_id', $domain->id)
        ->whereRaw("{$dayUtc} = ?", [$date])
        ->where(function ($q) {
            foreach (['gclid', 'gbraid', 'wbraid'] as $col) {
                if (Schema::hasColumn('visits', $col)) {
                    $q->orWhere(function ($inner) use ($col) {
                        $inner->whereNotNull($col)->where($col, '!=', '');
                    });
                }
            }
        })
        ->select(['id', 'visited_at', 'ip', 'url', 'gclid', 'gbraid', 'wbraid', 'google_campaign_id', 'is_invalid_traffic'])
        ->limit(20)
        ->get();

    foreach ($paidVisits as $v) {
        $gad = '';
        if (preg_match('/gad_campaignid=(\d+)/', (string) $v->url, $m)) {
            $gad = $m[1];
        }
        echo "  visit #{$v->id} {$v->visited_at} ip={$v->ip} invalid=" . ($v->is_invalid_traffic ? '1' : '0') . "\n";
        echo "    gclid=" . ($v->gclid ?: '-') . " gad_campaignid={$gad} stored_cid=" . ($v->google_campaign_id ?: '-') . "\n";
        echo '    url=' . substr((string) $v->url, 0, 120) . "\n";
    }

  // Any paid visits last 3 days
    $recentPaid = DB::table('visits')
        ->where('domain_id', $domain->id)
        ->where('visited_at', '>=', now()->subDays(3))
        ->where(function ($q) {
            foreach (['gclid', 'gbraid', 'wbraid'] as $col) {
                if (Schema::hasColumn('visits', $col)) {
                    $q->orWhere(function ($inner) use ($col) {
                        $inner->whereNotNull($col)->where($col, '!=', '');
                    });
                }
            }
        })
        ->orderByDesc('visited_at')
        ->limit(10)
        ->get(['id', 'visited_at', 'ip', 'url', 'gclid', 'gbraid', 'wbraid', 'google_campaign_id', 'is_invalid_traffic']);
    echo "  Paid visits last 3 days: {$recentPaid->count()}\n";
    foreach ($recentPaid as $v) {
        $gad = '';
        if (preg_match('/gad_campaignid=(\d+)/', (string) $v->url, $m)) {
            $gad = $m[1];
        }
        echo "    #{$v->id} {$v->visited_at} ip={$v->ip} gclid=" . ($v->gclid ?: '-') . " gad={$gad}\n";
    }
    echo "\n";
}

if (Schema::hasTable('paid_marketing_visits')) {
    $pmvAll = DB::table('paid_marketing_visits')
        ->where('domain_id', $domain->id)
        ->orderByDesc('last_click_at')
        ->limit(5)
        ->get();
    echo "=== paid_marketing_visits (domain) total: " . DB::table('paid_marketing_visits')->where('domain_id', $domain->id)->count() . " ===\n";
    foreach ($pmvAll as $r) {
        echo "  #{$r->id} {$r->last_click_at} ip={$r->ip} visits={$r->visits}\n";
    }
    echo "\n";
}

// Jul 6 in Google account TZ (America/New_York)
$jul6NyStart = '2026-07-06 04:00:00'; // midnight EDT = 04:00 UTC
$jul6NyEnd = '2026-07-07 03:59:59';
$nyVisits = DB::table('visits')
    ->where('domain_id', $domain->id)
    ->whereBetween('visited_at', [$jul6NyStart, $jul6NyEnd])
    ->get(['id', 'visited_at', 'ip', 'gclid', 'gbraid', 'wbraid', 'url', 'is_paid_traffic']);
echo "=== All visits Jul 6 America/New_York day (UTC {$jul6NyStart} – {$jul6NyEnd}) ===\n";
echo '  Count: ' . $nyVisits->count() . "\n";
foreach ($nyVisits as $v) {
    echo "  #{$v->id} {$v->visited_at} ip={$v->ip} paid=" . ($v->is_paid_traffic ? '1' : '0');
    echo ' gclid=' . ($v->gclid ?: '-') . "\n";
}

echo "\nDB: " . config('database.connections.mysql.database') . '@' . config('database.connections.mysql.host') . "\n";
