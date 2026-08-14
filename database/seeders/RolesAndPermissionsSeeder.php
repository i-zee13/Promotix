<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $menu = config('admin.menu', []);
        $routePermissions = config('admin.route_permission', []);

        foreach ($menu as $slug => $item) {
            $routeName = $item['route'] ?? $slug;
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => PermissionCatalog::displayName($slug, $item['label'] ?? $slug), 'route_name' => $routeName]
            );
        }

        // Ensure every route_permission slug exists (some are not in the flat menu).
        foreach (array_unique(array_values($routePermissions)) as $slug) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => PermissionCatalog::displayName($slug, Str::headline(str_replace('-', ' ', $slug))),
                    'route_name' => $slug,
                ]
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
