<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectSuperAdminFromLegacyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->is_super_admin ?? false)) {
            $routeName = $request->route()?->getName();
            $target = config("super-admin.legacy_route_redirects.{$routeName}");

            if ($target) {
                return redirect()->route($target, $request->query());
            }
        }

        return $next($request);
    }
}
