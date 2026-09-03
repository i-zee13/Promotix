<?php

namespace Tests\Unit;

use Tests\TestCase;

class CustomerSupportInboxConfigTest extends TestCase
{
    public function test_customer_support_routes_are_not_aliased_to_super_admin(): void
    {
        $redirects = config('super-admin.legacy_route_redirects');

        $this->assertArrayNotHasKey('support-system', $redirects);
        $this->assertArrayNotHasKey('support-system.show', $redirects);
        $this->assertArrayNotHasKey('support-system.create', $redirects);
        $this->assertArrayNotHasKey('support-system.store', $redirects);
        $this->assertArrayNotHasKey('support-system.reply', $redirects);
    }

    public function test_customer_sidebar_does_not_list_support_page(): void
    {
        $this->assertArrayNotHasKey('support-system', config('admin.menu'));
        $siteGroup = collect(config('admin.groups'))->firstWhere('label', 'SITE MANAGEMENT');
        $this->assertIsArray($siteGroup);
        $this->assertArrayNotHasKey('support-system', $siteGroup['items'] ?? []);
    }
}
