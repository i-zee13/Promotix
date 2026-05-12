<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Email verification using a 6-digit OTP code (replaces the default Laravel link flow).
 *
 * Flow:
 *   - On signup (or "resend"), a 6-digit code is generated, hashed, and stored in
 *     `email_verification_codes`. Codes expire after 60 minutes and there are 6
 *     attempts per code before requiring a resend.
 *   - The verification screen accepts the code, marks the user's email as verified,
 *     fires the `Verified` event, and redirects to the next step (plan selection).
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
            return redirect()->route('onboarding.plan');
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
            return redirect()->route('onboarding.plan');
        }

        $code = (string) random_int(100000, 999999);

        DB::table('email_verification_codes')->updateOrInsert(
            ['email' => strtolower($user->email)],
            [
                'email' => strtolower($user->email),
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(60),
                'created_at' => now(),
            ]
        );

        $this->mailCode($user->name, $user->email, $code);

        $response = back()->with('status', 'A fresh 6-digit verification code has been sent to your email.');

        if (! $this->mailIsConfigured()) {
            $response->with('dev_code', $code);
        }

        return $response;
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
            return redirect()->route('onboarding.plan');
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

        if (! Hash::check($data['code'], $row->code_hash)) {
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

        return redirect()->route('onboarding.plan');
    }

    private function mailCode(string $name, string $email, string $code): void
    {
        try {
            Mail::raw(
                "Hi {$name},\n\nYour Promotix email verification code is: {$code}\n\nThis code expires in 60 minutes. If you did not request it, you can ignore this email.\n\n— Promotix",
                function ($message) use ($email) {
                    $message->to($email)->subject('Your Promotix verification code');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Email verification code email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mailIsConfigured(): bool
    {
        $mailer = config('mail.default', 'log');

        return ! in_array($mailer, ['log', 'array', 'null'], true);
    }
}
