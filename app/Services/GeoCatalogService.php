<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeoCatalogService
{
    /** @return list<array{code: string, name: string}> */
    public function countries(): array
    {
        if (! Schema::hasTable('geo_countries')) {
            return [];
        }

        return DB::table('geo_countries')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    /** @return list<array{code: string, name: string}> */
    public function states(string $countryCode): array
    {
        if (! Schema::hasTable('geo_states')) {
            return [];
        }

        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === '') {
            return [];
        }

        return DB::table('geo_states')
            ->where('country_code', $countryCode)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    /** @return list<string> */
    public function cities(string $countryCode, string $stateCode): array
    {
        if (! Schema::hasTable('geo_cities')) {
            return [];
        }

        $countryCode = strtoupper(trim($countryCode));
        $stateCode = strtoupper(trim($stateCode));
        if ($countryCode === '') {
            return [];
        }

        $query = DB::table('geo_cities')->where('country_code', $countryCode);
        if ($stateCode !== '') {
            $query->where('state_code', $stateCode);
        } else {
            $query->where('state_code', '*');
        }

        return $query
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }
}
