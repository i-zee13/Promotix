<?php

namespace Tests\Unit;

use App\Support\TotpAuthenticator;
use PHPUnit\Framework\TestCase;

class TotpAuthenticatorTest extends TestCase
{
    public function test_secret_and_code_round_trip(): void
    {
        $secret = TotpAuthenticator::generateSecret();
        $this->assertNotSame('', $secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);

        $code = TotpAuthenticator::currentCode($secret);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue(TotpAuthenticator::verify($secret, $code));
        $this->assertFalse(TotpAuthenticator::verify($secret, '000000'));
    }

    public function test_provisioning_uri_contains_secret(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = TotpAuthenticator::provisioningUri($secret, 'user@example.com', 'Clickronix');
        $this->assertStringContainsString('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
    }
}
