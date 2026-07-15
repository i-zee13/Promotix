<?php

namespace App\Support;

class RiskLabels
{
    public const VALID = 'Valid';

    public const SUSPICIOUS = 'Suspicious';

    public const INVALID = 'Invalid';

    public const BLOCKED = 'Blocked';

    public const ALLOWED_OVERRIDE = 'Allowed Override';

    public const GOOGLE_INVALID = 'Google Invalid';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VALID,
            self::SUSPICIOUS,
            self::INVALID,
            self::BLOCKED,
            self::ALLOWED_OVERRIDE,
            self::GOOGLE_INVALID,
        ];
    }

    public static function cssClass(string $label): string
    {
        return match ($label) {
            self::VALID => 'risk-badge risk-badge--valid',
            self::SUSPICIOUS => 'risk-badge risk-badge--suspicious',
            self::INVALID => 'risk-badge risk-badge--invalid',
            self::BLOCKED => 'risk-badge risk-badge--blocked',
            self::ALLOWED_OVERRIDE => 'risk-badge risk-badge--allowed',
            self::GOOGLE_INVALID => 'risk-badge risk-badge--google',
            default => 'risk-badge',
        };
    }

    /**
     * Normalize mixed threat/action/manual signals into a single product label (UI-03).
     *
     * @param  array{
     *   is_allowlisted?: bool,
     *   is_blocked?: bool,
     *   manual_decision?: ?string,
     *   threat_group?: ?string,
     *   threat_type?: ?string,
     *   action_taken?: ?string,
     *   google_invalid?: bool,
     *   reasons?: list<string>
     * }  $context
     */
    public static function fromContext(array $context): string
    {
        if (! empty($context['is_allowlisted']) || ($context['manual_decision'] ?? null) === 'allowed') {
            return self::ALLOWED_OVERRIDE;
        }

        if (($context['manual_decision'] ?? null) === 'valid') {
            return self::VALID;
        }

        if (! empty($context['google_invalid'])) {
            return self::GOOGLE_INVALID;
        }

        if (
            ! empty($context['is_blocked'])
            || ($context['manual_decision'] ?? null) === 'blocked'
            || ($context['action_taken'] ?? null) === 'block'
            || strtolower((string) ($context['threat_group'] ?? '')) === 'blocked'
            || strtolower((string) ($context['threat_type'] ?? '')) === 'block'
        ) {
            return self::BLOCKED;
        }

        if (($context['manual_decision'] ?? null) === 'invalid') {
            return self::INVALID;
        }

        $reasons = array_map('strtolower', array_map('strval', $context['reasons'] ?? []));
        $suspiciousHints = ['vpn', 'proxy', 'data_center', 'datacenter', 'rapid_repeat', 'no_interaction', 'repeated_behavior'];
        $hasSuspicious = (bool) array_intersect($reasons, $suspiciousHints)
            || in_array(strtolower((string) ($context['threat_group'] ?? '')), ['vpn', 'proxy', 'data_center', 'datacenter', 'abnormal_rate_limit'], true);

        if (($context['action_taken'] ?? null) === 'flag' || ($context['threat_type'] ?? null) === 'flag') {
            return $hasSuspicious ? self::SUSPICIOUS : self::INVALID;
        }

        if (filled($context['threat_group'] ?? null) || filled($context['threat_type'] ?? null)) {
            return $hasSuspicious && ($context['action_taken'] ?? '') !== 'block'
                ? self::SUSPICIOUS
                : self::INVALID;
        }

        return self::VALID;
    }
}
