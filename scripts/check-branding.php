<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'primary='.app_setting('branding.color_primary', '#6400B2').PHP_EOL;
echo 'secondary='.app_setting('branding.color_secondary', '#4D008E').PHP_EOL;
echo 'bg='.app_setting('branding.color_background', '#0d0d0d').PHP_EOL;
