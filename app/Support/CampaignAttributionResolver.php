<?php

namespace App\Support;

use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CampaignAttributionResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{google_campaign_id: ?string, campaign_name: ?string, campaign: ?string}
     */
    public static function resolve(Domain $domain, array $data): array
    {
        $utmCampaign = trim((string) ($data['utm_campaign'] ?? ''));
        $googleCampaignId = self::extractGoogleCampaignId($data);

        $campaignName = $utmCampaign !== '' ? $utmCampaign : null;

        if (($campaignName === null || $campaignName === '') && $googleCampaignId !== '') {
            $campaignName = self::lookupCampaignName($domain->id, $googleCampaignId);
        }

        if (
            ($campaignName === null || $campaignName === '')
            && $googleCampaignId === ''
            && GoogleClickAttribution::isPaidTraffic($data)
        ) {
            $single = self::singleCampaignForDomain($domain->id);
            if ($single !== null) {
                $googleCampaignId = $single['campaign_id'];
                $campaignName = $single['campaign_name'];
            }
        }

        return [
            'google_campaign_id' => $googleCampaignId !== '' ? $googleCampaignId : null,
            'campaign_name' => $campaignName,
            'campaign' => $campaignName,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function extractGoogleCampaignId(array $data): string
    {
        foreach (['gad_campaignid', 'google_campaign_id', 'campaignid'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                return preg_replace('/\D+/', '', $value) ?? $value;
            }
        }

        $url = (string) ($data['url'] ?? '');
        if ($url === '') {
            return '';
        }

        $queryString = (string) parse_url($url, PHP_URL_QUERY);
        if ($queryString === '') {
            return '';
        }

        parse_str($queryString, $query);

        foreach (['gad_campaignid', 'campaignid'] as $key) {
            $value = trim((string) ($query[$key] ?? ''));
            if ($value !== '') {
                return preg_replace('/\D+/', '', $value) ?? $value;
            }
        }

        return '';
    }

    public static function lookupCampaignName(int $domainId, string $campaignId): ?string
    {
        if ($campaignId === '' || ! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return null;
        }

        $name = DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->where('campaign_id', $campaignId)
            ->orderByDesc('metric_date')
            ->value('campaign_name');

        return filled($name) ? (string) $name : null;
    }

    /**
     * @return array{campaign_id: string, campaign_name: string}|null
     */
    public static function singleCampaignForDomain(int $domainId): ?array
    {
        if (! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return null;
        }

        $rows = DB::table('google_ads_campaign_daily_metrics')
            ->where('domain_id', $domainId)
            ->select('campaign_id', DB::raw('MAX(campaign_name) as campaign_name'))
            ->groupBy('campaign_id')
            ->get();

        if ($rows->count() !== 1) {
            return null;
        }

        $row = $rows->first();

        return [
            'campaign_id' => (string) $row->campaign_id,
            'campaign_name' => (string) $row->campaign_name,
        ];
    }
}
