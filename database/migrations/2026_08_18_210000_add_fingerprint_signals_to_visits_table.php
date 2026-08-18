<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'fingerprint_signals')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->json('fingerprint_signals')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'fingerprint_signals')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->dropColumn('fingerprint_signals');
            });
        }
    }
};
