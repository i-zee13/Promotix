<?php

namespace Tests\Unit;

use App\Support\TransparentClickTracker;
use Illuminate\Http\Request;
use Tests\TestCase;

class TransparentClickTrackerTest extends TestCase
{
    public function test_mint_id_uses_cxtrk_prefix(): void
    {
        $id = TransparentClickTracker::mintId();

        $this->assertMatchesRegularExpression('/^CXTRK_[A-F0-9]{16}$/', $id);
    }

    public function test_landing_url_prefers_redirect_over_final_url(): void
    {
        $request = Request::create('/click?redirect=https://a.example/x&final_url=https://b.example/y', 'GET');

        $this->assertSame('https://a.example/x', TransparentClickTracker::landingUrl($request));
    }

    public function test_cxtrk_is_read_from_landing_url(): void
    {
        $id = TransparentClickTracker::cxtrkFromPayload([
            'url' => 'https://site.example/lp?gclid=x&cxtrk=CXTRK_AABBCCDDEEFF0011',
        ]);

        $this->assertSame('CXTRK_AABBCCDDEEFF0011', $id);
    }

    public function test_click_id_prefers_gclid(): void
    {
        $parsed = TransparentClickTracker::clickIdFromParams([
            'gclid' => 'G1',
            'gbraid' => 'B1',
        ]);

        $this->assertSame(['click_id' => 'G1', 'click_id_type' => 'gclid'], $parsed);
    }
}
