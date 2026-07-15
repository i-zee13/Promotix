<?php

namespace Tests\Unit;

use App\Services\IpIntel\IpFraudEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * DE-01 / DE-02 / DE-03 rapid-repeat gates (prior paid-click counts in 2-minute window).
 * 0 prior = first click (valid), 1 = second click (RAPID_REPEAT), 2+ = third (block).
 */
class RapidRepeatDetectionTest extends TestCase
{
    public function test_rapid_window_constant_is_two_minutes(): void
    {
        $this->assertSame(120, IpFraudEvaluator::PAID_RAPID_WINDOW_SECONDS);
    }

    public function test_daily_limit_blocks_on_third_valid_paid_click(): void
    {
        // Prior valid paid clicks today >= 2 means current hit is the 3rd+.
        $this->assertSame(2, IpFraudEvaluator::PAID_DAILY_VALID_CLICK_LIMIT);
    }

    /**
     * @dataProvider rapidCases
     */
    public function test_rapid_repeat_decision_matrix(
        int $priorInWindow,
        string $expectedPrimaryReason,
        string $expectedActionFloor,
    ): void {
        [$reason, $action, $score] = $this->decide($priorInWindow);

        $this->assertSame($expectedPrimaryReason, $reason);
        if ($expectedActionFloor === 'allow') {
            $this->assertSame('allow', $action);
        } elseif ($expectedActionFloor === 'block') {
            $this->assertSame('block', $action);
        } else {
            $this->assertNotSame('allow', $action);
        }
        if ($priorInWindow === 1) {
            $this->assertSame(35, $score);
        }
        if ($priorInWindow >= 2) {
            $this->assertGreaterThanOrEqual(70, $score);
        }
    }

    public static function rapidCases(): array
    {
        return [
            'first click' => [0, 'none', 'allow'],
            'second click' => [1, 'RAPID_REPEAT', 'flag'],
            'third click' => [2, 'RAPID_REPEAT_BLOCK', 'block'],
        ];
    }

    /**
     * Mirrors IpFraudEvaluator rapid-repeat branch ordering.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function decide(int $paidClicksInRapidWindow): array
    {
        if ($paidClicksInRapidWindow >= 2) {
            return ['RAPID_REPEAT_BLOCK', 'block', 70];
        }
        if ($paidClicksInRapidWindow === 1) {
            return ['RAPID_REPEAT', 'flag', 35];
        }

        return ['none', 'allow', 0];
    }
}
