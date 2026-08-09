<?php

namespace App\Support\Clickronix;

/**
 * Implementable subset of Clickronix rule catalogue wired to current Promotix signals.
 * Points / modes follow Clickronix_Full_Bot_Protection…_Manual_v2 §9 + §15.
 */
final class RuleCatalog
{
    public const RULESET_VERSION = 'clickronix-v2.1';

    /** @var array<string, RuleDefinition>|null */
    private static ?array $definitions = null;

    public static function get(string $code): ?RuleDefinition
    {
        return self::all()[$code] ?? null;
    }

    /**
     * @return array<string, RuleDefinition>
     */
    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $defs = [
            // —— Policy / lists (standalone) ——
            new RuleDefinition(
                code: 'ALLOW_LIST',
                category: Category::NETWORK,
                mode: DecisionMode::TRUST,
                basePoints: -100,
                maxPoints: -100,
                legacyGroup: null,
                defaultAction: 'allow',
                requiredFields: ['ip_address'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'BLOCK_LIST',
                category: Category::NETWORK,
                mode: DecisionMode::STANDALONE,
                basePoints: 100,
                maxPoints: 100,
                legacyGroup: 'blocked',
                defaultAction: 'block',
                requiredFields: ['ip_address'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'COUNTRY_RESTRICTION',
                category: Category::NETWORK,
                mode: DecisionMode::STANDALONE,
                basePoints: 100,
                maxPoints: 100,
                legacyGroup: 'blocked_country',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'ip_country'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'OUT_OF_GEO',
                category: Category::NETWORK,
                mode: DecisionMode::STANDALONE,
                basePoints: 55,
                maxPoints: 70,
                legacyGroup: 'out_of_geo',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'ip_country'],
                entityScope: 'ip',
            ),

            // —— IP / Network ——
            new RuleDefinition(
                code: 'MALICIOUS_IP_REPUTATION',
                category: Category::NETWORK,
                mode: DecisionMode::CORRELATED,
                basePoints: 40,
                maxPoints: 60,
                legacyGroup: 'malicious',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'intel_confidence'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'VPN',
                category: Category::NETWORK,
                mode: DecisionMode::SUPPORTING,
                basePoints: 10,
                maxPoints: 20,
                legacyGroup: 'vpn',
                defaultAction: 'flag',
                requiredFields: ['ip_address', 'intel_confidence'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'TOR_EXIT',
                category: Category::NETWORK,
                mode: DecisionMode::CORRELATED,
                basePoints: 30,
                maxPoints: 45,
                legacyGroup: 'vpn',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'intel_confidence'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'PUBLIC_PROXY',
                category: Category::NETWORK,
                mode: DecisionMode::CORRELATED,
                basePoints: 25,
                maxPoints: 40,
                legacyGroup: 'proxy',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'intel_confidence'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'DATACENTER_IP',
                category: Category::NETWORK,
                mode: DecisionMode::CORRELATED,
                basePoints: 15,
                maxPoints: 30,
                legacyGroup: 'data_center',
                defaultAction: 'flag',
                requiredFields: ['ip_address', 'intel_confidence'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'HIGH_REQUEST_VELOCITY',
                category: Category::NETWORK,
                mode: DecisionMode::CORRELATED,
                basePoints: 25,
                maxPoints: 50,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'request_count_window', 'window_seconds'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'EXTREME_REQUEST_VELOCITY',
                category: Category::NETWORK,
                mode: DecisionMode::STANDALONE,
                basePoints: 60,
                maxPoints: 80,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'request_count_window', 'window_seconds'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'SESSION_RATE_LIMIT',
                category: Category::BEHAVIOR,
                mode: DecisionMode::CORRELATED,
                basePoints: 20,
                maxPoints: 40,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'flag',
                requiredFields: ['session_id', 'request_count_window'],
                entityScope: 'session',
            ),

            // —— Paid rapid / frequency (customer Detection Settings = policy floor for block_at) ——
            new RuleDefinition(
                code: 'RAPID_REPEAT',
                category: Category::BEHAVIOR,
                mode: DecisionMode::CORRELATED,
                basePoints: 30,
                maxPoints: 50,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'flag',
                requiredFields: ['ip_address', 'paid_clicks_in_window', 'rapid_window_seconds'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'RAPID_REPEAT_BLOCK',
                category: Category::BEHAVIOR,
                mode: DecisionMode::STANDALONE,
                basePoints: 70,
                maxPoints: 85,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'paid_clicks_in_window', 'rapid_window_seconds'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'PAID_DAILY_CLICK_LIMIT',
                category: Category::BEHAVIOR,
                mode: DecisionMode::STANDALONE,
                basePoints: 75,
                maxPoints: 90,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'block',
                requiredFields: ['ip_address', 'paid_clicks_today'],
                entityScope: 'ip',
            ),

            // —— Browser / crawler (wired signals) ——
            new RuleDefinition(
                code: 'CRAWLER_UA',
                category: Category::CRAWLER,
                mode: DecisionMode::CORRELATED,
                basePoints: 35,
                maxPoints: 55,
                legacyGroup: 'data_center',
                defaultAction: 'block',
                requiredFields: ['user_agent'],
                entityScope: 'session',
            ),
            new RuleDefinition(
                code: 'CLAIMED_GOOD_BOT_UNVERIFIED',
                category: Category::CRAWLER,
                mode: DecisionMode::STANDALONE,
                basePoints: 80,
                maxPoints: 100,
                legacyGroup: 'data_center',
                defaultAction: 'block',
                requiredFields: ['user_agent', 'ptr_hostname'],
                entityScope: 'ip',
            ),

            // —— Trust ——
            new RuleDefinition(
                code: 'NORMAL_INTERACTION',
                category: Category::BEHAVIOR,
                mode: DecisionMode::TRUST,
                basePoints: -15,
                maxPoints: -30,
                legacyGroup: null,
                defaultAction: 'allow',
                requiredFields: ['session_id'],
                entityScope: 'session',
            ),
            new RuleDefinition(
                code: 'SHARED_IP_DETECTED',
                category: Category::DEVICE,
                mode: DecisionMode::TRUST,
                basePoints: -15,
                maxPoints: -30,
                legacyGroup: null,
                defaultAction: 'allow',
                requiredFields: ['ip_address', 'device_count'],
                entityScope: 'ip',
            ),

            // —— Behavior Control (customer policy / session post-process) ——
            new RuleDefinition(
                code: 'ZERO_INTERACTION',
                category: Category::BEHAVIOR,
                mode: DecisionMode::SUPPORTING,
                basePoints: 10,
                maxPoints: 20,
                legacyGroup: 'suspicious',
                defaultAction: 'flag',
                requiredFields: ['session_id', 'event_stream'],
                entityScope: 'session',
            ),
            new RuleDefinition(
                code: 'IDLE_RETURN_BLOCK',
                category: Category::BEHAVIOR,
                mode: DecisionMode::STANDALONE,
                basePoints: 80,
                maxPoints: 95,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'block',
                requiredFields: ['session_id', 'device_id'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'SCROLL_PATTERN_REPEAT',
                category: Category::BEHAVIOR,
                mode: DecisionMode::CORRELATED,
                basePoints: 35,
                maxPoints: 55,
                legacyGroup: 'suspicious',
                defaultAction: 'flag',
                requiredFields: ['session_id', 'scroll_events'],
                entityScope: 'ip',
            ),
            new RuleDefinition(
                code: 'SCROLL_PATTERN_BLOCK',
                category: Category::BEHAVIOR,
                mode: DecisionMode::STANDALONE,
                basePoints: 75,
                maxPoints: 90,
                legacyGroup: 'abnormal_rate_limit',
                defaultAction: 'block',
                requiredFields: ['session_id', 'scroll_events'],
                entityScope: 'ip',
            ),
        ];

        $map = [];
        foreach ($defs as $def) {
            $map[$def->code] = $def;
        }

        return self::$definitions = $map;
    }

    /**
     * Map legacy reason codes to Clickronix rule codes (audit / labels).
     */
    public static function legacyReasonToRule(string $reason): ?string
    {
        return match ($reason) {
            'allow_list' => 'ALLOW_LIST',
            'block_list' => 'BLOCK_LIST',
            'blocked_country' => 'COUNTRY_RESTRICTION',
            'out_of_geo' => 'OUT_OF_GEO',
            'vpn_isp_match' => 'VPN',
            'abuse_tor' => 'TOR_EXIT',
            'proxy_isp_match' => 'PUBLIC_PROXY',
            'ipdetails_hosting' => 'DATACENTER_IP',
            'abuse_confidence', 'ipdetails_abuser_high', 'ipdetails_abuser_medium' => 'MALICIOUS_IP_REPUTATION',
            'rapid_page_opens', 'ip_rate_limit' => 'HIGH_REQUEST_VELOCITY',
            'session_rate_limit' => 'SESSION_RATE_LIMIT',
            'RAPID_REPEAT' => 'RAPID_REPEAT',
            'RAPID_REPEAT_BLOCK' => 'RAPID_REPEAT_BLOCK',
            'paid_daily_click_limit' => 'PAID_DAILY_CLICK_LIMIT',
            'crawler_ua' => 'CRAWLER_UA',
            default => null,
        };
    }
}
