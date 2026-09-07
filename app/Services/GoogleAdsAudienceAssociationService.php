<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainGoogleAdsMapping;
use App\Models\GoogleAdsAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Attach a Google Ads user list (audience) as a negative campaign criterion.
 * Google then stops serving matching users — we only attach the list.
 */
class GoogleAdsAudienceAssociationService
{
    public function __construct(
        private readonly GoogleAdsConnectionService $connectionApi,
    ) {
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array{
     *   ok: bool,
     *   attached: list<string>,
     *   failed: list<string>,
     *   message: string,
     *   stored: array<string, mixed>
     * }
     */
    public function applyToCampaigns(
        Domain $domain,
        array $campaignIds,
        string $audienceName,
        ?string $userListId = null,
        string $eventName = AudienceSignalService::DEFAULT_EVENT,
    ): array {
        $campaignIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => preg_replace('/\D+/', '', (string) $id),
            $campaignIds,
        ))));

        $stored = [
            'audience_name' => $audienceName,
            'event_name' => $eventName,
            'user_list_id' => $userListId ? preg_replace('/\D+/', '', $userListId) : null,
            'campaign_ids' => $campaignIds,
            'desired' => true,
            'status' => 'queued',
            'updated_at' => now()->toIso8601String(),
        ];

        $this->persistAssociation($domain, $stored);

        if ($campaignIds === []) {
            return [
                'ok' => false,
                'attached' => [],
                'failed' => [],
                'message' => 'Select at least one campaign.',
                'stored' => $stored,
            ];
        }

        $account = $this->resolveAccount($domain);
        if (! $account || ! $account->connection || (bool) $account->is_manager) {
            $stored['status'] = 'saved_no_account';
            $this->persistAssociation($domain, $stored);

            return [
                'ok' => true,
                'attached' => [],
                'failed' => [],
                'message' => 'Audience exclusion saved for '.$domain->hostname.'. Link Google Ads to attach the list to campaigns via API.',
                'stored' => $stored,
            ];
        }

        $listId = $stored['user_list_id'];
        if ($listId === null || $listId === '') {
            $stored['status'] = 'awaiting_user_list';
            $this->persistAssociation($domain, $stored);

            return [
                'ok' => true,
                'attached' => [],
                'failed' => [],
                'message' => 'Audience “'.$audienceName.'” queued for '.count($campaignIds).' campaign(s). '
                    .'Google builds membership from '.$eventName.' + Client ID. '
                    .'When the GA4/Ads audience (user list) ID is available, attach it to finish API apply.',
                'stored' => $stored,
            ];
        }

        $headers = $this->headersForAccount($account);
        if ($headers === null) {
            $stored['status'] = 'auth_failed';
            $this->persistAssociation($domain, $stored);

            return [
                'ok' => false,
                'attached' => [],
                'failed' => $campaignIds,
                'message' => 'Google Ads API credentials unavailable. Reconnect Google Ads.',
                'stored' => $stored,
            ];
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        $version = $this->connectionApi->apiVersions()[0] ?? 'v24';
        $userListResource = "customers/{$customerId}/userLists/{$listId}";
        $attached = [];
        $failed = [];

        foreach ($campaignIds as $campaignId) {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($version, "customers/{$customerId}/campaignCriteria:mutate"), [
                    'operations' => [[
                        'create' => [
                            'campaign' => "customers/{$customerId}/campaigns/{$campaignId}",
                            'negative' => true,
                            'userList' => [
                                'userList' => $userListResource,
                            ],
                        ],
                    ]],
                ]);

            if ($response->successful() || $this->isBenignDuplicate((string) $response->body())) {
                $attached[] = $campaignId;
                continue;
            }

            $failed[] = $campaignId.': '.Str::limit($this->extractError((string) $response->body()), 160);
            Log::warning('Google Ads audience exclusion attach failed', [
                'domain_id' => $domain->id,
                'campaign_id' => $campaignId,
                'user_list_id' => $listId,
                'body' => Str::limit((string) $response->body(), 500),
            ]);
        }

        $stored['status'] = $attached !== [] ? ($failed === [] ? 'applied' : 'partial') : 'failed';
        $stored['attached_campaign_ids'] = $attached;
        $stored['errors'] = $failed;
        $stored['updated_at'] = now()->toIso8601String();
        $this->persistAssociation($domain, $stored);

        $message = $attached !== []
            ? 'Attached audience to '.count($attached).' campaign(s). Google will exclude matching Client IDs going forward.'
            : 'Could not attach audience to campaigns: '.implode(' | ', array_slice($failed, 0, 2));

        return [
            'ok' => $attached !== [],
            'attached' => $attached,
            'failed' => $failed,
            'message' => $message,
            'stored' => $stored,
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
        $settings['audience_association'] = $association;
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
