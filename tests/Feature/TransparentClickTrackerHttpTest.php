<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransparentClickTrackerHttpTest extends TestCase
{
    public function test_click_redirects_when_only_redirect_param_is_present(): void
    {
        $this->get('/click?redirect='.urlencode('https://example.com/lp?gclid=test'))
            ->assertRedirect();
    }

    public function test_public_tracker_docs_are_available(): void
    {
        $this->get('/docs/click-tracker')
            ->assertOk()
            ->assertSee('Transparent Click Tracker', false)
            ->assertSee('CXTRK_', false);
    }
}
