<?php

namespace Tests\Unit;

use App\Support\AnalyticsDataPipeline;
use PHPUnit\Framework\TestCase;

class AnalyticsDataPipelineTest extends TestCase
{
    public function test_recommended_flow_has_eight_stages(): void
    {
        $this->assertCount(8, AnalyticsDataPipeline::STAGES);
        $this->assertSame([
            'tracking_tag',
            'event_collector',
            'session_service',
            'enrichment',
            'analytics_store',
            'analytics_dashboard',
            'traffic_control',
            'reports',
        ], AnalyticsDataPipeline::stageKeys());
    }
}
