<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\PaidAdvertisingDashboardController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class AdvancedViewIpSearchFilterTest extends TestCase
{
    public function test_advanced_search_filter_applies_ip_like_on_visits_query(): void
    {
        $controller = app(PaidAdvertisingDashboardController::class);
        $method = new ReflectionMethod($controller, 'applyAdvancedSearchFilter');
        $method->setAccessible(true);

        $request = Request::create('/paid-marketing/detailed-visits', 'GET', [
            'ip' => '72.159.77.95',
        ]);

        $query = \Illuminate\Support\Facades\DB::table('visits');
        $method->invoke($controller, $query, $request, 'visits');

        $sql = strtolower($query->toSql());
        $bindings = $query->getBindings();

        $this->assertStringContainsString('ip', $sql);
        $this->assertStringContainsString('like', $sql);
        $this->assertTrue(
            collect($bindings)->contains(fn ($b) => is_string($b) && str_contains($b, '72.159.77.95')),
            'IP search term should be bound into the inventory query.'
        );
    }

    public function test_empty_search_does_not_add_ip_constraint(): void
    {
        $controller = app(PaidAdvertisingDashboardController::class);
        $method = new ReflectionMethod($controller, 'applyAdvancedSearchFilter');
        $method->setAccessible(true);

        $request = Request::create('/paid-marketing/detailed-visits', 'GET', [
            'ip' => '',
        ]);

        $query = \Illuminate\Support\Facades\DB::table('visits');
        $before = $query->toSql();
        $method->invoke($controller, $query, $request, 'visits');

        $this->assertSame($before, $query->toSql());
    }

    public function test_device_id_search_does_not_use_ip_like(): void
    {
        \Illuminate\Support\Facades\Schema::shouldReceive('hasColumn')
            ->andReturnUsing(fn (string $table, string $column): bool => $table === 'visits' && $column === 'device_id');

        $controller = app(PaidAdvertisingDashboardController::class);
        $method = new ReflectionMethod($controller, 'applyAdvancedSearchFilter');
        $method->setAccessible(true);

        $request = Request::create('/paid-marketing/detailed-visits', 'GET', [
            'ip' => 'DEV_6F831D99B9B3',
        ]);

        $query = \Illuminate\Support\Facades\DB::table('visits');
        $method->invoke($controller, $query, $request, 'visits');

        $sql = strtolower($query->toSql());
        $bindings = $query->getBindings();

        $this->assertStringContainsString('device_id', $sql);
        $this->assertTrue(
            collect($bindings)->contains(fn ($b) => is_string($b) && str_contains(strtoupper($b), 'DEV_6F831D99B9B3')),
            'Device ID search should bind the DEV_ term.'
        );
        $this->assertFalse(
            collect($bindings)->contains(fn ($b) => is_string($b) && str_starts_with($b, '%DEV_')),
            'Device ID search must not use IP-style %DEV_% like matching.'
        );
    }
}
