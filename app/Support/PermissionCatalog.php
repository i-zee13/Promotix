<?php

namespace App\Support;

use Illuminate\Support\Str;

class PermissionCatalog
{
    /**
     * Friendly label for roles UI / seeding (disambiguates duplicate menu names).
     */
    public static function displayName(string $slug, ?string $fallbackName = null): string
    {
        $menuLabel = self::menuLabelForSlug($slug);
        if ($menuLabel === null) {
            return $fallbackName !== null && $fallbackName !== ''
                ? $fallbackName
                : Str::headline(str_replace('-', ' ', $slug));
        }

        $group = PermissionGrouper::groupLabelForSlug($slug);
        if ($group === null) {
            return $menuLabel;
        }

        if (self::needsGroupPrefix($menuLabel)) {
            return self::shortGroupName($group).' · '.$menuLabel;
        }

        return $menuLabel;
    }

    private static function needsGroupPrefix(string $menuLabel): bool
    {
        return in_array(strtolower(trim($menuLabel)), [
            'dashboard',
            'advanced view',
            'overview',
        ], true);
    }

    private static function shortGroupName(string $groupLabel): string
    {
        return match (strtoupper(trim($groupLabel))) {
            'PAID ADVERTISING' => 'Paid Ads',
            'BOT PROTECTION' => 'Bot Protection',
            'SITE MANAGEMENT' => 'Site',
            'HOME' => 'Home',
            default => Str::title(strtolower($groupLabel)),
        };
    }

    private static function menuLabelForSlug(string $slug): ?string
    {
        $menu = (array) config('admin.menu', []);
        if (isset($menu[$slug]['label'])) {
            return (string) $menu[$slug]['label'];
        }

        foreach ((array) config('admin.groups', []) as $group) {
            foreach ((array) ($group['items'] ?? []) as $itemSlug => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $permissionSlug = (string) ($item['permission'] ?? $itemSlug);
                if ($permissionSlug === $slug && isset($item['label'])) {
                    return (string) $item['label'];
                }
            }
        }

        return null;
    }
}
