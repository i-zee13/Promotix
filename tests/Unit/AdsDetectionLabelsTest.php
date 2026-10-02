<?php

namespace Tests\Unit;

use App\Support\PaidAdvertising\AdsDetectionLabels;
use PHPUnit\Framework\TestCase;

class AdsDetectionLabelsTest extends TestCase
{
    public function test_maps_known_codes_to_facing_names(): void
    {
        $this->assertSame('Same Device Detected', AdsDetectionLabels::label('ADS_FP_REMATCH'));
        $this->assertSame('Duplicate Ad Click ID', AdsDetectionLabels::label('ADS_GCLID_DUP'));
        $this->assertSame('Repeated Ad Clicks', AdsDetectionLabels::label('ADS_REPEAT_3_15M'));
        $this->assertSame('Repeated Ad Clicks', AdsDetectionLabels::label('ADS_REPEAT_3_5M'));
        $this->assertSame('Recurring Ad Visits', AdsDetectionLabels::label('ADS_PERSISTENT_REPEAT'));
        $this->assertSame('Unusual Activity', AdsDetectionLabels::label('Abnormal Rate'));
    }

    public function test_maps_compound_cells(): void
    {
        $this->assertSame('Unusual Activity, Repeated Ad Clicks', AdsDetectionLabels::label('Abnormal Rate, Repeated'));
    }

    public function test_unknown_ads_repeat_prefix_still_maps(): void
    {
        $this->assertSame('Repeated Ad Clicks', AdsDetectionLabels::label('ADS_REPEAT_99_99M'));
    }
}
