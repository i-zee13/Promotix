<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Jobs\EnrichIpIntelJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TrackingController extends Controller
{
    /** 1×1 transparent GIF for GET pixel fallback (see TagController::pixel). */
    private const TRACKING_PIXEL_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    public function collect(Request $request)
    {
        // Handle CORS preflight
        if ($request->isMethod('options')) {
            return $this->cors($request, response()->noContent());
        }

        $data = Validator::make($request->all(), [
            'domainKey' => ['required', 'string'],
            'type' => ['nullable', 'string'],
            'url' => ['nullable', 'string'],
            'path' => ['nullable', 'string'],
            'referrer' => ['nullable', 'string'],
            'gclid' => ['nullable', 'string'],
            'utm_source' => ['nullable', 'string'],
            'utm_medium' => ['nullable', 'string'],
            'utm_campaign' => ['nullable', 'string'],
            'utm_term' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string'],
            'session_id' => ['nullable', 'string', 'max:128'],
        ])->validate();

        $domain = Domain::where('domain_key', $data['domainKey'])->firstOrFail();
        if (($domain->status ?? 'pending') === 'disabled') {
            return $this->cors($request, response()->json(['ok' => true, 'skipped' => 'disabled']));
        }

        $ip = $this->clientIp($request);
        $ua = $request->userAgent() ?? '';
        $browser = $this->browserFromUa($ua);
        $os = $this->osFromUa($ua);
        $country = $request->headers->get('CF-IPCountry') ?: null;
        $device = $this->platformFromUa($ua);
        $isCrawler = $this->isCrawlerUa($ua);
        $isPaidTraffic = ! empty($data['gclid'] ?? null) || ! empty($data['utm_campaign'] ?? null);
        $visitedAt = now();
        $sessionId = (string) ($request->input('session_id') ?: $request->cookie(config('session.cookie', 'laravel_session')) ?: $request->session()->getId());
        $sessionId = $sessionId !== '' ? $sessionId : null;

        // Mark domain as connected/seen
        $domain->last_seen_at = now();
        $domain->tag_connected = true;
        $domain->status = 'connected';
        $domain->save();

        // Log IP into existing ip_logs table
        $ipLog = IpLog::firstOrNew(['ip' => $ip]);
        if (! $ipLog->exists) {
            $ipLog->hits = 0;
        }
        $ipLog->hits = ($ipLog->hits ?? 0) + 1;
        $ipLog->user_agent = $ua;
        $ipLog->last_seen_at = now();
        $ipLog->last_path = $data['path'] ?? null;
        $ipLog->last_referrer = $data['referrer'] ?? null;
        $ipLog->save();

        // Enrich IP intel asynchronously (VPN/Proxy + abuse score)
        EnrichIpIntelJob::dispatch($ipLog->id);

        $existingSessionHits = 0;
        if ($sessionId !== null && Schema::hasTable('ip_sessions')) {
            $existingSessionHits = (int) (DB::table('ip_sessions')
                ->where('domain_id', $domain->id)
                ->where('session_id', $sessionId)
                ->value('hits') ?? 0);
        }

        $detection = $this->evaluateDetection($domain, $ipLog, $country, $existingSessionHits + 1);
        if ($detection['action_taken'] === 'block') {
            $ipLog->is_blocked = true;
            $ipLog->save();
        }

        // Paid marketing visit row (1 row per domain+ip)
        $visit = PaidMarketingVisit::firstOrNew([
            'domain_id' => $domain->id,
            'ip' => $ip,
        ]);
        if (! $visit->exists) {
            $visit->visits = 0;
        }
        $visit->visits = ($visit->visits ?? 0) + 1;
        $visit->last_click_at = now();
        $visit->last_path = $data['path'] ?? null;
        $visit->campaign = $data['utm_campaign'] ?? null;
        $visit->platform = $device;
        $visit->country = $country ?? $ipLog->intel_country_code;
        $visit->threat_group = $detection['threat_group'];
        $visit->threat_type = $detection['action_taken'] === 'allow' ? null : $detection['action_taken'];
        $visit->save();

        // Click detail entry (used by the modal list)
        PaidMarketingClick::create([
            'paid_marketing_visit_id' => $visit->id,
            'clicked_at' => now(),
            'ip' => $ip,
            'country' => $country ?? $ipLog->intel_country_code,
            'last_click_at' => now(),
            'threat_group' => $detection['threat_group'],
            'campaign' => $data['utm_campaign'] ?? null,
            'paid_id' => $data['gclid'] ?? null,
            'path' => $data['url'] ?? ($data['path'] ?? null),
            'keyword' => $data['utm_term'] ?? ($data['keyword'] ?? null),
            'browser_name' => $browser['name'],
            'browser_version' => $browser['version'],
            'os' => $os,
        ]);

        $visitId = null;
        if (Schema::hasTable('visits')) {
            $visitPayload = [
                'domain_id' => $domain->id,
                'session_id' => $sessionId,
                'ip' => $ip,
                'country' => $country ?? $ipLog->intel_country_code,
                'device' => $device,
                'browser' => $browser['name'],
                'os' => $os,
                'url' => $data['url'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'utm_term' => $data['utm_term'] ?? ($data['keyword'] ?? null),
                'is_paid_traffic' => $isPaidTraffic,
                'is_invalid_traffic' => $detection['action_taken'] !== 'allow',
                'visited_at' => $visitedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('visits', 'threat_score')) {
                $visitPayload['threat_score'] = $detection['threat_score'];
                $visitPayload['threat_group'] = $detection['threat_group'];
                $visitPayload['action_taken'] = $detection['action_taken'];
                $visitPayload['detection_reasons'] = json_encode($detection['reasons']);
            }

            if (Schema::hasColumn('visits', 'user_agent')) {
                $visitPayload['user_agent'] = $ua;
            }
            if (Schema::hasColumn('visits', 'is_crawler')) {
                $visitPayload['is_crawler'] = $isCrawler;
            }

            $visitId = DB::table('visits')->insertGetId($visitPayload);
        }

        if ($sessionId !== null && Schema::hasTable('ip_sessions')) {
            $existingSession = DB::table('ip_sessions')
                ->where('domain_id', $domain->id)
                ->where('session_id', $sessionId)
                ->first();

            if ($existingSession) {
                DB::table('ip_sessions')
                    ->where('id', $existingSession->id)
                    ->update([
                        'ip' => $ip,
                        'hits' => ((int) $existingSession->hits) + 1,
                        'last_seen_at' => $visitedAt,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('ip_sessions')->insert([
                    'domain_id' => $domain->id,
                    'session_id' => $sessionId,
                    'ip' => $ip,
                    'hits' => 1,
                    'last_seen_at' => $visitedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('analytics_hourly')) {
            $bucketHour = $visitedAt->copy()->startOfHour();
            $existingHour = DB::table('analytics_hourly')
                ->where('domain_id', $domain->id)
                ->where('bucket_hour', $bucketHour)
                ->first();

            if ($existingHour) {
                DB::table('analytics_hourly')
                    ->where('id', $existingHour->id)
                    ->update([
                        'total_visits' => ((int) $existingHour->total_visits) + 1,
                        'paid_visits' => ((int) $existingHour->paid_visits) + ($isPaidTraffic ? 1 : 0),
                        'invalid_visits' => ((int) $existingHour->invalid_visits) + ($detection['action_taken'] !== 'allow' ? 1 : 0),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('analytics_hourly')->insert([
                    'domain_id' => $domain->id,
                    'bucket_hour' => $bucketHour,
                    'total_visits' => 1,
                    'paid_visits' => $isPaidTraffic ? 1 : 0,
                    'invalid_visits' => $detection['action_taken'] !== 'allow' ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('detection_logs') && $detection['action_taken'] !== 'allow') {
            DB::table('detection_logs')->insert([
                'domain_id' => $domain->id,
                'visit_id' => $visitId,
                'ip' => $ip,
                'threat_score' => $detection['threat_score'],
                'threat_group' => $detection['threat_group'],
                'action_taken' => $detection['action_taken'],
                'reasons' => json_encode($detection['reasons']),
                'detected_at' => $visitedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($request->isMethod('get')) {
            return $this->cors(
                $request,
                response(self::TRACKING_PIXEL_GIF, 200, [
                    'Content-Type' => 'image/gif',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ])
            );
        }

        return $this->cors($request, response()->json(['ok' => true]));
    }

    private function clientIp(Request $request): string
    {
        $candidates = [
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('True-Client-IP'),
            $request->headers->get('X-Real-IP'),
            $request->headers->get('X-Forwarded-For'),
            // Some stacks (e.g. behind Apache/LiteSpeed) use this non-standard header.
            $request->headers->get('X-Cluster-Client-IP'),
        ];

        $ips = [];
        foreach ($candidates as $value) {
            if (! $value) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', $value) as $ip) {
                $ip = trim($ip);
                if ($ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        // Prefer a valid non-loopback IP if available.
        foreach ($ips as $ip) {
            if ($this->isValidIp($ip) && ! $this->isLoopbackIp($ip)) {
                return $ip;
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function isLoopbackIp(string $ip): bool
    {
        return $ip === '127.0.0.1' || $ip === '::1';
    }

    protected function cors(Request $request, $response)
    {
        $origin = $request->headers->get('Origin');
        $allowOrigin = $origin ?: '*';

        return $response
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Vary', 'Origin')
            ->header('Access-Control-Allow-Credentials', $origin ? 'true' : 'false')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Accept, Origin')
            ->header('Access-Control-Max-Age', '86400');
    }

    private function platformFromUa(string $ua): ?string
    {
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'mobile')) return 'Mobile';
        return 'Desktop';
    }

    private function osFromUa(string $ua): ?string
    {
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'windows')) return 'Windows';
        if (str_contains($uaLower, 'mac os') || str_contains($uaLower, 'macintosh')) return 'Mac';
        if (str_contains($uaLower, 'android')) return 'Android';
        if (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ios')) return 'iOS';
        if (str_contains($uaLower, 'linux')) return 'Linux';
        return null;
    }

    private function isCrawlerUa(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        $needles = [
            'Googlebot', 'bingbot', 'Slurp', 'DuckDuckBot', 'YandexBot', 'Baiduspider',
            'facebookexternalhit', 'Twitterbot', 'LinkedInBot', 'Applebot', 'AhrefsBot',
            'SemrushBot', 'MJ12bot', 'PetalBot', 'Bytespider', 'GPTBot', 'ClaudeBot',
        ];

        foreach ($needles as $needle) {
            if (stripos($ua, $needle) !== false) {
                return true;
            }
        }

        return preg_match('/(crawler|spider|bot)\\b/i', $ua) === 1;
    }

    private function browserFromUa(string $ua): array
    {
        // Very lightweight parsing (good enough for MVP UI).
        $patterns = [
            'Chrome' => '/Chrome\\/([0-9\\.]+)/',
            'Edge' => '/Edg\\/([0-9\\.]+)/',
            'Firefox' => '/Firefox\\/([0-9\\.]+)/',
            'Safari' => '/Version\\/([0-9\\.]+).*Safari/',
        ];
        foreach ($patterns as $name => $regex) {
            if (preg_match($regex, $ua, $m)) {
                return ['name' => $name, 'version' => $m[1] ?? null];
            }
        }
        return ['name' => null, 'version' => null];
    }

    private function evaluateDetection(Domain $domain, IpLog $ipLog, ?string $country, int $sessionHits): array
    {
        $settings = DomainDetectionSetting::firstOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => 'block',
                'invalid_malicious_action' => 'block',
                'suspicious_enabled' => true,
                'suspicious_matrix' => [
                    'vpn' => 'allow',
                    'proxy' => 'block',
                    'data_center' => 'block',
                    'abnormal_rate_limit' => 'allow',
                ],
                'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
            ]
        );

        if ($settings->allow_list_enabled && $this->ipInAllowList($ipLog->ip, (string) $settings->allow_list_ips)) {
            return [
                'threat_score' => 0,
                'threat_group' => null,
                'action_taken' => 'allow',
                'reasons' => ['allow_list'],
            ];
        }

        $matrix = (array) ($settings->suspicious_matrix ?? []);
        $signals = [];

        if ((int) ($ipLog->iphub_block ?? 0) === 1) {
            $signals[] = ['group' => 'data_center', 'score' => 60, 'action' => $matrix['data_center'] ?? 'block'];
        }

        if ((bool) ($ipLog->abuse_is_tor ?? false)) {
            $signals[] = ['group' => 'vpn', 'score' => 70, 'action' => $matrix['vpn'] ?? 'allow'];
        }

        if ((int) ($ipLog->abuse_confidence_score ?? 0) >= 50) {
            $signals[] = ['group' => 'malicious', 'score' => (int) $ipLog->abuse_confidence_score, 'action' => $settings->invalid_malicious_action];
        }

        if ($settings->frequency_capping && $sessionHits > 5) {
            $signals[] = ['group' => 'abnormal_rate_limit', 'score' => min(100, 40 + ($sessionHits * 5)), 'action' => $matrix['abnormal_rate_limit'] ?? 'allow'];
        }

        if ($settings->out_of_geo_enabled && $country) {
            $allowedCountries = collect((array) ($settings->out_of_geo_countries ?? []))
                ->map(fn ($value) => strtoupper(trim((string) $value)))
                ->filter()
                ->all();
            if ($allowedCountries !== [] && ! in_array(strtoupper($country), $allowedCountries, true)) {
                $signals[] = ['group' => 'out_of_geo', 'score' => 55, 'action' => 'flag'];
            }
        }

        if ($signals === []) {
            return [
                'threat_score' => 0,
                'threat_group' => null,
                'action_taken' => 'allow',
                'reasons' => [],
            ];
        }

        usort($signals, fn ($a, $b) => $b['score'] <=> $a['score']);
        $action = $this->strongestAction(array_column($signals, 'action'));

        return [
            'threat_score' => max(array_column($signals, 'score')),
            'threat_group' => $signals[0]['group'],
            'action_taken' => $action,
            'reasons' => array_values(array_unique(array_column($signals, 'group'))),
        ];
    }

    private function strongestAction(array $actions): string
    {
        if (in_array('block', $actions, true)) {
            return 'block';
        }
        if (in_array('flag', $actions, true)) {
            return 'flag';
        }
        return 'allow';
    }

    private function ipInAllowList(string $ip, string $allowList): bool
    {
        $items = preg_split('/[\s,]+/', $allowList) ?: [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if ($item === $ip) {
                return true;
            }
            if (str_ends_with($item, '*') && str_starts_with($ip, rtrim($item, '*'))) {
                return true;
            }
        }
        return false;
    }
}

