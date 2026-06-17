<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visits')) {
            return;
        }

        Schema::table('visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('visits', 'gbraid')) {
                $table->string('gbraid', 255)->nullable()->after('gclid')->index();
            }
            if (! Schema::hasColumn('visits', 'wbraid')) {
                $table->string('wbraid', 255)->nullable()->after('gbraid')->index();
            }
            if (! Schema::hasColumn('visits', 'google_click_type')) {
                $table->string('google_click_type', 16)->nullable()->after('wbraid')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visits')) {
            return;
        }

        Schema::table('visits', function (Blueprint $table): void {
            foreach (['google_click_type', 'wbraid', 'gbraid'] as $column) {
                if (Schema::hasColumn('visits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
