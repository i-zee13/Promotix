<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tracking_scripts')) {
            Schema::create('tracking_scripts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->string('script_key', 64)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('domain_settings')) {
            Schema::create('domain_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->unique()->constrained()->cascadeOnDelete();
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visits')) {
            Schema::create('visits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->string('session_id', 128)->nullable()->index();
                $table->string('ip', 45)->index();
                $table->string('country', 8)->nullable()->index();
                $table->string('device', 40)->nullable();
                $table->string('browser', 80)->nullable();
                $table->string('os', 60)->nullable();
                $table->text('url')->nullable();
                $table->text('referrer')->nullable();
                $table->string('utm_source')->nullable();
                $table->string('utm_medium')->nullable();
                $table->string('utm_campaign')->nullable();
                $table->string('utm_term')->nullable();
                $table->boolean('is_paid_traffic')->default(false)->index();
                $table->boolean('is_invalid_traffic')->default(false)->index();
                $table->timestamp('visited_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ip_sessions')) {
            Schema::create('ip_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->string('session_id', 128)->index();
                $table->string('ip', 45)->index();
                $table->unsignedInteger('hits')->default(0);
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();
                $table->unique(['domain_id', 'session_id']);
            });
        }

        if (! Schema::hasTable('analytics_hourly')) {
            Schema::create('analytics_hourly', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->dateTime('bucket_hour')->index();
                $table->unsignedBigInteger('total_visits')->default(0);
                $table->unsignedBigInteger('paid_visits')->default(0);
                $table->unsignedBigInteger('invalid_visits')->default(0);
                $table->timestamps();
                $table->unique(['domain_id', 'bucket_hour']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_hourly');
        Schema::dropIfExists('ip_sessions');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('domain_settings');
        Schema::dropIfExists('tracking_scripts');
    }
};
