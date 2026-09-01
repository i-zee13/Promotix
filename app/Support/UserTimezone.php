<?php

namespace App\Support;

use App\Models\Domain;
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

    public const REPORTING_PROFILE = 'profile';

    public const REPORTING_UTC = 'utc';

    public const REPORTING_GOOGLE = 'google';

    /** @var list<string> */
    public const REPORTING_MODES = [
        self::REPORTING_PROFILE,
        self::REPORTING_UTC,
        self::REPORTING_GOOGLE,
    ];

    public static function reportingMode(?User $user): string
    {
        $mode = (string) ($user?->reporting_timezone ?? self::REPORTING_PROFILE);

        return in_array($mode, self::REPORTING_MODES, true) ? $mode : self::REPORTING_PROFILE;
    }

    public static function reportingModeLabel(?User $user): string
    {
        return match (self::reportingMode($user)) {
            self::REPORTING_UTC => 'UTC',
            self::REPORTING_GOOGLE => 'Google Ads account',
            default => 'My profile timezone',
        };
    }

    public static function reportingTimezoneForUser(?User $user, ?string $googleAccountTimezone = null): string
    {
        return match (self::reportingMode($user)) {
            self::REPORTING_UTC => 'UTC',
            self::REPORTING_GOOGLE => self::isValid($googleAccountTimezone)
                ? $googleAccountTimezone
                : self::forUser($user),
            default => self::forUser($user),
        };
    }

    /**
     * Linked Google Ads account timezone for the selected domain(s), if any.
     *
     * @param  iterable<int, int>|null  $domainIds
     */
    public static function resolveGoogleAccountTimezone(?User $user, ?int $domainId = null, ?iterable $domainIds = null): ?string
    {
        if (! $user) {
            return null;
        }

        $query = Domain::query()
            ->where('user_id', $user->id)
            ->forPaidMarketing()
            ->with('googleAdsAccount');

        if ($domainId !== null && $domainId > 0) {
            $query->where('id', $domainId);
        } elseif ($domainIds !== null) {
            $ids = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->values();
            if ($ids->isNotEmpty()) {
                $query->whereIn('id', $ids);
            }
        }

        $domain = $query->orderBy('id')->get()
            ->first(fn (Domain $row) => self::isValid($row->googleAdsAccount?->time_zone));

        $tz = $domain?->googleAdsAccount?->time_zone;

        return self::isValid($tz) ? $tz : null;
    }

    /**
     * Reporting timezone for paid marketing views (respects profile reporting mode + domain Google TZ).
     *
     * @param  iterable<int, int>|null  $domainIds
     */
    public static function reportingTimezoneForRequest(?User $user, ?int $domainId = null, ?iterable $domainIds = null): string
    {
        return self::reportingTimezoneForUser(
            $user,
            self::resolveGoogleAccountTimezone($user, $domainId, $domainIds),
        );
    }

    /**
     * Map reporting-calendar dates to Google Ads metric_date bounds (account timezone).
     *
     * @return array{0: string, 1: string}
     */
    public static function googleMetricDateBounds(
        string $fromDate,
        string $toDate,
        string $reportingTz,
        string $googleTz,
    ): array {
        if ($reportingTz === $googleTz) {
            return [$fromDate, $toDate];
        }

        $dates = self::googleMetricDatesForReportingRange($fromDate, $toDate, $reportingTz, $googleTz);
        sort($dates);

        return [$dates[0], $dates[count($dates) - 1]];
    }

    /**
     * @return list<string>
     */
    public static function googleMetricDatesForReportingRange(
        string $fromDate,
        string $toDate,
        string $reportingTz,
        string $googleTz,
    ): array {
        $dates = [];
        $cursor = Carbon::parse($fromDate, $reportingTz)->startOfDay();
        $end = Carbon::parse($toDate, $reportingTz)->startOfDay();

        while ($cursor->lte($end)) {
            $dates[$cursor->copy()->startOfDay()->utc()->timezone($googleTz)->toDateString()] = true;
            $dates[$cursor->copy()->endOfDay()->utc()->timezone($googleTz)->toDateString()] = true;
            $cursor->addDay();
        }

        return array_keys($dates);
    }

    /**
     * @return array<string, mixed>
     */
    public static function dashboardContext(
        ?User $user,
        ?string $googleAccountTimezone,
        string $fromDate,
        string $toDate,
        ?Domain $domain = null,
    ): array {
        $reportingTz = self::reportingTimezoneForUser($user, $googleAccountTimezone);
        [$googleFrom, $googleTo] = self::isValid($googleAccountTimezone)
            ? self::googleMetricDateBounds($fromDate, $toDate, $reportingTz, $googleAccountTimezone)
            : [$fromDate, $toDate];

        $context = [
            'reporting_mode' => self::reportingMode($user),
            'reporting_mode_label' => self::reportingModeLabel($user),
            'reporting_timezone' => $reportingTz,
            'reporting_timezone_label' => self::formatDisplay($reportingTz),
            'profile_timezone' => self::forUser($user),
            'google_timezone' => $googleAccountTimezone,
            'google_timezone_label' => self::formatDisplay($googleAccountTimezone),
            'visit_dates' => ['from' => $fromDate, 'to' => $toDate],
            'google_dates' => ['from' => $googleFrom, 'to' => $googleTo],
        ];

        if ($domain !== null) {
            $context['domain'] = self::domainTimezoneEntry($domain);
        }

        return $context;
    }

    public static function formatDisplay(?string $timezone): ?string
    {
        if (! self::isValid($timezone)) {
            return null;
        }

        try {
            $abbr = now($timezone)->format('T');

            return "{$timezone} ({$abbr})";
        } catch (\Throwable) {
            return $timezone;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function domainTimezoneEntry(Domain $domain): ?array
    {
        $domain->loadMissing('googleAdsAccount');
        $googleTz = self::isValid($domain->googleAdsAccount?->time_zone)
            ? $domain->googleAdsAccount->time_zone
            : null;

        $currencyCode = \App\Support\AccountCurrency::fromDomain($domain);

        return [
            'id' => (int) $domain->id,
            'hostname' => $domain->hostname,
            'google_account_name' => $domain->googleAdsAccount?->displayLabel(),
            'google_timezone' => $googleTz,
            'google_timezone_label' => self::formatDisplay($googleTz),
            'currency_code' => $currencyCode,
            'currency_label' => \App\Support\AccountCurrency::label($currencyCode),
        ];
    }

    /**
     * @param  iterable<Domain>  $domains
     * @return array<int, array<string, mixed>>
     */
    public static function domainCatalog(iterable $domains): array
    {
        $catalog = [];

        foreach ($domains as $domain) {
            $entry = self::domainTimezoneEntry($domain);
            if ($entry !== null) {
                $catalog[(int) $domain->id] = $entry;
            }
        }

        return $catalog;
    }

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

        return $now->format('g:i A');
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
    public static function dateRangeFromRequest(Request $request, ?User $user = null, int $defaultDays = 6, ?string $timezone = null): array
    {
        $tz = $timezone && self::isValid($timezone) ? $timezone : self::reportingTimezoneForUser($user);
        if ($request->boolean('use_utc') || strtolower((string) $request->query('timezone', '')) === 'utc') {
            $tz = 'UTC';
        }

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

    /**
     * Calendar dates (Y-m-d) in the user's timezone — use for Google Ads daily metrics, not UTC timestamps.
     *
     * @return array{0: string, 1: string}
     */
    public static function calendarDateRangeFromRequest(Request $request, ?User $user = null, int $defaultDays = 6, ?string $timezone = null): array
    {
        $tz = $timezone && self::isValid($timezone) ? $timezone : self::reportingTimezoneForUser($user);
        if ($request->boolean('use_utc') || strtolower((string) $request->query('timezone', '')) === 'utc') {
            $tz = 'UTC';
        }

        $fromDate = $request->query('from')
            ? Carbon::parse((string) $request->query('from'), $tz)->toDateString()
            : Carbon::now($tz)->subDays($defaultDays)->toDateString();

        $toDate = $request->query('to')
            ? Carbon::parse((string) $request->query('to'), $tz)->toDateString()
            : Carbon::now($tz)->toDateString();

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        // Guard against multi-year "All time" payloads that overload paid queries.
        $maxDays = 800;
        $from = Carbon::parse($fromDate, $tz)->startOfDay();
        $to = Carbon::parse($toDate, $tz)->startOfDay();
        if ($from->diffInDays($to) > $maxDays) {
            $fromDate = $to->copy()->subDays($maxDays)->toDateString();
        }

        return [$fromDate, $toDate];
    }

    /**
     * Strict calendar-day bounds in the user's timezone (inclusive).
     *
     * Uses a single UTC whereBetween spanning fromDate start → toDate end.
     * (Previously expanded into one OR per day, which exploded SQL for "All time"
     * ranges and caused host/WAF 403 timeouts.)
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyCalendarDateRangeFilter(
        $query,
        string $column,
        string $fromDate,
        string $toDate,
        ?User $user = null,
        ?string $timezone = null,
    ): void {
        $tz = $timezone && self::isValid($timezone) ? $timezone : self::reportingTimezoneForUser($user);

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $fromUtc = Carbon::parse($fromDate, $tz)->startOfDay()->utc()->toDateTimeString();
        $toUtc = Carbon::parse($toDate, $tz)->endOfDay()->utc()->toDateTimeString();

        $query->whereBetween($column, [$fromUtc, $toUtc]);
    }

    /** SQL expression for the visit timestamp's calendar date in the user's timezone. */
    public static function localDateSql(string $column, ?User $user = null, ?string $timezone = null): string
    {
        $offset = Carbon::now($timezone && self::isValid($timezone) ? $timezone : self::reportingTimezoneForUser($user))->format('P');

        return "DATE(CONVERT_TZ({$column}, '+00:00', '{$offset}'))";
    }

    /** SQL bucket key for local hour (e.g. 2026-08-17 14:00:00). */
    public static function localHourBucketSql(string $column, ?User $user = null, ?string $timezone = null): string
    {
        $offset = Carbon::now($timezone && self::isValid($timezone) ? $timezone : self::reportingTimezoneForUser($user))->format('P');

        return "DATE_FORMAT(CONVERT_TZ({$column}, '+00:00', '{$offset}'), '%Y-%m-%d %H:00:00')";
    }

    /** SQL expression for the visit timestamp converted to the user's timezone. */
    public static function localDateTimeSql(string $column, ?User $user = null, ?string $timezone = null): string
    {
        $offset = Carbon::now($timezone && self::isValid($timezone) ? $timezone : self::reportingTimezoneForUser($user))->format('P');

        return "CONVERT_TZ({$column}, '+00:00', '{$offset}')";
    }

    public static function toUserTimezone(?Carbon $dateTime, ?User $user, ?string $googleAccountTimezone = null): ?Carbon
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->copy()->timezone(self::reportingTimezoneForUser($user, $googleAccountTimezone));
    }

    public static function formatForUser(?Carbon $dateTime, ?User $user, string $format, ?string $googleAccountTimezone = null): ?string
    {
        return self::toUserTimezone($dateTime, $user, $googleAccountTimezone)?->format($format);
    }

    public static function isoForUser(?Carbon $dateTime, ?User $user, ?string $googleAccountTimezone = null): ?string
    {
        return self::toUserTimezone($dateTime, $user, $googleAccountTimezone)?->toIso8601String();
    }

    /**
     * Parse a UTC instant stored in the database (not wall-clock in the user's timezone).
     */
    public static function parseUtcInstant(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse((string) $value, 'UTC');
    }

    public static function formatUtcInstantForUser(mixed $value, ?User $user, string $format): ?string
    {
        return self::formatForUser(self::parseUtcInstant($value), $user, $format);
    }

    public static function isoUtcInstantForUser(mixed $value, ?User $user): ?string
    {
        return self::isoForUser(self::parseUtcInstant($value), $user);
    }
}
