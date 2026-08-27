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

    public function test_behavior_control_does_not_bypass_session_recording_toggle(): void
    {
        $this->assertFalse(SessionRecordingGate::shouldRecord(
            $this->settings(sessionRecordings: false, behaviorControl: true),
            ['action_taken' => 'allow', 'threat_group' => null],
            isPaidTraffic: true,
            planBehaviorControl: true,
            planSessionRecordings: true,
        ));
    }

    public function test_organic_allow_records_when_toggle_on(): void
    {
        $this->assertTrue(SessionRecordingGate::shouldRecord(
            $this->settings(sessionRecordings: true),
            ['action_taken' => 'allow', 'threat_group' => null],
            isPaidTraffic: false,
        ));
    }

    public function test_allows_ingest_requires_toggle_and_plan(): void
    {
        $this->assertTrue(SessionRecordingGate::allowsIngest($this->settings(true), true));
        $this->assertFalse(SessionRecordingGate::allowsIngest($this->settings(false), true));
        $this->assertFalse(SessionRecordingGate::allowsIngest($this->settings(true), false));
        $this->assertFalse(SessionRecordingGate::allowsIngest(null, true));
    }
}
