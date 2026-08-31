<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\App\Services\Mail\SmtpConfigResolver::apply(true);

echo 'mailer=' . config('mail.default') . PHP_EOL;
echo 'scheme=' . var_export(config('mail.mailers.smtp.scheme'), true) . PHP_EOL;
echo 'user_len=' . strlen((string) config('mail.mailers.smtp.username')) . PHP_EOL;
echo 'pass_len=' . strlen((string) config('mail.mailers.smtp.password')) . PHP_EOL;

$to = config('mail.from.address');
try {
    $ok = App\Services\Mail\AppMailer::sendRaw(
        $to,
        'Promotix SMTP diagnostic',
        'Promotix SMTP diagnostic at ' . now()->toDateTimeString(),
        'diag-mail'
    );
    echo $ok ? "SEND_RESULT=OK\n" : "SEND_RESULT=FAIL (see laravel.log)\n";
} catch (Throwable $e) {
    echo "SEND_RESULT=EXCEPTION\n";
    echo $e->getMessage() . PHP_EOL;
}
