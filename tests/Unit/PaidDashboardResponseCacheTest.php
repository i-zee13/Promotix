<?php

namespace Tests\Unit;

use App\Support\PaidMarketing\DashboardResponseCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaidDashboardResponseCacheTest extends TestCase
{
    public function test_filter_fingerprint_changes_with_filters(): void
    {
        $cache = new DashboardResponseCache;

        $a = Request::create('/paid-marketing/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-07',
            'domain_id' => '1',
        ]);
        $b = Request::create('/paid-marketing/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-07',
            'domain_id' => '2',
        ]);

        $this->assertNotSame($cache->filterFingerprint($a), $cache->filterFingerprint($b));
    }

    public function test_remember_uses_version_in_key_and_refreshes_on_new_version(): void
    {
        Cache::flush();
        $cache = new DashboardResponseCache;
        $request = Request::create('/paid-marketing/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-07',
        ]);

        $calls = 0;
        $builder = function () use (&$calls) {
            $calls++;

            return ['paid_visits' => $calls];
        };

        $first = $cache->remember($request, 'summary', 'v1', $builder);
        $second = $cache->remember($request, 'summary', 'v1', $builder);
        $this->assertSame(1, $calls);
        $this->assertSame(['paid_visits' => 1], $first);
        $this->assertSame(['paid_visits' => 1], $second);
        $this->assertSame('HIT', $cache->lastStatus());

        $third = $cache->remember($request, 'summary', 'v2-new-ip', $builder);
        $this->assertSame(2, $calls);
        $this->assertSame(['paid_visits' => 2], $third);
        $this->assertSame('MISS', $cache->lastStatus());
    }

    public function test_bypass_skips_cache(): void
    {
        Cache::flush();
        $cache = new DashboardResponseCache;
        $request = Request::create('/paid-marketing/summary', 'GET');

        $calls = 0;
        $builder = function () use (&$calls) {
            $calls++;

            return ['n' => $calls];
        };

        $cache->remember($request, 'summary', 'v1', $builder, true);
        $cache->remember($request, 'summary', 'v1', $builder, true);
        $this->assertSame(2, $calls);
        $this->assertSame('BYPASS', $cache->lastStatus());
    }
}
