<?php

namespace Tests\Unit;

use App\Support\AdminIntegrationCatalog;
use PHPUnit\Framework\TestCase;

class AdminIntegrationCatalogGuidanceTest extends TestCase
{
    public function test_guidance_chatbot_is_in_catalog_meta(): void
    {
        $meta = AdminIntegrationCatalog::cardMeta('guidance-chatbot');
        $this->assertStringContainsString('Guidance', $meta['subtitle']);
        $this->assertSame('C', $meta['icon']);
    }

    public function test_cross_domain_is_in_catalog_meta(): void
    {
        $meta = AdminIntegrationCatalog::cardMeta('cross-domain');
        $this->assertStringContainsString('domains', $meta['subtitle']);
        $this->assertSame('Enabled for tenants', $meta['connected_label']);
    }

    public function test_pixel_and_placement_require_audience_exclusion_gate(): void
    {
        $this->assertFalse(AdminIntegrationCatalog::placementExclusionAvailableForUser(null));
        $this->assertFalse(AdminIntegrationCatalog::pixelGuardAvailableForUser(null));

        $source = file_get_contents((new \ReflectionClass(AdminIntegrationCatalog::class))->getFileName());
        $this->assertStringContainsString('audienceExclusionAvailableForUser($user)', $source);
        $this->assertStringContainsString('Placement + Pixel Guard ride with Audience Exclusion', $source);
    }

    public function test_audience_exclusion_meta_mentions_placement_and_pixel(): void
    {
        $meta = AdminIntegrationCatalog::cardMeta('audience-exclusion');
        $this->assertStringContainsString('Pixel Guard', $meta['subtitle']);
        $this->assertStringContainsString('Placement', $meta['subtitle']);
    }
}
