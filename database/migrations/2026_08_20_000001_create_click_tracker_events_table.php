<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('click_tracker_events')) {
            return;
        }

        Schema::create('click_tracker_events', function (Blueprint $table): void {
            $table->id();
            $table->string('cxtrk_id', 32)->unique();
            $table->unsignedBigInteger('domain_id')->nullable()->index();
            $table->unsignedBigInteger('landing_visit_id')->nullable()->index();
            $table->string('click_id', 255)->nullable()->index();
            $table->string('click_id_type', 16)->nullable();
            $table->text('landing_url');
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('cx_account', 128)->nullable();
            $table->string('cx_campaign', 128)->nullable();
            $table->string('cx_adgroup', 128)->nullable();
            $table->string('cx_creative', 128)->nullable();
            $table->string('cx_keyword', 255)->nullable();
            $table->json('cx_registry')->nullable();
            $table->timestamp('tracked_at')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'click_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_tracker_events');
    }
};
