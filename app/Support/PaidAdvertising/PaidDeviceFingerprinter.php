<?php

namespace App\Support\PaidAdvertising;

/**
 * Device / fingerprint helpers for Manual v3 identity resolution.
 *
 * Device ID must NOT include cookie Browser ID — otherwise a cookie reset
 * creates a "new device" even when the fingerprint is the same person.
 *
 * Client tag fingerprint (payload.fingerprint) should include:
 * WebGL vendor/renderer, canvas characteristics, browser family, OS family,
 * device type, touch capability, pixel ratio, screen characteristics.
 * Server hashes that client string into FP_/DEV_ ids (cookie-independent).
 */
final class PaidDeviceFingerprinter
{
    public static function fingerprintId(?string $clientFingerprint, string $browserId, ?string $userAgent, ?string $acceptLanguage): string
    {
        $client = trim((string) $clientFingerprint);
        if ($client !== '') {
            return 'FP_'.strtoupper(substr(hash('sha256', $client), 0, 12));
        }

        // Fallback must be cookie-independent. Including $browserId here made
        // FP_/DEV_ rotate every visit when cx_bid was missing / private mode.
        $basis = 'ua|'.strtolower(trim((string) $userAgent))
            .'|lang|'.strtolower(trim((string) $acceptLanguage));

        return 'FP_'.strtoupper(substr(hash('sha256', $basis), 0, 12));
    }

    public static function deviceId(string $fingerprintId, ?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        $family = self::uaFamily($ua);

        // Device = fingerprint hash + UA family only.
        // Never Visitor ID / Browser ID — those are cookies and reset often.
        return 'DEV_'.strtoupper(substr(hash('sha256', $fingerprintId.'|'.$family), 0, 12));
    }

    public static function uaFamily(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        foreach (['iphone', 'ipad', 'android', 'windows', 'mac os', 'linux', 'cros'] as $needle) {
            if (str_contains($ua, $needle)) {
                return str_replace(' ', '', $needle);
            }
        }

        return 'other';
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
