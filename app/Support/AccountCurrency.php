<?php

namespace App\Support;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AccountCurrency
{
    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return strlen($code) === 3 ? $code : 'USD';
    }

    public static function fromDomain(?Domain $domain): string
    {
        return self::normalize($domain?->googleAdsAccount?->currency_code);
    }

    /**
     * @param  Collection<int, Domain>|iterable<Domain>  $domains
     */
    public static function resolveForRequest(Request $request, iterable $domains): string
    {
        $domains = $domains instanceof Collection ? $domains : collect($domains);
        $selectedId = (int) $request->query('domain_id', 0);

        if ($selectedId > 0) {
            $domain = $domains->firstWhere('id', $selectedId)
                ?? Domain::query()->with('googleAdsAccount')->find($selectedId);

            return self::fromDomain($domain);
        }

        foreach ($domains as $domain) {
            $code = trim((string) ($domain->googleAdsAccount?->currency_code ?? ''));
            if ($code !== '') {
                return self::normalize($code);
            }
        }

        return 'USD';
    }

    public static function symbol(string $currencyCode): string
    {
        return match (self::normalize($currencyCode)) {
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'NZD' => 'NZ$',
            'INR' => '₹',
            'PKR' => 'Rs ',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'JPY' => '¥',
            'CNY' => '¥',
            'CHF' => 'CHF ',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'ZAR' => 'R',
            'BRL' => 'R$',
            'MXN' => 'MX$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            default => strtoupper($currencyCode).' ',
        };
    }

    public static function label(string $currencyCode): string
    {
        $code = self::normalize($currencyCode);

        return self::symbol($code).' '.$code;
    }

    public static function formatAmount(float $amount, string $currencyCode = 'USD'): string
    {
        $code = self::normalize($currencyCode);

        if (class_exists(\NumberFormatter::class) && ! in_array($code, ['PKR'], true)) {
            $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $code);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return self::symbol($code).number_format($amount, 2);
    }

    /** Compact display for dashboard cards (e.g. Rs 1.79K). */
    public static function formatCompact(float $amount, string $currencyCode = 'USD'): string
    {
        $symbol = self::symbol($currencyCode);
        $abs = abs($amount);
        if ($abs >= 1_000_000) {
            return $symbol.rtrim(rtrim(number_format($amount / 1_000_000, 2), '0'), '.').'M';
        }
        if ($abs >= 1_000) {
            return $symbol.rtrim(rtrim(number_format($amount / 1_000, 2), '0'), '.').'K';
        }

        return $symbol.number_format($amount, 2);
    }
}
