<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class GoogleVerifiedPaidTraffic
{
    /**
     * @param  Collection<int, int>  $domainIds
     * @param  Collection<int, Domain>  $domains
     */
    public function buildLookup(
        Collection $domainIds,
        string $fromDate,
        string $toDate,
        ?User $user,
        string $reportingTz,
        Collection $domains,
    ): GoogleVerifiedCampaignLookup {
        if (
            $domainIds->isEmpty()
            || ! Schema::hasTable('google_ads_campaign_daily_metrics')
        ) {
            return GoogleVerifiedCampaignLookup::empty();
        }

        $googleTimezoneByDomainId = [];
        $metricDates = [];

        foreach ($domains as $domain) {
            if (! $domainIds->contains((int) $domain->id)) {
                continue;
            }

            $googleTz = UserTimezone::isValid($domain->googleAdsAccount?->time_zone)
                ? (string) $domain->googleAdsAccount->time_zone
                : 'UTC';
            $googleTimezoneByDomainId[(int) $domain->id] = $googleTz;

            foreach (UserTimezone::googleMetricDatesForReportingRange($fromDate, $toDate, $reportingTz, $googleTz) as $date) {
                $metricDates[$date] = true;
            }
        }

        if ($metricDates === []) {
            return new GoogleVerifiedCampaignLookup([], $googleTimezoneByDomainId);
        }

        $sortedDates = array_keys($metricDates);
        sort($sortedDates);

        $rows = DB::table('google_ads_campaign_daily_metrics')
            ->whereIn('domain_id', $domainIds->all())
            ->whereBetween('metric_date', [$sortedDates[0], $sortedDates[count($sortedDates) - 1]])
            ->where('clicks', '>', 0)
            ->get(['domain_id', 'campaign_id', 'metric_date']);

        $activeCampaignDays = [];
        foreach ($rows as $row) {
            $key = (int) $row->domain_id . '|' . (string) $row->campaign_id . '|' . (string) $row->metric_date;
            $activeCampaignDays[$key] = true;
        }

        return new GoogleVerifiedCampaignLookup($activeCampaignDays, $googleTimezoneByDomainId);
    }

    /**
     * @param  array<string, mixed>|object  $row
     */
    public static function resolveCampaignId(array|object $row): string
    {
        $data = (array) $row;
        $url = (string) ($data['url'] ?? $data['path'] ?? '');

        $fromUrl = CampaignAttributionResolver::extractGoogleCampaignId(['url' => $url]);
        if ($fromUrl !== '') {
            return $fromUrl;
        }

        $stored = trim((string) ($data['google_campaign_id'] ?? ''));
        if ($stored !== '') {
            return preg_replace('/\D+/', '', $stored) ?? '';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isInvalidRow(array $data): bool
    {
        if (array_key_exists('is_invalid_traffic', $data)) {
            return (bool) $data['is_invalid_traffic'];
        }

        return filled($data['threat_group'] ?? null);
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $rows
     * @return array{verified: int, unverified: int, verified_valid: int}
     */
    public function countRows(
        iterable $rows,
        GoogleVerifiedCampaignLookup $lookup,
        string $reportingTz,
    ): array {
        $verified = 0;
        $unverified = 0;
        $verifiedValid = 0;
        $seenClickIds = [];

        foreach ($rows as $row) {
            $data = (array) $row;
            $clickId = GoogleClickAttribution::clickIdValue($data);
            if ($clickId !== '') {
                $dedupeKey = (int) ($data['domain_id'] ?? 0) . '|' . $clickId;
                if (isset($seenClickIds[$dedupeKey])) {
                    continue;
                }
                $seenClickIds[$dedupeKey] = true;
            }

            $domainId = (int) ($data['domain_id'] ?? 0);
            $campaignId = self::resolveCampaignId($row);
            $clickedAt = $data['visited_at'] ?? $data['clicked_at'] ?? null;
            $invalid = self::isInvalidRow($data);

            if ($lookup->isVerified($domainId, $campaignId, $clickedAt, $reportingTz)) {
                $verified++;
                if (! $invalid) {
                    $verifiedValid++;
                }
            } else {
                $unverified++;
            }
        }

        return [
            'verified' => $verified,
            'unverified' => $unverified,
            'verified_valid' => $verifiedValid,
        ];
    }
}
