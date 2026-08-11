<?php

namespace App\Support\PaidAdvertising;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual v3 Step 4: rolling paid-click counters by IP / browser / device / paid identity.
 *
 * Counts are derived from visits (source of truth) and mirrored into click_windows
 * for fast reads. Never counts unfinished organic traffic.
 */
class ClickWindowCounter
{
    /** @var array<string, int> minutes */
    public const WINDOWS = [
        '1m' => 1,
        '5m' => 5,
        '15m' => 15,
        '30m' => 30,
        '60m' => 60,
        '6h' => 360,
        '24h' => 1440,
        '7d' => 10080,
    ];

    /**
     * @return array{
     *   ip: array<string,int>,
     *   browser: array<string,int>,
     *   device: array<string,int>,
     *   paid_identity: array<string,int>
     * }
     */
    public function snapshot(
        int $domainId,
        string $ip,
        ?string $browserId,
        ?string $deviceId,
        ?string $paidIdentityPublicId,
        ?Carbon $now = null,
    ): array {
        $now = $now ?: now();

        return [
            'ip' => $this->countsFor($domainId, 'ip', $ip, $now),
            'browser' => $browserId ? $this->countsFor($domainId, 'browser', $browserId, $now) : $this->empty(),
            'device' => $deviceId ? $this->countsFor($domainId, 'device', $deviceId, $now) : $this->empty(),
            'paid_identity' => $paidIdentityPublicId
                ? $this->countsFor($domainId, 'paid_identity', $paidIdentityPublicId, $now)
                : $this->empty(),
        ];
    }

    /**
     * Record the current paid click into click_windows (best-effort mirror).
     */
    public function recordClick(
        int $domainId,
        string $ip,
        ?string $browserId,
        ?string $deviceId,
        ?string $paidIdentityPublicId,
        ?string $campaign = null,
        ?Carbon $now = null,
    ): void {
        if (! $this->tableReady('click_windows')) {
            return;
        }

        $now = $now ?: now();
        $entities = [
            ['ip', $ip],
            ['browser', $browserId],
            ['device', $deviceId],
            ['paid_identity', $paidIdentityPublicId],
            ['campaign', $campaign],
        ];

        foreach ($entities as [$type, $id]) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            foreach (array_keys(self::WINDOWS) as $windowKey) {
                $this->incrementWindow($domainId, $type, $id, $windowKey, $now);
            }
        }
    }

    /**
     * Distinct calendar days with paid clicks for a device / identity (Manual persistent-repeat).
     */
    public function distinctPaidDays(
        int $domainId,
        ?string $deviceId,
        ?string $paidIdentityPublicId,
        int $lookbackDays = 14,
    ): int {
        if (! $this->tableReady('visits') || ! $this->columnReady('visits', 'visited_at')) {
            return 0;
        }

        $since = now()->subDays(max(1, $lookbackDays));
        $q = DB::table('visits')
            ->where('domain_id', $domainId)
            ->where('is_paid_traffic', true)
            ->where('visited_at', '>=', $since);

        $q->where(function ($match) use ($deviceId, $paidIdentityPublicId): void {
            $has = false;
            if ($deviceId && $this->columnReady('visits', 'device_id')) {
                $match->orWhere('device_id', $deviceId);
                $has = true;
            }
            if ($paidIdentityPublicId && $this->columnReady('visits', 'paid_identity_id')) {
                $match->orWhere('paid_identity_id', $paidIdentityPublicId);
                $has = true;
            }
            if (! $has) {
                $match->whereRaw('1=0');
            }
        });

        return (int) $q->selectRaw('COUNT(DISTINCT DATE(visited_at)) as days')->value('days');
    }

    /**
     * @return array<string, int>
     */
    public function countsFor(int $domainId, string $entityType, string $entityId, Carbon $now): array
    {
        $entityId = trim($entityId);
        if ($entityId === '' || ! $this->tableReady('visits')) {
            return $this->empty();
        }

        $out = $this->empty();
        foreach (self::WINDOWS as $key => $minutes) {
            $out[$key] = $this->visitCount($domainId, $entityType, $entityId, $now->copy()->subMinutes($minutes));
        }

        return $out;
    }

    private function visitCount(int $domainId, string $entityType, string $entityId, Carbon $since): int
    {
        $q = DB::table('visits')
            ->where('domain_id', $domainId)
            ->where('is_paid_traffic', true)
            ->where('visited_at', '>=', $since);

        if ($this->columnReady('visits', 'is_duplicate_paid_click')) {
            $q->where(function ($inner) {
                $inner->whereNull('is_duplicate_paid_click')
                    ->orWhere('is_duplicate_paid_click', false);
            });
        }

        match ($entityType) {
            'ip' => $q->where('ip', $entityId),
            'browser' => $this->columnReady('visits', 'browser_id')
                ? $q->where('browser_id', $entityId)
                : $q->whereRaw('1=0'),
            'device' => $this->columnReady('visits', 'device_id')
                ? $q->where('device_id', $entityId)
                : $q->whereRaw('1=0'),
            'paid_identity' => $this->columnReady('visits', 'paid_identity_id')
                ? $q->where('paid_identity_id', $entityId)
                : $q->whereRaw('1=0'),
            default => $q->whereRaw('1=0'),
        };

        return (int) $q->count();
    }

    private function incrementWindow(
        int $domainId,
        string $entityType,
        string $entityId,
        string $windowKey,
        Carbon $now,
    ): void {
        $minutes = self::WINDOWS[$windowKey] ?? null;
        if ($minutes === null) {
            return;
        }

        $startedAt = $now->copy()->subMinutes($minutes);
        $existing = DB::table('click_windows')
            ->where('domain_id', $domainId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('window_key', $windowKey)
            ->first();

        if (! $existing) {
            DB::table('click_windows')->insert([
                'domain_id' => $domainId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'window_key' => $windowKey,
                'click_count' => 1,
                'window_started_at' => $startedAt,
                'last_click_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        // Refresh count from visits every write so stale mirrors do not drift.
        $fresh = $this->visitCount($domainId, $entityType, $entityId, $startedAt);
        DB::table('click_windows')->where('id', $existing->id)->update([
            'click_count' => max(1, $fresh),
            'window_started_at' => $startedAt,
            'last_click_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function empty(): array
    {
        return array_fill_keys(array_keys(self::WINDOWS), 0);
    }

    private function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnReady(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
