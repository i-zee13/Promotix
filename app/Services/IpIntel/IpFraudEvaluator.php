<?php

namespace App\Services\IpIntel;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Support\GeoAudienceMatcher;

class IpFraudEvaluator
{
    private const IP_RATE_WINDOW_MINUTES = 5;

    private const IP_RATE_THRESHOLD = 10;

    /** Hide site when the same IP opens pages this many times within one minute. */
    public const IP_MINUTE_VISIT_THRESHOLD = 4;

    /** Paid marketing: max valid paid clicks per IP per calendar day (3rd+ is blocked). */
    public const PAID_DAILY_VALID_CLICK_LIMIT = 2;

    public function __construct(private readonly IpIntelService $intel)
    {
    }

    /**
     * @return array{
     *   threat_score: int,
     *   threat_group: ?string,
     *   action_taken: string,
     *   reasons: list<string>
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

        if ($settings->allow_list_enabled && self::isIpAllowListed($ipLog->ip, (string) $settings->allow_list_ips)) {
            return $this->allowResult(['allow_list']);
        }

        if (! $settings->suspicious_enabled) {
            return $this->allowResult([]);
        }

        $matrix = (array) ($settings->suspicious_matrix ?? []);
        $botAction = $settings->invalid_bot_action ?? 'block';
        $signals = [];

        if ($isCrawler) {
            $signals[] = [
                'group' => 'data_center',
                'score' => 75,
                'action' => $botAction,
                'reason' => 'crawler_ua',
            ];
        }

        if ($this->intel->isHostingType($ipLog)) {
            $signals[] = [
                'group' => 'data_center',
                'score' => 65,
                'action' => $matrix['data_center'] ?? $botAction,
                'reason' => 'ipdetails_hosting',
            ];
        }

        if ($this->intel->isVpnSuspect($ipLog)) {
            $signals[] = [
                'group' => 'vpn',
                'score' => 72,
                'action' => $matrix['vpn'] ?? $botAction,
                'reason' => (bool) ($ipLog->abuse_is_tor ?? false) ? 'abuse_tor' : 'vpn_isp_match',
            ];
        }

        if ($this->intel->isProxySuspect($ipLog)) {
            $signals[] = [
                'group' => 'proxy',
                'score' => 58,
                'action' => $matrix['proxy'] ?? $botAction,
                'reason' => 'proxy_isp_match',
            ];
        }

        $abuseScore = (int) ($ipLog->abuse_confidence_score ?? 0);
        if ($abuseScore >= 50) {
            $signals[] = [
                'group' => 'malicious',
                'score' => min(100, $abuseScore),
                'action' => $settings->invalid_malicious_action ?? 'block',
                'reason' => 'abuse_confidence',
            ];
        }

        $abuserScore = $ipLog->ipdetails_abuser_score;
        if (is_numeric($abuserScore)) {
            $score = (float) $abuserScore;
            if ($score >= 0.7) {
                $signals[] = [
                    'group' => 'malicious',
                    'score' => (int) round($score * 100),
                    'action' => $settings->invalid_malicious_action ?? 'block',
                    'reason' => 'ipdetails_abuser_high',
                ];
            } elseif ($score >= 0.2) {
                $signals[] = [
                    'group' => 'malicious',
                    'score' => (int) round($score * 100),
                    'action' => $settings->invalid_malicious_action ?? 'block',
                    'reason' => 'ipdetails_abuser_medium',
                ];
            }
        }

        if ($settings->frequency_capping && $ipMinuteHits >= self::IP_MINUTE_VISIT_THRESHOLD) {
            $signals[] = [
                'group' => 'abnormal_rate_limit',
                'score' => 92,
                'action' => $matrix['abnormal_rate_limit'] ?? $botAction,
                'reason' => 'rapid_page_opens',
            ];
        }

        if ($settings->frequency_capping && $sessionHits > 5) {
            $signals[] = [
                'group' => 'abnormal_rate_limit',
                'score' => min(100, 40 + ($sessionHits * 5)),
                'action' => $matrix['abnormal_rate_limit'] ?? $botAction,
                'reason' => 'session_rate_limit',
            ];
        }

        if ($settings->frequency_capping && $ipRecentHits >= self::IP_RATE_THRESHOLD) {
            $signals[] = [
                'group' => 'abnormal_rate_limit',
                'score' => min(100, 50 + ($ipRecentHits * 3)),
                'action' => $matrix['abnormal_rate_limit'] ?? $botAction,
                'reason' => 'ip_rate_limit',
            ];
        }

        if (
            $isPaidTraffic
            && $paidClicksToday >= self::PAID_DAILY_VALID_CLICK_LIMIT
        ) {
            $signals[] = [
                'group' => 'abnormal_rate_limit',
                'score' => 85,
                'action' => $matrix['abnormal_rate_limit'] ?? $botAction,
                'reason' => 'paid_daily_click_limit',
            ];
        }

        $resolvedCountry = strtoupper(trim((string) ($country ?: $ipLog->intel_country_code ?: '')));
        if ($settings->out_of_geo_enabled) {
            $raw = (array) ($ipLog->ipdetails_raw ?? []);
            $allowed = GeoAudienceMatcher::isAllowed(
                $settings,
                $resolvedCountry,
                $raw['region'] ?? $raw['state'] ?? null,
                $raw['city'] ?? null,
                $ipLog,
            );

            if (! $allowed) {
                $signals[] = [
                    'group' => 'out_of_geo',
                    'score' => 55,
                    'action' => 'block',
                    'reason' => 'out_of_geo',
                ];
            }
        }

        if ($signals === []) {
            return $this->allowResult([]);
        }

        usort($signals, fn ($a, $b) => $b['score'] <=> $a['score']);
        $action = $this->strongestAction(array_column($signals, 'action'));

        return [
            'threat_score' => max(array_column($signals, 'score')),
            'threat_group' => $signals[0]['group'],
            'action_taken' => $action,
            'reasons' => array_values(array_unique(array_column($signals, 'reason'))),
        ];
    }

    /**
     * @param  list<string>  $reasons
     * @return array{threat_score: int, threat_group: ?string, action_taken: string, reasons: list<string>}
     */
    private function allowResult(array $reasons): array
    {
        return [
            'threat_score' => 0,
            'threat_group' => null,
            'action_taken' => 'allow',
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  list<string>  $actions
     */
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

    public static function isIpAllowListed(string $ip, string $allowList): bool
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
