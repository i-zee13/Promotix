<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginHistoryLogger;
use App\Support\TotpAuthenticator;
use App\Support\UserTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.two_factor.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $userId = (int) $request->session()->get('login.two_factor.id');
        if ($userId < 1) {
            return redirect()->route('login');
        }

        $throttleKey = 'two-factor:'.$userId.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Try again in a minute.',
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['login.two_factor.id', 'login.two_factor.remember']);

            return redirect()->route('login');
        }

        $code = trim((string) $request->input('code'));
        $ok = TotpAuthenticator::verify((string) $user->two_factor_secret, $code);

        if (! $ok) {
            $ok = $this->consumeRecoveryCode($user, $code);
        }

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'code' => 'Invalid authentication code.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull('login.two_factor.remember', false);
        $request->session()->forget('login.two_factor.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('auth.two_factor_passed', true);

        $user->forceFill(['last_login_at' => now()])->save();
        LoginHistoryLogger::record($user, $request);
        UserTimezone::captureForUser($user, $request);

        if ($user->is_super_admin ?? false) {
            return redirect()->intended(route('super-admin.dashboard', [], false));
        }

        return redirect()->intended(route($user->homeRouteName(), [], false));
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = collect((array) ($user->two_factor_recovery_codes ?? []))
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter()
            ->values();

        $needle = strtoupper(trim($code));
        $idx = $codes->search(fn ($c) => hash_equals($c, $needle));
        if ($idx === false) {
            return false;
        }

        $codes->forget($idx);
        $user->forceFill([
            'two_factor_recovery_codes' => $codes->values()->all(),
        ])->save();

        return true;
    }
}
