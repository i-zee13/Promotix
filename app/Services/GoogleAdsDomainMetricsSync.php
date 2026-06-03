<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsCampaignDailyMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleAdsDomainMetricsSync
{
    public const DEFAULT_SYNC_DAYS = 30;

    /**
     * Pull Google Ads campaign metrics for this domain's linked account and store per day in DB.
     */
    public function syncDomain(Domain $domain, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;

        if (! $account || (bool) $account->is_manager) {
            return 0;
        }

        $to = ($to ?? Carbon::now())->copy()->endOfDay();
        $from = ($from ?? $to->copy()->subDays(self::DEFAULT_SYNC_DAYS))->copy()->startOfDay();

        $dailyRows = $this->fetchDailyFromGoogle($domain, $account, $from, $to);
        if ($dailyRows === []) {
            Log::info('Google Ads domain metrics sync: no rows', [
                'domain_id' => $domain->id,
                'hostname' => $domain->hostname,
                'customer_id' => $account->customer_id,
            ]);

            return 0;
        }

        GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        $now = now();
        $saved = 0;

        foreach ($dailyRows as $row) {
            GoogleAdsCampaignDailyMetric::query()->create([
                'domain_id' => $domain->id,
                'google_ads_account_id' => $account->id,
                'campaign_id' => (string) ($row['campaign_id'] ?? ''),
                'campaign_name' => (string) ($row['campaign'] ?? 'Campaign'),
                'status' => (string) ($row['status'] ?? ''),
                'metric_date' => (string) ($row['metric_date'] ?? $from->toDateString()),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'cost' => (float) ($row['cost'] ?? 0),
                'cpc' => (float) ($row['cpc'] ?? 0),
                'conversions' => (float) ($row['conversions'] ?? 0),
                'phone_calls' => (int) ($row['phone_calls'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $saved++;
        }

        $domain->ads_synced_at = $now;
        $domain->save();

        return $saved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function aggregatedCampaignRows(int $domainId, Carbon $from, Carbon $to): array
    {
        $rows = GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('campaign_id, MAX(campaign_name) as campaign_name, MAX(status) as status, SUM(clicks) as clicks, SUM(impressions) as impressions, SUM(cost) as cost, SUM(conversions) as conversions, SUM(phone_calls) as phone_calls, AVG(ctr) as ctr')
            ->groupBy('campaign_id')
            ->orderByDesc('clicks')
            ->limit(100)
            ->get();

        return $rows->map(function ($r) {
            $clicks = (int) $r->clicks;
            $cost = round((float) $r->cost, 2);

            return [
                'campaign_id' => (string) $r->campaign_id,
                'campaign' => (string) $r->campaign_name,
                'status' => (string) ($r->status ?? ''),
                'clicks' => $clicks,
                'impressions' => (int) $r->impressions,
                'cost' => $cost,
                'cpc' => $clicks > 0 ? round($cost / $clicks, 2) : 0,
                'conversions' => (float) $r->conversions,
                'phone_calls' => (int) $r->phone_calls,
                'ctr' => round((float) $r->ctr, 2),
                'total' => $clicks,
                'invalid' => 0,
                'valid' => $clicks,
                'source' => 'google_ads_db',
            ];
        })->values()->all();
    }

    public function shouldRefresh(Domain $domain, Carbon $from, Carbon $to): bool
    {
        if (! $domain->google_ads_account_id) {
            return false;
        }

        if (! $domain->ads_synced_at) {
            return true;
        }

        if ($domain->ads_synced_at->lt(now()->subHours(6))) {
            return true;
        }

        return ! GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchDailyFromGoogle(Domain $domain, GoogleAdsAccount $account, Carbon $from, Carbon $to): array
    {
        $connection = $account->connection;
        if (! $connection) {
            return [];
        }

        $api = app(GoogleAdsConnectionService::class);
        $headers = $api->apiHeaders($connection);
        if (! $headers) {
            return [];
        }

        $loginId = $account->manager_customer_id ?: $api->loginCustomerId();
        if ($loginId !== '') {
            $headers['login-customer-id'] = $loginId;
        }

        $version = $api->apiVersions()[0] ?? 'v24';

        return app(GoogleAdsMetricsService::class)->dailyCampaignMetrics(
            $account,
            $version,
            $headers,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $domain->hostname
        );
    }
}
