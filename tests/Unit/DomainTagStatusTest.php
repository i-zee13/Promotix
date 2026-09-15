<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Support\DomainTagStatus;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DomainTagStatusTest extends TestCase
{
    public function test_not_detected_when_tag_not_connected(): void
    {
        $domain = new Domain();
        $domain->forceFill([
            'tag_connected' => false,
            'last_seen_at' => null,
        ]);

        $status = DomainTagStatus::forDomain($domain);
        $this->assertFalse($status['installed']);
        $this->assertSame('Not detected', $status['label']);
        $this->assertSame('—', $status['last_seen_human']);
    }

    public function test_installed_with_last_seen(): void
    {
        $status = DomainTagStatus::describe(
            installed: true,
            lastSeenAt: Carbon::now()->subMinutes(5),
        );
        $this->assertTrue($status['installed']);
        $this->assertSame('Installed', $status['label']);
        $this->assertNotSame('—', $status['last_seen_human']);
    }

    public function test_gtm_channel_stays_not_detected_for_direct_install(): void
    {
        $domain = new Domain();
        $domain->forceFill([
            'tag_connected' => true,
            'tag_install_method' => 'manual',
        ]);
        $domain->syncOriginal();

        $gtm = DomainTagStatus::forDomain($domain, 'gtm');
        $this->assertFalse($gtm['installed']);
        $this->assertSame('Not detected', $gtm['label']);

        $any = DomainTagStatus::forDomain($domain);
        $this->assertTrue($any['installed']);
        $this->assertSame('Installed', $any['label']);
    }

    public function test_gtm_channel_installed_only_when_method_is_gtm(): void
    {
        $domain = new Domain();
        $domain->forceFill([
            'tag_connected' => true,
            'tag_install_method' => 'gtm',
        ]);
        $domain->syncOriginal();

        $gtm = DomainTagStatus::forDomain($domain, 'gtm');
        $this->assertTrue($gtm['installed']);
        $this->assertSame('Installed', $gtm['label']);
    }
}
