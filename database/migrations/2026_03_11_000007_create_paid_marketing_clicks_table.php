<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_marketing_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paid_marketing_visit_id')->constrained('paid_marketing_visits')->cascadeOnDelete();

            $table->timestamp('clicked_at')->nullable();

            // Fields shown inside the Click Details modal
            $table->string('ip', 45)->nullable();
            $table->string('country')->nullable();
            $table->timestamp('last_click_at')->nullable();
            $table->string('threat_group')->nullable();
            $table->string('campaign')->nullable();
            $table->string('campaignr')->nullable(); // keeping as-is from screenshot label (can rename later)

            $table->string('browser_name')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('os')->nullable();
            $table->string('paid_id')->nullable();
            $table->text('path')->nullable();
            $table->string('keyword')->nullable();

            $table->timestamps();
            $table->index(['paid_marketing_visit_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_marketing_clicks');
    }
};

