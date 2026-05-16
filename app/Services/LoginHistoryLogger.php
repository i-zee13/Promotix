<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;

class LoginHistoryLogger
{
    public static function record(User $user, Request $request, string $event = 'login', string $status = 'success'): void
    {
        $agent = (string) $request->userAgent();
        $browser = 'Unknown';
        if (str_contains($agent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($agent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($agent, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($agent, 'Edge')) {
            $browser = 'Edge';
        }

        $device = str_contains($agent, 'Mobile') ? 'Mobile' : 'Desktop';

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
    }
}
