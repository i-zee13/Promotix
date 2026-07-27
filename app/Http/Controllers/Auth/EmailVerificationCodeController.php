<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\VerificationCodeMailer;
use App\Services\LoginHistoryLogger;
use App\Services\Mail\AppMailer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Email verification using a 6-digit OTP code (replaces the default Laravel link flow).
 */
class EmailVerificationCodeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRouteName());
        }

        $email = strtolower((string) $user->email);

        // Signup may fail SMTP on the first request — retry once when landing here.
        if (session()->pull('otp_send_failed')) {
            [$devCode, $sent, $mailConfigured] = VerificationCodeMailer::issueAndSend($user);

            if ($devCode !== null) {
                session()->flash('dev_code', $devCode);
            }

            if ($sent) {
                session()->flash('status', "We've sent a 6-digit verification code to {$user->email}.");
            } elseif (! $mailConfigured) {
                session()->flash('status', 'Mail is not configured — use the dev code below.');
            }
        } elseif (! VerificationCodeMailer::hasActiveCode($email)) {
            [$devCode, $sent, $mailConfigured] = VerificationCodeMailer::issueAndSend($user);

            if ($devCode !== null) {
                session()->flash('dev_code', $devCode);
            }

            if ($sent) {
                session()->flash('status', "We've sent a 6-digit verification code to {$user->email}.");
            } elseif (! $mailConfigured) {
                session()->flash('status', 'Mail is not configured — use the dev code below.');
            }
        }

        return view('auth.verify-email', [
            'email' => $user->email,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRouteName());
        }

        [$devCode, $sent, $mailConfigured] = VerificationCodeMailer::issueAndSend($user);

        if (! $mailConfigured) {
            return back()
                ->with('status', 'Mail is not configured — use the dev code below.')
                ->with('dev_code', $devCode);
        }

        if (! $sent) {
            return back()
                ->withErrors(['email' => 'We could not send the verification email. Check mail settings or try again shortly.'])
                ->with('dev_code', $devCode);
        }

        return back()->with('status', 'A fresh 6-digit verification code has been sent to your email.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRouteName());
        }

        $row = DB::table('email_verification_codes')
            ->where('email', strtolower($user->email))
            ->first();

        if (! $row) {
            return back()->withErrors(['code' => 'No active code — please request a new one.']);
        }

        if (now()->greaterThan($row->expires_at)) {
            return back()->withErrors(['code' => 'That code has expired. Please resend a new code.']);
        }

        if ((int) $row->attempts >= 6) {
            return back()->withErrors(['code' => 'Too many attempts. Please resend a new code.']);
        }

        $hash = (string) $row->code_hash;
        $ok = hash_equals($hash, hash('sha256', $data['code']))
            || (str_starts_with($hash, '$2') && Hash::check($data['code'], $hash));

        if (! $ok) {
            DB::table('email_verification_codes')
                ->where('email', strtolower($user->email))
                ->update(['attempts' => $row->attempts + 1]);

            return back()->withErrors(['code' => 'That code is incorrect. Please try again.']);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        event(new Verified($user));

        DB::table('email_verification_codes')
            ->where('email', strtolower($user->email))
            ->delete();

        LoginHistoryLogger::ensureCurrentSession($user, $request, 'verified');

        AppMailer::sendTemplate('welcome_email', $user->email, [
            '{{user_name}}' => $user->name ?: 'there',
        ]);

        return redirect()->route($user->homeRouteName());
    }
}
