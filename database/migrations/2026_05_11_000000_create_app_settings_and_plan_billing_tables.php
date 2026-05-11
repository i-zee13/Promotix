<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('group', 60)->default('general')->index();
                $table->string('key', 120)->unique();
                $table->string('label')->nullable();
                $table->text('description')->nullable();
                $table->string('type', 24)->default('string');
                $table->longText('value')->nullable();
                $table->boolean('is_public')->default(false)->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'plan_id')) {
                    $table->foreignId('plan_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'receipt_path')) {
                    $table->string('receipt_path')->nullable()->after('masked_payment');
                }
                if (! Schema::hasColumn('payments', 'receipt_original_name')) {
                    $table->string('receipt_original_name')->nullable()->after('receipt_path');
                }
                if (! Schema::hasColumn('payments', 'bank_reference')) {
                    $table->string('bank_reference')->nullable()->after('receipt_original_name');
                }
                if (! Schema::hasColumn('payments', 'notes')) {
                    $table->text('notes')->nullable()->after('bank_reference');
                }
                if (! Schema::hasColumn('payments', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('paid_at');
                }
                if (! Schema::hasColumn('payments', 'verified_by_id')) {
                    $table->foreignId('verified_by_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('verified_by_id');
                }
                if (! Schema::hasColumn('payments', 'rejection_reason')) {
                    $table->string('rejection_reason')->nullable()->after('rejected_at');
                }
            });
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscriptions', 'is_trial')) {
                    $table->boolean('is_trial')->default(false)->after('status')->index();
                }
                if (! Schema::hasColumn('subscriptions', 'last_payment_id')) {
                    $table->foreignId('last_payment_id')->nullable()->after('metadata')->constrained('payments')->nullOnDelete();
                }
            });
        }

        $this->seedDefaultSettings();
        $this->seedDefaultPlans();
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                foreach (['is_trial', 'last_payment_id'] as $column) {
                    if (Schema::hasColumn('subscriptions', $column)) {
                        if ($column === 'last_payment_id') {
                            $table->dropForeign(['last_payment_id']);
                        }
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                foreach (['plan_id', 'verified_by_id'] as $fk) {
                    if (Schema::hasColumn('payments', $fk)) {
                        $table->dropForeign([$fk]);
                    }
                }
                $columns = array_filter([
                    'plan_id', 'receipt_path', 'receipt_original_name', 'bank_reference', 'notes',
                    'verified_at', 'verified_by_id', 'rejected_at', 'rejection_reason',
                ], fn ($c) => Schema::hasColumn('payments', $c));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('app_settings');
    }

    private function seedDefaultSettings(): void
    {
        $defaults = [
            ['group' => 'trial',    'key' => 'trial.enabled',        'label' => 'Enable free trial on signup', 'type' => 'boolean', 'value' => '1', 'is_public' => true],
            ['group' => 'trial',    'key' => 'trial.days',           'label' => 'Free trial duration (days)',  'type' => 'integer', 'value' => '14', 'is_public' => true],
            ['group' => 'trial',    'key' => 'trial.plan_slug',      'label' => 'Plan applied during trial',   'type' => 'string',  'value' => 'starter', 'is_public' => true],
            ['group' => 'bank',     'key' => 'bank.account_name',    'label' => 'Bank account holder name',    'type' => 'string',  'value' => '', 'is_public' => true],
            ['group' => 'bank',     'key' => 'bank.account_number',  'label' => 'Bank account number / IBAN',  'type' => 'string',  'value' => '', 'is_public' => true],
            ['group' => 'bank',     'key' => 'bank.bank_name',       'label' => 'Bank name',                   'type' => 'string',  'value' => '', 'is_public' => true],
            ['group' => 'bank',     'key' => 'bank.swift',           'label' => 'SWIFT / BIC',                 'type' => 'string',  'value' => '', 'is_public' => true],
            ['group' => 'bank',     'key' => 'bank.instructions',    'label' => 'Payment instructions',        'type' => 'text',    'value' => 'Please use your registered email as the payment reference.', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.support_email','label' => 'Support email',              'type' => 'string',  'value' => 'support@promotix.local', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.company_name','label' => 'Company name',                'type' => 'string',  'value' => 'Promotix', 'is_public' => true],
        ];

        foreach ($defaults as $row) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    private function seedDefaultPlans(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        $plans = [
            [
                'name' => 'Starter', 'slug' => 'starter', 'tier' => 'starter',
                'price_cents' => 1900, 'currency' => 'USD', 'billing_interval' => 'monthly',
                'trial_days' => 14,
                'feature_limits' => json_encode([
                    'domain_limit' => 3,
                    'users_limit' => 2,
                    'visit_retention_days' => 30,
                ]),
                'feature_flags' => json_encode(['paid_marketing' => true, 'bot_protection' => true]),
                'is_active' => true,
            ],
            [
                'name' => 'Pro', 'slug' => 'pro', 'tier' => 'pro',
                'price_cents' => 4900, 'currency' => 'USD', 'billing_interval' => 'monthly',
                'trial_days' => 14,
                'feature_limits' => json_encode([
                    'domain_limit' => 10,
                    'users_limit' => 10,
                    'visit_retention_days' => 90,
                ]),
                'feature_flags' => json_encode(['paid_marketing' => true, 'bot_protection' => true, 'integrations' => true]),
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise', 'slug' => 'enterprise', 'tier' => 'enterprise',
                'price_cents' => 19900, 'currency' => 'USD', 'billing_interval' => 'monthly',
                'trial_days' => 14,
                'feature_limits' => json_encode([
                    'domain_limit' => 100,
                    'users_limit' => 100,
                    'visit_retention_days' => 365,
                ]),
                'feature_flags' => json_encode(['paid_marketing' => true, 'bot_protection' => true, 'integrations' => true, 'priority_support' => true]),
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                array_merge($plan, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
};
