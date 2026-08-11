<?php

namespace App\Support\PaidAdvertising;

/**
 * Device / fingerprint helpers for Manual v3 identity resolution.
 *
 * Device ID must NOT include cookie Browser ID — otherwise a cookie reset
 * creates a "new device" even when the fingerprint is the same person.
 */
final class PaidDeviceFingerprinter
{
    public static function fingerprintId(?string $clientFingerprint, string $browserId, ?string $userAgent, ?string $acceptLanguage): string
    {
        $client = trim((string) $clientFingerprint);
        if ($client !== '') {
            return 'FP_'.strtoupper(substr(hash('sha256', $client), 0, 12));
        }

        $basis = $browserId.'|'.((string) $userAgent).'|'.((string) $acceptLanguage);

        return 'FP_'.strtoupper(substr(hash('sha256', $basis), 0, 12));
    }

    public static function deviceId(string $fingerprintId, ?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        $family = 'other';
        foreach (['iphone', 'ipad', 'android', 'windows', 'mac os', 'linux', 'cros'] as $needle) {
            if (str_contains($ua, $needle)) {
                $family = str_replace(' ', '', $needle);
                break;
            }
        }

        // Fingerprint + coarse UA family only — cookie churn must not mint a new DEV_*.
        return 'DEV_'.strtoupper(substr(hash('sha256', $fingerprintId.'|'.$family), 0, 12));
    }

    /**
     * Similarity in [0,1]. Exact client string / identical FP id → 1.0.
     * Token overlap used when raw fingerprints differ slightly.
     */
    public static function similarity(?string $left, ?string $right): float
    {
        $a = trim((string) $left);
        $b = trim((string) $right);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        // Compare FP_/DEV_ style ids: same prefix hash → identical.
        if (str_starts_with($a, 'FP_') && str_starts_with($b, 'FP_')) {
            return $a === $b ? 1.0 : 0.0;
        }

        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $overlap = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        return $union > 0 ? round($overlap / $union, 4) : 0.0;
    }

    public static function isHighSimilarity(float $score): bool
    {
        return $score >= 0.92;
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $raw): array
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', strtolower($raw)) ?: [];

        return array_values(array_filter($parts, fn ($p) => strlen($p) >= 2));
    }
}
