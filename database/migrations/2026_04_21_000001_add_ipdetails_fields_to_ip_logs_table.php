<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ip_logs', function (Blueprint $table) {
            $table->decimal('ipdetails_abuser_score', 6, 4)->nullable()->after('iphub_block_reason');
            $table->json('ipdetails_raw')->nullable()->after('ipdetails_abuser_score');
        });
    }

    public function down(): void
    {
        Schema::table('ip_logs', function (Blueprint $table) {
            $table->dropColumn(['ipdetails_abuser_score', 'ipdetails_raw']);
        });
    }
};

