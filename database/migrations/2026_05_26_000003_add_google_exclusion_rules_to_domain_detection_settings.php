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
            if (! Schema::hasColumn('domain_detection_settings', 'google_exclusion_rules')) {
                $table->json('google_exclusion_rules')->nullable()->after('audience_exclusion_event');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('domain_detection_settings')) {
            return;
        }

        Schema::table('domain_detection_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('domain_detection_settings', 'google_exclusion_rules')) {
                $table->dropColumn('google_exclusion_rules');
            }
        });
    }
};
