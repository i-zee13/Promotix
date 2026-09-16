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
            if (! Schema::hasColumn('domains', 'google_tag_detected_at')) {
                $table->timestamp('google_tag_detected_at')->nullable()->after('gtm_detected_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table): void {
            if (Schema::hasColumn('domains', 'google_tag_detected_at')) {
                $table->dropColumn('google_tag_detected_at');
            }
        });
    }
};
