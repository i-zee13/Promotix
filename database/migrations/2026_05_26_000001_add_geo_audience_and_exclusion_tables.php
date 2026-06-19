<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('domain_detection_settings') && ! Schema::hasColumn('domain_detection_settings', 'out_of_geo_audience')) {
            Schema::table('domain_detection_settings', function (Blueprint $table): void {
                $table->json('out_of_geo_audience')->nullable()->after('out_of_geo_countries');
            });
        }

        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            Schema::create('google_ads_ip_exclusions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->string('ip', 45)->index();
                $table->string('threat_group', 40)->nullable();
                $table->string('exclusion_mode', 40)->default('exclude_all_threat_groups_auto');
                $table->string('sync_status', 20)->default('pending')->index();
                $table->timestamp('synced_at')->nullable();
                $table->text('sync_error')->nullable();
                $table->timestamps();
                $table->unique(['domain_id', 'ip']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('domain_detection_settings') && Schema::hasColumn('domain_detection_settings', 'out_of_geo_audience')) {
            Schema::table('domain_detection_settings', function (Blueprint $table): void {
                $table->dropColumn('out_of_geo_audience');
            });
        }

        Schema::dropIfExists('google_ads_ip_exclusions');
    }
};
