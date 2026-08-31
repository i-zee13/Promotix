<?php

namespace App\Providers;

use App\Services\Mail\SmtpConfigResolver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $helpers = app_path('Support/helpers.php');
        if (is_file($helpers)) {
            require_once $helpers;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        try {
            SmtpConfigResolver::apply();
        } catch (\Throwable) {
            // Never block boot when mail integration tables are unavailable.
        }
    }
}
