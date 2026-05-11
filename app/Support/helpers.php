<?php

use App\Models\AppSetting;

if (! function_exists('app_setting')) {
    /**
     * Read a configuration value from the app_settings table (cached).
     */
    function app_setting(string $key, mixed $default = null): mixed
    {
        return AppSetting::value($key, $default);
    }
}

if (! function_exists('format_money_cents')) {
    function format_money_cents(int $cents, string $currency = 'USD'): string
    {
        return strtoupper($currency) . ' ' . number_format($cents / 100, 2);
    }
}
