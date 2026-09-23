<?php

namespace Tests\Unit;

use App\Services\AudienceSignalService;
use Tests\TestCase;

class AudienceSignalServiceTest extends TestCase
{
    public function test_fires_only_on_final_invalid(): void
    {
        $svc = new AudienceSignalService;

        $invalid = $svc->decisionFromDetection([
            'traffic_status' => 'invalid',
            'action_taken' => 'block',
            'threat_score' => 94,
            'threat_group' => 'repeat_clicks',
            'is_paid_traffic' => true,
        ]);
        $this->assertTrue($invalid['fire']);
        $this->assertSame('cr_invalid_traffic', $invalid['event']);
        $this->assertSame('invalid', $invalid['traffic_verdict']);
        $this->assertNotEmpty($invalid['decision_id']);

        $organicInvalid = $svc->decisionFromDetection([
            'traffic_status' => 'invalid',
            'action_taken' => 'block',
            'threat_score' => 94,
            'is_paid_traffic' => false,
        ]);
        $this->assertFalse($organicInvalid['fire']);

        $suspicious = $svc->decisionFromDetection([
            'traffic_status' => 'suspicious',
            'action_taken' => 'flag',
            'threat_score' => 50,
            'is_paid_traffic' => true,
        ]);
        $this->assertFalse($suspicious['fire']);

        $valid = $svc->decisionFromDetection([
            'traffic_status' => 'valid',
            'action_taken' => 'allow',
            'is_paid_traffic' => true,
        ]);
        $this->assertFalse($valid['fire']);
    }

    public function test_fires_on_blocked_via_default_any_rule(): void
    {
        // Default preset is Match ANY: invalid OR blocked.
        $flags = (new AudienceSignalService)->clientFlags([
            'traffic_status' => 'suspicious',
            'action_taken' => 'block',
            'threat_score' => 70,
            'is_paid_traffic' => true,
        ]);

        $this->assertTrue($flags['fire_audience_event'] ?? false);
        $this->assertSame('blocked', $flags['audience_protection_action'] ?? null);
        $this->assertSame(AudienceSignalService::EVENT_VERSION, $flags['audience_event_version'] ?? null);
    }

    public function test_client_flags_use_canonical_contract(): void
    {
        $flags = (new AudienceSignalService)->clientFlags([
            'traffic_status' => 'invalid',
            'action_taken' => 'block',
            'threat_score' => 90,
            'threat_group' => 'blocked',
            'is_paid_traffic' => true,
        ]);

        $this->assertTrue($flags['fire_audience_event']);
        $this->assertSame('cr_invalid_traffic', $flags['audience_event']);
        $this->assertSame('invalid', $flags['audience_traffic_verdict']);
        $this->assertArrayHasKey('audience_decision_id', $flags);
        $this->assertArrayHasKey('audience_event_id', $flags);
        $this->assertSame('blocked', $flags['audience_protection_action']);
        $this->assertArrayHasKey('audience_id', $flags);
    }

    public function test_build_decision_params_omit_pii(): void
    {
        $params = (new AudienceSignalService)->buildDecisionParams([
            'traffic_status' => 'invalid',
            'action_taken' => 'block',
            'threat_score' => 88,
            'ip' => '1.2.3.4',
            'fingerprint' => 'fp-secret',
        ]);

        $this->assertArrayNotHasKey('ip', $params);
        $this->assertArrayNotHasKey('fingerprint', $params);
        $this->assertSame('invalid', $params['cr_traffic_verdict']);
        $this->assertSame('blocked', $params['cr_protection_action']);
    }
}
