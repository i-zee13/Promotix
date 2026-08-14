<?php

namespace Tests\Unit;

use App\Models\DomainDetectionSetting;
use App\Support\GeoAudienceMatcher;
use PHPUnit\Framework\TestCase;

class GeoAudienceCaliforniaMatchTest extends TestCase
{
    private function californiaSettings(): DomainDetectionSetting
    {
        return new DomainDetectionSetting([
            'out_of_geo_enabled' => true,
            'out_of_geo_audience' => [
                'rules' => [
                    ['country' => 'US', 'state' => 'CA', 'city' => null],
                ],
            ],
        ]);
    }

    public function test_california_full_name_matches_ca_rule(): void
    {
        $settings = $this->californiaSettings();

        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', 'California', 'Los Angeles'));
        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', 'CA', 'San Diego'));
        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'United States', 'California', 'Sacramento'));
    }

    public function test_nevada_las_vegas_is_out_of_geo_for_california_only(): void
    {
        $settings = $this->californiaSettings();

        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'US', 'Nevada', 'Las Vegas'));
        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'US', 'NV', 'Las Vegas'));
    }

    public function test_missing_region_does_not_false_positive_when_country_matches(): void
    {
        $settings = $this->californiaSettings();

        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', null, null));
        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'PK', null, null));
    }

    public function test_california_cities_match_even_when_region_missing_or_is_city_name(): void
    {
        $settings = $this->californiaSettings();

        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', null, 'Sacramento'));
        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', null, 'Los Angeles'));
        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', null, 'Rancho Cucamonga'));
        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', 'Sacramento', 'Sacramento'));
        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'United States', '', 'Hayward'));
    }

    public function test_non_california_city_still_out_of_geo(): void
    {
        $settings = $this->californiaSettings();

        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'US', null, 'Las Vegas'));
        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'US', null, 'Tulsa'));
        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'US', 'Washington', 'Washington'));
    }
}
