<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows truncating google_ads_accounts in phpMyAdmin without FK errors.
 * Columns google_ads_account_id remain; only DB-level foreign keys are removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropGoogleAdsAccountForeignKey('domain_google_ads_mappings');
        $this->dropGoogleAdsAccountForeignKey('google_ads_advertised_hosts');
        $this->dropGoogleAdsAccountForeignKey('google_ads_campaign_daily_metrics');
        $this->dropGoogleAdsAccountForeignKey('domains');
    }

    public function down(): void
    {
        if (Schema::hasTable('domain_google_ads_mappings') && Schema::hasTable('google_ads_accounts')) {
            Schema::table('domain_google_ads_mappings', function (Blueprint $table) {
                $table->foreign('google_ads_account_id')
                    ->references('id')
                    ->on('google_ads_accounts')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('google_ads_advertised_hosts') && Schema::hasTable('google_ads_accounts')) {
            Schema::table('google_ads_advertised_hosts', function (Blueprint $table) {
                $table->foreign('google_ads_account_id')
                    ->references('id')
                    ->on('google_ads_accounts')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('google_ads_campaign_daily_metrics') && Schema::hasTable('google_ads_accounts')) {
            Schema::table('google_ads_campaign_daily_metrics', function (Blueprint $table) {
                $table->foreign('google_ads_account_id')
                    ->references('id')
                    ->on('google_ads_accounts')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('domains') && Schema::hasColumn('domains', 'google_ads_account_id') && Schema::hasTable('google_ads_accounts')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->foreign('google_ads_account_id')
                    ->references('id')
                    ->on('google_ads_accounts')
                    ->nullOnDelete();
            });
        }
    }

    private function dropGoogleAdsAccountForeignKey(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'google_ads_account_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropForeign(['google_ads_account_id']);
        });
    }
};
