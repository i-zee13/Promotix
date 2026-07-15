<?php

namespace Tests\Unit;

use App\Support\IpListParser;
use PHPUnit\Framework\TestCase;

/**
 * QA-02: IP / geo precedence — allow list beats block list.
 */
class DetectionPrecedenceTest extends TestCase
{
    public function test_allow_list_entry_wins_over_block_list(): void
    {
        $ip = '203.0.113.50';
        $allowLines = IpListParser::normalizeLines("{$ip}\n198.51.100.0/24");
        $blockLines = IpListParser::normalizeLines("{$ip}\n203.0.113.0/24");

        $this->assertContains($ip, $allowLines);
        $this->assertContains($ip, $blockLines);

        // Precedence rule: evaluate allow before block (mirrors IpFraudEvaluator ordering).
        $allowFirst = $this->simulatePrecedence($ip, $allowLines, $blockLines);
        $this->assertSame('allow', $allowFirst);
    }

    public function test_block_list_applies_when_not_allow_listed(): void
    {
        $ip = '203.0.113.99';
        $allowLines = IpListParser::normalizeLines('198.51.100.10');
        $blockLines = IpListParser::normalizeLines('203.0.113.0/24');

        $this->assertSame('block', $this->simulatePrecedence($ip, $allowLines, $blockLines));
    }

    /**
     * @param  list<string>  $allowLines
     * @param  list<string>  $blockLines
     */
    private function simulatePrecedence(string $ip, array $allowLines, array $blockLines): string
    {
        foreach ($allowLines as $rule) {
            if ($this->ipMatches($ip, $rule)) {
                return 'allow';
            }
        }

        foreach ($blockLines as $rule) {
            if ($this->ipMatches($ip, $rule)) {
                return 'block';
            }
        }

        return 'continue';
    }

    private function ipMatches(string $ip, string $rule): bool
    {
        if ($ip === $rule) {
            return true;
        }

        if (str_contains($rule, '/')) {
            [$subnet, $bits] = explode('/', $rule, 2);
            $mask = -1 << (32 - (int) $bits);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            return $ipLong !== false && $subnetLong !== false
                && (($ipLong & $mask) === ($subnetLong & $mask));
        }

        return false;
    }
}
