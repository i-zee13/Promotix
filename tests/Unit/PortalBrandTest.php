<?php

namespace Tests\Unit;

use App\Support\PortalBrand;
use Tests\TestCase;

class PortalBrandTest extends TestCase
{
    public function test_clickronix_host_uses_clickronix_name(): void
    {
        $this->assertSame('Clickronix', PortalBrand::name('app.clickronix.com'));
        $this->assertSame('Clickronix', PortalBrand::name('dashboard.clickronix.com'));
    }

    public function test_clickguard_host_uses_clickguard_name(): void
    {
        $this->assertSame('ClickGuard', PortalBrand::name('app.clickguard.com'));
        $this->assertSame('ClickGuard', PortalBrand::name('www.clickguard.io'));
    }

    public function test_unknown_host_falls_back_to_app_name(): void
    {
        $this->assertSame((string) config('app.name', 'Digital Promotix'), PortalBrand::name('admin.digitalpromotix.com'));
    }

    public function test_localize_copy_rewrites_product_names(): void
    {
        $this->assertStringContainsString(
            'ClickGuard',
            PortalBrand::localizeCopy('Open Clickronix → Domains', 'app.clickguard.com')
        );
        $this->assertStringContainsString(
            'Clickronix',
            PortalBrand::localizeCopy('Open ClickGuard → Domains', 'app.clickronix.com')
        );
    }
}
