<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visits') || Schema::hasColumn('visits', 'gclid')) {
            return;
        }

        Schema::table('visits', function (Blueprint $table): void {
            $table->string('gclid', 255)->nullable()->after('utm_term')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visits') || ! Schema::hasColumn('visits', 'gclid')) {
            return;
        }

        Schema::table('visits', function (Blueprint $table): void {
            $table->dropColumn('gclid');
        });
    }
};
