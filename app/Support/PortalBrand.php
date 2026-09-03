<?php

namespace App\Support;

use Illuminate\Support\Str;

class PortalBrand
{
    public static function name(?string $host = null): string
    {
        $brand = self::match($host);

        return $brand['name'] ?? (string) config('app.name', 'Digital Promotix');
    }

    public static function slug(?string $host = null): string
    {
        $brand = self::match($host);
        if ($brand !== null) {
            return Str::slug((string) $brand['name']);
        }

        return 'promotix';
    }

    /**
     * @return array{dark: string, light: string}|null
     */
    public static function logoUrls(?string $host = null): ?array
    {
        $brand = self::match($host);
        if ($brand === null) {
            return self::filePair('images/logo-dark.png', 'images/logo-light.png')
                ?? self::filePair('images/clickronix-logo-dark.png', 'images/clickronix-logo-light.png');
        }

        return self::filePair($brand['logo_dark'] ?? null, $brand['logo_light'] ?? null);
    }

    /**
     * Rewrite product-specific names in Copilot / FAQ copy to the current host brand.
     */
    public static function localizeCopy(string $text, ?string $host = null): string
    {
        $name = self::name($host);
        if ($name === '') {
            return $text;
        }

        return (string) str_ireplace(
            ['Clickronix', 'ClickRonix', 'CLICKRONIX', 'ClickGuard', 'Click Guard', 'CLICKGUARD'],
            $name,
            $text
        );
    }

    /**
     * @return array{name: string, logo_dark?: ?string, logo_light?: ?string}|null
     */
    private static function match(?string $host): ?array
    {
        $host = strtolower($host ?? (string) (request()?->getHost() ?? ''));
        if ($host === '') {
            return null;
        }

        foreach ((array) config('portal-brand.hosts', []) as $needle => $brand) {
            if (! is_string($needle) || $needle === '' || ! is_array($brand)) {
                continue;
            }
            if (str_contains($host, strtolower($needle))) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * @return array{dark: string, light: string}|null
     */
    private static function filePair(mixed $darkRel, mixed $lightRel): ?array
    {
        $dark = self::publicUrl(is_string($darkRel) ? $darkRel : null);
        $light = self::publicUrl(is_string($lightRel) ? $lightRel : null);
        if ($dark === null && $light === null) {
            return null;
        }

        return [
            'dark' => $dark ?? $light,
            'light' => $light ?? $dark,
        ];
    }

    private static function publicUrl(?string $relative): ?string
    {
        $relative = ltrim((string) $relative, '/');
        if ($relative === '') {
            return null;
        }
        $path = public_path($relative);
        if (! is_file($path)) {
            return null;
        }

        return url('/'.$relative).'?v='.filemtime($path);
    }
}
