<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate web request timezone middleware (the bug trigger).
App\Support\UserTimezone::applyForUser(
    App\Models\Domain::query()->where('hostname', 'like', '%fibreopticnet%')->first()?->user
);

$domain = App\Models\Domain::query()->where('hostname', 'like', '%fibreopticnet%')->first();
$user = $domain->user;
$request = Illuminate\Http\Request::create('/paid-marketing/detailed-visits', 'GET', [
    'domain_id' => $domain->id,
    'from' => '2026-07-01',
    'to' => '2026-07-01',
]);
$request->setUserResolver(fn () => $user);

$controller = app(App\Http\Controllers\Admin\PaidMarketingController::class);
$response = $controller->detailedVisits($request);
$data = $response->getData(true);

echo 'Rows: ' . count($data['rows']) . "\n";
foreach ($data['rows'] as $row) {
    echo "  {$row['ip']} | last_click: {$row['last_click_label']}\n";
}
