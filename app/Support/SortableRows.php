<?php

namespace App\Support;

/**
 * Shared row sorting for admin tables (client portals + exports).
 */
class SortableRows
{
    /**
     * @param  iterable<int, array<string, mixed>|object>  $rows
     * @return list<array<string, mixed>|object>
     */
    public static function sort(iterable $rows, ?string $key, string $direction = 'asc', array $numericKeys = []): array
    {
        $list = collect($rows)->values()->all();
        $key = trim((string) $key);
        if ($key === '') {
            return $list;
        }

        $dir = strtolower($direction) === 'desc' ? -1 : 1;
        $numeric = array_fill_keys($numericKeys, true);

        usort($list, function ($a, $b) use ($key, $dir, $numeric) {
            $left = self::value($a, $key);
            $right = self::value($b, $key);

            if ($left === null && $right === null) {
                return 0;
            }
            if ($left === null) {
                return 1;
            }
            if ($right === null) {
                return -1;
            }

            if (isset($numeric[$key]) || (is_numeric($left) && is_numeric($right))) {
                $cmp = ((float) $left) <=> ((float) $right);
            } else {
                $cmp = strnatcasecmp((string) $left, (string) $right);
            }

            return $cmp * $dir;
        });

        return $list;
    }

    public static function toggleDirection(?string $currentKey, string $nextKey, string $currentDir = 'asc'): array
    {
        if ($currentKey === $nextKey) {
            return [$nextKey, strtolower($currentDir) === 'asc' ? 'desc' : 'asc'];
        }

        return [$nextKey, 'asc'];
    }

    private static function value(array|object $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
