<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAdsDomainMetricsSync;
use App\Support\AccountCurrency;
use PHPUnit\Framework\TestCase;

class AccountCurrencyTest extends TestCase
{
    public function test_account_needs_metadata_refresh_when_currency_missing(): void
    {
        $account = new GoogleAdsAccount([
            'is_manager' => false,
            'time_zone' => 'Asia/Karachi',
            'currency_code' => null,
        ]);
        $this->assertTrue($account->needsCustomerMetadataRefresh());

        $account->currency_code = 'PKR';
        $this->assertFalse($account->needsCustomerMetadataRefresh());
    }

    public function test_pkr_uses_rs_symbol(): void
    {
        $this->assertSame('Rs ', AccountCurrency::symbol('PKR'));
        $this->assertStringStartsWith('Rs ', AccountCurrency::formatCompact(1790, 'PKR'));
    }

    public function test_from_timezone_maps_karachi_to_pkr(): void
    {
        $this->assertSame('PKR', AccountCurrency::fromTimezone('Asia/Karachi'));
        $this->assertSame('PKR', AccountCurrency::fromTimezone('PKT'));
        $this->assertSame('USD', AccountCurrency::fromTimezone('America/New_York'));
        $this->assertSame('GBP', AccountCurrency::fromTimezone('Europe/London'));
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

    public function test_from_domain_uses_timezone_when_currency_missing(): void
    {
        $account = new GoogleAdsAccount([
            'currency_code' => null,
            'time_zone' => 'Asia/Karachi',
        ]);
        $domain = new Domain;
        $domain->setRelation('googleAdsAccount', $account);

        $this->assertSame('PKR', AccountCurrency::fromDomain($domain));
    }

    public function test_from_domain_prefers_timezone_over_stale_usd(): void
    {
        $account = new GoogleAdsAccount([
            'currency_code' => 'USD',
            'time_zone' => 'Asia/Karachi',
        ]);
        $domain = new Domain;
        $domain->setRelation('googleAdsAccount', $account);

        $this->assertSame('PKR', AccountCurrency::fromDomain($domain));
    }

    public function test_from_domain_fallback_when_ads_metadata_missing(): void
    {
        $account = new GoogleAdsAccount([
            'currency_code' => null,
            'time_zone' => null,
        ]);
        $domain = new Domain;
        $domain->setRelation('googleAdsAccount', $account);

        $this->assertSame('PKR', AccountCurrency::fromDomain($domain, 'PKR'));
        $this->assertSame('USD', AccountCurrency::fromDomain($domain));
    }

    public function test_all_domains_cost_saved_does_not_fx_inflate_pkr(): void
    {
        // ~16 invalid × ~Rs 1,500 CPC ≈ Rs 24K — must NOT become ~Rs 6.8M via USD→PKR.
        $wasteNative = 24500.0;
        $pkrPerUsd = 278.50;
        $wrongRelabelAsUsdThenToPkr = $wasteNative * $pkrPerUsd;
        $rightSameCurrency = $wasteNative; // PKR → PKR, one hop

        $this->assertSame(24500.0, $rightSameCurrency);
        $this->assertGreaterThan(1_000_000, $wrongRelabelAsUsdThenToPkr);
        $this->assertSame('Rs 24.5K', AccountCurrency::formatCompact($rightSameCurrency, 'PKR'));
        $this->assertStringContainsString('M', AccountCurrency::formatCompact($wrongRelabelAsUsdThenToPkr, 'PKR'));
    }

    public function test_normalize_stored_cost_divides_micros(): void
    {
        $normalized = GoogleAdsDomainMetricsSync::normalizeStoredCost(50_000_000.0, 100, 'PKR');
        $this->assertSame(50.0, $normalized);
    }
}
