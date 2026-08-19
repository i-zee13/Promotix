<?php

namespace App\Support;

class CountryFlag
{
    public static function iso2(?string $country): ?string
    {
        $raw = trim((string) $country);
        if ($raw === '' || $raw === '—' || strcasecmp($raw, 'unknown') === 0) {
            return null;
        }

        if (strlen($raw) === 2 && ctype_alpha($raw)) {
            return strtoupper($raw);
        }

        return self::nameMap()[strtolower($raw)] ?? null;
    }

    public static function url(?string $country): ?string
    {
        $iso = self::iso2($country);
        if ($iso === null) {
            return null;
        }

        return '/media/flags/'.strtolower($iso);
    }

    /** @return array<string, string> */
    public static function nameMap(): array
    {
        return [
            'united states' => 'US',
            'usa' => 'US',
            'united kingdom' => 'GB',
            'uk' => 'GB',
            'great britain' => 'GB',
            'germany' => 'DE',
            'india' => 'IN',
            'singapore' => 'SG',
            'russia' => 'RU',
            'canada' => 'CA',
            'france' => 'FR',
            'brazil' => 'BR',
            'pakistan' => 'PK',
            'china' => 'CN',
            'australia' => 'AU',
            'netherlands' => 'NL',
            'japan' => 'JP',
            'united arab emirates' => 'AE',
            'uae' => 'AE',
            'mexico' => 'MX',
            'dominican republic' => 'DO',
            'spain' => 'ES',
            'italy' => 'IT',
            'turkey' => 'TR',
            'indonesia' => 'ID',
            'south korea' => 'KR',
            'korea' => 'KR',
            'saudi arabia' => 'SA',
            'south africa' => 'ZA',
            'nigeria' => 'NG',
            'bangladesh' => 'BD',
            'philippines' => 'PH',
            'vietnam' => 'VN',
            'thailand' => 'TH',
            'malaysia' => 'MY',
            'poland' => 'PL',
            'sweden' => 'SE',
            'norway' => 'NO',
            'denmark' => 'DK',
            'finland' => 'FI',
            'ireland' => 'IE',
            'switzerland' => 'CH',
            'austria' => 'AT',
            'belgium' => 'BE',
            'portugal' => 'PT',
            'greece' => 'GR',
            'egypt' => 'EG',
            'israel' => 'IL',
            'new zealand' => 'NZ',
            'argentina' => 'AR',
            'colombia' => 'CO',
            'chile' => 'CL',
            'peru' => 'PE',
            'ukraine' => 'UA',
            'romania' => 'RO',
            'hungary' => 'HU',
            'czech republic' => 'CZ',
            'czechia' => 'CZ',
        ];
    }
}
