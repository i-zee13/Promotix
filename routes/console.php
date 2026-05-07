<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly analytics roll-up aggregation. Recomputes the last 2 hours each run
// to backfill any racing visits and keep dashboard live numbers consistent.
Schedule::command('analytics:aggregate-hourly --hours=2')->hourly();
