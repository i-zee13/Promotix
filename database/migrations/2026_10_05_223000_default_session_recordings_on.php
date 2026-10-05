<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domain_detection_settings')
            || ! Schema::hasColumn('domain_detection_settings', 'session_recordings')) {
            return;
        }

        DB::table('domain_detection_settings')
            ->where('session_recordings', false)
            ->update(['session_recordings' => true]);

        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE domain_detection_settings MODIFY session_recordings TINYINT(1) NOT NULL DEFAULT 1');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE domain_detection_settings ALTER COLUMN session_recordings SET DEFAULT true');
            }
        } catch (\Throwable) {
            // Default change is best-effort; row values above are what matter.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('domain_detection_settings')
            || ! Schema::hasColumn('domain_detection_settings', 'session_recordings')) {
            return;
        }

        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE domain_detection_settings MODIFY session_recordings TINYINT(1) NOT NULL DEFAULT 0');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE domain_detection_settings ALTER COLUMN session_recordings SET DEFAULT false');
            }
        } catch (\Throwable) {
            //
        }
    }
};
