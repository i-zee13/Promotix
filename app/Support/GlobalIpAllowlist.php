<?php

namespace App\Support;

use App\Models\GlobalIpAllowlistEntry;
use App\Models\IpLog;
use App\Services\IpIntel\IpFraudEvaluator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class GlobalIpAllowlist
{
    public const CACHE_KEY = 'global_ip_allowlist_patterns';

    public const CACHE_PROVIDERS_KEY = 'global_ip_allowlist_providers';

    /**
     * Known crawler / ads-bot CIDRs. Enable via System Settings → IP / Provider Whitelist.
     *
     * @return array<string, list<string>>
     */
    public static function providerCidrs(): array
    {
        return [
            'google' => [
                '66.249.0.0/16',
                '64.233.160.0/19',
                '72.14.192.0/18',
                '74.125.0.0/16',
                '209.85.128.0/17',
                '216.239.32.0/19',
                '66.102.0.0/20',
                '2001:4860::/32',
            ],
            'bing' => [
                '40.77.167.0/24',
                '207.46.13.0/24',
                '157.55.39.0/24',
                '13.66.139.0/24',
            ],
            'meta' => [
                '31.13.24.0/21',
                '66.220.144.0/20',
                '69.63.176.0/20',
                '69.171.224.0/19',
            ],
        ];
    }

    /**
     * @return array<string, array{asns: list<int>, needles: list<string>}>
     */
    public static function providerIdentity(): array
    {
        return [
            'google' => [
                'asns' => [15169, 36040, 36384],
                'needles' => ['google llc', 'google inc', 'googlebot', 'adsbot-google', 'google ireland'],
            ],
            'bing' => [
                'asns' => [8075],
                'needles' => ['microsoft', 'bingbot', 'msnbot'],
            ],
            'meta' => [
                'asns' => [32934],
                'needles' => ['facebook', 'meta platforms', 'facebookbot'],
            ],
        ];
    }

    public static function matches(string $ip, array $context = [], ?IpLog $ipLog = null): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }

        if (IpFraudEvaluator::isIpInList($ip, implode("\n", self::patterns()))) {
            return true;
        }

        return self::matchesProviderIdentity($context, $ipLog);
    }

    /**
     * @return list<string>
     */
    public static function patterns(): array
    {
        $catalog = self::providerCidrs();

        if (! self::tableReady()) {
            // Until the admin table is migrated, still trust Google crawler ranges.
            return $catalog['google'];
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () use ($catalog): array {
            $patterns = [];
            $entries = GlobalIpAllowlistEntry::query()
                ->where('enabled', true)
                ->get(['kind', 'provider', 'value']);

            foreach ($entries as $entry) {
                if ($entry->kind === 'provider') {
                    $key = strtolower((string) ($entry->provider ?: $entry->value));
                    foreach ($catalog[$key] ?? [] as $cidr) {
                        $patterns[] = $cidr;
                    }

                    continue;
                }

                $value = trim((string) $entry->value);
                if ($value !== '') {
                    $patterns[] = $value;
                }
            }

            return array_values(array_unique($patterns));
        });
    }

    /**
     * @return list<string>
     */
    public static function enabledProviders(): array
    {
        if (! self::tableReady()) {
            return ['google'];
        }

        return Cache::remember(self::CACHE_PROVIDERS_KEY, now()->addMinutes(5), function (): array {
            return GlobalIpAllowlistEntry::query()
                ->where('kind', 'provider')
                ->where('enabled', true)
                ->get(['provider', 'value'])
                ->map(fn (GlobalIpAllowlistEntry $entry) => strtolower((string) ($entry->provider ?: $entry->value)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_PROVIDERS_KEY);
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('global_ip_allowlist_entries');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function matchesProviderIdentity(array $context, ?IpLog $ipLog): bool
    {
        $raw = $context['raw'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }
        if ($ipLog !== null && is_array($ipLog->ipdetails_raw)) {
            $raw = array_merge($raw, $ipLog->ipdetails_raw);
        } elseif ($ipLog !== null && is_string($ipLog->ipdetails_raw)) {
            $decoded = json_decode($ipLog->ipdetails_raw, true);
            if (is_array($decoded)) {
                $raw = array_merge($raw, $decoded);
            }
        }

        $asn = self::normalizeAsn(
            $context['asn']
                ?? $raw['ASN']
                ?? $raw['asn']
                ?? $raw['as_number']
                ?? data_get($raw, 'connection.asn')
                ?? null
        );

        $haystack = strtolower(trim(implode(' ', array_filter([
            (string) ($context['org'] ?? ''),
            (string) ($context['isp'] ?? ''),
            (string) ($context['company'] ?? ''),
            (string) ($ipLog?->intel_isp ?? ''),
            (string) ($raw['company'] ?? ''),
            (string) ($raw['org'] ?? ''),
            (string) ($raw['isp'] ?? ''),
            (string) data_get($raw, 'connection.org', ''),
            (string) data_get($raw, 'company.name', ''),
        ]))));

        foreach (self::enabledProviders() as $provider) {
            $identity = self::providerIdentity()[$provider] ?? null;
            if ($identity === null) {
                continue;
            }

            if ($asn !== null && in_array($asn, $identity['asns'], true)) {
                return true;
            }

            foreach ($identity['needles'] as $needle) {
                if ($haystack !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function normalizeAsn(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/(\d+)/', $value, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }
}
