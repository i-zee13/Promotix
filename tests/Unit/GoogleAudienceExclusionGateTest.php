<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Services\GoogleAudienceExclusionService;
use Tests\TestCase;

class GoogleAudienceExclusionGateTest extends TestCase
{
    public function test_skips_queue_when_google_ads_is_not_connected(): void
    {
        $domain = new Domain([
            'source' => Domain::SOURCE_MANUAL,
            'google_ads_account_id' => null,
        ]);
        $domain->setRelation('googleAdsMappings', collect());

        $queued = (new GoogleAudienceExclusionService())->queueBlockedIpIfEligible(
            $domain,
            '74.112.117.2',
            'abnormal_rate_limit',
            isPaidTraffic: true,
        );

        $this->assertFalse($queued);
        $this->assertFalse($domain->hasGoogleAdsConnection());
    }

    public function test_skips_queue_for_allowlisted_google_ips(): void
    {
        $domain = new Domain([
            'source' => Domain::SOURCE_MANUAL,
            'google_ads_account_id' => 12,
        ]);

        $queued = (new GoogleAudienceExclusionService())->queueBlockedIpIfEligible(
            $domain,
            '66.249.89.3',
            'automation',
            isPaidTraffic: true,
        );

        $this->assertTrue($domain->hasGoogleAdsConnection());
        $this->assertFalse($queued);
    }
}
