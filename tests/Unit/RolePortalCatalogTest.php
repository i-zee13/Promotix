<?php

namespace Tests\Unit;

use App\Support\RolePortalCatalog;
use PHPUnit\Framework\TestCase;

class RolePortalCatalogTest extends TestCase
{
    public function test_portals_are_separate(): void
    {
        $keys = array_column(RolePortalCatalog::portals(), 'key');
        $this->assertSame(['user', 'admin'], $keys);
    }

    public function test_user_pages_do_not_include_admin_pages(): void
    {
        $userKeys = array_column(RolePortalCatalog::userPages(), 'key');
        $adminKeys = array_column(RolePortalCatalog::adminPages(), 'key');
        $this->assertEmpty(array_intersect($userKeys, $adminKeys));
        $this->assertContains('traffic_control', $userKeys);
        $this->assertContains('roles_create', $adminKeys);
    }

    public function test_permission_slugs_only_for_granted_pages(): void
    {
        $slugs = RolePortalCatalog::permissionSlugsForPageAccess([
            'overview' => 'view',
            'domains' => 'full',
            'dashboard' => 'none',
        ]);
        $this->assertContains('dashboard', $slugs);
        $this->assertContains('domain-management', $slugs);
        $this->assertNotContains('paid-marketing-dashboard', $slugs);
    }
}
