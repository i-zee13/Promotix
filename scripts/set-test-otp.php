<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$code = '123456';
Illuminate\Support\Facades\DB::table('email_verification_codes')
    ->where('email', 'trial.user.0723@example.com')
    ->update([
        'code_hash' => Illuminate\Support\Facades\Hash::make($code),
        'attempts' => 0,
        'expires_at' => now()->addHour(),
    ]);

$user = App\Models\User::where('email', 'trial.user.0723@example.com')->first();
echo json_encode([
    'otp' => $code,
    'user' => $user ? [
        'id' => $user->id,
        'is_admin' => (bool) $user->is_admin,
        'verified' => (bool) $user->email_verified_at,
        'home' => $user->homeRouteName(),
    ] : null,
], JSON_PRETTY_PRINT);
