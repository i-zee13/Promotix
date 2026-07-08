<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClientIp;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Services\GoogleAudienceExclusionService;
use App\Services\IpIntel\VisitProtectionService;
use App\Support\CampaignAttributionResolver;
use App\Support\CountryValue;
use App\Support\GoogleAdsClickRedirect;
use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class TrackingController extends Controller
{
    use ResolvesClientIp;

    /** 1×1 transparent GIF for GET pixel fallback (see TagController::pixel). */
    private const TRACKING_PIXEL_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    /**
     * Google Ads tracking template entry point (ClickRonix-style).
     * Captures click server-side, then redirects to the advertiser landing page.
     */
    public function googleAdsClick(Request $request): Response
    {
        $params = GoogleAdsClickRedirect::parseClickRequest($request);
        $finalUrl = (string) ($params['final_url'] ?? '');

        if ($finalUrl === '') {
            return response('Missing final_url', 400);
        }

        $domain = GoogleAdsClickRedirect::resolveDomainFromFinalUrl($finalUrl);
        if (! $domain) {
            return response('Unknown landing domain', 404);
        }

        if (! GoogleAdsClickRedirect::isAllowedFinalUrl($finalUrl, $domain)) {
            return response('Landing URL not allowed for domain', 403);
        }

        if (($domain->status ?? 'pending') === 'disabled') {
            return redirect()->away($finalUrl, 302);
        }

        $redirectUrl = GoogleAdsClickRedirect::buildRedirectUrl($finalUrl, $params);
        $path = (string) (parse_url($finalUrl, PHP_URL_PATH) ?: '/');

        $ingestPayload = [
            'domainKey' => $domain->domain_key,
            'type' => 'google_ads_click',
            'url' => $redirectUrl,
            'path' => $path,
            'referrer' => 'https://www.google.com/',
            'gclid' => $params['gclid'] ?: null,
            'gbraid' => $params['gbraid'] ?: null,
            'wbraid' => $params['wbraid'] ?: null,
            'gad_campaignid' => $params['gad_campaignid'] ?: null,
            'keyword' => $params['keyword'] ?: null,
            'utm_term' => $params['keyword'] ?: null,
            'utm_source' => ($params['source'] ?? '') === 'google_ads' ? 'google' : null,
            'utm_medium' => ($params['source'] ?? '') === 'google_ads' ? 'cpc' : null,
            'click_source' => 'server',
            'ad_click_meta' => GoogleAdsClickRedirect::adClickMeta($params),
        ];

        $ingest = $request->duplicate(null, $ingestPayload);
        $ingest->setMethod('POST');

        try {
            $this->collect($ingest);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->away($redirectUrl, 302);
    }

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
            'gbraid' => ['nullable', 'string'],
            'wbraid' => ['nullable', 'string'],
            'utm_source' => ['nullable', 'string'],
            'utm_medium' => ['nullable', 'string'],
            'utm_campaign' => ['nullable', 'string'],
            'gad_campaignid' => ['nullable', 'string'],
            'utm_term' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string'],
            'session_id' => ['nullable', 'string', 'max:128'],
            'ts' => ['nullable', 'numeric'],
            'click_source' => ['nullable', 'string', 'max:16'],
            'ad_click_meta' => ['nullable'],
        ])->validate();

        if (isset($data['ad_click_meta']) && is_string($data['ad_click_meta'])) {
            $decoded = json_decode($data['ad_click_meta'], true);
            $data['ad_click_meta'] = is_array($decoded) ? $decoded : [];
        }
        if (! isset($data['ad_click_meta']) || ! is_array($data['ad_click_meta'])) {
            $data['ad_click_meta'] = [];
        }
        if (! filled($data['click_source'] ?? null)) {
            $data['click_source'] = 'tag';
        }

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
        $isPaidTraffic = GoogleClickAttribution::isPaidTraffic($data);
        $googleClick = GoogleClickAttribution::resolve($data);
        $visitedAt = isset($data['ts']) && is_numeric($data['ts'])
            ? UserTimezone::parseInstant($data['ts'])
            : UserTimezone::nowUtc();
        $sessionId = (string) ($request->input('session_id') ?: $request->cookie(config('session.cookie', 'laravel_session')) ?: $request->session()->getId());
        $sessionId = $sessionId !== '' ? $sessionId : null;

        // Log IP and run fraud protection (sync intel + block repeat offenders).
        $protection = app(VisitProtectionService::class);
        $ipLog = $protection->touchIpLog($ip, $ua, $data['path'] ?? null, $data['referrer'] ?? null);
        $assessment = $protection->assess($domain, $ipLog, $country, $sessionId, $isCrawler, $isPaidTraffic, $visitedAt);
        $ipLog = $assessment['ipLog'];
        $detection = $assessment['detection'];
        $enforceBlock = $assessment['enforce_block'];
        $captchaRequired = $protection->shouldEnforceCaptcha($domain, $detection, $ip);
        $skipVisitLog = $protection->shouldSkipOrganicRepeatVisit($domain, $sessionId, $isPaidTraffic, $visitedAt);

        $resolvedCountry = $country ?? $ipLog->intel_country_code ?? $ipLog->intel_country_name;
        $visitCountryCode = CountryValue::forVisitsTable($ipLog, $country);
        $displayCountry = CountryValue::forDisplay($ipLog, $country);
        $domain->last_seen_at = UserTimezone::nowUtc();
        $domain->tag_connected = true;
        $domain->status = 'connected';
        if ($isPaidTraffic) {
            $domain->paid_marketing_connected = true;
        }
        if ($detection['action_taken'] !== 'allow' || $isCrawler) {
            $domain->bot_mitigation_connected = true;
        }
        $domain->save();

        $campaignAttribution = CampaignAttributionResolver::resolve($domain, $data);

        $visitId = null;
        DB::transaction(function () use (
            &$visitId,
            $domain,
            $data,
            $ip,
            $ua,
            $browser,
            $os,
            $device,
            $isCrawler,
            $isPaidTraffic,
            $googleClick,
            $visitedAt,
            $sessionId,
            $skipVisitLog,
            $detection,
            $displayCountry,
            $visitCountryCode,
            $campaignAttribution,
        ): void {
        if ($isPaidTraffic) {
            // Paid marketing funnel: Google click IDs (gclid / gbraid / wbraid) only.
            $paidId = (string) ($googleClick['id'] ?? '');
            $skipPaidClickRow = $paidId !== '' && $this->paidClickIdExists($domain->id, $paidId);

            $visit = PaidMarketingVisit::firstOrNew([
                'domain_id' => $domain->id,
                'ip' => $ip,
            ]);
            if (! $visit->exists) {
                $visit->visits = 0;
            }
            $visit->visits = ($visit->visits ?? 0) + 1;
            $visit->last_click_at = $visitedAt;
            $visit->last_path = $data['path'] ?? null;
            $visit->campaign = $campaignAttribution['campaign'];
            $visit->platform = $device;
            $visit->country = $displayCountry;
            $visit->threat_group = $detection['threat_group'];
            $visit->threat_type = $detection['action_taken'] === 'allow' ? null : $detection['action_taken'];
            if (Schema::hasColumn('paid_marketing_visits', 'google_campaign_id')) {
                $visit->google_campaign_id = $campaignAttribution['google_campaign_id'];
            }
            if (Schema::hasColumn('paid_marketing_visits', 'campaign_name')) {
                $visit->campaign_name = $campaignAttribution['campaign_name'];
            }
            $visit->save();

            if (! $skipPaidClickRow) {
                $clickPayload = [
                    'paid_marketing_visit_id' => $visit->id,
                    'clicked_at' => $visitedAt,
                    'ip' => $ip,
                    'country' => $displayCountry,
                    'last_click_at' => $visitedAt,
                    'threat_group' => $detection['threat_group'],
                    'campaign' => $campaignAttribution['campaign'],
                    'paid_id' => $paidId !== '' ? $paidId : ($data['gclid'] ?? null),
                    'path' => $data['url'] ?? ($data['path'] ?? null),
                    'keyword' => $data['utm_term'] ?? ($data['keyword'] ?? null),
                    'browser_name' => $browser['name'],
                    'browser_version' => $browser['version'],
                    'os' => $os,
                ];
                if (Schema::hasColumn('paid_marketing_clicks', 'google_campaign_id')) {
                    $clickPayload['google_campaign_id'] = $campaignAttribution['google_campaign_id'];
                }
                if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                    $clickPayload['campaign_name'] = $campaignAttribution['campaign_name'];
                }
                if (Schema::hasColumn('paid_marketing_clicks', 'click_source')) {
                    $clickPayload['click_source'] = $data['click_source'] ?? 'tag';
                }

                PaidMarketingClick::create($clickPayload);
            }
        }

        if (Schema::hasTable('visits') && ! $skipVisitLog) {
            $visitPayload = [
                'domain_id' => $domain->id,
                'session_id' => $sessionId,
                'ip' => $ip,
                'country' => $visitCountryCode,
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
                'created_at' => UserTimezone::nowUtc(),
                'updated_at' => UserTimezone::nowUtc(),
            ];

            if (Schema::hasColumn('visits', 'gclid')) {
                $visitPayload['gclid'] = $data['gclid'] ?? null;
            }
            if (Schema::hasColumn('visits', 'gbraid')) {
                $visitPayload['gbraid'] = $data['gbraid'] ?? null;
            }
            if (Schema::hasColumn('visits', 'wbraid')) {
                $visitPayload['wbraid'] = $data['wbraid'] ?? null;
            }
            if (Schema::hasColumn('visits', 'google_click_type')) {
                $visitPayload['google_click_type'] = $googleClick['type'] ?? null;
            }
            if (Schema::hasColumn('visits', 'google_campaign_id')) {
                $visitPayload['google_campaign_id'] = $campaignAttribution['google_campaign_id'];
            }
            if (Schema::hasColumn('visits', 'campaign_name')) {
                $visitPayload['campaign_name'] = $campaignAttribution['campaign_name'];
            }

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
            if (Schema::hasColumn('visits', 'click_source')) {
                $visitPayload['click_source'] = $data['click_source'] ?? 'tag';
            }
            if (Schema::hasColumn('visits', 'ad_click_meta') && ($data['ad_click_meta'] ?? []) !== []) {
                $visitPayload['ad_click_meta'] = json_encode($data['ad_click_meta']);
            }

            $visitId = DB::table('visits')->insertGetId($visitPayload);
        }
        });

        if ($sessionId !== null && Schema::hasTable('ip_sessions') && ! $skipVisitLog) {
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
                        'updated_at' => UserTimezone::nowUtc(),
                    ]);
            } else {
                DB::table('ip_sessions')->insert([
                    'domain_id' => $domain->id,
                    'session_id' => $sessionId,
                    'ip' => $ip,
                    'hits' => 1,
                    'last_seen_at' => $visitedAt,
                    'created_at' => UserTimezone::nowUtc(),
                    'updated_at' => UserTimezone::nowUtc(),
                ]);
            }
        }

        if (Schema::hasTable('analytics_hourly') && ! $skipVisitLog) {
            $domain->loadMissing('user');
            $ownerTz = UserTimezone::forUser($domain->user);
            $bucketHour = $visitedAt->copy()->timezone($ownerTz)->startOfHour()->utc();
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
                        'updated_at' => UserTimezone::nowUtc(),
                    ]);
            } else {
                DB::table('analytics_hourly')->insert([
                    'domain_id' => $domain->id,
                    'bucket_hour' => $bucketHour,
                    'total_visits' => 1,
                    'paid_visits' => $isPaidTraffic ? 1 : 0,
                    'invalid_visits' => $detection['action_taken'] !== 'allow' ? 1 : 0,
                    'created_at' => UserTimezone::nowUtc(),
                    'updated_at' => UserTimezone::nowUtc(),
                ]);
            }
        }

        if (Schema::hasTable('detection_logs') && $detection['action_taken'] !== 'allow' && ! $skipVisitLog) {
            DB::table('detection_logs')->insert([
                'domain_id' => $domain->id,
                'visit_id' => $visitId,
                'ip' => $ip,
                'threat_score' => $detection['threat_score'],
                'threat_group' => $detection['threat_group'],
                'action_taken' => $detection['action_taken'],
                'reasons' => json_encode($detection['reasons']),
                'detected_at' => $visitedAt,
                'created_at' => UserTimezone::nowUtc(),
                'updated_at' => UserTimezone::nowUtc(),
            ]);
        }

        if (
            $detection['action_taken'] === 'block'
            && ! $protection->isAllowListed($domain, $ip)
            && $isPaidTraffic
        ) {
            app(GoogleAudienceExclusionService::class)->queueBlockedIpIfEligible(
                $domain,
                $ip,
                $detection['threat_group'] ?? null,
                isPaidTraffic: true,
            );
        }

        $clientPayload = $protection->clientPayload(
            $detection,
            $enforceBlock,
            $captchaRequired,
            $this->shouldRecordSession($domain, $detection),
            $visitId,
        );

        if ($request->isMethod('get')) {
            return $this->cors(
                $request,
                response(self::TRACKING_PIXEL_GIF, 200, [
                    'Content-Type' => 'image/gif',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ])
            );
        }

        return $this->cors($request, response()->json(['ok' => true] + $clientPayload));
    }

    public function sessionRecording(Request $request)
    {
        if ($request->isMethod('options')) {
            return $this->cors($request, response()->noContent());
        }

        $data = Validator::make($request->all(), [
            'domainKey' => ['required', 'string'],
            'session_id' => ['nullable', 'string', 'max:128'],
            'visit_id' => ['nullable', 'integer'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'duration_ms' => ['nullable', 'integer', 'max:15000'],
            'threat_group' => ['nullable', 'string', 'max:40'],
            'events' => ['required', 'array', 'max:500'],
        ])->validate();

        if (! Schema::hasTable('visit_session_recordings')) {
            return $this->cors($request, response()->json(['ok' => true, 'skipped' => true]));
        }

        $domain = Domain::where('domain_key', $data['domainKey'])->firstOrFail();
        $ip = $this->clientIp($request);
        $events = array_slice((array) $data['events'], 0, 500);

        DB::table('visit_session_recordings')->insert([
            'domain_id' => $domain->id,
            'visit_id' => $data['visit_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'ip' => $ip,
            'threat_group' => $data['threat_group'] ?? null,
            'duration_ms' => min((int) ($data['duration_ms'] ?? 0), 15000),
            'page_url' => $data['page_url'] ?? null,
            'events' => json_encode($events),
            'created_at' => UserTimezone::nowUtc(),
            'updated_at' => UserTimezone::nowUtc(),
        ]);

        return $this->cors($request, response()->json(['ok' => true]));
    }

    /** @param  array{threat_group: ?string, action_taken?: string}  $detection */
    private function shouldRecordSession(Domain $domain, array $detection): bool
    {
        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        if ($settings === null || ! $settings->session_recordings) {
            return false;
        }

        if (($detection['action_taken'] ?? 'allow') === 'allow') {
            return false;
        }

        $group = strtolower((string) ($detection['threat_group'] ?? ''));

        return $group === 'malicious'
            || in_array($group, ['vpn', 'proxy', 'data_center', 'datacenter', 'abnormal_rate_limit'], true);
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

    private function paidClickIdExists(int $domainId, string $paidId): bool
    {
        if ($paidId === '' || ! Schema::hasTable('paid_marketing_clicks')) {
            return false;
        }

        return DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->where('pv.domain_id', $domainId)
            ->where('pc.paid_id', $paidId)
            ->exists();
    }

}

