<?php

namespace App\Http\Middleware;

use App\Services\BillingAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProtectionAccess
{
    private array $alwaysAllowed = [
        'dashboard',
        'billing.index',
        'billing.submit',
        'billing.payment-methods.store',
        'billing.payment-methods.destroy',
        'upgrade-plan',
        'upgrade-plan.submit',
        'support-system',
        'support-system.create',
        'support-system.store',
        'support-system.show',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'password.update',
        'impersonate.stop',
        'onboarding.plan',
        'onboarding.start-trial',
        'verification.notice',
        'verification.verify',
        'verification.verify-code',
        'verification.send',
        'verification.send-code',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        BillingAccess::applyGraceExpiryIfNeeded($user);

        $routeName = optional($request->route())->getName();
        if ($routeName && in_array($routeName, $this->alwaysAllowed, true)) {
            return $next($request);
        }

        if (BillingAccess::hasProtectionAccess($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your subscription payment is past due. Tracking and protection are paused until you pay.',
            ], 402);
        }

        return redirect()
            ->route('billing.index')
            ->with('billing_alert', 'Your subscription payment is past due. Tracking and protection are paused.');
    }
}
