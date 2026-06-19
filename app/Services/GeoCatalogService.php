<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeoCatalogService
{
    /** @return list<array{code: string, name: string}> */
    public function countries(?string $query = null, int $limit = 50): array
    {
        if (! Schema::hasTable('geo_countries')) {
            return [];
        }

        $builder = DB::table('geo_countries')->orderBy('name');

        $query = trim((string) $query);
        if ($query !== '') {
            $like = '%' . $query . '%';
            $builder->where(function ($q) use ($like, $query): void {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', '%' . strtoupper($query) . '%');
            });
        }

        return $builder
            ->limit(max(1, min($limit, 100)))
            ->get(['code', 'name'])
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    /** @return list<array{code: string, name: string}> */
    public function states(string $countryCode, ?string $query = null): array
    {
        if (! Schema::hasTable('geo_states')) {
            return [];
        }

        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === '') {
            return [];
        }

        $builder = DB::table('geo_states')
            ->where('country_code', $countryCode)
            ->orderBy('name');

        $query = trim((string) $query);
        if ($query !== '') {
            $like = '%' . $query . '%';
            $builder->where(function ($q) use ($like, $query): void {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', '%' . strtoupper($query) . '%');
            });
        }

        return $builder
            ->get(['code', 'name'])
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    /** @return list<string> */
    public function cities(string $countryCode, string $stateCode, ?string $query = null, int $limit = 80): array
    {
        if (! Schema::hasTable('geo_cities')) {
            return [];
        }

        $countryCode = strtoupper(trim($countryCode));
        $stateCode = strtoupper(trim($stateCode));
        if ($countryCode === '' || $stateCode === '') {
            return [];
        }

        $builder = DB::table('geo_cities')
            ->where('country_code', $countryCode)
            ->where('state_code', $stateCode)
            ->orderBy('name');

        $query = trim((string) $query);
        if ($query !== '') {
            $builder->where('name', 'like', '%' . $query . '%');
        }

        return $builder
            ->limit(max(1, min($limit, 100)))
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }
}
