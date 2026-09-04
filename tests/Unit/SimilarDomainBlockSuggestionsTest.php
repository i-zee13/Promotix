<?php

namespace Tests\Unit;

use App\Support\CrossDomainIntel;
use App\Support\SimilarDomainBlockSuggestions;
use Tests\TestCase;

class SimilarDomainBlockSuggestionsTest extends TestCase
{
    public function test_internet_fiber_style_names_score_as_related(): void
    {
        $intel = new CrossDomainIntel;
        $score = $intel->hostnameSimilarity('internetfiber.online', 'internetpowerdeals.online');

        $this->assertGreaterThanOrEqual(0.55, $score);
    }

    public function test_unrelated_hostnames_stay_below_threshold(): void
    {
        $intel = new CrossDomainIntel;
        $score = $intel->hostnameSimilarity('acme-widgets.com', 'purple-bananas.io');

        $this->assertLessThan(0.55, $score);
    }

    public function test_empty_hostname_returns_no_suggestions(): void
    {
        $result = (new SimilarDomainBlockSuggestions)->forHostname('');

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['suggested_ips']);
    }
}
