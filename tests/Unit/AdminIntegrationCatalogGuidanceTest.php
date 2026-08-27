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
}
