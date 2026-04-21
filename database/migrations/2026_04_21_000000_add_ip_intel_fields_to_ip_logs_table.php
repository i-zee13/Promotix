<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ip_logs', function (Blueprint $table) {
            $table->unsignedTinyInteger('iphub_block')->nullable()->after('last_referrer'); // 0 good, 1 bad, 2 mixed
            $table->json('iphub_proxy_type')->nullable()->after('iphub_block');
            $table->string('iphub_block_reason')->nullable()->after('iphub_proxy_type');

            $table->unsignedTinyInteger('abuse_confidence_score')->nullable()->after('iphub_block_reason'); // 0-100
            $table->unsignedInteger('abuse_total_reports')->nullable()->after('abuse_confidence_score');
            $table->boolean('abuse_is_tor')->nullable()->after('abuse_total_reports');

            $table->string('intel_country_code', 8)->nullable()->after('abuse_is_tor');
            $table->string('intel_country_name')->nullable()->after('intel_country_code');
            $table->string('intel_isp')->nullable()->after('intel_country_name');

            $table->timestamp('intel_checked_at')->nullable()->after('intel_isp');
            $table->string('intel_status')->nullable()->after('intel_checked_at'); // ok / error / skipped
        });
    }

    public function down(): void
    {
        Schema::table('ip_logs', function (Blueprint $table) {
            $table->dropColumn([
                'iphub_block',
                'iphub_proxy_type',
                'iphub_block_reason',
                'abuse_confidence_score',
                'abuse_total_reports',
                'abuse_is_tor',
                'intel_country_code',
                'intel_country_name',
                'intel_isp',
                'intel_checked_at',
                'intel_status',
            ]);
        });
    }
};

