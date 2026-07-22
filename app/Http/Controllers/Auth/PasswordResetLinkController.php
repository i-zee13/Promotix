<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mail\AppMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($request->input('email'));
        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        $user = User::query()->where('email', $email)->first();
        $sent = false;

        if ($user) {
            $sent = AppMailer::sendTemplate('password_reset_email', $user->email, [
                '{{user_name}}' => $user->name ?: 'there',
                '{{otp_code}}' => $code,
                '{{reset_expiry}}' => '60',
            ]);
        }

        $request->session()->put('password.reset.email', $email);

        $next = redirect()->route('password.code')
            ->with('status', "We've sent a 6-digit code to {$email}.");

        if (! AppMailer::mailIsConfigured() || ! $sent) {
            $next = $next->with('dev_code', $code);
            if (AppMailer::mailIsConfigured() && $user && ! $sent) {
                $next = $next->withErrors(['email' => 'We could not send the reset email. Check SMTP settings, or use the code shown below.']);
            }
        }

        return $next;
    }
}
