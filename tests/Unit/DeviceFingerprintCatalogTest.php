<?php

namespace Tests\Unit;

use App\Support\DeviceFingerprintCatalog;
use PHPUnit\Framework\TestCase;

class DeviceFingerprintCatalogTest extends TestCase
{
    public function test_catalog_covers_spec_fields(): void
    {
        $keys = array_column(DeviceFingerprintCatalog::fields(), 'key');

        foreach ([
            'browser_family',
            'browser_major',
            'user_agent',
            'client_hints',
            'os_family',
            'os_version',
            'device_type',
            'screen_size',
            'pixel_ratio',
            'touch_points',
            'hardware_concurrency',
            'device_memory',
            'webgl_vendor',
            'webgl_renderer',
            'webgl_hash',
            'canvas_hash',
            'language',
            'timezone',
            'pointer_type',
            'api_profile',
        ] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_sanitize_keeps_known_keys_only(): void
    {
        $clean = DeviceFingerprintCatalog::sanitize([
            'browser_family' => 'Chrome',
            'browser_major' => '151',
            'evil' => 'drop-me',
            'webgl_hash' => 'wgl_8ac12',
        ]);

        $this->assertSame('Chrome', $clean['browser_family']);
        $this->assertSame('151', $clean['browser_major']);
        $this->assertSame('wgl_8ac12', $clean['webgl_hash']);
        $this->assertArrayNotHasKey('evil', $clean);
    }
}
