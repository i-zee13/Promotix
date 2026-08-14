<?php

namespace Tests\Unit;

use App\Support\PaidAdvertising\IpRowRiskScorer;
use PHPUnit\Framework\TestCase;

class IpRowRiskScorerTest extends TestCase
{
    public function test_single_invalid_click_does_not_force_high_70(): void
    {
        $outOfGeo = IpRowRiskScorer::score([
            'invalid' => 1,
            'total' => 1,
            'top_threat' => 'out_of_geo',
            'intel_score' => 12,
        ]);

        $this->assertSame('Medium', $outOfGeo['risk_level']);
        $this->assertSame(55, $outOfGeo['risk_score']);
        $this->assertNotSame(70, $outOfGeo['risk_score']);
    }

    public function test_threat_groups_produce_different_scores(): void
    {
        $vpn = IpRowRiskScorer::score([
            'invalid' => 1,
            'total' => 1,
            'top_threat' => 'vpn',
        ]);
        $dc = IpRowRiskScorer::score([
            'invalid' => 1,
            'total' => 1,
            'top_threat' => 'data_center',
        ]);
        $malicious = IpRowRiskScorer::score([
            'invalid' => 1,
            'total' => 1,
            'top_threat' => 'malicious',
        ]);

        $this->assertSame(75, $vpn['risk_score']);
        $this->assertSame(80, $dc['risk_score']);
        $this->assertSame(90, $malicious['risk_score']);
        $this->assertSame('High', $vpn['risk_level']);
        $this->assertSame('High', $dc['risk_level']);
        $this->assertSame('High', $malicious['risk_level']);
    }

    public function test_visit_threat_score_wins_when_higher(): void
    {
        $scored = IpRowRiskScorer::score([
            'invalid' => 1,
            'total' => 1,
            'top_threat' => 'out_of_geo',
            'threat_score' => 88,
            'intel_score' => 10,
        ]);

        $this->assertSame(88, $scored['risk_score']);
        $this->assertSame('High', $scored['risk_level']);
    }

    public function test_valid_traffic_stays_low_without_score(): void
    {
        $scored = IpRowRiskScorer::score([
            'invalid' => 0,
            'total' => 3,
        ]);

        $this->assertSame('Low', $scored['risk_level']);
        $this->assertNull($scored['risk_score']);
    }
}
