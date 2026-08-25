<?php

namespace App\Support;

class TrafficSourceClassifier
{
    /** @return 'paid'|'organic'|'social'|'direct'|'referral' */
    public static function bucket(
        bool $isPaid = false,
        ?string $utmMedium = null,
        ?string $utmSource = null,
        ?string $referrer = null,
        ?string $gclid = null,
    ): string {
        $medium = strtolower(trim((string) $utmMedium));
        $source = strtolower(trim((string) $utmSource));
        $ref = strtolower(trim((string) $referrer));

        if ($isPaid || filled($gclid) || in_array($medium, ['cpc', 'ppc', 'paid', 'paidsearch', 'cpm'], true)) {
            return 'paid';
        }

        if ($medium === 'social' || self::isSocialReferrer($ref)) {
            return 'social';
        }

        if ($medium === 'organic' || self::isSearchReferrer($ref)) {
            return 'organic';
        }

        if ($ref === '' && $source === '') {
            return 'direct';
        }

        if ($ref !== '') {
            return 'referral';
        }

        if ($source !== '') {
            return in_array($source, ['google', 'bing', 'yahoo', 'duckduckgo'], true) ? 'organic' : 'referral';
        }

        return 'direct';
    }

    public static function platformLabel(
        bool $isPaid = false,
        ?string $utmMedium = null,
        ?string $utmSource = null,
        ?string $referrer = null,
    ): string {
        $ref = strtolower(trim((string) $referrer));
        $source = strtolower(trim((string) $utmSource));

        if ($isPaid || in_array(strtolower((string) $utmMedium), ['cpc', 'ppc', 'paid'], true)) {
            return 'Paid Search';
        }

        if (str_contains($ref, 'google.') || $source === 'google') {
            return 'Google';
        }
        if (str_contains($ref, 'facebook.') || str_contains($ref, 'fb.') || $source === 'facebook') {
            return 'Facebook';
        }
        if (str_contains($ref, 'instagram.') || $source === 'instagram') {
            return 'Instagram';
        }
        if (str_contains($ref, 'yahoo.') || $source === 'yahoo') {
            return 'Yahoo';
        }
        if (str_contains($ref, 'bing.') || $source === 'bing') {
            return 'Bing';
        }
        if (str_contains($ref, 'linkedin.') || str_contains($ref, 'twitter.') || str_contains($ref, 't.co')) {
            return 'Social';
        }

        $bucket = self::bucket($isPaid, $utmMedium, $utmSource, $referrer);

        return match ($bucket) {
            'direct' => 'Direct',
            'organic' => 'Organic Search',
            'social' => 'Social Media',
            'referral' => 'Backlinks',
            'paid' => 'Paid Search',
        };
    }

    private static function isSocialReferrer(string $ref): bool
    {
        return (bool) preg_match('/facebook|instagram|twitter|linkedin|tiktok|t\.co|pinterest|reddit|youtube\.com\/shorts/', $ref);
    }

    private static function isSearchReferrer(string $ref): bool
    {
        return (bool) preg_match('/google\.|bing\.|yahoo\.|duckduckgo\.|baidu\.|yandex\./', $ref);
    }

    public static function pathFromUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '/';
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    public static function deviceBucket(?string $device, ?string $os = null): string
    {
        $label = strtolower(trim((string) ($device ?: $os ?: '')));
        if (str_contains($label, 'mobile') || str_contains($label, 'iphone') || str_contains($label, 'android')) {
            return 'mobile';
        }
        if (str_contains($label, 'tablet') || str_contains($label, 'ipad')) {
            return 'tablet';
        }
        if ($label !== '') {
            return 'desktop';
        }

        return 'other';
    }
}
