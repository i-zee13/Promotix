<?php

namespace Tests\Unit;

use App\Services\IpIntel\IpFraudEvaluator;
use App\Support\IpListParser;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class IpListParserTest extends TestCase
{
    public function test_normalize_lines_converts_duration_to_expires(): void
    {
        $now = Carbon::parse('2026-07-14 12:00:00', 'UTC');
        $lines = IpListParser::normalizeLines("1.2.3.4 | 1h\n5.6.7.8 | permanent\n9.9.9.9", $now);

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('1.2.3.4 #expires=', $lines[0]);
        $this->assertSame('5.6.7.8', $lines[1]);
        $this->assertSame('9.9.9.9', $lines[2]);
    }

    public function test_expired_entries_are_inactive(): void
    {
        $now = Carbon::parse('2026-07-14 12:00:00', 'UTC');
        $entry = '1.2.3.4 #expires=2026-07-14T11:00:00+00:00';

        $this->assertFalse(IpListParser::isActiveEntry($entry, $now));
        $this->assertTrue(IpListParser::isActiveEntry('1.2.3.4 #expires=2026-07-14T13:00:00+00:00', $now));
        $this->assertTrue(IpListParser::isActiveEntry('1.2.3.4', $now));
    }

    public function test_evaluator_skips_expired_block_entries(): void
    {
        $list = "1.2.3.4 #expires=2020-01-01T00:00:00+00:00\n5.6.7.8 #expires=2099-01-01T00:00:00+00:00\n10.0.0.*";

        $this->assertFalse(IpFraudEvaluator::isIpInList('1.2.3.4', $list));
        $this->assertTrue(IpFraudEvaluator::isIpInList('5.6.7.8', $list));
        $this->assertTrue(IpFraudEvaluator::isIpInList('10.0.0.55', $list));
    }
}
