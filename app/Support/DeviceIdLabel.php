<?php

namespace App\Support;

/**
 * Canonical Device ID labels shared by Visitor Journey, Traffic Control, and Advanced View.
 * Keeps display + search aligned so a copied Device ID can be found elsewhere.
 */
final class DeviceIdLabel
{
    /**
     * Prefer real DEV_ tokens; never surface unknown_* placeholders in the UI.
     */
    public static function format(?string $deviceRaw, ?string $fingerprintRaw = null, ?string $ip = null): string
    {
        $deviceRaw = trim((string) $deviceRaw);
        $fingerprintRaw = trim((string) $fingerprintRaw);
        $ip = trim((string) $ip);

        if ($deviceRaw !== '') {
            if (str_starts_with($deviceRaw, 'unknown_')) {
                return 'DEV_'.strtoupper(substr(hash('sha256', $deviceRaw), 0, 12));
            }
            if (str_starts_with($deviceRaw, 'FP_')) {
                return 'DEV_'.substr($deviceRaw, 3);
            }
            if (str_starts_with($deviceRaw, 'dev_')) {
                return 'DEV_'.substr($deviceRaw, 4);
            }

            return $deviceRaw;
        }

        if ($fingerprintRaw !== '') {
            if (str_starts_with($fingerprintRaw, 'DEV_') || str_starts_with($fingerprintRaw, 'dev_')) {
                return str_starts_with($fingerprintRaw, 'dev_')
                    ? 'DEV_'.substr($fingerprintRaw, 4)
                    : $fingerprintRaw;
            }
            if (str_starts_with($fingerprintRaw, 'FP_')) {
                return 'DEV_'.substr($fingerprintRaw, 3);
            }

            return 'DEV_'.strtoupper(substr(hash('sha256', $fingerprintRaw), 0, 12));
        }

        if ($ip !== '') {
            return 'DEV_'.strtoupper(substr(hash('sha256', 'ip|'.$ip), 0, 12));
        }

        return 'DEV_UNKNOWN';
    }

    /**
     * Variants to try when searching DB columns for a UI Device ID.
     *
     * @return list<string>
     */
    public static function searchNeedles(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $needles = [$term];
        $upper = strtoupper($term);
        $lower = strtolower($term);
        $needles[] = $upper;
        $needles[] = $lower;

        if (preg_match('/^dev_(.+)$/i', $term, $m)) {
            $needles[] = 'DEV_'.$m[1];
            $needles[] = 'dev_'.$m[1];
            $needles[] = 'FP_'.$m[1];
            $needles[] = $m[1];
        }

        if (preg_match('/^fp_(.+)$/i', $term, $m)) {
            $needles[] = 'DEV_'.$m[1];
            $needles[] = 'FP_'.$m[1];
        }

        return array_values(array_unique(array_filter($needles, static fn ($v) => $v !== '')));
    }

    public static function looksLikeDeviceId(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^(DEV_|dev_|FP_|fp_)[A-Za-z0-9_-]+$/i', $value);
    }
}
