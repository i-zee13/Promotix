<?php

namespace Tests\Unit;

use App\Support\DetectionPlanFeatures;
use PHPUnit\Framework\TestCase;

class DetectionPlanFeaturesTest extends TestCase
{
    public function test_clamp_disables_forbidden_modules(): void
    {
        $allowed = DetectionPlanFeatures::allEnabled();
        $allowed[DetectionPlanFeatures::VPN] = false;
        $allowed[DetectionPlanFeatures::BEHAVIOR_CONTROL] = false;
        $allowed[DetectionPlanFeatures::GEO_ALLOW] = false;

        $clamped = DetectionPlanFeatures::clampSettingsData([
            'suspicious_vpn' => 'block',
            'suspicious_proxy' => 'block',
            'behavior_control_enabled' => true,
            'out_of_geo_enabled' => true,
            'invalid_bot_action' => 'block',
            'invalid_malicious_action' => 'block',
            'suspicious_data_center' => 'block',
            'suspicious_abnormal_rate_limit' => 'block',
            'detection_profile' => 'extreme',
        ], $allowed);

        $this->assertSame('allow', $clamped['suspicious_vpn']);
        $this->assertSame('block', $clamped['suspicious_proxy']);
        $this->assertFalse($clamped['behavior_control_enabled']);
        $this->assertFalse($clamped['out_of_geo_enabled']);
    }

    public function test_merge_preserves_existing_false_flags(): void
    {
        $merged = DetectionPlanFeatures::mergeIntoFlags([
            'ad_protection' => true,
            DetectionPlanFeatures::VPN => false,
        ]);

        $this->assertTrue($merged['ad_protection']);
        $this->assertFalse($merged[DetectionPlanFeatures::VPN]);
        $this->assertTrue($merged[DetectionPlanFeatures::PROXY]);
    }
}
