<?php

namespace App\Support;

use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Convert money using Super Admin → Currency Rates (units per 1 USD).
 */
class CurrencyConverter
{
    /**
     * @return array<string, float> code => units_per_usd
     */
    public static function rateMap(): array
    {
        if (! Schema::hasTable('currency_rates')) {
            return ['USD' => 1.0];
        }

        return Cache::remember('currency_rates:active_map_v1', 300, function () {
            $map = CurrencyRate::query()
                ->where('is_active', true)
                ->pluck('units_per_usd', 'code')
                ->mapWithKeys(fn ($rate, $code) => [strtoupper((string) $code) => max(0.000001, (float) $rate)])
                ->all();

            $map['USD'] = $map['USD'] ?? 1.0;

            return $map;
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget('currency_rates:active_map_v1');
    }

    public static function unitsPerUsd(string $code): float
    {
        $code = AccountCurrency::normalize($code);
        $map = self::rateMap();

        return (float) ($map[$code] ?? 1.0);
    }

    public static function toUsd(float $amount, string $fromCode): float
    {
        $rate = self::unitsPerUsd($fromCode);

        return $rate > 0 ? ($amount / $rate) : $amount;
    }

    public static function fromUsd(float $amountUsd, string $toCode): float
    {
        return $amountUsd * self::unitsPerUsd($toCode);
    }

    public static function convert(float $amount, string $fromCode, string $toCode): float
    {
        $from = AccountCurrency::normalize($fromCode);
        $to = AccountCurrency::normalize($toCode);
        if ($from === $to) {
            return $amount;
        }

        return self::fromUsd(self::toUsd($amount, $from), $to);
    }

    /**
     * Sum Google Ads cost bundles into one display currency (All Domains FX).
     *
     * @param  list<array{currency_code?: string, clicks?: int, cost?: float}>  $bundles
     * @return array{cost: float, clicks: int}
     */
    public static function sumBundlesInCurrency(array $bundles, string $toCode): array
    {
        $cost = 0.0;
        $clicks = 0;
        foreach ($bundles as $bundle) {
            $clicks += (int) ($bundle['clicks'] ?? 0);
            $cost += self::convert(
                (float) ($bundle['cost'] ?? 0),
                (string) ($bundle['currency_code'] ?? 'USD'),
                $toCode,
            );
        }

        return [
            'cost' => round($cost, 2),
            'clicks' => $clicks,
        ];
    }
}
