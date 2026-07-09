<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['key' => 'google_ads_integration', 'name' => 'Enable Google Ads Integration', 'description' => 'Track and optimize Google Ads campaigns.', 'enabled' => true],
            ['key' => 'session_recording', 'name' => 'Enable Session Recording (Future)', 'description' => 'Record user sessions for better insights.', 'enabled' => false],
        ] as $flag) {
            DB::table('feature_flags')->updateOrInsert(
                ['key' => $flag['key']],
                array_merge($flag, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('feature_flags')->whereIn('key', ['google_ads_integration', 'session_recording'])->delete();
    }
};
