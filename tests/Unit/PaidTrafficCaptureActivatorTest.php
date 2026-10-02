<?php

namespace Tests\Unit;

use App\Support\GoogleClickAttribution;
use App\Support\PaidTrafficCaptureActivator;
use Tests\TestCase;

class PaidTrafficCaptureActivatorTest extends TestCase
{
    public function test_activator_is_noop_without_ads_link(): void
    {
        $domain = new \App\Models\Domain([
            'hostname' => 'example.test',
            'source' => 'manual',
            'google_ads_account_id' => null,
        ]);
        $domain->id = 91001;

        $result = (new PaidTrafficCaptureActivator)->activateDomain($domain);

        $this->assertSame(['visits_marked' => 0, 'clicks_created' => 0], $result);
    }

    public function test_tracking_controller_never_skips_google_click_id_as_organic(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\App\Http\Controllers\TrackingController::class))->getFileName()
        );

        $this->assertStringContainsString(
            '$isPaidTraffic || $hasPaidClickId',
            $source
        );
        $this->assertTrue(GoogleClickAttribution::isPaidTraffic(['gclid' => 'abc']));
    }

    public function test_paid_dashboard_surfaces_prelink_click_id_visits(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\App\Http\Controllers\Admin\PaidAdvertisingDashboardController::class))->getFileName()
        );

        $this->assertStringContainsString('activatePreLinkPaidCaptures', $source);
        $this->assertStringContainsString('applyHasClickIdFilter', $source);
        $this->assertStringContainsString("where('is_paid_traffic', true)", $source);
    }
}
