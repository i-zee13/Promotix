<?php

namespace Tests\Unit;

use App\Support\DashboardNotifications;
use Tests\TestCase;

class DashboardNotificationsScopeTest extends TestCase
{
    public function test_blocked_hits_are_scoped_to_the_users_visits_not_global_ip_logs(): void
    {
        $source = file_get_contents((new \ReflectionClass(DashboardNotifications::class))->getFileName());

        $this->assertStringNotContainsString('IpLog::query()', $source);
        $this->assertStringContainsString("whereIn('domain_id', \$domainIds)", $source);
        $this->assertStringContainsString("where('action_taken', 'block')", $source);
    }
}
