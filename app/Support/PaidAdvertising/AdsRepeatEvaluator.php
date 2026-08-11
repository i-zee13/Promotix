<?php

namespace App\Support\PaidAdvertising;

/**
 * Manual v3 Step 5 subset: ADS_REPEAT_* + ADS_IP_* rules that can run once
 * identity + rolling windows exist.
 *
 * Does not replace existing RAPID_REPEAT (2-minute IP policy); that stays for
 * backward compatibility. These rules use Manual-aligned 60m / 5m / 24h windows.
 */
class AdsRepeatEvaluator
{
    public const RULESET_VERSION = 'paid-risk-v4.0-google';

    /**
     * @param  array{ip: array<string,int>, browser: array<string,int>, device: array<string,int>, paid_identity: array<string,int>}  $windows
     * @param  array<string, int|bool>  $thresholds DetectionProfiles thresholds
     * @param  array{repeat_days?: int}  $context
     * @return list<array{
     *   rule_code: string,
     *   decision_type: string,
     *   can_block_alone: bool,
     *   base_points: int,
     *   max_points: int,
     *   recommended_action: string,
     *   evidence: array<string, mixed>
     * }>
     */
    public function evaluate(ResolvedPaidIdentity $identity, array $windows, array $thresholds = [], array $context = []): array
    {
        $profile = $this->campaignProfile($thresholds);
        $triggered = [];

        $id60 = max(
            (int) ($windows['paid_identity']['60m'] ?? 0),
            (int) ($windows['device']['60m'] ?? 0),
            (int) ($windows['browser']['60m'] ?? 0),
        );
        // Current click is not yet in windows count (caller passes prior counts).
        $identityClicksInclCurrent = $id60 + 1;
        $ip60 = ((int) ($windows['ip']['60m'] ?? 0)) + 1;
        $id5 = ((int) ($windows['device']['5m'] ?? 0)) + 1;
        $id15 = max(
            (int) ($windows['paid_identity']['15m'] ?? 0),
            (int) ($windows['device']['15m'] ?? 0),
            (int) ($windows['browser']['15m'] ?? 0),
        ) + 1;
        $id24 = max(
            (int) ($windows['paid_identity']['24h'] ?? 0),
            (int) ($windows['device']['24h'] ?? 0),
        ) + 1;
        $id7d = max(
            (int) ($windows['paid_identity']['7d'] ?? 0),
            (int) ($windows['device']['7d'] ?? 0),
        ) + 1;
        $repeatDays = (int) ($context['repeat_days'] ?? 0);

        // --- IP-only supporting / correlated (never identity-block by itself) ---
        if ($ip60 >= 2 && ! $identity->isMediumOrBetter()) {
            $triggered[] = $this->rule(
                'ADS_IP_2_60M',
                'supporting',
                false,
                15,
                25,
                'monitor',
                ['ip_clicks_60m' => $ip60]
            );
        }
        if ($ip60 >= 3 && ! $identity->isHigh()) {
            $triggered[] = $this->rule(
                'ADS_IP_3_60M',
                'correlated',
                false,
                25,
                35,
                'challenge',
                ['ip_clicks_60m' => $ip60]
            );
        }
        if ($ip60 >= 5) {
            $triggered[] = $this->rule(
                'ADS_IP_5_60M',
                'correlated',
                false,
                40,
                55,
                'challenge',
                ['ip_clicks_60m' => $ip60]
            );
        }

        // --- Identity-scoped repeat (Manual §14 / §8) ---
        if ($identity->isVeryHigh() && $identityClicksInclCurrent >= 2 && $profile === 'very_strict') {
            $triggered[] = $this->rule(
                'ADS_REPEAT_2_60M',
                'strict_decisive',
                true,
                35,
                45,
                'block_identity',
                [
                    'identity_clicks_60m' => $identityClicksInclCurrent,
                    'identity_confidence' => $identity->confidence,
                    'campaign_profile' => $profile,
                ]
            );
        }

        if ($identity->isHigh() && $identityClicksInclCurrent >= 3) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_3_60M',
                'decisive',
                true,
                60,
                70,
                'block_identity',
                [
                    'identity_clicks_60m' => $identityClicksInclCurrent,
                    'identity_confidence' => $identity->confidence,
                ]
            );
        }

        if ($identity->isMediumOrBetter() && $id5 >= 3) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_3_5M',
                'decisive',
                true,
                75,
                85,
                'block_identity',
                ['identity_clicks_5m' => $id5]
            );
        }

        if ($identity->isMediumOrBetter() && $id15 >= 3) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_3_15M',
                'decisive',
                true,
                65,
                75,
                'block_identity',
                ['identity_clicks_15m' => $id15]
            );
        }

        if ($identity->isMediumOrBetter() && $identityClicksInclCurrent >= 4) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_4_60M',
                'decisive',
                true,
                75,
                85,
                'block_identity',
                ['identity_clicks_60m' => $identityClicksInclCurrent]
            );
        }

        if ($identity->isMediumOrBetter() && $identityClicksInclCurrent >= 5) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_5_60M',
                'standalone',
                true,
                90,
                100,
                'block_identity',
                ['identity_clicks_60m' => $identityClicksInclCurrent]
            );
        }

        if ($identity->isMediumOrBetter() && $id24 >= 8) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_8_24H',
                'correlated',
                false,
                60,
                75,
                'challenge',
                ['identity_clicks_24h' => $id24]
            );
        }

        if ($identity->isHigh() && $id24 >= 10) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_10_24H',
                'decisive',
                true,
                75,
                90,
                'block_identity',
                ['identity_clicks_24h' => $id24]
            );
        }

        if ($identity->isMediumOrBetter() && $id7d >= 20) {
            $triggered[] = $this->rule(
                'ADS_REPEAT_20_7D',
                'correlated',
                false,
                70,
                90,
                'challenge',
                ['identity_clicks_7d' => $id7d]
            );
        }

        if ($identity->isMediumOrBetter() && $repeatDays >= 3) {
            $triggered[] = $this->rule(
                'ADS_PERSISTENT_REPEAT',
                'correlated',
                false,
                40,
                60,
                'challenge',
                ['repeat_days' => $repeatDays]
            );
        }

        if ($identity->knownFraud) {
            $triggered[] = $this->rule(
                'ADS_KNOWN_FRAUD_DEVICE',
                'standalone',
                true,
                100,
                100,
                'block_identity',
                ['known_fraud' => true]
            );
        }

        return $triggered;
    }

    /**
     * Map DetectionProfiles → Manual §18 campaign profile buckets.
     *
     * @param  array<string, int|bool>  $thresholds
     */
    public function campaignProfile(array $thresholds): string
    {
        $daily = (int) ($thresholds['daily_valid_click_limit'] ?? 2);
        $requireCombined = (bool) ($thresholds['require_combined_evidence'] ?? false);

        if ($requireCombined && $daily <= 1) {
            return 'very_strict';
        }
        if ($daily <= 1) {
            return 'strict';
        }
        if ($daily <= 2) {
            return 'balanced';
        }

        return 'moderate';
    }

    /**
     * Highest-priority action from triggered ADS rules.
     *
     * @param  list<array<string, mixed>>  $triggered
     */
    public function recommendedAction(array $triggered): string
    {
        $rank = [
            'block_identity' => 40,
            'challenge' => 20,
            'monitor' => 10,
            'allow' => 0,
        ];
        $best = 'allow';
        foreach ($triggered as $row) {
            $action = (string) ($row['recommended_action'] ?? 'allow');
            if (($rank[$action] ?? 0) > ($rank[$best] ?? 0)) {
                $best = $action;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{
     *   rule_code: string,
     *   decision_type: string,
     *   can_block_alone: bool,
     *   base_points: int,
     *   max_points: int,
     *   recommended_action: string,
     *   evidence: array<string, mixed>,
     *   ruleset_version: string
     * }
     */
    private function rule(
        string $code,
        string $decisionType,
        bool $alone,
        int $base,
        int $max,
        string $action,
        array $evidence,
    ): array {
        return [
            'rule_code' => $code,
            'decision_type' => $decisionType,
            'can_block_alone' => $alone,
            'base_points' => $base,
            'max_points' => $max,
            'recommended_action' => $action,
            'evidence' => $evidence,
            'ruleset_version' => self::RULESET_VERSION,
        ];
    }
}
