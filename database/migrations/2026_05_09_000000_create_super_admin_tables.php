<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'is_super_admin')) {
                    $table->boolean('is_super_admin')->default(false)->after('is_admin')->index();
                }
                if (! Schema::hasColumn('users', 'status')) {
                    $table->string('status', 24)->default('active')->after('is_super_admin')->index();
                }
            });

            DB::table('users')
                ->where('email', 'admin@example.com')
                ->update(['is_super_admin' => true, 'is_admin' => true, 'status' => 'active']);
        }

        if (! Schema::hasTable('saas_products')) {
            Schema::create('saas_products', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('settings')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('saas_product_id')->nullable()->constrained('saas_products')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('tier', 40)->default('basic')->index();
                $table->unsignedInteger('price_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('billing_interval', 20)->default('monthly');
                $table->boolean('is_custom')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('trial_days')->default(0);
                $table->json('feature_limits')->nullable();
                $table->json('feature_flags')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->json('plan_scope')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('feature_flag_id')->nullable()->constrained('feature_flags')->nullOnDelete();
                $table->string('feature_key');
                $table->string('limit_value')->nullable();
                $table->boolean('is_unlimited')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
                $table->unique(['plan_id', 'feature_key']);
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedInteger('amount_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('billing_interval', 20)->default('monthly');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_ends_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->string('invoice_number')->nullable()->index();
                $table->unsignedInteger('amount_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('status', 32)->default('pending')->index();
                $table->string('payment_method')->nullable();
                $table->string('masked_payment')->nullable();
                $table->timestamp('paid_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('saas_products');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                $columns = [];
                foreach (['is_super_admin', 'status'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $columns[] = $column;
                    }
                }
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
