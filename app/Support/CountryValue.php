<?php

namespace App\Support;

use App\Models\IpLog;

class CountryValue
{
    /** ISO / short code for visits.country (VARCHAR 8). */
    public static function forVisitsTable(?IpLog $ipLog, ?string $cfCountry = null): ?string
    {
        $code = $cfCountry ?? $ipLog?->intel_country_code;
        if ($code === null || trim($code) === '') {
            return null;
        }

        return strtoupper(substr(trim($code), 0, 8));
    }

    /** Full label for paid marketing tables and UI. */
    public static function forDisplay(?IpLog $ipLog, ?string $cfCountry = null): ?string
    {
        if ($ipLog?->intel_country_name) {
            return $ipLog->intel_country_name;
        }

        if ($cfCountry !== null && strlen(trim($cfCountry)) > 3) {
            return trim($cfCountry);
        }

        return $cfCountry ?? $ipLog?->intel_country_code;
    }
}
