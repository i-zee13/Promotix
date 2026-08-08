<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClientIp;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Services\GoogleAudienceExclusionService;
use App\Services\IpIntel\VisitProtectionService;
use App\Models\IpLog;
use App\Support\CampaignAttributionResolver;
use App\Support\CountryValue;
use App\Support\DetectionPlanFeatures;
use App\Support\DetectionProfiles;
use App\Support\GoogleAdsClickRedirect;
use App\Support\GoogleClickAttribution;
use App\Support\SessionBehaviorAnalyzer;
use App\Support\SessionBehaviorFingerprint;
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
     * Google Ads tracking template entry (ClickRonix-style).
     * Public GET, no login, never blocks Google: capture → redirect to Final URL.
     * Saves only when gclid / gbraid / wbraid is present.
     */
    public function googleAdsClick(Request $request): Response
    {
        $params = GoogleAdsClickRedirect::parseClickRequest($request);
        $finalUrl = (string) ($params['final_url'] ?? '');

        if ($finalUrl === '') {
            return response('Missing final_url', 400);
        }

        $redirectUrl = GoogleAdsClickRedirect::buildRedirectUrl($finalUrl, $params);

        // Rule: save when Google click ID exists, or campaign_id matches a synced Ads campaign.
        $domain = GoogleAdsClickRedirect::resolveDomainFromFinalUrl($finalUrl);
        if (
            $domain
            && GoogleClickAttribution::isPaidTraffic($params, (int) $domain->id)
            && GoogleAdsClickRedirect::isAllowedFinalUrl($finalUrl, $domain)
            && ($domain->status ?? 'pending') !== 'disabled'
        ) {
            try {
                $this->ingestGoogleAdsServerClick($request, $domain, $params, $finalUrl);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Always send the visitor to the landing page (never block Google / never 204 here).
        return redirect()->away($redirectUrl, 302);
    }

    /**
     * Fast paid-only server capture for /click — skips fraud blocks and bot gates.
     *
     * @param  array<string, mixed>  $params
     */
    private function ingestGoogleAdsServerClick(Request $request, Domain $domain, array $params, string $finalUrl): void
    {
        $landingUrl = GoogleAdsClickRedirect::buildRedirectUrl($finalUrl, $params);
        $path = (string) (parse_url($finalUrl, PHP_URL_PATH) ?: '/');

        $data = [
            'gclid' => $params['gclid'] ?: null,
            'gbraid' => $params['gbraid'] ?: null,
            'wbraid' => $params['wbraid'] ?: null,
            'gad_campaignid' => $params['gad_campaignid'] ?: null,
            'campaign_id' => $params['campaign_id'] ?: null,
            'adgroup_id' => $params['adgroup_id'] ?: null,
            'keyword' => $params['keyword'] ?: null,
            'device' => $params['device'] ?: null,
            'network' => $params['network'] ?: null,
            'url' => $landingUrl,
            'path' => $path,
            'utm_term' => $params['keyword'] ?: null,
            'utm_source' => ($params['source'] ?? '') === 'google_ads' ? 'google' : null,
            'utm_medium' => ($params['source'] ?? '') === 'google_ads' ? 'cpc' : null,
            'click_source' => 'server',
            'ad_click_meta' => GoogleAdsClickRedirect::adClickMeta($params),
        ];

        $ip = $this->clientIp($request);
        $ua = $request->userAgent() ?? '';
        $browser = $this->browserFromUa($ua);
        $os = $this->osFromUa($ua);
        $device = $this->platformFromUa($ua);
        $countryHeader = $request->headers->get('CF-IPCountry') ?: null;
        $googleClick = GoogleClickAttribution::resolve($data);
        $paidId = (string) ($googleClick['id'] ?? '');
        $visitedAt = UserTimezone::nowUtc();
        $campaignAttribution = CampaignAttributionResolver::resolve($domain, $data);
        $displayCountry = CountryValue::forDisplay(null, $countryHeader);
        $visitCountryCode = CountryValue::forVisitsTable(null, $countryHeader);

        $domain->last_seen_at = $visitedAt;
        $domain->paid_marketing_connected = true;
        if (($domain->status ?? 'pending') !== 'disabled') {
            $domain->status = 'connected';
        }
        $domain->save();

        DB::transaction(function () use (
            $domain,
            $data,
            $ip,
            $ua,
            $browser,
            $os,
            $device,
            $googleClick,
            $paidId,
            $visitedAt,
            $displayCountry,
            $visitCountryCode,
            $campaignAttribution,
        ): void {
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
            $visit->threat_group = null;
            $visit->threat_type = null;
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
                    'threat_group' => null,
                    'campaign' => $campaignAttribution['campaign'],
                    'paid_id' => $paidId,
                    'path' => $data['url'] ?? ($data['path'] ?? null),
                    'keyword' => $data['keyword'] ?? null,
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
                    $clickPayload['click_source'] = 'server';
                }

                PaidMarketingClick::create($clickPayload);
            }

            if (Schema::hasTable('visits')) {
                $visitPayload = [
                    'domain_id' => $domain->id,
                    'session_id' => null,
                    'ip' => $ip,
                    'country' => $visitCountryCode,
                    'device' => $device,
                    'browser' => $browser['name'],
                    'os' => $os,
                    'url' => $data['url'] ?? null,
                    'referrer' => 'https://www.google.com/',
                    'utm_source' => $data['utm_source'] ?? null,
                    'utm_medium' => $data['utm_medium'] ?? null,
                    'utm_campaign' => null,
                    'utm_term' => $data['utm_term'] ?? ($data['keyword'] ?? null),
                    'is_paid_traffic' => true,
                    'is_invalid_traffic' => false,
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
                    $visitPayload['threat_score'] = 0;
                    $visitPayload['threat_group'] = null;
                    $visitPayload['action_taken'] = 'allow';
                    $visitPayload['detection_reasons'] = json_encode([]);
                }
                if (Schema::hasColumn('visits', 'user_agent')) {
                    $visitPayload['user_agent'] = $ua;
                }
                if (Schema::hasColumn('visits', 'is_crawler')) {
                    $visitPayload['is_crawler'] = false;
                }
                if (Schema::hasColumn('visits', 'click_source')) {
                    $visitPayload['click_source'] = 'server';
                }
                if (Schema::hasColumn('visits', 'tracking_confidence')) {
                    $visitPayload['tracking_confidence'] = 'high';
                }
                if (Schema::hasColumn('visits', 'ad_click_meta') && ($data['ad_click_meta'] ?? []) !== []) {
                    $visitPayload['ad_click_meta'] = json_encode($data['ad_click_meta']);
                }

                DB::table('visits')->insert($visitPayload);
            }
        });
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
            'campaign_id' => ['nullable', 'string'],
            'campaignid' => ['nullable', 'string'],
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
            $data['click_source'] = $request->isMethod('get') ? 'pixel' : 'tag';
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
        $isPaidTraffic = GoogleClickAttribution::isPaidTraffic($data, (int) $domain->id);
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

        $paidId = (string) ($googleClick['id'] ?? '');
        $duplicatePaidClick = $isPaidTraffic
            && $paidId !== ''
            && $this->paidClickIdExists($domain->id, $paidId);

        $trackingConfidence = $this->resolveTrackingConfidence((string) ($data['click_source'] ?? 'tag'));

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
            $paidId,
            $duplicatePaidClick,
            $enforceBlock,
            $trackingConfidence,
        ): void {
        if ($isPaidTraffic) {
            // Paid marketing funnel: Google click IDs (gclid / gbraid / wbraid) only.
            $skipPaidClickRow = $duplicatePaidClick;

            $visit = PaidMarketingVisit::firstOrNew([
                'domain_id' => $domain->id,
                'ip' => $ip,
            ]);
            if (! $visit->exists) {
                $visit->visits = 0;
            }
            // Unique gclid/gbraid/wbraid counts once; duplicate hits still update latest metadata.
            if (! $skipPaidClickRow) {
                $visit->visits = ($visit->visits ?? 0) + 1;
            }
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
            $reasons = $detection['reasons'];
            if ($duplicatePaidClick) {
                $reasons[] = 'DUPLICATE_PAID_CLICK';
                $reasons = array_values(array_unique($reasons));
            }

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
                'is_invalid_traffic' => $detection['action_taken'] !== 'allow' || $duplicatePaidClick,
                'visited_at' => $visitedAt,
                'created_at' => UserTimezone::nowUtc(),
                'updated_at' => UserTimezone::nowUtc(),
            ];

            if (Schema::hasColumn('visits', 'is_duplicate_paid_click')) {
                $visitPayload['is_duplicate_paid_click'] = $duplicatePaidClick;
            }

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
                $visitPayload['action_taken'] = $duplicatePaidClick && $detection['action_taken'] === 'allow'
                    ? 'flag'
                    : $detection['action_taken'];
                $visitPayload['detection_reasons'] = json_encode($reasons);
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
            if (Schema::hasColumn('visits', 'tracking_confidence')) {
                $visitPayload['tracking_confidence'] = $trackingConfidence;
            }
            if (Schema::hasColumn('visits', 'block_enforced')) {
                $visitPayload['block_enforced'] = $enforceBlock && $isPaidTraffic;
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
            $this->shouldRecordSession($domain, $detection, $isPaidTraffic),
            $visitId,
            $domain,
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
        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        $thresholds = DetectionProfiles::thresholdsFor(
            $settings?->detection_profile,
            is_array($settings?->detection_thresholds) ? $settings->detection_thresholds : null,
        );
        $behaviorOn = (bool) ($thresholds['behavior_control_enabled'] ?? false);
        $domain->loadMissing('user');
        if (! DetectionPlanFeatures::enabled($domain->user, DetectionPlanFeatures::BEHAVIOR_CONTROL)) {
            $behaviorOn = false;
        }

        $ip = $this->clientIp($request);
        $events = array_slice((array) $data['events'], 0, 500);
        $durationMs = min((int) ($data['duration_ms'] ?? 0), 15000);
        $analysis = SessionBehaviorAnalyzer::analyze($events, $durationMs);
        $signals = $analysis['signals'];
        $fingerprint = SessionBehaviorFingerprint::fromEvents($events, $durationMs);

        // Soft signal when the same IP/domain recently repeated this low-human pattern.
        $repeatScore = 0;
        if ($fingerprint !== '' && Schema::hasColumn('visit_session_recordings', 'behavior_fingerprint')) {
            $repeatScore = (int) DB::table('visit_session_recordings')
                ->where('domain_id', $domain->id)
                ->where('ip', $ip)
                ->where('behavior_fingerprint', $fingerprint)
                ->where('created_at', '>=', UserTimezone::nowUtc()->subDays(7))
                ->count();
        }

        $priorIdle = 0;
        if (
            $behaviorOn
            && in_array(SessionBehaviorAnalyzer::NO_INTERACTION, $signals, true)
            && Schema::hasColumn('visit_session_recordings', 'behavior_signals')
        ) {
            $priorIdle = (int) DB::table('visit_session_recordings')
                ->where('domain_id', $domain->id)
                ->where('ip', $ip)
                ->where('created_at', '>=', UserTimezone::nowUtc()->subDays(7))
                ->where('behavior_signals', 'like', '%'.SessionBehaviorAnalyzer::NO_INTERACTION.'%')
                ->count();
        }

        $behaviorAction = 'allow';
        if ($behaviorOn) {
            if (in_array(SessionBehaviorAnalyzer::NO_INTERACTION, $signals, true) && $priorIdle >= 1) {
                $signals[] = 'IDLE_RETURN_BLOCK';
                $behaviorAction = 'block';
            } elseif ($analysis['scroll_count'] > 0 && $repeatScore >= 2) {
                $signals[] = 'SCROLL_PATTERN_BLOCK';
                $signals[] = 'REPEATED_BEHAVIOR';
                $behaviorAction = 'block';
            } elseif ($analysis['scroll_count'] > 0 && $repeatScore >= 1) {
                $signals[] = 'SCROLL_PATTERN_REPEAT';
                $signals[] = 'REPEATED_BEHAVIOR';
                $behaviorAction = 'flag';
            }
        } elseif ($repeatScore >= 1) {
            $signals[] = 'REPEATED_BEHAVIOR';
            $behaviorAction = 'flag';
        }

        $signals = array_values(array_unique($signals));

        $payload = [
            'domain_id' => $domain->id,
            'visit_id' => $data['visit_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'ip' => $ip,
            'threat_group' => $data['threat_group'] ?? null,
            'duration_ms' => $durationMs,
            'page_url' => $data['page_url'] ?? null,
            'events' => json_encode($events),
            'created_at' => UserTimezone::nowUtc(),
            'updated_at' => UserTimezone::nowUtc(),
        ];

        if (Schema::hasColumn('visit_session_recordings', 'behavior_signals')) {
            $payload['behavior_signals'] = json_encode($signals);
        }
        if (Schema::hasColumn('visit_session_recordings', 'behavior_fingerprint')) {
            $payload['behavior_fingerprint'] = $fingerprint;
        }
        if (Schema::hasColumn('visit_session_recordings', 'cta_clicks')) {
            $payload['cta_clicks'] = min(65535, (int) $analysis['cta_clicks']);
        }
        if (Schema::hasColumn('visit_session_recordings', 'tel_clicks')) {
            $payload['tel_clicks'] = min(65535, (int) $analysis['tel_clicks']);
        }
        if (Schema::hasColumn('visit_session_recordings', 'page_changes')) {
            $payload['page_changes'] = min(65535, (int) $analysis['page_changes']);
        }
        if (Schema::hasColumn('visit_session_recordings', 'scroll_count')) {
            $payload['scroll_count'] = min(65535, (int) $analysis['scroll_count']);
        }
        if (Schema::hasColumn('visit_session_recordings', 'last_cta_href') && ! empty($analysis['last_cta_href'])) {
            $payload['last_cta_href'] = (string) $analysis['last_cta_href'];
        }

        DB::table('visit_session_recordings')->insert($payload);

        // Attach behavior signals / actions to the visit record when present.
        if (
            ($signals !== [] || $behaviorAction !== 'allow')
            && ! empty($data['visit_id'])
            && Schema::hasTable('visits')
            && Schema::hasColumn('visits', 'detection_reasons')
        ) {
            $visit = DB::table('visits')->where('id', (int) $data['visit_id'])->where('domain_id', $domain->id)->first();
            if ($visit) {
                $existing = json_decode((string) ($visit->detection_reasons ?? '[]'), true);
                if (! is_array($existing)) {
                    $existing = [];
                }
                $merged = array_values(array_unique(array_merge($existing, $signals)));
                $update = [
                    'detection_reasons' => json_encode($merged),
                    'updated_at' => UserTimezone::nowUtc(),
                ];

                if ($behaviorAction === 'block') {
                    if (Schema::hasColumn('visits', 'threat_score')) {
                        $update['threat_score'] = max((int) ($visit->threat_score ?? 0), 70);
                    }
                    $update['action_taken'] = 'block';
                    $update['is_invalid_traffic'] = true;
                    if (
                        Schema::hasColumn('visits', 'threat_group')
                        && empty($visit->threat_group)
                    ) {
                        $update['threat_group'] = 'abnormal_rate_limit';
                    }
                } elseif (
                    $behaviorAction === 'flag'
                    && Schema::hasColumn('visits', 'threat_score')
                ) {
                    $update['threat_score'] = max((int) ($visit->threat_score ?? 0), 30);
                    if (($visit->action_taken ?? 'allow') === 'allow') {
                        $update['action_taken'] = 'flag';
                        $update['is_invalid_traffic'] = true;
                    }
                }

                DB::table('visits')->where('id', $visit->id)->update($update);

                if ($behaviorAction === 'block' && Schema::hasTable('ip_logs')) {
                    $ipLog = IpLog::query()->firstOrCreate(
                        ['ip' => $ip],
                        ['is_blocked' => false]
                    );
                    if (! $ipLog->is_blocked) {
                        $ipLog->is_blocked = true;
                        $ipLog->save();
                    }

                    if ((bool) ($visit->is_paid_traffic ?? false)) {
                        app(GoogleAudienceExclusionService::class)->queueBlockedIpIfEligible(
                            $domain,
                            $ip,
                            $update['threat_group'] ?? ($visit->threat_group ?? 'abnormal_rate_limit'),
                            isPaidTraffic: true,
                        );
                    }
                }
            }
        }

        return $this->cors($request, response()->json([
            'ok' => true,
            'signals' => $signals,
            'fingerprint' => $fingerprint,
            'prior_matches' => $repeatScore,
            'prior_idle' => $priorIdle,
            'behavior_action' => $behaviorAction,
            'cta_clicks' => $analysis['cta_clicks'],
            'tel_clicks' => $analysis['tel_clicks'],
            'page_changes' => $analysis['page_changes'],
        ]));
    }

    /** @param  array{threat_group: ?string, action_taken?: string}  $detection */
    private function shouldRecordSession(Domain $domain, array $detection, bool $isPaidTraffic = false): bool
    {
        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        if ($settings === null) {
            return false;
        }

        $thresholds = DetectionProfiles::thresholdsFor(
            $settings->detection_profile,
            is_array($settings->detection_thresholds) ? $settings->detection_thresholds : null,
        );

        // Behavior Control: record paid clicks so idle/scroll rules can fire on return visits.
        if (
            $isPaidTraffic
            && (bool) ($thresholds['behavior_control_enabled'] ?? false)
            && DetectionPlanFeatures::enabled(
                $domain->relationLoaded('user') ? $domain->user : $domain->user()->first(),
                DetectionPlanFeatures::BEHAVIOR_CONTROL
            )
        ) {
            return true;
        }

        if (
            ! $settings->session_recordings
            || ! DetectionPlanFeatures::enabled(
                $domain->relationLoaded('user') ? $domain->user : $domain->user()->first(),
                DetectionPlanFeatures::SESSION_RECORDINGS
            )
        ) {
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

    private function resolveTrackingConfidence(string $clickSource): string
    {
        return in_array($clickSource, ['noscript', 'pixel'], true) ? 'reduced' : 'high';
    }

}

