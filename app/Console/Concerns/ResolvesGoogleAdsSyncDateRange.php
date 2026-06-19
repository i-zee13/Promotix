<?php

namespace App\Console\Concerns;

use Carbon\Carbon;

trait ResolvesGoogleAdsSyncDateRange
{
    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function resolveSyncDateRange(int $defaultDays = 30): ?array
    {
        $fromOpt = trim((string) $this->option('from'));
        $toOpt = trim((string) $this->option('to'));

        if ($fromOpt !== '' || $toOpt !== '') {
            if ($fromOpt === '' || $toOpt === '') {
                $this->error('Use both --from=YYYY-MM-DD and --to=YYYY-MM-DD together.');

                return null;
            }

            try {
                $from = Carbon::parse($fromOpt)->startOfDay();
                $to = Carbon::parse($toOpt)->endOfDay();
            } catch (\Throwable) {
                $this->error('Invalid --from or --to date. Use YYYY-MM-DD.');

                return null;
            }

            if ($from->gt($to)) {
                $this->error('--from must be on or before --to.');

                return null;
            }

            return [$from, $to];
        }

        $days = max(1, (int) $this->option('days'));

        return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
    }
}
