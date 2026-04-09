<?php

namespace App\Http\Controllers;

use App\Models\IpLog;
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

        $ip = $request->ip();
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

