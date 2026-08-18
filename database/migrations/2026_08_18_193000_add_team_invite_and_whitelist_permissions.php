<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['slug' => 'team-invite', 'name' => 'Team invite', 'route_name' => 'team.invite'],
            ['slug' => 'provider-ip-whitelist', 'name' => 'Provider / IP whitelist', 'route_name' => 'super-admin.settings.whitelist'],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name'], 'route_name' => $row['route_name']]
            );
            $ids[] = $permission->id;
        }

        $defaultRole = Role::query()->where('slug', 'default-user')->first();
        if ($defaultRole && $ids !== []) {
            $defaultRole->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        Permission::query()->whereIn('slug', ['team-invite', 'provider-ip-whitelist'])->delete();
    }
};
