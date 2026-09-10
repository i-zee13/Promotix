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
    public function createAudienceList(
        Domain $domain,
        string $audienceName,
        string $durationLabel = '30 days',
        string $eventName = AudienceSignalService::DEFAULT_EVENT,
        string $method = 'ga4',
        bool $forceNew = false,
    ): array {
        $method = $method === 'website' ? 'website' : 'ga4';
        $audienceName = trim($audienceName);
        if ($audienceName === '') {
            $audienceName = $method === 'website'
                ? 'Clickronix | Invalid Traffic | Google Ads'
                : 'Clickronix | Invalid Traffic | GA4';
        }
        $days = $this->parseMembershipDays($durationLabel);

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
            'user_list_created' => (bool) ($resolved['created'] ?? false),
            'membership_days' => $days,
            'method' => $method,
            'route' => $method,
            'desired' => true,
            'status' => 'created',
            'campaign_ids' => [],
            'updated_at' => now()->toIso8601String(),
        ];
        $this->persistAssociation($domain, $stored);

        $action = ! empty($resolved['created']) ? 'Created' : 'Reused existing';

        return [
            'ok' => true,
            'message' => $action.' Google Ads audience “'.$stored['user_list_name'].'” (ID '.$stored['user_list_id']
                .', '.$days.'-day membership). Next: Apply exclusion to Search/Display campaigns.',
            'user_list_id' => $stored['user_list_id'],
            'user_list_name' => $stored['user_list_name'],
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
                if (($row['id'] ?? '') === $preferredId) {
                    return [
                        'id' => $row['id'],
                        'name' => $row['name'] ?? $audienceName,
                        'created' => false,
                        'error' => null,
                    ];
                }
            }
        }

        if (! $forceNew) {
            $match = $this->matchUserListByName($lists, $audienceName);
            if ($match !== null) {
                return [
                    'id' => $match['id'],
                    'name' => $match['name'],
                    'created' => false,
                    'error' => null,
                ];
            }
        } else {
            // Unique list name so Create always adds another Ads audience (never silently reuses).
            $base = $audienceName;
            $candidate = $base;
            $n = 2;
            while ($this->matchUserListByName($lists, $candidate) !== null) {
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
     * @return list<array{id: string, name: string}>|null
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
                $out[] = ['id' => $id, 'name' => $name !== '' ? $name : ('List '.$id)];
            }
        }

        return $out;
    }

    /**
     * Reuse only when the Ads user-list name matches exactly (case-insensitive).
     * Fuzzy / "contains Clickronix" matching was rewriting the user's chosen name.
     *
     * @param  list<array{id: string, name: string}>  $lists
     * @return array{id: string, name: string}|null
     */
    private function matchUserListByName(array $lists, string $audienceName): ?array
    {
        $needle = mb_strtolower(trim($audienceName));
        if ($needle === '') {
            return null;
        }

        foreach ($lists as $row) {
            if (mb_strtolower(trim($row['name'])) === $needle) {
                return [
                    'id' => $row['id'],
                    // Keep the name the user requested (canonical casing from their input).
                    'name' => trim($audienceName),
                    'ads_name' => $row['name'],
                ];
            }
        }

        return null;
    }

    /**
     * Create an Ads-side audience shell so it can be attached as campaign exclusion.
     * Membership still grows from GA4 event / remarketing when linked; attach is what
     * makes the list appear under Exclusions in the Ads UI.
     *
     * Never renames the audience (no timestamp suffix). On duplicate name, reuse the
     * exact existing list so the user-facing name stays stable.
     *
     * @param  array<string, string>  $headers
     * @param  list<array{id: string, name: string}>  $knownLists
     * @return array{id: ?string, name: ?string, created?: bool, error: ?string}
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
            'Clickronix invalid-traffic exclusion. Event: '.$eventName
            .' + Google Client ID. Attach as negative audience on Search/Display campaigns.',
            0,
            500
        );

        // Prefer remarketing/rule list (shows under Audiences); CRM Contact Info often needs Customer Match agreement.
        $attempts = [
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
                                'ruleItems' => [[
                                    'name' => 'e:'.$eventName,
                                    'stringRuleItem' => [
                                        'operator' => 'EQUALS',
                                        'value' => $eventName,
                                    ],
                                ]],
                            ]],
                            'lookbackWindowDays' => $lifeSpan,
                        ]],
                    ],
                ],
            ],
            [
                'name' => $baseName,
                'description' => $description,
                'membershipStatus' => 'OPEN',
                'membershipLifeSpan' => $lifeSpan,
                'crmBasedUserList' => [
                    'uploadKeyType' => 'CONTACT_INFO',
                ],
            ],
        ];

        $lastError = 'User list create failed.';
        foreach ($attempts as $createBody) {
            $payload = ['operations' => [['create' => $createBody]]];
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/userLists:mutate"), $payload);

            // Duplicate name → reuse exact list; do not append date/time to the name.
            if (! $response->successful() && $this->isBenignDuplicate((string) $response->body())) {
                $existing = $this->matchUserListByName($knownLists, $baseName);
                if ($existing === null) {
                    $refreshed = $this->fetchUserLists($customerId, $version, $headers) ?? [];
                    $existing = $this->matchUserListByName($refreshed, $baseName);
                }
                if ($existing !== null) {
                    return [
                        'id' => $existing['id'],
                        'name' => $baseName,
                        'created' => false,
                        'error' => null,
                    ];
                }
                $lastError = 'An audience with this exact name already exists but could not be loaded.';
                continue;
            }

            if (! $response->successful()) {
                $lastError = Str::limit($this->extractError((string) $response->body()), 200);
                Log::warning('Google Ads user list create attempt failed', [
                    'customer_id' => $customerId,
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
                    'error' => null,
                ];
            }
            $lastError = 'User list created but ID missing in API response.';
        }

        return [
            'id' => null,
            'name' => null,
            'error' => $lastError,
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
