<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\User;

/**
 * Plan-gated detection panel modules.
 *
 * Super Admin controls these via plan feature_flags (e.g. detection_vpn: 0).
 * Missing keys default to enabled so existing plans keep current behavior.
 */
class DetectionPlanFeatures
{
    public const VPN = 'detection_vpn';

    public const PROXY = 'detection_proxy';

    public const DATA_CENTER = 'detection_data_center';

    public const ABNORMAL_RATE = 'detection_abnormal_rate';

    public const REPEATED_CLICK = 'detection_repeated_click';

    public const SUSPICIOUS_BEHAVIOR = 'detection_suspicious_behavior';

    public const BOT = 'detection_bot';

    public const GEO_ALLOW = 'detection_geo_allow';

    public const GEO_BLOCK = 'detection_geo_block';

    public const ALLOW_LIST = 'detection_allow_list';

    public const BLOCK_LIST = 'detection_block_list';

    public const BEHAVIOR_CONTROL = 'detection_behavior_control';

    public const RAPID_CLICK = 'detection_rapid_click';

    public const FREQUENCY_LIMITS = 'detection_frequency_limits';

    public const SESSION_RECORDINGS = 'detection_session_recordings';

    public const GOOGLE_EXCLUSION = 'detection_google_exclusion';

    public const PROFILES = 'detection_profiles';

    /**
     * @return list<array{key: string, label: string, group: string, description: string}>
     */
    public static function catalog(): array
    {
        return [
            ['key' => self::VPN, 'label' => 'VPN Detection', 'group' => 'engine', 'description' => 'Detection Engine: VPN / anonymizer'],
            ['key' => self::PROXY, 'label' => 'Proxy Detection', 'group' => 'engine', 'description' => 'Detection Engine: Proxy'],
            ['key' => self::DATA_CENTER, 'label' => 'Datacenter Detection', 'group' => 'engine', 'description' => 'Detection Engine: Datacenter / hosting'],
            ['key' => self::ABNORMAL_RATE, 'label' => 'Abnormal Rate Detection', 'group' => 'engine', 'description' => 'Detection Engine: Abnormal / rate signals'],
            ['key' => self::REPEATED_CLICK, 'label' => 'Repeated Click Detection', 'group' => 'engine', 'description' => 'Detection Engine: Frequency capping'],
            ['key' => self::SUSPICIOUS_BEHAVIOR, 'label' => 'Suspicious Behavior', 'group' => 'engine', 'description' => 'Detection Engine: Malicious / abuse'],
            ['key' => self::BOT, 'label' => 'Bot Detection', 'group' => 'engine', 'description' => 'Known crawler / bot user-agent action'],
            ['key' => self::GEO_ALLOW, 'label' => 'Geo Allow (Targeting)', 'group' => 'access', 'description' => 'Primary Access: allow countries'],
            ['key' => self::GEO_BLOCK, 'label' => 'Geo Block Countries', 'group' => 'access', 'description' => 'Primary Access: block countries'],
            ['key' => self::ALLOW_LIST, 'label' => 'IP Allow List', 'group' => 'access', 'description' => 'Primary Access: whitelist IPs'],
            ['key' => self::BLOCK_LIST, 'label' => 'IP Block List', 'group' => 'access', 'description' => 'Primary Access: block IPs'],
            ['key' => self::BEHAVIOR_CONTROL, 'label' => 'Behavior Control', 'group' => 'advanced', 'description' => 'Idle / scroll pattern rules on paid visits'],
            ['key' => self::RAPID_CLICK, 'label' => 'Rapid Click Rules', 'group' => 'advanced', 'description' => 'Advanced: rapid click window'],
            ['key' => self::FREQUENCY_LIMITS, 'label' => 'Click Frequency Limits', 'group' => 'advanced', 'description' => 'Advanced: hourly/daily/weekly/monthly caps'],
            ['key' => self::SESSION_RECORDINGS, 'label' => 'Session Recordings', 'group' => 'advanced', 'description' => 'Session replay for flagged visits'],
            ['key' => self::GOOGLE_EXCLUSION, 'label' => 'Google Ads Exclusion', 'group' => 'advanced', 'description' => 'Auto-exclude blocked IPs in Google Ads'],
            ['key' => self::PROFILES, 'label' => 'Detection Profiles', 'group' => 'advanced', 'description' => 'Standard / Advanced / Extreme / Marketing profiles'],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::catalog(), 'key');
    }

    /**
     * All detection flags enabled (default for seeding / bypasses).
     *
     * @return array<string, bool>
     */
    public static function allEnabled(): array
    {
        return array_fill_keys(self::keys(), true);
    }

    /**
     * Resolve enabled map for a user based on their current plan.
     *
     * @return array<string, bool>
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return self::allEnabled();
        }

        if ($user->bypassesPlanLimits()) {
            return self::allEnabled();
        }

        return self::forPlan($user->currentPlan());
    }

    /**
     * @return array<string, bool>
     */
    public static function forPlan(?Plan $plan): array
    {
        $out = self::allEnabled();
        if ($plan === null) {
            return $out;
        }

        $flags = is_array($plan->feature_flags) ? $plan->feature_flags : [];
        foreach (self::keys() as $key) {
            if (! array_key_exists($key, $flags)) {
                continue;
            }
            $out[$key] = filter_var($flags[$key], FILTER_VALIDATE_BOOLEAN);
        }

        return $out;
    }

    public static function enabled(?User $user, string $key, bool $default = true): bool
    {
        $map = self::forUser($user);

        return (bool) ($map[$key] ?? $default);
    }

    /**
     * Merge all detection keys into a plan's feature_flags (preserving existing overrides).
     *
     * @param  array<string, mixed>|null  $existing
     * @return array<string, bool>
     */
    public static function mergeIntoFlags(?array $existing): array
    {
        $base = [];
        foreach ($existing ?? [] as $k => $v) {
            $base[(string) $k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }

        return array_merge(self::allEnabled(), $base);
    }

    /**
     * Clamp request/settings fields so disabled plan modules cannot be turned on.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, bool>  $allowed
     * @return array<string, mixed>
     */
    public static function clampSettingsData(array $data, array $allowed): array
    {
        $on = static fn (string $key): bool => (bool) ($allowed[$key] ?? true);

        if (! $on(self::VPN)) {
            $data['suspicious_vpn'] = 'allow';
        }
        if (! $on(self::PROXY)) {
            $data['suspicious_proxy'] = 'allow';
        }
        if (! $on(self::DATA_CENTER)) {
            $data['suspicious_data_center'] = 'allow';
        }
        if (! $on(self::ABNORMAL_RATE)) {
            $data['suspicious_abnormal_rate_limit'] = 'allow';
        }
        if (! $on(self::REPEATED_CLICK)) {
            $data['frequency_capping'] = false;
        }
        if (! $on(self::SUSPICIOUS_BEHAVIOR)) {
            $data['invalid_malicious_action'] = 'allow';
        }
        if (! $on(self::BOT)) {
            $data['invalid_bot_action'] = 'allow';
        }
        if (! $on(self::GEO_ALLOW)) {
            $data['out_of_geo_enabled'] = false;
        }
        if (! $on(self::GEO_BLOCK)) {
            $data['google_geo_block_enabled'] = false;
        }
        if (! $on(self::ALLOW_LIST)) {
            $data['allow_list_enabled'] = false;
        }
        if (! $on(self::BLOCK_LIST)) {
            $data['block_list_enabled'] = false;
        }
        if (! $on(self::SESSION_RECORDINGS)) {
            $data['session_recordings'] = false;
        }
        if (! $on(self::GOOGLE_EXCLUSION)) {
            $data['google_exclusion_enabled'] = false;
        }
        if (! $on(self::BEHAVIOR_CONTROL)) {
            $data['behavior_control_enabled'] = false;
        }
        if (! $on(self::PROFILES)) {
            $data['detection_profile'] = 'standard';
        }

        // Master suspicious switch only useful when at least one engine module remains.
        $anyEngine = $on(self::VPN) || $on(self::PROXY) || $on(self::DATA_CENTER)
            || $on(self::ABNORMAL_RATE) || $on(self::REPEATED_CLICK) || $on(self::SUSPICIOUS_BEHAVIOR)
            || $on(self::BOT) || $on(self::RAPID_CLICK) || $on(self::FREQUENCY_LIMITS);
        if (! $anyEngine) {
            $data['suspicious_enabled'] = false;
        }

        return $data;
    }

    /**
     * Module key mapped from Detection Engine card keys.
     */
    public static function engineModuleFlag(string $moduleKey): ?string
    {
        return match ($moduleKey) {
            'vpn' => self::VPN,
            'proxy' => self::PROXY,
            'data_center' => self::DATA_CENTER,
            'abnormal_rate_limit' => self::ABNORMAL_RATE,
            'repeated_click' => self::REPEATED_CLICK,
            'suspicious_behavior' => self::SUSPICIOUS_BEHAVIOR,
            default => null,
        };
    }
}
