<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ip_logs', function (Blueprint $table) {
            $table->id();
            
            $table->string('ip', 45)->unique();
            $table->text('user_agent')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_path')->nullable();
            $table->string('last_referrer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_logs');
    }
};

