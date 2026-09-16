<?php

namespace Tests\Unit;

use App\Models\ClickronixDevice;
use App\Services\ClickronixDeviceService;
use App\Support\PaidAdvertising\PaidDeviceFingerprinter;
use PHPUnit\Framework\TestCase;

class ClickronixDeviceIntelligenceTest extends TestCase
{
    public function test_device_id_prefers_persistent_token_over_fingerprint(): void
    {
        $fpA = PaidDeviceFingerprinter::fingerprintId('canvas:same', 'B1', 'Mozilla/5.0 (iPhone)', 'en');
        $fpB = PaidDeviceFingerprinter::fingerprintId('canvas:other', 'B2', 'Mozilla/5.0 (iPhone)', 'en');

        $token = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $dev1 = PaidDeviceFingerprinter::deviceId($fpA, 'Mozilla/5.0 (iPhone)', $token, 9);
        $dev2 = PaidDeviceFingerprinter::deviceId($fpB, 'Mozilla/5.0 (Android)', $token, 9);

        $this->assertSame($dev1, $dev2);
        $this->assertStringStartsWith('DEV_', $dev1);
    }

    public function test_fingerprint_alone_does_not_equal_token_device(): void
    {
        $fp = PaidDeviceFingerprinter::fingerprintId('canvas:x', 'B', 'Mozilla/5.0 (Windows)', 'en');
        $fromFp = PaidDeviceFingerprinter::deviceId($fp, 'Mozilla/5.0 (Windows)');
        $fromToken = PaidDeviceFingerprinter::deviceId($fp, 'Mozilla/5.0 (Windows)', 'token-stable-001', 1);

        $this->assertNotSame($fromFp, $fromToken);
    }

    public function test_exclusion_rule_requires_combined_conditions(): void
    {
        $service = new ClickronixDeviceService;
        $device = new ClickronixDevice([
            'paid_click_count' => 4,
            'conversion_count' => 0,
            'device_confidence' => 0.96,
            'ip_change_count' => 3,
            'automation_detected' => false,
            'invalid_click_count' => 0,
            'datacenter' => false,
            'proxy' => false,
            'vpn' => false,
            'ip_count' => 4,
            'risk_score' => 0,
        ]);

        $decision = $service->evaluateExclusion($device);
        $this->assertTrue($decision['exclusion_candidate']);
        $this->assertSame('Suspicious Repeat Clicker', $decision['risk_label']);
        $this->assertSame('ip_rotation', $decision['risk_reason']);
    }

    public function test_invalid_clicks_strong_signal_labels_repeat_non_converter(): void
    {
        $service = new ClickronixDeviceService;
        $device = new ClickronixDevice([
            'paid_click_count' => 4,
            'conversion_count' => 0,
            'device_confidence' => 0.96,
            'ip_change_count' => 1,
            'automation_detected' => false,
            'invalid_click_count' => 3,
            'datacenter' => false,
            'proxy' => false,
            'vpn' => false,
            'ip_count' => 2,
            'risk_score' => 0,
        ]);

        $decision = $service->evaluateExclusion($device);
        $this->assertTrue($decision['exclusion_candidate']);
        $this->assertSame('Repeat Non-Converter', $decision['risk_label']);
    }

    public function test_converted_visitor_is_never_excluded(): void
    {
        $service = new ClickronixDeviceService;
        $device = new ClickronixDevice([
            'paid_click_count' => 8,
            'conversion_count' => 1,
            'device_confidence' => 0.99,
            'ip_change_count' => 5,
            'automation_detected' => true,
            'risk_score' => 90,
        ]);

        $decision = $service->evaluateExclusion($device);
        $this->assertFalse($decision['exclusion_candidate']);
        $this->assertSame('Converted', $decision['risk_label']);
    }

    public function test_low_confidence_never_auto_excludes(): void
    {
        $service = new ClickronixDeviceService;
        $device = new ClickronixDevice([
            'paid_click_count' => 5,
            'conversion_count' => 0,
            'device_confidence' => 0.55,
            'ip_change_count' => 4,
            'automation_detected' => true,
            'risk_score' => 0,
        ]);

        $decision = $service->evaluateExclusion($device);
        $this->assertFalse($decision['exclusion_candidate']);
    }
}
