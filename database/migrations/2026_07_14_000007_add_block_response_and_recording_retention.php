<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domain_detection_settings')) {
            return;
        }

        Schema::table('domain_detection_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('domain_detection_settings', 'block_response')) {
                $table->string('block_response', 32)->default('hide')->after('fail_mode');
            }
            if (! Schema::hasColumn('domain_detection_settings', 'block_redirect_url')) {
                $table->string('block_redirect_url', 2048)->nullable()->after('block_response');
            }
            if (! Schema::hasColumn('domain_detection_settings', 'recording_retention_days')) {
                $table->unsignedSmallInteger('recording_retention_days')->default(30)->after('session_recordings');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('domain_detection_settings')) {
            return;
        }

        Schema::table('domain_detection_settings', function (Blueprint $table): void {
            foreach (['block_response', 'block_redirect_url', 'recording_retention_days'] as $col) {
                if (Schema::hasColumn('domain_detection_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
