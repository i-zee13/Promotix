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

    public ?string $lastMessage = null;

    /**
     * @return array{saved: int, message: ?string}
     */
    public function syncDomain(Domain $domain, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $this->lastMessage = null;
        $empty = ['saved' => 0, 'message' => null];

        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;

        if (! $account || (bool) $account->is_manager) {
            $this->lastMessage = 'No client Google Ads account linked.';

            return array_merge($empty, ['message' => $this->lastMessage]);
        }

        $to = ($to ?? Carbon::now())->copy()->endOfDay();
        $from = ($from ?? $to->copy()->subDays(self::DEFAULT_SYNC_DAYS))->copy()->startOfDay();

        // Store all campaigns for the linked account (no hostname filter on save).
        $dailyRows = $this->fetchDailyFromGoogle($account, $from, $to);

        if ($dailyRows === []) {
            $dailyRows = $this->fetchAggregateFallback($account, $from, $to);
            if ($dailyRows !== []) {
                $this->lastMessage = 'Saved period totals (daily breakdown was empty).';
            }
        }

        if ($dailyRows === []) {
            $this->lastMessage = 'Google returned no campaign metrics for the last ' . self::DEFAULT_SYNC_DAYS . ' days.';
            Log::info('Google Ads domain metrics sync: no rows', [
                'domain_id' => $domain->id,
                'hostname' => $domain->hostname,
                'customer_id' => $account->customer_id,
            ]);

            return array_merge($empty, ['message' => $this->lastMessage]);
        }

        GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        $now = now();
        $saved = 0;

        foreach ($dailyRows as $row) {
            $campaignId = (string) ($row['campaign_id'] ?? '');
            if ($campaignId === '') {
                continue;
            }

            $metricDate = (string) ($row['metric_date'] ?? '');
            if ($metricDate === '') {
                $metricDate = $to->toDateString();
            }

            GoogleAdsCampaignDailyMetric::query()->create([
                'domain_id' => $domain->id,
                'google_ads_account_id' => $account->id,
                'campaign_id' => $campaignId,
                'campaign_name' => (string) ($row['campaign'] ?? 'Campaign'),
                'status' => (string) ($row['status'] ?? ''),
                'metric_date' => $metricDate,
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

        return [
            'saved' => $saved,
            'message' => $this->lastMessage,
        ];
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
    private function fetchDailyFromGoogle(GoogleAdsAccount $account, Carbon $from, Carbon $to): array
    {
        $connection = $account->connection;
        if (! $connection) {
            $this->lastMessage = 'Google connection missing for this account.';

            return [];
        }

        $api = app(GoogleAdsConnectionService::class);
        $baseHeaders = $api->apiHeaders($connection);
        if (! $baseHeaders) {
            $this->lastMessage = 'Could not build Google API headers (reconnect Gmail).';

            return [];
        }

        $version = $api->apiVersions()[0] ?? 'v24';
        $metrics = app(GoogleAdsMetricsService::class);
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $headerAttempts = [array_merge($baseHeaders, [])];
        $loginId = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: $api->loginCustomerId()));
        if ($loginId !== '') {
            $withMcc = $baseHeaders;
            $withMcc['login-customer-id'] = $loginId;
            array_unshift($headerAttempts, $withMcc);
        }

        foreach ($headerAttempts as $headers) {
            $rows = $metrics->dailyCampaignMetrics($account, $version, $headers, $fromStr, $toStr, null);
            if ($rows !== []) {
                return $rows;
            }
        }

        $this->lastMessage = $metrics->lastApiError
            ?? 'Google Ads API returned no campaign rows for customer ' . $account->customer_id;

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAggregateFallback(GoogleAdsAccount $account, Carbon $from, Carbon $to): array
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
        $snapshotDate = $to->format('Y-m-d');

        $agg = app(GoogleAdsMetricsService::class)->campaignMetrics(
            $account,
            $version,
            $headers,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            null
        );

        return array_map(fn (array $row) => array_merge($row, ['metric_date' => $snapshotDate]), $agg);
    }
}
