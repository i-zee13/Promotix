<?php

namespace Tests\Unit;

use App\Support\ClickronixTrafficReport;
use App\Support\SettingsDataReportCatalog;
use PHPUnit\Framework\TestCase;

class SettingsDataReportCatalogTest extends TestCase
{
    public function test_report_types_match_spec(): void
    {
        $ids = array_column(SettingsDataReportCatalog::reportTypeOptions(), 'id');
        $this->assertSame([
            'paid_advertising',
            'analytics_dashboard',
            'traffic_control',
            'detection_session',
        ], $ids);
    }

    public function test_column_groups_match_spec(): void
    {
        $ids = array_column(SettingsDataReportCatalog::columnGroupOptions(), 'id');
        $this->assertSame([
            'traffic',
            'conversion',
            'device',
            'fraud_risk',
            'revenue',
            'events',
        ], $ids);
    }

    public function test_normalizes_legacy_group_ids(): void
    {
        $this->assertSame('paid_advertising', SettingsDataReportCatalog::normalizeType('advanced'));
        $this->assertSame('analytics_dashboard', SettingsDataReportCatalog::normalizeType('bot'));
        $this->assertSame('traffic_control', SettingsDataReportCatalog::normalizeType('bot_advanced'));
    }

    public function test_clickronix_resolves_settings_column_groups(): void
    {
        $keys = ClickronixTrafficReport::resolveExportKeys('fraud_risk', null);
        $this->assertNotNull($keys);
        $this->assertContains('ip', $keys);
        $this->assertContains('intel_risk_score', $keys);
        $this->assertSame('Fraud / Risk', ClickronixTrafficReport::groupLabel('fraud_risk'));
    }

    public function test_normalize_format(): void
    {
        $this->assertSame('pdf', SettingsDataReportCatalog::normalizeFormat('PDF'));
        $this->assertSame('xlsx', SettingsDataReportCatalog::normalizeFormat('xlsx'));
        $this->assertSame('csv', SettingsDataReportCatalog::normalizeFormat('nope'));
    }
}
