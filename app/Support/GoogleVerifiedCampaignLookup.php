<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Preloaded Google campaign activity for verifying tag-captured paid visits.
 *
 * A visit is verified when its resolved campaign_id had Google Ads clicks (> 0)
 * on a metric_date that aligns with the visit's reporting-calendar day.
 */
final class GoogleVerifiedCampaignLookup
{
    /**
     * @param  array<string, true>  $activeCampaignDays  keys: "{domainId}|{campaignId}|{metricDate}"
     * @param  array<int, string>  $googleTimezoneByDomainId
     */
    public function __construct(
        private readonly array $activeCampaignDays,
        private readonly array $googleTimezoneByDomainId,
    ) {}

    public function isVerified(int $domainId, string $campaignId, mixed $clickedAtUtc, string $reportingTz): bool
    {
        $campaignId = preg_replace('/\D+/', '', $campaignId) ?? '';
        if ($campaignId === '') {
            return false;
        }

        $instant = UserTimezone::parseUtcInstant($clickedAtUtc);
        if ($instant === null) {
            return false;
        }

        $googleTz = $this->googleTimezoneByDomainId[$domainId] ?? 'UTC';
        $reportingDate = $instant->copy()->timezone($reportingTz)->toDateString();
        $googleDates = UserTimezone::googleMetricDatesForReportingRange(
            $reportingDate,
            $reportingDate,
            $reportingTz,
            $googleTz,
        );

        foreach ($googleDates as $metricDate) {
            if (isset($this->activeCampaignDays["{$domainId}|{$campaignId}|{$metricDate}"])) {
                return true;
            }
        }

        return false;
    }

    public function statusLabel(int $domainId, string $campaignId, mixed $clickedAtUtc, string $reportingTz): string
    {
        $campaignId = preg_replace('/\D+/', '', $campaignId) ?? '';
        if ($campaignId === '') {
            return 'No campaign key';
        }

        return $this->isVerified($domainId, $campaignId, $clickedAtUtc, $reportingTz)
            ? 'Verified'
            : 'Unverified';
    }

    public static function empty(): self
    {
        return new self([], []);
    }
}
