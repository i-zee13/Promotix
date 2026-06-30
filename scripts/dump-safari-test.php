<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$domains = DB::table('domains')->where('hostname', 'like', '%insuranceforme%')->get();
echo "=== DOMAINS ===\n";
foreach ($domains as $d) {
    echo "#{$d->id} {$d->hostname} last_seen={$d->last_seen_at} tag={$d->tag_connected} paid={$d->paid_marketing_connected}\n";
}

$ids = $domains->pluck('id')->all();
if ($ids === []) {
    exit(1);
}

echo "\n=== VISITS (last 30, any domain) ===\n";
$cols = ['id', 'domain_id', 'visited_at', 'ip', 'gclid', 'gbraid', 'wbraid', 'is_paid_traffic', 'browser', 'os', 'url'];
$existing = array_filter($cols, fn ($c) => $c === 'domain_id' || Schema::hasColumn('visits', $c));
$visits = DB::table('visits')->whereIn('domain_id', $ids)->orderByDesc('visited_at')->limit(30)->get($existing);
foreach ($visits as $v) {
    $url = substr((string) ($v->url ?? ''), 0, 120);
    echo "{$v->visited_at} | ip={$v->ip} | paid={$v->is_paid_traffic} | gclid=" . substr((string) ($v->gclid ?? ''), 0, 20)
        . " | gbraid=" . substr((string) ($v->gbraid ?? ''), 0, 20)
        . " | wbraid=" . substr((string) ($v->wbraid ?? ''), 0, 20)
        . " | {$v->browser}/{$v->os} | {$url}\n";
}

echo "\n=== VISITS with promotix_test or skip (all time) ===\n";
$test = DB::table('visits')->whereIn('domain_id', $ids)
    ->where(function ($q) {
        $q->where('url', 'like', '%promotix_test%')
            ->orWhere('url', 'like', '%promotix_hit%')
            ->orWhere('url', 'like', '%skip_%');
    })
    ->orderByDesc('visited_at')
    ->get($existing);
if ($test->isEmpty()) {
    echo "(none)\n";
} else {
    foreach ($test as $v) {
        echo json_encode($v, JSON_UNESCAPED_SLASHES) . "\n";
    }
}

echo "\n=== GOOGLE_ADS_CAMPAIGN_DAILY_METRICS (last 14 days) ===\n";
if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
    $rows = DB::table('google_ads_campaign_daily_metrics')
        ->whereIn('domain_id', $ids)
        ->where('metric_date', '>=', now()->subDays(14)->toDateString())
        ->orderByDesc('metric_date')
        ->get();
    $byDay = [];
    foreach ($rows as $r) {
        $byDay[$r->metric_date] = ($byDay[$r->metric_date] ?? 0) + (int) $r->clicks;
    }
    foreach ($byDay as $day => $clicks) {
        echo "{$day}: {$clicks} clicks\n";
    }
    if ($rows->isEmpty()) {
        echo "(no rows)\n";
    }
}

echo "\n=== VISITS on " . now()->toDateString() . " ===\n";
$today = DB::table('visits')->whereIn('domain_id', $ids)->whereDate('visited_at', now()->toDateString())->get($existing);
echo 'count=' . $today->count() . "\n";
foreach ($today as $v) {
    echo json_encode($v, JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== SAFARI visits (last 10) ===\n";
$safari = DB::table('visits')->whereIn('domain_id', $ids)
    ->where(function ($q) {
        $q->where('browser', 'like', '%Safari%')->orWhere('os', 'like', '%Mac%')->orWhere('os', 'like', '%iOS%');
    })
    ->orderByDesc('visited_at')->limit(10)->get($existing);
foreach ($safari as $v) {
    echo "{$v->visited_at} | ip={$v->ip} | paid={$v->is_paid_traffic} | {$v->browser}/{$v->os} | " . substr((string) $v->url, 0, 100) . "\n";
}

echo "\n=== PAID_MARKETING (last 10 clicks) ===\n";
if (Schema::hasTable('paid_marketing_clicks')) {
    $pc = DB::table('paid_marketing_clicks as pc')
        ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
        ->whereIn('pv.domain_id', $ids)
        ->orderByDesc('pc.clicked_at')
        ->limit(10)
        ->get(['pc.clicked_at', 'pc.ip', 'pc.paid_id', 'pc.path', 'pc.threat_group']);
    foreach ($pc as $r) {
        echo json_encode($r) . "\n";
    }
    if ($pc->isEmpty()) {
        echo "(none)\n";
    }
}
