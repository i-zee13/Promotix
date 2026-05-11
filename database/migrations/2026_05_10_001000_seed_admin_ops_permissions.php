<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach ([
            ['name' => 'Traffic Logs', 'slug' => 'traffic-bot-logs', 'route_name' => 'traffic-bot-logs'],
            ['name' => 'Automation', 'slug' => 'automation', 'route_name' => 'automation'],
            ['name' => 'Integrations', 'slug' => 'integrations', 'route_name' => 'integrations'],
            ['name' => 'Support System', 'slug' => 'support-system', 'route_name' => 'support-system'],
            ['name' => 'Security Logs', 'slug' => 'security-logs', 'route_name' => 'security-logs'],
            ['name' => 'System Settings', 'slug' => 'system-settings', 'route_name' => 'system-settings'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                array_merge($permission, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('slug', ['traffic-bot-logs', 'automation', 'integrations', 'support-system', 'security-logs', 'system-settings'])
                ->delete();
        }
    }
};
