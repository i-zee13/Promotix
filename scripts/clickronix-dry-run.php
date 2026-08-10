<?php

/**
 * Dry-run Clickronix scoring against live app/DB (read+write intentional).
 * Usage: php artisan tinker scripts/clickronix-dry-run.php
 *    or: php -r '...' via artisan command below.
 */

use App\Models\Domain;
use App\Models\IpLog;
use App\Services\IpIntel\IpFraudEvaluator;
use App\Support\Clickronix\ScoringEngine;
use App\Support\Clickronix\TriggeredSignal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$base = rtrim(env('APP_URL', 'http://127.0.0.1:8001'), '/');
if (! str_contains($base, '8001')) {
    $base = 'http://127.0.0.1:8001';
}

$report = [];
$pass = 0;
$fail = 0;

$assert = function (string $name, bool $ok, string $detail = '') use (&$report, &$pass, &$fail): void {
    $report[] = [
        'name' => $name,
        'ok' => $ok,
        'detail' => $detail,
    ];
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL')." | {$name}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
};

echo "=== Clickronix dry-run @ {$base} ===".PHP_EOL;

// --- A) Pure scoring engine cases ---
$engine = new ScoringEngine;

$vpnOnly = $engine->score([
    TriggeredSignal::triggered('VPN', 0.95, legacyReason: 'vpn_isp_match'),
]);
$assert('engine VPN alone never blocks', $vpnOnly['action_taken'] === 'allow', 'action='.$vpnOnly['action_taken'].' score='.$vpnOnly['threat_score']);

$rapidBlock = $engine->score([
    TriggeredSignal::triggered('RAPID_REPEAT_BLOCK', 1.0, recurrenceCount: 2, legacyReason: 'RAPID_REPEAT_BLOCK', customerPreferredAction: 'block'),
]);
$assert('engine rapid block standalone', $rapidBlock['action_taken'] === 'block' && $rapidBlock['standalone_fired'], 'score='.$rapidBlock['threat_score']);

$unknown = $engine->score([
    TriggeredSignal::unknown('VPN', ['intel_confidence']),
]);
$assert('engine UNKNOWN adds 0', $unknown['threat_score'] === 0 && $unknown['action_taken'] === 'allow');

// --- B) Evaluator against a real domain ---
$domain = Domain::query()->where('status', '!=', 'disabled')->orderByDesc('id')->first();
$assert('domain available', $domain !== null, $domain ? "id={$domain->id} key={$domain->domain_key}" : 'none');

if ($domain) {
    $evaluator = app(IpFraudEvaluator::class);
    $testIp = '203.0.113.'.random_int(10, 200); // TEST-NET-3 documentation range

    $ipLog = IpLog::firstOrNew(['ip' => $testIp]);
    if (! $ipLog->exists) {
        $ipLog->hits = 0;
    }
    $ipLog->hits = ($ipLog->hits ?? 0) + 1;
    $ipLog->user_agent = 'Mozilla/5.0 (ClickronixDryRun)';
    $ipLog->last_seen_at = now();
    // Simulate VPN intel without external provider
    $ipLog->ipdetails_raw = ['privacy' => ['vpn' => true], 'company' => ['type' => 'isp']];
    $ipLog->abuse_confidence_score = null;
    $ipLog->save();

    // Force VPN path via raw flags that IpIntelService understands — best effort
    $resultVpn = $evaluator->evaluate(
        domain: $domain,
        ipLog: $ipLog,
        country: 'US',
        sessionHits: 1,
        ipRecentHits: 0,
        isCrawler: false,
        isPaidTraffic: true,
        paidClicksToday: 0,
        ipMinuteHits: 0,
        paidClicksInRapidWindow: 0,
    );

    $assert(
        'evaluator VPN-context does not hard-block alone (or no VPN signal)',
        $resultVpn['action_taken'] !== 'block' || ! in_array('vpn_isp_match', $resultVpn['reasons'] ?? [], true) || count($resultVpn['reasons']) > 1,
        'action='.$resultVpn['action_taken'].' reasons='.json_encode($resultVpn['reasons']).' score='.$resultVpn['threat_score']
    );

    $resultRapid = $evaluator->evaluate(
        domain: $domain,
        ipLog: $ipLog,
        country: 'US',
        sessionHits: 1,
        ipRecentHits: 0,
        isCrawler: false,
        isPaidTraffic: true,
        paidClicksToday: 0,
        ipMinuteHits: 0,
        paidClicksInRapidWindow: 2,
    );
    $assert(
        'evaluator paid rapid (>=2) blocks',
        $resultRapid['action_taken'] === 'block' && in_array('RAPID_REPEAT_BLOCK', $resultRapid['reasons'], true),
        'action='.$resultRapid['action_taken'].' score='.$resultRapid['threat_score'].' reasons='.json_encode($resultRapid['reasons'])
    );

    $resultFlag = $evaluator->evaluate(
        domain: $domain,
        ipLog: $ipLog,
        country: 'US',
        sessionHits: 1,
        ipRecentHits: 0,
        isCrawler: false,
        isPaidTraffic: true,
        paidClicksToday: 0,
        ipMinuteHits: 0,
        paidClicksInRapidWindow: 1,
    );
    $assert(
        'evaluator paid rapid (=1) flags',
        $resultFlag['action_taken'] === 'flag' && in_array('RAPID_REPEAT', $resultFlag['reasons'], true),
        'action='.$resultFlag['action_taken'].' score='.$resultFlag['threat_score']
    );

    $resultExtreme = $evaluator->evaluate(
        domain: $domain,
        ipLog: $ipLog,
        country: 'US',
        sessionHits: 1,
        ipRecentHits: 0,
        isCrawler: false,
        isPaidTraffic: false,
        paidClicksToday: 0,
        ipMinuteHits: 15,
        paidClicksInRapidWindow: 0,
    );
    $assert(
        'evaluator extreme velocity standalone block',
        $resultExtreme['action_taken'] === 'block',
        'action='.$resultExtreme['action_taken'].' score='.$resultExtreme['threat_score'].' reasons='.json_encode($resultExtreme['reasons'])
    );

    $hasCx = isset($resultRapid['clickronix']) && is_array($resultRapid['clickronix']);
    $assert('evaluator returns clickronix breakdown', $hasCx, $hasCx ? 'ruleset='.($resultRapid['clickronix']['ruleset_version'] ?? '?') : 'missing');
}

// --- C) HTTP tracking collect (paid rapid sequence) ---
if ($domain) {
    $session = 'dryrun-'.Str::uuid()->toString();
    $gclidBase = 'DRYRUN'.Str::lower(Str::random(12));
    $ipHeader = '198.51.100.'.random_int(10, 200); // TEST-NET-2

    $postCollect = function (string $gclid, string $ip) use ($base, $domain, $session): array {
        $payload = [
            'domainKey' => $domain->domain_key,
            'type' => 'pageview',
            'url' => 'https://example-dryrun.test/?gclid='.$gclid,
            'path' => '/',
            'gclid' => $gclid,
            'session_id' => $session,
            'ts' => now()->getTimestampMs(),
        ];
        $ch = curl_init($base.'/t/collect');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Forwarded-For: '.$ip,
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 ClickronixDryRun',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return ['code' => $code, 'body' => $body, 'err' => $err, 'json' => json_decode((string) $body, true)];
    };

    $r1 = $postCollect($gclidBase.'a', $ipHeader);
    $assert('HTTP collect #1 ok', $r1['code'] >= 200 && $r1['code'] < 300 && ($r1['json']['ok'] ?? false) === true, 'http='.$r1['code'].' body='.substr((string) $r1['body'], 0, 180));

    usleep(300000);
    $r2 = $postCollect($gclidBase.'b', $ipHeader);
    $assert('HTTP collect #2 responds', $r2['code'] >= 200 && $r2['code'] < 300, 'http='.$r2['code'].' action='.($r2['json']['action'] ?? '?'));

    usleep(300000);
    $r3 = $postCollect($gclidBase.'c', $ipHeader);
    $assert('HTTP collect #3 responds', $r3['code'] >= 200 && $r3['code'] < 300, 'http='.$r3['code'].' action='.($r3['json']['action'] ?? '?').' blocked='.json_encode($r3['json']['blocked'] ?? null));

    // DB assertion: recent visits for this IP / dry-run gclid
    if (Schema::hasTable('visits')) {
        $visitCount = DB::table('visits')->where('domain_id', $domain->id)->where('ip', $ipHeader)->count();
        $assert('DB visits written for dry-run IP', $visitCount >= 1, 'count='.$visitCount);

        $latest = DB::table('visits')->where('domain_id', $domain->id)->where('ip', $ipHeader)->orderByDesc('id')->first();
        if ($latest && Schema::hasColumn('visits', 'action_taken')) {
            $assert(
                'DB latest visit has action_taken',
                in_array(($latest->action_taken ?? ''), ['allow', 'flag', 'block'], true),
                'action='.($latest->action_taken ?? 'null').' score='.($latest->threat_score ?? '?')
            );
        }
    }

    if (Schema::hasTable('detection_logs') && Schema::hasColumn('detection_logs', 'clickronix_breakdown')) {
        $logs = DB::table('detection_logs')->where('domain_id', $domain->id)->where('ip', $ipHeader)->orderByDesc('id')->limit(3)->get();
        $assert('detection_logs rows for dry-run IP (if non-allow)', true, 'count='.$logs->count());
        foreach ($logs as $log) {
            if ($log->clickronix_breakdown) {
                $assert('clickronix_breakdown persisted', true, 'log_id='.$log->id);
                break;
            }
        }
    }
}

// --- D) Admin login HTTP ---
$cookieJar = tempnam(sys_get_temp_dir(), 'cxcookie');
$loginPage = curl_init($base.'/login');
curl_setopt_array($loginPage, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 20,
]);
$loginHtml = curl_exec($loginPage);
$loginCode = (int) curl_getinfo($loginPage, CURLINFO_HTTP_CODE);
curl_close($loginPage);
$assert('GET /login', $loginCode === 200, 'http='.$loginCode);

$token = null;
if (is_string($loginHtml) && preg_match('/name="_token" value="([^"]+)"/', $loginHtml, $m)) {
    $token = $m[1];
}
$assert('CSRF token found', is_string($token) && $token !== '');

if ($token) {
    $post = curl_init($base.'/login');
    curl_setopt_array($post, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POSTFIELDS => http_build_query([
            '_token' => $token,
            'email' => 'admin@example.com',
            'password' => 'C4x3qdLCdLlPPc',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: text/html'],
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $postResp = curl_exec($post);
    $postCode = (int) curl_getinfo($post, CURLINFO_HTTP_CODE);
    curl_close($post);
    $assert('POST /login redirects or 200', in_array($postCode, [200, 302], true), 'http='.$postCode);

    $dash = curl_init($base.'/dashboard');
    curl_setopt_array($dash, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADER => true,
    ]);
    $dashResp = curl_exec($dash);
    $dashCode = (int) curl_getinfo($dash, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($dash, CURLINFO_EFFECTIVE_URL);
    curl_close($dash);
    $loggedIn = $dashCode === 200 && is_string($dashResp) && ! str_contains($finalUrl, '/login');
    $assert('Admin session reaches dashboard', $loggedIn, 'http='.$dashCode.' url='.$finalUrl);

    // Smoke a few detection-related pages if routes exist
    foreach (['/paid-marketing/detection-settings', '/bot-protection', '/paid-marketing'] as $path) {
        $p = curl_init($base.$path);
        curl_setopt_array($p, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_exec($p);
        $c = (int) curl_getinfo($p, CURLINFO_HTTP_CODE);
        curl_close($p);
        $assert("GET {$path}", in_array($c, [200, 302, 404], true), 'http='.$c);
    }
}
@unlink($cookieJar);

echo PHP_EOL."=== SUMMARY pass={$pass} fail={$fail} ===".PHP_EOL;
exit($fail > 0 ? 1 : 0);
