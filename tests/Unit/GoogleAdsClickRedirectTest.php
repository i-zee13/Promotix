<?php

namespace Tests\Unit;

use App\Support\GoogleAdsClickRedirect;
use Tests\TestCase;

class GoogleAdsClickRedirectTest extends TestCase
{
    public function test_tracking_template_matches_google_doc_shape(): void
    {
        $url = GoogleAdsClickRedirect::trackingTemplateUrl('https://app.promotix.test');

        $this->assertStringContainsString('https://app.promotix.test/click?', $url);
        $this->assertStringContainsString('final_url={lpurl}', $url);
        $this->assertStringContainsString('source=google_ads', $url);
        $this->assertStringContainsString('campaign_id={campaignid}', $url);
        $this->assertStringContainsString('keyword={keyword}', $url);
    }

    public function test_build_redirect_forwards_click_id_and_campaign_params(): void
    {
        $landing = 'https://insuranceforme.online/conventional/';
        $redirect = GoogleAdsClickRedirect::buildRedirectUrl($landing, [
            'gclid' => 'REAL_GOOGLE_CLICK_ID',
            'gad_campaignid' => '23997382536',
            'campaign_id' => '23997382536',
            'keyword' => 'insurance quotes',
            'source' => 'google_ads',
            'adgroup_id' => '123456789',
            'device' => 'm',
        ]);

        $this->assertStringContainsString('gclid=REAL_GOOGLE_CLICK_ID', $redirect);
        $this->assertStringContainsString('gad_campaignid=23997382536', $redirect);
        $this->assertStringContainsString('keyword=insurance+quotes', $redirect);
        $this->assertStringContainsString('adgroup_id=123456789', $redirect);
    }
}
