<?php

namespace App\Support;

use App\Models\Domain;
use Illuminate\Http\Request;

/**
 * Google Ads tracking-template click URL (ClickRonix-style) → landing redirect.
 */
final class GoogleAdsClickRedirect
{
    /** @var list<string> */
    public const TEMPLATE_PARAMS = [
        'final_url',
        'source',
        'adgroup_id',
        'keyword',
        'device',
        'network',
        'matchtype',
        'creative',
        'placement',
        'gclid',
        'gbraid',
        'wbraid',
    ];

    /** @var list<string> */
    public const FORWARD_QUERY_KEYS = [
        'gclid',
        'gbraid',
        'wbraid',
        'adgroup_id',
        'keyword',
        'source',
        'device',
        'network',
        'matchtype',
        'creative',
        'placement',
        'gad_source',
    ];

    public static function trackingTemplateUrl(?string $baseUrl = null): string
    {
        $base = TransparentClickTracker::baseUrl($baseUrl);

        return $base . '/click?redirect={lpurl}&final_url={lpurl}&source=google_ads'
            . '&cx_campaign={campaignid}&cx_adgroup={adgroupid}&cx_creative={creative}&cx_keyword={keyword}'
            . '&adgroup_id={adgroupid}&keyword={keyword}&device={device}&network={network}'
            . '&matchtype={matchtype}&creative={creative}&placement={placement}';
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseClickRequest(Request $request): array
    {
        $finalUrl = TransparentClickTracker::landingUrl($request);
        $registry = TransparentClickTracker::registryFromRequest($request);

        return [
            'final_url' => $finalUrl,
            'cxtrk' => trim((string) $request->query('cxtrk', '')),
            'cx_registry' => $registry,
            'source' => trim((string) $request->query('source', '')),
            'adgroup_id' => trim((string) ($request->query('adgroup_id') ?: $request->query('adgroupid') ?: '')),
            'keyword' => trim((string) $request->query('keyword', '')),
            'device' => trim((string) $request->query('device', '')),
            'network' => trim((string) $request->query('network', '')),
            'matchtype' => trim((string) $request->query('matchtype', '')),
            'creative' => trim((string) $request->query('creative', '')),
            'placement' => trim((string) $request->query('placement', '')),
            'gclid' => self::firstNonEmpty(
                trim((string) $request->query('gclid', '')),
                self::queryParamFromUrl($finalUrl, 'gclid'),
            ),
            'gbraid' => self::firstNonEmpty(
                trim((string) $request->query('gbraid', '')),
                self::queryParamFromUrl($finalUrl, 'gbraid'),
            ),
            'wbraid' => self::firstNonEmpty(
                trim((string) $request->query('wbraid', '')),
                self::queryParamFromUrl($finalUrl, 'wbraid'),
            ),
        ];
    }

    private static function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function queryParamFromUrl(string $url, string $key): string
    {
        $queryString = (string) parse_url($url, PHP_URL_QUERY);
        if ($queryString === '') {
            return '';
        }

        parse_str($queryString, $query);

        return trim((string) ($query[$key] ?? ''));
    }

    public static function resolveDomainFromFinalUrl(string $finalUrl): ?Domain
    {
        $host = self::normalizeHost((string) parse_url($finalUrl, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        $candidates = array_unique([$host, 'www.' . $host]);
        if (str_starts_with($host, 'www.')) {
            $candidates[] = substr($host, 4);
        }

        return Domain::query()
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhere('hostname', $candidate);
                }
            })
            ->orderBy('id')
            ->first();
    }

    public static function isAllowedFinalUrl(string $finalUrl, Domain $domain): bool
    {
        $host = self::normalizeHost((string) parse_url($finalUrl, PHP_URL_HOST));
        $registered = self::normalizeHost((string) $domain->hostname);

        if ($host === '' || $registered === '') {
            return false;
        }

        return $host === $registered
            || $host === 'www.' . $registered
            || 'www.' . $host === $registered;
    }

    /**
     * Build landing URL with click params forwarded for the site tag.
     */
    public static function buildRedirectUrl(string $finalUrl, array $params): string
    {
        $finalUrl = trim($finalUrl);
        if ($finalUrl === '') {
            return '/';
        }

        $parts = parse_url($finalUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $finalUrl;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if (! empty($params['gclid'])) {
            $query['gclid'] = $params['gclid'];
        }
        if (! empty($params['gbraid'])) {
            $query['gbraid'] = $params['gbraid'];
        }
        if (! empty($params['wbraid'])) {
            $query['wbraid'] = $params['wbraid'];
        }
        if (! empty($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (! empty($params['source'])) {
            $query['source'] = $params['source'];
        }
        if (! empty($params['cxtrk'])) {
            $query['cxtrk'] = $params['cxtrk'];
        }
        foreach (['adgroup_id', 'device', 'network', 'matchtype', 'creative', 'placement'] as $key) {
            if (! empty($params[$key])) {
                $query[$key] = $params[$key];
            }
        }

        $path = $parts['path'] ?? '/';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        $queryString = $query !== [] ? '?' . http_build_query($query) : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port . $path . $queryString . $fragment;
    }

    /**
     * @return array<string, mixed>
     */
    public static function adClickMeta(array $params): array
    {
        $registry = is_array($params['cx_registry'] ?? null) ? $params['cx_registry'] : [];

        return array_filter([
            'source' => $params['source'] ?? null,
            'cxtrk' => $params['cxtrk'] ?? null,
            'cx_registry' => $registry !== [] ? $registry : null,
            'adgroup_id' => $params['adgroup_id'] ?? null,
            'keyword' => $params['keyword'] ?? null,
            'device' => $params['device'] ?? null,
            'network' => $params['network'] ?? null,
            'matchtype' => $params['matchtype'] ?? null,
            'creative' => $params['creative'] ?? null,
            'placement' => $params['placement'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function normalizeHost(string $host): string
    {
        return strtolower(trim($host));
    }
}
