<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_google_ads_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_ads_account_id')->constrained()->cascadeOnDelete();
            $table->string('protection_type')->default('ip_blocking'); // ip_blocking | pixel_guard
            $table->boolean('audience_exclusion_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'google_ads_account_id'], 'domain_google_ads_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_google_ads_mappings');
    }
};

