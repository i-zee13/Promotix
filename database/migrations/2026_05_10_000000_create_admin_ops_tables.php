<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_automation_jobs')) {
            Schema::create('admin_automation_jobs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('schedule_cron')->nullable();
                $table->string('schedule_label')->nullable();
                $table->string('queue')->default('default');
                $table->string('status')->default('active')->index();
                $table->json('config')->nullable();
                $table->timestamp('last_ran_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_job_runs')) {
            Schema::create('admin_job_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_automation_job_id')->constrained()->cascadeOnDelete();
                $table->string('status')->default('queued')->index();
                $table->unsignedInteger('attempt')->default(1);
                $table->longText('output_log')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_integration_settings')) {
            Schema::create('admin_integration_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('display_name');
                $table->string('provider')->default('custom')->index();
                $table->boolean('enabled')->default(false);
                $table->json('settings')->nullable();
                $table->text('secret_payload')->nullable();
                $table->unsignedInteger('key_version')->default(1);
                $table->string('status')->default('not_configured')->index();
                $table->timestamp('last_rotated_at')->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'name']);
            });
        }

        if (! Schema::hasTable('admin_webhooks')) {
            Schema::create('admin_webhooks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('url');
                $table->json('events')->nullable();
                $table->string('secret', 128);
                $table->boolean('is_active')->default(true)->index();
                $table->string('last_delivery_status')->nullable();
                $table->timestamp('last_delivered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject');
                $table->string('status')->default('open')->index();
                $table->string('priority')->default('medium')->index();
                $table->string('category')->nullable();
                $table->longText('body')->nullable();
                $table->timestamp('sla_due_at')->nullable()->index();
                $table->timestamp('escalated_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->longText('body');
                $table->boolean('is_agent_reply')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('admin_webhooks');
        Schema::dropIfExists('admin_integration_settings');
        Schema::dropIfExists('admin_job_runs');
        Schema::dropIfExists('admin_automation_jobs');
    }
};
