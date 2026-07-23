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

        // Default role for newly registered customers: full customer menu access.
        // Onboarding (trial + card) is enforced separately by EnsureOnboardingComplete.
        $defaultRole = Role::updateOrCreate(
            ['slug' => 'default-user'],
            ['name' => 'Default User', 'description' => 'Customer portal access after onboarding']
        );

        $defaultRole->permissions()->sync(Permission::pluck('id'));
    }
}
