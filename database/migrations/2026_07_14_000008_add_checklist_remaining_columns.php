<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'workspace_geo_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('workspace_geo_settings')->nullable()->after('reporting_timezone');
            });
        }

        if (Schema::hasTable('domain_detection_settings')) {
            Schema::table('domain_detection_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('domain_detection_settings', 'geo_rule_scope')) {
                    $table->string('geo_rule_scope', 16)->default('domain')->after('control_mode');
                }
                if (! Schema::hasColumn('domain_detection_settings', 'consent_required')) {
                    $table->boolean('consent_required')->default(false)->after('recording_retention_days');
                }
                if (! Schema::hasColumn('domain_detection_settings', 'consent_regions')) {
                    $table->json('consent_regions')->nullable()->after('consent_required');
                }
                if (! Schema::hasColumn('domain_detection_settings', 'recording_mask_passwords')) {
                    $table->boolean('recording_mask_passwords')->default(true)->after('consent_regions');
                }
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                if (! Schema::hasColumn('visits', 'block_enforced')) {
                    $table->boolean('block_enforced')->default(false)->index()->after('action_taken');
                }
                if (! Schema::hasColumn('visits', 'tracking_confidence')) {
                    $table->string('tracking_confidence', 16)->default('high')->after('click_source');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                if (Schema::hasColumn('visits', 'block_enforced')) {
                    $table->dropColumn('block_enforced');
                }
                if (Schema::hasColumn('visits', 'tracking_confidence')) {
                    $table->dropColumn('tracking_confidence');
                }
            });
        }

        if (Schema::hasTable('domain_detection_settings')) {
            Schema::table('domain_detection_settings', function (Blueprint $table) {
                foreach (['geo_rule_scope', 'consent_required', 'consent_regions', 'recording_mask_passwords'] as $col) {
                    if (Schema::hasColumn('domain_detection_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'workspace_geo_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('workspace_geo_settings');
            });
        }
    }
};
