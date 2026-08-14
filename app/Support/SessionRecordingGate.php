<?php

namespace App\Support;

use App\Models\DomainDetectionSetting;

class SessionRecordingGate
{
    /**
     * Whether the tracking tag should start a session recording for this visit.
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

        $thresholds = DetectionProfiles::thresholdsFor(
            $settings->detection_profile,
            is_array($settings->detection_thresholds) ? $settings->detection_thresholds : null,
        );

        if (
            $isPaidTraffic
            && (bool) ($thresholds['behavior_control_enabled'] ?? false)
            && $planBehaviorControl
        ) {
            return true;
        }

        if (! $settings->session_recordings || ! $planSessionRecordings) {
            return false;
        }

        // CTA/tel metrics need recordings for normal paid visits too.
        if ($isPaidTraffic) {
            return true;
        }

        if (($detection['action_taken'] ?? 'allow') === 'allow') {
            return false;
        }

        $group = strtolower((string) ($detection['threat_group'] ?? ''));

        return $group === 'malicious'
            || in_array($group, ['vpn', 'proxy', 'data_center', 'datacenter', 'abnormal_rate_limit'], true);
    }
}
