<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Support\PermissionGrouper;
use Tests\TestCase;

class PermissionGrouperTest extends TestCase
{
    public function test_groups_permissions_by_admin_menu_groups(): void
    {
        $permissions = collect([
            $this->permission(1, 'paid-marketing-dashboard', 'Dashboard'),
            $this->permission(2, 'bot-protection', 'Dashboard'),
            $this->permission(3, 'users', 'Users'),
        ]);

        $grouped = PermissionGrouper::group($permissions);

        $this->assertArrayHasKey('PAID ADVERTISING', $grouped);
        $this->assertArrayHasKey('BOT PROTECTION', $grouped);
        $this->assertArrayHasKey(PermissionGrouper::ADMIN_OPS_GROUP, $grouped);
        $this->assertSame('paid-marketing-dashboard', $grouped['PAID ADVERTISING']->first()->slug);
        $this->assertSame('users', $grouped[PermissionGrouper::ADMIN_OPS_GROUP]->first()->slug);
    }

    public function test_group_order_follows_config(): void
    {
        $order = PermissionGrouper::groupOrder();

        $this->assertSame('HOME', $order[0]);
        $this->assertSame('PAID ADVERTISING', $order[1]);
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
