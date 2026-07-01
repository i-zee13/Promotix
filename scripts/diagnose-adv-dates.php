<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use App\Models\User;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$domain = Domain::query()->where('hostname', 'like', '%fibreopticnet%')->first();
$user = $domain?->user ?? User::query()->first();
$tz = UserTimezone::forUser($user);
$from = '2026-07-01';
$to = '2026-07-01';

echo "User TZ: {$tz}\n";
echo "Filter: {$from} to {$to}\n\n";

$localDate = UserTimezone::localDateSql('pc.clicked_at', $user);
$rows = DB::table('paid_marketing_clicks as pc')
    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
    ->where('pv.domain_id', $domain->id)
    ->whereNotNull('pc.paid_id')
    ->where('pc.paid_id', '!=', '')
    ->whereRaw("{$localDate} BETWEEN ? AND ?", [$from, $to])
    ->select('pc.id', 'pc.ip', 'pc.clicked_at', DB::raw("{$localDate} as local_day"))
    ->orderBy('pc.clicked_at')
    ->get();

echo "Clicks matching local calendar day (localDateSql): {$rows->count()}\n";
foreach ($rows as $row) {
    $raw = (string) $row->clicked_at;
    $utc = Carbon::parse($raw, 'UTC');
    $wrong = Carbon::parse($raw, $tz);
    echo "  IP {$row->ip} | DB raw: {$raw} | local_day: {$row->local_day}";
    echo " | UTC→user: " . $utc->timezone($tz)->format('m/d/y H:i');
    echo " | misread as local: " . $wrong->format('m/d/y H:i');
    echo " | formatForUser(wrong): " . UserTimezone::formatForUser($wrong, $user, 'm/d/y');
    echo " | formatForUser(utc): " . UserTimezone::formatForUser($utc, $user, 'm/d/y');
    echo "\n";
}

$query = DB::table('paid_marketing_clicks as pc')
    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
    ->where('pv.domain_id', $domain->id)
    ->whereNotNull('pc.paid_id')
    ->where('pc.paid_id', '!=', '');
UserTimezone::applyCalendarDateRangeFilter($query, 'pc.clicked_at', $from, $to, $user);
$utcRows = $query->select('pc.id', 'pc.ip', 'pc.clicked_at')->get();
echo "\nClicks matching applyCalendarDateRangeFilter: {$utcRows->count()}\n";
