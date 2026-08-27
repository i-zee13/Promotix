<?php

namespace Tests\Unit;

use App\Support\SessionBehaviorAnalyzer;
use App\Support\SessionBehaviorFingerprint;
use PHPUnit\Framework\TestCase;

class SessionBehaviorAnalyzerTest extends TestCase
{
    public function test_no_interaction_when_long_idle_session(): void
    {
        $signals = SessionBehaviorAnalyzer::signals([
            ['type' => 'meta', 't' => 0, 'vw' => 1200, 'vh' => 800],
        ], 5000);

        $this->assertContains(SessionBehaviorAnalyzer::NO_INTERACTION, $signals);
    }

    public function test_movement_prevents_no_interaction(): void
    {
        $signals = SessionBehaviorAnalyzer::signals([
            ['type' => 'mousemove', 't' => 100, 'x' => 10, 'y' => 20],
        ], 5000);

        $this->assertSame([], $signals);
    }

    public function test_short_sessions_are_ignored(): void
    {
        $signals = SessionBehaviorAnalyzer::signals([], 500);

        $this->assertSame([], $signals);
    }

    public function test_analyze_counts_cta_tel_and_pages(): void
    {
        $analysis = SessionBehaviorAnalyzer::analyze([
            ['type' => 'page', 't' => 0, 'url' => 'https://example.com/'],
            ['type' => 'click', 't' => 100, 'x' => 1, 'y' => 2, 'cta' => 1, 'href' => '/signup'],
            ['type' => 'click', 't' => 200, 'x' => 3, 'y' => 4, 'tel' => 1, 'href' => 'tel:+15551212'],
            ['type' => 'page', 't' => 300, 'url' => 'https://example.com/thanks'],
            ['type' => 'scroll', 't' => 400, 'x' => 0, 'y' => 120],
        ], 5000);

        $this->assertSame([], $analysis['signals']);
        $this->assertSame(1, $analysis['cta_clicks']);
        $this->assertSame(1, $analysis['tel_clicks']);
        $this->assertSame(1, $analysis['page_changes']);
        $this->assertSame(1, $analysis['scroll_count']);
        $this->assertSame(400, $analysis['first_scroll_ms']);
    }

    public function test_analyze_recognizes_tel_href_without_an_explicit_marker(): void
    {
        $analysis = SessionBehaviorAnalyzer::analyze([
            ['type' => 'click', 't' => 100, 'href' => 'TEL:+15551212'],
        ], 5000);

        $this->assertSame(0, $analysis['cta_clicks']);
        $this->assertSame(1, $analysis['tel_clicks']);
        $this->assertSame('TEL:+15551212', $analysis['last_cta_href']);
    }

    public function test_analyze_infers_cta_from_btn_anchor_class(): void
    {
        $analysis = SessionBehaviorAnalyzer::analyze([
            ['type' => 'click', 't' => 100, 'tag' => 'A', 'class' => 'btn btn-primary', 'href' => '/signup'],
        ], 5000);

        $this->assertSame(1, $analysis['cta_clicks']);
        $this->assertSame(0, $analysis['tel_clicks']);
    }

    public function test_analyze_counts_typed_cta_and_phone_events(): void
    {
        $analysis = SessionBehaviorAnalyzer::analyze([
            ['type' => 'page_view', 't' => 0, 'url' => 'https://example.com/', 'title' => 'Home'],
            ['type' => 'cta_click', 't' => 100, 'href' => '/signup', 'element_text' => 'Sign up', 'page_url' => 'https://example.com/'],
            ['type' => 'phone_click', 't' => 200, 'href' => 'tel:+15551212', 'tel_number' => '+15551212'],
            ['type' => 'click', 't' => 250, 'cta' => 1, 'href' => '/ignored-legacy'],
        ], 5000);

        $this->assertSame(1, $analysis['cta_clicks']);
        $this->assertSame(1, $analysis['tel_clicks']);
        $this->assertSame('tel:+15551212', $analysis['last_cta_href']);
    }

    public function test_analyze_counts_forms_and_commerce(): void
    {
        $analysis = SessionBehaviorAnalyzer::analyze([
            ['type' => 'form_start', 't' => 50, 'form_id' => 'lead'],
            ['type' => 'form_submit', 't' => 100, 'form_id' => 'lead', 'success' => true],
            ['type' => 'add_to_cart', 't' => 150, 'sku' => 'A'],
            ['type' => 'checkout', 't' => 200],
            ['type' => 'purchase', 't' => 250, 'order_id' => '1'],
        ], 5000);

        $this->assertSame(1, $analysis['form_starts']);
        $this->assertSame(1, $analysis['form_submits']);
        $this->assertSame(1, $analysis['add_to_cart']);
        $this->assertSame(1, $analysis['checkouts']);
        $this->assertSame(1, $analysis['purchases']);
        $this->assertNotEmpty($analysis['timeline']);
    }

    public function test_fingerprint_includes_scroll_timing_bucket(): void
    {
        $fp = SessionBehaviorFingerprint::fromEvents([
            ['type' => 'scroll', 't' => 1000, 'x' => 0, 'y' => 40],
            ['type' => 'scroll', 't' => 1500, 'x' => 0, 'y' => 80],
        ], 5000);

        $this->assertStringStartsWith('v2:', $fp);
        $this->assertStringContainsString('sf2', $fp);
        $this->assertStringContainsString('sp2', $fp);
    }
}
