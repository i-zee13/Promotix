<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\User;

/**
 * Workspace (non-detection) plan gates: team invite, provider whitelist, cross-domain.
 */
class WorkspacePlanFeatures
{
    public const TEAM_INVITE = 'team_invite';

    public const PROVIDER_WHITELIST = 'provider_ip_whitelist';

    public const CROSS_DOMAIN = 'cross_domain';

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => self::TEAM_INVITE,
                'label' => 'Team invite',
                'description' => 'Person+plus header control to invite teammates. Default on for Enterprise, Advanced, and Custom.',
            ],
            [
                'key' => self::PROVIDER_WHITELIST,
                'label' => 'Provider / IP whitelist',
                'description' => 'Treat Google / Bing / Meta ranges as trusted so they are not blocked.',
            ],
            [
                'key' => self::CROSS_DOMAIN,
                'label' => 'Cross-domain intelligence',
                'description' => 'Show Cross-domain card on Detection Settings. Also requires Super Admin → Integrations → Cross-domain toggle On.',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::catalog(), 'key');
    }

    public static function enabled(?User $user, string $key): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->bypassesPlanLimits()) {
            return true;
        }

        $plan = $user->currentPlan();
        $flags = is_array($plan?->feature_flags) ? $plan->feature_flags : [];
        if (array_key_exists($key, $flags)) {
            return filter_var($flags[$key], FILTER_VALIDATE_BOOLEAN);
        }

        return self::defaultEnabled($plan, $key);
    }

    public static function defaultEnabled(?Plan $plan, string $key): bool
    {
        if ($plan === null) {
            return false;
        }

        $tier = strtolower(trim((string) $plan->tier));
        $name = strtolower(trim((string) $plan->name));
        $isPremiumTier = $plan->is_custom
            || in_array($tier, ['enterprise', 'custom', 'premium', 'advanced'], true)
            || str_contains($name, 'enterprise')
            || str_contains($name, 'advanced')
            || str_contains($name, 'custom');

        return match ($key) {
            // Premium defaults — Cross-domain stays off until Super Admin checks the plan box
            // (platform Integrations toggle is a separate master switch).
            self::TEAM_INVITE, self::PROVIDER_WHITELIST => $isPremiumTier,
            self::CROSS_DOMAIN => false,
            default => false,
        };
    }
}
