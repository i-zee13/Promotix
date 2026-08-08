<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_session_recordings')) {
            return;
        }

        Schema::table('visit_session_recordings', function (Blueprint $table): void {
            if (! Schema::hasColumn('visit_session_recordings', 'last_cta_href')) {
                $table->string('last_cta_href', 500)->nullable()->after('scroll_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visit_session_recordings')) {
            return;
        }

        Schema::table('visit_session_recordings', function (Blueprint $table): void {
            if (Schema::hasColumn('visit_session_recordings', 'last_cta_href')) {
                $table->dropColumn('last_cta_href');
            }
        });
    }
};
