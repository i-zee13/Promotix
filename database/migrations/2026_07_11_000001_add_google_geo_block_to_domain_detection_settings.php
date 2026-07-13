<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_detection_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('domain_detection_settings', 'google_geo_block_enabled')) {
                $table->boolean('google_geo_block_enabled')->default(false)->after('out_of_geo_audience');
            }
            if (! Schema::hasColumn('domain_detection_settings', 'google_geo_block_audience')) {
                $table->json('google_geo_block_audience')->nullable()->after('google_geo_block_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('domain_detection_settings', function (Blueprint $table) {
            if (Schema::hasColumn('domain_detection_settings', 'google_geo_block_audience')) {
                $table->dropColumn('google_geo_block_audience');
            }
            if (Schema::hasColumn('domain_detection_settings', 'google_geo_block_enabled')) {
                $table->dropColumn('google_geo_block_enabled');
            }
        });
    }
};
