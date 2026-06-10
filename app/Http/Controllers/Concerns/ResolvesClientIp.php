<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesClientIp
{
    protected function clientIp(Request $request): string
    {
        $candidates = [
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('True-Client-IP'),
            $request->headers->get('X-Real-IP'),
            $request->headers->get('X-Forwarded-For'),
            $request->headers->get('X-Cluster-Client-IP'),
        ];

        $ips = [];
        foreach ($candidates as $value) {
            if (! $value) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', $value) as $ip) {
                $ip = trim($ip);
                if ($ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        foreach ($ips as $ip) {
            if ($this->isValidIp($ip) && ! $this->isLoopbackIp($ip)) {
                return $ip;
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
