<?php

namespace App\Support;

use App\Models\Domain;
use Illuminate\Http\Request;

/**
 * Ensures tracking keys only activate / ingest for the registered domain hostname.
 * Prevents Domain A keys pasted on Domain B from marking A as connected or logging traffic under A.
 */
class DomainKeyHostGuard
{
    /**
     * Validate browser Origin / Referer / page URL against the domain's registered hostname.
     *
     * @return string|null Error message when mismatched; null when allowed.
     */
    public static function mismatchReason(Request $request, Domain $domain, ?string $pageUrl = null): ?string
    {
        $registered = self::normalizeHost((string) $domain->hostname);
        if ($registered === '') {
            return 'Domain hostname is not configured.';
        }

        $candidates = [];

        $origin = trim((string) $request->headers->get('Origin', ''));
        if ($origin !== '' && strcasecmp($origin, 'null') !== 0) {
            $host = self::hostFromUrl($origin);
            if ($host !== '') {
                $candidates['origin'] = $host;
            }
        }

        $referer = trim((string) $request->headers->get('Referer', ''));
        if ($referer !== '') {
            $host = self::hostFromUrl($referer);
            if ($host !== '') {
                $candidates['referer'] = $host;
            }
        }

        $pageUrl = trim((string) ($pageUrl ?? ''));
        if ($pageUrl === '') {
            $pageUrl = trim((string) ($request->input('url') ?: $request->input('page_url') ?: ''));
        }
        if ($pageUrl !== '') {
            $host = self::hostFromUrl($pageUrl);
            if ($host !== '') {
                $candidates['page'] = $host;
            }
        }

        if ($candidates === []) {
            return 'Tracking request must include Origin, Referer, or page URL so the domain key can be bound to its registered hostname.';
        }

        // Prefer browser-controlled Origin, then Referer, then payload URL.
        foreach (['origin', 'referer', 'page'] as $key) {
            if (! isset($candidates[$key])) {
                continue;
            }
            if (! self::hostsMatch($candidates[$key], $registered)) {
                return sprintf(
                    'Domain key belongs to %s but this request came from %s. Install each site’s own keys — keys cannot be shared across domains.',
                    $registered,
                    $candidates[$key]
                );
            }

            return null;
        }

        return 'Hostname could not be verified for this domain key.';
    }

    public static function hostsMatch(string $candidate, string $registered): bool
    {
        $candidate = self::normalizeHost($candidate);
        $registered = self::normalizeHost($registered);

        if ($candidate === '' || $registered === '') {
            return false;
        }

        return $candidate === $registered
            || $candidate === 'www.'.$registered
            || 'www.'.$candidate === $registered;
    }

    public static function hostFromUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $value)) {
            // Bare host or path-only — treat as host when it looks like one.
            if (str_contains($value, '/') || str_contains($value, ' ')) {
                return '';
            }

            return self::normalizeHost($value);
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) ? self::normalizeHost($host) : '';
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host)[0] ?? $host;
        $host = explode(':', $host)[0] ?? $host;
        $host = rtrim($host, '.');

        return $host;
    }
}
