<?php

namespace App\Support\PaidMarketing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned response cache for Paid Advertising dashboard JSON endpoints.
 *
 * Cache keys include a data version (visit watermark + domain/settings signatures).
 * When a new visit/IP arrives, or domains/settings change, the version changes and
 * the next request naturally misses — so fresh events are never served as stale forever.
 *
 * A short TTL is a safety net for edge cases (e.g. in-place row updates that somehow
 * do not bump the version signature).
 */
class DashboardResponseCache
{
    public const TTL_SECONDS = 120;

    private string $lastStatus = 'BYPASS';

    public function lastStatus(): string
    {
        return $this->lastStatus;
    }

    /**
     * @param  Closure():mixed  $builder
     */
    public function remember(
        Request $request,
        string $bucket,
        string $version,
        Closure $builder,
        bool $bypass = false,
        ?int $ttlSeconds = null,
    ): mixed {
        if ($bypass || $version === '') {
            $this->lastStatus = 'BYPASS';

            return $builder();
        }

        $key = $this->key($request, $bucket, $version);
        $ttl = max(15, $ttlSeconds ?? self::TTL_SECONDS);

        if (Cache::has($key)) {
            $this->lastStatus = 'HIT';

            return Cache::get($key);
        }

        $this->lastStatus = 'MISS';
        $payload = $builder();
        Cache::put($key, $payload, $ttl);

        return $payload;
    }

    public function key(Request $request, string $bucket, string $version): string
    {
        $userId = (int) ($request->user()?->id ?? 0);

        return implode(':', [
            'pmdash',
            'v1',
            (string) $userId,
            $bucket,
            $this->filterFingerprint($request),
            $version,
        ]);
    }

    public function filterFingerprint(Request $request): string
    {
        $parts = [
            'domain_id' => (string) $request->query('domain_id', ''),
            'google_ads_account_id' => (string) $request->query('google_ads_account_id', ''),
            'path' => (string) $request->query('path', ''),
            'campaign' => (string) $request->query('campaign', ''),
            'campaign_id' => (string) $request->query('campaign_id', ''),
            'traffic_source' => (string) $request->query('traffic_source', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'window' => (string) $request->query('window', ''),
            'country' => (string) $request->query('country', ''),
            'ip' => (string) $request->query('ip', ''),
        ];

        return substr(hash('sha256', json_encode($parts)), 0, 24);
    }
}
