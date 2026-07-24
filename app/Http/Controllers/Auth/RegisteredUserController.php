<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\LoginHistoryLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name' => ['nullable', 'string', 'max:160'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ]);

        $defaultRole = Role::where('slug', 'default-user')->first();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company_name' => $data['company_name'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'password' => Hash::make($data['password']),
            'role_id' => $defaultRole?->id,
            // Customers must complete trial + payment onboarding (admins bypass those gates).
            'is_admin' => false,
        ]);

        event(new Registered($user));

        Auth::login($user);
        LoginHistoryLogger::record($user, $request, 'register');

        // Issue OTP first, then redirect with the same status UX as Resend.
        [$devCode, $sent, $mailConfigured] = $this->issueVerificationCode($user);

        $redirect = redirect()->route('verification.notice');

        if ($devCode !== null) {
            $redirect->with('dev_code', $devCode);
        }

        if ($sent) {
            $redirect->with('status', "We've sent a 6-digit verification code to {$user->email}.");
        } elseif (! $mailConfigured) {
            $redirect->with('status', 'Mail is not configured — use the dev code below (or configure SMTP).');
        } else {
            $redirect->withErrors([
                'email' => 'We could not send the verification email. Tap Resend code, or check SMTP settings.',
            ]);
        }

        return $redirect;
    }

    /**
     * @return array{0: ?string, 1: bool, 2: bool} [devCode, sent, mailConfigured]
     */
    private function issueVerificationCode(User $user): array
    {
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

        $mailConfigured = \App\Services\Auth\VerificationCodeMailer::mailIsConfigured();
        $sent = false;

        try {
            $sent = \App\Services\Auth\VerificationCodeMailer::send($user->name, $user->email, $code);
        } catch (\Throwable $e) {
            Log::warning('Signup OTP send failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        // Always expose the code when mail is not configured, or when send failed in debug.
        $devCode = null;
        if (! $mailConfigured || (! $sent && config('app.debug'))) {
            $devCode = $code;
        }

        return [$devCode, $sent, $mailConfigured];
    }
}
