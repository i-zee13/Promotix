<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('direct_ads_integrations')) {
            return;
        }

        Schema::create('direct_ads_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('account_label')->nullable();
            $table->string('account_id', 120)->nullable();
            $table->string('tag_id', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_ads_integrations');
    }
};
