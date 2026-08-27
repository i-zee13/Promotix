<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_behavior_events')) {
            return;
        }

        Schema::create('visit_behavior_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id')->index();
            $table->unsignedBigInteger('recording_id')->nullable()->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('visitor_id', 128)->nullable()->index();
            $table->string('event_type', 40)->index();
            $table->string('page_url', 500)->nullable();
            $table->string('page_path', 500)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('element_text', 255)->nullable();
            $table->string('href', 500)->nullable();
            $table->string('element_id', 120)->nullable();
            $table->string('element_class', 255)->nullable();
            $table->string('link_type', 40)->nullable();
            $table->string('tel_number', 64)->nullable();
            $table->string('form_id', 120)->nullable();
            $table->string('form_name', 120)->nullable();
            $table->boolean('success')->nullable();
            $table->unsignedTinyInteger('scroll_depth')->nullable();
            $table->string('product_id', 120)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('order_id', 120)->nullable();
            $table->decimal('revenue', 12, 2)->nullable();
            $table->string('currency', 12)->nullable();
            $table->string('value', 64)->nullable();
            $table->unsignedInteger('relative_ms')->default(0);
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'session_id', 'event_type']);
            $table->index(['domain_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_behavior_events');
    }
};
