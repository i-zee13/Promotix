<?php

namespace App\Support\PaidAdvertising;

/**
 * Google-only attribution integrity subset (Manual ADS_CLICKID_* / GCLID_*).
 * Meta / Microsoft skipped by product scope.
 */
class AdsAttributionEvaluator
{
    /**
     * @param  array{
     *   paid_id?: ?string,
     *   click_type?: ?string,
     *   duplicate_paid_click?: bool,
     *   is_paid_traffic?: bool
     * }  $attribution
     * @return list<array<string, mixed>>
     */
    public function evaluate(array $attribution): array
    {
        if (! ($attribution['is_paid_traffic'] ?? false)) {
            return [];
        }

        $triggered = [];
        $paidId = trim((string) ($attribution['paid_id'] ?? ''));
        $type = strtolower(trim((string) ($attribution['click_type'] ?? '')));

        if ($paidId === '') {
            $triggered[] = $this->rule(
                'ADS_CLICKID_MISSING',
                'supporting',
                false,
                5,
                10,
                'monitor',
                ['expected' => 'gclid|gbraid|wbraid']
            );

            return $triggered;
        }

        if ($type === 'gclid' && ! $this->looksLikeGclid($paidId)) {
            $triggered[] = $this->rule(
                'ADS_GCLID_MALFORMED',
                'correlated',
                false,
                10,
                20,
                'challenge',
                ['gclid_length' => strlen($paidId)]
            );
        }

        if ((bool) ($attribution['duplicate_paid_click'] ?? false)) {
            $code = match ($type) {
                'gbraid' => 'ADS_GBRAID_REPLAY',
                'wbraid' => 'ADS_WBRAID_REPLAY',
                default => 'ADS_GCLID_DUP',
            };
            $triggered[] = $this->rule(
                $code,
                'correlated',
                false,
                20,
                35,
                'challenge',
                [
                    'click_type' => $type !== '' ? $type : 'gclid',
                    'duplicate_paid_click' => true,
                ]
            );
        }

        return $triggered;
    }

    private function looksLikeGclid(string $value): bool
    {
        // Soft structural check — Google formats vary; reject only obvious garbage.
        if (strlen($value) < 20 || strlen($value) > 200) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $value);
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
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
            'ruleset_version' => 'paid-risk-v4.0-google',
        ];
    }
}
