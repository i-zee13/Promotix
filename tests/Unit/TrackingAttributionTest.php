<?php

namespace Tests\Unit;

use App\Services\IpIntel\AllowListMatcher;
use App\Services\IpIntel\IpFraudEvaluator;
use App\Services\IpIntel\VisitProtectionService;
use App\Models\DomainDetectionSetting;
use App\Support\GoogleClickAttribution;
use Tests\TestCase;

class TrackingAttributionTest extends TestCase
{
    public function test_google_click_attribution_detects_gclid_gbraid_wbraid(): void
    {
        $this->assertTrue(GoogleClickAttribution::isPaidTraffic(['gclid' => 'abc']));
        $this->assertTrue(GoogleClickAttribution::isPaidTraffic(['gbraid' => 'xyz']));
        $this->assertTrue(GoogleClickAttribution::isPaidTraffic(['wbraid' => 'ios']));
        // Campaign IDs never qualify as paid traffic — only Google click IDs do.
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic(['gad_campaignid' => '123']));
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic(['campaign_id' => '123'], null));
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic(['utm_medium' => 'cpc']));
    }

    public function test_campaign_id_never_qualifies_as_paid_traffic(): void
    {
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic([
            'campaign_id' => '23997382536',
        ], 91001));
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic([
            'gad_campaignid' => '23997382536',
            'url' => 'https://example.com/?gad_campaignid=23997382536',
        ], 91001));
    }

    public function test_google_click_attribution_prefers_gclid_over_gbraid(): void
    {
        $resolved = GoogleClickAttribution::resolve([
            'gclid' => 'primary',
            'gbraid' => 'secondary',
        ]);

        $this->assertSame(['id' => 'primary', 'type' => 'gclid'], $resolved);
    }

    /** Regression: isIpInList must use the $list argument (not undefined $allowList). */
    public function test_is_ip_in_list_matches_literal_and_wildcard(): void
    {
        $this->assertTrue(IpFraudEvaluator::isIpInList('203.0.113.10', "203.0.113.10\n203.0.113.11"));
        $this->assertTrue(IpFraudEvaluator::isIpAllowListed('203.0.113.55', '203.0.113.*'));
        $this->assertFalse(IpFraudEvaluator::isIpInList('203.0.113.10', '198.51.100.1'));
    }

    /** Allow list IPs are stored but ignored until allow_list_enabled is true. */
    public function test_allow_list_only_applies_when_toggle_is_on(): void
    {
        $settings = new DomainDetectionSetting([
            'allow_list_enabled' => false,
            'allow_list_ips' => "103.207.87.2\n192.168.110.101",
        ]);

        $this->assertFalse(AllowListMatcher::matchesSettings($settings, '103.207.87.2'));

        $settings->allow_list_enabled = true;

        $this->assertTrue(AllowListMatcher::matchesSettings($settings, '103.207.87.2'));
        $this->assertFalse(AllowListMatcher::matchesSettings($settings, '10.0.0.1'));
    }

    /** Regression: VisitProtectionService lives outside App\Support and must import GoogleClickAttribution. */
    public function test_visit_protection_service_imports_google_click_attribution(): void
    {
        $source = file_get_contents((new \ReflectionClass(VisitProtectionService::class))->getFileName());

        $this->assertStringContainsString('use App\Support\GoogleClickAttribution;', $source);
    }
}
