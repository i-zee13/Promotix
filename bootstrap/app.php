<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Hostinger / reverse proxies send X-Forwarded-For; trust them so $request->ip() and fallbacks see the real visitor.
        $middleware->trustProxies(at: '*');

        // Exclude the public tracking endpoint from CSRF validation so it can be called from any site.
        $middleware->validateCsrfTokens(except: [
            '/ip-check',
            '/t/collect',
            '/ingest/visit',
            '/api/admin/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'onboarded' => \App\Http\Middleware\EnsureOnboardingComplete::class,
            'protection' => \App\Http\Middleware\EnsureProtectionAccess::class,
        ]);

        // Onboarding gate: forces unverified / no-plan users through the funnel.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureOnboardingComplete::class,
            \App\Http\Middleware\EnsureProtectionAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
