<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$primary = app_setting('branding.color_primary', '#6400B2');
echo "current primary={$primary}\n";

// Temporary lime to verify card remap (same vibe as user's screenshot).
App\Models\AppSetting::set('branding.color_primary', '#A8E010');
App\Models\AppSetting::set('branding.color_secondary', '#7AAB00');
App\Models\AppSetting::flushCache();

echo 'updated primary='.app_setting('branding.color_primary').PHP_EOL;
