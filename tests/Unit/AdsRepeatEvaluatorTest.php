<?php

namespace Tests\Unit;

use App\Support\PaidAdvertising\AdsRepeatEvaluator;
use App\Support\PaidAdvertising\ResolvedPaidIdentity;
use PHPUnit\Framework\TestCase;

class AdsRepeatEvaluatorTest extends TestCase
{
    public function test_ip_only_two_clicks_is_supporting_not_decisive(): void
    {
        $identity = new ResolvedPaidIdentity(
            publicId: 'PID_TEST',
            visitorId: null,
            browserId: null,
            deviceId: null,
            fingerprintId: null,
            confidence: 0.40,
            confidenceBand: 'low',
        );

        $windows = [
            'ip' => $this->window(1), // prior 1 → current becomes 2
            'browser' => $this->window(0),
            'device' => $this->window(0),
            'paid_identity' => $this->window(0),
        ];

        $rules = (new AdsRepeatEvaluator)->evaluate($identity, $windows, [
            'daily_valid_click_limit' => 2,
            'require_combined_evidence' => false,
        ]);

        $codes = array_column($rules, 'rule_code');
        $this->assertContains('ADS_IP_2_60M', $codes);
        $this->assertNotContains('ADS_REPEAT_2_60M', $codes);
        $this->assertNotContains('ADS_REPEAT_3_60M', $codes);
        $this->assertFalse(collect($rules)->contains(fn ($r) => $r['can_block_alone']));
    }

    public function test_high_confidence_third_click_is_decisive(): void
    {
        $identity = new ResolvedPaidIdentity(
            publicId: 'PID_HI',
            visitorId: 'VID',
            browserId: 'BR',
            deviceId: 'DEV',
            fingerprintId: 'FP',
            confidence: 0.90,
            confidenceBand: 'high',
        );

        $windows = [
            'ip' => $this->window(2),
            'browser' => $this->window(2),
            'device' => $this->window(2),
            'paid_identity' => $this->window(2),
        ];

        $rules = (new AdsRepeatEvaluator)->evaluate($identity, $windows, [
            'daily_valid_click_limit' => 2,
        ]);

        $codes = array_column($rules, 'rule_code');
        $this->assertContains('ADS_REPEAT_3_60M', $codes);
        $decisive = collect($rules)->firstWhere('rule_code', 'ADS_REPEAT_3_60M');
        $this->assertTrue($decisive['can_block_alone']);
        $this->assertSame('block_identity', (new AdsRepeatEvaluator)->recommendedAction($rules));
    }

    public function test_very_strict_second_click_requires_very_high_confidence(): void
    {
        $high = new ResolvedPaidIdentity(
            publicId: 'PID_H',
            visitorId: 'V',
            browserId: 'B',
            deviceId: 'D',
            fingerprintId: 'F',
            confidence: 0.90,
            confidenceBand: 'high',
        );
        $veryHigh = new ResolvedPaidIdentity(
            publicId: 'PID_VH',
            visitorId: 'V',
            browserId: 'B',
            deviceId: 'D',
            fingerprintId: 'F',
            confidence: 0.97,
            confidenceBand: 'very_high',
        );

        $windows = [
            'ip' => $this->window(1),
            'browser' => $this->window(1),
            'device' => $this->window(1),
            'paid_identity' => $this->window(1),
        ];

        $strictThresholds = [
            'daily_valid_click_limit' => 1,
            'require_combined_evidence' => true,
        ];

        $highRules = (new AdsRepeatEvaluator)->evaluate($high, $windows, $strictThresholds);
        $vhRules = (new AdsRepeatEvaluator)->evaluate($veryHigh, $windows, $strictThresholds);

        $this->assertNotContains('ADS_REPEAT_2_60M', array_column($highRules, 'rule_code'));
        $this->assertContains('ADS_REPEAT_2_60M', array_column($vhRules, 'rule_code'));
    }

    /**
     * @return array<string, int>
     */
    private function window(int $sixty): array
    {
        return [
            '1m' => 0,
            '5m' => 0,
            '15m' => 0,
            '30m' => 0,
            '60m' => $sixty,
            '6h' => $sixty,
            '24h' => $sixty,
            '7d' => $sixty,
        ];
    }
}
