<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\VerificationCodeMailer;
use App\Services\LoginHistoryLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        Auth::login($user);

        // Send OTP in this same request — before redirect — so the inbox gets it immediately.
        [$devCode, $sent, $mailConfigured] = VerificationCodeMailer::issueAndSend($user);

        LoginHistoryLogger::record($user, $request, 'register');
        event(new Registered($user));

        $redirect = redirect()->route('verification.notice');

        if ($devCode !== null) {
            $redirect->with('dev_code', $devCode);
        }

        if ($sent) {
            $redirect->with('status', "We've sent a 6-digit verification code to {$user->email}.");
        } elseif (! $mailConfigured) {
            $redirect->with('status', 'Mail is not configured — use the dev code below (or configure SMTP).');
        } else {
            $redirect
                ->with('otp_send_failed', true)
                ->withErrors([
                    'email' => 'We could not send the verification email. We will retry automatically — or tap Resend code.',
                ]);
        }

        return $redirect;
    }
}
