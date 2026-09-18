<?php

namespace App\Support;

use App\Models\GlobalIpAllowlistEntry;
use App\Models\IpLog;
use App\Services\IpIntel\IpFraudEvaluator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide provider / IP blocklist (super-admin).
 * When Google (or another provider) is blocklisted, all matching CIDRs + ASN/ISP hits are blocked.
 */
class GlobalIpBlocklist
{
    public const CACHE_KEY = 'global_ip_blocklist_patterns';

    public const CACHE_PROVIDERS_KEY = 'global_ip_blocklist_providers';

    public static function matches(string $ip, array $context = [], ?IpLog $ipLog = null): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }

        // Never block if the same IP is on the platform whitelist.
        if (GlobalIpAllowlist::matches($ip, $context, $ipLog)) {
            return false;
        }

        if (IpFraudEvaluator::isIpInList($ip, implode("\n", self::patterns()))) {
            return true;
        }

        return self::matchesProviderIdentity($context, $ipLog);
    }

    public static function matchesIp(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }

        $ipLog = IpLog::query()->where('ip', $ip)->orderByDesc('id')->first();

        return self::matches($ip, [
            'isp' => $ipLog?->intel_isp,
            'org' => $ipLog?->intel_isp,
            'asn' => $ipLog?->intel_asn ?? data_get($ipLog?->ipdetails_raw, 'asn'),
            'raw' => $ipLog?->ipdetails_raw ?? [],
        ], $ipLog);
    }

    /**
     * @return list<string>
     */
    public static function patterns(): array
    {
        $catalog = GlobalIpAllowlist::providerCidrs();

        if (! self::tableReady()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () use ($catalog): array {
            $patterns = [];
            $entries = GlobalIpAllowlistEntry::query()
                ->where('enabled', true)
                ->where('list_type', 'block')
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
    public static function blockedProviders(): array
    {
        if (! self::tableReady()) {
            return [];
        }

        return Cache::remember(self::CACHE_PROVIDERS_KEY, now()->addMinutes(5), function (): array {
            return GlobalIpAllowlistEntry::query()
                ->where('kind', 'provider')
                ->where('enabled', true)
                ->where('list_type', 'block')
                ->get(['provider', 'value'])
                ->map(fn (GlobalIpAllowlistEntry $entry) => strtolower((string) ($entry->provider ?: $entry->value)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }

    public static function flushCaches(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_PROVIDERS_KEY);
    }

    public static function flush(): void
    {
        self::flushCaches();
        Cache::forget(GlobalIpAllowlist::CACHE_KEY);
        Cache::forget(GlobalIpAllowlist::CACHE_PROVIDERS_KEY);
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('global_ip_allowlist_entries')
                && Schema::hasColumn('global_ip_allowlist_entries', 'list_type');
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

        foreach (self::blockedProviders() as $provider) {
            $identity = GlobalIpAllowlist::providerIdentity()[$provider] ?? null;
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
