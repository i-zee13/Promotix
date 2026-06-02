<?php

namespace App\Services;

use App\Models\GoogleAdsAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsMetricsService
{
    /**
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
        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '' || (bool) $account->is_manager) {
            return [];
        }

        $query = "SELECT campaign.id, campaign.name, campaign.status, metrics.clicks, metrics.impressions, metrics.cost_micros, metrics.average_cpc, metrics.conversions, metrics.phone_calls, metrics.ctr FROM campaign WHERE segments.date BETWEEN '{$fromDate}' AND '{$toDate}' AND campaign.status != 'REMOVED' ORDER BY metrics.clicks DESC LIMIT 100";

        $res = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($apiVersion, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $res->successful()) {
            Log::warning('Google Ads campaign metrics failed', [
                'customer_id' => $customerId,
                'status' => $res->status(),
                'body' => Str::limit($res->body(), 500),
            ]);

            return [];
        }

        $rows = [];
        foreach ($this->parseRows($res->json()) as $row) {
            $campaign = $row['campaign'] ?? [];
            $metrics = $row['metrics'] ?? [];
            if (! is_array($campaign) || ! is_array($metrics)) {
                continue;
            }

            $clicks = (int) ($metrics['clicks'] ?? 0);
            $costMicros = (int) ($metrics['costMicros'] ?? $metrics['cost_micros'] ?? 0);
            $avgCpcMicros = (int) ($metrics['averageCpc'] ?? $metrics['average_cpc'] ?? 0);

            $rows[] = [
                'campaign_id' => (string) ($campaign['id'] ?? ''),
                'campaign' => (string) ($campaign['name'] ?? 'Campaign'),
                'status' => (string) ($campaign['status'] ?? ''),
                'clicks' => $clicks,
                'impressions' => (int) ($metrics['impressions'] ?? 0),
                'cost' => round($costMicros / 1_000_000, 2),
                'cpc' => $avgCpcMicros > 0 ? round($avgCpcMicros / 1_000_000, 2) : ($clicks > 0 ? round(($costMicros / 1_000_000) / $clicks, 2) : 0),
                'conversions' => (float) ($metrics['conversions'] ?? 0),
                'phone_calls' => (int) ($metrics['phoneCalls'] ?? $metrics['phone_calls'] ?? 0),
                'ctr' => round((float) ($metrics['ctr'] ?? 0) * 100, 2),
                'total' => $clicks,
                'invalid' => 0,
                'valid' => $clicks,
                'source' => 'google_ads',
            ];
        }

        if ($hostnameFilter !== null && $hostnameFilter !== '') {
            $hostRows = $this->campaignIdsForHostname($customerId, $apiVersion, $headers, $hostnameFilter, $fromDate, $toDate);
            if ($hostRows !== []) {
                $rows = array_values(array_filter($rows, fn ($r) => in_array($r['campaign_id'], $hostRows, true)));
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function campaignIdsForHostname(
        string $customerId,
        string $apiVersion,
        array $headers,
        string $hostname,
        string $fromDate,
        string $toDate
    ): array {
        $host = strtolower(trim($hostname));
        $query = "SELECT campaign.id, landing_page_view.unexpanded_final_url FROM landing_page_view WHERE segments.date BETWEEN '{$fromDate}' AND '{$toDate}' LIMIT 500";

        $res = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($apiVersion, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $res->successful()) {
            return [];
        }

        $ids = [];
        foreach ($this->parseRows($res->json()) as $row) {
            $url = (string) ($row['landingPageView']['unexpandedFinalUrl'] ?? $row['landingPageView']['unexpanded_final_url'] ?? '');
            if ($url === '') {
                continue;
            }
            if (str_contains(strtolower($url), $host)) {
                $id = (string) ($row['campaign']['id'] ?? '');
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
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
