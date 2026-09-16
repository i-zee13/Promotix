<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Support\CampaignAttributionResolver;
use Tests\TestCase;

class CampaignAttributionResolverTest extends TestCase
{
    public function test_resolve_reads_gad_campaignid_from_url(): void
    {
        $domain = new Domain;
        $domain->id = 1;

        $resolved = CampaignAttributionResolver::resolve($domain, [
            'url' => 'https://example.com/landing?gclid=abc&gad_campaignid=1234567890',
            'utm_campaign' => '',
        ]);

        $this->assertSame('1234567890', $resolved['google_campaign_id']);
    }

    public function test_resolve_prefers_utm_campaign_name(): void
    {
        $domain = new Domain;
        $domain->id = 1;

        $resolved = CampaignAttributionResolver::resolve($domain, [
            'url' => 'https://example.com/?gad_campaignid=999&utm_campaign=JFK%20airport%20transfer',
        ]);

        $this->assertSame('999', $resolved['google_campaign_id']);
        $this->assertSame('JFK airport transfer', $resolved['campaign_name']);
        $this->assertSame('JFK airport transfer', $resolved['campaign']);
    }

    public function test_extract_google_campaign_id_from_query_keys(): void
    {
        $id = CampaignAttributionResolver::extractGoogleCampaignId([
            'gad_campaignid' => '222-333',
        ]);

        $this->assertSame('222333', $id);
    }
}
