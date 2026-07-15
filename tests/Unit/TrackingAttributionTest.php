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
        // Campaign alone is never paid without a domain context / known synced campaign.
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic(['gad_campaignid' => '123']));
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic(['campaign_id' => '123'], null));
        $this->assertFalse(GoogleClickAttribution::isPaidTraffic(['utm_medium' => 'cpc']));
    }

    public function test_paid_from_campaign_id_requires_synced_match(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('google_ads_campaign_daily_metrics')) {
                $this->markTestSkipped('google_ads_campaign_daily_metrics missing');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: ' . $e->getMessage());
        }

        $domainId = 91001;
        $campaignId = '23997382536';

        \Illuminate\Support\Facades\DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->where('campaign_id', $campaignId)
            ->delete();

        $this->assertFalse(GoogleClickAttribution::isPaidTraffic([
            'campaign_id' => $campaignId,
        ], $domainId));

        try {
            \Illuminate\Support\Facades\DB::table('google_ads_campaign_daily_metrics')->insert([
                'domain_id' => $domainId,
                'google_ads_account_id' => 1,
                'campaign_id' => $campaignId,
                'campaign_name' => 'QA Campaign',
                'metric_date' => now()->toDateString(),
                'clicks' => 1,
                'impressions' => 10,
                'cost' => 1,
                'cpc' => 1,
                'conversions' => 0,
                'phone_calls' => 0,
                'ctr' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not insert test campaign row: ' . $e->getMessage());
        }

        try {
            $this->assertTrue(GoogleClickAttribution::isPaidTraffic([
                'campaign_id' => $campaignId,
            ], $domainId));
            $this->assertTrue(GoogleClickAttribution::isPaidTraffic([
                'gad_campaignid' => $campaignId,
                'url' => 'https://example.com/?gad_campaignid=' . $campaignId,
            ], $domainId));
            $this->assertFalse(GoogleClickAttribution::isPaidTraffic([
                'campaign_id' => '99999999999',
            ], $domainId));
        } finally {
            \Illuminate\Support\Facades\DB::table('google_ads_campaign_daily_metrics')
                ->where('domain_id', $domainId)
                ->where('campaign_id', $campaignId)
                ->delete();
        }
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
