<?php

namespace Tests\Unit;

use App\Support\PermissionDescription;
use PHPUnit\Framework\TestCase;

class PermissionDescriptionTest extends TestCase
{
    public function test_known_permission_has_a_specific_access_description(): void
    {
        $this->assertSame(
            'View, filter, export, and investigate individual paid-traffic visits.',
            PermissionDescription::for('paid-marketing-detailed'),
        );
    }

    public function test_unknown_permission_has_a_safe_fallback_description(): void
    {
        $this->assertSame(
            'Allows access to the custom.area area and its permitted actions.',
            PermissionDescription::for('custom-access', 'custom.area'),
        );
    }
}
