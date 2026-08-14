<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'browser_version')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->string('browser_version', 40)->nullable()->after('browser');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'browser_version')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->dropColumn('browser_version');
            });
        }
    }
};
