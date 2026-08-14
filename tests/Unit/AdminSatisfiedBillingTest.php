<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\User;
use Mockery;
use Tests\TestCase;

class AdminSatisfiedBillingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admin_created_subscription_skips_card_onboarding(): void
    {
        $subscription = new Subscription([
            'status' => 'active',
            'metadata' => ['source' => 'super_admin_create_user'],
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('activeSubscription')->andReturn($subscription);
        $user->shouldReceive('hasPaymentMethodOnFile')->andReturn(false);

        $this->assertTrue($user->hasAdminSatisfiedBilling());
        $this->assertFalse($user->needsCardOnboarding());
    }

    public function test_admin_assigned_plan_skips_card_onboarding(): void
    {
        $subscription = new Subscription([
            'status' => 'active',
            'metadata' => ['source' => 'super_admin_assign_plan'],
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('activeSubscription')->andReturn($subscription);
        $user->shouldReceive('hasPaymentMethodOnFile')->andReturn(false);

        $this->assertFalse($user->needsCardOnboarding());
    }

    public function test_self_serve_subscription_still_needs_card(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPaymentMethodOnFile')->andReturn(false);
        $user->shouldReceive('hasAdminSatisfiedBilling')->andReturn(false);

        $this->assertTrue($user->needsCardOnboarding());
    }
}
