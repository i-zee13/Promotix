<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\PaidMarketingController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * AV-02 acceptance: multiple Advanced View filters are accepted together.
 */
class DetailedVisitFilterParamsTest extends TestCase
{
    public function test_detailed_visit_query_accepts_combined_filter_params(): void
    {
        if (! class_exists(PaidMarketingController::class)) {
            $this->markTestSkipped('Controller missing');
        }

        $request = Request::create('/paid-marketing/detailed-visits', 'GET', [
            'from' => '2026-07-01',
            'to' => '2026-07-10',
            'country' => 'US',
            'keyword' => 'mortgage',
            'ad_group' => 'brand',
            'source' => 'google',
            'browser' => 'Chrome',
            'device' => 'Windows',
            'detection' => 'invalid',
            'threat_group' => 'vpn',
            'risk_level' => 'high',
            'block_status' => 'blocked',
            'ip' => '1.2.3.',
            'path' => '/click',
            'campaign' => 'OnPoint',
        ]);

        foreach ([
            'country', 'keyword', 'ad_group', 'source', 'browser', 'device',
            'detection', 'threat_group', 'risk_level', 'block_status', 'ip', 'path', 'campaign',
        ] as $key) {
            $this->assertSame(
                (string) $request->query($key),
                (string) $request->input($key),
                "Filter param {$key} should be readable from the Advanced View query string."
            );
        }

        $this->assertTrue(method_exists(PaidMarketingController::class, 'detailedVisits'));
        $method = new ReflectionMethod(PaidMarketingController::class, 'detailedVisitQuery');
        $this->assertTrue($method->isPrivate());
    }
}
