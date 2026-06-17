<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserTimezone
{
    /** @var list<string> */
    public const COMMON = [
        'UTC',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'Europe/London',
        'Europe/Paris',
        'Asia/Dubai',
        'Asia/Karachi',
        'Asia/Kolkata',
        'Asia/Singapore',
        'Australia/Sydney',
    ];

    /** @var array<string, string> */
    private const COUNTRY_DEFAULTS = [
        'PK' => 'Asia/Karachi',
        'US' => 'America/New_York',
        'GB' => 'Europe/London',
        'AE' => 'Asia/Dubai',
        'IN' => 'Asia/Kolkata',
        'DE' => 'Europe/Berlin',
        'FR' => 'Europe/Paris',
        'AU' => 'Australia/Sydney',
        'CA' => 'America/Toronto',
        'SG' => 'Asia/Singapore',
    ];

    public static function isValid(?string $timezone): bool
    {
        if ($timezone === null || $timezone === '') {
            return false;
        }

        return in_array($timezone, timezone_identifiers_list(), true);
    }

    public static function forUser(?User $user): string
    {
        $tz = $user?->timezone;

        return self::isValid($tz) ? $tz : (string) config('app.timezone', 'UTC');
    }

    public static function applyForUser(?User $user): void
    {
        $tz = self::forUser($user);
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
    }

    public static function assign(User $user, string $timezone, string $source): void
    {
        if (! self::isValid($timezone)) {
            return;
        }

        $user->forceFill([
            'timezone' => $timezone,
            'timezone_source' => $source,
        ])->save();
    }

    public static function captureForUser(User $user, Request $request): void
    {
        if (($user->timezone_source ?? '') === 'manual') {
            return;
        }

        $fromBrowser = $request->input('timezone');
        if (self::isValid($fromBrowser)) {
            self::assign($user, $fromBrowser, 'browser');

            return;
        }

        $country = strtoupper(trim((string) $request->headers->get('CF-IPCountry', '')));
        if ($country !== '' && isset(self::COUNTRY_DEFAULTS[$country])) {
            self::assign($user, self::COUNTRY_DEFAULTS[$country], 'ip');
        }
    }

    public static function headerLabel(?User $user): ?string
    {
        if (! $user?->timezone) {
            return null;
        }

        $tz = self::forUser($user);
        $now = Carbon::now($tz);

        return $now->format('T').' · '.$now->format('g:i A');
    }

    public static function headerTitle(?User $user): string
    {
        if (! $user?->timezone) {
            return 'Timezone not set';
        }

        $source = match ($user->timezone_source) {
            'manual' => 'Set manually in profile',
            'ip' => 'Detected from login location',
            default => 'Detected from your browser',
        };

        return self::forUser($user).' ('.$source.')';
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'manual' => 'Manual',
            'ip' => 'Auto (location)',
            default => 'Auto (browser)',
        };
    }

    /** @return array<string, list<string>> */
    public static function groupedOptions(): array
    {
        $groups = [];
        foreach (timezone_identifiers_list() as $identifier) {
            $region = str_contains($identifier, '/')
                ? explode('/', $identifier, 2)[0]
                : 'Other';
            $groups[$region][] = $identifier;
        }

        return $groups;
    }

    public static function nowUtc(): Carbon
    {
        return Carbon::now('UTC');
    }

    public static function parseInstant(int|float|string|null $timestampMs): Carbon
    {
        if ($timestampMs === null || $timestampMs === '') {
            return self::nowUtc();
        }

        return Carbon::createFromTimestampMs((int) $timestampMs, 'UTC');
    }

    /**
     * Parse dashboard ?from=&to= dates in the user's timezone and return UTC bounds for DB queries.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dateRangeFromRequest(Request $request, ?User $user = null, int $defaultDays = 6): array
    {
        $tz = self::forUser($user);

        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'), $tz)->startOfDay()->utc()
            : Carbon::now($tz)->subDays($defaultDays)->startOfDay()->utc();

        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'), $tz)->endOfDay()->utc()
            : Carbon::now($tz)->endOfDay()->utc();

        if ($from->gt($to)) {
            [$from, $to] = [
                $to->copy()->startOfDay()->utc(),
                $from->copy()->endOfDay()->utc(),
            ];
        }

        return [$from, $to];
    }

    public static function toUserTimezone(?Carbon $dateTime, ?User $user): ?Carbon
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->copy()->timezone(self::forUser($user));
    }

    public static function formatForUser(?Carbon $dateTime, ?User $user, string $format): ?string
    {
        return self::toUserTimezone($dateTime, $user)?->format($format);
    }

    public static function isoForUser(?Carbon $dateTime, ?User $user): ?string
    {
        return self::toUserTimezone($dateTime, $user)?->toIso8601String();
    }
}
