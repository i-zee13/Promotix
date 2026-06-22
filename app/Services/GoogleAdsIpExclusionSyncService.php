<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GoogleAdsIpExclusionSyncService
{
    public function __construct(
        private readonly GoogleAdsConnectionService $connectionApi,
        private readonly GoogleAdsMetricsService $metrics,
    ) {
    }

    public function syncPendingForDomain(Domain $domain, int $limit = 25): int
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return 0;
        }

        $rows = DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domain->id)
            ->where('sync_status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $synced = 0;
        foreach ($rows as $row) {
            if ($this->syncRow($domain, (string) $row->ip, (int) $row->id)) {
                $synced++;
            }
        }

        return $synced;
    }

    public function syncRow(Domain $domain, string $ip, ?int $rowId = null): bool
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return false;
        }

        $ip = trim($ip);
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->markRow($domain->id, $ip, 'failed', 'Invalid IP address.');

            return false;
        }

        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;
        if (! $account || (bool) $account->is_manager) {
            $this->markRow($domain->id, $ip, 'skipped', 'Domain has no linked Google Ads customer account.');

            return false;
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            $this->markRow($domain->id, $ip, 'failed', 'Google Ads API credentials unavailable.');

            return false;
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '') {
            $this->markRow($domain->id, $ip, 'failed', 'Missing Google Ads customer id.');

            return false;
        }

        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $campaignIds = $this->resolveCampaignIds($domain, $account, $customerId, $version, $headers);

        if ($campaignIds === []) {
            $this->markRow($domain->id, $ip, 'skipped', 'No Google Ads campaigns found for this domain hostname yet. Run metrics sync first.');

            return false;
        }

        $failures = [];
        $successes = 0;

        foreach ($campaignIds as $campaignId) {
            if ($this->ipAlreadyBlockedOnCampaign($customerId, $campaignId, $version, $headers, $ip)) {
                $successes++;

                continue;
            }

            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/campaignCriteria:mutate"), [
                    'operations' => [[
                        'create' => [
                            'campaign' => "customers/{$customerId}/campaigns/{$campaignId}",
                            'negative' => true,
                            'ipBlock' => [
                                'ipAddress' => $ip,
                            ],
                        ],
                    ]],
                ]);

            if ($response->successful()) {
                $successes++;
                Log::info('Google Ads campaign IP exclusion synced', [
                    'domain_id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'customer_id' => $customerId,
                    'campaign_id' => $campaignId,
                    'ip' => $ip,
                ]);

                continue;
            }

            $error = $this->extractErrorMessage((string) $response->body());
            if ($this->isBenignDuplicate($error)) {
                $successes++;

                continue;
            }

            $failures[] = "campaign {$campaignId}: " . Str::limit($error, 300);
        }

        if ($successes > 0) {
            $this->markRow($domain->id, $ip, 'synced', $failures === [] ? null : implode(' | ', $failures), now());

            return true;
        }

        $this->markRow($domain->id, $ip, 'failed', implode(' | ', $failures) ?: 'Could not add IP to any campaign.');

        return false;
    }

    /**
     * Campaigns that advertise this domain's hostname (from stored metrics or Google Ads API).
     *
     * @return list<string>
     */
    private function resolveCampaignIds(
        Domain $domain,
        GoogleAdsAccount $account,
        string $customerId,
        string $version,
        array $headers,
    ): array {
        $ids = [];

        if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $ids = DB::table('google_ads_campaign_daily_metrics')
                ->where('domain_id', $domain->id)
                ->whereNotNull('campaign_id')
                ->where('campaign_id', '!=', '')
                ->distinct()
                ->pluck('campaign_id')
                ->map(fn ($id) => preg_replace('/\D+/', '', (string) $id))
                ->filter()
                ->values()
                ->all();
        }

        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        $hostname = strtolower(trim((string) $domain->hostname));
        if ($hostname === '') {
            return [];
        }

        $to = Carbon::now()->toDateString();
        $from = Carbon::now()->subDays(30)->toDateString();

        return $this->metrics->campaignIdsForHostname(
            $account,
            $version,
            $headers,
            $hostname,
            $from,
            $to,
        );
    }

    /** @return array<string, string>|null */
    private function headersForAccount(GoogleAdsAccount $account): ?array
    {
        $connection = $account->connection;
        if (! $connection) {
            return null;
        }

        $this->connectionApi->refreshAccessToken($connection);
        $connection->refresh();

        $headers = $this->connectionApi->apiHeaders($connection, forceRefresh: true);
        if (! $headers) {
            return null;
        }

        $loginId = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: $this->connectionApi->loginCustomerId()));
        if ($loginId !== '') {
            $headers['login-customer-id'] = $loginId;
        }

        return $headers;
    }

    /** @param  array<string, string>  $headers */
    private function ipAlreadyBlockedOnCampaign(
        string $customerId,
        string $campaignId,
        string $version,
        array $headers,
        string $ip,
    ): bool {
        $query = "SELECT campaign_criterion.resource_name, campaign_criterion.ip_block.ip_address FROM campaign_criterion WHERE campaign_criterion.type = IP_BLOCK AND campaign_criterion.negative = TRUE AND campaign.id = {$campaignId}";

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            return false;
        }

        foreach ($this->parseRows($response->json()) as $row) {
            $block = $row['campaignCriterion']['ipBlock']
                ?? $row['campaign_criterion']['ip_block']
                ?? [];
            $existing = (string) ($block['ipAddress'] ?? $block['ip_address'] ?? '');
            if ($existing === $ip) {
                return true;
            }
        }

        return false;
    }

    private function markRow(int $domainId, string $ip, string $status, ?string $error, ?\Illuminate\Support\Carbon $syncedAt = null): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return;
        }

        DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domainId)
            ->where('ip', $ip)
            ->update([
                'sync_status' => $status,
                'sync_error' => $error,
                'synced_at' => $syncedAt,
                'updated_at' => now(),
            ]);
    }

    private function isBenignDuplicate(string $error): bool
    {
        $needles = [
            'DUPLICATE',
            'ALREADY_EXISTS',
            'already exists',
            'CRITERION_ALREADY_EXISTS',
            'Resource has been deleted',
        ];

        foreach ($needles as $needle) {
            if (stripos($error, $needle) !== false) {
                return true;
            }
        }

        return false;
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

    /** @return list<array<string, mixed>> */
    private function parseRows(mixed $payload): array
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
