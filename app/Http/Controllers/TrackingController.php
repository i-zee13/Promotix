<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClientIp;
use App\Models\Domain;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Services\IpIntel\VisitProtectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TrackingController extends Controller
{
    use ResolvesClientIp;

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
            'ts' => ['nullable', 'numeric'],
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
        $visitedAt = isset($data['ts']) && is_numeric($data['ts'])
            ? Carbon::createFromTimestampMs((int) $data['ts'])
            : now();
        $sessionId = (string) ($request->input('session_id') ?: $request->cookie(config('session.cookie', 'laravel_session')) ?: $request->session()->getId());
        $sessionId = $sessionId !== '' ? $sessionId : null;

        // Log IP and run fraud protection (sync intel + block repeat offenders).
        $protection = app(VisitProtectionService::class);
        $ipLog = $protection->touchIpLog($ip, $ua, $data['path'] ?? null, $data['referrer'] ?? null);
        $assessment = $protection->assess($domain, $ipLog, $country, $sessionId, $isCrawler);
        $ipLog = $assessment['ipLog'];
        $detection = $assessment['detection'];
        $enforceBlock = $assessment['enforce_block'];

        $resolvedCountry = $country ?? $ipLog->intel_country_code ?? $ipLog->intel_country_name;
        $domain->last_seen_at = now();
        $domain->tag_connected = true;
        $domain->status = 'connected';
        if ($isPaidTraffic) {
            $domain->paid_marketing_connected = true;
        }
        if ($detection['action_taken'] !== 'allow' || $isCrawler) {
            $domain->bot_mitigation_connected = true;
        }
        $domain->save();

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
        $visit->country = $ipLog->intel_country_name ?? $resolvedCountry;
        $visit->threat_group = $detection['threat_group'];
        $visit->threat_type = $detection['action_taken'] === 'allow' ? null : $detection['action_taken'];
        $visit->save();

        // Click detail entry (used by the modal list)
        PaidMarketingClick::create([
            'paid_marketing_visit_id' => $visit->id,
            'clicked_at' => now(),
            'ip' => $ip,
            'country' => $ipLog->intel_country_name ?? $resolvedCountry,
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
                'country' => $ipLog->intel_country_name ?? $resolvedCountry,
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

        $clientPayload = $protection->clientPayload($detection, $enforceBlock);

        if ($request->isMethod('get')) {
            if ($enforceBlock) {
                return $this->cors($request, response()->json($clientPayload, 403));
            }

            return $this->cors(
                $request,
                response(self::TRACKING_PIXEL_GIF, 200, [
                    'Content-Type' => 'image/gif',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ])
            );
        }

        return $this->cors($request, response()->json($clientPayload));
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

}

