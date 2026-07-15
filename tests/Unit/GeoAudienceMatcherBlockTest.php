<?php

namespace Tests\Unit;

use App\Models\DomainDetectionSetting;
use App\Support\GeoAudienceMatcher;
use PHPUnit\Framework\TestCase;

class GeoAudienceMatcherBlockTest extends TestCase
{
    public function test_block_country_rule_matches_selected_country(): void
    {
        $settings = new DomainDetectionSetting([
            'google_geo_block_enabled' => true,
            'google_geo_block_audience' => [
                'rules' => [
                    ['country' => 'PK', 'state' => null, 'city' => null],
                ],
            ],
        ]);

        $this->assertTrue(GeoAudienceMatcher::isBlocked($settings, 'PK', null, null));
        $this->assertFalse(GeoAudienceMatcher::isBlocked($settings, 'US', null, null));
    }

    public function test_allow_country_mode_only_allows_selected(): void
    {
        $settings = new DomainDetectionSetting([
            'out_of_geo_enabled' => true,
            'out_of_geo_audience' => [
                'rules' => [
                    ['country' => 'US', 'state' => null, 'city' => null],
                ],
            ],
        ]);

        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', null, null));
        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'PK', null, null));
    }
}
