<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscriptions', 'grace_period_ends_at')) {
                    $table->timestamp('grace_period_ends_at')->nullable()->after('current_period_ends_at');
                }
                if (! Schema::hasColumn('subscriptions', 'protection_paused_at')) {
                    $table->timestamp('protection_paused_at')->nullable()->after('grace_period_ends_at');
                }
            });
        }

        if (! Schema::hasTable('automation_settings')) {
            Schema::create('automation_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('setting_key')->unique();
                $table->text('setting_value')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });

            $defaults = [
                ['trial_start_automation', '1', true],
                ['trial_expiry_automation', '1', true],
                ['auto_disable_after_trial', '1', true],
                ['trial_ending_email', '1', true],
                ['auto_charge_payment', '0', false],
                ['payment_reminder_email', '1', true],
                ['failed_payment_email', '1', true],
                ['auto_disable_after_failed_payment', '1', true],
                ['auto_reactivate_after_payment', '1', true],
                ['auto_generate_invoice', '1', true],
                ['auto_send_invoice_email', '0', false],
                ['auto_mark_invoice_paid', '1', true],
                ['auto_mark_invoice_failed', '1', true],
                ['payment_grace_days', '7', true],
                ['trial_days_default', '14', true],
                ['payment_retry_attempts', '3', true],
            ];

            foreach ($defaults as [$key, $value, $enabled]) {
                DB::table('automation_settings')->insert([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'is_enabled' => $enabled,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('label', 80)->nullable();
                $table->string('brand', 40)->nullable();
                $table->string('last_four', 4)->nullable();
                $table->string('exp_month', 2)->nullable();
                $table->string('exp_year', 4)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_temporary')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('login_histories')) {
            Schema::create('login_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('device', 120)->nullable();
                $table->string('browser', 120)->nullable();
                $table->string('location', 120)->nullable();
                $table->string('status', 32)->default('success');
                $table->string('event', 32)->default('login');
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('user_invites')) {
            Schema::create('user_invites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email');
                $table->string('name')->nullable();
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $table->string('token', 64)->unique();
                $table->string('status', 32)->default('pending');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['email', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invites');
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('automation_settings');

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                foreach (['grace_period_ends_at', 'protection_paused_at'] as $col) {
                    if (Schema::hasColumn('subscriptions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
