<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;

class GeoAudienceMatcher
{
    /** @var array<string, list<string>> US state code → accepted aliases (lowercase). */
    private const US_STATE_ALIASES = [
        'AL' => ['alabama', 'al'],
        'AK' => ['alaska', 'ak'],
        'AZ' => ['arizona', 'az'],
        'AR' => ['arkansas', 'ar'],
        'CA' => ['california', 'ca', 'calif'],
        'CO' => ['colorado', 'co'],
        'CT' => ['connecticut', 'ct'],
        'DE' => ['delaware', 'de'],
        'FL' => ['florida', 'fl'],
        'GA' => ['georgia', 'ga'],
        'HI' => ['hawaii', 'hi'],
        'ID' => ['idaho', 'id'],
        'IL' => ['illinois', 'il'],
        'IN' => ['indiana', 'in'],
        'IA' => ['iowa', 'ia'],
        'KS' => ['kansas', 'ks'],
        'KY' => ['kentucky', 'ky'],
        'LA' => ['louisiana', 'la'],
        'ME' => ['maine', 'me'],
        'MD' => ['maryland', 'md'],
        'MA' => ['massachusetts', 'ma'],
        'MI' => ['michigan', 'mi'],
        'MN' => ['minnesota', 'mn'],
        'MS' => ['mississippi', 'ms'],
        'MO' => ['missouri', 'mo'],
        'MT' => ['montana', 'mt'],
        'NE' => ['nebraska', 'ne'],
        'NV' => ['nevada', 'nv'],
        'NH' => ['new hampshire', 'nh'],
        'NJ' => ['new jersey', 'nj'],
        'NM' => ['new mexico', 'nm'],
        'NY' => ['new york', 'ny'],
        'NC' => ['north carolina', 'nc'],
        'ND' => ['north dakota', 'nd'],
        'OH' => ['ohio', 'oh'],
        'OK' => ['oklahoma', 'ok'],
        'OR' => ['oregon', 'or'],
        'PA' => ['pennsylvania', 'pa'],
        'RI' => ['rhode island', 'ri'],
        'SC' => ['south carolina', 'sc'],
        'SD' => ['south dakota', 'sd'],
        'TN' => ['tennessee', 'tn'],
        'TX' => ['texas', 'tx'],
        'UT' => ['utah', 'ut'],
        'VT' => ['vermont', 'vt'],
        'VA' => ['virginia', 'va'],
        'WA' => ['washington', 'wa'],
        'WV' => ['west virginia', 'wv'],
        'WI' => ['wisconsin', 'wi'],
        'WY' => ['wyoming', 'wy'],
        'DC' => ['district of columbia', 'washington dc', 'washington, d.c.', 'dc'],
    ];

    /**
     * Common US cities → state code. Used when IP intel has city but region is
     * missing, a metro label, or the city was incorrectly stored as region.
     *
     * @var array<string, string>
     */
    private const US_CITY_STATE = [
        // California (export false-positives + metros)
        'los angeles' => 'CA',
        'san diego' => 'CA',
        'san francisco' => 'CA',
        'san jose' => 'CA',
        'sacramento' => 'CA',
        'oakland' => 'CA',
        'fresno' => 'CA',
        'long beach' => 'CA',
        'bakersfield' => 'CA',
        'anaheim' => 'CA',
        'santa ana' => 'CA',
        'riverside' => 'CA',
        'stockton' => 'CA',
        'irvine' => 'CA',
        'chula vista' => 'CA',
        'fremont' => 'CA',
        'san bernardino' => 'CA',
        'modesto' => 'CA',
        'fontana' => 'CA',
        'oxnard' => 'CA',
        'moreno valley' => 'CA',
        'huntington beach' => 'CA',
        'glendale' => 'CA',
        'santa clarita' => 'CA',
        'garden grove' => 'CA',
        'oceanside' => 'CA',
        'rancho cucamonga' => 'CA',
        'santa rosa' => 'CA',
        'ontario' => 'CA',
        'elk grove' => 'CA',
        'corona' => 'CA',
        'lancaster' => 'CA',
        'palmdale' => 'CA',
        'salinas' => 'CA',
        'hayward' => 'CA',
        'pomona' => 'CA',
        'escondido' => 'CA',
        'sunnyvale' => 'CA',
        'torrance' => 'CA',
        'pasadena' => 'CA',
        'orange' => 'CA',
        'fullerton' => 'CA',
        'thousand oaks' => 'CA',
        'visalia' => 'CA',
        'simi valley' => 'CA',
        'concord' => 'CA',
        'roseville' => 'CA',
        'santa clara' => 'CA',
        'vallejo' => 'CA',
        'victorville' => 'CA',
        'el monte' => 'CA',
        'berkeley' => 'CA',
        'downey' => 'CA',
        'costa mesa' => 'CA',
        'inglewood' => 'CA',
        'carlsbad' => 'CA',
        'san buenaventura' => 'CA',
        'ventura' => 'CA',
        'fairfield' => 'CA',
        'west covina' => 'CA',
        'murrieta' => 'CA',
        'richmond' => 'CA',
        'norwalk' => 'CA',
        'antioch' => 'CA',
        'temecula' => 'CA',
        'burbank' => 'CA',
        'daly city' => 'CA',
        'rialto' => 'CA',
        'el cajon' => 'CA',
        'san mateo' => 'CA',
        'clovis' => 'CA',
        'compton' => 'CA',
        'jurupa valley' => 'CA',
        'vista' => 'CA',
        'south gate' => 'CA',
        'mission viejo' => 'CA',
        'vacaville' => 'CA',
        'carson' => 'CA',
        'hesperia' => 'CA',
        'santa maria' => 'CA',
        'redding' => 'CA',
        'chico' => 'CA',
        'tracy' => 'CA',
        'alhambra' => 'CA',
        'livermore' => 'CA',
        'citrus heights' => 'CA',
        'hawthorne' => 'CA',
        'whittier' => 'CA',
        'newport beach' => 'CA',
        'san leandro' => 'CA',
        'san ramon' => 'CA',
        'upland' => 'CA',
        'mountain view' => 'CA',
        'tujunga' => 'CA',
        'north hills' => 'CA',
        'sylmar' => 'CA',
        'north highlands' => 'CA',
        'van nuys' => 'CA',
        'sherman oaks' => 'CA',
        'hollywood hills' => 'CA',
        'santa monica' => 'CA',
        'palo alto' => 'CA',
        'redwood city' => 'CA',
        'cupertino' => 'CA',
        'milpitas' => 'CA',
        'napa' => 'CA',
        'davis' => 'CA',
        // Other frequent US cities (non-CA) so inference stays accurate
        'las vegas' => 'NV',
        'henderson' => 'NV',
        'reno' => 'NV',
        'seattle' => 'WA',
        'spokane' => 'WA',
        'tacoma' => 'WA',
        'portland' => 'OR',
        'phoenix' => 'AZ',
        'tucson' => 'AZ',
        'denver' => 'CO',
        'houston' => 'TX',
        'dallas' => 'TX',
        'austin' => 'TX',
        'san antonio' => 'TX',
        'chicago' => 'IL',
        'new york' => 'NY',
        'new york city' => 'NY',
        'brooklyn' => 'NY',
        'miami' => 'FL',
        'orlando' => 'FL',
        'tampa' => 'FL',
        'atlanta' => 'GA',
        'boston' => 'MA',
        'philadelphia' => 'PA',
        'detroit' => 'MI',
        'minneapolis' => 'MN',
        'salt lake city' => 'UT',
        'albuquerque' => 'NM',
        'oklahoma city' => 'OK',
        'tulsa' => 'OK',
        'washington' => 'DC',
        'washington dc' => 'DC',
    ];

    /** @var array<string, string> */
    private const COUNTRY_ALIASES = [
        'UNITED STATES' => 'US',
        'UNITED STATES OF AMERICA' => 'US',
        'USA' => 'US',
        'U.S.' => 'US',
        'U.S.A.' => 'US',
        'AMERICA' => 'US',
        'CANADA' => 'CA',
        'UNITED KINGDOM' => 'GB',
        'GREAT BRITAIN' => 'GB',
        'UK' => 'GB',
        'AUSTRALIA' => 'AU',
    ];

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

        $country = self::normalizeCountryCode($countryCode ?: $ipLog?->intel_country_code ?: $ipLog?->intel_country_name);
        [$regionName, $cityName] = self::resolvePlaceNames($region, $city, $ipLog);

        if ($country === '') {
            return false;
        }

        foreach ($rules as $rule) {
            if ($rule['country'] !== $country) {
                continue;
            }

            // Country-only rule (All regions).
            if ($rule['state'] === null && $rule['city'] === null) {
                return true;
            }

            if ($rule['state'] !== null && ! self::stateRuleMatches($rule['state'], $regionName, $cityName, $country)) {
                continue;
            }

            if ($rule['city'] === null) {
                return true;
            }

            if ($cityName !== '' && self::cityMatches($rule['city'], $cityName)) {
                return true;
            }
        }

        return false;
    }

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

        $country = self::normalizeCountryCode($countryCode ?: $ipLog?->intel_country_code ?: $ipLog?->intel_country_name);
        [$regionName, $cityName] = self::resolvePlaceNames($region, $city, $ipLog);

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

            // Don't block on incomplete intel for state/city block rules.
            if ($regionName === '' && $cityName === '') {
                continue;
            }

            if ($rule['state'] !== null && ! self::stateRuleMatches($rule['state'], $regionName, $cityName, $country, softAllowIncomplete: false)) {
                continue;
            }

            if ($rule['city'] === null) {
                return true;
            }

            if ($cityName !== '' && self::cityMatches($rule['city'], $cityName)) {
                return true;
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
            $country = self::normalizeCountryCode($row['country'] ?? null);
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
                $country = self::normalizeCountryCode($row['country'] ?? null);
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
            $code = self::normalizeCountryCode($code);
            if ($code !== '') {
                $rules[] = ['country' => $code, 'state' => null, 'city' => null];
            }
        }

        return $rules;
    }

    public static function normalizeCountryCode(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '';
        }

        if (isset(self::COUNTRY_ALIASES[$value])) {
            return self::COUNTRY_ALIASES[$value];
        }

        if (strlen($value) === 2) {
            return $value;
        }

        return self::COUNTRY_ALIASES[$value] ?? $value;
    }

    /**
     * @return array{0: string, 1: string} [region, city] normalized lowercase
     */
    private static function resolvePlaceNames(?string $region, ?string $city, ?IpLog $ipLog): array
    {
        $raw = (array) ($ipLog?->ipdetails_raw ?? []);

        $regionName = self::normalizeName(
            $region
            ?: ($ipLog?->intel_region ?? null)
            ?: ($raw['region'] ?? null)
            ?: ($raw['region_code'] ?? null)
            ?: ($raw['state'] ?? null)
            ?: ($raw['state_code'] ?? null)
            ?: ($raw['state1'] ?? null)
            ?: ($raw['state2'] ?? null)
            ?: ($raw['_geo_region'] ?? null)
        );
        $cityName = self::normalizeName(
            $city
            ?: ($ipLog?->intel_city ?? null)
            ?: ($raw['city'] ?? null)
            ?: ($raw['_geo_city'] ?? null)
        );

        // Some providers put the city into "region" when state is missing.
        if ($regionName !== '' && $cityName !== '' && $regionName === $cityName) {
            $regionName = '';
        }

        // ipdetails.io often uses state1/state2; if "region" is actually a city
        // label, treat it as the city for California / state inference.
        if ($regionName !== '' && $cityName === '' && ! self::looksLikeUsState($regionName)) {
            $cityName = $regionName;
            $regionName = '';
        }

        return [$regionName, $cityName];
    }

    /**
     * State-level allow/block match.
     *
     * Prefer an explicit US state on the IP. If region is missing / not a state
     * (metro label, city leaked into region), infer state from city so CA cities
     * are not false-positive Out of Geo against a California rule.
     */
    private static function stateRuleMatches(
        string $ruleState,
        string $regionName,
        string $cityName,
        string $country,
        bool $softAllowIncomplete = true,
    ): bool {
        if ($regionName !== '' && self::looksLikeUsState($regionName, $country)) {
            return self::regionMatches($ruleState, $regionName, $country);
        }

        if ($cityName !== '' && ($country === 'US' || $country === '')) {
            $inferred = self::inferUsStateCodeFromCity($cityName);
            if ($inferred !== null) {
                return self::regionMatches($ruleState, $inferred, 'US');
            }
        }

        if ($regionName !== '' && self::regionMatches($ruleState, $regionName, $country)) {
            return true;
        }

        // Incomplete intel: prefer allow over false-positive Out of Geo.
        if ($softAllowIncomplete && $regionName === '') {
            return true;
        }

        return false;
    }

    private static function looksLikeUsState(string $regionName, string $country = 'US'): bool
    {
        if ($country !== '' && $country !== 'US') {
            return false;
        }

        $region = strtolower(trim($regionName));
        if ($region === '') {
            return false;
        }

        foreach (self::US_STATE_ALIASES as $code => $aliases) {
            $aliasSet = array_merge([strtolower($code)], $aliases);
            if (in_array($region, $aliasSet, true)) {
                return true;
            }
        }

        return false;
    }

    private static function inferUsStateCodeFromCity(string $cityName): ?string
    {
        $city = strtolower(trim($cityName));
        if ($city === '') {
            return null;
        }

        if (isset(self::US_CITY_STATE[$city])) {
            return self::US_CITY_STATE[$city];
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('geo_cities')) {
                $row = \Illuminate\Support\Facades\DB::table('geo_cities')
                    ->where('country_code', 'US')
                    ->whereRaw('LOWER(name) = ?', [$city])
                    ->value('state_code');
                if (is_string($row) && $row !== '') {
                    return strtoupper($row);
                }
            }
        } catch (\Throwable) {
            // Unit tests / no DB — fall through.
        }

        return null;
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

    private static function regionMatches(string $ruleState, string $regionName, string $country = ''): bool
    {
        $rule = strtolower(trim($ruleState));
        $region = strtolower(trim($regionName));
        if ($rule === '' || $region === '') {
            return false;
        }

        if ($rule === $region) {
            return true;
        }

        if ($country === 'US' || strlen($rule) === 2 || strlen($region) === 2) {
            foreach (self::US_STATE_ALIASES as $stateCode => $aliases) {
                $aliasSet = array_merge([strtolower($stateCode)], $aliases);
                $ruleIsState = in_array($rule, $aliasSet, true);
                $regionIsState = in_array($region, $aliasSet, true);
                if ($ruleIsState && $regionIsState) {
                    return true;
                }
            }

            // Full-name contains match only for aliases longer than 2 chars
            // (avoid "ca" matching inside "north carolina").
            $code = strtoupper($rule);
            $aliases = self::US_STATE_ALIASES[$code] ?? null;
            if ($aliases !== null) {
                foreach ($aliases as $alias) {
                    if (strlen($alias) <= 2) {
                        continue;
                    }
                    if ($region === $alias || str_contains($region, $alias)) {
                        return true;
                    }
                }
            }
        }

        return str_contains($region, $rule) || str_contains($rule, $region);
    }

    private static function cityMatches(string $ruleCity, string $cityName): bool
    {
        $rule = strtolower(trim($ruleCity));

        return $rule === $cityName || str_contains($cityName, $rule) || str_contains($rule, $cityName);
    }
}
