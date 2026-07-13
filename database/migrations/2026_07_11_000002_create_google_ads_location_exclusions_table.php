<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_ads_location_exclusions')) {
            return;
        }

        Schema::create('google_ads_location_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('geo_level', 20); // country|state|city
            $table->string('country_code', 2);
            $table->string('country_name')->nullable();
            $table->string('state_code', 20)->nullable();
            $table->string('state_name')->nullable();
            $table->string('city_name')->nullable();
            $table->string('rule_key', 191);
            $table->string('google_criterion_id', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'rule_key'], 'gads_loc_excl_domain_rule_uq');
            $table->index(['domain_id', 'sync_status', 'is_active'], 'gads_loc_excl_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_location_exclusions');
    }
};
