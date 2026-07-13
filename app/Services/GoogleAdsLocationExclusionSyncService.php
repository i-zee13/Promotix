<?php

namespace App\Services;

use App\Jobs\SyncGoogleAdsLocationExclusionJob;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\GoogleAdsAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GoogleAdsLocationExclusionSyncService
{
    public function __construct(
        private readonly GoogleAdsConnectionService $connectionApi,
        private readonly GoogleAdsMetricsService $metrics,
        private readonly GoogleAdsGeoTargetResolver $geoResolver,
    ) {}

    /**
     * Persist detection-panel blocked geos and queue Google Ads location exclusions.
     *
     * @return array{queued: int, deactivated: int, synced: int}
     */
    public function syncSettingsForDomain(Domain $domain, DomainDetectionSetting $settings, bool $pushNow = true): array
    {
        if (! Schema::hasTable('google_ads_location_exclusions')) {
            return ['queued' => 0, 'deactivated' => 0, 'synced' => 0];
        }

        $enabled = (bool) $settings->google_geo_block_enabled;
        $rules = $this->normalizedRules($settings);

        $activeKeys = [];
        $queued = 0;

        if ($enabled) {
            foreach ($rules as $rule) {
                $key = $this->ruleKey($rule);
                $activeKeys[] = $key;

                $existing = DB::table('google_ads_location_exclusions')
                    ->where('domain_id', $domain->id)
                    ->where('rule_key', $key)
                    ->first();

                $payload = [
                    'geo_level' => $rule['geo_level'],
                    'country_code' => $rule['country'],
                    'country_name' => $rule['country_name'],
                    'state_code' => $rule['state'],
                    'state_name' => $rule['state_name'],
                    'city_name' => $rule['city'],
                    'is_active' => true,
                    'sync_status' => 'pending',
                    'sync_error' => null,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    // Keep criterion id if rule unchanged and already synced.
                    $sameTarget = (string) ($existing->country_code ?? '') === $rule['country']
                        && (string) ($existing->state_code ?? '') === (string) ($rule['state'] ?? '')
                        && (string) ($existing->city_name ?? '') === (string) ($rule['city'] ?? '');

                    if ($sameTarget && (string) ($existing->sync_status ?? '') === 'synced' && (bool) ($existing->is_active ?? false)) {
                        DB::table('google_ads_location_exclusions')->where('id', $existing->id)->update([
                            'country_name' => $rule['country_name'],
                            'state_name' => $rule['state_name'],
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);

                        continue;
                    }

                    DB::table('google_ads_location_exclusions')->where('id', $existing->id)->update($payload);
                    $queued++;
                    SyncGoogleAdsLocationExclusionJob::dispatch($domain->id, (int) $existing->id);
                } else {
                    $id = DB::table('google_ads_location_exclusions')->insertGetId(array_merge($payload, [
                        'domain_id' => $domain->id,
                        'rule_key' => $key,
                        'created_at' => now(),
                    ]));
                    $queued++;
                    SyncGoogleAdsLocationExclusionJob::dispatch($domain->id, $id);
                }
            }
        }

        $deactivateQuery = DB::table('google_ads_location_exclusions')
            ->where('domain_id', $domain->id)
            ->where('is_active', true);

        if ($activeKeys !== []) {
            $deactivateQuery->whereNotIn('rule_key', $activeKeys);
        }

        $deactivated = $deactivateQuery->update([
            'is_active' => false,
            'sync_status' => 'disabled',
            'updated_at' => now(),
        ]);

        $synced = 0;
        if ($pushNow) {
            $synced = $this->syncPendingForDomain($domain, 50);
        }

        return [
            'queued' => $queued,
            'deactivated' => (int) $deactivated,
            'synced' => $synced,
        ];
    }

    public function syncPendingForDomain(Domain $domain, int $limit = 25): int
    {
        if (! Schema::hasTable('google_ads_location_exclusions')) {
            return 0;
        }

        $rows = DB::table('google_ads_location_exclusions')
            ->where('domain_id', $domain->id)
            ->where('is_active', true)
            ->where('sync_status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $synced = 0;
        foreach ($rows as $row) {
            if ($this->syncRow($domain, (int) $row->id)) {
                $synced++;
            }
        }

        return $synced;
    }

    public function syncRow(Domain $domain, int $rowId): bool
    {
        if (! Schema::hasTable('google_ads_location_exclusions')) {
            return false;
        }

        $row = DB::table('google_ads_location_exclusions')->where('id', $rowId)->first();
        if (! $row || (int) $row->domain_id !== (int) $domain->id) {
            return false;
        }

        if (! (bool) ($row->is_active ?? true)) {
            return false;
        }

        $domain->loadMissing('googleAdsAccount.connection');
        $account = $domain->googleAdsAccount;
        if (! $account || (bool) $account->is_manager) {
            $this->markRow($rowId, 'skipped', 'Domain has no linked Google Ads customer account.');

            return false;
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            $this->markRow($rowId, 'failed', 'Google Ads API credentials unavailable. Reconnect Google in Integrations.');

            return false;
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '') {
            $this->markRow($rowId, 'failed', 'Missing Google Ads customer id.');

            return false;
        }

        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $criterion = (string) ($row->google_criterion_id ?? '');
        if ($criterion === '') {
            $resource = $this->geoResolver->resolveCriterionResource(
                $headers,
                $version,
                (string) $row->country_code,
                $row->country_name ? (string) $row->country_name : null,
                $row->state_name ? (string) $row->state_name : null,
                $row->city_name ? (string) $row->city_name : null,
            );

            if ($resource === null) {
                $this->markRow($rowId, 'failed', 'Could not resolve Google geo target for this location.');

                return false;
            }

            $criterion = preg_replace('/\D+/', '', $resource) ?: $resource;
            if (str_starts_with($resource, 'geoTargetConstants/')) {
                $criterion = substr($resource, strlen('geoTargetConstants/'));
            }

            DB::table('google_ads_location_exclusions')->where('id', $rowId)->update([
                'google_criterion_id' => $criterion,
                'updated_at' => now(),
            ]);
        }

        $geoTargetConstant = str_starts_with((string) $criterion, 'geoTargetConstants/')
            ? (string) $criterion
            : 'geoTargetConstants/' . $criterion;

        $campaignIds = $this->resolveCampaignIds($domain, $account, $customerId, $version, $headers);
        if ($campaignIds === []) {
            $this->markRow($rowId, 'failed', 'No eligible Google Ads campaigns found for this domain.');

            return false;
        }

        $successes = 0;
        $failures = [];

        foreach ($campaignIds as $campaignId) {
            if ($this->locationAlreadyExcluded($customerId, $campaignId, $version, $headers, $geoTargetConstant)) {
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
                            'location' => [
                                'geoTargetConstant' => $geoTargetConstant,
                            ],
                        ],
                    ]],
                ]);

            if ($response->successful() || $this->isBenignDuplicate((string) $response->body())) {
                $successes++;
                Log::info('Google Ads campaign location exclusion synced', [
                    'domain_id' => $domain->id,
                    'campaign_id' => $campaignId,
                    'geo_target' => $geoTargetConstant,
                    'rule_key' => $row->rule_key,
                ]);

                continue;
            }

            $error = $this->extractErrorMessage((string) $response->body());
            if ($this->isSkippableCampaignError($error)) {
                continue;
            }

            $failures[] = "campaign {$campaignId}: {$error}";
        }

        if ($successes > 0) {
            $this->markRow($rowId, 'synced', $failures !== [] ? implode(' | ', array_slice($failures, 0, 3)) : null);

            return true;
        }

        $this->markRow(
            $rowId,
            'failed',
            $failures !== [] ? implode(' | ', array_slice($failures, 0, 3)) : 'Location exclusion mutate failed for all campaigns.'
        );

        return false;
    }

    /**
     * @return list<array{geo_level: string, country: string, country_name: ?string, state: ?string, state_name: ?string, city: ?string}>
     */
    public function normalizedRules(DomainDetectionSetting $settings): array
    {
        $audience = (array) ($settings->google_geo_block_audience ?? []);
        $rules = [];

        foreach ((array) ($audience['rules'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $country = strtoupper(trim((string) ($row['country'] ?? '')));
            if ($country === '') {
                continue;
            }

            $state = $this->nullable($row['state'] ?? null);
            $city = $this->nullable($row['city'] ?? null);
            $geoLevel = $city !== null ? 'city' : ($state !== null ? 'state' : 'country');

            $rules[] = [
                'geo_level' => $geoLevel,
                'country' => $country,
                'country_name' => $this->nullable($row['country_name'] ?? null),
                'state' => $state,
                'state_name' => $this->nullable($row['state_name'] ?? null),
                'city' => $city,
            ];
        }

        return $rules;
    }

    /**
     * @param  array{country: string, state: ?string, city: ?string}  $rule
     */
    public function ruleKey(array $rule): string
    {
        return strtolower(implode('|', [
            $rule['country'],
            $rule['state'] ?? '',
            $rule['city'] ?? '',
        ]));
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function markRow(int $rowId, string $status, ?string $error): void
    {
        DB::table('google_ads_location_exclusions')->where('id', $rowId)->update([
            'sync_status' => $status,
            'sync_error' => $error,
            'synced_at' => $status === 'synced' ? now() : null,
            'updated_at' => now(),
        ]);
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

    /**
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

        if ($candidateIds === []) {
            return [];
        }

        $chunks = array_chunk($candidateIds, 50);
        $eligible = [];

        foreach ($chunks as $chunk) {
            $inList = implode(',', array_map('intval', $chunk));
            $query = "SELECT campaign.id, campaign.status FROM campaign WHERE campaign.id IN ({$inList}) AND campaign.status IN ('ENABLED', 'PAUSED')";

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
                if ($id !== '') {
                    $eligible[] = $id;
                }
            }
        }

        return array_values(array_unique($eligible));
    }

    private function locationAlreadyExcluded(
        string $customerId,
        string $campaignId,
        string $version,
        array $headers,
        string $geoTargetConstant,
    ): bool {
        $criterionId = preg_replace('/\D+/', '', $geoTargetConstant);
        if ($criterionId === '') {
            return false;
        }

        $query = "SELECT campaign_criterion.criterion_id, campaign_criterion.negative, campaign_criterion.location.geo_target_constant FROM campaign_criterion WHERE campaign.id = {$campaignId} AND campaign_criterion.type = 'LOCATION' AND campaign_criterion.negative = TRUE AND campaign_criterion.criterion_id = {$criterionId}";

        $response = Http::timeout(20)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            return false;
        }

        return $this->parseRows($response->json()) !== [];
    }

    private function isBenignDuplicate(string $body): bool
    {
        return stripos($body, 'already exists') !== false
            || stripos($body, 'DUPLICATE') !== false
            || stripos($body, 'Resource was not found') !== false && stripos($body, 'ALREADY') !== false;
    }

    private function isSkippableCampaignError(string $error): bool
    {
        $needles = [
            'does not have permission',
            'PERMISSION_DENIED',
            'OPERATION_NOT_PERMITTED',
            'not supported for this campaign',
            'CRITERION_NOT_SUPPORTED',
            'OPERATION_NOT_PERMITTED_FOR_CAMPAIGN_TYPE',
            'LOCATION_CRITERION',
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
        if (is_array($json)) {
            $message = $json['error']['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return trim(Str::limit(strip_tags($body), 280));
    }

    /**
     * @param  mixed  $payload
     * @return list<array<string, mixed>>
     */
    private function parseRows($payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $rows = [];
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
