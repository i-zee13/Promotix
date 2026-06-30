<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return;
        }

        if (! Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
            Schema::table('google_ads_ip_exclusions', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('exclusion_mode')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('google_ads_ip_exclusions') && Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
            Schema::table('google_ads_ip_exclusions', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
