<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            'is_admin' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Issue a 6-digit OTP and route the user to the verification screen.
        $devCode = $this->issueVerificationCode($user);

        $redirect = redirect()->route('verification.notice');

        if ($devCode !== null) {
            $redirect->with('dev_code', $devCode);
        }

        return $redirect;
    }

    /**
     * Generate and email a 6-digit verification code.
     * Returns the plain code only when mail is not configured (dev mode), otherwise null.
     */
    private function issueVerificationCode(User $user): ?string
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

        try {
            Mail::raw(
                "Hi {$user->name},\n\nYour Promotix email verification code is: {$code}\n\nThis code expires in 60 minutes.\n\n— Promotix",
                function ($message) use ($user) {
                    $message->to($user->email)->subject('Your Promotix verification code');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Verification email failed at signup', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        $mailer = config('mail.default', 'log');
        $mailConfigured = ! in_array($mailer, ['log', 'array', 'null'], true);

        return $mailConfigured ? null : $code;
    }

}
