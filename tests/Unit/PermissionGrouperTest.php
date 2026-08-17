<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Support\PermissionGrouper;
use Tests\TestCase;

class PermissionGrouperTest extends TestCase
{
    public function test_groups_permissions_by_product_and_advanced_view(): void
    {
        $permissions = collect([
            $this->permission(1, 'paid-marketing-dashboard', 'Dashboard'),
            $this->permission(2, 'paid-marketing-detailed', 'Advanced View'),
            $this->permission(3, 'bot-protection', 'Dashboard'),
            $this->permission(4, 'users', 'Users'),
        ]);

        $grouped = PermissionGrouper::group($permissions);

        $this->assertArrayHasKey('PRODUCT', $grouped);
        $this->assertArrayHasKey('ADVANCED VIEW', $grouped);
        $this->assertArrayHasKey(PermissionGrouper::ADMIN_OPS_GROUP, $grouped);
        $this->assertTrue($grouped['PRODUCT']->contains(fn (Permission $p) => $p->slug === 'paid-marketing-dashboard'));
        $this->assertTrue($grouped['PRODUCT']->contains(fn (Permission $p) => $p->slug === 'bot-protection'));
        $this->assertSame('paid-marketing-detailed', $grouped['ADVANCED VIEW']->first()->slug);
        $this->assertSame('users', $grouped[PermissionGrouper::ADMIN_OPS_GROUP]->first()->slug);
    }

    public function test_group_order_follows_permission_groups_config(): void
    {
        $order = PermissionGrouper::groupOrder();

        $this->assertSame('PRODUCT', $order[0]);
        $this->assertSame('ADVANCED VIEW', $order[1]);
        $this->assertSame(PermissionGrouper::ADMIN_OPS_GROUP, $order[array_key_last($order)]);
    }

    private function permission(int $id, string $slug, string $name): Permission
    {
        $permission = new Permission([
            'slug' => $slug,
            'name' => $name,
            'route_name' => $slug,
        ]);
        $permission->id = $id;

        return $permission;
    }
}
