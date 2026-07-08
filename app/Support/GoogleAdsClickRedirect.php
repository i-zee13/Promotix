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
        'campaign_id',
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
        'campaign_id',
        'adgroup_id',
        'keyword',
        'source',
        'device',
        'network',
        'matchtype',
        'creative',
        'placement',
        'gad_campaignid',
        'gad_source',
    ];

    public static function trackingTemplateUrl(?string $baseUrl = null): string
    {
        $base = rtrim($baseUrl ?? (string) config('app.url'), '/');

        return $base . '/click?final_url={lpurl}&source=google_ads&campaign_id={campaignid}'
            . '&adgroup_id={adgroupid}&keyword={keyword}&device={device}&network={network}'
            . '&matchtype={matchtype}&creative={creative}&placement={placement}';
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseClickRequest(Request $request): array
    {
        $finalUrl = trim((string) ($request->query('final_url') ?: $request->query('url') ?: ''));

        $campaignId = trim((string) ($request->query('campaign_id') ?: $request->query('campaignid') ?: ''));
        $gadCampaignId = trim((string) ($request->query('gad_campaignid') ?: $campaignId));

        return [
            'final_url' => $finalUrl,
            'source' => trim((string) $request->query('source', '')),
            'campaign_id' => $campaignId,
            'gad_campaignid' => $gadCampaignId,
            'adgroup_id' => trim((string) ($request->query('adgroup_id') ?: $request->query('adgroupid') ?: '')),
            'keyword' => trim((string) $request->query('keyword', '')),
            'device' => trim((string) $request->query('device', '')),
            'network' => trim((string) $request->query('network', '')),
            'matchtype' => trim((string) $request->query('matchtype', '')),
            'creative' => trim((string) $request->query('creative', '')),
            'placement' => trim((string) $request->query('placement', '')),
            'gclid' => trim((string) $request->query('gclid', '')),
            'gbraid' => trim((string) $request->query('gbraid', '')),
            'wbraid' => trim((string) $request->query('wbraid', '')),
        ];
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
        if (! empty($params['gad_campaignid'])) {
            $query['gad_campaignid'] = preg_replace('/\D+/', '', (string) $params['gad_campaignid']) ?? $params['gad_campaignid'];
        }
        if (! empty($params['campaign_id']) && empty($query['gad_campaignid'])) {
            $query['campaign_id'] = preg_replace('/\D+/', '', (string) $params['campaign_id']) ?? $params['campaign_id'];
        }
        if (! empty($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (! empty($params['source'])) {
            $query['source'] = $params['source'];
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
        return array_filter([
            'source' => $params['source'] ?? null,
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
