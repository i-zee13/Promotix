<?php

namespace Tests\Unit;

use App\Services\IpIntel\IpFraudEvaluator;
use App\Support\GlobalIpAllowlist;
use Tests\TestCase;

class GlobalIpAllowlistTest extends TestCase
{
    public function test_google_provider_cidrs_cover_googlebot_ips(): void
    {
        $list = implode("\n", GlobalIpAllowlist::providerCidrs()['google']);

        $this->assertTrue(IpFraudEvaluator::isIpInList('66.249.89.3', $list));
        $this->assertTrue(IpFraudEvaluator::isIpInList('66.249.89.7', $list));
        $this->assertTrue(IpFraudEvaluator::isIpInList('66.249.89.8', $list));
        $this->assertTrue(IpFraudEvaluator::isIpInList('2001:4860:4860::8888', $list));
        $this->assertFalse(IpFraudEvaluator::isIpInList('65.249.65.6', $list));
        $this->assertTrue(GlobalIpAllowlist::matches('66.249.89.3'));
        $this->assertTrue(GlobalIpAllowlist::matches('66.249.95.7', [
            'org' => 'Google LLC',
            'asn' => 15169,
        ]));
    }
}
