<?php

use App\Models\SaasProduct;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('saas_products')) {
            $existing = DB::table('saas_products')->where('slug', 'clickronix')->first();
            $settings = [
                'type' => 'tracking',
                'gates_customer_portal' => true,
            ];

            if ($existing) {
                $merged = array_merge(
                    is_string($existing->settings) ? (json_decode($existing->settings, true) ?: []) : (array) ($existing->settings ?? []),
                    $settings
                );
                DB::table('saas_products')->where('id', $existing->id)->update([
                    'name' => $existing->name ?: 'ClickRonix',
                    'description' => $existing->description ?: 'Customer portal solution — Paid Advertising, Bot Protection, Domains, and related modules.',
                    // First deploy stays open; Super Admin can deactivate later from Products.
                    'is_active' => true,
                    'settings' => json_encode($merged),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('saas_products')->insert([
                    'name' => 'ClickRonix',
                    'slug' => 'clickronix',
                    'description' => 'Customer portal solution — Paid Advertising, Bot Protection, Domains, and related modules.',
                    'is_active' => true,
                    'settings' => json_encode($settings),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('email_templates')) {
            $now = now();
            $defaults = [
                'password_reset_email' => [
                    'name' => 'Password Reset Email',
                    'description' => 'Triggered when user clicks Forgot Password (OTP code)',
                    'subject' => 'Your {{app_name}} Password Reset Code',
                    'body' => "Hi {{user_name}},\n\nYour password reset code is:\n\n{{otp_code}}\n\nThis code expires in {{reset_expiry}} minutes.\nIf you didn't request this, ignore this email.\n\n— {{app_name}} Team",
                    'variables' => json_encode(['app_name', 'user_name', 'otp_code', 'reset_expiry']),
                ],
                'user_invite_email' => [
                    'name' => 'User Invite Email',
                    'description' => 'Sent when a super admin invites a new user',
                    'subject' => "You're invited to {{app_name}}",
                    'body' => "Hi {{user_name}},\n\nYou've been invited to join {{app_name}}.\n\nCreate your account here:\n{{invite_url}}\n\nThis invite expires on {{invite_expires}}.\nIf you weren't expecting this, you can ignore this email.\n\n— {{app_name}} Team",
                    'variables' => json_encode(['app_name', 'user_name', 'invite_url', 'invite_expires']),
                ],
            ];

            foreach ($defaults as $key => $row) {
                $exists = DB::table('email_templates')->where('key', $key)->first();
                if ($exists) {
                    if ($key === 'password_reset_email') {
                        DB::table('email_templates')->where('key', $key)->update([
                            'subject' => $row['subject'],
                            'body' => $row['body'],
                            'variables' => $row['variables'],
                            'description' => $row['description'],
                            'updated_at' => $now,
                        ]);
                    }
                } else {
                    DB::table('email_templates')->insert([
                        'key' => $key,
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
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('saas_products')) {
            $row = DB::table('saas_products')->where('slug', 'clickronix')->first();
            if ($row) {
                $settings = is_string($row->settings) ? (json_decode($row->settings, true) ?: []) : (array) ($row->settings ?? []);
                unset($settings['gates_customer_portal']);
                DB::table('saas_products')->where('id', $row->id)->update([
                    'settings' => json_encode($settings),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
