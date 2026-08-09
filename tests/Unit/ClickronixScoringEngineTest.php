<?php

namespace Tests\Unit;

use App\Support\Clickronix\EnforcementMatrix;
use App\Support\Clickronix\ScoringEngine;
use App\Support\Clickronix\TriggeredSignal;
use PHPUnit\Framework\TestCase;

class ClickronixScoringEngineTest extends TestCase
{
    private ScoringEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ScoringEngine;
    }

    public function test_vpn_alone_never_blocks(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered('VPN', 0.95, legacyReason: 'vpn_isp_match'),
        ]);

        $this->assertSame('allow', $result['action_taken']);
        $this->assertFalse($result['standalone_fired']);
        $this->assertLessThan(40, $result['threat_score']);
        $this->assertContains('vpn_isp_match', $result['reasons']);
    }

    public function test_vpn_with_trust_interaction_stays_allow(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered('VPN', 0.95, legacyReason: 'vpn_isp_match'),
            TriggeredSignal::trust('NORMAL_INTERACTION', 1.0),
        ]);

        $this->assertSame('allow', $result['action_taken']);
        $this->assertSame(0, $result['threat_score']);
    }

    public function test_rapid_repeat_block_is_standalone(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered(
                'RAPID_REPEAT_BLOCK',
                1.0,
                recurrenceCount: 2,
                legacyReason: 'RAPID_REPEAT_BLOCK',
                customerPreferredAction: 'block',
            ),
        ]);

        $this->assertTrue($result['standalone_fired']);
        $this->assertSame('block', $result['action_taken']);
        $this->assertContains('RAPID_REPEAT_BLOCK', $result['reasons']);
        $this->assertGreaterThanOrEqual(70, $result['threat_score']);
    }

    public function test_rapid_repeat_flag_via_action_floor_semantics(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered(
                'RAPID_REPEAT',
                0.95,
                legacyReason: 'RAPID_REPEAT',
                customerPreferredAction: 'flag',
            ),
        ]);

        // Score alone may be low/allow; evaluator applyActionFloors raises to flag.
        $this->assertFalse($result['standalone_fired']);
        $this->assertNotSame('block', $result['action_taken']);
        $this->assertContains('RAPID_REPEAT', $result['reasons']);
    }

    public function test_category_caps_prevent_double_count_browser_like_network_stack(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered('VPN', 1.0, legacyReason: 'vpn_isp_match'),
            TriggeredSignal::triggered('DATACENTER_IP', 1.0, legacyReason: 'ipdetails_hosting'),
            TriggeredSignal::triggered('PUBLIC_PROXY', 1.0, legacyReason: 'proxy_isp_match'),
            TriggeredSignal::triggered('MALICIOUS_IP_REPUTATION', 0.9, legacyReason: 'abuse_confidence'),
        ]);

        // All network — one category, capped at 30.
        $this->assertSame(30, $result['category_scores']['network']);
        $this->assertFalse($result['correlation_satisfied']);
        $this->assertNotSame('block', $result['action_taken']);
    }

    public function test_cross_category_correlation_can_reach_block_band(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered('MALICIOUS_IP_REPUTATION', 1.0, legacyReason: 'abuse_confidence'),
            TriggeredSignal::triggered('RAPID_REPEAT', 1.0, recurrenceCount: 3, legacyReason: 'RAPID_REPEAT'),
            TriggeredSignal::triggered('CRAWLER_UA', 0.9, legacyReason: 'crawler_ua'),
            TriggeredSignal::triggered('HIGH_REQUEST_VELOCITY', 0.95, legacyReason: 'rapid_page_opens'),
        ]);

        $this->assertTrue($result['correlation_satisfied']);
        $this->assertGreaterThanOrEqual(40, $result['threat_score']);
    }

    public function test_unknown_signal_adds_zero_points(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::unknown('VPN', ['intel_confidence']),
        ]);

        $this->assertSame(0, $result['threat_score']);
        $this->assertSame('allow', $result['action_taken']);
        $this->assertSame('UNKNOWN', $result['detections'][0]['state']);
    }

    public function test_extreme_velocity_standalone_blocks(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered(
                'EXTREME_REQUEST_VELOCITY',
                0.95,
                legacyReason: 'rapid_page_opens',
                customerPreferredAction: 'block',
            ),
        ]);

        $this->assertTrue($result['standalone_fired']);
        $this->assertSame('block', $result['action_taken']);
    }

    public function test_enforcement_matrix_bands(): void
    {
        $this->assertSame('allow', EnforcementMatrix::forScore(10)['action']);
        $this->assertSame('allow', EnforcementMatrix::forScore(35)['action']);
        $this->assertSame('flag', EnforcementMatrix::forScore(50)['action']);
        $this->assertSame('flag', EnforcementMatrix::forScore(70)['action']);
        $this->assertSame('block', EnforcementMatrix::forScore(80)['action']);
        $this->assertSame('block', EnforcementMatrix::forScore(95)['action']);
    }

    public function test_block_list_style_country_via_standalone_catalog(): void
    {
        $result = $this->engine->score([
            TriggeredSignal::triggered(
                'COUNTRY_RESTRICTION',
                1.0,
                legacyReason: 'blocked_country',
                customerPreferredAction: 'block',
            ),
        ]);

        $this->assertTrue($result['standalone_fired']);
        $this->assertSame('block', $result['action_taken']);
        $this->assertSame('blocked_country', $result['threat_group']);
    }
}
