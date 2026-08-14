<?php

namespace Tests\Unit;

use App\Models\DomainDetectionSetting;
use App\Support\SessionRecordingGate;
use Tests\TestCase;

class SessionRecordingGateTest extends TestCase
{
    private function settings(bool $sessionRecordings = true, bool $behaviorControl = false): DomainDetectionSetting
    {
        return new DomainDetectionSetting([
            'session_recordings' => $sessionRecordings,
            'detection_profile' => 'standard',
            'detection_thresholds' => [
                'behavior_control_enabled' => $behaviorControl,
            ],
        ]);
    }

    public function test_paid_allow_records_when_session_recordings_enabled(): void
    {
        $this->assertTrue(SessionRecordingGate::shouldRecord(
            $this->settings(sessionRecordings: true),
            ['action_taken' => 'allow', 'threat_group' => null],
            isPaidTraffic: true,
        ));
    }

    public function test_paid_allow_does_not_record_when_session_recordings_disabled(): void
    {
        $this->assertFalse(SessionRecordingGate::shouldRecord(
            $this->settings(sessionRecordings: false),
            ['action_taken' => 'allow', 'threat_group' => null],
            isPaidTraffic: true,
        ));
    }

    public function test_paid_allow_records_with_behavior_control_even_without_session_recordings(): void
    {
        $this->assertTrue(SessionRecordingGate::shouldRecord(
            $this->settings(sessionRecordings: false, behaviorControl: true),
            ['action_taken' => 'allow', 'threat_group' => null],
            isPaidTraffic: true,
        ));
    }

    public function test_organic_allow_does_not_record_without_threat(): void
    {
        $this->assertFalse(SessionRecordingGate::shouldRecord(
            $this->settings(sessionRecordings: true),
            ['action_taken' => 'allow', 'threat_group' => null],
            isPaidTraffic: false,
        ));
    }
}
