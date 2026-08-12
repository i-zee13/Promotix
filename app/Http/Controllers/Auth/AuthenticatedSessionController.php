<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginHistoryLogger;
use App\Support\UserTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if (in_array((string) ($user->status ?? 'active'), ['suspended', 'banned'], true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This account is not active.']);
        }

        if ($user->hasTwoFactorEnabled()) {
            Auth::guard('web')->logout();
            $request->session()->put('login.two_factor.id', $user->id);
            $request->session()->put('login.two_factor.remember', $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

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

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
