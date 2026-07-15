<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;

class GeoAudienceMatcher
{
    /**
     * Resolve effective geo settings (domain vs workspace inheritance — CT-04).
     */
    public static function effectiveSettings(DomainDetectionSetting $settings, ?Domain $domain = null): DomainDetectionSetting
    {
        $scope = (string) ($settings->geo_rule_scope ?? 'domain');
        if ($scope !== 'workspace' || $domain === null) {
            return $settings;
        }

        $domain->loadMissing('user');
        $workspace = (array) ($domain->user?->workspace_geo_settings ?? []);
        if ($workspace === []) {
            return $settings;
        }

        $clone = clone $settings;
        if (array_key_exists('out_of_geo_enabled', $workspace)) {
            $clone->out_of_geo_enabled = (bool) $workspace['out_of_geo_enabled'];
        }
        if (array_key_exists('out_of_geo_audience', $workspace)) {
            $clone->out_of_geo_audience = $workspace['out_of_geo_audience'];
        }
        if (array_key_exists('out_of_geo_countries', $workspace)) {
            $clone->out_of_geo_countries = $workspace['out_of_geo_countries'];
        }
        if (array_key_exists('google_geo_block_enabled', $workspace)) {
            $clone->google_geo_block_enabled = (bool) $workspace['google_geo_block_enabled'];
        }
        if (array_key_exists('google_geo_block_audience', $workspace)) {
            $clone->google_geo_block_audience = $workspace['google_geo_block_audience'];
        }

        return $clone;
    }
    /**
     * @param  array<int, array{country?: string, state?: ?string, city?: ?string}>  $rules
     */
    public static function isAllowed(
        DomainDetectionSetting $settings,
        ?string $countryCode,
        ?string $region,
        ?string $city,
        ?IpLog $ipLog = null,
        ?Domain $domain = null,
    ): bool {
        $settings = self::effectiveSettings($settings, $domain);

        if (! $settings->out_of_geo_enabled) {
            return true;
        }

        $rules = self::normalizedRules($settings);
        if ($rules === []) {
            return true;
        }

        $country = strtoupper(trim((string) ($countryCode ?: $ipLog?->intel_country_code ?: '')));
        $regionName = self::normalizeName($region ?: self::regionFromIpLog($ipLog));
        $cityName = self::normalizeName($city ?: self::cityFromIpLog($ipLog));

        if ($country === '') {
            return false;
        }

        foreach ($rules as $rule) {
            if ($rule['country'] !== $country) {
                continue;
            }

            if ($rule['state'] === null && $rule['city'] === null) {
                return true;
            }

            if ($rule['state'] !== null && $regionName !== '' && self::regionMatches($rule['state'], $regionName)) {
                if ($rule['city'] === null) {
                    return true;
                }

                if ($cityName !== '' && self::cityMatches($rule['city'], $cityName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Blocklist match: visitor matches a blocked geo rule.
     */
    public static function isBlocked(
        DomainDetectionSetting $settings,
        ?string $countryCode,
        ?string $region,
        ?string $city,
        ?IpLog $ipLog = null,
        ?Domain $domain = null,
    ): bool {
        $settings = self::effectiveSettings($settings, $domain);

        if (! (bool) ($settings->google_geo_block_enabled ?? false)) {
            return false;
        }

        $rules = self::normalizedBlockRules($settings);
        if ($rules === []) {
            return false;
        }

        $country = strtoupper(trim((string) ($countryCode ?: $ipLog?->intel_country_code ?: '')));
        $regionName = self::normalizeName($region ?: self::regionFromIpLog($ipLog));
        $cityName = self::normalizeName($city ?: self::cityFromIpLog($ipLog));

        if ($country === '') {
            return false;
        }

        foreach ($rules as $rule) {
            if ($rule['country'] !== $country) {
                continue;
            }

            if ($rule['state'] === null && $rule['city'] === null) {
                return true;
            }

            if ($rule['state'] !== null && $regionName !== '' && self::regionMatches($rule['state'], $regionName)) {
                if ($rule['city'] === null) {
                    return true;
                }

                if ($cityName !== '' && self::cityMatches($rule['city'], $cityName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array{country: string, state: ?string, city: ?string}>
     */
    public static function normalizedBlockRules(DomainDetectionSetting $settings): array
    {
        $audience = (array) ($settings->google_geo_block_audience ?? []);
        $rules = [];

        foreach ((array) ($audience['rules'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $country = strtoupper(trim((string) ($row['country'] ?? '')));
            if ($country === '') {
                continue;
            }
            $rules[] = [
                'country' => $country,
                'state' => self::nullableCode($row['state'] ?? null),
                'city' => self::nullableName($row['city'] ?? null),
            ];
        }

        return $rules;
    }

    /**
     * @return list<array{country: string, state: ?string, city: ?string}>
     */
    public static function normalizedRules(DomainDetectionSetting $settings): array
    {
        $audience = (array) ($settings->out_of_geo_audience ?? []);
        $rules = [];

        if (isset($audience['rules']) && is_array($audience['rules'])) {
            foreach ($audience['rules'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $country = strtoupper(trim((string) ($row['country'] ?? '')));
                if ($country === '') {
                    continue;
                }
                $state = self::nullableCode($row['state'] ?? null);
                $city = self::nullableName($row['city'] ?? null);
                $rules[] = ['country' => $country, 'state' => $state, 'city' => $city];
            }
        }

        if ($rules !== []) {
            return $rules;
        }

        foreach ((array) ($settings->out_of_geo_countries ?? []) as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code !== '') {
                $rules[] = ['country' => $code, 'state' => null, 'city' => null];
            }
        }

        return $rules;
    }

    private static function regionFromIpLog(?IpLog $ipLog): ?string
    {
        $raw = (array) ($ipLog?->ipdetails_raw ?? []);

        return $raw['region'] ?? $raw['state'] ?? null;
    }

    private static function cityFromIpLog(?IpLog $ipLog): ?string
    {
        $raw = (array) ($ipLog?->ipdetails_raw ?? []);

        return $raw['city'] ?? null;
    }

    private static function nullableCode(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value !== '' ? $value : null;
    }

    private static function nullableName(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function normalizeName(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    private static function regionMatches(string $ruleState, string $regionName): bool
    {
        $rule = strtolower(trim($ruleState));
        if ($rule === $regionName) {
            return true;
        }

        return str_contains($regionName, $rule) || str_contains($rule, $regionName);
    }

    private static function cityMatches(string $ruleCity, string $cityName): bool
    {
        $rule = strtolower(trim($ruleCity));

        return $rule === $cityName || str_contains($cityName, $rule) || str_contains($rule, $cityName);
    }
}
