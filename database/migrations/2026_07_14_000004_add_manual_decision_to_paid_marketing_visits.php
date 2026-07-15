<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paid_marketing_visits')) {
            return;
        }

        Schema::table('paid_marketing_visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('paid_marketing_visits', 'manual_decision')) {
                $table->string('manual_decision', 32)->nullable()->after('threat_type');
            }
            if (! Schema::hasColumn('paid_marketing_visits', 'manual_decision_reason')) {
                $table->string('manual_decision_reason', 500)->nullable()->after('manual_decision');
            }
            if (! Schema::hasColumn('paid_marketing_visits', 'manual_decision_by')) {
                $table->unsignedBigInteger('manual_decision_by')->nullable()->after('manual_decision_reason');
            }
            if (! Schema::hasColumn('paid_marketing_visits', 'manual_decision_at')) {
                $table->timestamp('manual_decision_at')->nullable()->after('manual_decision_by');
            }
            if (! Schema::hasColumn('paid_marketing_visits', 'original_threat_group')) {
                $table->string('original_threat_group')->nullable()->after('manual_decision_at');
            }
            if (! Schema::hasColumn('paid_marketing_visits', 'original_threat_type')) {
                $table->string('original_threat_type')->nullable()->after('original_threat_group');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('paid_marketing_visits')) {
            return;
        }

        Schema::table('paid_marketing_visits', function (Blueprint $table): void {
            foreach ([
                'manual_decision',
                'manual_decision_reason',
                'manual_decision_by',
                'manual_decision_at',
                'original_threat_group',
                'original_threat_type',
            ] as $col) {
                if (Schema::hasColumn('paid_marketing_visits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
