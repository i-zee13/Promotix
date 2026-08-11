<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A foundation for Paid Advertising Manual v3:
 * identity graph + rolling click windows + visit identity columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paid_identities')) {
            Schema::create('paid_identities', function (Blueprint $table): void {
                $table->id();
                $table->string('public_id', 32)->unique();
                $table->unsignedBigInteger('domain_id')->index();
                $table->string('visitor_id', 64)->nullable()->index();
                $table->string('browser_id', 64)->nullable()->index();
                $table->string('device_id', 64)->nullable()->index();
                $table->string('fingerprint_id', 64)->nullable()->index();
                $table->decimal('identity_confidence', 4, 3)->default(0.400);
                $table->string('confidence_band', 24)->default('low');
                $table->boolean('known_fraud')->default(false);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['domain_id', 'device_id']);
                $table->index(['domain_id', 'browser_id']);
            });
        }

        if (! Schema::hasTable('paid_identity_links')) {
            Schema::create('paid_identity_links', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('paid_identity_id')->index();
                $table->string('link_type', 32); // ip|session|visitor|browser|device|fingerprint
                $table->string('link_value', 191);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['paid_identity_id', 'link_type', 'link_value'], 'paid_identity_links_unique');
                $table->index(['link_type', 'link_value']);
            });
        }

        if (! Schema::hasTable('click_windows')) {
            Schema::create('click_windows', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('domain_id')->index();
                $table->string('entity_type', 32); // ip|browser|device|paid_identity|campaign
                $table->string('entity_id', 191);
                $table->string('window_key', 16); // 1m|5m|15m|30m|60m|6h|24h|7d
                $table->unsignedInteger('click_count')->default(0);
                $table->timestamp('window_started_at')->nullable();
                $table->timestamp('last_click_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['domain_id', 'entity_type', 'entity_id', 'window_key'],
                    'click_windows_entity_unique'
                );
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                if (! Schema::hasColumn('visits', 'visitor_id')) {
                    $table->string('visitor_id', 64)->nullable()->after('session_id')->index();
                }
                if (! Schema::hasColumn('visits', 'browser_id')) {
                    $table->string('browser_id', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('visits', 'device_id')) {
                    $table->string('device_id', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('visits', 'fingerprint_id')) {
                    $table->string('fingerprint_id', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('visits', 'paid_identity_id')) {
                    $table->string('paid_identity_id', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('visits', 'identity_confidence')) {
                    $table->decimal('identity_confidence', 4, 3)->nullable();
                }
                if (! Schema::hasColumn('visits', 'ads_detections')) {
                    $table->json('ads_detections')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                foreach ([
                    'visitor_id',
                    'browser_id',
                    'device_id',
                    'fingerprint_id',
                    'paid_identity_id',
                    'identity_confidence',
                    'ads_detections',
                ] as $col) {
                    if (Schema::hasColumn('visits', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('click_windows');
        Schema::dropIfExists('paid_identity_links');
        Schema::dropIfExists('paid_identities');
    }
};
