<?php

namespace Tests\Unit;

use App\Support\DetectionReasonLabels;
use App\Support\RiskLabels;
use PHPUnit\Framework\TestCase;

class RiskLabelsTest extends TestCase
{
    public function test_allow_list_maps_to_allowed_override(): void
    {
        $this->assertSame(
            RiskLabels::ALLOWED_OVERRIDE,
            RiskLabels::fromContext(['is_allowlisted' => true])
        );
    }

    public function test_blocked_and_invalid_labels(): void
    {
        $this->assertSame(RiskLabels::BLOCKED, RiskLabels::fromContext(['is_blocked' => true]));
        $this->assertSame(RiskLabels::INVALID, RiskLabels::fromContext([
            'threat_group' => 'manual_invalid',
            'action_taken' => 'flag',
        ]));
        $this->assertSame(RiskLabels::SUSPICIOUS, RiskLabels::fromContext([
            'threat_group' => 'vpn',
            'action_taken' => 'flag',
            'reasons' => ['vpn'],
        ]));
        $this->assertSame(RiskLabels::GOOGLE_INVALID, RiskLabels::fromContext(['google_invalid' => true]));
    }

    public function test_reason_labels_are_human_readable(): void
    {
        $rows = DetectionReasonLabels::explain(['RAPID_REPEAT', 'NO_INTERACTION']);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('rapid window', $rows[0]['label']);
        $this->assertStringContainsString('no mouse', strtolower($rows[1]['label']));
    }
}
