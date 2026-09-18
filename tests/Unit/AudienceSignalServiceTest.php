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
        ]);
        $this->assertTrue($invalid['fire']);
        $this->assertSame('cr_invalid_traffic', $invalid['event']);
        $this->assertSame('invalid', $invalid['traffic_verdict']);
        $this->assertNotEmpty($invalid['decision_id']);

        $suspicious = $svc->decisionFromDetection([
            'traffic_status' => 'suspicious',
            'action_taken' => 'flag',
            'threat_score' => 50,
        ]);
        $this->assertFalse($suspicious['fire']);

        $valid = $svc->decisionFromDetection([
            'traffic_status' => 'valid',
            'action_taken' => 'allow',
        ]);
        $this->assertFalse($valid['fire']);
    }

    public function test_client_flags_use_canonical_contract(): void
    {
        $flags = (new AudienceSignalService)->clientFlags([
            'traffic_status' => 'invalid',
            'action_taken' => 'block',
            'threat_score' => 90,
            'threat_group' => 'blocked',
        ]);

        $this->assertTrue($flags['fire_audience_event']);
        $this->assertSame('cr_invalid_traffic', $flags['audience_event']);
        $this->assertSame('invalid', $flags['audience_traffic_verdict']);
        $this->assertArrayHasKey('audience_decision_id', $flags);
        $this->assertArrayHasKey('audience_event_id', $flags);
    }

    public function test_does_not_fire_on_block_when_status_suspicious(): void
    {
        $flags = (new AudienceSignalService)->clientFlags([
            'traffic_status' => 'suspicious',
            'action_taken' => 'block',
            'threat_score' => 70,
        ]);

        $this->assertSame([], $flags);
    }
}
