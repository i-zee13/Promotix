<?php

namespace Tests\Unit;

use App\Support\PaidAdvertising\AdsRepeatEvaluator;
use App\Support\PaidAdvertising\PaidDeviceFingerprinter;
use App\Support\PaidAdvertising\ResolvedPaidIdentity;
use PHPUnit\Framework\TestCase;

class PaidGptGapsPhaseCTest extends TestCase
{
    public function test_device_id_stable_when_browser_cookie_resets(): void
    {
        $fp = PaidDeviceFingerprinter::fingerprintId('canvas:abc|webgl:xyz', 'BROWSER_A', 'Mozilla/5.0 (Linux; Android 14)', 'en-US');
        $dev1 = PaidDeviceFingerprinter::deviceId($fp, 'Mozilla/5.0 (Linux; Android 14)');
        $dev2 = PaidDeviceFingerprinter::deviceId($fp, 'Mozilla/5.0 (Linux; Android 14)');

        $this->assertSame($dev1, $dev2);
        $this->assertStringStartsWith('DEV_', $dev1);

        // Different browser cookie must NOT change device when fingerprint is same.
        $fpB = PaidDeviceFingerprinter::fingerprintId('canvas:abc|webgl:xyz', 'BROWSER_B_RESET', 'Mozilla/5.0 (Linux; Android 14)', 'en-US');
        $this->assertSame($fp, $fpB);
        $this->assertSame($dev1, PaidDeviceFingerprinter::deviceId($fpB, 'Mozilla/5.0 (Linux; Android 14)'));
    }

    public function test_fingerprint_similarity_threshold(): void
    {
        $this->assertSame(1.0, PaidDeviceFingerprinter::similarity('same', 'same'));
        $this->assertTrue(PaidDeviceFingerprinter::isHighSimilarity(0.96));
        $this->assertFalse(PaidDeviceFingerprinter::isHighSimilarity(0.5));
    }

    public function test_repeat_rules_include_15m_8_24h_persistent(): void
    {
        $identity = new ResolvedPaidIdentity(
            publicId: 'PID_T',
            visitorId: 'V',
            browserId: 'B',
            deviceId: 'D',
            fingerprintId: 'F',
            confidence: 0.9,
            confidenceBand: 'high',
        );

        $windows = [
            'ip' => ['60m' => 0],
            'browser' => ['5m' => 0, '15m' => 2, '60m' => 2, '24h' => 7, '7d' => 19],
            'device' => ['5m' => 0, '15m' => 2, '60m' => 2, '24h' => 7, '7d' => 19],
            'paid_identity' => ['5m' => 0, '15m' => 2, '60m' => 2, '24h' => 7, '7d' => 19],
        ];

        $rules = (new AdsRepeatEvaluator)->evaluate($identity, $windows, [], [
            'repeat_days' => 3,
        ]);
        $codes = array_column($rules, 'rule_code');

        $this->assertContains('ADS_REPEAT_3_15M', $codes);
        $this->assertContains('ADS_REPEAT_8_24H', $codes);
        $this->assertContains('ADS_REPEAT_20_7D', $codes);
        $this->assertContains('ADS_PERSISTENT_REPEAT', $codes);

        $eight = collect($rules)->firstWhere('rule_code', 'ADS_REPEAT_8_24H');
        $this->assertFalse((bool) $eight['can_block_alone']);
    }

    public function test_ip_only_three_clicks_cannot_block_alone_when_identities_differ(): void
    {
        $identity = new ResolvedPaidIdentity(
            publicId: 'PID_L',
            visitorId: 'V1',
            browserId: 'B1',
            deviceId: 'D1',
            fingerprintId: 'F1',
            confidence: 0.55,
            confidenceBand: 'low',
        );

        $windows = [
            'ip' => ['60m' => 2],
            'browser' => ['5m' => 0, '15m' => 0, '60m' => 0, '24h' => 0, '7d' => 0],
            'device' => ['5m' => 0, '15m' => 0, '60m' => 0, '24h' => 0, '7d' => 0],
            'paid_identity' => ['5m' => 0, '15m' => 0, '60m' => 0, '24h' => 0, '7d' => 0],
        ];

        $rules = (new AdsRepeatEvaluator)->evaluate($identity, $windows);
        $codes = array_column($rules, 'rule_code');
        $this->assertContains('ADS_IP_3_60M', $codes);
        $this->assertFalse(collect($rules)->contains(fn ($r) => $r['can_block_alone']));
    }
}
