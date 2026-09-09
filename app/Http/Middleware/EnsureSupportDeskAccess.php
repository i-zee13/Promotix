<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super admins and staff assigned to an admin-panel team/department
 * may access the Support System (ticket balance / assignment board).
 */
class EnsureSupportDeskAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->canAccessSupportDesk(), 403);
        abort_if(in_array((string) ($user->status ?? 'active'), ['suspended', 'banned'], true), 403, 'Your account is not active.');

        return $next($request);
    }
}
