<?php

namespace App\Http\Controllers;

use App\Models\IpLog;
use App\Jobs\EnrichIpIntelJob;
use Illuminate\Http\Request;

class IpFilterController extends Controller
{
    /**
     * Handle IP check and logging.
     *
     * This endpoint is called from the small script you embed in any website.
     * It logs the visitor IP and tells the script whether this IP is allowed.
     */
    public function check(Request $request)
    {
        // Handle CORS preflight
        if ($request->isMethod('options')) {
            return $this->cors($request, response()->noContent());
        }

        $ip = $this->clientIp($request);
        $userAgent = $request->userAgent() ?? '';

        $log = IpLog::firstOrNew(['ip' => $ip]);

        if (! $log->exists) {
            $log->hits = 0;
        }

        $log->hits = ($log->hits ?? 0) + 1;
        $log->user_agent = $userAgent;
        $log->last_seen_at = now();
        $log->last_path = $request->input('path');
        $log->last_referrer = $request->input('referrer');
        $log->save();

        EnrichIpIntelJob::dispatch($log->id);

        // For now, "fake" detection = IPs you manually mark as blocked in the database.
        // If is_blocked is true, the script should bounce this visitor.
        $allowed = ! $log->is_blocked;

        return $this->cors(
            $request,
            response()->json([
                'allowed' => $allowed,
            ])
        );
    }

    private function clientIp(Request $request): string
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

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function isLoopbackIp(string $ip): bool
    {
        return $ip === '127.0.0.1' || $ip === '::1';
    }

    /**
     * Attach permissive CORS headers so this can be called from any domain.
     */
    protected function cors(Request $request, $response)
    {
        $origin = $request->headers->get('Origin');
        $allowOrigin = $origin ?: '*';

        return $response
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Vary', 'Origin')
            ->header('Access-Control-Allow-Credentials', $origin ? 'true' : 'false')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Accept, Origin')
            ->header('Access-Control-Max-Age', '86400');
    }
}

