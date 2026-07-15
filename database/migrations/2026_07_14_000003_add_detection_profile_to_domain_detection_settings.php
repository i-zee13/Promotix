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
            if (! Schema::hasColumn('domain_detection_settings', 'detection_profile')) {
                $table->string('detection_profile', 32)->default('standard')->after('suspicious_enabled');
            }
            if (! Schema::hasColumn('domain_detection_settings', 'detection_thresholds')) {
                $table->json('detection_thresholds')->nullable()->after('detection_profile');
            }
            if (! Schema::hasColumn('domain_detection_settings', 'fail_mode')) {
                $table->string('fail_mode', 16)->default('open')->after('detection_thresholds');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('domain_detection_settings')) {
            return;
        }

        Schema::table('domain_detection_settings', function (Blueprint $table): void {
            foreach (['detection_profile', 'detection_thresholds', 'fail_mode'] as $col) {
                if (Schema::hasColumn('domain_detection_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
