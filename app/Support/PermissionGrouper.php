<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PermissionGrouper
{
    public const ADMIN_OPS_GROUP = 'Admin & Operations';

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, Collection<int, Permission>>
     */
    public static function group(Collection $permissions): array
    {
        $slugToGroup = self::slugToGroupMap();
        $buckets = [];

        foreach ($permissions->sortBy(fn (Permission $p) => PermissionCatalog::displayName($p->slug, $p->name)) as $permission) {
            $group = $slugToGroup[$permission->slug] ?? self::ADMIN_OPS_GROUP;
            $buckets[$group] ??= collect();
            $buckets[$group]->push($permission);
        }

        $ordered = [];
        foreach (self::groupOrder() as $label) {
            if (isset($buckets[$label]) && $buckets[$label]->isNotEmpty()) {
                $ordered[$label] = $buckets[$label];
                unset($buckets[$label]);
            }
        }

        foreach ($buckets as $label => $items) {
            if ($items->isNotEmpty()) {
                $ordered[$label] = $items;
            }
        }

        return $ordered;
    }

    public static function groupLabelForSlug(string $slug): ?string
    {
        return self::slugToGroupMap()[$slug] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        $labels = collect(config('admin.groups', []))
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        $labels[] = self::ADMIN_OPS_GROUP;

        return $labels;
    }

    /**
     * @return array<string, string> permission slug => group label
     */
    public static function slugToGroupMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach ((array) config('admin.groups', []) as $group) {
            $groupLabel = (string) ($group['label'] ?? '');
            if ($groupLabel === '') {
                continue;
            }

            foreach ((array) ($group['items'] ?? []) as $slug => $item) {
                if (! is_array($item)) {
                    continue;
                }

                // Alias menu entries (e.g. bot-protection-advanced-alias) reuse another permission slug.
                if (str_contains((string) $slug, '-alias')) {
                    continue;
                }

                $permissionSlug = (string) ($item['permission'] ?? $slug);
                $map[$permissionSlug] = $groupLabel;
            }
        }

        return $map;
    }

    /**
     * Fallback grouping for legacy slugs not present in admin.groups.
     */
    public static function legacyGroupLabel(string $slug): string
    {
        if (Str::startsWith($slug, ['users', 'roles'])) {
            return self::ADMIN_OPS_GROUP;
        }

        return self::ADMIN_OPS_GROUP;
    }
}
