<?php

namespace App\Support;

use App\Models\GoogleConnection;

class GoogleAdsApiHealth
{
    /**
     * Connection Health ring status: ok | pending | error.
     *
     * OAuth + synced Ads accounts stay healthy even when a later metrics pull
     * saved 0 campaign rows. That used to mark the whole API as failed (75%).
     */
    public static function status(?GoogleConnection $connection, int $syncedAccountCount = 0): string
    {
        if (! $connection) {
            return 'pending';
        }

        $raw = strtolower(trim((string) ($connection->health_status ?: $connection->last_sync_status ?: '')));
        $message = (string) ($connection->last_sync_message ?? '');

        if (in_array($raw, ['error', 'failed'], true) && self::isHardFailure($message)) {
            return 'error';
        }

        if ($syncedAccountCount > 0
            || $connection->last_sync_at
            || in_array($raw, ['ok', 'success', 'healthy', 'connected'], true)) {
            return 'ok';
        }

        return in_array($raw, ['error', 'failed'], true) ? 'error' : 'pending';
    }

    public static function isHardFailure(string $message): bool
    {
        $m = strtolower($message);
        if ($m === '') {
            return true;
        }

        if (self::isSoftMetricsFailure($m)) {
            return false;
        }

        foreach ([
            'token',
            'reconnect',
            'oauth',
            'developer token',
            'unauthenticated',
            'unauthorized',
            'invalid_grant',
            'access token',
            'refresh token',
        ] as $needle) {
            if (str_contains($m, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isSoftMetricsFailure(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'no campaign')
            || str_contains($m, 'returned no')
            || str_contains($m, 'no campaign metrics')
            || str_contains($m, '0 row');
    }
}
