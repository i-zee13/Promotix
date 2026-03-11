<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    /**
     * Allow access if user is super admin (is_admin) or has a role with permissions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();
        if ($user->is_admin) {
            return $next($request);
        }

        if (! $user->role_id) {
            abort(403, 'You do not have access to the admin area.');
        }

        $user->loadMissing('role.permissions');
        if ($user->role->permissions->isEmpty()) {
            abort(403, 'Your role has no permissions assigned.');
        }

        return $next($request);
    }
}
