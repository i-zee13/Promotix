<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'is_duplicate_paid_click')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->boolean('is_duplicate_paid_click')->default(false)->index()->after('is_paid_traffic');
            });
        }

        if (Schema::hasTable('visit_session_recordings') && ! Schema::hasColumn('visit_session_recordings', 'behavior_signals')) {
            Schema::table('visit_session_recordings', function (Blueprint $table): void {
                $table->json('behavior_signals')->nullable()->after('events');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'is_duplicate_paid_click')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->dropColumn('is_duplicate_paid_click');
            });
        }

        if (Schema::hasTable('visit_session_recordings') && Schema::hasColumn('visit_session_recordings', 'behavior_signals')) {
            Schema::table('visit_session_recordings', function (Blueprint $table): void {
                $table->dropColumn('behavior_signals');
            });
        }
    }
};
