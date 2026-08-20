<?php

namespace Tests\Unit;

use App\Support\GoogleAdsClickRedirect;
use Illuminate\Http\Request;
use Tests\TestCase;

class GoogleAdsClickRedirectTest extends TestCase
{
    public function test_tracking_template_matches_google_doc_shape(): void
    {
        $url = GoogleAdsClickRedirect::trackingTemplateUrl('https://app.promotix.test');

        $this->assertStringContainsString('https://app.promotix.test/click?', $url);
        $this->assertStringContainsString('redirect={lpurl}', $url);
        $this->assertStringContainsString('final_url={lpurl}', $url);
        $this->assertStringContainsString('source=google_ads', $url);
        $this->assertStringContainsString('cx_campaign={campaignid}', $url);
        $this->assertStringContainsString('keyword={keyword}', $url);
    }

    public function test_build_redirect_forwards_click_id_without_campaign_id(): void
    {
        $landing = 'https://insuranceforme.online/conventional/';
        $redirect = GoogleAdsClickRedirect::buildRedirectUrl($landing, [
            'gclid' => 'REAL_GOOGLE_CLICK_ID',
            'keyword' => 'insurance quotes',
            'source' => 'google_ads',
            'adgroup_id' => '123456789',
            'device' => 'm',
        ]);

        $this->assertStringContainsString('gclid=REAL_GOOGLE_CLICK_ID', $redirect);
        $this->assertStringNotContainsString('gad_campaignid=', $redirect);
        $this->assertStringNotContainsString('campaign_id=', $redirect);
        $this->assertStringContainsString('keyword=insurance+quotes', $redirect);
        $this->assertStringContainsString('adgroup_id=123456789', $redirect);
    }

    public function test_parse_click_accepts_redirect_alias_and_cx_registry(): void
    {
        $request = Request::create(
            '/click?redirect=' . urlencode('https://insuranceforme.online/lp')
            . '&cx_campaign=2399&adgroup_id=88&gclid=ABC123',
            'GET'
        );

        $params = GoogleAdsClickRedirect::parseClickRequest($request);

        $this->assertSame('https://insuranceforme.online/lp', $params['final_url']);
        $this->assertSame('ABC123', $params['gclid']);
        $this->assertSame('2399', $params['cx_registry']['cx_campaign']);
        $this->assertSame('88', $params['cx_registry']['cx_adgroup']);
    }

    public function test_parse_click_reads_gclid_from_encoded_final_url(): void
    {
        $request = Request::create(
            '/click?final_url=' . urlencode('https://insuranceforme.online/?gclid=inside_final_url') . '&campaign_id=123',
            'GET'
        );

        $params = GoogleAdsClickRedirect::parseClickRequest($request);

        $this->assertSame('inside_final_url', $params['gclid']);
        $this->assertArrayNotHasKey('campaign_id', $params);
    }
}
