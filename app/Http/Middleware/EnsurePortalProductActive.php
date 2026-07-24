<?php

namespace App\Http\Middleware;

use App\Models\SaasProduct;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalProductActive
{
    /** @var list<string> */
    private array $skipRouteNames = [
        'portal.inactive',
        'logout',
        'impersonate.stop',
        'login',
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
        'onboarding.payment',
        'onboarding.payment.store',
        'onboarding.payment.stripe-confirm',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'profile.timezone.sync',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = optional($request->route())->getName();
        if ($routeName && in_array($routeName, $this->skipRouteNames, true)) {
            return $next($request);
        }

        if (str_starts_with((string) $routeName, 'super-admin.')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && ($user->is_super_admin ?? false)) {
            return $next($request);
        }

        $product = SaasProduct::portalProduct();
        if (! $product || $product->is_active) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "{$product->name} is currently inactive. Access has been paused by the administrator.",
                'product' => $product->name,
            ], 503);
        }

        return redirect()->route('portal.inactive');
    }
}
