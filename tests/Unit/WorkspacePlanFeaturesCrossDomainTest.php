<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\User;
use App\Support\WorkspacePlanFeatures;
use Mockery;
use Tests\TestCase;

class WorkspacePlanFeaturesCrossDomainTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cross_domain_defaults_off_until_plan_flag_set(): void
    {
        $plan = new Plan([
            'name' => 'Enterprise',
            'tier' => 'enterprise',
            'is_custom' => false,
            'feature_flags' => [],
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('bypassesPlanLimits')->andReturn(false);
        $user->shouldReceive('currentPlan')->andReturn($plan);

        $this->assertFalse(WorkspacePlanFeatures::enabled($user, WorkspacePlanFeatures::CROSS_DOMAIN));
    }

    public function test_cross_domain_on_when_plan_flag_enabled(): void
    {
        $plan = new Plan([
            'name' => 'Starter',
            'tier' => 'basic',
            'is_custom' => false,
            'feature_flags' => ['cross_domain' => true],
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('bypassesPlanLimits')->andReturn(false);
        $user->shouldReceive('currentPlan')->andReturn($plan);

        $this->assertTrue(WorkspacePlanFeatures::enabled($user, WorkspacePlanFeatures::CROSS_DOMAIN));
    }
}
