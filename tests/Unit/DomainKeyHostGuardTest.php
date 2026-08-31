<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Support\DomainKeyHostGuard;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class DomainKeyHostGuardTest extends TestCase
{
    public function test_www_variants_match(): void
    {
        $this->assertTrue(DomainKeyHostGuard::hostsMatch('www.example.com', 'example.com'));
        $this->assertTrue(DomainKeyHostGuard::hostsMatch('example.com', 'www.example.com'));
        $this->assertTrue(DomainKeyHostGuard::hostsMatch('Example.COM', 'example.com'));
    }

    public function test_different_domains_do_not_match(): void
    {
        $this->assertFalse(DomainKeyHostGuard::hostsMatch('other.com', 'example.com'));
        $this->assertFalse(DomainKeyHostGuard::hostsMatch('evil-example.com', 'example.com'));
        $this->assertFalse(DomainKeyHostGuard::hostsMatch('sub.example.com', 'example.com'));
    }

    public function test_origin_mismatch_is_rejected(): void
    {
        $domain = new Domain(['hostname' => 'example.com']);
        $request = Request::create('https://track.test/ingest/visit', 'POST', [
            'url' => 'https://example.com/',
        ]);
        $request->headers->set('Origin', 'https://other.com');

        $reason = DomainKeyHostGuard::mismatchReason($request, $domain);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('other.com', $reason);
    }

    public function test_matching_origin_is_allowed(): void
    {
        $domain = new Domain(['hostname' => 'example.com']);
        $request = Request::create('https://track.test/ingest/visit', 'POST', [
            'url' => 'https://other.com/', // spoofed payload ignored when Origin matches
        ]);
        $request->headers->set('Origin', 'https://www.example.com');

        $this->assertNull(DomainKeyHostGuard::mismatchReason($request, $domain));
    }

    public function test_missing_host_signals_are_rejected(): void
    {
        $domain = new Domain(['hostname' => 'example.com']);
        $request = Request::create('https://track.test/ingest/visit', 'POST', []);

        $reason = DomainKeyHostGuard::mismatchReason($request, $domain);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('Origin', $reason);
    }
}
