<?php

namespace App\Support;

class DetectionReasonLabels
{
    /** @var array<string, string> */
    private const MAP = [
        'allow_list' => 'IP is on the allow list (highest precedence).',
        'block_list' => 'IP is on the block list.',
        'blocked_country' => 'Visitor country/region is on the block-country list.',
        'out_of_geo' => 'Visitor is outside the allow-country audience.',
        'RAPID_REPEAT' => 'Second paid click from the same IP inside the rapid window.',
        'RAPID_REPEAT_BLOCK' => 'Third (or later) paid click from the same IP inside the rapid window — customer rapid policy.',
        'paid_daily_click_limit' => 'Paid click count exceeded the daily valid-click limit for this IP.',
        'rapid_page_opens' => 'Same IP opened pages too quickly (frequency capping).',
        'session_rate_limit' => 'Session hit count exceeded the configured rate limit.',
        'ip_rate_limit' => 'IP hit count exceeded the short-window rate limit.',
        'vpn' => 'VPN / anonymizer reputation signal.',
        'vpn_isp_match' => 'VPN / anonymizer ISP match (supporting signal — never blocks alone).',
        'abuse_tor' => 'Tor exit node reputation signal.',
        'proxy_isp_match' => 'Proxy reputation signal.',
        'ipdetails_hosting' => 'Datacenter / hosting IP signal.',
        'crawler_ua' => 'Crawler / bot user-agent signal.',
        'proxy' => 'Proxy reputation signal.',
        'data_center' => 'Datacenter / hosting ASN signal.',
        'datacenter' => 'Datacenter / hosting ASN signal.',
        'bot' => 'Known crawler / bot user-agent.',
        'abuse_confidence' => 'AbuseIPDB confidence threshold exceeded.',
        'ipdetails_abuser_high' => 'IPDetails abuser score is high.',
        'ipdetails_abuser_medium' => 'IPDetails abuser score is elevated.',
        'NO_INTERACTION' => 'Session had no mouse, scroll, click, or keyboard activity.',
        'REPEATED_BEHAVIOR' => 'Same IP repeated a nearly identical low-human session pattern.',
        'IDLE_RETURN_BLOCK' => 'Same IP returned idle (no interaction) on a later visit — blocked by Behavior Control.',
        'SCROLL_PATTERN_REPEAT' => 'Same IP repeated a similar scroll timing pattern (2nd match) — flagged suspicious.',
        'SCROLL_PATTERN_BLOCK' => 'Same IP repeated a similar scroll timing pattern (3rd+ match) — blocked by Behavior Control.',
        'DUPLICATE_PAID_CLICK' => 'Repeat request with an already-seen Google click ID.',
        'fail_open' => 'Detection service failed; fail-open allowed the request.',
        'fail_closed' => 'Detection service failed; fail-closed blocked the request.',
        'previously_blocked' => 'IP was already marked blocked from a prior decision.',
        'manual_invalid' => 'Manually marked invalid by an admin.',
        'allowed_override' => 'Manually allowed override by an admin.',
        'VPN' => 'VPN supporting network signal (Clickronix).',
        'DATACENTER_IP' => 'Datacenter IP (correlated; needs supporting evidence to hard-block).',
        'PUBLIC_PROXY' => 'Public / anonymous proxy (correlated).',
        'MALICIOUS_IP_REPUTATION' => 'Malicious IP reputation (correlated).',
        'HIGH_REQUEST_VELOCITY' => 'High request velocity (correlated).',
        'EXTREME_REQUEST_VELOCITY' => 'Extreme request velocity (standalone block).',
        'PAID_DAILY_CLICK_LIMIT' => 'Paid daily valid-click limit reached (customer policy).',
    ];

    public static function label(string $code): string
    {
        $key = trim($code);
        if ($key === '') {
            return 'Unknown reason';
        }

        return self::MAP[$key] ?? self::MAP[strtolower($key)] ?? ('Detection signal: ' . $key);
    }

    /**
     * @param  list<string>|null  $codes
     * @return list<array{code: string, label: string}>
     */
    public static function explain(?array $codes): array
    {
        $out = [];
        foreach ($codes ?? [] as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $out[] = [
                'code' => $code,
                'label' => self::label($code),
            ];
        }

        return $out;
    }
}
