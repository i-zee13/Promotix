<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

final class GoogleClickAttribution
{
    /** @var list<string> */
    public const CLICK_ID_COLUMNS = ['gclid', 'gbraid', 'wbraid'];

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: string, type: string}|null
     */
    public static function resolve(array $data): ?array
    {
        foreach (self::CLICK_ID_COLUMNS as $type) {
            $value = trim((string) ($data[$type] ?? ''));
            if ($value !== '') {
                return ['id' => $value, 'type' => $type];
            }
        }

        return null;
    }

    /**
     * Paid marketing funnel is strictly click-ID based. A campaign ID alone is
     * attribution metadata, not proof of a paid click.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isPaidTraffic(array $data, ?int $domainId = null): bool
    {
        return self::resolve($data) !== null;
    }

    /**
     * Paid marketing visits: gclid / gbraid / wbraid only.
     */
    public static function applyHasClickIdFilter(Builder $query, string $prefix = ''): void
    {
        $query->where(function (Builder $group) use ($prefix): void {
            $p = $prefix !== '' ? $prefix . '.' : '';
            $added = false;

            foreach (self::CLICK_ID_COLUMNS as $column) {
                if (! Schema::hasColumn('visits', $column)) {
                    continue;
                }

                $clause = function (Builder $inner) use ($p, $column): void {
                    $inner->whereNotNull($p . $column)->where($p . $column, '!=', '');
                };

                if ($added) {
                    $group->orWhere($clause);
                } else {
                    $group->where($clause);
                    $added = true;
                }
            }

            if (! $added) {
                $group->whereRaw('0 = 1');
            }
        });
    }

    /**
     * Bot protection funnel: exclude paid-marketing click-ID / paid traffic.
     */
    public static function excludeClickIds(Builder $query, string $prefix = ''): void
    {
        $p = $prefix !== '' ? $prefix . '.' : '';

        foreach (self::CLICK_ID_COLUMNS as $column) {
            if (! Schema::hasColumn('visits', $column)) {
                continue;
            }

            $query->where(function (Builder $group) use ($p, $column): void {
                $group->whereNull($p . $column)->orWhere($p . $column, '');
            });
        }

    }

    /**
     * Legacy paid_marketing_clicks rows keyed by paid_id (gclid family).
     */
    public static function applyPaidClickIdFilter(Builder $query, string $paidIdColumn = 'paid_id'): void
    {
        $query->whereNotNull($paidIdColumn)->where($paidIdColumn, '!=', '');
    }

    /**
     * SQL expression for the resolved Google click ID on a visits row.
     */
    public static function distinctClickIdSql(string $prefix = ''): string
    {
        $p = $prefix !== '' ? $prefix . '.' : '';
        $parts = [];

        foreach (self::CLICK_ID_COLUMNS as $column) {
            if (Schema::hasColumn('visits', $column)) {
                $parts[] = "NULLIF({$p}{$column}, '')";
            }
        }

        if ($parts === []) {
            return "''";
        }

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function clickIdValue(array $data): string
    {
        $resolved = self::resolve($data);

        return $resolved['id'] ?? '';
    }

    public static function countDistinctClickIds(Builder $query, string $prefix = ''): int
    {
        $expr = self::distinctClickIdSql($prefix);

        return (int) (clone $query)
            ->whereRaw("{$expr} IS NOT NULL")
            ->selectRaw("COUNT(DISTINCT {$expr}) as aggregate")
            ->value('aggregate');
    }
}
