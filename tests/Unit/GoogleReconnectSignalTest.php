<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\PaidAdvertisingDashboardController;
use ReflectionMethod;
use Tests\TestCase;

class GoogleReconnectSignalTest extends TestCase
{
    public function test_only_oauth_failures_request_reconnect(): void
    {
        $controller = app(PaidAdvertisingDashboardController::class);
        $method = new ReflectionMethod($controller, 'googleSyncLooksAuthRelated');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, 'UNAUTHENTICATED: OAuth refresh token expired'));
        $this->assertTrue($method->invoke($controller, 'invalid_grant'));

        $this->assertFalse($method->invoke($controller, 'Google returned no campaign metrics'));
        $this->assertFalse($method->invoke($controller, '403 permission denied for customer account'));
        $this->assertFalse($method->invoke($controller, 'login-customer-id configuration mismatch'));
    }
}
