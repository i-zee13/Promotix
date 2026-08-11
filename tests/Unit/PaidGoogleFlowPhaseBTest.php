<?php

namespace Tests\Unit;

use App\Support\PaidAdvertising\AdsAttributionEvaluator;
use App\Support\PaidAdvertising\IpExclusionEligibilityGate;
use App\Support\PaidAdvertising\PaidTrafficClassifier;
use App\Support\PaidAdvertising\ResolvedPaidIdentity;
use PHPUnit\Framework\TestCase;

class PaidGoogleFlowPhaseBTest extends TestCase
{
    public function test_duplicate_gclid_is_correlated_not_decisive(): void
    {
        $rules = (new AdsAttributionEvaluator)->evaluate([
            'is_paid_traffic' => true,
            'paid_id' => str_repeat('A', 40),
            'click_type' => 'gclid',
            'duplicate_paid_click' => true,
        ]);

        $this->assertContains('ADS_GCLID_DUP', array_column($rules, 'rule_code'));
        $this->assertFalse(collect($rules)->contains(fn ($r) => $r['can_block_alone']));
    }

    public function test_classifier_decisive_becomes_invalid_without_waiting_for_85(): void
    {
        $identity = new ResolvedPaidIdentity(
            publicId: 'PID_X',
            visitorId: 'V',
            browserId: 'B',
            deviceId: 'D',
            fingerprintId: 'F',
            confidence: 0.97,
            confidenceBand: 'very_high',
        );

        $classified = (new PaidTrafficClassifier)->classify(
            ['threat_score' => 35, 'action_taken' => 'allow'],
            [[
                'rule_code' => 'ADS_REPEAT_3_60M',
                'can_block_alone' => true,
                'base_points' => 60,
                'recommended_action' => 'block_identity',
            ]],
            $identity,
        );

        $this->assertSame('invalid', $classified['traffic_status']);
        $this->assertSame('block', $classified['action']);
        $this->assertGreaterThanOrEqual(60, $classified['paid_risk_score']);
    }

    public function test_exclusion_gate_suppresses_low_confidence_without_decisive(): void
    {
        $identity = new ResolvedPaidIdentity(
            publicId: 'PID_L',
            visitorId: null,
            browserId: null,
            deviceId: null,
            fingerprintId: null,
            confidence: 0.45,
            confidenceBand: 'low',
        );

        $gate = new IpExclusionEligibilityGate;
        $result = $gate->evaluate(1, '203.0.113.9', $identity, [
            'action_taken' => 'block',
            'traffic_status' => 'invalid',
            'paid_risk_score' => 70,
            'ads_detections' => [],
        ]);

        $this->assertFalse($result['eligible']);
        $this->assertSame('suppressed', $result['status']);
    }
}
