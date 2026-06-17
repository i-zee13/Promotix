<?php

namespace App\Http\Middleware;

use App\Support\UserTimezone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        UserTimezone::applyForUser($request->user());

        return $next($request);
    }
}
