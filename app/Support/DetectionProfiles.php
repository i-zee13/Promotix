<?php

namespace App\Support;

class DetectionProfiles
{
    public const STANDARD = 'standard';

    public const ADVANCED = 'advanced';

    public const EXTREME = 'extreme';

    public const MARKETING = 'marketing';

    /**
     * @return array<string, array{
     *   label: string,
     *   summary: string,
     *   false_positive_risk: string,
     *   recommended: string,
     *   thresholds: array{
     *     rapid_window_seconds: int,
     *     rapid_flag_at: int,
     *     rapid_block_at: int,
     *     daily_valid_click_limit: int,
     *     require_combined_evidence: bool
     *   }
     * }>
     */
    public static function catalog(): array
    {
        return [
            self::STANDARD => [
                'label' => 'Standard',
                'summary' => 'Balanced defaults for most campaigns.',
                'false_positive_risk' => 'Low–medium',
                'recommended' => 'General paid traffic protection.',
                'thresholds' => [
                    'rapid_window_seconds' => 120,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 2,
                    'daily_valid_click_limit' => 2,
                    'require_combined_evidence' => false,
                ],
            ],
            self::ADVANCED => [
                'label' => 'Advanced',
                'summary' => 'Stricter rate limits and stronger VPN/proxy weighting.',
                'false_positive_risk' => 'Medium',
                'recommended' => 'High-value campaigns with known fraud pressure.',
                'thresholds' => [
                    'rapid_window_seconds' => 120,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 2,
                    'daily_valid_click_limit' => 1,
                    'require_combined_evidence' => false,
                ],
            ],
            self::EXTREME => [
                'label' => 'Extreme',
                'summary' => 'Combines repeat clicks, reputation, VPN/proxy, and datacenter signals into a scored decision.',
                'false_positive_risk' => 'Higher',
                'recommended' => 'Severe abuse. Prefer combined evidence over a single weak signal.',
                'thresholds' => [
                    'rapid_window_seconds' => 180,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 2,
                    'daily_valid_click_limit' => 1,
                    'require_combined_evidence' => true,
                ],
            ],
            self::MARKETING => [
                'label' => 'Marketing-optimized',
                'summary' => 'Protects campaigns while reducing false positives for legitimate revisits.',
                'false_positive_risk' => 'Lowest',
                'recommended' => 'Brands with frequent returning customers.',
                'thresholds' => [
                    'rapid_window_seconds' => 90,
                    'rapid_flag_at' => 1,
                    'rapid_block_at' => 3,
                    'daily_valid_click_limit' => 4,
                    'require_combined_evidence' => true,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $overrides
     * @return array{
     *   rapid_window_seconds: int,
     *   rapid_flag_at: int,
     *   rapid_block_at: int,
     *   daily_valid_click_limit: int,
     *   require_combined_evidence: bool
     * }
     */
    public static function thresholdsFor(?string $profile, ?array $overrides = null): array
    {
        $catalog = self::catalog();
        $key = is_string($profile) && isset($catalog[$profile]) ? $profile : self::STANDARD;
        $base = $catalog[$key]['thresholds'];
        if (! is_array($overrides) || $overrides === []) {
            return $base;
        }

        return array_merge($base, array_intersect_key($overrides, $base));
    }
}
