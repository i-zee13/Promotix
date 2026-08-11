<?php

namespace Tests\Unit;

use App\Support\PaidAdvertising\PaidIdentityResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaidIdentityResolverTest extends TestCase
{
    public function test_resolve_without_tables_returns_stable_ids_and_confidence_band(): void
    {
        $request = Request::create('/t/collect', 'POST', [], [
            PaidIdentityResolver::COOKIE_VISITOR => 'VISITORTEST12AB',
            PaidIdentityResolver::COOKIE_BROWSER => 'BROWSERTEST34CD',
        ]);
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/537.36');

        $identity = (new PaidIdentityResolver)->resolve(
            $request,
            domainId: 1,
            ip: '203.0.113.10',
            sessionId: 'sess_1',
            clientFingerprint: 'canvas-abc',
        );

        $this->assertSame('VISITORTEST12AB', $identity->visitorId);
        $this->assertSame('BROWSERTEST34CD', $identity->browserId);
        $this->assertNotEmpty($identity->deviceId);
        $this->assertNotEmpty($identity->fingerprintId);
        $this->assertSame('very_high', $identity->confidenceBand);
        $this->assertGreaterThanOrEqual(0.95, $identity->confidence);
        $this->assertStringStartsWith('PID_', $identity->publicId);
    }

    public function test_ip_alone_does_not_create_very_high_confidence(): void
    {
        $request = Request::create('/t/collect', 'POST');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $identity = (new PaidIdentityResolver)->resolve($request, 1, '203.0.113.11');

        $this->assertLessThan(0.95, $identity->confidence);
        $this->assertNotSame('very_high', $identity->confidenceBand);
    }
}
