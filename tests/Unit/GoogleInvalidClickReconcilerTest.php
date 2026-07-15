<?php

namespace Tests\Unit;

use App\Support\GoogleInvalidClickReconciler;
use App\Support\GoogleVerifiedCampaignLookup;
use Tests\TestCase;

class GoogleInvalidClickReconcilerTest extends TestCase
{
    public function test_categorizes_platform_google_and_overlap_buckets(): void
    {
        $lookup = new GoogleVerifiedCampaignLookup(
            ['1|100|2026-07-01' => true],
            [1 => 'UTC'],
        );

        $rows = [
            (object) [
                'domain_id' => 1,
                'url' => 'https://x.test/?gad_campaignid=100&gclid=a',
                'visited_at' => '2026-07-01 10:00:00',
                'is_invalid_traffic' => true,
            ],
            (object) [
                'domain_id' => 1,
                'url' => 'https://x.test/?gad_campaignid=999&gclid=b',
                'visited_at' => '2026-07-01 11:00:00',
                'is_invalid_traffic' => false,
            ],
            (object) [
                'domain_id' => 1,
                'url' => 'https://x.test/?gad_campaignid=999&gclid=c',
                'visited_at' => '2026-07-01 12:00:00',
                'is_invalid_traffic' => true,
            ],
        ];

        $result = (new GoogleInvalidClickReconciler())->categorize($rows, $lookup, 'UTC');

        $this->assertSame(1, $result['platform_only']);
        $this->assertSame(1, $result['google_only']);
        $this->assertSame(1, $result['overlap']);
        $this->assertSame(2, $result['platform_invalid_total']);
        $this->assertSame(2, $result['google_gap_total']);
    }
}
