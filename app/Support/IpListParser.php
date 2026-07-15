<?php

namespace App\Support;

use Carbon\Carbon;

class IpListParser
{
    /**
     * Normalize a multiline IP list. Supports optional duration suffixes:
     * 1.2.3.4
     * 1.2.3.4 | 2m
     * 1.2.3.4 | 1h
     * 1.2.3.4 | 24h
     * 1.2.3.4 | 7d
     * 1.2.3.4 | permanent
     *
     * @return list<string>
     */
    public static function normalizeLines(string $raw, ?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $duration = null;
            if (preg_match('/^(.*?)(?:\s*[|]\s*|\s+#expires=)(.+)$/i', $line, $m)) {
                $line = trim($m[1]);
                $duration = trim($m[2]);
            }

            if ($line === '') {
                continue;
            }

            if ($duration !== null && strtolower($duration) !== 'permanent') {
                $expires = self::resolveExpiry($duration, $now);
                if ($expires !== null) {
                    $line .= ' #expires=' . $expires->utc()->toIso8601String();
                }
            }

            $out[] = $line;
        }

        return array_values(array_unique($out));
    }

    public static function isActiveEntry(string $entry, ?Carbon $now = null): bool
    {
        $now ??= Carbon::now('UTC');
        $expires = self::entryExpiresAt($entry);
        if ($expires === null) {
            return true;
        }

        return $expires->greaterThan($now);
    }

    public static function entryIp(string $entry): string
    {
        $entry = trim($entry);
        if (preg_match('/^(.*?)(?:\s+#expires=.*)?$/i', $entry, $m)) {
            return trim($m[1]);
        }

        return $entry;
    }

    public static function entryExpiresAt(string $entry): ?Carbon
    {
        if (! preg_match('/#expires=([^\s]+)/i', $entry, $m)) {
            return null;
        }

        try {
            return Carbon::parse($m[1])->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolveExpiry(string $duration, Carbon $now): ?Carbon
    {
        $duration = strtolower(trim($duration));

        return match ($duration) {
            '2m', '2min', '2 minutes' => $now->copy()->addMinutes(2),
            '1h', '1 hour' => $now->copy()->addHour(),
            '24h', '1d', '1 day' => $now->copy()->addDay(),
            '7d', '7 days' => $now->copy()->addDays(7),
            default => self::tryParseAbsolute($duration),
        };
    }

    private static function tryParseAbsolute(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
