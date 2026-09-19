<?php

namespace Tests\Unit;

use App\Support\DeviceIdLabel;
use Tests\TestCase;

class DeviceIdLabelTest extends TestCase
{
    public function test_format_prefers_dev_token(): void
    {
        $this->assertSame('DEV_ABC123', DeviceIdLabel::format('DEV_ABC123', 'fp-other', '1.2.3.4'));
        $this->assertSame('DEV_ABC123', DeviceIdLabel::format('dev_ABC123'));
        $this->assertSame('DEV_ABC123', DeviceIdLabel::format('FP_ABC123'));
    }

    public function test_looks_like_device_id(): void
    {
        $this->assertTrue(DeviceIdLabel::looksLikeDeviceId('DEV_ABC123XYZ'));
        $this->assertTrue(DeviceIdLabel::looksLikeDeviceId('fp_abc'));
        $this->assertFalse(DeviceIdLabel::looksLikeDeviceId('1.2.3.4'));
        $this->assertFalse(DeviceIdLabel::looksLikeDeviceId('Cj0KCQjwn'));
    }

    public function test_search_needles_include_variants(): void
    {
        $needles = DeviceIdLabel::searchNeedles('DEV_AbC123');
        $this->assertContains('DEV_AbC123', $needles);
        $this->assertContains('DEV_ABC123', $needles);
        $this->assertContains('dev_AbC123', $needles);
        $this->assertContains('FP_AbC123', $needles);
        $this->assertContains('AbC123', $needles);
    }
}
