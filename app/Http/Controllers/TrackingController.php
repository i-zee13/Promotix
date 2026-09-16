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
use App\Support\BehaviorEventPersister;
use App\Support\DetectionPlanFeatures;
use App\Support\DetectionProfiles;
use App\Support\DomainKeyHostGuard;
use App\Support\GoogleAdsClickRedirect;
use App\Support\GoogleClickAttribution;
use App\Support\TransparentClickTracker;
use App\Support\PaidAdvertising\PaidAdvertisingPipeline;
use App\Support\PaidAdvertising\ResolvedPaidIdentity;
use App\Support\SessionBehaviorAnalyzer;
use App\Support\SessionBehaviorFingerprint;
use App\Support\SessionRecordingGate;
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
            return response('Missing redirect (or final_url)', 400);
        }

        $cxtrkId = TransparentClickTracker::mintId();
        $params['cxtrk'] = $cxtrkId;
        $redirectUrl = GoogleAdsClickRedirect::buildRedirectUrl($finalUrl, $params);

        try {
            $domain = GoogleAdsClickRedirect::resolveDomainFromFinalUrl($finalUrl);
            TransparentClickTracker::record(
                $request,
                $params,
                $finalUrl,
                $cxtrkId,
                $domain?->id,
                $this->clientIp($request),
            );

            // Rule: save only when a Google click ID exists.
            if (
                $domain
                && GoogleClickAttribution::isPaidTraffic($params, (int) $domain->id)
                && GoogleAdsClickRedirect::isAllowedFinalUrl($finalUrl, $domain)
                && ($domain->status ?? 'pending') !== 'disabled'
            ) {
                $this->ingestGoogleAdsServerClick($request, $domain, $params, $finalUrl);
            }
        } catch (\Throwable $e) {
            report($e);
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
            'cxtrk' => $params['cxtrk'] ?? null,
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

            if (Schema::hasColumn('visits', 'browser_version')) {
                $visitPayload['browser_version'] = $browser['version'];
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
            'utm_term' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string'],
            'session_id' => ['nullable', 'string', 'max:128'],
            'fingerprint' => ['nullable', 'string', 'max:512'],
            'fingerprint_signals' => ['nullable'],
            'device_token' => ['nullable', 'string', 'max:80'],
            'ga4_client_id' => ['nullable', 'string', 'max:128'],
            'ts' => ['nullable', 'numeric'],
            'click_source' => ['nullable', 'string', 'max:16'],
            'ad_click_meta' => ['nullable'],
            'cxtrk' => ['nullable', 'string', 'max:32'],
        ])->validate();

        if (isset($data['ad_click_meta']) && is_string($data['ad_click_meta'])) {
            $decoded = json_decode($data['ad_click_meta'], true);
            $data['ad_click_meta'] = is_array($decoded) ? $decoded : [];
        }
        if (! isset($data['ad_click_meta']) || ! is_array($data['ad_click_meta'])) {
            $data['ad_click_meta'] = [];
        }
        $data['fingerprint'] = (string) ($data['fingerprint'] ?? $request->input('fingerprint') ?? '');
        $data['fingerprint_signals'] = \App\Support\DeviceFingerprintCatalog::sanitize(
            $data['fingerprint_signals'] ?? $request->input('fingerprint_signals')
        );
        if (! filled($data['click_source'] ?? null)) {
            $data['click_source'] = $request->isMethod('get') ? 'pixel' : 'tag';
        }

        $domain = Domain::where('domain_key', $data['domainKey'])->firstOrFail();
        if (($domain->status ?? 'pending') === 'disabled') {
            return $this->cors($request, response()->json(['ok' => true, 'skipped' => 'disabled']));
        }

        $hostMismatch = DomainKeyHostGuard::mismatchReason(
            $request,
            $domain,
            (string) ($data['url'] ?? $data['page_url'] ?? '')
        );
        if ($hostMismatch !== null) {
            return $this->cors($request, response()->json([
                'ok' => false,
                'error' => 'hostname_mismatch',
                'message' => $hostMismatch,
            ], 403));
        }

        $ip = $this->clientIp($request);
        $ua = $request->userAgent() ?? '';
        $browser = $this->browserFromUa($ua);
        $os = $this->osFromUa($ua);
        $country = $request->headers->get('CF-IPCountry') ?: null;
        $device = $this->platformFromUa($ua);
        $fpSignals = is_array($data['fingerprint_signals'] ?? null) ? $data['fingerprint_signals'] : [];
        if (filled($fpSignals['browser_family'] ?? null)) {
            $browser['name'] = (string) $fpSignals['browser_family'];
        }
        if (filled($fpSignals['browser_major'] ?? null)) {
            $browser['version'] = (string) $fpSignals['browser_major'];
        }
        if (filled($fpSignals['os_version'] ?? null)) {
            $os = (string) $fpSignals['os_version'];
        } elseif (filled($fpSignals['os_family'] ?? null)) {
            $os = (string) $fpSignals['os_family'];
        }
        if (filled($fpSignals['device_type'] ?? null)) {
            $device = (string) $fpSignals['device_type'];
        }
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
        $botEnabled = (bool) ($domain->bot_mitigation_connected ?? false);
        $paidEnabled = (bool) ($domain->paid_marketing_connected ?? false) || $domain->hasGoogleAdsConnection();
        $assessment = $protection->assess($domain, $ipLog, $country, $sessionId, $isCrawler, $isPaidTraffic, $visitedAt);
        $ipLog = $assessment['ipLog'];
        $detection = $assessment['detection'];
        // Bot Protection product off → never block/captcha (Google Tag / Ads-only must not activate bot).
        $enforceBlock = $botEnabled
            ? $assessment['enforce_block']
            : false;
        $captchaRequired = $botEnabled
            ? $protection->shouldEnforceCaptcha($domain, $detection, $ip)
            : false;
        if (! $botEnabled) {
            $detection = array_merge($detection, [
                'action_taken' => 'allow',
                'threat_score' => 0,
                'threat_group' => null,
                'reasons' => ['bot_protection_off'],
            ]);
        }
        $skipVisitLog = $protection->shouldSkipOrganicRepeatVisit($domain, $sessionId, $isPaidTraffic, $visitedAt);

        $paidId = (string) ($googleClick['id'] ?? '');
        $priorPaidClick = ($isPaidTraffic && $paidEnabled && $paidId !== '')
            ? $this->findPaidClickById($domain->id, $paidId)
            : null;
        // Same Google click often lands twice: /click server ingest, then website tag.
        // That is NOT click-id fraud — only treat as ADS_GCLID_DUP when it looks like a real replay.
        $sameClickContinuation = $priorPaidClick !== null
            && $this->isSamePaidClickContinuation($priorPaidClick, $ip, (string) ($data['click_source'] ?? 'tag'), $visitedAt);
        $duplicatePaidClick = $priorPaidClick !== null && ! $sameClickContinuation;
        $allowListedIp = $protection->isAllowListed($domain, $ip);

        $paidIdentity = null;
        $adsDetections = [];
        $ipExclusionEligible = false;
        if ($isPaidTraffic && $paidEnabled && ! $allowListedIp) {
            try {
                $pipeline = app(PaidAdvertisingPipeline::class);
                $clientFp = (string) ($data['fingerprint'] ?? $request->input('fingerprint') ?: (
                    $data['behavior_fingerprint'] ?? $request->input('behavior_fingerprint') ?: ''
                ));
                $clientFp = $clientFp !== '' ? $clientFp : null;
                $paidEnrichment = $pipeline->enrichPaidDetection(
                    $request,
                    $domain,
                    $ip,
                    $sessionId,
                    $detection,
                    $clientFp,
                    [
                        'paid_id' => $paidId !== '' ? $paidId : null,
                        'click_type' => $googleClick['type'] ?? null,
                        'duplicate_paid_click' => $duplicatePaidClick,
                        'is_paid_traffic' => true,
                        'device_token' => trim((string) ($data['device_token'] ?? $request->input('device_token') ?: '')),
                    ],
                );
                $paidIdentity = $paidEnrichment['identity'];
                $adsDetections = $paidEnrichment['detections'];
                $detection = $paidEnrichment['detection'];
                $ipExclusionEligible = (bool) ($paidEnrichment['exclusion']['eligible'] ?? false);
                if (($detection['action_taken'] ?? '') === 'block') {
                    $enforceBlock = $protection->shouldEnforceBlock($domain, $detection, $isPaidTraffic, $ip);
                }
                $captchaRequired = $protection->shouldEnforceCaptcha($domain, $detection, $ip);
                foreach ($pipeline->cookiesFor($paidIdentity) as $cookie) {
                    cookie()->queue(cookie(
                        $cookie['name'],
                        $cookie['value'],
                        $cookie['minutes'],
                        '/',
                        null,
                        $request->isSecure(),
                        false,
                        false,
                        'Lax'
                    ));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $resolvedCountry = $country ?? $ipLog->intel_country_code ?? $ipLog->intel_country_name;
        $visitCountryCode = CountryValue::forVisitsTable($ipLog, $country);
        $displayCountry = CountryValue::forDisplay($ipLog, $country);
        $domain->last_seen_at = UserTimezone::nowUtc();
        $domain->tag_connected = true;
        $domain->status = 'connected';
        // Clickronix script only. Do NOT imply Bot Protection or Paid Marketing from a tag ping.
        // Bot Protection: domains toggle / detection setup sets bot_mitigation_connected.
        // Paid Marketing: Ads link or /click tracker — never a lone organic tag hit.
        if ($isPaidTraffic && $domain->hasGoogleAdsConnection()) {
            $domain->paid_marketing_connected = true;
        }
        $domain->save();

        $campaignAttribution = CampaignAttributionResolver::resolve($domain, $data);

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
            $sameClickContinuation,
            $priorPaidClick,
            $allowListedIp,
            $enforceBlock,
            $trackingConfidence,
            $paidIdentity,
            $adsDetections,
            $paidEnabled,
            $botEnabled,
        ): void {
        if ($isPaidTraffic && $paidEnabled) {
            // Paid marketing funnel only when Ads/paid product is connected for this domain.
            // Skip a second paid_marketing_clicks row for the same click id (server→tag is one click).
            $skipPaidClickRow = $priorPaidClick !== null;

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
                if (Schema::hasColumn('paid_marketing_clicks', 'device_id') && $paidIdentity instanceof ResolvedPaidIdentity) {
                    $clickPayload['device_id'] = $paidIdentity->deviceId;
                }
                if (Schema::hasColumn('paid_marketing_clicks', 'ga4_client_id')) {
                    $ga4 = trim((string) ($data['ga4_client_id'] ?? ''));
                    if ($ga4 !== '') {
                        $clickPayload['ga4_client_id'] = $ga4;
                    }
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

            // Whitelist / Google IPs: never force invalid from click-id dedupe.
            // Same server→tag click: not fraud, keep valid.
            // Bot Protection off → never mark invalid from protection scores.
            $markInvalid = $botEnabled && ! $allowListedIp && (
                $detection['action_taken'] !== 'allow' || $duplicatePaidClick
            );

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
                'is_invalid_traffic' => $markInvalid,
                'visited_at' => $visitedAt,
                'created_at' => UserTimezone::nowUtc(),
                'updated_at' => UserTimezone::nowUtc(),
            ];

            if (Schema::hasColumn('visits', 'browser_version')) {
                $visitPayload['browser_version'] = $browser['version'];
            }

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
                $visitPayload['action_taken'] = ($duplicatePaidClick && ! $allowListedIp && $detection['action_taken'] === 'allow')
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

            if ($paidIdentity instanceof ResolvedPaidIdentity) {
                if (Schema::hasColumn('visits', 'visitor_id')) {
                    $visitPayload['visitor_id'] = $paidIdentity->visitorId;
                }
                if (Schema::hasColumn('visits', 'browser_id')) {
                    $visitPayload['browser_id'] = $paidIdentity->browserId;
                }
                if (Schema::hasColumn('visits', 'device_id')) {
                    $visitPayload['device_id'] = $paidIdentity->deviceId;
                }
                if (Schema::hasColumn('visits', 'fingerprint_id')) {
                    $visitPayload['fingerprint_id'] = $paidIdentity->fingerprintId;
                }
                if (Schema::hasColumn('visits', 'paid_identity_id')) {
                    $visitPayload['paid_identity_id'] = $paidIdentity->publicId;
                }
                if (Schema::hasColumn('visits', 'identity_confidence')) {
                    $visitPayload['identity_confidence'] = $paidIdentity->confidence;
                }
            }
            if (Schema::hasColumn('visits', 'ads_detections') && $adsDetections !== []) {
                $visitPayload['ads_detections'] = json_encode($adsDetections);
            }
            $fpSignals = is_array($data['fingerprint_signals'] ?? null) ? $data['fingerprint_signals'] : [];
            if ($fpSignals !== [] && Schema::hasColumn('visits', 'fingerprint_signals')) {
                $visitPayload['fingerprint_signals'] = json_encode($fpSignals);
            }
            if ($fpSignals !== []) {
                if (Schema::hasColumn('visits', 'screen_resolution') && filled($fpSignals['screen_size'] ?? null)) {
                    $visitPayload['screen_resolution'] = (string) $fpSignals['screen_size'];
                }
                if (Schema::hasColumn('visits', 'language') && filled($fpSignals['language'] ?? null)) {
                    $visitPayload['language'] = (string) $fpSignals['language'];
                }
                if (Schema::hasColumn('visits', 'timezone') && filled($fpSignals['timezone'] ?? null)) {
                    $visitPayload['timezone'] = (string) $fpSignals['timezone'];
                }
            }

            $visitId = DB::table('visits')->insertGetId($visitPayload);

            TransparentClickTracker::joinLandingVisit((int) $domain->id, (int) $visitId, $data);

            // Duplicate attribution IDs are excluded from unique Google totals,
            // but every paid visit must advance fraud rolling windows.
            if ($isPaidTraffic && $paidIdentity instanceof ResolvedPaidIdentity) {
                try {
                    app(PaidAdvertisingPipeline::class)->recordClick(
                        $domain,
                        $ip,
                        $paidIdentity,
                        $campaignAttribution['campaign'] ?? null,
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
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
            $logRow = [
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
            ];

            if (Schema::hasColumn('detection_logs', 'risk_level')) {
                $logRow['risk_level'] = $detection['risk_level'] ?? null;
            }
            if (Schema::hasColumn('detection_logs', 'clickronix_breakdown')) {
                $breakdown = is_array($detection['clickronix'] ?? null) ? $detection['clickronix'] : [];
                if (! empty($detection['ads_detections'])) {
                    $breakdown['ads_detections'] = $detection['ads_detections'];
                    $breakdown['paid_identity'] = $detection['paid_identity'] ?? null;
                    $breakdown['ads_ruleset_version'] = $detection['ads_ruleset_version'] ?? null;
                }
                $logRow['clickronix_breakdown'] = $breakdown !== [] ? json_encode($breakdown) : null;
            }
            if (Schema::hasColumn('detection_logs', 'ruleset_version')) {
                $logRow['ruleset_version'] = $detection['ads_ruleset_version']
                    ?? ($detection['clickronix']['ruleset_version'] ?? null);
            }

            DB::table('detection_logs')->insert($logRow);
        }

        if (
            $detection['action_taken'] === 'block'
            && ! $protection->isAllowListed($domain, $ip)
            && $isPaidTraffic
        ) {
            // Exclusion Manager On → queue for Google Ads IP lists.
            // Safety-gate eligibility is advisory; manager rules decide auto-queue.
            $queued = app(GoogleAudienceExclusionService::class)->queueBlockedIpIfEligible(
                $domain,
                $ip,
                $detection['threat_group'] ?? null,
                isPaidTraffic: true,
            );
            if ($queued) {
                $detection['ip_exclusion_status'] = 'queued';
                $detection['ip_exclusion_eligible'] = true;
            } elseif (! $ipExclusionEligible) {
                $detection['ip_exclusion_status'] = $detection['ip_exclusion_status'] ?? 'suppressed';
            }
        }

        $clientPayload = $protection->clientPayload(
            $detection,
            $enforceBlock,
            $captchaRequired,
            $this->shouldRecordSession($domain, $detection, $isPaidTraffic),
            $visitId,
            $domain,
            60000,
        );

        // Device intelligence (spec): persistent DEV_ ≠ fingerprint, paid history, exclusion → GA4.
        try {
            $threatGroup = strtolower((string) ($detection['threat_group'] ?? ''));
            $deviceIntel = app(\App\Services\ClickronixDeviceService::class)->ingest($domain, [
                'device_token' => trim((string) ($data['device_token'] ?? '')),
                'fingerprint' => $data['fingerprint'] ?? null,
                'fingerprint_id' => $paidIdentity instanceof ResolvedPaidIdentity ? $paidIdentity->fingerprintId : null,
                'ga4_client_id' => trim((string) ($data['ga4_client_id'] ?? '')),
                'gclid' => $data['gclid'] ?? null,
                'gbraid' => $data['gbraid'] ?? null,
                'wbraid' => $data['wbraid'] ?? null,
                'ip' => $ip,
                // Count unique paid entries only when Paid Marketing is connected for this domain.
                'is_paid' => $paidEnabled && $isPaidTraffic && $priorPaidClick === null,
                'is_invalid' => $botEnabled && $paidEnabled && $isPaidTraffic && ($detection['action_taken'] !== 'allow') && ! $allowListedIp && ! $sameClickContinuation,
                'landing_page' => $data['url'] ?? $data['path'] ?? null,
                'session_id' => $sessionId,
                'automation' => str_contains($threatGroup, 'bot') || str_contains($threatGroup, 'automat'),
                'proxy' => str_contains($threatGroup, 'proxy'),
                'vpn' => str_contains($threatGroup, 'vpn'),
                'datacenter' => str_contains($threatGroup, 'data_center') || str_contains($threatGroup, 'datacenter'),
                'user_agent' => $ua,
            ]);

            if (Schema::hasTable('visits') && $visitId && filled($deviceIntel['device_id'] ?? null)) {
                $update = [];
                if (Schema::hasColumn('visits', 'device_id')) {
                    $update['device_id'] = $deviceIntel['device_id'];
                }
                if (Schema::hasColumn('visits', 'fingerprint_id') && filled($deviceIntel['fingerprint_id'] ?? null)) {
                    $update['fingerprint_id'] = $deviceIntel['fingerprint_id'];
                }
                if (Schema::hasColumn('visits', 'ga4_client_id') && filled($deviceIntel['ga4_client_id'] ?? null)) {
                    $update['ga4_client_id'] = $deviceIntel['ga4_client_id'];
                } elseif (Schema::hasColumn('visits', 'ga4_client_id') && filled($data['ga4_client_id'] ?? null)) {
                    $update['ga4_client_id'] = trim((string) $data['ga4_client_id']);
                }
                if (Schema::hasColumn('visits', 'device_confidence')) {
                    $update['device_confidence'] = $deviceIntel['device_confidence'];
                }
                if (Schema::hasColumn('visits', 'device_token') && filled($deviceIntel['device_token'] ?? null)) {
                    $update['device_token'] = $deviceIntel['device_token'];
                }
                if ($update !== []) {
                    DB::table('visits')->where('id', $visitId)->update($update);
                }
            }

            $clientPayload['device_id'] = $deviceIntel['device_id'] ?? null;
            $clientPayload['device_token'] = $deviceIntel['device_token'] ?? null;
            $clientPayload['device_confidence'] = $deviceIntel['device_confidence'] ?? null;
            $clientPayload['exclusion_candidate'] = (bool) ($deviceIntel['exclusion_candidate'] ?? false);
            $clientPayload['fire_exclude_event'] = (bool) ($deviceIntel['fire_exclude_event'] ?? false);
            $clientPayload['exclude_event'] = \App\Services\ClickronixDeviceService::EXCLUDE_EVENT;
            $clientPayload['exclude_reason'] = $deviceIntel['risk_reason'] ?? null;
            $clientPayload['risk_label'] = $deviceIntel['risk_label'] ?? null;
            if (! empty($deviceIntel['ga4_client_id'])) {
                $clientPayload['ga4_client_id'] = $deviceIntel['ga4_client_id'];
            }
        } catch (\Throwable $e) {
            report($e);
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
            'visitor_id' => ['nullable', 'string', 'max:128'],
            'visit_id' => ['nullable', 'integer'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'duration_ms' => ['nullable', 'integer', 'max:120000'],
            'threat_group' => ['nullable', 'string', 'max:40'],
            'events' => ['required', 'array', 'max:800'],
        ])->validate();

        if (! Schema::hasTable('visit_session_recordings')) {
            return $this->cors($request, response()->json(['ok' => true, 'skipped' => true]));
        }

        $domain = Domain::where('domain_key', $data['domainKey'])->firstOrFail();
        $hostMismatch = DomainKeyHostGuard::mismatchReason(
            $request,
            $domain,
            (string) ($data['page_url'] ?? '')
        );
        if ($hostMismatch !== null) {
            return $this->cors($request, response()->json([
                'ok' => false,
                'error' => 'hostname_mismatch',
                'message' => $hostMismatch,
                'skipped' => true,
            ], 403));
        }

        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        $domain->loadMissing('user');
        $planSessionRecordings = DetectionPlanFeatures::enabled($domain->user, DetectionPlanFeatures::SESSION_RECORDINGS);
        if (! SessionRecordingGate::allowsIngest($settings, $planSessionRecordings)) {
            return $this->cors($request, response()->json(['ok' => true, 'skipped' => true, 'reason' => 'session_recording_off']));
        }

        $thresholds = DetectionProfiles::thresholdsFor(
            $settings?->detection_profile,
            is_array($settings?->detection_thresholds) ? $settings->detection_thresholds : null,
        );
        $behaviorOn = (bool) ($thresholds['behavior_control_enabled'] ?? false);
        if (! DetectionPlanFeatures::enabled($domain->user, DetectionPlanFeatures::BEHAVIOR_CONTROL)) {
            $behaviorOn = false;
        }

        $ip = $this->clientIp($request);
        $events = array_slice((array) $data['events'], 0, 800);
        $durationMs = min((int) ($data['duration_ms'] ?? 0), 120000);
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

        $recordingId = DB::table('visit_session_recordings')->insertGetId($payload);

        $startedAt = UserTimezone::nowUtc()->subMilliseconds(max(0, $durationMs));
        BehaviorEventPersister::insert(
            BehaviorEventPersister::extractRows(
                $events,
                (int) $domain->id,
                (int) $recordingId,
                isset($data['visit_id']) ? (int) $data['visit_id'] : null,
                isset($data['session_id']) ? (string) $data['session_id'] : null,
                isset($data['visitor_id']) ? (string) $data['visitor_id'] : null,
                $startedAt,
            )
        );

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
        $user = $domain->relationLoaded('user') ? $domain->user : $domain->user()->first();

        return SessionRecordingGate::shouldRecord(
            $settings,
            $detection,
            $isPaidTraffic,
            DetectionPlanFeatures::enabled($user, DetectionPlanFeatures::BEHAVIOR_CONTROL),
            DetectionPlanFeatures::enabled($user, DetectionPlanFeatures::SESSION_RECORDINGS),
        );
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
        // Persist exact browser version as fingerprint evidence as well as UI detail.
        // Keep more-specific browser tokens before Chrome/Safari.
        $patterns = [
            'Edge' => '/Edg\\/([0-9\\.]+)/',
            'Samsung Internet' => '/SamsungBrowser\\/([0-9\\.]+)/',
            'Opera' => '/(?:OPR|Opera)\\/([0-9\\.]+)/',
            'Chrome' => '/(?:Chrome|CriOS)\\/([0-9\\.]+)/',
            'Firefox' => '/Firefox\\/([0-9\\.]+)/',
            'Firefox iOS' => '/FxiOS\\/([0-9\\.]+)/',
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
        return $this->findPaidClickById($domainId, $paidId) !== null;
    }

    /**
     * @return object{paid_id: string, ip: ?string, clicked_at: mixed, click_source: ?string}|null
     */
    private function findPaidClickById(int $domainId, string $paidId): ?object
    {
        if ($paidId === '' || ! Schema::hasTable('paid_marketing_clicks')) {
            return null;
        }

        $select = ['pc.paid_id', 'pc.ip', 'pc.clicked_at'];
        if (Schema::hasColumn('paid_marketing_clicks', 'click_source')) {
            $select[] = 'pc.click_source';
        }

        $row = DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->where('pv.domain_id', $domainId)
            ->where('pc.paid_id', $paidId)
            ->orderByDesc('pc.id')
            ->first($select);

        return $row ?: null;
    }

    /**
     * Server /click ingest then website tag (or quick same-IP reload) is one Google click, not replay fraud.
     */
    private function isSamePaidClickContinuation(
        object $prior,
        string $ip,
        string $currentSource,
        Carbon $visitedAt,
    ): bool {
        try {
            $priorAt = $prior->clicked_at
                ? Carbon::parse((string) $prior->clicked_at)
                : null;
        } catch (\Throwable) {
            $priorAt = null;
        }

        if ($priorAt === null) {
            return false;
        }

        $ageMinutes = $priorAt->diffInMinutes($visitedAt, false);
        if ($ageMinutes < 0 || $ageMinutes > 60) {
            return false;
        }

        $priorSource = strtolower(trim((string) ($prior->click_source ?? '')));
        $currentSource = strtolower(trim($currentSource));

        // Canonical path: Google Ads redirect (/click, click_source=server) → landing tag.
        if ($priorSource === 'server' && in_array($currentSource, ['tag', '', 'pixel', 'noscript'], true)) {
            return true;
        }

        // Same IP within 2 minutes (reload / double pixel) — still one click.
        $priorIp = trim((string) ($prior->ip ?? ''));
        if ($priorIp !== '' && $priorIp === $ip && $ageMinutes <= 2) {
            return true;
        }

        return false;
    }

    private function resolveTrackingConfidence(string $clickSource): string
    {
        return in_array($clickSource, ['noscript', 'pixel'], true) ? 'reduced' : 'high';
    }

}

