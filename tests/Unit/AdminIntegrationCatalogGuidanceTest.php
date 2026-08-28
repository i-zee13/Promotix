<?php

namespace Tests\Unit;

use App\Support\AdminIntegrationCatalog;
use PHPUnit\Framework\TestCase;

class AdminIntegrationCatalogGuidanceTest extends TestCase
{
    public function test_guidance_chatbot_is_in_catalog_meta(): void
    {
        $meta = AdminIntegrationCatalog::cardMeta('guidance-chatbot');
        $this->assertSame('Synced', $meta['connected_label']);
        $this->assertStringContainsString('Guidance', $meta['subtitle']);
        $this->assertSame('C', $meta['icon']);
    }

    public function test_cross_domain_is_in_catalog_meta(): void
    {
        $meta = AdminIntegrationCatalog::cardMeta('cross-domain');
        $this->assertStringContainsString('domains', $meta['subtitle']);
        $this->assertSame('Enabled for tenants', $meta['connected_label']);
    }
}
