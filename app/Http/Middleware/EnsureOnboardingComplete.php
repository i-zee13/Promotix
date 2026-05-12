<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onboarding gate (runs after `auth`).
 *
 *   - Not email-verified  → /verify-email
 *   - Verified but no active/trialing subscription → /onboarding/plan
 *   - Super admins bypass both checks.
 *
 * Routes that should be reachable while onboarding is incomplete (login, logout,
 * verification screens, plan selection, etc.) are listed in the `skipRouteNames` array.
 */
class EnsureOnboardingComplete
{
    private array $skipRouteNames = [
        'login',
        'logout',
        'register',
        'password.request',
        'password.email',
        'password.code',
        'password.code.verify',
        'password.reset',
        'password.store',
        'verification.notice',
        'verification.verify',
        'verification.verify-code',
        'verification.send',
        'verification.send-code',
        'onboarding.plan',
        'onboarding.start-trial',
        'impersonate.stop',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->is_super_admin ?? false) {
            return $next($request);
        }

        $routeName = optional($request->route())->getName();

        if ($routeName && in_array($routeName, $this->skipRouteNames, true)) {
            return $next($request);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->activeSubscription()) {
            return redirect()->route('onboarding.plan');
        }

        return $next($request);
    }
}
