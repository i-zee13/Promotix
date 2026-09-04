<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use App\Support\GoogleIpBlockFormatter;
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
            ->where('sync_status', 'pending');

        if (Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
            $rows->where('is_active', true);
        }

        $rows = $rows->orderBy('id')
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
        $googleIp = GoogleIpBlockFormatter::normalize($ip);
        if ($ip === '' || $googleIp === null) {
            $this->markRow($domain->id, $ip, 'failed', 'Invalid IP or range. Use single IP, CIDR (e.g. 13.0.0.0/8), or wildcard (e.g. 216.67.176.* → converted to /24).', null, $rowId);

            return false;
        }

        $ip = $googleIp;

        if ($rowId !== null && Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
            $activeRow = DB::table('google_ads_ip_exclusions')->where('id', $rowId)->first();
            if ($activeRow && ! (bool) ($activeRow->is_active ?? true)) {
                return false;
            }
        }

        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;
        if (! $account || (bool) $account->is_manager) {
            $this->markRow($domain->id, $ip, 'skipped', 'Domain has no linked Google Ads customer account.', null, $rowId);

            return false;
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            $this->markRow($domain->id, $ip, 'failed', 'Google Ads API credentials unavailable.', null, $rowId);

            return false;
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '') {
            $this->markRow($domain->id, $ip, 'failed', 'Missing Google Ads customer id.', null, $rowId);

            return false;
        }

        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $campaignIds = $this->resolveCampaignIds($domain, $account, $customerId, $version, $headers);

        $failures = [];
        $skipped = [];
        $successes = 0;

        foreach ($campaignIds as $campaignId) {
            if ($this->ipAlreadyBlockedOnCampaign($customerId, $campaignId, $version, $headers, $googleIp)) {
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
                                'ipAddress' => $googleIp,
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

            if ($this->isCampaignLimitError($error)) {
                $failures[] = "campaign {$campaignId}: IP exclusion list is full (500/500 max per campaign).";

                continue;
            }

            if ($this->isSkippableCampaignError($error)) {
                $skipped[] = "campaign {$campaignId}: " . Str::limit($error, 200);

                continue;
            }

            $failures[] = "campaign {$campaignId}: " . Str::limit($error, 300);
        }

        $onCampaigns = $this->verifyIpOnCampaigns($domain, $googleIp);
        $onAccount = $this->ipAlreadyBlockedOnAccount($customerId, $version, $headers, $googleIp);

        if ($onCampaigns === [] && ! $onAccount) {
            $accountResult = $this->syncIpAtAccountLevel($customerId, $ip, $googleIp, $version, $headers);
            if ($accountResult['ok']) {
                $onAccount = $this->ipAlreadyBlockedOnAccount($customerId, $version, $headers, $googleIp);
            } elseif ($accountResult['error']) {
                $failures[] = $accountResult['error'];
            }
        }

        if ($onCampaigns !== []) {
            $campaignList = implode(', ', array_unique(array_column($onCampaigns, 'campaign_id')));
            $note = "Confirmed on campaign(s): {$campaignList}";
            $extra = array_merge($skipped, $failures);
            if ($extra !== []) {
                $note .= ' | ' . implode(' | ', $extra);
            }
            $this->markRow($domain->id, $ip, 'synced', $note, now(), $rowId);

            return true;
        }

        if ($onAccount) {
            $note = 'Confirmed at Google Ads account level (not in campaign list — campaign may be full at 500/500 or unsupported type).';
            $extra = array_merge($skipped, $failures);
            if ($extra !== []) {
                $note .= ' | ' . implode(' | ', $extra);
            }
            $this->markRow($domain->id, $ip, 'synced', $note, now(), $rowId);

            return true;
        }

        $combined = array_merge($failures, $skipped);
        $message = $combined !== [] ? implode(' | ', $combined) : 'Google did not confirm the IP on campaign or account exclusions.';
        if ($this->allPermissionErrors($combined)) {
            $message .= ' Reconnect Google Ads in Integrations with Standard access, or ensure campaigns belong to this linked account.';
        }

        $this->markRow($domain->id, $ip, 'failed', $message, null, $rowId);

        return false;
    }

    /** @return array{ok: bool, error: ?string} */
    private function syncIpAtAccountLevel(
        string $customerId,
        string $rawIp,
        string $googleIp,
        string $version,
        array $headers,
    ): array {
        if ($this->ipAlreadyBlockedOnAccount($customerId, $version, $headers, $rawIp)) {
            return ['ok' => true, 'error' => null];
        }

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/customerNegativeCriteria:mutate"), [
                'operations' => [[
                    'create' => [
                        'ipBlock' => [
                            'ipAddress' => $googleIp,
                        ],
                    ],
                ]],
            ]);

        if ($response->successful()) {
            Log::info('Google Ads account-level IP exclusion synced', [
                'customer_id' => $customerId,
                'ip' => $rawIp,
            ]);

            return ['ok' => true, 'error' => null];
        }

        $error = $this->extractErrorMessage((string) $response->body());
        if ($this->isBenignDuplicate($error)) {
            return ['ok' => true, 'error' => null];
        }

        return ['ok' => false, 'error' => 'Account-level: ' . Str::limit($error, 300)];
    }

    /** @param  array<string, string>  $headers */
    private function ipAlreadyBlockedOnAccount(
        string $customerId,
        string $version,
        array $headers,
        string $ip,
    ): bool {
        $query = 'SELECT customer_negative_criterion.resource_name, customer_negative_criterion.ip_block.ip_address FROM customer_negative_criterion WHERE customer_negative_criterion.type = IP_BLOCK';

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            return false;
        }

        foreach ($this->parseRows($response->json()) as $row) {
            $block = $row['customerNegativeCriterion']['ipBlock']
                ?? $row['customer_negative_criterion']['ip_block']
                ?? [];
            $existing = (string) ($block['ipAddress'] ?? $block['ip_address'] ?? '');
            if ($this->ipsMatch($existing, $ip)) {
                return true;
            }
        }

        return false;
    }

    private function formatIpForGoogle(string $ip): string
    {
        return GoogleIpBlockFormatter::normalize($ip) ?? $ip;
    }

    /**
     * @param  list<string>  $ips
     * @return array{synced: int, failed: int, invalid: list<string>, errors: list<string>}
     */
    public function syncManyIps(Domain $domain, array $ips, int $limit = 200): array
    {
        $ips = array_values(array_unique(array_filter(array_map('trim', $ips))));
        $ips = array_slice($ips, 0, max(1, $limit));

        $synced = 0;
        $failed = 0;
        $invalid = [];
        $errors = [];

        foreach ($ips as $ip) {
            if (GoogleIpBlockFormatter::normalize($ip) === null) {
                $invalid[] = $ip;

                continue;
            }

            if ($this->syncRow($domain, $ip)) {
                $synced++;
            } else {
                $failed++;
                $row = DB::table('google_ads_ip_exclusions')
                    ->where('domain_id', $domain->id)
                    ->where('ip', $ip)
                    ->first();
                if ($row?->sync_error) {
                    $errors[] = "{$ip}: {$row->sync_error}";
                }
            }
        }

        return compact('synced', 'failed', 'invalid', 'errors');
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
        $hostname = strtolower(trim((string) $domain->hostname));
        $to = Carbon::now()->toDateString();
        $from = Carbon::now()->subDays(30)->toDateString();

        $hostnameIds = $hostname !== ''
            ? $this->metrics->campaignIdsForHostname($account, $version, $headers, $hostname, $from, $to)
            : [];

        $metricIds = [];
        if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $metricIds = DB::table('google_ads_campaign_daily_metrics')
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

        $candidateIds = $hostnameIds !== []
            ? array_values(array_unique($hostnameIds))
            : array_values(array_unique($metricIds));

        if ($hostnameIds !== [] && $metricIds !== []) {
            $overlap = array_values(array_intersect($hostnameIds, $metricIds));
            if ($overlap !== []) {
                $candidateIds = $overlap;
            }
        }

        return $this->filterEligibleCampaignIds($customerId, $candidateIds, $version, $headers);
    }

    /**
     * Keep only active campaigns the API can read (drops removed / inaccessible IDs).
     *
     * @param  list<string>  $campaignIds
     * @return list<string>
     */
    private function filterEligibleCampaignIds(
        string $customerId,
        array $campaignIds,
        string $version,
        array $headers,
    ): array {
        $campaignIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => preg_replace('/\D+/', '', (string) $id),
            $campaignIds,
        ))));

        if ($campaignIds === []) {
            return [];
        }

        $chunks = array_chunk($campaignIds, 50);
        $eligible = [];

        foreach ($chunks as $chunk) {
            $inList = implode(',', array_map('intval', $chunk));
            $query = "SELECT campaign.id, campaign.status, campaign.advertising_channel_type FROM campaign WHERE campaign.id IN ({$inList}) AND campaign.status IN ('ENABLED', 'PAUSED')";

            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                    'query' => $query,
                ]);

            if (! $response->successful()) {
                continue;
            }

            foreach ($this->parseRows($response->json()) as $row) {
                $id = (string) ($row['campaign']['id'] ?? '');
                $channel = (string) ($row['campaign']['advertisingChannelType'] ?? $row['campaign']['advertising_channel_type'] ?? '');
                if ($id === '' || $this->channelLikelyUnsupportedForIpBlock($channel)) {
                    continue;
                }
                $eligible[] = $id;
            }
        }

        return array_values(array_unique($eligible));
    }

    private function channelLikelyUnsupportedForIpBlock(string $channel): bool
    {
        // Google Ads UI: campaign-level IP exclusions are NOT supported for these types.
        $unsupported = [
            'PERFORMANCE_MAX',
            'VIDEO',
            'DEMAND_GEN',
            'APP',
            'LOCAL',
            'LOCAL_SERVICES',
            'HOTEL',
            'TRAVEL',
            'SMART',
        ];

        return in_array(strtoupper(trim($channel)), $unsupported, true);
    }

    private function isCampaignLimitError(string $error): bool
    {
        $needles = [
            'Exceeded entity limit',
            'RESOURCE_LIMIT',
            'negative IP blocks per campaign',
            'Limit: 500',
        ];

        foreach ($needles as $needle) {
            if (stripos($error, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isSkippableCampaignError(string $error): bool
    {
        $needles = [
            'does not have permission',
            'PERMISSION_DENIED',
            'USER_PERMISSION_DENIED',
            'AUTHORIZATION_ERROR',
            'OPERATION_NOT_PERMITTED',
            'NOT_PERMITTED',
            'CANNOT_MODIFY',
            'Criterion type is not supported',
            'not supported for this campaign',
            'invalid argument',
            'INVALID_ARGUMENT',
            'OPERATION_NOT_PERMITTED_FOR_CAMPAIGN_TYPE',
            'CRITERION_NOT_SUPPORTED',
        ];

        foreach ($needles as $needle) {
            if (stripos($error, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $messages */
    private function allPermissionErrors(array $messages): bool
    {
        if ($messages === []) {
            return false;
        }

        foreach ($messages as $message) {
            if (! $this->isSkippableCampaignError($message)) {
                return false;
            }
        }

        return true;
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
            if ($this->ipsMatch($existing, $ip)) {
                return true;
            }
        }

        return false;
    }

    private function ipsMatch(string $a, string $b): bool
    {
        return GoogleIpBlockFormatter::matches($a, $b);
    }

    private function normalizeIpForCompare(string $ip): string
    {
        return GoogleIpBlockFormatter::normalize($ip) ?? trim($ip);
    }

    /**
     * Check whether an IP is blocked on any campaign for this domain (live Google Ads API).
     *
     * @return list<array{campaign_id: string, ip_address: string}>
     */
    public function verifyIpOnCampaigns(Domain $domain, string $ip): array
    {
        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;
        if (! $account || (bool) $account->is_manager) {
            return [];
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            return [];
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $campaignIds = $this->resolveCampaignIds($domain, $account, $customerId, $version, $headers);
        $found = [];

        foreach ($campaignIds as $campaignId) {
            $query = "SELECT campaign.id, campaign_criterion.ip_block.ip_address FROM campaign_criterion WHERE campaign_criterion.type = IP_BLOCK AND campaign_criterion.negative = TRUE AND campaign.id = {$campaignId}";

            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                    'query' => $query,
                ]);

            if (! $response->successful()) {
                continue;
            }

            foreach ($this->parseRows($response->json()) as $row) {
                $block = $row['campaignCriterion']['ipBlock'] ?? $row['campaign_criterion']['ip_block'] ?? [];
                $existing = (string) ($block['ipAddress'] ?? $block['ip_address'] ?? '');
                if ($this->ipsMatch($existing, $ip)) {
                    $found[] = [
                        'campaign_id' => (string) ($row['campaign']['id'] ?? $campaignId),
                        'ip_address' => $existing,
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * Remove an IP block from Google Ads campaigns and account-level exclusions.
     */
    public function removeRow(Domain $domain, string $ip, ?int $rowId = null): bool
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return false;
        }

        $googleIp = GoogleIpBlockFormatter::normalize(trim($ip));
        if ($googleIp === null) {
            return false;
        }

        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;
        if (! $account || (bool) $account->is_manager) {
            $this->markRowDisabled($domain->id, $googleIp, $rowId, 'Domain has no linked Google Ads customer account.');

            return false;
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            $this->markRowDisabled($domain->id, $googleIp, $rowId, 'Google Ads API credentials unavailable.');

            return false;
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '') {
            $this->markRowDisabled($domain->id, $googleIp, $rowId, 'Missing Google Ads customer id.');

            return false;
        }

        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $campaignIds = $this->resolveCampaignIds($domain, $account, $customerId, $version, $headers);
        $removed = 0;
        $errors = [];

        foreach ($campaignIds as $campaignId) {
            $resourceNames = $this->campaignIpBlockResourceNames($customerId, $campaignId, $version, $headers, $googleIp);
            foreach ($resourceNames as $resourceName) {
                $response = Http::timeout(30)
                    ->withHeaders($headers)
                    ->post($this->googleAdsUrl($version, "customers/{$customerId}/campaignCriteria:mutate"), [
                        'operations' => [['remove' => $resourceName]],
                    ]);

                if ($response->successful() || $this->isBenignDuplicate($this->extractErrorMessage((string) $response->body()))) {
                    $removed++;
                } else {
                    $errors[] = 'campaign ' . $campaignId . ': ' . Str::limit($this->extractErrorMessage((string) $response->body()), 200);
                }
            }
        }

        foreach ($this->accountIpBlockResourceNames($customerId, $version, $headers, $googleIp) as $resourceName) {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/customerNegativeCriteria:mutate"), [
                    'operations' => [['remove' => $resourceName]],
                ]);

            if ($response->successful() || $this->isBenignDuplicate($this->extractErrorMessage((string) $response->body()))) {
                $removed++;
            } else {
                $errors[] = 'account: ' . Str::limit($this->extractErrorMessage((string) $response->body()), 200);
            }
        }

        $stillOnCampaigns = $this->verifyIpOnCampaigns($domain, $googleIp);
        $stillOnAccount = $this->ipAlreadyBlockedOnAccount($customerId, $version, $headers, $googleIp);

        if ($stillOnCampaigns === [] && ! $stillOnAccount) {
            $this->markRowDisabled($domain->id, $googleIp, $rowId, $removed > 0 ? 'Removed from Google Ads.' : 'Block disabled locally.');

            return true;
        }

        $message = $errors !== []
            ? implode(' | ', $errors)
            : 'IP may still be blocked on Google Ads — try again or remove manually in Google Ads.';

        $this->markRow($domain->id, $googleIp, 'failed', $message);

        return false;
    }

    /** @return list<string> */
    private function campaignIpBlockResourceNames(
        string $customerId,
        string $campaignId,
        string $version,
        array $headers,
        string $ip,
    ): array {
        $query = "SELECT campaign_criterion.resource_name, campaign_criterion.ip_block.ip_address FROM campaign_criterion WHERE campaign_criterion.type = IP_BLOCK AND campaign_criterion.negative = TRUE AND campaign.id = {$campaignId}";

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $names = [];
        foreach ($this->parseRows($response->json()) as $row) {
            $block = $row['campaignCriterion']['ipBlock'] ?? $row['campaign_criterion']['ip_block'] ?? [];
            $existing = (string) ($block['ipAddress'] ?? $block['ip_address'] ?? '');
            $resourceName = (string) ($row['campaignCriterion']['resourceName'] ?? $row['campaign_criterion']['resource_name'] ?? '');
            if ($resourceName !== '' && $this->ipsMatch($existing, $ip)) {
                $names[] = $resourceName;
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function accountIpBlockResourceNames(
        string $customerId,
        string $version,
        array $headers,
        string $ip,
    ): array {
        $query = 'SELECT customer_negative_criterion.resource_name, customer_negative_criterion.ip_block.ip_address FROM customer_negative_criterion WHERE customer_negative_criterion.type = IP_BLOCK';

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $names = [];
        foreach ($this->parseRows($response->json()) as $row) {
            $block = $row['customerNegativeCriterion']['ipBlock'] ?? $row['customer_negative_criterion']['ip_block'] ?? [];
            $existing = (string) ($block['ipAddress'] ?? $block['ip_address'] ?? '');
            $resourceName = (string) ($row['customerNegativeCriterion']['resourceName'] ?? $row['customer_negative_criterion']['resource_name'] ?? '');
            if ($resourceName !== '' && $this->ipsMatch($existing, $ip)) {
                $names[] = $resourceName;
            }
        }

        return $names;
    }

    private function markRowDisabled(int $domainId, string $ip, ?int $rowId, ?string $note = null): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return;
        }

        $payload = [
            'sync_status' => 'disabled',
            'sync_error' => $note,
            'synced_at' => null,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
            $payload['is_active'] = false;
        }

        $query = DB::table('google_ads_ip_exclusions')->where('domain_id', $domainId);
        if ($rowId !== null) {
            $query->where('id', $rowId);
        } else {
            $query->where('ip', $ip);
        }

        $query->update($payload);
    }

    private function markRow(
        int $domainId,
        string $ip,
        string $status,
        ?string $error,
        ?\Illuminate\Support\Carbon $syncedAt = null,
        ?int $rowId = null,
    ): void {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return;
        }

        $payload = [
            'sync_status' => $status,
            'sync_error' => $error,
            'synced_at' => $syncedAt,
            'updated_at' => now(),
        ];

        $normalized = GoogleIpBlockFormatter::normalize($ip);
        if ($normalized !== null) {
            // Keep stored IP aligned with Google form so later updates match.
            $payload['ip'] = $normalized;
        }

        if (Schema::hasColumn('google_ads_ip_exclusions', 'is_active') && $status === 'synced') {
            $payload['is_active'] = true;
        }

        $query = DB::table('google_ads_ip_exclusions')->where('domain_id', $domainId);

        if ($rowId !== null) {
            $query->where('id', $rowId);
        } else {
            $needle = $normalized ?? $ip;
            $existing = DB::table('google_ads_ip_exclusions')
                ->where('domain_id', $domainId)
                ->get()
                ->first(fn ($row) => GoogleIpBlockFormatter::matches((string) ($row->ip ?? ''), $needle));

            if ($existing) {
                $query->where('id', $existing->id);
            } else {
                $query->where('ip', $ip);
            }
        }

        $query->update($payload);
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
