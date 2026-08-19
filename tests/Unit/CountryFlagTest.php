<?php

namespace Tests\Unit;

use App\Support\CountryFlag;
use Tests\TestCase;

class CountryFlagTest extends TestCase
{
    public function test_iso2_accepts_codes_and_common_names(): void
    {
        $this->assertSame('US', CountryFlag::iso2('us'));
        $this->assertSame('US', CountryFlag::iso2('United States'));
        $this->assertSame('GB', CountryFlag::iso2('United Kingdom'));
        $this->assertSame('/media/flags/us', CountryFlag::url('United States'));
        $this->assertNull(CountryFlag::iso2('Unknown'));
        $this->assertNull(CountryFlag::iso2(''));
    }
}
