<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsGeoTargetResolver
{
    /**
     * Resolve a PromoTix geo rule to a Google Ads geoTargetConstants/{id} resource name.
     *
     * @param  array<string, string>  $headers
     */
    public function resolveCriterionResource(
        array $headers,
        string $apiVersion,
        string $countryCode,
        ?string $countryName = null,
        ?string $stateName = null,
        ?string $cityName = null,
    ): ?string {
        $countryCode = strtoupper(trim($countryCode));
        $cityName = $this->cleanName($cityName);
        $stateName = $this->cleanName($stateName);
        $countryName = $this->cleanName($countryName);

        if ($cityName !== null) {
            $id = $this->suggestBest($headers, $apiVersion, $countryCode, $cityName, ['City', 'Municipality', 'Postal Code']);
            if ($id !== null) {
                return $id;
            }
        }

        if ($stateName !== null) {
            $id = $this->suggestBest($headers, $apiVersion, $countryCode, $stateName, ['State', 'Province', 'Region', 'Territory', 'Governorate', 'Canton', 'Department']);
            if ($id !== null) {
                return $id;
            }
        }

        $lookupName = $countryName ?: $countryCode;
        $id = $this->suggestBest($headers, $apiVersion, null, $lookupName, ['Country']);
        if ($id !== null) {
            return $id;
        }

        // Fallback: try ISO country code as the location name (rarely works) then country name only.
        if ($countryName !== null && $countryName !== $lookupName) {
            return $this->suggestBest($headers, $apiVersion, null, $countryName, ['Country']);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<string>  $preferredTypes
     */
    private function suggestBest(
        array $headers,
        string $apiVersion,
        ?string $countryCode,
        string $name,
        array $preferredTypes,
    ): ?string {
        $payload = [
            'locale' => 'en',
            'locationNames' => [
                'names' => [$name],
            ],
        ];
        if ($countryCode !== null && $countryCode !== '') {
            $payload['countryCode'] = $countryCode;
        }

        $response = Http::timeout(20)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($apiVersion, 'geoTargetConstants:suggest'), $payload);

        if (! $response->successful()) {
            Log::warning('Google geoTargetConstants:suggest failed', [
                'status' => $response->status(),
                'name' => $name,
                'country' => $countryCode,
                'body' => Str::limit((string) $response->body(), 300),
            ]);

            return null;
        }

        $suggestions = $response->json('geoTargetConstantSuggestions')
            ?? $response->json('geo_target_constant_suggestions')
            ?? [];

        if (! is_array($suggestions) || $suggestions === []) {
            return null;
        }

        $preferred = array_map('strtolower', $preferredTypes);
        $best = null;
        $bestScore = -1;

        foreach ($suggestions as $row) {
            if (! is_array($row)) {
                continue;
            }
            $constant = $row['geoTargetConstant'] ?? $row['geo_target_constant'] ?? null;
            if (! is_array($constant)) {
                continue;
            }

            $resource = (string) ($constant['resourceName'] ?? $constant['resource_name'] ?? '');
            $type = strtolower((string) ($constant['targetType'] ?? $constant['target_type'] ?? ''));
            $status = strtoupper((string) ($constant['status'] ?? 'ENABLED'));
            if ($resource === '' || ($status !== '' && $status !== 'ENABLED')) {
                continue;
            }

            $score = 0;
            $typeIndex = array_search($type, $preferred, true);
            if ($typeIndex !== false) {
                $score = 100 - (int) $typeIndex;
            }

            $reach = (int) ($row['reach'] ?? 0);
            $score += min(20, (int) floor($reach / 1_000_000));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $resource;
            }
        }

        return $best;
    }

    private function cleanName(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function googleAdsUrl(string $version, string $path): string
    {
        return 'https://googleads.googleapis.com/' . trim($version) . '/' . ltrim($path, '/');
    }
}
