<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_detection_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('domain_detection_settings', 'control_mode')) {
                $table->string('control_mode', 40)->default('mixed')->after('frequency_capping');
            }
        });
    }

    public function down(): void
    {
        Schema::table('domain_detection_settings', function (Blueprint $table) {
            if (Schema::hasColumn('domain_detection_settings', 'control_mode')) {
                $table->dropColumn('control_mode');
            }
        });
    }
};
