<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsAdvertisedHost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsDomainSync
{
    /** @var list<string> */
    private const LANDING_PAGE_QUERY = 'SELECT landing_page_view.unexpanded_final_url FROM landing_page_view WHERE landing_page_view.unexpanded_final_url != \'\' LIMIT 500';

    /** @var list<string> */
    private const FINAL_URLS_QUERY = 'SELECT ad_group_ad.ad.final_urls FROM ad_group_ad WHERE ad_group_ad.status != \'REMOVED\' LIMIT 500';

    /**
     * Pull advertised hostnames from Google Ads (stored separately; link manual domains only).
     */
    public function syncForAccount(
        int $userId,
        GoogleAdsAccount $account,
        string $customerId,
        string $apiVersion,
        array $headers
    ): int {
        if ((bool) $account->is_manager) {
            return 0;
        }

        $hostnames = $this->fetchHostnames($customerId, $apiVersion, $headers);
        if ($hostnames === []) {
            Log::info('Google Ads domain sync found no landing URLs', [
                'google_ads_account_id' => $account->id,
                'customer_id' => $customerId,
            ]);

            return 0;
        }

        $synced = 0;
        foreach ($hostnames as $hostname) {
            if ($this->upsertAdvertisedHost($userId, $account, $hostname)) {
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * @return list<string>
     */
    private function fetchHostnames(string $customerId, string $apiVersion, array $headers): array
    {
        $found = [];

        foreach ([self::LANDING_PAGE_QUERY, self::FINAL_URLS_QUERY] as $query) {
            $res = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->googleAdsUrl($apiVersion, "customers/{$customerId}/googleAds:searchStream"), [
                    'query' => $query,
                ]);

            if (! $res->successful()) {
                Log::warning('Google Ads domain URL fetch failed', [
                    'customer_id' => $customerId,
                    'status' => $res->status(),
                    'query' => Str::limit($query, 80),
                    'body' => Str::limit($res->body(), 500),
                ]);
                continue;
            }

            foreach ($this->parseSearchStreamHostnames($res->json()) as $hostname) {
                $found[$hostname] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * @param mixed $payload
     * @return list<string>
     */
    private function parseSearchStreamHostnames($payload): array
    {
        $hostnames = [];
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

                $landingUrl = $row['landingPageView']['unexpandedFinalUrl'] ?? null;
                if (is_string($landingUrl) && $landingUrl !== '') {
                    $hostname = $this->hostnameFromUrl($landingUrl);
                    if ($hostname !== null) {
                        $hostnames[] = $hostname;
                    }
                }

                $finalUrls = $row['adGroupAd']['ad']['finalUrls'] ?? [];
                if (is_array($finalUrls)) {
                    foreach ($finalUrls as $url) {
                        if (! is_string($url) || $url === '') {
                            continue;
                        }
                        $hostname = $this->hostnameFromUrl($url);
                        if ($hostname !== null) {
                            $hostnames[] = $hostname;
                        }
                    }
                }
            }
        }

        return $hostnames;
    }

    private function upsertAdvertisedHost(int $userId, GoogleAdsAccount $account, string $hostname): bool
    {
        GoogleAdsAdvertisedHost::updateOrCreate(
            [
                'google_ads_account_id' => $account->id,
                'hostname' => $hostname,
            ],
            ['synced_at' => now()]
        );

        $manual = Domain::query()
            ->where('user_id', $userId)
            ->manual()
            ->where('hostname', $hostname)
            ->first();

        if ($manual) {
            $manual->google_ads_account_id = $account->id;
            $manual->paid_marketing_connected = true;
            $manual->ads_synced_at = now();
            $manual->save();
        }

        return true;
    }

    private function hostnameFromUrl(string $url): ?string
    {
        $hostname = $this->normalizeHostname($url);
        if ($hostname === '' || ! str_contains($hostname, '.')) {
            return null;
        }
        if (filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        $blocked = ['googleadservices.com', 'google.com', 'gstatic.com', 'doubleclick.net', 'googlesyndication.com'];
        foreach ($blocked as $suffix) {
            if ($hostname === $suffix || str_ends_with($hostname, '.' . $suffix)) {
                return null;
            }
        }

        return $hostname;
    }

    private function normalizeHostname(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = explode('/', $value)[0] ?? $value;
        $value = explode('?', $value)[0] ?? $value;
        $value = explode('#', $value)[0] ?? $value;

        return rtrim($value, '.');
    }

    private function googleAdsUrl(string $version, string $path): string
    {
        $version = trim($version);
        $path = ltrim($path, '/');

        return "https://googleads.googleapis.com/{$version}/{$path}";
    }
}
