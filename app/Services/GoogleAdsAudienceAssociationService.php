<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainGoogleAdsMapping;
use App\Models\GoogleAdsAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Attach a Google Ads user list as a negative campaign / ad-group criterion
 * so the audience appears under Exclusions in Google Ads.
 */
class GoogleAdsAudienceAssociationService
{
    public function __construct(
        private readonly GoogleAdsConnectionService $connectionApi,
    ) {
    }

    /**
     * @param  list<string>  $campaignIds
     * @param  list<string>  $adGroupIds
     * @return array{
     *   ok: bool,
     *   attached: list<string>,
     *   failed: list<string>,
     *   message: string,
     *   stored: array<string, mixed>,
     *   user_list_id: ?string,
     *   user_list_name: ?string
     * }
     */
    /**
     * Create (or reuse) the Ads-side audience user list that will appear under Exclusions.
     * Note: OAuth is Ads-only — this is not Analytics Admin GA4 audience create.
     *
     * @return array{
     *   ok: bool,
     *   message: string,
     *   user_list_id: ?string,
     *   user_list_name: ?string,
     *   created: bool,
     *   membership_days: int,
     *   method: string
     * }
     */
    /**
     * Stored GA4 / website audience list ids for a domain (from mapping settings).
     *
     * @return array{
     *   ga4: array{user_list_id: string, user_list_name: string}|null,
     *   website: array{user_list_id: string, user_list_name: string}|null
     * }
     */
    public function storedAssociationsForDomain(Domain $domain): array
    {
        $mapping = DomainGoogleAdsMapping::query()
            ->where('domain_id', $domain->id)
            ->orderByDesc('id')
            ->first();

        $byRoute = is_array($mapping?->settings['audience_associations'] ?? null)
            ? $mapping->settings['audience_associations']
            : [];

        $pick = function (string $route) use ($byRoute): ?array {
            $row = is_array($byRoute[$route] ?? null) ? $byRoute[$route] : null;
            if (! $row) {
                return null;
            }
            $id = trim((string) ($row['user_list_id'] ?? ''));
            if ($id === '') {
                return null;
            }

            return [
                'user_list_id' => $id,
                'user_list_name' => (string) ($row['user_list_name'] ?? $row['audience_name'] ?? ''),
                'rule' => is_array($row['rule'] ?? null) ? $row['rule'] : \App\Support\AudienceRuleSchema::defaultPreset(),
                'rule_summary' => (string) ($row['rule_summary'] ?? \App\Support\AudienceRuleSchema::naturalLanguageSummary($row['rule'] ?? [])),
                'membership_days' => (int) ($row['membership_days'] ?? 90),
                'status' => (string) ($row['status'] ?? 'created'),
                'attachment_status' => (string) ($row['attachment_status'] ?? 'pending'),
                'campaign_ids' => array_values($row['campaign_ids'] ?? []),
                'verified_campaign_ids' => array_values($row['verified_campaign_ids'] ?? []),
            ];
        };

        return [
            'ga4' => $pick('ga4'),
            'website' => $pick('website'),
        ];
    }

    /**
     * Live Google Ads user-list membership / size stats for the Apply modal + CSV export.
     *
     * @return array{
     *   ok: bool,
     *   user_list_id: ?string,
     *   user_list_name: ?string,
     *   membership_status: ?string,
     *   size_for_search: ?int,
     *   size_for_display: ?int,
     *   size_range_for_search: ?string,
     *   size_range_for_display: ?string,
     *   search_size_label: string,
     *   display_size_label: string,
     *   status_label: string,
     *   message: ?string
     * }
     */
    public function fetchUserListStats(
        Domain $domain,
        ?string $userListId = null,
        ?string $audienceName = null,
    ): array {
        $empty = [
            'ok' => false,
            'user_list_id' => $userListId ? preg_replace('/\D+/', '', $userListId) : null,
            'user_list_name' => $audienceName ? trim($audienceName) : null,
            'membership_status' => null,
            'size_for_search' => null,
            'size_for_display' => null,
            'size_range_for_search' => null,
            'size_range_for_display' => null,
            'search_size_label' => 'Not available yet',
            'display_size_label' => 'Not available yet',
            'status_label' => 'List not loaded',
            'message' => null,
        ];

        $account = $this->resolveAccount($domain);
        if (! $account || ! $account->connection || (bool) $account->is_manager) {
            $empty['message'] = 'Link a Google Ads customer account to this domain first.';

            return $empty;
        }

        $headers = $this->headersForAccount($account);
        if (! $headers) {
            $empty['message'] = 'Google Ads API auth failed — reconnect Google.';

            return $empty;
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $listId = preg_replace('/\D+/', '', (string) ($userListId ?? ''));
        $name = trim((string) ($audienceName ?? ''));

        if ($listId === '' && $name === '') {
            $stored = $this->storedAssociationsForDomain($domain);
            foreach (['ga4', 'website'] as $route) {
                $row = $stored[$route] ?? null;
                if (is_array($row) && ! empty($row['user_list_id'])) {
                    $listId = preg_replace('/\D+/', '', (string) $row['user_list_id']);
                    $name = (string) ($row['user_list_name'] ?? '');
                    break;
                }
            }
        }

        if ($listId === '' && $name !== '') {
            $lists = $this->fetchUserLists($customerId, $version, $headers) ?? [];
            $match = $this->matchUserListByName($lists, $name);
            if ($match !== null) {
                $listId = $match['id'];
                $name = $match['name'] ?? $name;
            }
        }

        if ($listId === '') {
            $empty['message'] = 'No user list id found for this audience yet.';

            return $empty;
        }

        $query = 'SELECT user_list.id, user_list.name, user_list.membership_status, user_list.type, '
            .'user_list.size_for_search, user_list.size_for_display, '
            .'user_list.size_range_for_search, user_list.size_range_for_display '
            ."FROM user_list WHERE user_list.id = '{$listId}' LIMIT 1";

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            Log::warning('Google Ads user list stats failed', [
                'customer_id' => $customerId,
                'user_list_id' => $listId,
                'body' => Str::limit((string) $response->body(), 500),
            ]);
            $empty['message'] = 'Could not load list stats from Google Ads.';

            return $empty;
        }

        $list = null;
        $payload = $response->json();
        if (is_array($payload)) {
            foreach ($payload as $chunk) {
                if (! is_array($chunk)) {
                    continue;
                }
                foreach (($chunk['results'] ?? []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $candidate = $row['userList'] ?? $row['user_list'] ?? null;
                    if (is_array($candidate)) {
                        $list = $candidate;
                        break 2;
                    }
                }
            }
        }

        if (! is_array($list)) {
            $empty['message'] = 'User list not found in this Ads account.';

            return $empty;
        }

        $id = preg_replace('/\D+/', '', (string) ($list['id'] ?? $listId));
        $listName = trim((string) ($list['name'] ?? $name));
        $listType = strtoupper((string) ($list['type'] ?? ''));
        $membership = strtoupper((string) ($list['membershipStatus'] ?? $list['membership_status'] ?? ''));
        $sizeSearch = $this->normalizeUserListSize($list['sizeForSearch'] ?? $list['size_for_search'] ?? null);
        $sizeDisplay = $this->normalizeUserListSize($list['sizeForDisplay'] ?? $list['size_for_display'] ?? null);
        $rangeSearch = $this->normalizeSizeRange($list['sizeRangeForSearch'] ?? $list['size_range_for_search'] ?? null);
        $rangeDisplay = $this->normalizeSizeRange($list['sizeRangeForDisplay'] ?? $list['size_range_for_display'] ?? null);

        $searchLabel = $this->formatUserListSizeLabel($sizeSearch, $rangeSearch, 'Search');
        $displayLabel = $this->formatUserListSizeLabel($sizeDisplay, $rangeDisplay, 'Display');
        $isCrm = ! $this->isEventCapableUserListType($listType);
        $statusLabel = $isCrm
            ? 'Wrong type: Customer Match (CRM) — will stay “Too small”; Create Audience again for a rule-based list'
            : match ($membership) {
                'OPEN' => ($sizeSearch === 0 || $sizeSearch === null) && ($sizeDisplay === 0 || $sizeDisplay === null)
                    ? 'Open — waiting for cr_invalid_traffic events (may show Too small until members arrive)'
                    : 'Open — receiving members',
                'CLOSED' => 'Closed',
                default => $membership !== '' ? $membership : 'Ready',
            };
        if ($isCrm) {
            $searchLabel = 'N/A — CRM Customer list (not event-fed)';
            $displayLabel = 'N/A — recreate as rule-based · Event list';
        }

        return [
            'ok' => true,
            'user_list_id' => $id !== '' ? $id : null,
            'user_list_name' => $listName !== '' ? $listName : null,
            'user_list_type' => $listType !== '' ? $listType : null,
            'membership_status' => $membership !== '' ? $membership : null,
            'size_for_search' => $sizeSearch,
            'size_for_display' => $sizeDisplay,
            'size_range_for_search' => $rangeSearch,
            'size_range_for_display' => $rangeDisplay,
            'search_size_label' => $searchLabel,
            'display_size_label' => $displayLabel,
            'status_label' => $statusLabel,
            'is_crm_shell' => $isCrm,
            'message' => $isCrm
                ? 'This Google Ads list is Customer Match CRM — Clickronix cannot push emails into it. Create Audience again to make a rule-based list.'
                : null,
        ];
    }

    private function normalizeUserListSize(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === -1 || $value === '-1') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (int) $value;

        return $n < 0 ? null : $n;
    }

    private function normalizeSizeRange(mixed $value): ?string
    {
        $raw = strtoupper(trim((string) ($value ?? '')));
        if ($raw === '' || in_array($raw, ['UNSPECIFIED', 'UNKNOWN'], true)) {
            return null;
        }

        return $raw;
    }

    private function formatUserListSizeLabel(?int $size, ?string $range, string $network): string
    {
        if ($size !== null) {
            return number_format($size).' users ('.$network.')';
        }

        $rangeLabels = [
            'LESS_THAN_FIVE_HUNDRED' => '< 500',
            'LESS_THAN_ONE_THOUSAND' => '< 1,000',
            'LESS_THAN_TEN_THOUSAND' => '< 10,000',
            'LESS_THAN_FIFTY_THOUSAND' => '< 50,000',
            'LESS_THAN_ONE_HUNDRED_THOUSAND' => '< 100,000',
            'LESS_THAN_THREE_HUNDRED_THOUSAND' => '< 300,000',
            'LESS_THAN_FIVE_HUNDRED_THOUSAND' => '< 500,000',
            'LESS_THAN_ONE_MILLION' => '< 1,000,000',
            'OVER_ONE_MILLION' => '1,000,000+',
        ];
        if ($range !== null && isset($rangeLabels[$range])) {
            return $rangeLabels[$range].' users ('.$network.')';
        }

        return '0 / not reported yet ('.$network.') — list may still be populating';
    }

    public function createAudienceList(
        Domain $domain,
        string $audienceName,
        string $durationLabel = '90 days',
        string $eventName = AudienceSignalService::DEFAULT_EVENT,
        string $method = 'ga4',
        bool $forceNew = false,
        ?array $rule = null,
    ): array {
        $method = $method === 'website' ? 'website' : 'ga4';
        $audienceName = trim($audienceName);
        if ($audienceName === '') {
            $audienceName = \App\Support\AudienceRuleSchema::defaultAudienceName($method);
        }
        $days = $this->parseMembershipDays($durationLabel);
        $normalizedRule = \App\Support\AudienceRuleSchema::normalize($rule ?? \App\Support\AudienceRuleSchema::defaultPreset());
        $rulePayload = $normalizedRule['rule'];

        $account = $this->resolveAccount($domain);
        if (! $account || ! $account->connection || (bool) $account->is_manager) {
            return [
                'ok' => false,
                'message' => 'Link a Google Ads customer account to this domain first.',
                'user_list_id' => null,
                'user_list_name' => null,
                'created' => false,
                'membership_days' => $days,
                'method' => $method,
            ];
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            return [
                'ok' => false,
                'message' => 'Google Ads API credentials unavailable. Reconnect Google Ads.',
                'user_list_id' => null,
                'user_list_name' => null,
                'created' => false,
                'membership_days' => $days,
                'method' => $method,
            ];
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $resolved = null;
        foreach ($this->headerVariantsForAccount($account) as $headersTry) {
            $resolved = $this->resolveUserList(
                $customerId,
                $version,
                $headersTry,
                $audienceName,
                null,
                $eventName,
                $days,
                $forceNew,
            );
            if (($resolved['id'] ?? null) !== null) {
                $headers = $headersTry;
                break;
            }
        }
        $resolved ??= [
            'id' => null,
            'name' => null,
            'created' => false,
            'error' => 'Could not create Google Ads audience list.',
        ];

        if (($resolved['id'] ?? null) === null) {
            return [
                'ok' => false,
                'message' => 'Could not create Google Ads audience list: '.($resolved['error'] ?? 'unknown error'),
                'user_list_id' => null,
                'user_list_name' => null,
                'created' => false,
                'membership_days' => $days,
                'method' => $method,
            ];
        }

        $stored = [
            'audience_name' => (string) ($resolved['name'] ?? $audienceName),
            'event_name' => $eventName,
            'user_list_id' => (string) $resolved['id'],
            'user_list_name' => (string) ($resolved['name'] ?? $audienceName),
            'user_list_type' => (string) ($resolved['type'] ?? 'RULE_BASED'),
            'user_list_created' => (bool) ($resolved['created'] ?? false),
            'membership_days' => $days,
            'method' => $method,
            'route' => $method,
            'rule' => $rulePayload,
            'rule_summary' => \App\Support\AudienceRuleSchema::naturalLanguageSummary($rulePayload),
            'desired' => true,
            'status' => 'created',
            'attachment_status' => 'pending',
            'campaign_ids' => [],
            'verified_campaign_ids' => [],
            'updated_at' => now()->toIso8601String(),
        ];
        $this->persistAssociation($domain, $stored);

        $action = ! empty($resolved['created']) ? 'Created' : 'Reused existing';
        $typeNote = str_contains((string) ($resolved['name'] ?? ''), '· Event')
            ? ' Replaced an empty Customer Match (“Too small”) shell with a rule-based event list.'
            : '';

        return [
            'ok' => true,
            'message' => $action.' Google Ads audience “'.$stored['user_list_name'].'” (ID '.$stored['user_list_id']
                .', type '.$stored['user_list_type'].', '.$days.'-day membership).'.$typeNote
                .' Next: Apply exclusion to Search/Display campaigns.',
            'user_list_id' => $stored['user_list_id'],
            'user_list_name' => $stored['user_list_name'],
            'user_list_type' => $stored['user_list_type'],
            'created' => (bool) ($resolved['created'] ?? false),
            'membership_days' => $days,
            'method' => $stored['method'],
        ];
    }

    private function parseMembershipDays(string $durationLabel): int
    {
        if (preg_match('/(\d+)/', $durationLabel, $m)) {
            $days = (int) $m[1];

            return max(1, min(540, $days > 0 ? $days : 30));
        }

        return 30;
    }

    /**
     * Confirm campaign criteria actually exist in Google Ads after mutate.
     *
     * @param  list<string>  $campaignIds
     * @param  array<string, string>  $headers
     * @return list<string> campaign ids that have the negative user list
     */
    private function verifyCampaignExclusions(
        string $customerId,
        string $version,
        array $headers,
        array $campaignIds,
        string $userListId,
    ): array {
        if ($campaignIds === []) {
            return [];
        }

        $ids = implode(', ', $campaignIds);
        $query = 'SELECT campaign.id, campaign_criterion.negative, campaign_criterion.user_list.user_list '
            .'FROM campaign_criterion '
            ."WHERE campaign.id IN ({$ids}) "
            .'AND campaign_criterion.type = \'USER_LIST\' '
            .'AND campaign_criterion.negative = TRUE';

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $want = "customers/{$customerId}/userLists/{$userListId}";
        $found = [];
        $payload = $response->json();
        if (! is_array($payload)) {
            return [];
        }

        foreach ($payload as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            foreach (($chunk['results'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $cid = preg_replace('/\D+/', '', (string) ($row['campaign']['id'] ?? ''));
                $list = (string) (
                    $row['campaignCriterion']['userList']['userList']
                    ?? $row['campaign_criterion']['user_list']['user_list']
                    ?? ''
                );
                if ($cid !== '' && ($list === $want || str_ends_with($list, '/userLists/'.$userListId))) {
                    $found[] = $cid;
                }
            }
        }

        return array_values(array_unique($found));
    }

    public function applyToCampaigns(
        Domain $domain,
        array $campaignIds,
        string $audienceName,
        ?string $userListId = null,
        string $eventName = AudienceSignalService::DEFAULT_EVENT,
        array $adGroupIds = [],
        string $scope = 'campaign',
        string $route = 'ga4',
    ): array {
        $campaignIds = $this->normalizeIds($campaignIds);
        $adGroupIds = $this->normalizeIds($adGroupIds);
        $route = $route === 'website' ? 'website' : 'ga4';
        $audienceName = trim($audienceName);
        if ($audienceName === '') {
            $audienceName = $route === 'website'
                ? 'Clickronix | Invalid Traffic | Google Ads'
                : 'Clickronix | Invalid Traffic | GA4';
        }

        $stored = [
            'audience_name' => $audienceName,
            'event_name' => $eventName,
            'user_list_id' => $userListId ? preg_replace('/\D+/', '', $userListId) : null,
            'campaign_ids' => $campaignIds,
            'ad_group_ids' => $adGroupIds,
            'scope' => $scope === 'adgroup' ? 'adgroup' : 'campaign',
            'method' => $route,
            'route' => $route,
            'desired' => true,
            'status' => 'queued',
            'updated_at' => now()->toIso8601String(),
        ];

        $this->persistAssociation($domain, $stored);

        $targets = $stored['scope'] === 'adgroup' ? $adGroupIds : $campaignIds;
        if ($targets === []) {
            return $this->fail($stored, [], [], 'Select at least one '.($stored['scope'] === 'adgroup' ? 'ad group' : 'campaign').'.');
        }

        $account = $this->resolveAccount($domain);
        if (! $account || ! $account->connection || (bool) $account->is_manager) {
            $stored['status'] = 'saved_no_account';
            $this->persistAssociation($domain, $stored);

            return $this->fail(
                $stored,
                [],
                $targets,
                'Link a Google Ads customer account to this domain first, then Apply again.',
            );
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            $stored['status'] = 'auth_failed';
            $this->persistAssociation($domain, $stored);

            return $this->fail($stored, [], $targets, 'Google Ads API credentials unavailable. Reconnect Google Ads.');
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $headerVariants = $this->headerVariantsForAccount($account);
        if ($headerVariants === []) {
            $headerVariants = [$headers];
        }

        $resolved = null;
        foreach ($headerVariants as $headersTry) {
            $resolved = $this->resolveUserList(
                $customerId,
                $version,
                $headersTry,
                $audienceName,
                $stored['user_list_id'],
                $eventName,
                $this->parseMembershipDays((string) ($stored['membership_days'] ?? '30')),
            );
            if (($resolved['id'] ?? null) !== null) {
                $headers = $headersTry;
                break;
            }
        }
        $resolved ??= [
            'id' => null,
            'name' => null,
            'created' => false,
            'error' => 'Could not find or create user list.',
        ];

        if (($resolved['id'] ?? null) === null) {
            $stored['status'] = 'user_list_missing';
            $stored['resolve_error'] = $resolved['error'] ?? 'Could not find or create user list.';
            $this->persistAssociation($domain, $stored);

            return $this->fail(
                $stored,
                [],
                $targets,
                'Could not resolve a Google Ads audience (user list) to attach. '
                .($resolved['error'] ?? 'Create/link the GA4 audience in Google Ads, then Apply again.'),
                null,
                $resolved['name'] ?? null,
            );
        }

        $listId = (string) $resolved['id'];
        $listName = (string) ($resolved['name'] ?? $audienceName);
        $stored['user_list_id'] = $listId;
        $stored['user_list_name'] = $listName;
        $stored['user_list_created'] = (bool) ($resolved['created'] ?? false);
        $this->persistAssociation($domain, $stored);

        $userListResource = "customers/{$customerId}/userLists/{$listId}";
        $attached = [];
        $failed = [];
        // Recompute variants after resolve may have selected a working login header.
        $headerVariants = $this->headerVariantsForAccount($account);
        if ($headerVariants === []) {
            $headerVariants = [$headers];
        }

        if ($stored['scope'] === 'adgroup') {
            foreach ($adGroupIds as $adGroupId) {
                $attach = $this->mutateNegativeUserList(
                    $customerId,
                    $version,
                    $headerVariants,
                    'adGroupCriteria',
                    [
                        'adGroup' => "customers/{$customerId}/adGroups/{$adGroupId}",
                        'negative' => true,
                        'userList' => ['userList' => $userListResource],
                    ],
                );
                if ($attach['ok']) {
                    $attached[] = 'ag:'.$adGroupId;
                    continue;
                }
                $failed[] = 'adGroup '.$adGroupId.': '.Str::limit((string) $attach['error'], 160);
                Log::warning('Google Ads ad-group audience exclusion attach failed', [
                    'domain_id' => $domain->id,
                    'ad_group_id' => $adGroupId,
                    'user_list_id' => $listId,
                    'error' => $attach['error'],
                ]);
            }
        } else {
            foreach ($campaignIds as $campaignId) {
                $attach = $this->mutateNegativeUserList(
                    $customerId,
                    $version,
                    $headerVariants,
                    'campaignCriteria',
                    [
                        'campaign' => "customers/{$customerId}/campaigns/{$campaignId}",
                        'negative' => true,
                        'userList' => ['userList' => $userListResource],
                    ],
                );
                if ($attach['ok']) {
                    $attached[] = $campaignId;
                    continue;
                }
                $failed[] = $campaignId.': '.Str::limit((string) $attach['error'], 160);
                Log::warning('Google Ads audience exclusion attach failed', [
                    'domain_id' => $domain->id,
                    'campaign_id' => $campaignId,
                    'user_list_id' => $listId,
                    'error' => $attach['error'],
                ]);
            }
        }

        // Read-back confirmation — required for campaign scope so UI false-positives don't slip through.
        if ($stored['scope'] !== 'adgroup' && $attached !== []) {
            $verified = [];
            foreach ($headerVariants as $headersTry) {
                $verified = $this->verifyCampaignExclusions($customerId, $version, $headersTry, $attached, $listId);
                if ($verified !== []) {
                    break;
                }
            }
            if ($verified === []) {
                foreach ($attached as $cid) {
                    $failed[] = $cid.': Attached API call succeeded but exclusion not found in Google Ads read-back. Check MCC access / login-customer-id.';
                }
                $attached = [];
            } else {
                foreach (array_diff($attached, $verified) as $cid) {
                    $failed[] = $cid.': Not confirmed in Google Ads campaign exclusions read-back.';
                }
                $attached = $verified;
            }
            $stored['verified_campaign_ids'] = $verified;
        }

        $stored['status'] = $attached !== [] ? ($failed === [] ? 'applied' : 'partial') : 'failed';
        $stored['attachment_status'] = $attached !== []
            ? (($failed === [] && ! empty($stored['verified_campaign_ids'])) ? 'verified' : 'attached')
            : 'pending';
        $stored['attached_campaign_ids'] = $attached;
        $stored['errors'] = $failed;
        $stored['updated_at'] = now()->toIso8601String();
        $this->persistAssociation($domain, $stored);

        if ($attached === []) {
            return $this->fail(
                $stored,
                [],
                $failed,
                'Could not attach audience to exclusions: '.implode(' | ', array_slice($failed, 0, 2)),
                $listId,
                $listName,
            );
        }

        $createdNote = ! empty($stored['user_list_created'])
            ? ' Created Ads audience “'.$listName.'” (ID '.$listId.').'
            : ' Using Ads audience “'.$listName.'” (ID '.$listId.').';

        return [
            'ok' => true,
            'attached' => $attached,
            'failed' => $failed,
            'message' => 'Confirmed on '.count($attached).' Search/Display campaign exclusion(s) in Google Ads.'
                .$createdNote
                .' Check: Campaign → Audiences → Exclusions (or Tools → Audience manager).',
            'stored' => $stored,
            'user_list_id' => $listId,
            'user_list_name' => $listName,
        ];
    }

    /**
     * Find existing Ads user list by id/name, or create one so Apply can attach exclusions.
     *
     * @param  array<string, string>  $headers
     * @return array{id: ?string, name: ?string, created: bool, error: ?string}
     */
    private function resolveUserList(
        string $customerId,
        string $version,
        array $headers,
        string $audienceName,
        ?string $preferredId,
        string $eventName,
        int $membershipDays = 30,
        bool $forceNew = false,
    ): array {
        $lists = $this->fetchUserLists($customerId, $version, $headers);
        if ($lists === null) {
            return [
                'id' => null,
                'name' => null,
                'created' => false,
                'error' => 'Could not load user lists from Google Ads.',
            ];
        }

        if ($preferredId && ! $forceNew) {
            foreach ($lists as $row) {
                if (($row['id'] ?? '') !== $preferredId) {
                    continue;
                }
                if (! $this->isEventCapableUserListType($row['type'] ?? null)) {
                    // Prefer creating a rule-based list over reusing an empty Customer Match shell.
                    Log::info('Skipping preferred CRM/Customer Match user list for event audience', [
                        'customer_id' => $customerId,
                        'user_list_id' => $preferredId,
                        'type' => $row['type'] ?? null,
                    ]);
                    break;
                }

                return [
                    'id' => $row['id'],
                    'name' => $row['name'] ?? $audienceName,
                    'created' => false,
                    'error' => null,
                    'type' => $row['type'] ?? null,
                ];
            }
        }

        if (! $forceNew) {
            $match = $this->matchUserListByName($lists, $audienceName, true);
            if ($match !== null) {
                return [
                    'id' => $match['id'],
                    'name' => $match['name'],
                    'created' => false,
                    'error' => null,
                    'type' => $match['type'] ?? null,
                ];
            }
        }

        // Name taken by a CRM "Customer list" (Too small) — create an event-capable list with a clear suffix.
        $crmBlocker = $this->matchUserListByName($lists, $audienceName, false);
        if ($crmBlocker !== null && ! $this->isEventCapableUserListType($crmBlocker['type'] ?? null)) {
            $audienceName = rtrim($audienceName).' · Event';
            $n = 2;
            while ($this->matchUserListByName($lists, $audienceName, false) !== null) {
                $audienceName = rtrim((string) ($crmBlocker['name'] ?? 'Clickronix Invalid')).' · Event ('.$n.')';
                $n++;
                if ($n > 50) {
                    $audienceName = rtrim((string) ($crmBlocker['name'] ?? 'Clickronix Invalid')).' · Event · '.now()->format('Ymd-Hi');
                    break;
                }
            }
        } elseif ($forceNew) {
            $base = $audienceName;
            $candidate = $base;
            $n = 2;
            while ($this->matchUserListByName($lists, $candidate, false) !== null) {
                $candidate = $base.' ('.$n.')';
                $n++;
                if ($n > 200) {
                    $candidate = $base.' · '.now()->format('Y-m-d H:i');
                    break;
                }
            }
            $audienceName = $candidate;
        }

        $created = $this->createUserList($customerId, $version, $headers, $audienceName, $eventName, $membershipDays, $lists);
        if (($created['id'] ?? null) !== null) {
            return [
                'id' => $created['id'],
                'name' => $created['name'] ?? $audienceName,
                'created' => (bool) ($created['created'] ?? true),
                'error' => null,
                'type' => $created['type'] ?? 'RULE_BASED',
            ];
        }

        return [
            'id' => null,
            'name' => null,
            'created' => false,
            'error' => $created['error'] ?? 'User list create failed.',
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<array{id: string, name: string, type: string}>|null
     */
    private function fetchUserLists(string $customerId, string $version, array $headers): ?array
    {
        $query = "SELECT user_list.id, user_list.name, user_list.type, user_list.membership_status "
            ."FROM user_list WHERE user_list.membership_status = 'OPEN' ORDER BY user_list.name";

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($version, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if (! $response->successful()) {
            Log::warning('Google Ads user list search failed', [
                'customer_id' => $customerId,
                'body' => Str::limit((string) $response->body(), 500),
            ]);

            return null;
        }

        $out = [];
        $payload = $response->json();
        if (! is_array($payload)) {
            return [];
        }

        foreach ($payload as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            foreach (($chunk['results'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $list = $row['userList'] ?? $row['user_list'] ?? [];
                if (! is_array($list)) {
                    continue;
                }
                $id = preg_replace('/\D+/', '', (string) ($list['id'] ?? ''));
                $name = trim((string) ($list['name'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $type = strtoupper((string) ($list['type'] ?? ''));
                $out[] = [
                    'id' => $id,
                    'name' => $name !== '' ? $name : ('List '.$id),
                    'type' => $type,
                ];
            }
        }

        return $out;
    }

    /**
     * CRM / Customer Match lists never receive GA4/tag events — they stay "Too small".
     * Only rule/basic/logical lists are usable for invalid-traffic exclusion membership.
     */
    private function isEventCapableUserListType(?string $type): bool
    {
        $type = strtoupper(trim((string) $type));
        if ($type === '' || $type === 'UNKNOWN' || $type === 'UNSPECIFIED') {
            // Unknown: allow reuse but create path will still prefer rule-based.
            return true;
        }

        return ! in_array($type, [
            'CRM_BASED',
            'LOOKALIKE',
            'SIMILAR',
        ], true);
    }

    /**
     * Reuse only when the Ads user-list name matches exactly (case-insensitive)
     * AND the list type can receive browser/event membership (not Customer Match CRM).
     *
     * @param  list<array{id: string, name: string, type?: string}>  $lists
     * @return array{id: string, name: string, type?: string}|null
     */
    private function matchUserListByName(array $lists, string $audienceName, bool $eventCapableOnly = true): ?array
    {
        $needle = mb_strtolower(trim($audienceName));
        if ($needle === '') {
            return null;
        }

        foreach ($lists as $row) {
            if (mb_strtolower(trim($row['name'])) !== $needle) {
                continue;
            }
            if ($eventCapableOnly && ! $this->isEventCapableUserListType($row['type'] ?? null)) {
                continue;
            }

            return [
                'id' => $row['id'],
                'name' => trim($audienceName),
                'ads_name' => $row['name'],
                'type' => $row['type'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Create an Ads-side RULE-BASED audience shell for exclusion.
     *
     * Never creates CRM / Customer Match lists — those show as "Customer list" /
     * "Too small to use on Google properties" and never receive GA4/tag events.
     * Membership grows when the site fires cr_invalid_traffic (Google browser identity).
     *
     * @param  array<string, string>  $headers
     * @param  list<array{id: string, name: string, type?: string}>  $knownLists
     * @return array{id: ?string, name: ?string, created?: bool, type?: ?string, error: ?string}
     */
    private function createUserList(
        string $customerId,
        string $version,
        array $headers,
        string $audienceName,
        string $eventName,
        int $membershipDays = 30,
        array $knownLists = [],
    ): array {
        $lifeSpan = (string) max(1, min(540, $membershipDays));
        $baseName = mb_substr(trim($audienceName), 0, 255);
        $description = mb_substr(
            'CR Invalid Traffic audience. Rule: event cr_invalid_traffic AND cr_traffic_verdict=invalid. '
            .'Membership via Google browser identity (not Clickronix Device ID). Attach as campaign exclusion.',
            0,
            500
        );

        $eventRuleItem = [
            'name' => 'e:'.$eventName,
            'stringRuleItem' => [
                'operator' => 'EQUALS',
                'value' => $eventName,
            ],
        ];
        $verdictRuleItem = [
            'name' => AudienceSignalService::VERDICT_PARAM,
            'stringRuleItem' => [
                'operator' => 'EQUALS',
                'value' => AudienceSignalService::VERDICT_INVALID,
            ],
        ];
        // Also try custom-parameter form without e: prefix (Ads remarketing params).
        $eventParamItem = [
            'name' => $eventName,
            'stringRuleItem' => [
                'operator' => 'EQUALS',
                'value' => $eventName,
            ],
        ];

        $attempts = [
            // 1) Event + verdict (canonical Clickronix contract)
            [
                'name' => $baseName,
                'description' => $description,
                'membershipStatus' => 'OPEN',
                'membershipLifeSpan' => $lifeSpan,
                'ruleBasedUserList' => [
                    'prepopulationStatus' => 'REQUESTED',
                    'flexibleRuleUserList' => [
                        'inclusiveRuleOperator' => 'AND',
                        'inclusiveOperands' => [[
                            'ruleItemGroups' => [[
                                'ruleItems' => [$eventRuleItem, $verdictRuleItem],
                            ]],
                            'lookbackWindowDays' => $lifeSpan,
                        ]],
                    ],
                ],
            ],
            // 2) Event name only (e:cr_invalid_traffic)
            [
                'name' => $baseName,
                'description' => $description,
                'membershipStatus' => 'OPEN',
                'membershipLifeSpan' => $lifeSpan,
                'ruleBasedUserList' => [
                    'prepopulationStatus' => 'REQUESTED',
                    'flexibleRuleUserList' => [
                        'inclusiveRuleOperator' => 'AND',
                        'inclusiveOperands' => [[
                            'ruleItemGroups' => [[
                                'ruleItems' => [$eventRuleItem],
                            ]],
                            'lookbackWindowDays' => $lifeSpan,
                        ]],
                    ],
                ],
            ],
            // 3) Custom parameter style
            [
                'name' => $baseName,
                'description' => $description,
                'membershipStatus' => 'OPEN',
                'membershipLifeSpan' => $lifeSpan,
                'ruleBasedUserList' => [
                    'prepopulationStatus' => 'NONE',
                    'flexibleRuleUserList' => [
                        'inclusiveRuleOperator' => 'AND',
                        'inclusiveOperands' => [[
                            'ruleItemGroups' => [[
                                'ruleItems' => [$eventParamItem, $verdictRuleItem],
                            ]],
                            'lookbackWindowDays' => $lifeSpan,
                        ]],
                    ],
                ],
            ],
            // 4) Minimal open rule shell (url__ never-match) so Attach still works; events may still fill via linked GA4 later
            [
                'name' => $baseName,
                'description' => $description,
                'membershipStatus' => 'OPEN',
                'membershipLifeSpan' => $lifeSpan,
                'ruleBasedUserList' => [
                    'prepopulationStatus' => 'NONE',
                    'flexibleRuleUserList' => [
                        'inclusiveRuleOperator' => 'OR',
                        'inclusiveOperands' => [[
                            'ruleItemGroups' => [[
                                'ruleItems' => [[
                                    'name' => 'url__',
                                    'stringRuleItem' => [
                                        'operator' => 'EQUALS',
                                        'value' => 'https://clickronix.invalid/cr_invalid_traffic_shell',
                                    ],
                                ]],
                            ]],
                            'lookbackWindowDays' => $lifeSpan,
                        ]],
                    ],
                ],
            ],
        ];

        $lastError = 'User list create failed.';
        foreach ($attempts as $idx => $createBody) {
            $payload = ['operations' => [['create' => $createBody]]];
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/userLists:mutate"), $payload);

            // Duplicate name → reuse exact list only when it is event-capable (not CRM Customer Match).
            if (! $response->successful() && $this->isBenignDuplicate((string) $response->body())) {
                $existing = $this->matchUserListByName($knownLists, $baseName, true);
                if ($existing === null) {
                    $refreshed = $this->fetchUserLists($customerId, $version, $headers) ?? [];
                    $existing = $this->matchUserListByName($refreshed, $baseName, true);
                }
                if ($existing !== null) {
                    return [
                        'id' => $existing['id'],
                        'name' => $baseName,
                        'created' => false,
                        'type' => $existing['type'] ?? null,
                        'error' => null,
                    ];
                }
                $lastError = 'An audience with this name already exists as a Customer Match list (Too small). Create again to make a rule-based “· Event” list.';
                Log::warning('Google Ads user list name blocked by CRM duplicate', [
                    'customer_id' => $customerId,
                    'name' => $baseName,
                    'attempt' => $idx,
                ]);
                continue;
            }

            if (! $response->successful()) {
                $lastError = Str::limit($this->extractError((string) $response->body()), 240);
                Log::warning('Google Ads rule-based user list create attempt failed', [
                    'customer_id' => $customerId,
                    'attempt' => $idx,
                    'body' => Str::limit((string) $response->body(), 600),
                ]);
                continue;
            }

            $json = $response->json();
            $resource = (string) (
                $json['results'][0]['resourceName']
                ?? $json['results'][0]['resource_name']
                ?? ''
            );
            if (preg_match('#userLists/(\d+)#', $resource, $m)) {
                return [
                    'id' => $m[1],
                    'name' => $baseName,
                    'created' => true,
                    'type' => 'RULE_BASED',
                    'error' => null,
                ];
            }
            $lastError = 'User list created but ID missing in API response.';
        }

        return [
            'id' => null,
            'name' => null,
            'error' => $lastError.' (CRM/Customer Match fallback disabled — those lists never receive invalid-traffic events.)',
        ];
    }

    /**
     * Try mutate with alternate login-customer-id headers until one works.
     *
     * @param  list<array<string, string>>  $headerVariants
     * @param  array<string, mixed>  $criterionCreate
     * @return array{ok: bool, error: ?string}
     */
    private function mutateNegativeUserList(
        string $customerId,
        string $version,
        array $headerVariants,
        string $resource,
        array $criterionCreate,
    ): array {
        $lastError = 'Google Ads rejected the audience exclusion.';
        foreach ($headerVariants as $headers) {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/{$resource}:mutate"), [
                    'partialFailure' => true,
                    'operations' => [['create' => $criterionCreate]],
                ]);

            $body = (string) $response->body();
            if ($this->isBenignDuplicate($body)) {
                return ['ok' => true, 'error' => null];
            }

            if (! $response->successful()) {
                $lastError = $this->extractError($body);
                continue;
            }

            if ($this->mutateHadPartialFailure($body)) {
                $lastError = $this->extractError($body);
                continue;
            }

            if ($this->mutateReturnedResourceName($body)) {
                return ['ok' => true, 'error' => null];
            }

            $lastError = 'Google Ads returned success without a criterion resource name.';
        }

        return ['ok' => false, 'error' => $lastError];
    }

    private function mutateReturnedResourceName(string $body): bool
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return false;
        }
        foreach ((is_array($json['results'] ?? null) ? $json['results'] : []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['resourceName'] ?? $row['resource_name'] ?? ''));
            if ($name !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, string>>
     */
    private function headerVariantsForAccount(GoogleAdsAccount $account): array
    {
        $connection = $account->connection;
        if (! $connection) {
            return [];
        }

        $this->connectionApi->refreshAccessToken($connection);
        $connection->refresh();
        $base = $this->connectionApi->apiHeaders($connection, forceRefresh: true);
        if (! $base) {
            return [];
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id) ?: '';
        $candidates = [];
        $manager = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: '')) ?: '';
        $root = preg_replace('/\D+/', '', (string) $this->connectionApi->loginCustomerId()) ?: '';
        if ($manager !== '' && $manager !== $customerId) {
            $candidates[] = $manager;
        }
        if ($root !== '' && $root !== $customerId && $root !== $manager) {
            $candidates[] = $root;
        }
        $candidates[] = ''; // direct / no MCC header

        $out = [];
        $seen = [];
        foreach ($candidates as $loginId) {
            $headers = $base;
            unset($headers['login-customer-id']);
            if ($loginId !== '') {
                $headers['login-customer-id'] = $loginId;
            }
            $key = $loginId === '' ? '_none' : $loginId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $headers;
        }

        return $out;
    }

    private function mutateHadPartialFailure(string $body): bool
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return false;
        }

        return ! empty($json['partialFailureError']) || ! empty($json['partial_failure_error']);
    }

    /**
     * @param  list<string|int>  $ids
     * @return list<string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($id) => preg_replace('/\D+/', '', (string) $id),
            $ids,
        ))));
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  list<string>  $attached
     * @param  list<string>  $failed
     * @return array{
     *   ok: bool,
     *   attached: list<string>,
     *   failed: list<string>,
     *   message: string,
     *   stored: array<string, mixed>,
     *   user_list_id: ?string,
     *   user_list_name: ?string
     * }
     */
    private function fail(
        array $stored,
        array $attached,
        array $failed,
        string $message,
        ?string $userListId = null,
        ?string $userListName = null,
    ): array {
        return [
            'ok' => false,
            'attached' => $attached,
            'failed' => $failed,
            'message' => $message,
            'stored' => $stored,
            'user_list_id' => $userListId ?? ($stored['user_list_id'] ?? null),
            'user_list_name' => $userListName ?? ($stored['user_list_name'] ?? null),
        ];
    }

    private function resolveAccount(Domain $domain): ?GoogleAdsAccount
    {
        $domain->loadMissing(['googleAdsAccount.connection', 'googleAdsMappings.account.connection']);
        if ($domain->googleAdsAccount instanceof GoogleAdsAccount) {
            return $domain->googleAdsAccount;
        }

        $mapped = $domain->googleAdsMappings
            ->pluck('account')
            ->filter(fn ($a) => $a instanceof GoogleAdsAccount && ! (bool) $a->is_manager)
            ->first();

        return $mapped instanceof GoogleAdsAccount ? $mapped : null;
    }

    /** @param  array<string, mixed>  $association */
    private function persistAssociation(Domain $domain, array $association): void
    {
        $mapping = DomainGoogleAdsMapping::query()
            ->where('domain_id', $domain->id)
            ->orderByDesc('id')
            ->first();

        if (! $mapping && $domain->google_ads_account_id) {
            $mapping = DomainGoogleAdsMapping::query()->create([
                'domain_id' => $domain->id,
                'google_ads_account_id' => $domain->google_ads_account_id,
                'audience_exclusion_enabled' => true,
                'settings' => [],
            ]);
        }

        if (! $mapping) {
            return;
        }

        $settings = is_array($mapping->settings) ? $mapping->settings : [];
        // Never wipe other routes — GA4 + Website lists must coexist on campaigns.
        $route = strtolower(trim((string) ($association['route'] ?? $association['method'] ?? 'default')));
        if ($route === '') {
            $route = 'default';
        }
        $association['route'] = $route;
        $settings['audience_association'] = $association;
        $byRoute = is_array($settings['audience_associations'] ?? null)
            ? $settings['audience_associations']
            : [];
        $byRoute[$route] = $association;
        $settings['audience_associations'] = $byRoute;
        $mapping->audience_exclusion_enabled = true;
        $mapping->settings = $settings;
        $mapping->save();
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

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        $loginId = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: ''));
        if ($loginId === '') {
            $loginId = preg_replace('/\D+/', '', (string) $this->connectionApi->loginCustomerId());
        }
        if ($loginId !== '' && $loginId !== $customerId) {
            $headers['login-customer-id'] = $loginId;
        }

        return $headers;
    }

    private function googleAdsUrl(string $version, string $path): string
    {
        return 'https://googleads.googleapis.com/'.trim($version).'/'.ltrim($path, '/');
    }

    private function isBenignDuplicate(string $body): bool
    {
        foreach (['DUPLICATE', 'ALREADY_EXISTS', 'CRITERION_ALREADY_EXISTS'] as $needle) {
            if (stripos($body, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractError(string $body): string
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return $body;
        }

        $partial = $json['partialFailureError'] ?? $json['partial_failure_error'] ?? null;
        if (is_array($partial)) {
            foreach (($partial['details'] ?? []) as $detail) {
                foreach (($detail['errors'] ?? []) as $err) {
                    if (! empty($err['message'])) {
                        return (string) $err['message'];
                    }
                }
            }
            if (! empty($partial['message'])) {
                return (string) $partial['message'];
            }
        }

        foreach (($json['error']['details'] ?? []) as $detail) {
            foreach (($detail['errors'] ?? []) as $err) {
                if (! empty($err['message'])) {
                    return (string) $err['message'];
                }
            }
        }

        return (string) ($json['error']['message'] ?? $body);
    }
}
