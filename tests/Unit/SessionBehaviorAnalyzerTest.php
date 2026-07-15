<?php

namespace Tests\Unit;

use App\Support\SessionBehaviorAnalyzer;
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
}
