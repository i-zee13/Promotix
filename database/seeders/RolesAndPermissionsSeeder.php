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
    }
}
