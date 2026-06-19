<?php

namespace App\Support;

/**
 * Static geographic catalog for audience pool UI (expand over time).
 */
class GeoAudienceCatalog
{
    /** @return list<array{code: string, name: string}> */
    public static function countries(): array
    {
        return [
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
            ['code' => 'PK', 'name' => 'Pakistan'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'MX', 'name' => 'Mexico'],
        ];
    }

    /** @return list<array{code: string, name: string}> */
    public static function states(string $countryCode): array
    {
        $countryCode = strtoupper(trim($countryCode));

        return match ($countryCode) {
            'US' => [
                ['code' => 'CA', 'name' => 'California'],
                ['code' => 'NY', 'name' => 'New York'],
                ['code' => 'TX', 'name' => 'Texas'],
                ['code' => 'FL', 'name' => 'Florida'],
                ['code' => 'IL', 'name' => 'Illinois'],
            ],
            'GB' => [
                ['code' => 'ENG', 'name' => 'England'],
                ['code' => 'SCT', 'name' => 'Scotland'],
                ['code' => 'WLS', 'name' => 'Wales'],
            ],
            'AE' => [
                ['code' => 'DU', 'name' => 'Dubai'],
                ['code' => 'AZ', 'name' => 'Abu Dhabi'],
            ],
            'PK' => [
                ['code' => 'PB', 'name' => 'Punjab'],
                ['code' => 'SD', 'name' => 'Sindh'],
                ['code' => 'KP', 'name' => 'Khyber Pakhtunkhwa'],
            ],
            'CA' => [
                ['code' => 'ON', 'name' => 'Ontario'],
                ['code' => 'BC', 'name' => 'British Columbia'],
                ['code' => 'QC', 'name' => 'Quebec'],
            ],
            default => [],
        };
    }

    /** @return list<string> */
    public static function cities(string $countryCode, string $stateCode): array
    {
        $key = strtoupper(trim($countryCode)) . ':' . strtoupper(trim($stateCode));

        return match ($key) {
            'US:CA' => ['Los Angeles', 'San Francisco', 'San Diego'],
            'US:NY' => ['New York City', 'Buffalo', 'Albany'],
            'US:TX' => ['Houston', 'Dallas', 'Austin'],
            'US:FL' => ['Miami', 'Orlando', 'Tampa'],
            'US:IL' => ['Chicago', 'Springfield'],
            'GB:ENG' => ['London', 'Manchester', 'Birmingham'],
            'GB:SCT' => ['Edinburgh', 'Glasgow'],
            'AE:DU' => ['Dubai'],
            'AE:AZ' => ['Abu Dhabi'],
            'PK:PB' => ['Lahore', 'Faisalabad', 'Rawalpindi'],
            'PK:SD' => ['Karachi', 'Hyderabad'],
            'CA:ON' => ['Toronto', 'Ottawa'],
            'CA:BC' => ['Vancouver', 'Victoria'],
            default => [],
        };
    }
}
