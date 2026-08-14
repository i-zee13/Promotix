<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ip_logs')) {
            return;
        }

        Schema::table('ip_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ip_logs', 'intel_region')) {
                $table->string('intel_region', 120)->nullable()->after('intel_country_name');
            }
            if (! Schema::hasColumn('ip_logs', 'intel_city')) {
                $table->string('intel_city', 120)->nullable()->after('intel_region');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ip_logs')) {
            return;
        }

        Schema::table('ip_logs', function (Blueprint $table): void {
            foreach (['intel_city', 'intel_region'] as $col) {
                if (Schema::hasColumn('ip_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
