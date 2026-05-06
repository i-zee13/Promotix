<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_detection_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('invalid_bot_action')->default('block');
            $table->string('invalid_malicious_action')->default('block');
            $table->boolean('suspicious_enabled')->default(true);
            $table->json('suspicious_matrix')->nullable(); // vpn/proxy/datacenter/rate_limit actions
            $table->boolean('session_recordings')->default(false);
            $table->boolean('frequency_capping')->default(false);
            $table->boolean('out_of_geo_enabled')->default(false);
            $table->json('out_of_geo_countries')->nullable();
            $table->boolean('allow_list_enabled')->default(false);
            $table->text('allow_list_ips')->nullable();
            $table->string('audience_exclusion_event')->default('exclude_all_threat_groups_auto');
            $table->timestamps();

            $table->unique('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_detection_settings');
    }
};

