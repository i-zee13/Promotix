<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $menu = config('admin.menu', []);

        foreach ($menu as $slug => $item) {
            $routeName = $item['route'] ?? $slug;
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $item['label'] ?? $slug, 'route_name' => $routeName]
            );
        }

        $superAdmin = Role::updateOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Full access to all admin areas']
        );

        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // Default role for newly registered users:
        // grant only the dashboard permission so they land in the dashboard after registration.
        $defaultRole = Role::updateOrCreate(
            ['slug' => 'default-user'],
            ['name' => 'Default User', 'description' => 'Access to basic admin dashboard']
        );

        $dashboardPermission = Permission::where('slug', 'dashboard')->first();
        if ($dashboardPermission) {
            $defaultRole->permissions()->sync([$dashboardPermission->id]);
        }
    }
}
