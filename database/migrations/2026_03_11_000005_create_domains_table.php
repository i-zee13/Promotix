<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hostname')->index(); // example: storeeo.app

            // Keys used for client-side integrations (WP/GTM/manual).
            $table->string('domain_key')->unique();
            $table->string('secret_key')->unique();
            $table->string('authentication_key')->unique();

            // Feature/module connection flags (for the Domain Management grid).
            $table->boolean('tag_connected')->default(false);
            $table->boolean('paid_marketing_connected')->default(false);
            $table->boolean('bot_mitigation_connected')->default(false);
            $table->boolean('monitoring_only_mode')->default(false);

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hostname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};

