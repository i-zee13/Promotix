<?php

namespace Tests\Unit;

use App\Support\AccountCurrency;
use PHPUnit\Framework\TestCase;

class AccountCurrencyTest extends TestCase
{
    public function test_pkr_uses_rs_symbol(): void
    {
        $this->assertSame('Rs ', AccountCurrency::symbol('PKR'));
        $this->assertStringStartsWith('Rs ', AccountCurrency::formatCompact(1790, 'PKR'));
    }

    public function test_format_compact_thousands(): void
    {
        $this->assertSame('Rs 1.79K', AccountCurrency::formatCompact(1790, 'PKR'));
        $this->assertSame('Rs 224K', AccountCurrency::formatCompact(224000, 'PKR'));
    }

    public function test_cpc_and_cost_per_conversion_formulas(): void
    {
        $googleCost = 224000.0;
        $googleClicks = 125;
        $totalConversions = 24;

        $avgCpc = $googleClicks > 0 ? round($googleCost / $googleClicks, 4) : 0.0;
        $costPerConversion = $totalConversions > 0 ? round($googleCost / $totalConversions, 4) : 0.0;

        $this->assertSame(1792.0, $avgCpc);
        $this->assertSame(9333.3333, $costPerConversion);
        $this->assertSame('Rs 1.79K', AccountCurrency::formatCompact($avgCpc, 'PKR'));
        $this->assertSame('Rs 9.33K', AccountCurrency::formatCompact($costPerConversion, 'PKR'));
    }
}
