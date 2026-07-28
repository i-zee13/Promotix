<?php

namespace App\Support;

class Branding
{
    /** @return array<string, string|null> */
    public static function cssVars(): array
    {
        $primary = self::sanitizeColor(app_setting('branding.color_primary', '#6400B2'), '#6400B2');
        $secondary = self::sanitizeColor(app_setting('branding.color_secondary', '#4D008E'), '#4D008E');
        $background = self::sanitizeColor(app_setting('branding.color_background', '#0d0d0d'), '#0d0d0d');
        $surface = self::sanitizeColor(app_setting('branding.color_surface', '#212121'), '#212121');
        $text = self::sanitizeColor(app_setting('branding.color_text', '#FFFFFF'), '#FFFFFF');
        $textMuted = self::sanitizeColor(app_setting('branding.color_text_muted', '#A9A9A9'), '#A9A9A9');
        $outline = self::sanitizeColor(app_setting('branding.color_outline', '#3D3D3D'), '#3D3D3D');
        $cta = self::sanitizeColor(app_setting('branding.color_cta', '#FFFFFF'), '#FFFFFF');
        $ctaText = self::sanitizeColor(app_setting('branding.color_cta_text', '#111111'), '#111111');

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'background' => $background,
            'surface' => $surface,
            'text' => $text,
            'text_muted' => $textMuted,
            'outline' => $outline,
            'cta' => $cta,
            'cta_text' => $ctaText,
            'company_name' => (string) (app_setting('branding.company_name', config('app.name', 'Promotix')) ?: config('app.name', 'Promotix')),
            'logo_url' => self::nullableString(app_setting('branding.logo_url')),
            'support_email' => self::nullableString(app_setting('branding.support_email')),
            'font_family' => self::nullableString(app_setting('branding.font_family')) ?: 'Inter',
            'font_size_base' => (string) (app_setting('branding.font_size_base', 16) ?: 16),
        ];
    }

    public static function rootStyleBlock(): string
    {
        $b = self::cssVars();
        $primaryRgb = self::hexToRgb($b['primary']) ?? '100, 0, 178';
        $fontSize = is_numeric($b['font_size_base']) ? ((int) $b['font_size_base']).'px' : '16px';

        return ':root{'
            ."--brand-primary:{$b['primary']};"
            ."--brand-secondary:{$b['secondary']};"
            ."--brand-background:{$b['background']};"
            ."--brand-surface:{$b['surface']};"
            ."--brand-text:{$b['text']};"
            ."--brand-text-muted:{$b['text_muted']};"
            ."--brand-outline:{$b['outline']};"
            ."--brand-cta:{$b['cta']};"
            ."--brand-cta-text:{$b['cta_text']};"
            ."--brand-primary-rgb:{$primaryRgb};"
            ."--brand-input-bg:color-mix(in srgb, {$b['surface']} 62%, {$b['background']});"
            ."--brand-glow:color-mix(in srgb, {$b['primary']} 45%, transparent);"
            ."--brand-shadow:color-mix(in srgb, {$b['primary']} 28%, transparent);"
            ."--brand-font-family:{$b['font_family']},ui-sans-serif,system-ui,sans-serif;"
            ."--brand-font-size:{$fontSize};"
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
