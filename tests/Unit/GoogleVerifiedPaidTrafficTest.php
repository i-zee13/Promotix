<?php

namespace Tests\Unit;

use App\Support\CampaignAttributionResolver;
use App\Support\GoogleVerifiedCampaignLookup;
use App\Support\GoogleVerifiedPaidTraffic;
use Tests\TestCase;

class GoogleVerifiedPaidTrafficTest extends TestCase
{
    public function test_resolve_campaign_id_prefers_gad_campaignid_in_url(): void
    {
        $id = GoogleVerifiedPaidTraffic::resolveCampaignId([
            'url' => 'https://example.com/?gad_campaignid=23965408733&gclid=abc',
            'google_campaign_id' => '11111111111',
        ]);

        $this->assertSame('23965408733', $id);
    }

    public function test_resolve_campaign_id_falls_back_to_stored_google_campaign_id(): void
    {
        $id = GoogleVerifiedPaidTraffic::resolveCampaignId([
            'url' => 'https://example.com/contact/',
            'google_campaign_id' => '23965408733',
        ]);

        $this->assertSame('23965408733', $id);
    }

    public function test_lookup_verifies_when_campaign_had_clicks_on_metric_date(): void
    {
        $lookup = new GoogleVerifiedCampaignLookup(
            ['5|23965408733|2026-07-01' => true],
            [5 => 'UTC'],
        );

        $this->assertTrue($lookup->isVerified(5, '23965408733', '2026-07-01 14:00:00', 'UTC'));
        $this->assertSame('Verified', $lookup->statusLabel(5, '23965408733', '2026-07-01 14:00:00', 'UTC'));
    }

    public function test_lookup_rejects_unknown_campaign_or_inactive_day(): void
    {
        $lookup = new GoogleVerifiedCampaignLookup(
            ['5|23965408733|2026-07-01' => true],
            [5 => 'UTC'],
        );

        $this->assertFalse($lookup->isVerified(5, '23924617616', '2026-07-06 04:20:28', 'UTC'));
        $this->assertFalse($lookup->isVerified(5, '23965408733', '2026-07-06 04:20:28', 'UTC'));
        $this->assertSame('Unverified', $lookup->statusLabel(5, '23924617616', '2026-07-06 04:20:28', 'UTC'));
        $this->assertSame('No campaign key', $lookup->statusLabel(5, '', '2026-07-06 04:20:28', 'UTC'));
    }

    public function test_count_rows_splits_verified_and_unverified(): void
    {
        $lookup = new GoogleVerifiedCampaignLookup(
            ['1|100|2026-07-01' => true],
            [1 => 'UTC'],
        );

        $service = new GoogleVerifiedPaidTraffic();
        $counts = $service->countRows([
            (object) [
                'domain_id' => 1,
                'url' => 'https://x.test/?gad_campaignid=100&gclid=a',
                'visited_at' => '2026-07-01 10:00:00',
            ],
            (object) [
                'domain_id' => 1,
                'url' => 'https://x.test/?gad_campaignid=999&gclid=b',
                'visited_at' => '2026-07-01 11:00:00',
            ],
        ], $lookup, 'UTC');

        $this->assertSame(1, $counts['verified']);
        $this->assertSame(1, $counts['unverified']);
    }

    public function test_campaign_attribution_resolver_extracts_gad_campaignid(): void
    {
        $this->assertSame(
            '23965408733',
            CampaignAttributionResolver::extractGoogleCampaignId([
                'url' => 'https://onpointmortgagepro.com/?gad_campaignid=23965408733&gclid=x',
            ])
        );
    }
}
