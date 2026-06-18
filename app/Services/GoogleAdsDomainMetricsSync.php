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

        Log::info('Google Ads domain metrics sync → start', [
            'domain_id' => $domain->id,
            'hostname' => $domain->hostname,
            'google_ads_account_id' => $account->id,
            'customer_id' => $account->customer_id,
            'manager_customer_id' => $account->manager_customer_id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        // Store all campaigns for the linked account (no hostname filter on save).
        $dailyRows = $this->fetchDailyFromGoogle($account, $from, $to);

        if ($dailyRows === []) {
            $dailyRows = $this->fetchAggregateFallback($account, $from, $to);
            if ($dailyRows !== []) {
                $this->lastMessage = 'Saved period totals (daily breakdown was empty).';
            }
        }

        if ($dailyRows === []) {
            $apiErr = app(GoogleAdsMetricsService::class)->lastApiError;
            $this->lastMessage = $apiErr
                ?: ('Google returned no campaign metrics for the last ' . self::DEFAULT_SYNC_DAYS . ' days.');

            Log::warning('Google Ads domain metrics sync ← no rows saved', [
                'domain_id' => $domain->id,
                'hostname' => $domain->hostname,
                'customer_id' => $account->customer_id,
                'api_error' => $apiErr,
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

        Log::info('Google Ads domain metrics sync ← done', [
            'domain_id' => $domain->id,
            'rows_saved' => $saved,
            'table' => 'google_ads_campaign_daily_metrics',
        ]);

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

    /**
     * Use header date range when it overlaps DB rows; otherwise use all stored dates for this domain.
     *
     * @return array{from: Carbon, to: Carbon, used_stored_bounds: bool}
     */
    public function effectiveMetricRange(int $domainId, Carbon $from, Carbon $to): array
    {
        $hasInRange = GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->exists();

        if ($hasInRange) {
            return ['from' => $from, 'to' => $to, 'used_stored_bounds' => false];
        }

        $bounds = GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->selectRaw('MIN(metric_date) as min_date, MAX(metric_date) as max_date')
            ->first();

        if (! $bounds?->min_date || ! $bounds?->max_date) {
            return ['from' => $from, 'to' => $to, 'used_stored_bounds' => false];
        }

        return [
            'from' => Carbon::parse($bounds->min_date)->startOfDay(),
            'to' => Carbon::parse($bounds->max_date)->endOfDay(),
            'used_stored_bounds' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function aggregatedCampaignRowsWithFallback(int $domainId, Carbon $from, Carbon $to): array
    {
        $range = $this->effectiveMetricRange($domainId, $from, $to);

        return $this->aggregatedCampaignRows($domainId, $range['from'], $range['to']);
    }

    /**
     * @return array{clicks: int, cost: float, impressions: int, from: string, to: string, used_stored_bounds: bool}
     */
    public function clickTotalsForDomain(int $domainId, Carbon $from, Carbon $to): array
    {
        $range = $this->effectiveMetricRange($domainId, $from, $to);

        $agg = GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$range['from']->toDateString(), $range['to']->toDateString()])
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(impressions), 0) as impressions')
            ->first();

        return [
            'clicks' => (int) ($agg->clicks ?? 0),
            'cost' => round((float) ($agg->cost ?? 0), 2),
            'impressions' => (int) ($agg->impressions ?? 0),
            'from' => $range['from']->toDateString(),
            'to' => $range['to']->toDateString(),
            'used_stored_bounds' => $range['used_stored_bounds'],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<string, object{metric_date: string, clicks: int}>
     */
    public function dailyClicksByDate(int $domainId, Carbon $from, Carbon $to)
    {
        $range = $this->effectiveMetricRange($domainId, $from, $to);

        return GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$range['from']->toDateString(), $range['to']->toDateString()])
            ->selectRaw('metric_date, SUM(clicks) as clicks')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->metric_date)->toDateString());
    }

    /**
     * @param  iterable<int>  $domainIds
     * @return array{clicks: int, cost: float, impressions: int, from: string, to: string, used_stored_bounds: bool}
     */
    public function clickTotalsForDomains(iterable $domainIds, Carbon $from, Carbon $to): array
    {
        $ids = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [
                'clicks' => 0,
                'cost' => 0.0,
                'impressions' => 0,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'used_stored_bounds' => false,
            ];
        }

        if ($ids->count() === 1) {
            return $this->clickTotalsForDomain((int) $ids->first(), $from, $to);
        }

        $agg = GoogleAdsCampaignDailyMetric::query()
            ->whereIn('domain_id', $ids)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(impressions), 0) as impressions')
            ->first();

        if ((int) ($agg->clicks ?? 0) === 0) {
            $totals = ['clicks' => 0, 'cost' => 0.0, 'impressions' => 0, 'used_stored_bounds' => false];
            foreach ($ids as $domainId) {
                $part = $this->clickTotalsForDomain((int) $domainId, $from, $to);
                $totals['clicks'] += (int) ($part['clicks'] ?? 0);
                $totals['cost'] += (float) ($part['cost'] ?? 0);
                $totals['impressions'] += (int) ($part['impressions'] ?? 0);
                $totals['used_stored_bounds'] = $totals['used_stored_bounds'] || (bool) ($part['used_stored_bounds'] ?? false);
            }
            $totals['cost'] = round($totals['cost'], 2);

            return array_merge($totals, [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
        }

        return [
            'clicks' => (int) ($agg->clicks ?? 0),
            'cost' => round((float) ($agg->cost ?? 0), 2),
            'impressions' => (int) ($agg->impressions ?? 0),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'used_stored_bounds' => false,
        ];
    }

    /**
     * @param  iterable<int>  $domainIds
     * @return \Illuminate\Support\Collection<string, object{metric_date: string, clicks: int}>
     */
    public function dailyClicksByDateForDomains(iterable $domainIds, Carbon $from, Carbon $to)
    {
        $ids = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        if ($ids->count() === 1) {
            return $this->dailyClicksByDate((int) $ids->first(), $from, $to);
        }

        return GoogleAdsCampaignDailyMetric::query()
            ->whereIn('domain_id', $ids)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('metric_date, SUM(clicks) as clicks')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->metric_date)->toDateString());
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

        foreach ($headerAttempts as $index => $headers) {
            Log::info('Google Ads domain metrics sync: API attempt', [
                'attempt' => $index + 1,
                'customer_id' => $account->customer_id,
                'login_customer_id' => $headers['login-customer-id'] ?? null,
            ]);

            $rows = $metrics->dailyCampaignMetrics($account, $version, $headers, $fromStr, $toStr, null);
            if ($rows !== []) {
                Log::info('Google Ads domain metrics sync: API attempt succeeded', [
                    'attempt' => $index + 1,
                    'rows' => count($rows),
                ]);

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
