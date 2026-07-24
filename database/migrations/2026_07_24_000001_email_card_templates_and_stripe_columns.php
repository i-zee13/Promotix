<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_templates')) {
            $now = now();
            $row = [
                'name' => 'Payment Method Saved',
                'description' => 'Sent when a customer saves/verifies a payment card',
                'subject' => 'Your {{card_brand}} card is active on {{app_name}}',
                'body' => "Hi {{user_name}},\n\nYour payment method was saved and verified on {{app_name}}.\n\nCard: {{card_brand}} •••• {{last_four}}\nStatus: Active / valid for billing\n\nYou can manage cards anytime here:\n{{billing_url}}\n\nIf you did not add this card, contact support immediately.\n\n— {{app_name}} Billing",
                'variables' => json_encode(['app_name', 'user_name', 'card_brand', 'last_four', 'billing_url']),
            ];

            $exists = DB::table('email_templates')->where('key', 'payment_method_saved_email')->first();
            if ($exists) {
                DB::table('email_templates')->where('key', 'payment_method_saved_email')->update([
                    'subject' => $row['subject'],
                    'body' => $row['body'],
                    'variables' => $row['variables'],
                    'description' => $row['description'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('email_templates')->insert([
                    'key' => 'payment_method_saved_email',
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'subject' => $row['subject'],
                    'body' => $row['body'],
                    'variables' => $row['variables'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Ensure critical templates stay active so first OTP / invites actually send.
            DB::table('email_templates')
                ->whereIn('key', [
                    'otp_verification_email',
                    'welcome_email',
                    'user_invite_email',
                    'password_reset_email',
                    'payment_method_saved_email',
                ])
                ->update(['is_active' => true, 'updated_at' => $now]);
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function ($table): void {
                $table->string('stripe_customer_id')->nullable()->after('remember_token');
            });
        }

        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function ($table): void {
                if (! Schema::hasColumn('payment_methods', 'stripe_payment_method_id')) {
                    $table->string('stripe_payment_method_id')->nullable()->after('last_four');
                }
                if (! Schema::hasColumn('payment_methods', 'verification_status')) {
                    $table->string('verification_status', 32)->nullable()->after('is_temporary');
                }
                if (! Schema::hasColumn('payment_methods', 'verification_charge_id')) {
                    $table->string('verification_charge_id')->nullable()->after('verification_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function ($table): void {
                foreach (['stripe_payment_method_id', 'verification_status', 'verification_charge_id'] as $col) {
                    if (Schema::hasColumn('payment_methods', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function ($table): void {
                $table->dropColumn('stripe_customer_id');
            });
        }
    }
};
