<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use App\Models\PaidMarketingVisit;
use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Illuminate\Support\Facades\DB;

$domain = Domain::query()->where('hostname', 'like', '%fibreopticnet%')->orWhere('id', 10)->first();
if (! $domain) {
    fwrite(STDERR, "domain not found\n");
    exit(1);
}

$user = $domain->user;
$ranges = [
    ['2026-07-01', '2026-07-01', 'Jul 1 only'],
    ['2026-06-30', '2026-06-30', 'Jun 30 only'],
    ['2026-06-30', '2026-07-01', 'Jun 30 – Jul 1'],
];

foreach ($ranges as [$from, $to, $label]) {
    $count = PaidMarketingVisit::query()
        ->where('domain_id', $domain->id)
        ->whereHas('clicks', function ($clickQuery) use ($from, $to, $user): void {
            UserTimezone::applyCalendarDateRangeFilter($clickQuery, 'clicked_at', $from, $to, $user);
            GoogleClickAttribution::applyPaidClickIdFilter($clickQuery, 'paid_id');
        })
        ->count();

    echo "{$label}: {$count} IPs\n";
}

if (\Illuminate\Support\Facades\Schema::hasTable('visits')) {
    echo "\nDashboard (visits table):\n";
    foreach ($ranges as [$from, $to, $label]) {
        $visitQuery = DB::table('visits')->where('domain_id', $domain->id);
        UserTimezone::applyCalendarDateRangeFilter($visitQuery, 'visited_at', $from, $to, $user);
        App\Support\GoogleClickAttribution::applyHasClickIdFilter($visitQuery);
        $ips = (clone $visitQuery)->distinct('ip')->count('ip');
        $visits = (clone $visitQuery)->count();
        echo "{$label}: {$ips} unique IPs, {$visits} visits\n";
    }
}
