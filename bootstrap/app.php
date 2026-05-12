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
        ]);

        // Onboarding gate: forces unverified / no-plan users through the funnel.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureOnboardingComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
