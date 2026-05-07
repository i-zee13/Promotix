<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AggregateHourlyAnalyticsCommand extends Command
{
    protected $signature = 'analytics:aggregate-hourly {--hours=24 : How many recent hours to recompute}';

    protected $description = 'Recompute the analytics_hourly buckets from the visits table for the recent window.';

    public function handle(): int
    {
        if (! Schema::hasTable('visits') || ! Schema::hasTable('analytics_hourly')) {
            $this->warn('visits or analytics_hourly table missing.');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $start = Carbon::now()->subHours($hours)->startOfHour();

        $rows = DB::table('visits')
            ->selectRaw('domain_id, DATE_FORMAT(visited_at, "%Y-%m-%d %H:00:00") as bucket_hour, COUNT(*) as total_visits, SUM(CASE WHEN is_paid_traffic = 1 THEN 1 ELSE 0 END) as paid_visits, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid_visits')
            ->where('visited_at', '>=', $start)
            ->groupBy('domain_id', 'bucket_hour')
            ->get();

        foreach ($rows as $row) {
            DB::table('analytics_hourly')->updateOrInsert(
                ['domain_id' => $row->domain_id, 'bucket_hour' => $row->bucket_hour],
                [
                    'total_visits' => (int) $row->total_visits,
                    'paid_visits' => (int) $row->paid_visits,
                    'invalid_visits' => (int) $row->invalid_visits,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->info("Aggregated {$rows->count()} bucket rows since {$start}.");

        return self::SUCCESS;
    }
}
