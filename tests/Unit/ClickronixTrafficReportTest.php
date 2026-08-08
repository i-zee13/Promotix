<?php

namespace Tests\Unit;

use App\Support\ClickronixTrafficReport;
use PHPUnit\Framework\TestCase;

class ClickronixTrafficReportTest extends TestCase
{
    public function test_headers_match_template_column_count(): void
    {
        $headers = ClickronixTrafficReport::headers();

        $this->assertCount(42, $headers);
        $this->assertSame('IP Address', $headers[0]);
        $this->assertSame('Total CTA Clicks', $headers[27]);
        $this->assertSame('Total Tel Clicks', $headers[28]);
        $this->assertSame('Checked At', $headers[41]);
    }

    public function test_values_align_with_headers(): void
    {
        $row = ClickronixTrafficReport::valuesFromDetailedVisit([
            'ip' => '1.2.3.4',
            'visits' => 3,
            'invalid_clicks' => 1,
            'valid_clicks' => 2,
            'google_verified_label' => 'Yes',
            'intel_risk_level' => 'High',
            'intel_risk_score' => 88,
            'status' => 'Blocked',
            'intel_block_reason' => 'vpn',
            'device_fingerprint' => 'abc',
            'session_count' => 2,
            'session_id' => 's1',
            'device' => 'Mobile',
            'browser' => 'Chrome',
            'os' => 'iOS',
            'cta_clicks' => 4,
            'tel_clicks' => 1,
            'page_changes' => 2,
            'last_path' => '/thanks',
            'last_cta' => 'tel:+15551212',
            'intel_vpn' => 'Yes',
            'intel_proxy' => 'No',
            'intel_tor' => 'No',
            'intel_datacenter' => 'No',
        ]);

        $this->assertCount(count(ClickronixTrafficReport::headers()), $row);
        $this->assertSame('1.2.3.4', $row[0]);
        $this->assertSame(4, $row[27]);
        $this->assertSame(1, $row[28]);
        $this->assertSame('tel:+15551212', $row[40]);
    }
}
