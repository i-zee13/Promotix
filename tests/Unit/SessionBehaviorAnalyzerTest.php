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
