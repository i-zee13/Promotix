<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Users — profile fields collected at signup.
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone', 40)->nullable()->after('email');
                }
                if (! Schema::hasColumn('users', 'company_name')) {
                    $table->string('company_name', 160)->nullable()->after('phone');
                }
                if (! Schema::hasColumn('users', 'website_url')) {
                    $table->string('website_url', 255)->nullable()->after('company_name');
                }
            });

            // Backfill `email_verified_at` for legacy users so the new onboarding gate
            // does not lock out anyone created before this migration.
            DB::table('users')
                ->whereNull('email_verified_at')
                ->update(['email_verified_at' => now()]);
        }

        // 2) Email verification 6-digit OTP table (one active code per email).
        if (! Schema::hasTable('email_verification_codes')) {
            Schema::create('email_verification_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('code_hash');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 3) Refresh trial.days default to 7 and ensure trial.plan_slug exists.
        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->where('key', 'trial.days')->update([
                'value' => '7',
                'updated_at' => now(),
            ]);
        }

        // 4) Refresh plan seed to match Figma: Starter $99 / Pro $149 / Advanced $349.
        if (Schema::hasTable('plans')) {
            $plans = [
                [
                    'name' => 'Starter', 'slug' => 'starter', 'tier' => 'starter',
                    'price_cents' => 9900, 'currency' => 'USD', 'billing_interval' => 'monthly',
                    'trial_days' => 7, 'is_active' => true,
                    'feature_limits' => json_encode([
                        'domain_limit' => 1,
                        'users_limit' => 1,
                        'visits_limit' => 5000,
                        'visit_retention_days' => 30,
                    ]),
                    'feature_flags' => json_encode([
                        'ad_protection' => true, 'bot_protection' => true,
                        'marketing_optimization' => true, 'consent_mode' => false,
                    ]),
                ],
                [
                    'name' => 'Pro', 'slug' => 'pro', 'tier' => 'pro',
                    'price_cents' => 14900, 'currency' => 'USD', 'billing_interval' => 'monthly',
                    'trial_days' => 7, 'is_active' => true,
                    'feature_limits' => json_encode([
                        'domain_limit' => 5,
                        'users_limit' => 5,
                        'visits_limit' => 10000,
                        'visit_retention_days' => 90,
                    ]),
                    'feature_flags' => json_encode([
                        'ad_protection' => true, 'bot_protection' => true,
                        'marketing_optimization' => true, 'pixel_guard' => true,
                        'multi_domain' => true, 'agency_permissions' => true,
                        'account_overview' => true, 'white_label_reports' => true,
                        'consent_mode' => false,
                    ]),
                ],
                [
                    'name' => 'Advanced', 'slug' => 'advanced', 'tier' => 'advanced',
                    'price_cents' => 34900, 'currency' => 'USD', 'billing_interval' => 'monthly',
                    'trial_days' => 7, 'is_active' => true,
                    'feature_limits' => json_encode([
                        'domain_limit' => 20,
                        'users_limit' => 20,
                        'visits_limit' => 50000,
                        'visit_retention_days' => 365,
                    ]),
                    'feature_flags' => json_encode([
                        'ad_protection' => true, 'bot_protection' => true,
                        'marketing_optimization' => true, 'pixel_guard' => true,
                        'multi_domain' => true, 'agency_permissions' => true,
                        'account_overview' => true, 'white_label_reports' => true,
                        'in_person_onboarding' => true, 'consent_mode' => false,
                    ]),
                ],
            ];

            foreach ($plans as $plan) {
                DB::table('plans')->updateOrInsert(
                    ['slug' => $plan['slug']],
                    array_merge($plan, ['created_at' => now(), 'updated_at' => now()])
                );
            }

            // Demote any old "enterprise" seed so it does not show on the public selector.
            DB::table('plans')->where('slug', 'enterprise')->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach (['phone', 'company_name', 'website_url'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
