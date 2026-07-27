<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $menu = config('admin.menu', []);
        $routePermissions = config('admin.route_permission', []);

        foreach ($menu as $slug => $item) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $item['label'] ?? $slug,
                    'route_name' => $item['route'] ?? $slug,
                ]
            );
        }

        foreach (array_unique(array_values($routePermissions)) as $slug) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline(str_replace('-', ' ', $slug)),
                    'route_name' => $slug,
                ]
            );
        }

        $defaultRole = Role::query()->where('slug', 'default-user')->first();
        if ($defaultRole) {
            $defaultRole->permissions()->sync(Permission::query()->pluck('id'));
        }
    }

    public function down(): void
    {
        // Non-destructive: leave permissions as-is on rollback.
    }
};
