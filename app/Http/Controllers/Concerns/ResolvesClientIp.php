<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesClientIp
{
    protected function clientIp(Request $request): string
    {
        // Prefer CDN / edge identity headers before X-Forwarded-For (TR-01).
        $trustedFirst = [
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('True-Client-IP'),
            $request->headers->get('X-Real-IP'),
        ];

        foreach ($trustedFirst as $value) {
            $ip = trim((string) $value);
            if ($ip !== '' && $this->isValidIp($ip) && ! $this->isLoopbackIp($ip) && ! $this->isPrivateOrReserved($ip)) {
                return $ip;
            }
            if ($ip !== '' && $this->isValidIp($ip) && ! $this->isLoopbackIp($ip)) {
                return $ip;
            }
        }

        $forwarded = $request->headers->get('X-Forwarded-For')
            ?: $request->headers->get('X-Cluster-Client-IP');

        if ($forwarded) {
            foreach (preg_split('/\s*,\s*/', $forwarded) ?: [] as $candidate) {
                $ip = trim((string) $candidate);
                // Skip private/reserved hops commonly injected by spoofed client headers.
                if ($ip !== '' && $this->isValidIp($ip) && ! $this->isLoopbackIp($ip) && ! $this->isPrivateOrReserved($ip)) {
                    return $ip;
                }
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    protected function isLoopbackIp(string $ip): bool
    {
        return $ip === '127.0.0.1' || $ip === '::1';
    }

    protected function isPrivateOrReserved(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    protected function cors(Request $request, $response)
    {
        $origin = $request->headers->get('Origin');
        $allowOrigin = $origin ?: '*';

        return $response
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Vary', 'Origin')
            ->header('Access-Control-Allow-Credentials', $origin ? 'true' : 'false')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Accept, Origin')
            ->header('Access-Control-Max-Age', '86400');
    }
}
