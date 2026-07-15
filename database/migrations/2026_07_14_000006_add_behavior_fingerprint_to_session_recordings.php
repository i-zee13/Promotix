<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_session_recordings') && ! Schema::hasColumn('visit_session_recordings', 'behavior_fingerprint')) {
            Schema::table('visit_session_recordings', function (Blueprint $table): void {
                $table->string('behavior_fingerprint', 64)->nullable()->index()->after('behavior_signals');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visit_session_recordings') && Schema::hasColumn('visit_session_recordings', 'behavior_fingerprint')) {
            Schema::table('visit_session_recordings', function (Blueprint $table): void {
                $table->dropColumn('behavior_fingerprint');
            });
        }
    }
};
