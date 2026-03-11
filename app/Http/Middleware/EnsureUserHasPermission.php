<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request. Check that the user has permission to access the current route.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $routeName = $request->route()?->getName();
        if (! $routeName) {
            return $next($request);
        }

        $routePermission = config('admin.route_permission', []);
        $permissionSlug = $routePermission[$routeName] ?? null;

        if ($permissionSlug === null) {
            return $next($request);
        }

        if (! $user->canAccess($permissionSlug)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
