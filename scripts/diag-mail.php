<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$summary = \App\Services\Mail\SmtpConfigResolver::diagnosticSummary();

foreach ($summary as $key => $value) {
    echo $key.'='.var_export($value, true).PHP_EOL;
}

$to = $argv[1] ?? config('mail.from.address');
if (! is_string($to) || trim($to) === '') {
    echo "SEND_RESULT=SKIP (pass recipient as first arg)\n";
    exit(1);
}

if ($summary['readiness_error'] ?? null) {
    echo 'SEND_RESULT=SKIP ('.$summary['readiness_error'].")\n";
    exit(1);
}

try {
    $ok = App\Services\Mail\AppMailer::sendRaw(
        $to,
        config('app.name', 'Promotix').' SMTP diagnostic',
        'Promotix SMTP diagnostic at '.now()->toDateTimeString(),
        'diag-mail'
    );
    if ($ok) {
        echo "SEND_RESULT=OK\n";
        exit(0);
    }

    echo 'SEND_RESULT=FAIL '.(App\Services\Mail\AppMailer::lastError() ?: '(see laravel.log)')."\n";
    exit(1);
} catch (Throwable $e) {
    echo "SEND_RESULT=EXCEPTION\n";
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
