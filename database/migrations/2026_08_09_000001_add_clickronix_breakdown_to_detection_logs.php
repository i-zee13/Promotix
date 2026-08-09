<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('detection_logs')) {
            return;
        }

        Schema::table('detection_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('detection_logs', 'clickronix_breakdown')) {
                $table->json('clickronix_breakdown')->nullable()->after('reasons');
            }
            if (! Schema::hasColumn('detection_logs', 'risk_level')) {
                $table->string('risk_level', 32)->nullable()->after('threat_score');
            }
            if (! Schema::hasColumn('detection_logs', 'ruleset_version')) {
                $table->string('ruleset_version', 64)->nullable()->after('clickronix_breakdown');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('detection_logs')) {
            return;
        }

        Schema::table('detection_logs', function (Blueprint $table): void {
            foreach (['clickronix_breakdown', 'risk_level', 'ruleset_version'] as $column) {
                if (Schema::hasColumn('detection_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
