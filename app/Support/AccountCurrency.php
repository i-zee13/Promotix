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
            'PKR' => '₨',
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

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $code);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return self::symbol($code).number_format($amount, 2);
    }
}
