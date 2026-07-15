<?php

namespace Tests\Unit;

use App\Http\Controllers\TrackingController;
use ReflectionClass;
use Tests\TestCase;

class TrackingLatencyTest extends TestCase
{
    public function test_tracking_confidence_resolution_is_fast(): void
    {
        $controller = new TrackingController;
        $method = (new ReflectionClass($controller))->getMethod('resolveTrackingConfidence');
        $method->setAccessible(true);

        $start = hrtime(true);
        for ($i = 0; $i < 10000; $i++) {
            $method->invoke($controller, $i % 2 === 0 ? 'tag' : 'noscript');
        }
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        $this->assertLessThan(50, $elapsedMs, 'Confidence resolution should stay well under ingest latency budget');
        $this->assertSame('high', $method->invoke($controller, 'tag'));
        $this->assertSame('reduced', $method->invoke($controller, 'noscript'));
        $this->assertSame('reduced', $method->invoke($controller, 'pixel'));
    }
}
