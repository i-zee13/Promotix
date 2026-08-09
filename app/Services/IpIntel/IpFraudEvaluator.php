<?php

namespace App\Services\IpIntel;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Support\Clickronix\DecisionMode;
use App\Support\Clickronix\RuleCatalog;
use App\Support\Clickronix\ScoringEngine;
use App\Support\Clickronix\SignalState;
use App\Support\Clickronix\TriggeredSignal;
use App\Support\DetectionPlanFeatures;
use App\Support\DetectionProfiles;
use App\Support\GeoAudienceMatcher;

/**
 * Collects Promotix signals and scores them via Clickronix ScoringEngine (manual v2).
 *
 * Policy rules (allow/block list, geo) remain immediate. Network context like VPN is
 * supporting-only and never hard-blocks alone. Paid rapid-block / daily caps are
 * standalone customer-policy rules from Detection Settings.
 */
class IpFraudEvaluator
{
    private const IP_RATE_WINDOW_MINUTES = 5;

    private const IP_RATE_THRESHOLD = 10;

    /** Hide site when the same IP opens pages this many times within one minute. */
    public const IP_MINUTE_VISIT_THRESHOLD = 4;

    /** Standalone extreme velocity (manual: 100/min — scaled to visit telemetry). */
    public const IP_MINUTE_EXTREME_THRESHOLD = 15;

    /** Paid marketing: max valid paid clicks per IP per calendar day (3rd+ daily is blocked). */
    public const PAID_DAILY_VALID_CLICK_LIMIT = 2;

    /** Paid marketing: seconds window for rapid-repeat detection (DE-02 / DE-03). */
    public const PAID_RAPID_WINDOW_SECONDS = 120;

    public function __construct(private readonly IpIntelService $intel)
    {
    }

    /**
     * @return array{
     *   threat_score: int,
     *   threat_group: ?string,
     *   action_taken: string,
     *   reasons: list<string>,
     *   risk_level?: string,
     *   block_scope?: string,
     *   category_scores?: array<string, int>,
     *   clickronix?: array<string, mixed>
     * }
     */
    public function evaluate(
        Domain $domain,
        IpLog $ipLog,
        ?string $country,
        int $sessionHits = 1,
        int $ipRecentHits = 0,
        bool $isCrawler = false,
        bool $isPaidTraffic = false,
        int $paidClicksToday = 0,
        int $ipMinuteHits = 0,
        int $paidClicksInRapidWindow = 0,
    ): array {
        $settings = DomainDetectionSetting::firstOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => 'block',
                'invalid_malicious_action' => 'block',
                'suspicious_enabled' => true,
                'frequency_capping' => true,
                'suspicious_matrix' => [
                    'vpn' => 'block',
                    'proxy' => 'block',
                    'data_center' => 'block',
                    'abnormal_rate_limit' => 'block',
                ],
                'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
            ]
        );

        $domain->loadMissing('user');
        $planFeatures = DetectionPlanFeatures::forUser($domain->user);
        $can = static fn (string $key): bool => (bool) ($planFeatures[$key] ?? true);

        // —— Policy short-circuits (manual: standalone / trust) ——
        if ($can(DetectionPlanFeatures::ALLOW_LIST) && $settings->allow_list_enabled && self::isIpInList($ipLog->ip, (string) $settings->allow_list_ips)) {
            return $this->finalizePolicyAllow(['allow_list']);
        }

        if ($can(DetectionPlanFeatures::BLOCK_LIST) && $settings->block_list_enabled && self::isIpInList($ipLog->ip, (string) $settings->block_list_ips)) {
            return $this->finalizeStandaloneBlock('blocked', ['block_list'], 100);
        }

        $resolvedCountryEarly = strtoupper(trim((string) ($country ?: $ipLog->intel_country_code ?: '')));
        $rawEarly = (array) ($ipLog->ipdetails_raw ?? []);
        if (
            $can(DetectionPlanFeatures::GEO_BLOCK)
            && GeoAudienceMatcher::isBlocked(
                $settings,
                $resolvedCountryEarly,
                $rawEarly['region'] ?? $rawEarly['state'] ?? null,
                $rawEarly['city'] ?? null,
                $ipLog,
                $domain,
            )
        ) {
            return $this->finalizeStandaloneBlock('blocked_country', ['blocked_country'], 100);
        }

        if ($can(DetectionPlanFeatures::GEO_ALLOW) && $settings->out_of_geo_enabled) {
            $allowed = GeoAudienceMatcher::isAllowed(
                $settings,
                $resolvedCountryEarly,
                $rawEarly['region'] ?? $rawEarly['state'] ?? null,
                $rawEarly['city'] ?? null,
                $ipLog,
                $domain,
            );
            if (! $allowed) {
                return $this->finalizeStandaloneBlock('out_of_geo', ['out_of_geo'], 55);
            }
        }

        if (! $settings->suspicious_enabled) {
            return $this->finalizePolicyAllow([]);
        }

        $matrix = (array) ($settings->suspicious_matrix ?? []);
        if (! $can(DetectionPlanFeatures::VPN)) {
            $matrix['vpn'] = 'allow';
        }
        if (! $can(DetectionPlanFeatures::PROXY)) {
            $matrix['proxy'] = 'allow';
        }
        if (! $can(DetectionPlanFeatures::DATA_CENTER)) {
            $matrix['data_center'] = 'allow';
        }
        if (! $can(DetectionPlanFeatures::ABNORMAL_RATE)) {
            $matrix['abnormal_rate_limit'] = 'allow';
        }

        $botAction = $can(DetectionPlanFeatures::BOT)
            ? ($settings->invalid_bot_action ?? 'block')
            : 'allow';
        $maliciousAction = $can(DetectionPlanFeatures::SUSPICIOUS_BEHAVIOR)
            ? ($settings->invalid_malicious_action ?? 'block')
            : 'allow';
        $frequencyOn = $can(DetectionPlanFeatures::REPEATED_CLICK) && $settings->frequency_capping;
        $rapidOn = $can(DetectionPlanFeatures::RAPID_CLICK);
        $limitsOn = $can(DetectionPlanFeatures::FREQUENCY_LIMITS);

        $thresholds = DetectionProfiles::thresholdsFor(
            $can(DetectionPlanFeatures::PROFILES)
                ? (string) ($settings->detection_profile ?? DetectionProfiles::STANDARD)
                : DetectionProfiles::STANDARD,
            is_array($settings->detection_thresholds) ? $settings->detection_thresholds : null,
        );

        /** @var list<TriggeredSignal> $signals */
        $signals = [];

        $intelConfidence = $this->intelConfidence($ipLog);

        if ($isCrawler && $botAction !== 'allow') {
            $signals[] = TriggeredSignal::triggered(
                'CRAWLER_UA',
                confidence: 0.85,
                evidence: ['user_agent' => $ipLog->user_agent],
                legacyReason: 'crawler_ua',
                customerPreferredAction: $botAction,
                rawFieldsPresent: ['user_agent'],
            );
        }

        if ($can(DetectionPlanFeatures::DATA_CENTER) && ($matrix['data_center'] ?? 'block') !== 'allow') {
            if ($this->intel->isHostingType($ipLog)) {
                if ($intelConfidence < 0.50) {
                    $signals[] = TriggeredSignal::unknown('DATACENTER_IP', ['intel_confidence']);
                } else {
                    $signals[] = TriggeredSignal::triggered(
                        'DATACENTER_IP',
                        confidence: $intelConfidence,
                        evidence: ['hosting' => true],
                        legacyReason: 'ipdetails_hosting',
                        customerPreferredAction: $matrix['data_center'] ?? $botAction,
                        rawFieldsPresent: ['ip_address', 'intel_confidence'],
                    );
                }
            }
        }

        if ($can(DetectionPlanFeatures::VPN) && ($matrix['vpn'] ?? 'block') !== 'allow') {
            if ($this->intel->isVpnSuspect($ipLog)) {
                $isTor = (bool) ($ipLog->abuse_is_tor ?? false);
                if ($intelConfidence < 0.50) {
                    $signals[] = TriggeredSignal::unknown($isTor ? 'TOR_EXIT' : 'VPN', ['intel_confidence']);
                } elseif ($isTor) {
                    $signals[] = TriggeredSignal::triggered(
                        'TOR_EXIT',
                        confidence: $intelConfidence,
                        legacyReason: 'abuse_tor',
                        customerPreferredAction: $matrix['vpn'] ?? $botAction,
                        rawFieldsPresent: ['ip_address', 'intel_confidence'],
                    );
                } else {
                    // Manual: VPN is supporting — never alone hard-blocks.
                    $signals[] = TriggeredSignal::triggered(
                        'VPN',
                        confidence: $intelConfidence,
                        legacyReason: 'vpn_isp_match',
                        customerPreferredAction: 'flag',
                        rawFieldsPresent: ['ip_address', 'intel_confidence'],
                    );
                }
            }
        }

        if ($can(DetectionPlanFeatures::PROXY) && ($matrix['proxy'] ?? 'block') !== 'allow') {
            if ($this->intel->isProxySuspect($ipLog)) {
                if ($intelConfidence < 0.50) {
                    $signals[] = TriggeredSignal::unknown('PUBLIC_PROXY', ['intel_confidence']);
                } else {
                    $signals[] = TriggeredSignal::triggered(
                        'PUBLIC_PROXY',
                        confidence: $intelConfidence,
                        legacyReason: 'proxy_isp_match',
                        customerPreferredAction: $matrix['proxy'] ?? $botAction,
                        rawFieldsPresent: ['ip_address', 'intel_confidence'],
                    );
                }
            }
        }

        if ($maliciousAction !== 'allow') {
            $abuseScore = (int) ($ipLog->abuse_confidence_score ?? 0);
            $abuserScore = $ipLog->ipdetails_abuser_score;
            $reputationConfidence = 0.0;
            $reputationReason = 'abuse_confidence';

            if ($abuseScore >= 50) {
                $reputationConfidence = max(0.50, min(1.0, $abuseScore / 100));
                $reputationReason = 'abuse_confidence';
            }

            if (is_numeric($abuserScore)) {
                $score = (float) $abuserScore;
                if ($score >= 0.2 && $score > $reputationConfidence) {
                    $reputationConfidence = max(0.50, min(1.0, $score));
                    $reputationReason = $score >= 0.7 ? 'ipdetails_abuser_high' : 'ipdetails_abuser_medium';
                }
            }

            if ($reputationConfidence >= 0.50) {
                $signals[] = TriggeredSignal::triggered(
                    'MALICIOUS_IP_REPUTATION',
                    confidence: $reputationConfidence,
                    evidence: [
                        'abuse_confidence_score' => $abuseScore,
                        'ipdetails_abuser_score' => $abuserScore,
                    ],
                    legacyReason: $reputationReason,
                    customerPreferredAction: $maliciousAction,
                    rawFieldsPresent: ['ip_address', 'intel_confidence'],
                    recurrenceCount: $reputationConfidence >= 0.7 ? 2 : 1,
                );
            }
        }

        if ($frequencyOn && ($matrix['abnormal_rate_limit'] ?? 'block') !== 'allow') {
            $rateAction = $matrix['abnormal_rate_limit'] ?? $botAction;

            if ($ipMinuteHits >= self::IP_MINUTE_EXTREME_THRESHOLD) {
                $signals[] = TriggeredSignal::triggered(
                    'EXTREME_REQUEST_VELOCITY',
                    confidence: 0.95,
                    evidence: [
                        'request_count_window' => $ipMinuteHits,
                        'window_seconds' => 60,
                    ],
                    legacyReason: 'rapid_page_opens',
                    customerPreferredAction: 'block',
                    rawFieldsPresent: ['ip_address', 'request_count_window', 'window_seconds'],
                    recurrenceCount: max(1, intdiv($ipMinuteHits, self::IP_MINUTE_EXTREME_THRESHOLD)),
                );
            } elseif ($ipMinuteHits >= self::IP_MINUTE_VISIT_THRESHOLD) {
                $signals[] = TriggeredSignal::triggered(
                    'HIGH_REQUEST_VELOCITY',
                    confidence: 0.90,
                    evidence: [
                        'request_count_window' => $ipMinuteHits,
                        'window_seconds' => 60,
                    ],
                    legacyReason: 'rapid_page_opens',
                    customerPreferredAction: $rateAction,
                    rawFieldsPresent: ['ip_address', 'request_count_window', 'window_seconds'],
                );
            }

            if ($sessionHits > 5) {
                $signals[] = TriggeredSignal::triggered(
                    'SESSION_RATE_LIMIT',
                    confidence: 0.85,
                    evidence: ['session_hits' => $sessionHits],
                    legacyReason: 'session_rate_limit',
                    customerPreferredAction: $rateAction,
                    rawFieldsPresent: ['session_id', 'request_count_window'],
                    recurrenceCount: max(1, $sessionHits - 4),
                );
            }

            if ($ipRecentHits >= self::IP_RATE_THRESHOLD) {
                $signals[] = TriggeredSignal::triggered(
                    'HIGH_REQUEST_VELOCITY',
                    confidence: 0.88,
                    evidence: [
                        'request_count_window' => $ipRecentHits,
                        'window_seconds' => self::IP_RATE_WINDOW_MINUTES * 60,
                    ],
                    legacyReason: 'ip_rate_limit',
                    customerPreferredAction: $rateAction,
                    rawFieldsPresent: ['ip_address', 'request_count_window', 'window_seconds'],
                    recurrenceCount: max(1, intdiv($ipRecentHits, self::IP_RATE_THRESHOLD)),
                );
            }
        }

        if ($rapidOn && $isPaidTraffic) {
            $blockAt = (int) $thresholds['rapid_block_at'];
            $flagAt = (int) $thresholds['rapid_flag_at'];
            if ($paidClicksInRapidWindow >= $blockAt && $blockAt > 0) {
                $signals[] = TriggeredSignal::triggered(
                    'RAPID_REPEAT_BLOCK',
                    confidence: 1.0,
                    evidence: [
                        'paid_clicks_in_window' => $paidClicksInRapidWindow,
                        'rapid_window_seconds' => (int) ($thresholds['rapid_window_seconds'] ?? self::PAID_RAPID_WINDOW_SECONDS),
                        'rapid_block_at' => $blockAt,
                    ],
                    legacyReason: 'RAPID_REPEAT_BLOCK',
                    customerPreferredAction: 'block',
                    rawFieldsPresent: ['ip_address', 'paid_clicks_in_window', 'rapid_window_seconds'],
                    recurrenceCount: max(1, $paidClicksInRapidWindow),
                );
            } elseif ($paidClicksInRapidWindow >= $flagAt && $flagAt > 0) {
                $signals[] = TriggeredSignal::triggered(
                    'RAPID_REPEAT',
                    confidence: 0.95,
                    evidence: [
                        'paid_clicks_in_window' => $paidClicksInRapidWindow,
                        'rapid_flag_at' => $flagAt,
                    ],
                    legacyReason: 'RAPID_REPEAT',
                    customerPreferredAction: 'flag',
                    rawFieldsPresent: ['ip_address', 'paid_clicks_in_window', 'rapid_window_seconds'],
                );
            }
        }

        if (
            $limitsOn
            && $isPaidTraffic
            && $paidClicksToday >= (int) $thresholds['daily_valid_click_limit']
        ) {
            $signals[] = TriggeredSignal::triggered(
                'PAID_DAILY_CLICK_LIMIT',
                confidence: 1.0,
                evidence: [
                    'paid_clicks_today' => $paidClicksToday,
                    'daily_limit' => (int) $thresholds['daily_valid_click_limit'],
                ],
                legacyReason: 'paid_daily_click_limit',
                customerPreferredAction: $matrix['abnormal_rate_limit'] ?? $botAction,
                rawFieldsPresent: ['ip_address', 'paid_clicks_today'],
            );
        }

        $resolvedCountry = strtoupper(trim((string) ($country ?: $ipLog->intel_country_code ?: '')));
        if ($can(DetectionPlanFeatures::GEO_ALLOW) && $settings->out_of_geo_enabled) {
            $raw = (array) ($ipLog->ipdetails_raw ?? []);
            $allowed = GeoAudienceMatcher::isAllowed(
                $settings,
                $resolvedCountry,
                $raw['region'] ?? $raw['state'] ?? null,
                $raw['city'] ?? null,
                $ipLog,
                $domain,
            );

            if (! $allowed) {
                $signals[] = TriggeredSignal::triggered(
                    'OUT_OF_GEO',
                    confidence: 1.0,
                    legacyReason: 'out_of_geo',
                    customerPreferredAction: 'block',
                    rawFieldsPresent: ['ip_address', 'ip_country'],
                );
            }
        }

        // Extreme / marketing profile still prefers combined evidence for soft signals.
        $requireCombined = (bool) ($thresholds['require_combined_evidence'] ?? false);

        if ($signals === []) {
            return $this->finalizePolicyAllow([]);
        }

        $result = (new ScoringEngine)->score($signals);
        $result = $this->applyActionFloors($result, $signals);

        if ($requireCombined && ! $result['standalone_fired'] && ! $result['correlation_satisfied']) {
            // Soften any remaining block when profile demands combined evidence.
            if ($result['action_taken'] === 'block' && $result['threat_score'] < 85) {
                $result['action_taken'] = 'flag';
            }
        }

        return [
            'threat_score' => $result['threat_score'],
            'threat_group' => $result['threat_group'],
            'action_taken' => $result['action_taken'],
            'reasons' => $result['reasons'],
            'risk_level' => $result['risk_level'],
            'block_scope' => $result['block_scope'],
            'category_scores' => $result['category_scores'],
            'clickronix' => [
                'model_version' => $result['model_version'],
                'ruleset_version' => $result['ruleset_version'],
                'risk_level' => $result['risk_level'],
                'block_scope' => $result['block_scope'],
                'duration_hint' => $result['duration_hint'],
                'category_scores' => $result['category_scores'],
                'trust_deduction' => $result['trust_deduction'],
                'correlation_satisfied' => $result['correlation_satisfied'],
                'standalone_fired' => $result['standalone_fired'],
                'detections' => $result['detections'],
            ],
        ];
    }

    /**
     * Ensure explicit customer floors (e.g. RAPID_REPEAT → flag) are not lost to low score bands.
     *
     * @param  array<string, mixed>  $result
     * @param  list<TriggeredSignal>  $signals
     * @return array<string, mixed>
     */
    private function applyActionFloors(array $result, array $signals): array
    {
        $rank = static fn (string $a): int => match ($a) {
            'block' => 3,
            'flag' => 2,
            default => 1,
        };

        $floor = 'allow';
        foreach ($signals as $signal) {
            if ($signal->state !== SignalState::TRIGGERED || $signal->confidence < 0.50) {
                continue;
            }
            $rule = RuleCatalog::get($signal->ruleCode);
            if ($rule === null) {
                continue;
            }

            // Supporting alone never raises floor to block (manual §15.3).
            if ($rule->mode === DecisionMode::SUPPORTING) {
                continue;
            }

            // Correlated alone: floor of flag max until correlation satisfied.
            $preferred = $signal->customerPreferredAction;
            if ($preferred === null) {
                continue;
            }

            if ($rule->mode === DecisionMode::CORRELATED && ! ($result['correlation_satisfied'] ?? false)) {
                if ($preferred === 'block') {
                    $preferred = 'flag';
                }
            }

            if ($rule->mode === DecisionMode::STANDALONE) {
                // Standalone already enforced via scoring; keep preferred if stronger.
            }

            if ($rank($preferred) > $rank($floor)) {
                $floor = $preferred;
            }
        }

        if ($rank($floor) > $rank((string) $result['action_taken'])) {
            $result['action_taken'] = $floor;
        }

        // If floor is flag but score was 0 edge case — keep reasons.
        return $result;
    }

    private function intelConfidence(IpLog $ipLog): float
    {
        // Provider outage / empty enrichment → below floor → UNKNOWN (0 points).
        $raw = $ipLog->ipdetails_raw;
        $hasIntel = is_array($raw) && $raw !== [];
        $hasAbuse = $ipLog->abuse_confidence_score !== null
            || $ipLog->abuse_is_tor !== null
            || $ipLog->ipdetails_abuser_score !== null;

        if (! $hasIntel && ! $hasAbuse) {
            return 0.0;
        }

        if (is_numeric($ipLog->abuse_confidence_score)) {
            return max(0.50, min(1.0, ((int) $ipLog->abuse_confidence_score) / 100));
        }

        return $hasIntel ? 0.85 : 0.55;
    }

    /**
     * @param  list<string>  $reasons
     * @return array{threat_score: int, threat_group: ?string, action_taken: string, reasons: list<string>}
     */
    private function finalizePolicyAllow(array $reasons): array
    {
        return [
            'threat_score' => 0,
            'threat_group' => null,
            'action_taken' => 'allow',
            'reasons' => $reasons,
            'risk_level' => 'trusted',
            'block_scope' => 'none',
            'category_scores' => [],
        ];
    }

    /**
     * @param  list<string>  $reasons
     * @return array{threat_score: int, threat_group: string, action_taken: string, reasons: list<string>}
     */
    private function finalizeStandaloneBlock(string $group, array $reasons, int $score): array
    {
        return [
            'threat_score' => $score,
            'threat_group' => $group,
            'action_taken' => 'block',
            'reasons' => $reasons,
            'risk_level' => 'critical',
            'block_scope' => 'ip',
            'category_scores' => [],
            'clickronix' => [
                'standalone_fired' => true,
                'ruleset_version' => RuleCatalog::RULESET_VERSION,
                'model_version' => ScoringEngine::MODEL_VERSION,
            ],
        ];
    }

    public static function isIpAllowListed(string $ip, string $allowList): bool
    {
        return self::isIpInList($ip, $allowList);
    }

    public static function isIpInList(string $ip, string $list): bool
    {
        $ip = trim($ip);
        if ($ip === '' || trim($list) === '') {
            return false;
        }

        foreach (preg_split('/\r\n|\r|\n|,/', $list) ?: [] as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '' || str_starts_with($entry, '#')) {
                continue;
            }

            if (! \App\Support\IpListParser::isActiveEntry($entry)) {
                continue;
            }

            $pattern = \App\Support\IpListParser::entryIp($entry);
            if ($pattern === '') {
                continue;
            }

            if (str_contains($pattern, '*')) {
                $prefix = rtrim($pattern, '*');
                if ($prefix !== '' && str_starts_with($ip, $prefix)) {
                    return true;
                }

                continue;
            }

            if (str_contains($pattern, '/')) {
                if (self::ipInCidr($ip, $pattern)) {
                    return true;
                }

                continue;
            }

            if (strcasecmp($ip, $pattern) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $mask = (int) $mask;
        if ($mask < 0 || $mask > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = $mask === 0 ? 0 : (~0 << (32 - $mask));

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
