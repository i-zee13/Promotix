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
     * @param  iterable<int, object|array<string, mixed>>  $rows
     * @return array{verified: int, unverified: int}
     */
    public function countRows(
        iterable $rows,
        GoogleVerifiedCampaignLookup $lookup,
        string $reportingTz,
    ): array {
        $verified = 0;
        $unverified = 0;

        foreach ($rows as $row) {
            $data = (array) $row;
            $domainId = (int) ($data['domain_id'] ?? 0);
            $campaignId = self::resolveCampaignId($row);
            $clickedAt = $data['visited_at'] ?? $data['clicked_at'] ?? null;

            if ($lookup->isVerified($domainId, $campaignId, $clickedAt, $reportingTz)) {
                $verified++;
            } else {
                $unverified++;
            }
        }

        return ['verified' => $verified, 'unverified' => $unverified];
    }
}
