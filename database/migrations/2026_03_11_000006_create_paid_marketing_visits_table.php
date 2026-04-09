<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_marketing_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();

            $table->string('ip', 45)->index();
            $table->unsignedInteger('visits')->default(1);

            // Columns seen in "Detailed View" table
            $table->string('campaign')->nullable();
            $table->timestamp('last_click_at')->nullable();
            $table->string('threat_group')->nullable();
            $table->string('threat_type')->nullable();
            $table->string('country')->nullable();

            // Useful filters
            $table->string('platform')->nullable();
            $table->string('last_path')->nullable();

            $table->timestamps();

            $table->index(['domain_id', 'last_click_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_marketing_visits');
    }
};

