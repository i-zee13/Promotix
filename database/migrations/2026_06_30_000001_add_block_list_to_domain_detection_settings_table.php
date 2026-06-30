<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_detection_settings', function (Blueprint $table) {
            $table->boolean('block_list_enabled')->default(false)->after('allow_list_ips');
            $table->text('block_list_ips')->nullable()->after('block_list_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('domain_detection_settings', function (Blueprint $table) {
            $table->dropColumn(['block_list_enabled', 'block_list_ips']);
        });
    }
};
