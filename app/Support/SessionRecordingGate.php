<?php

namespace App\Support;

use App\Models\DomainDetectionSetting;

class SessionRecordingGate
{
    /**
     * Whether the tracking tag should start a detailed session timeline recording.
     *
     * Session Recording toggle is authoritative: when OFF, no detailed timeline
     * is captured (aggregates may still come from other signals elsewhere).
     *
     * @param  array{action_taken?: string, threat_group?: ?string}  $detection
     */
    public static function shouldRecord(
        ?DomainDetectionSetting $settings,
        array $detection,
        bool $isPaidTraffic,
        bool $planBehaviorControl = true,
        bool $planSessionRecordings = true,
    ): bool {
        if ($settings === null) {
            return false;
        }

        if (! $settings->session_recordings || ! $planSessionRecordings) {
            return false;
        }

        // Toggle ON → capture detailed timeline for paid and organic visits.
        return true;
    }

    /**
     * Whether ingest may store a full event timeline for this domain.
     */
    public static function allowsIngest(
        ?DomainDetectionSetting $settings,
        bool $planSessionRecordings = true,
    ): bool {
        if ($settings === null) {
            return false;
        }

        return (bool) $settings->session_recordings && $planSessionRecordings;
    }
}
