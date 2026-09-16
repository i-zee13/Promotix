<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table): void {
            if (! Schema::hasColumn('domains', 'ga4_detected_at')) {
                $table->timestamp('ga4_detected_at')->nullable()->after('ga4_api_secret');
            }
            if (! Schema::hasColumn('domains', 'gtm_detected_at')) {
                $table->timestamp('gtm_detected_at')->nullable()->after('gtm_container_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table): void {
            foreach (['ga4_detected_at', 'gtm_detected_at'] as $col) {
                if (Schema::hasColumn('domains', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
