<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clickronix_devices')) {
            Schema::create('clickronix_devices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('domain_id')->index();
                $table->string('device_id', 64)->index();
                $table->string('device_token', 80)->nullable()->index();
                $table->string('fingerprint_id', 64)->nullable()->index();
                $table->decimal('device_confidence', 5, 4)->default(0.5);
                $table->string('ga4_client_id', 128)->nullable()->index();
                $table->string('last_gclid', 255)->nullable();
                $table->string('last_gbraid', 255)->nullable();
                $table->string('last_wbraid', 255)->nullable();
                $table->timestamp('first_paid_click_at')->nullable();
                $table->timestamp('last_paid_click_at')->nullable();
                $table->unsignedInteger('paid_click_count')->default(0);
                $table->unsignedInteger('ip_count')->default(0);
                $table->unsignedInteger('ip_change_count')->default(0);
                $table->unsignedInteger('invalid_click_count')->default(0);
                $table->unsignedInteger('valid_click_count')->default(0);
                $table->unsignedInteger('conversion_count')->default(0);
                $table->timestamp('last_conversion_at')->nullable();
                $table->string('last_conversion_type', 64)->nullable();
                $table->boolean('automation_detected')->default(false);
                $table->boolean('proxy')->default(false);
                $table->boolean('vpn')->default(false);
                $table->boolean('datacenter')->default(false);
                $table->unsignedTinyInteger('risk_score')->default(0);
                $table->string('risk_reason', 64)->nullable();
                $table->string('risk_label', 64)->nullable();
                $table->boolean('exclusion_candidate')->default(false)->index();
                $table->boolean('ga4_exclusion_sent')->default(false);
                $table->timestamp('ga4_exclusion_sent_at')->nullable();
                $table->string('last_ip', 64)->nullable();
                $table->json('ip_history')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['domain_id', 'device_id']);
                $table->index(['domain_id', 'device_token']);
                $table->index(['domain_id', 'ga4_client_id']);
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                if (! Schema::hasColumn('visits', 'ga4_client_id')) {
                    $table->string('ga4_client_id', 128)->nullable()->after('device_id')->index();
                }
                if (! Schema::hasColumn('visits', 'device_confidence')) {
                    $table->decimal('device_confidence', 5, 4)->nullable()->after('ga4_client_id');
                }
                if (! Schema::hasColumn('visits', 'device_token')) {
                    $table->string('device_token', 80)->nullable()->after('device_confidence')->index();
                }
            });
        }

        if (Schema::hasTable('paid_marketing_clicks')) {
            Schema::table('paid_marketing_clicks', function (Blueprint $table): void {
                if (! Schema::hasColumn('paid_marketing_clicks', 'device_id')) {
                    $table->string('device_id', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('paid_marketing_clicks', 'ga4_client_id')) {
                    $table->string('ga4_client_id', 128)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table): void {
                if (! Schema::hasColumn('domains', 'ga4_measurement_id')) {
                    $table->string('ga4_measurement_id', 32)->nullable();
                }
                if (! Schema::hasColumn('domains', 'ga4_api_secret')) {
                    $table->string('ga4_api_secret', 128)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table): void {
                foreach (['ga4_measurement_id', 'ga4_api_secret'] as $col) {
                    if (Schema::hasColumn('domains', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('paid_marketing_clicks')) {
            Schema::table('paid_marketing_clicks', function (Blueprint $table): void {
                foreach (['device_id', 'ga4_client_id'] as $col) {
                    if (Schema::hasColumn('paid_marketing_clicks', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                foreach (['ga4_client_id', 'device_confidence', 'device_token'] as $col) {
                    if (Schema::hasColumn('visits', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('clickronix_devices');
    }
};
