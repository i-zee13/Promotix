<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_templates')) {
            return;
        }

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('email_templates')->insert([
            [
                'key' => 'welcome_email',
                'name' => 'Welcome Email',
                'description' => 'Triggered after successful signup + email verification',
                'subject' => 'Welcome to {{app_name}}!',
                'body' => "Hi {{user_name}},\n\nYour account has been successfully created!\n\nHere's what you can do next:\n- Add your first domain\n- Enable tracking\n- Connect ad platforms\n- Configure alerts\n\nGet started: {{dashboard_url}}\n\nIf you need help, our support team is always here.\n\nBest,\n{{app_name}} Team",
                'variables' => json_encode(['app_name', 'dashboard_url', 'user_name', 'Company_name', 'ContactUs']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'otp_verification_email',
                'name' => 'OTP Verification Email',
                'description' => 'Used for signup, login, sensitive actions',
                'subject' => 'Your {{app_name}} Verification Code',
                'body' => "Hi {{user_name}},\n\nYour verification code is:\n\n{{otp_code}}\n\nThis code expires in {{otp_expiry}} minutes.\nIf you didn't request this, ignore this email.\n\n— {{app_name}} Security Team",
                'variables' => json_encode(['app_name', 'otp_code', 'otp_expiry', 'Time', 'ContactUs']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'password_reset_email',
                'name' => 'Password Reset Email',
                'description' => 'Triggered when user clicks Forgot Password',
                'subject' => 'Reset Your {{app_name}} Password',
                'body' => "Hi {{user_name}},\n\nWe received a request to reset your password.\n\nClick the link below to continue:\n{{reset_url}}\n\nThis link expires in {{reset_expiry}} minutes.\nIf you didn't request this, ignore this email.\n\n— {{app_name}} Team",
                'variables' => json_encode(['app_name', 'mail', 'reset_url', 'reset_expiry', 'ContactUs']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_failed_email',
                'name' => 'Payment Failed Email',
                'description' => 'Triggered when Stripe / PayPal payment fails',
                'subject' => 'Payment Failed – Action Required',
                'body' => "Hi {{user_name}},\n\nWe couldn't process your payment for {{plan_name}}.\n\nReason:\n{{failure_reason}}\n\nTo avoid service interruption, update your payment method:\n{{billing_url}}\n\n— {{app_name}} Billing Team",
                'variables' => json_encode(['app_name', 'plan_name', 'failure_reason', 'billing_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'subscription_cancelled_email',
                'name' => 'Subscription Cancelled Email',
                'description' => 'Triggered when a subscription is cancelled',
                'subject' => 'Your {{app_name}} Subscription Has Been Cancelled',
                'body' => "Hi {{user_name}},\n\nYour subscription to {{plan_name}} has been cancelled effective {{cancel_date}}.\n\nYou can resubscribe anytime here:\n{{billing_url}}\n\n— {{app_name}} Team",
                'variables' => json_encode(['app_name', 'plan_name', 'cancel_date', 'billing_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'security_alert_email',
                'name' => 'Alert Emails (Security / System)',
                'description' => 'Triggered for suspicious logins or security events',
                'subject' => 'Alert: {{alert_title}}',
                'body' => "Hi {{user_name}},\n\nWe detected the following activity:\n\n{{alert_message}}\n\nTime: {{event_time}}\nIP: {{ip_address}}\n\nIf this wasn't you, secure your account immediately:\n{{security_url}}\n\n— {{app_name}} Security",
                'variables' => json_encode(['app_name', 'alert_title', 'alert_message', 'event_time', 'ip_address', 'security_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
