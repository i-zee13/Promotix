<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginHistoryLogger
{
    public static function record(User $user, Request $request, string $event = 'login', string $status = 'success'): void
    {
        try {
            $agent = (string) $request->userAgent();
            $browser = 'Unknown';
            if (str_contains($agent, 'Edg/') || str_contains($agent, 'Edge')) {
                $browser = 'Edge';
            } elseif (str_contains($agent, 'Chrome')) {
                $browser = 'Chrome';
            } elseif (str_contains($agent, 'Firefox')) {
                $browser = 'Firefox';
            } elseif (str_contains($agent, 'Safari')) {
                $browser = 'Safari';
            }

            $device = preg_match('/Mobile|Android|iPhone|iPad/i', $agent) ? 'Mobile' : 'Desktop';

            LoginHistory::query()->create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr($agent, 0, 512),
                'device' => $device,
                'browser' => $browser,
                'location' => null,
                'status' => $status,
                'event' => $event,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Login history record failed', [
                'user_id' => $user->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ensure the current session appears at least once (register/OTP users never hit /login).
     */
    public static function ensureCurrentSession(User $user, Request $request, string $event = 'session'): void
    {
        $exists = LoginHistory::query()->where('user_id', $user->id)->exists();
        if ($exists) {
            return;
        }

        self::record($user, $request, $event);
    }
}
