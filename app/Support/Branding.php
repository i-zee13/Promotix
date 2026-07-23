<?php

namespace App\Support;

class Branding
{
    /** @return array{primary: string, secondary: string, background: string, company_name: string, logo_url: ?string, support_email: ?string} */
    public static function cssVars(): array
    {
        $primary = self::sanitizeColor(app_setting('branding.color_primary', '#6400B2'), '#6400B2');
        $secondary = self::sanitizeColor(app_setting('branding.color_secondary', '#4D008E'), '#4D008E');
        $background = self::sanitizeColor(app_setting('branding.color_background', '#0d0d0d'), '#0d0d0d');

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'background' => $background,
            'company_name' => (string) (app_setting('branding.company_name', config('app.name', 'Promotix')) ?: config('app.name', 'Promotix')),
            'logo_url' => self::nullableString(app_setting('branding.logo_url')),
            'support_email' => self::nullableString(app_setting('branding.support_email')),
        ];
    }

    public static function rootStyleBlock(): string
    {
        $b = self::cssVars();
        $primaryRgb = self::hexToRgb($b['primary']) ?? '100, 0, 178';

        return ':root{'
            ."--brand-primary:{$b['primary']};"
            ."--brand-secondary:{$b['secondary']};"
            ."--brand-background:{$b['background']};"
            ."--brand-primary-rgb:{$primaryRgb};"
            ."--auth-brand:{$b['primary']};"
            ."--auth-brand-dark:{$b['secondary']};"
            ."--auth-brand-glow:rgba({$primaryRgb},0.45);"
            ."--auth-brand-ring:rgba({$primaryRgb},0.82);"
            .'}';
    }

    private static function sanitizeColor(mixed $value, string $fallback): string
    {
        $color = trim((string) $value);
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
            return strtoupper(strlen($color) === 4
                ? sprintf('#%s%s%s%s%s%s', $color[1], $color[1], $color[2], $color[2], $color[3], $color[3])
                : $color);
        }

        return strtoupper($fallback);
    }

    /** @return ?string */
    private static function nullableString(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v !== '' ? $v : null;
    }

    private static function hexToRgb(string $hex): ?string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return null;
        }

        return hexdec(substr($hex, 0, 2)).', '.hexdec(substr($hex, 2, 2)).', '.hexdec(substr($hex, 4, 2));
    }
}
