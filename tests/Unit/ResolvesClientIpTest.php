<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\ResolvesClientIp;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ResolvesClientIpTest extends TestCase
{
    public function test_prefers_cloudflare_over_spoofed_forwarded_for(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_CF_CONNECTING_IP' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1, 203.0.113.10',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $this->assertSame('203.0.113.10', (new class {
            use ResolvesClientIp;

            public function resolve(Request $request): string
            {
                return $this->clientIp($request);
            }
        })->resolve($request));
    }

    public function test_skips_private_spoofed_xff_hops(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => '10.0.0.8, 203.0.113.44',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $this->assertSame('203.0.113.44', (new class {
            use ResolvesClientIp;

            public function resolve(Request $request): string
            {
                return $this->clientIp($request);
            }
        })->resolve($request));
    }
}
