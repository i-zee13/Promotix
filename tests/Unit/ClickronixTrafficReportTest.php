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

    public function test_group_export_matches_sheet_keys(): void
    {
        $keys = ClickronixTrafficReport::resolveExportKeys('paid_identity', null);

        $this->assertSame([
            'ip',
            'paid_identity_id',
            'visitor_id',
            'device_id',
            'browser_id',
            'fingerprint_id',
            'device_fingerprint',
            'session_id',
            'identity_confidence',
        ], $keys);

        $headers = ClickronixTrafficReport::headersForKeys($keys);
        $this->assertSame('IP Address', $headers[0]);
        $this->assertSame('Device ID', $headers[3]);
        $this->assertSame('Identity Confidence', $headers[8]);

        $values = ClickronixTrafficReport::valuesForKeys([
            'ip' => '8.8.8.8',
            'device_id' => 'DEV_abc',
            'identity_confidence' => 'High',
            'paid_identity_id' => 'pid-1',
        ], $keys);

        $this->assertSame('8.8.8.8', $values[0]);
        $this->assertSame('pid-1', $values[1]);
        $this->assertSame('DEV_abc', $values[3]);
        $this->assertSame('High', $values[8]);
    }

    public function test_explicit_columns_override_group(): void
    {
        $keys = ClickronixTrafficReport::resolveExportKeys('attribution', 'ip,device_id,gclid');

        $this->assertSame(['ip', 'device_id', 'gclid'], $keys);
        $this->assertNull(ClickronixTrafficReport::resolveExportKeys(null, null));
    }

    public function test_all_columns_option_exports_the_complete_catalog(): void
    {
        $keys = ClickronixTrafficReport::resolveExportKeys(null, 'all');

        $this->assertSame(array_keys(ClickronixTrafficReport::COLUMN_LABELS), $keys);
        $this->assertContains('paid_identity_id', $keys);
        $this->assertContains('session_recording', $keys);
        $this->assertContains('browser_version', $keys);
        $this->assertContains('intel_cloud_provider', $keys);
        $this->assertContains('ads_primary_rule', $keys);
        $this->assertContains('last_path', $keys);
        $this->assertContains('session_count', $keys);
        $this->assertSame('Yes', ClickronixTrafficReport::valueForKey([
            'session_recording_id' => 123,
        ], 'session_recording'));
    }
}
