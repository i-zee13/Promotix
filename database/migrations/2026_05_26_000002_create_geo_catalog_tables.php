<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('geo_countries')) {
            Schema::create('geo_countries', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 2)->unique();
                $table->string('name', 120);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('geo_states')) {
            Schema::create('geo_states', function (Blueprint $table): void {
                $table->id();
                $table->string('country_code', 2)->index();
                $table->string('code', 12);
                $table->string('name', 120);
                $table->timestamps();
                $table->unique(['country_code', 'code']);
            });
        }

        if (! Schema::hasTable('geo_cities')) {
            Schema::create('geo_cities', function (Blueprint $table): void {
                $table->id();
                $table->string('country_code', 2)->index();
                $table->string('state_code', 12)->index();
                $table->string('name', 120);
                $table->timestamps();
                $table->index(['country_code', 'state_code', 'name']);
            });
        }

        if (! Schema::hasTable('visit_session_recordings')) {
            Schema::create('visit_session_recordings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('visit_id')->nullable()->index();
                $table->string('session_id', 128)->nullable()->index();
                $table->string('ip', 45)->index();
                $table->string('threat_group', 40)->nullable();
                $table->unsignedSmallInteger('duration_ms')->default(0);
                $table->text('page_url')->nullable();
                $table->json('events');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_session_recordings');
        Schema::dropIfExists('geo_cities');
        Schema::dropIfExists('geo_states');
        Schema::dropIfExists('geo_countries');
    }
};
