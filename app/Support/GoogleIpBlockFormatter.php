<?php

namespace App\Support;

class GoogleIpBlockFormatter
{
    /**
     * Normalize an IP / wildcard / CIDR value for Google Ads IpBlockInfo.
     * Google does NOT accept wildcards like 216.67.176.* — use CIDR instead.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.\*$/', $value, $m)) {
            for ($i = 1; $i <= 3; $i++) {
                if ((int) $m[$i] > 255) {
                    return null;
                }
            }

            return "{$m[1]}.{$m[2]}.{$m[3]}.0/24";
        }

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return str_contains($value, '/') ? $value : $value . '/32';
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\/(\d{1,2})$/', $value, $m)) {
            if (! self::isValidIpv4Cidr($m[1], (int) $m[2])) {
                return null;
            }

            return $m[1] . '/' . $m[2];
        }

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_contains($value, '/') ? $value : $value . '/128';
        }

        if (preg_match('/^([0-9a-fA-F:]+)\/(\d{1,3})$/', $value, $m)) {
            $prefix = (int) $m[2];
            if ($prefix < 0 || $prefix > 128 || ! filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return null;
            }

            return strtolower($m[1]) . '/' . $prefix;
        }

        return null;
    }

    public static function isSupported(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /** @return list<string> */
    public static function parseList(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $google = self::normalize($part);
            if ($google !== null) {
                $normalized[] = $google;
            }
        }

        return array_values(array_unique($normalized));
    }

    public static function matches(string $stored, string $candidate): bool
    {
        $a = self::normalize($stored) ?? $stored;
        $b = self::normalize($candidate) ?? $candidate;

        if ($a === $b) {
            return true;
        }

        // Compare single-host forms: 1.2.3.4 vs 1.2.3.4/32
        $aHost = preg_replace('/\/32$/', '', $a);
        $bHost = preg_replace('/\/32$/', '', $b);

        return $aHost !== '' && $aHost === $bHost;
    }

    private static function isValidIpv4Cidr(string $ip, int $prefix): bool
    {
        if ($prefix < 0 || $prefix > 32 || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        if ($prefix === 0) {
            return $ip === '0.0.0.0';
        }

        $mask = (-1 << (32 - $prefix)) & 0xFFFFFFFF;

        return ($long & $mask) === $long;
    }
}
