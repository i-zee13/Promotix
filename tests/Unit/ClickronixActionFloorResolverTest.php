<?php

namespace Tests\Unit;

use App\Support\Clickronix\ActionFloorResolver;
use App\Support\Clickronix\EnforcementMatrix;
use App\Support\Clickronix\ScoringEngine;
use App\Support\Clickronix\TriggeredSignal;
use PHPUnit\Framework\TestCase;

class ClickronixActionFloorResolverTest extends TestCase
{
    public function test_correlated_block_preference_softens_to_flag_at_low_score(): void
    {
        $this->assertSame(
            'flag',
            ActionFloorResolver::softenCorrelatedPreference('block', 38, true)
        );
    }

    public function test_correlated_block_preference_allows_block_at_high_score(): void
    {
        $this->assertSame(
            'block',
            ActionFloorResolver::softenCorrelatedPreference('block', 80, true)
        );
    }

    public function test_datacenter_plus_rapid_at_low_score_flags_not_blocks(): void
    {
        $engine = new ScoringEngine;
        $scored = $engine->score([
            TriggeredSignal::triggered(
                'DATACENTER_IP',
                0.90,
                legacyReason: 'ipdetails_hosting',
                customerPreferredAction: 'block',
            ),
            TriggeredSignal::triggered(
                'RAPID_REPEAT',
                0.95,
                legacyReason: 'RAPID_REPEAT',
                customerPreferredAction: 'flag',
            ),
        ]);

        $this->assertTrue($scored['correlation_satisfied']);
        $this->assertLessThan(75, $scored['threat_score']);

        $final = ActionFloorResolver::apply($scored, [
            TriggeredSignal::triggered('DATACENTER_IP', 0.90, legacyReason: 'ipdetails_hosting', customerPreferredAction: 'block'),
            TriggeredSignal::triggered('RAPID_REPEAT', 0.95, legacyReason: 'RAPID_REPEAT', customerPreferredAction: 'flag'),
        ]);

        $this->assertSame('flag', $final['action_taken'], 'low-score correlated must not hard-block via matrix floor');
        $this->assertNotSame(EnforcementMatrix::LEVEL_TRUSTED, $final['risk_level']);
        $this->assertNotSame('block', $final['action_taken']);
    }

    public function test_rapid_repeat_flag_floor_still_applies_at_low_score(): void
    {
        $engine = new ScoringEngine;
        $scored = $engine->score([
            TriggeredSignal::triggered(
                'RAPID_REPEAT',
                0.95,
                legacyReason: 'RAPID_REPEAT',
                customerPreferredAction: 'flag',
            ),
        ]);

        $final = ActionFloorResolver::apply($scored, [
            TriggeredSignal::triggered('RAPID_REPEAT', 0.95, legacyReason: 'RAPID_REPEAT', customerPreferredAction: 'flag'),
        ]);

        $this->assertSame('flag', $final['action_taken']);
    }

    public function test_standalone_rapid_block_still_blocks(): void
    {
        $engine = new ScoringEngine;
        $scored = $engine->score([
            TriggeredSignal::triggered(
                'RAPID_REPEAT_BLOCK',
                1.0,
                recurrenceCount: 2,
                legacyReason: 'RAPID_REPEAT_BLOCK',
                customerPreferredAction: 'block',
            ),
        ]);

        $final = ActionFloorResolver::apply($scored, [
            TriggeredSignal::triggered(
                'RAPID_REPEAT_BLOCK',
                1.0,
                recurrenceCount: 2,
                legacyReason: 'RAPID_REPEAT_BLOCK',
                customerPreferredAction: 'block',
            ),
        ]);

        $this->assertTrue($final['standalone_fired']);
        $this->assertSame('block', $final['action_taken']);
    }
}
