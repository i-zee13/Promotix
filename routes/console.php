<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly analytics roll-up aggregation. Recomputes the last 2 hours each run
// to backfill any racing visits and keep dashboard live numbers consistent.
Schedule::command('analytics:aggregate-hourly --hours=2')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/cron.log'));
$queueConnection = config('queue.default', 'database');
$queueMaxTime = (int) config('queue.worker_max_time', 55);
$queueWorkers = (int) config('queue.scheduled_workers', 3);

for ($worker = 1; $worker <= $queueWorkers; $worker++) {
    Schedule::command("queue:work {$queueConnection} --stop-when-empty --max-time={$queueMaxTime} --tries=3")
        ->everyMinute()
        ->withoutOverlapping()
        ->name("queue-worker-{$worker}")
        ->appendOutputTo(storage_path('logs/queue.log'));
}