<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows dropping/truncating the domains table in phpMyAdmin without FK errors.
 * Columns domain_id remain; only DB-level foreign keys are removed.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablesWithDomainId = [
        'google_ads_campaign_daily_metrics',
        'domain_google_ads_mappings',
        'domain_detection_settings',
        'paid_marketing_visits',
        'tracking_scripts',
        'domain_settings',
        'visits',
        'ip_sessions',
        'analytics_hourly',
        'detection_logs',
    ];

    public function up(): void
    {
        foreach ($this->tablesWithDomainId as $table) {
            $this->dropDomainForeignKey($table);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        foreach ($this->tablesWithDomainId as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'domain_id')) {
                continue;
            }

            $hasForeignKey = collect(Schema::getForeignKeys($table))
                ->contains(fn (array $foreignKey) => in_array('domain_id', $foreignKey['columns'] ?? [], true));

            if ($hasForeignKey) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->foreign('domain_id')
                    ->references('id')
                    ->on('domains')
                    ->cascadeOnDelete();
            });
        }
    }

    private function dropDomainForeignKey(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'domain_id')) {
            return;
        }

        $hasForeignKey = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => in_array('domain_id', $foreignKey['columns'] ?? [], true));

        if (! $hasForeignKey) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
        });
    }
};
