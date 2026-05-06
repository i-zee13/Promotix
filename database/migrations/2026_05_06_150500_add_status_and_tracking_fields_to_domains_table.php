<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('hostname');
            $table->string('gtm_container_id', 32)->nullable()->after('authentication_key');
            $table->json('tracking_params')->nullable()->after('gtm_container_id');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['status', 'gtm_container_id', 'tracking_params']);
        });
    }
};
