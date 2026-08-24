<?php

namespace Tests\Unit;

use App\Support\PermissionCatalog;
use Tests\TestCase;

class PermissionCatalogTest extends TestCase
{
    public function test_disambiguates_duplicate_dashboard_labels(): void
    {
        $this->assertSame(
            'Paid Ads Dashboard',
            PermissionCatalog::displayName('paid-marketing-dashboard', 'Dashboard'),
        );
        $this->assertSame(
            'Analytics Dashboard',
            PermissionCatalog::displayName('bot-protection', 'Dashboard'),
        );
    }

    public function test_falls_back_to_headline_for_orphan_slug(): void
    {
        $this->assertSame(
            'Traffic Bot Logs',
            PermissionCatalog::displayName('traffic-bot-logs'),
        );
    }
}
