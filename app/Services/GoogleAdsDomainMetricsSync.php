<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleAdsDomainMetricsSync
{
    public const DEFAULT_SYNC_DAYS = 30;

    public ?string $lastMessage = null;

    public ?string $lastApiError = null;

    public function __construct(
        private readonly GoogleAdsMetricsService $metrics,
        private readonly GoogleAdsConnectionService $connectionApi,
    ) {}

    /**
     * @return array{saved: int, message: ?string, api_error: ?string}
     */
    public function syncDomain(Domain $domain, ?string $fromDate = null, ?string $toDate = null): array
    {
        $this->lastMessage = null;
        $this->lastApiError = null;
        $empty = ['saved' => 0, 'message' => null, 'api_error' => null];

        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;

        if ($account && ! $account->time_zone && ! (bool) $account->is_manager) {
            app(GoogleAdsAccountTimezoneService::class)->refreshForAccount($account);
            $account->refresh();
        }

        if (! $account || (bool) $account->is_manager) {
            $this->lastMessage = 'No client Google Ads account linked.';

            return array_merge($empty, ['message' => $this->lastMessage]);
        }

        $toDate = $toDate ?? Carbon::now()->toDateString();
        $fromDate = $fromDate ?? Carbon::parse($toDate)->subDays(self::DEFAULT_SYNC_DAYS)->toDateString();

        Log::info('Google Ads domain metrics sync → start', [
            'domain_id' => $domain->id,
            'hostname' => $domain->hostname,
            'google_ads_account_id' => $account->id,
            'customer_id' => $account->customer_id,
            'manager_customer_id' => $account->manager_customer_id,
            'from' => $fromDate,
            'to' => $toDate,
        ]);

        // Scope metrics to this domain's landing pages so shared Google Ads accounts
        // don't copy the same account-wide click totals onto every linked domain.
        $dailyRows = $this->fetchDailyFromGoogle($account, $fromDate, $toDate, $domain->hostname);

        // Hostname scope can return empty when landing_page_view has no URL match
        // (redirect domains, URL templates, etc.). If this is the only domain linked
        // to the Ads account, fall back to account-wide metrics so Total Google Ads
        // Clicks is not stuck at 0 while tracked clicks exist.
        if ($dailyRows === [] && filled($domain->hostname)) {
            $linkedDomains = Domain::query()
                ->where('google_ads_account_id', $account->id)
                ->count();
            if ($linkedDomains <= 1) {
                Log::info('Google Ads domain metrics sync: hostname scope empty; retrying account-wide', [
                    'domain_id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'google_ads_account_id' => $account->id,
                    'linked_domains' => $linkedDomains,
                ]);
                $dailyRows = $this->fetchDailyFromGoogle($account, $fromDate, $toDate, null);
            }
        }

        if ($dailyRows === []) {
            $apiErr = $this->metrics->lastApiError;
            $this->lastApiError = $apiErr;
            $this->lastMessage = $apiErr
                ?: sprintf(
                    'Google returned no campaign metrics between %s and %s.',
                    $fromDate,
                    $toDate
                );

            Log::warning('Google Ads domain metrics sync ← no rows saved', [
                'domain_id' => $domain->id,
                'hostname' => $domain->hostname,
                'customer_id' => $account->customer_id,
                'api_error' => $apiErr,
            ]);

            return array_merge($empty, [
                'message' => $this->lastMessage,
                'api_error' => $apiErr,
            ]);
        }

        $now = now();
        $validRows = collect($dailyRows)
            ->filter(fn (array $row): bool => trim((string) ($row['campaign_id'] ?? '')) !== '')
            ->values();

        // Never delete a previously successful snapshot unless the replacement
        // payload contains rows that can actually be stored.
        if ($validRows->isEmpty()) {
            $this->lastMessage = 'Google returned no usable campaign metric rows; keeping the previous successful totals.';

            return array_merge($empty, ['message' => $this->lastMessage]);
        }

        $incomingClicks = (int) $validRows->sum(fn (array $row) => (int) ($row['clicks'] ?? 0));
        $storedClicks = (int) GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->sum('clicks');
        if ($storedClicks > 0 && $incomingClicks === 0) {
            $this->lastMessage = 'Google returned a transient zero-click snapshot; keeping the previous successful totals.';

            Log::warning('Google Ads domain metrics sync ignored transient zero snapshot', [
                'domain_id' => $domain->id,
                'stored_clicks' => $storedClicks,
                'incoming_clicks' => $incomingClicks,
            ]);

            return array_merge($empty, ['message' => $this->lastMessage]);
        }

        $saved = DB::transaction(function () use ($domain, $account, $fromDate, $toDate, $validRows, $now): int {
            GoogleAdsCampaignDailyMetric::query()
                ->where('domain_id', $domain->id)
                ->whereBetween('metric_date', [$fromDate, $toDate])
                ->delete();

            $savedRows = 0;
            foreach ($validRows as $row) {
                $campaignId = trim((string) $row['campaign_id']);
                $metricDate = trim((string) ($row['metric_date'] ?? '')) ?: $toDate;

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
                $savedRows++;
            }

            $domain->ads_synced_at = $now;
            $domain->save();

            return $savedRows;
        });

        Log::info('Google Ads domain metrics sync ← done', [
            'domain_id' => $domain->id,
            'rows_saved' => $saved,
            'table' => 'google_ads_campaign_daily_metrics',
        ]);

        return [
            'saved' => $saved,
            'message' => $this->lastMessage,
            'api_error' => null,
        ];
    }

    public function purgeMetrics(Domain $domain, string $fromDate, string $toDate): int
    {
        return GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->delete();
    }

    public function purgeAllMetrics(Domain $domain): int
    {
        return GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function aggregatedCampaignRows(int $domainId, string $fromDate, string $toDate): array
    {
        $rows = GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$fromDate, $toDate])
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
     * Always honour the dashboard date filter — never expand to all stored metric dates.
     *
     * @return array{from: string, to: string, used_stored_bounds: bool}
     */
    public function effectiveMetricRange(int $domainId, string $fromDate, string $toDate): array
    {
        return [
            'from' => $fromDate,
            'to' => $toDate,
            'used_stored_bounds' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function aggregatedCampaignRowsWithFallback(int $domainId, string $fromDate, string $toDate): array
    {
        $range = $this->effectiveMetricRange($domainId, $fromDate, $toDate);

        return $this->aggregatedCampaignRows($domainId, $range['from'], $range['to']);
    }

    /**
     * @return array{clicks: int, cost: float, impressions: int, from: string, to: string, used_stored_bounds: bool}
     */
    public function clickTotalsForDomain(int $domainId, string $fromDate, string $toDate): array
    {
        $range = $this->effectiveMetricRange($domainId, $fromDate, $toDate);

        $agg = GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$range['from'], $range['to']])
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(impressions), 0) as impressions')
            ->first();

        return [
            'clicks' => (int) ($agg->clicks ?? 0),
            'cost' => round((float) ($agg->cost ?? 0), 2),
            'impressions' => (int) ($agg->impressions ?? 0),
            'from' => $range['from'],
            'to' => $range['to'],
            'used_stored_bounds' => $range['used_stored_bounds'],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<string, object{metric_date: string, clicks: int}>
     */
    public function dailyClicksByDate(int $domainId, string $fromDate, string $toDate)
    {
        $range = $this->effectiveMetricRange($domainId, $fromDate, $toDate);

        return GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domainId)
            ->whereBetween('metric_date', [$range['from'], $range['to']])
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
    public function clickTotalsForDomains(iterable $domainIds, string $fromDate, string $toDate): array
    {
        $ids = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [
                'clicks' => 0,
                'cost' => 0.0,
                'impressions' => 0,
                'from' => $fromDate,
                'to' => $toDate,
                'used_stored_bounds' => false,
            ];
        }

        if ($ids->count() === 1) {
            return $this->clickTotalsForDomain((int) $ids->first(), $fromDate, $toDate);
        }

        $agg = GoogleAdsCampaignDailyMetric::query()
            ->whereIn('domain_id', $ids)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(impressions), 0) as impressions')
            ->first();

        return [
            'clicks' => (int) ($agg->clicks ?? 0),
            'cost' => round((float) ($agg->cost ?? 0), 2),
            'impressions' => (int) ($agg->impressions ?? 0),
            'from' => $fromDate,
            'to' => $toDate,
            'used_stored_bounds' => false,
        ];
    }

    /**
     * @param  iterable<int>  $domainIds
     * @param  Collection<int, Domain>|null  $domains
     * @return array{clicks: int, cost: float, impressions: int, from: string, to: string, used_stored_bounds: bool}
     */
    public function clickTotalsForDomainsReporting(
        iterable $domainIds,
        string $reportingFrom,
        string $reportingTo,
        string $reportingTz,
        ?Collection $domains = null,
    ): array {
        $ids = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [
                'clicks' => 0,
                'cost' => 0.0,
                'impressions' => 0,
                'from' => $reportingFrom,
                'to' => $reportingTo,
                'used_stored_bounds' => false,
            ];
        }

        $domains ??= Domain::query()
            ->whereIn('id', $ids)
            ->with('googleAdsAccount')
            ->get()
            ->keyBy('id');

        $clicks = 0;
        $cost = 0.0;
        $impressions = 0;
        $googleFrom = $reportingFrom;
        $googleTo = $reportingTo;

        /** @var array<int, list<array{clicks: int, cost: float, impressions: int}>> $byAccount */
        $byAccount = [];
        /** @var list<array{clicks: int, cost: float, impressions: int}> $noAccount */
        $noAccount = [];

        foreach ($ids as $domainId) {
            $domain = $domains->get($domainId);
            $googleTz = UserTimezone::isValid($domain?->googleAdsAccount?->time_zone)
                ? $domain->googleAdsAccount->time_zone
                : $reportingTz;
            [$fromDate, $toDate] = UserTimezone::googleMetricDateBounds($reportingFrom, $reportingTo, $reportingTz, $googleTz);
            $googleFrom = min($googleFrom, $fromDate);
            $googleTo = max($googleTo, $toDate);
            $totals = $this->clickTotalsForDomain((int) $domainId, $fromDate, $toDate);
            $entry = [
                'clicks' => (int) ($totals['clicks'] ?? 0),
                'cost' => (float) ($totals['cost'] ?? 0),
                'impressions' => (int) ($totals['impressions'] ?? 0),
            ];
            $accountId = (int) ($domain?->google_ads_account_id ?? 0);
            if ($accountId > 0) {
                $byAccount[$accountId][] = $entry;
            } else {
                $noAccount[] = $entry;
            }
        }

        foreach ($byAccount as $entries) {
            // Legacy sync stored full account totals on every linked domain. If every
            // domain under the same account still has identical totals, count once.
            $uniqueFingerprints = collect($entries)
                ->map(fn ($e) => $e['clicks'].'|'.round($e['cost'], 2).'|'.$e['impressions'])
                ->unique()
                ->count();
            if (count($entries) > 1 && $uniqueFingerprints === 1) {
                $clicks += $entries[0]['clicks'];
                $cost += $entries[0]['cost'];
                $impressions += $entries[0]['impressions'];
                continue;
            }

            foreach ($entries as $entry) {
                $clicks += $entry['clicks'];
                $cost += $entry['cost'];
                $impressions += $entry['impressions'];
            }
        }

        foreach ($noAccount as $entry) {
            $clicks += $entry['clicks'];
            $cost += $entry['cost'];
            $impressions += $entry['impressions'];
        }

        return [
            'clicks' => $clicks,
            'cost' => round($cost, 2),
            'impressions' => $impressions,
            'from' => $googleFrom,
            'to' => $googleTo,
            'used_stored_bounds' => false,
        ];
    }

    /**
     * @param  iterable<int>  $domainIds
     * @param  Collection<int, Domain>|null  $domains
     * @return \Illuminate\Support\Collection<string, object{metric_date: string, clicks: int}>
     */
    public function dailyClicksByDateForDomainsReporting(
        iterable $domainIds,
        string $reportingFrom,
        string $reportingTo,
        string $reportingTz,
        ?Collection $domains = null,
    ) {
        $ids = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $domains ??= Domain::query()
            ->whereIn('id', $ids)
            ->with('googleAdsAccount')
            ->get()
            ->keyBy('id');

        $merged = collect();

        foreach ($ids as $domainId) {
            $domain = $domains->get($domainId);
            $googleTz = UserTimezone::isValid($domain?->googleAdsAccount?->time_zone)
                ? $domain->googleAdsAccount->time_zone
                : $reportingTz;
            [$fromDate, $toDate] = UserTimezone::googleMetricDateBounds($reportingFrom, $reportingTo, $reportingTz, $googleTz);
            $rows = $this->dailyClicksByDate((int) $domainId, $fromDate, $toDate);

            foreach ($rows as $date => $row) {
                $existing = $merged->get($date);
                $merged->put($date, (object) [
                    'metric_date' => $date,
                    'clicks' => (int) ($existing->clicks ?? 0) + (int) ($row->clicks ?? 0),
                ]);
            }
        }

        return $merged;
    }

    /**
     * @param  iterable<int>  $domainIds
     * @return \Illuminate\Support\Collection<string, object{metric_date: string, clicks: int}>
     */
    public function dailyClicksByDateForDomains(iterable $domainIds, string $fromDate, string $toDate)
    {
        $ids = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        if ($ids->count() === 1) {
            return $this->dailyClicksByDate((int) $ids->first(), $fromDate, $toDate);
        }

        return GoogleAdsCampaignDailyMetric::query()
            ->whereIn('domain_id', $ids)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->selectRaw('metric_date, SUM(clicks) as clicks')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->metric_date)->toDateString());
    }

    public function shouldRefresh(Domain $domain, string $fromDate, string $toDate): bool
    {
        if (! $domain->google_ads_account_id) {
            return false;
        }

        $hasRangeMetrics = GoogleAdsCampaignDailyMetric::query()
            ->where('domain_id', $domain->id)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->exists();

        if (! $hasRangeMetrics) {
            return true;
        }

        $staleMinutes = max(5, (int) config('promotix.google_ads_sync_stale_minutes', 15));

        if (! $domain->ads_synced_at) {
            return true;
        }

        $today = Carbon::now()->toDateString();
        if ($today >= $fromDate && $today <= $toDate && $domain->ads_synced_at->toDateString() < $today) {
            return true;
        }

        return $domain->ads_synced_at->lt(now()->subMinutes($staleMinutes));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchDailyFromGoogle(GoogleAdsAccount $account, string $fromDate, string $toDate, ?string $hostname = null): array
    {
        $connection = $account->connection;
        if (! $connection) {
            $this->lastMessage = 'Google connection missing for this account.';

            return [];
        }

        $this->connectionApi->refreshAccessToken($connection);
        $connection->refresh();

        $baseHeaders = $this->connectionApi->apiHeaders($connection, forceRefresh: true);
        if (! $baseHeaders) {
            $refreshError = $this->connectionApi->lastRefreshError;
            $this->lastMessage = $refreshError
                ?: 'Could not build Google API headers (reconnect Gmail / check GOOGLE_ADS_* env).';

            return [];
        }

        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $fromStr = $fromDate;
        $toStr = $toDate;

        $headerAttempts = [array_merge($baseHeaders, [])];
        $loginId = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: $this->connectionApi->loginCustomerId()));
        if ($loginId !== '') {
            $withMcc = $baseHeaders;
            $withMcc['login-customer-id'] = $loginId;
            array_unshift($headerAttempts, $withMcc);
        }

        $hostname = $hostname !== null ? trim($hostname) : null;
        if ($hostname === '') {
            $hostname = null;
        }

        foreach ($headerAttempts as $index => $headers) {
            Log::info('Google Ads domain metrics sync: API attempt', [
                'attempt' => $index + 1,
                'customer_id' => $account->customer_id,
                'login_customer_id' => $headers['login-customer-id'] ?? null,
                'hostname_filter' => $hostname,
            ]);

            $rows = $this->metrics->dailyCampaignMetrics($account, $version, $headers, $fromStr, $toStr, $hostname);
            if ($rows !== []) {
                Log::info('Google Ads domain metrics sync: API attempt succeeded', [
                    'attempt' => $index + 1,
                    'rows' => count($rows),
                    'hostname_filter' => $hostname,
                ]);

                return $rows;
            }
        }

        $this->lastApiError = $this->metrics->lastApiError;
        $this->lastMessage = $this->lastApiError
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
