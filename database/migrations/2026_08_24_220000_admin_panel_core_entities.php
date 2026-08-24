<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('name', 120);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 64)->unique();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_members')) {
            Schema::create('team_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['team_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('guidance_articles')) {
            Schema::create('guidance_articles', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->json('question_variants')->nullable();
                $table->longText('answer');
                $table->text('steps')->nullable();
                $table->string('related_page')->nullable();
                $table->string('department', 64)->nullable()->index();
                $table->string('keywords')->nullable();
                $table->string('role_visibility')->nullable();
                $table->string('package_visibility')->nullable();
                $table->boolean('is_published')->default(true)->index();
                $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('channel', 32)->default('dashboard');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('department', 64)->nullable();
                $table->string('status', 32)->default('open')->index();
                $table->json('transcript')->nullable();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->foreignId('ticket_id')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                if (! Schema::hasColumn('support_tickets', 'ticket_number')) {
                    $table->string('ticket_number', 32)->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('support_tickets', 'department')) {
                    $table->string('department', 64)->nullable()->index()->after('category');
                }
                if (! Schema::hasColumn('support_tickets', 'source')) {
                    $table->string('source', 32)->nullable()->after('department');
                }
                if (! Schema::hasColumn('support_tickets', 'context')) {
                    $table->json('context')->nullable();
                }
                if (! Schema::hasColumn('support_tickets', 'first_response_at')) {
                    $table->timestamp('first_response_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('plan_role')) {
            Schema::create('plan_role', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['plan_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('template_key')->nullable()->index();
                $table->string('recipient')->index();
                $table->string('status', 32)->default('queued');
                $table->string('provider_message_id')->nullable();
                $table->unsignedSmallInteger('retry_count')->default(0);
                $table->text('error')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('plan_role');
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('guidance_articles');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('departments');

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                foreach (['ticket_number', 'department', 'source', 'context', 'first_response_at'] as $col) {
                    if (Schema::hasColumn('support_tickets', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
