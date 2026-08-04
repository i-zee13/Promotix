<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for Paid Marketing + Bot Protection dashboards.
 * Speeds up large / "All time" date-range filters, joins, and group-bys.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexes('visits', [
            // baseVisitsQuery + summary / sparkline / country / domain rollups
            'visits_domain_visited_idx' => ['domain_id', 'visited_at'],
            'visits_domain_visited_invalid_idx' => ['domain_id', 'visited_at', 'is_invalid_traffic'],
            'visits_domain_visited_paid_idx' => ['domain_id', 'visited_at', 'is_paid_traffic'],
            'visits_domain_threat_visited_idx' => ['domain_id', 'threat_group', 'visited_at'],
            'visits_domain_action_visited_idx' => ['domain_id', 'action_taken', 'visited_at'],
            'visits_domain_crawler_visited_idx' => ['domain_id', 'is_crawler', 'visited_at'],
            'visits_domain_country_visited_idx' => ['domain_id', 'country', 'visited_at'],
            'visits_ip_visited_idx' => ['ip', 'visited_at'],
        ]);

        $this->addIndexes('paid_marketing_visits', [
            // detailed-visits orderBy + domain scope
            'pmv_domain_last_click_idx' => ['domain_id', 'last_click_at'],
            'pmv_last_click_idx' => ['last_click_at'],
            'pmv_domain_ip_idx' => ['domain_id', 'ip'],
            'pmv_domain_country_idx' => ['domain_id', 'country'],
            'pmv_domain_threat_idx' => ['domain_id', 'threat_group'],
        ]);

        $this->addIndexes('paid_marketing_clicks', [
            // date-range filters then join to visits (All time / wide windows)
            'pmc_clicked_at_idx' => ['clicked_at'],
            'pmc_clicked_visit_idx' => ['clicked_at', 'paid_marketing_visit_id'],
            'pmc_visit_clicked_idx' => ['paid_marketing_visit_id', 'clicked_at'],
            'pmc_paid_id_idx' => ['paid_id'],
            'pmc_ip_clicked_idx' => ['ip', 'clicked_at'],
            'pmc_threat_clicked_idx' => ['threat_group', 'clicked_at'],
        ]);

        $this->addIndexes('detection_logs', [
            'detection_domain_detected_idx' => ['domain_id', 'detected_at'],
            'detection_domain_threat_detected_idx' => ['domain_id', 'threat_group', 'detected_at'],
            'detection_domain_action_detected_idx' => ['domain_id', 'action_taken', 'detected_at'],
        ]);

        $this->addIndexes('google_ads_campaign_daily_metrics', [
            // buildLookup / Google verified paid traffic
            'ga_daily_domain_date_clicks_idx' => ['domain_id', 'metric_date', 'clicks'],
        ]);

        $this->addIndexes('ip_logs', [
            'ip_logs_blocked_last_seen_idx' => ['is_blocked', 'last_seen_at'],
        ]);
    }

    public function down(): void
    {
        $this->dropIndexes('visits', [
            'visits_domain_visited_idx',
            'visits_domain_visited_invalid_idx',
            'visits_domain_visited_paid_idx',
            'visits_domain_threat_visited_idx',
            'visits_domain_action_visited_idx',
            'visits_domain_crawler_visited_idx',
            'visits_domain_country_visited_idx',
            'visits_ip_visited_idx',
        ]);

        $this->dropIndexes('paid_marketing_visits', [
            'pmv_domain_last_click_idx',
            'pmv_last_click_idx',
            'pmv_domain_ip_idx',
            'pmv_domain_country_idx',
            'pmv_domain_threat_idx',
        ]);

        $this->dropIndexes('paid_marketing_clicks', [
            'pmc_clicked_at_idx',
            'pmc_clicked_visit_idx',
            'pmc_visit_clicked_idx',
            'pmc_paid_id_idx',
            'pmc_ip_clicked_idx',
            'pmc_threat_clicked_idx',
        ]);

        $this->dropIndexes('detection_logs', [
            'detection_domain_detected_idx',
            'detection_domain_threat_detected_idx',
            'detection_domain_action_detected_idx',
        ]);

        $this->dropIndexes('google_ads_campaign_daily_metrics', [
            'ga_daily_domain_date_clicks_idx',
        ]);

        $this->dropIndexes('ip_logs', [
            'ip_logs_blocked_last_seen_idx',
        ]);
    }

    /**
     * @param  array<string, list<string>>  $indexes  name => columns
     */
    private function addIndexes(string $table, array $indexes): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
            foreach ($indexes as $name => $columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue 2;
                    }
                }

                if ($this->indexExists($table, $name) || $this->equivalentIndexExists($table, $columns)) {
                    continue;
                }

                $blueprint->index($columns, $name);
            }
        });
    }

    /**
     * @param  list<string>  $names
     */
    private function dropIndexes(string $table, array $names): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $names): void {
            foreach ($names as $name) {
                if ($this->indexExists($table, $name)) {
                    $blueprint->dropIndex($name);
                }
            }
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function equivalentIndexExists(string $table, array $columns): bool
    {
        $wanted = array_values($columns);

        foreach (Schema::getIndexes($table) as $index) {
            $existing = array_values($index['columns'] ?? []);
            if ($existing === $wanted) {
                return true;
            }
        }

        return false;
    }
};
