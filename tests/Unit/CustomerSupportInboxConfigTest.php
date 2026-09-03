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
}
