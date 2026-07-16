<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Domain;
use Illuminate\Support\Facades\DB;

$d17 = Domain::find(17);
echo "Domain 17: ".($d17?->hostname ?? 'DELETED')." last_seen=".($d17?->last_seen_at ?? 'n/a')."\n";
$d19 = Domain::find(19);
echo "Domain 19: {$d19->hostname}\n";
echo "  domain_key={$d19->domain_key}\n";
echo "  secret_key={$d19->secret_key}\n";
echo "  auth_key={$d19->authentication_key}\n";
echo "  APP_URL=".config('app.url')."\n";

// Today clicks total for domain 19
$today = DB::table('google_ads_campaign_daily_metrics')
    ->where('domain_id', 19)
    ->where('metric_date', '2026-07-16')
    ->sum('clicks');
echo "Google clicks today (domain 19): {$today}\n";

// Check if collect endpoint would resolve this key
$found = Domain::where('domain_key', $d19->domain_key)->first();
echo "Lookup by domain_key works: ".($found ? 'YES' : 'NO')."\n";

// Recent intel job success sample
echo "\nRecent ip_logs with intel ok (5):\n";
foreach (DB::table('ip_logs')->where('intel_status','ok')->orderByDesc('intel_checked_at')->limit(5)->get() as $r) {
    echo "  {$r->ip} checked={$r->intel_checked_at} abuse={$r->abuse_confidence_score}\n";
}

echo "\nRecent ip_logs pending/null (5):\n";
foreach (DB::table('ip_logs')->where(function($q){$q->whereNull('intel_status')->orWhereIn('intel_status',['pending','error']);})->orderByDesc('id')->limit(5)->get() as $r) {
    echo "  {$r->ip} status=".($r->intel_status??'null')." checked=".($r->intel_checked_at??'-')."\n";
}
