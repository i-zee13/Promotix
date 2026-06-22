<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('geo_countries')) {
            return;
        }

        $countriesPath = database_path('data/geo_countries.json');
        $statesPath = database_path('data/geo_states.json');

        if (! is_readable($countriesPath) || ! is_readable($statesPath)) {
            $this->command?->warn('Missing database/data/geo_countries.json or geo_states.json — run geo:seed first.');

            return;
        }

        $countries = json_decode((string) file_get_contents($countriesPath), true);
        $states = json_decode((string) file_get_contents($statesPath), true);

        if (! is_array($countries) || ! is_array($states)) {
            return;
        }

        DB::table('geo_countries')->truncate();
        DB::table('geo_states')->truncate();
        DB::table('geo_cities')->truncate();

        $now = now();
        foreach (array_chunk($countries, 200) as $chunk) {
            $rows = [];
            foreach ($chunk as $country) {
                $code = strtoupper(trim((string) ($country['iso2'] ?? '')));
                $name = trim((string) ($country['name'] ?? ''));
                if ($code === '' || $name === '') {
                    continue;
                }
                $rows[] = [
                    'code' => $code,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('geo_countries')->insert($rows);
            }
        }

        foreach (array_chunk($states, 300) as $chunk) {
            $rows = [];
            foreach ($chunk as $state) {
                $countryCode = strtoupper(trim((string) ($state['country_code'] ?? '')));
                $code = strtoupper(trim((string) ($state['iso2'] ?? $state['state_code'] ?? '')));
                $name = trim((string) ($state['name'] ?? ''));
                if ($countryCode === '' || $code === '' || $name === '') {
                    continue;
                }
                $rows[] = [
                    'country_code' => $countryCode,
                    'code' => $code,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('geo_states')->insert($rows);
            }
        }

        $cityCount = $this->importCitiesFromCsv($now);
        if ($cityCount === 0) {
            $cityCount = $this->importFallbackCities($countries, $now);
        }

        $this->command?->info('Geo catalog seeded: ' . DB::table('geo_countries')->count() . ' countries, '
            . DB::table('geo_states')->count() . ' states, '
            . $cityCount . ' cities.');
    }

    private function importCitiesFromCsv(\DateTimeInterface $now): int
    {
        $path = database_path('data/geo_cities.csv');
        if (! is_readable($path)) {
            $this->command?->warn('Missing database/data/geo_cities.csv — only capitals will be imported. Run: php artisan geo:seed');

            return 0;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return 0;
        }

        $columns = array_flip(array_map('strtolower', $header));
        $nameIdx = $columns['name'] ?? 1;
        $stateCodeIdx = $columns['state_code'] ?? 3;
        $countryCodeIdx = $columns['country_code'] ?? 6;

        $batch = [];
        $total = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $countryCode = strtoupper(trim((string) ($row[$countryCodeIdx] ?? '')));
            $stateCode = strtoupper(trim((string) ($row[$stateCodeIdx] ?? '')));
            $name = trim((string) ($row[$nameIdx] ?? ''));

            if ($countryCode === '' || $stateCode === '' || $name === '') {
                continue;
            }

            $batch[] = [
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('geo_cities')->insert($batch);
                $total += count($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            DB::table('geo_cities')->insert($batch);
            $total += count($batch);
        }

        return $total;
    }

    /** @param  array<int, array<string, mixed>>  $countries */
    private function importFallbackCities(array $countries, \DateTimeInterface $now): int
    {
        $cityRows = [];

        foreach ($countries as $country) {
            $countryCode = strtoupper(trim((string) ($country['iso2'] ?? '')));
            $capital = trim((string) ($country['capital'] ?? ''));
            if ($countryCode === '' || $capital === '') {
                continue;
            }
            $cityRows[] = [
                'country_code' => $countryCode,
                'state_code' => '*',
                'name' => $capital,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($this->majorCities() as $city) {
            $cityRows[] = array_merge($city, ['created_at' => $now, 'updated_at' => $now]);
        }

        foreach (array_chunk($cityRows, 400) as $chunk) {
            DB::table('geo_cities')->insert($chunk);
        }

        return count($cityRows);
    }

    /** @return list<array{country_code: string, state_code: string, name: string}> */
    private function majorCities(): array
    {
        return [
            ['country_code' => 'US', 'state_code' => 'CA', 'name' => 'Los Angeles'],
            ['country_code' => 'US', 'state_code' => 'CA', 'name' => 'San Francisco'],
            ['country_code' => 'US', 'state_code' => 'NY', 'name' => 'New York City'],
            ['country_code' => 'US', 'state_code' => 'TX', 'name' => 'Houston'],
            ['country_code' => 'US', 'state_code' => 'TX', 'name' => 'Dallas'],
            ['country_code' => 'US', 'state_code' => 'FL', 'name' => 'Miami'],
            ['country_code' => 'GB', 'state_code' => 'ENG', 'name' => 'London'],
            ['country_code' => 'GB', 'state_code' => 'ENG', 'name' => 'Manchester'],
            ['country_code' => 'AE', 'state_code' => 'DU', 'name' => 'Dubai'],
            ['country_code' => 'PK', 'state_code' => 'PB', 'name' => 'Lahore'],
            ['country_code' => 'PK', 'state_code' => 'SD', 'name' => 'Karachi'],
            ['country_code' => 'IN', 'state_code' => 'MH', 'name' => 'Mumbai'],
            ['country_code' => 'IN', 'state_code' => 'DL', 'name' => 'New Delhi'],
            ['country_code' => 'CA', 'state_code' => 'ON', 'name' => 'Toronto'],
            ['country_code' => 'DE', 'state_code' => 'BE', 'name' => 'Berlin'],
            ['country_code' => 'FR', 'state_code' => 'IDF', 'name' => 'Paris'],
        ];
    }
}
