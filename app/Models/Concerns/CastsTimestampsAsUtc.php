<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Visit/click rows are persisted in UTC; user timezone middleware must not shift stored instants on read.
 */
trait CastsTimestampsAsUtc
{
    protected function asDateTime($value): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        return Carbon::parse((string) $value, 'UTC');
    }
}
