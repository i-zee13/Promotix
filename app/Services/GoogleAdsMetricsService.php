<?php

namespace App\Services;

use App\Models\GoogleAdsAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsMetricsService
{
    public ?string $lastApiError = null;

    /**
     * Period totals per campaign (aggregated from daily rows).
     *
     * @return list<array<string, mixed>>
     */
    public function campaignMetrics(
        GoogleAdsAccount $account,
        string $apiVersion,
        array $headers,
        string $fromDate,
        string $toDate,
        ?string $hostnameFilter = null
    ): array {
        $daily = $this->dailyCampaignMetrics($account, $apiVersion, $headers, $fromDate, $toDate, $hostnameFilter);
        if ($daily === []) {
            return [];
        }

        $byCampaign = [];
        foreach ($daily as $row) {
            $id = (string) ($row['campaign_id'] ?? '');
            if ($id === '') {
                continue;
            }

            if (! isset($byCampaign[$id])) {
                $byCampaign[$id] = $row;
                $byCampaign[$id]['phone_calls'] = 0;

                continue;
            }

            $byCampaign[$id]['clicks'] += (int) ($row['clicks'] ?? 0);
            $byCampaign[$id]['impressions'] += (int) ($row['impressions'] ?? 0);
            $byCampaign[$id]['cost'] = round((float) $byCampaign[$id]['cost'] + (float) ($row['cost'] ?? 0), 2);
            $byCampaign[$id]['conversions'] += (float) ($row['conversions'] ?? 0);
            $byCampaign[$id]['total'] = $byCampaign[$id]['clicks'];
            $byCampaign[$id]['valid'] = $byCampaign[$id]['clicks'];
            $clicks = (int) $byCampaign[$id]['clicks'];
            $byCampaign[$id]['cpc'] = $clicks > 0 ? round((float) $byCampaign[$id]['cost'] / $clicks, 2) : 0;
        }

        return array_values($byCampaign);
    }

    /**
     * Per-campaign, per-day metrics (stored in DB after domain connect).
     *
     * @return list<array<string, mixed>>
     */
    public function dailyCampaignMetrics(
        GoogleAdsAccount $account,
        string $apiVersion,
        array $headers,
        string $fromDate,
        string $toDate,
        ?string $hostnameFilter = null
    ): array {
        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '' || (bool) $account->is_manager) {
            return [];
        }

        $query = "SELECT campaign.id, campaign.name, campaign.status, segments.date, metrics.clicks, metrics.impressions, metrics.cost_micros, metrics.average_cpc, metrics.conversions, metrics.ctr FROM campaign WHERE segments.date BETWEEN '{$fromDate}' AND '{$toDate}' AND campaign.status != 'REMOVED' ORDER BY segments.date DESC, metrics.clicks DESC LIMIT 5000";

        $res = $this->searchStream($apiVersion, $customerId, $query, $headers, 'daily_campaign_metrics');
        if (! $res->successful()) {
            return [];
        }

        $rows = [];
        foreach ($this->parseRows($res->json()) as $row) {
            $campaign = $row['campaign'] ?? [];
            $metrics = $row['metrics'] ?? [];
            $segments = $row['segments'] ?? [];
            if (! is_array($campaign) || ! is_array($metrics)) {
                continue;
            }

            $clicks = (int) ($metrics['clicks'] ?? 0);
            $costMicros = (int) ($metrics['costMicros'] ?? $metrics['cost_micros'] ?? 0);
            $avgCpcMicros = (int) ($metrics['averageCpc'] ?? $metrics['average_cpc'] ?? 0);
            $metricDate = (string) ($segments['date'] ?? '');

            $rows[] = [
                'campaign_id' => (string) ($campaign['id'] ?? ''),
                'campaign' => (string) ($campaign['name'] ?? 'Campaign'),
                'status' => (string) ($campaign['status'] ?? ''),
                'metric_date' => $metricDate,
                'clicks' => $clicks,
                'impressions' => (int) ($metrics['impressions'] ?? 0),
                'cost' => round($costMicros / 1_000_000, 2),
                'cpc' => $avgCpcMicros > 0 ? round($avgCpcMicros / 1_000_000, 2) : ($clicks > 0 ? round(($costMicros / 1_000_000) / $clicks, 2) : 0),
                'conversions' => (float) ($metrics['conversions'] ?? 0),
                'phone_calls' => 0,
                'ctr' => round((float) ($metrics['ctr'] ?? 0) * 100, 2),
            ];
        }

        if ($hostnameFilter !== null && $hostnameFilter !== '') {
            $hostRows = $this->campaignIdsForHostnameQuery($customerId, $apiVersion, $headers, $hostnameFilter, $fromDate, $toDate);
            // null = landing-page lookup failed — keep account-wide rows instead of wiping clicks.
            // [] = lookup succeeded but no campaigns serve this hostname — return empty so the
            // caller can decide (e.g. sole linked domain retries without hostname).
            if ($hostRows === null) {
                Log::warning('Google Ads hostname filter lookup failed; using account-wide campaign metrics', [
                    'customer_id' => $customerId,
                    'hostname_filter' => $hostnameFilter,
                    'date_from' => $fromDate,
                    'date_to' => $toDate,
                    'rows_kept' => count($rows),
                    'api_error' => $this->lastApiError,
                ]);
            } elseif ($hostRows === []) {
                Log::info('Google Ads daily campaign metrics hostname filter matched no campaigns', [
                    'customer_id' => $customerId,
                    'hostname_filter' => $hostnameFilter,
                    'date_from' => $fromDate,
                    'date_to' => $toDate,
                    'rows_before_filter' => count($rows),
                ]);

                return [];
            } else {
                $rows = array_values(array_filter($rows, fn ($r) => in_array($r['campaign_id'], $hostRows, true)));
            }
        }

        Log::info('Google Ads daily campaign metrics parsed', [
            'customer_id' => $customerId,
            'login_customer_id' => $headers['login-customer-id'] ?? null,
            'date_from' => $fromDate,
            'date_to' => $toDate,
            'rows_parsed' => count($rows),
            'hostname_filter' => $hostnameFilter,
        ]);

        return $rows;
    }

    /**
     * Campaign IDs whose landing pages include the given hostname.
     *
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    public function campaignIdsForHostname(
        GoogleAdsAccount $account,
        string $apiVersion,
        array $headers,
        string $hostname,
        string $fromDate,
        string $toDate,
    ): array {
        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '' || (bool) $account->is_manager) {
            return [];
        }

        return $this->campaignIdsForHostnameQuery($customerId, $apiVersion, $headers, $hostname, $fromDate, $toDate) ?? [];
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<string>|null  Campaign IDs, empty list when none match, or null when the API call failed
     */
    private function campaignIdsForHostnameQuery(
        string $customerId,
        string $apiVersion,
        array $headers,
        string $hostname,
        string $fromDate,
        string $toDate
    ): ?array {
        $query = "SELECT campaign.id, landing_page_view.unexpanded_final_url FROM landing_page_view WHERE segments.date BETWEEN '{$fromDate}' AND '{$toDate}' LIMIT 5000";

        $res = $this->searchStream($apiVersion, $customerId, $query, $headers, 'landing_page_hostname');
        if (! $res->successful()) {
            return null;
        }

        $ids = [];
        foreach ($this->parseRows($res->json()) as $row) {
            $url = (string) ($row['landingPageView']['unexpandedFinalUrl'] ?? $row['landingPageView']['unexpanded_final_url'] ?? '');
            if ($url === '') {
                continue;
            }
            if ($this->urlMatchesHostname($url, $hostname)) {
                $id = (string) ($row['campaign']['id'] ?? '');
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private function urlMatchesHostname(string $url, string $hostname): bool
    {
        $needle = strtolower(trim($hostname));
        $needle = preg_replace('/^www\./', '', $needle) ?? $needle;
        if ($needle === '') {
            return false;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);
        if (is_string($urlHost) && $urlHost !== '') {
            $urlHost = strtolower($urlHost);
            $urlHost = preg_replace('/^www\./', '', $urlHost) ?? $urlHost;
            if ($urlHost === $needle || str_ends_with($urlHost, '.'.$needle)) {
                return true;
            }
        }

        return str_contains(strtolower($url), $needle);
    }

    /**
     * GCLIDs for a campaign in a date range (click_view). Used to match tag visits to Google campaigns.
     *
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    public function gclidsForCampaign(
        GoogleAdsAccount $account,
        string $apiVersion,
        array $headers,
        string $campaignId,
        string $fromDate,
        string $toDate,
    ): array {
        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        $campaignId = preg_replace('/\D+/', '', $campaignId);
        if ($customerId === '' || $campaignId === '' || (bool) $account->is_manager) {
            return [];
        }

        $queries = [
            "SELECT click_view.gclid FROM click_view WHERE campaign.id = {$campaignId} AND segments.date BETWEEN '{$fromDate}' AND '{$toDate}' AND click_view.gclid != '' LIMIT 10000",
            "SELECT click_view.gclid, campaign.id FROM click_view WHERE campaign.id = {$campaignId} AND segments.date BETWEEN '{$fromDate}' AND '{$toDate}' LIMIT 10000",
        ];

        foreach ($queries as $index => $query) {
            $res = $this->searchStream($apiVersion, $customerId, $query, $headers, 'campaign_gclids_' . $index);
            if (! $res->successful()) {
                continue;
            }

            $gclids = [];
            foreach ($this->parseRows($res->json()) as $row) {
                $gclid = (string) ($row['clickView']['gclid'] ?? $row['click_view']['gclid'] ?? '');
                if ($gclid !== '') {
                    $gclids[$gclid] = true;
                }
            }

            if ($gclids !== []) {
                return array_keys($gclids);
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $headers
     */
    private function searchStream(
        string $apiVersion,
        string $customerId,
        string $query,
        array $headers,
        string $context = 'search_stream'
    ): \Illuminate\Http\Client\Response {
        $safeHeaders = $headers;
        unset($safeHeaders['Authorization']);

        Log::info('Google Ads API → request', [
            'context' => $context,
            'customer_id' => $customerId,
            'api_version' => $apiVersion,
            'login_customer_id' => $safeHeaders['login-customer-id'] ?? null,
            'query' => $query,
        ]);

        $res = Http::timeout(45)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($apiVersion, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        $body = (string) $res->body();
        $parsedCount = $res->successful() ? count($this->parseRows($res->json())) : 0;

        if (! $res->successful()) {
            $this->lastApiError = 'HTTP ' . $res->status() . ': ' . Str::limit($this->extractErrorMessage($body), 280);

            Log::warning('Google Ads API ← error response', [
                'context' => $context,
                'customer_id' => $customerId,
                'status' => $res->status(),
                'login_customer_id' => $safeHeaders['login-customer-id'] ?? null,
                'error_summary' => $this->lastApiError,
                'body' => Str::limit($body, 4000),
            ]);
        } else {
            $this->lastApiError = null;

            Log::info('Google Ads API ← success response', [
                'context' => $context,
                'customer_id' => $customerId,
                'status' => $res->status(),
                'login_customer_id' => $safeHeaders['login-customer-id'] ?? null,
                'parsed_rows' => $parsedCount,
                'body_preview' => Str::limit($body, 2500),
            ]);
        }

        return $res;
    }

    private function extractErrorMessage(string $body): string
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return $body;
        }

        $message = (string) ($json['error']['message'] ?? '');
        if ($message !== '') {
            return $message;
        }

        foreach (($json[0]['error']['details'] ?? []) as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            foreach (($detail['errors'] ?? []) as $err) {
                if (is_array($err) && ! empty($err['message'])) {
                    return (string) $err['message'];
                }
            }
        }

        return $body;
    }

    /**
     * @param mixed $payload
     * @return list<array<string, mixed>>
     */
    private function parseRows($payload): array
    {
        $rows = [];
        if (! is_array($payload)) {
            return [];
        }
        foreach ($payload as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            foreach (($chunk['results'] ?? []) as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    private function googleAdsUrl(string $version, string $path): string
    {
        return 'https://googleads.googleapis.com/' . trim($version) . '/' . ltrim($path, '/');
    }
}
