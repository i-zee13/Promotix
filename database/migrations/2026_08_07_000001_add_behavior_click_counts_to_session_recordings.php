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
            if (! Schema::hasColumn('visit_session_recordings', 'cta_clicks')) {
                $table->unsignedSmallInteger('cta_clicks')->default(0)->after('behavior_fingerprint');
            }
            if (! Schema::hasColumn('visit_session_recordings', 'tel_clicks')) {
                $table->unsignedSmallInteger('tel_clicks')->default(0)->after('cta_clicks');
            }
            if (! Schema::hasColumn('visit_session_recordings', 'page_changes')) {
                $table->unsignedSmallInteger('page_changes')->default(0)->after('tel_clicks');
            }
            if (! Schema::hasColumn('visit_session_recordings', 'scroll_count')) {
                $table->unsignedSmallInteger('scroll_count')->default(0)->after('page_changes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visit_session_recordings')) {
            return;
        }

        Schema::table('visit_session_recordings', function (Blueprint $table): void {
            foreach (['cta_clicks', 'tel_clicks', 'page_changes', 'scroll_count'] as $col) {
                if (Schema::hasColumn('visit_session_recordings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
