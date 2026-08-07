<?php

namespace App\Support;

class DetectionProfiles
{
    public const STANDARD = 'standard';

    public const ADVANCED = 'advanced';

    public const EXTREME = 'extreme';

    public const MARKETING = 'marketing';

    /**
     * Shared threshold keys merged into every profile (UI + evaluator).
     *
     * @return array<string, int|bool>
     */
    public static function sharedThresholdDefaults(): array
    {
        return [
            'hourly_valid_click_limit' => 3,
            'weekly_valid_click_limit' => 100,
            'monthly_valid_click_limit' => 300,
            'behavior_control_enabled' => false,
            'require_combined_evidence' => false,
        ];
    }

    /**
     * @return list<string>
     */
    public static function thresholdKeys(): array
    {
        return [
            'rapid_window_seconds',
            'rapid_flag_at',
            'rapid_block_at',
            'hourly_valid_click_limit',
            'daily_valid_click_limit',
            'weekly_valid_click_limit',
            'monthly_valid_click_limit',
            'require_combined_evidence',
            'behavior_control_enabled',
        ];
    }

    /**
     * @return array<string, array{
     *   label: string,
     *   summary: string,
     *   false_positive_risk: string,
     *   recommended: string,
     *   thresholds: array<string, int|bool>
     * }>
     */
    public static function catalog(): array
    {
        $shared = self::sharedThresholdDefaults();

        return [
            self::STANDARD => [
                'label' => 'Standard',
                'summary' => 'Balanced defaults for most campaigns.',
                'false_positive_risk' => 'Low–medium',
                'recommended' => 'General paid traffic protection.',
                'thresholds' => array_merge($shared, [
                    'rapid_window_seconds' => 120,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 2,
                    'daily_valid_click_limit' => 2,
                    'require_combined_evidence' => false,
                ]),
            ],
            self::ADVANCED => [
                'label' => 'Advanced',
                'summary' => 'Stricter rate limits and stronger VPN/proxy weighting.',
                'false_positive_risk' => 'Medium',
                'recommended' => 'High-value campaigns with known fraud pressure.',
                'thresholds' => array_merge($shared, [
                    'rapid_window_seconds' => 120,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 2,
                    'daily_valid_click_limit' => 1,
                    'require_combined_evidence' => false,
                ]),
            ],
            self::EXTREME => [
                'label' => 'Extreme',
                'summary' => 'Combines repeat clicks, reputation, VPN/proxy, and datacenter signals into a scored decision.',
                'false_positive_risk' => 'Higher',
                'recommended' => 'Severe abuse. Prefer combined evidence over a single weak signal.',
                'thresholds' => array_merge($shared, [
                    'rapid_window_seconds' => 180,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 2,
                    'daily_valid_click_limit' => 1,
                    'require_combined_evidence' => true,
                ]),
            ],
            self::MARKETING => [
                'label' => 'Marketing-optimized',
                'summary' => 'Protects campaigns while reducing false positives for legitimate revisits.',
                'false_positive_risk' => 'Lowest',
                'recommended' => 'Brands with frequent returning customers.',
                'thresholds' => array_merge($shared, [
                    'rapid_window_seconds' => 90,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 3,
                    'daily_valid_click_limit' => 4,
                    'require_combined_evidence' => true,
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $overrides
     * @return array<string, int|bool>
     */
    public static function thresholdsFor(?string $profile, ?array $overrides = null): array
    {
        $catalog = self::catalog();
        $key = is_string($profile) && isset($catalog[$profile]) ? $profile : self::STANDARD;
        $base = $catalog[$key]['thresholds'];
        if (! is_array($overrides) || $overrides === []) {
            return $base;
        }

        $allow = array_flip(self::thresholdKeys());
        $filtered = array_intersect_key($overrides, $allow);

        $merged = array_merge($base, $filtered);
        $merged['behavior_control_enabled'] = filter_var(
            $merged['behavior_control_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $merged['require_combined_evidence'] = filter_var(
            $merged['require_combined_evidence'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        foreach ([
            'rapid_window_seconds',
            'rapid_flag_at',
            'rapid_block_at',
            'hourly_valid_click_limit',
            'daily_valid_click_limit',
            'weekly_valid_click_limit',
            'monthly_valid_click_limit',
        ] as $intKey) {
            $merged[$intKey] = (int) ($merged[$intKey] ?? $base[$intKey] ?? 0);
        }

        return $merged;
    }
}
