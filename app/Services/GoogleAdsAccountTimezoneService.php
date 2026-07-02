<?php

namespace App\Services;

use App\Models\GoogleAdsAccount;
use App\Support\UserTimezone;
use Illuminate\Support\Facades\Log;

class GoogleAdsAccountTimezoneService
{
    public function __construct(
        private readonly GoogleAdsConnectionService $connectionApi,
    ) {}

    public function refreshForAccount(GoogleAdsAccount $account, ?string $apiVersion = null, ?array $headers = null): ?string
    {
        $account->loadMissing('connection');
        $connection = $account->connection;

        if (! $connection || (bool) $account->is_manager) {
            return $account->time_zone;
        }

        $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
        if ($customerId === '') {
            return $account->time_zone;
        }

        $this->connectionApi->refreshAccessToken($connection);
        $connection->refresh();

        $headers ??= $this->connectionApi->apiHeaders($connection, forceRefresh: true);
        if (! $headers) {
            return $account->time_zone;
        }

        $loginId = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: $this->connectionApi->loginCustomerId()));
        if ($loginId !== '') {
            $headers['login-customer-id'] = $loginId;
        }

        $version = $apiVersion ?? ($this->connectionApi->apiVersions()[0] ?? 'v24');
        $timezone = $this->fetchCustomerTimezone($version, $customerId, $headers);

        if ($timezone !== null) {
            $account->forceFill(['time_zone' => $timezone])->save();
        }

        return $account->time_zone;
    }

    private function fetchCustomerTimezone(string $apiVersion, string $customerId, array $headers): ?string
    {
        $developerToken = config('services.google.ads_developer_token');
        if (! $developerToken) {
            return null;
        }

        $url = sprintf(
            'https://googleads.googleapis.com/%s/customers/%s/googleAds:searchStream',
            $apiVersion,
            $customerId
        );

        $response = \Illuminate\Support\Facades\Http::withHeaders(array_merge($headers, [
            'developer-token' => $developerToken,
            'Content-Type' => 'application/json',
        ]))->post($url, [
            'query' => 'SELECT customer.id, customer.time_zone FROM customer LIMIT 1',
        ]);

        if (! $response->successful()) {
            Log::info('Google Ads customer timezone fetch failed', [
                'customer_id' => $customerId,
                'status' => $response->status(),
            ]);

            return null;
        }

        foreach ($response->json() as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }

            foreach ($chunk['results'] ?? [] as $row) {
                $customer = $row['customer'] ?? [];
                $timezone = trim((string) ($customer['timeZone'] ?? $customer['time_zone'] ?? ''));

                if (UserTimezone::isValid($timezone)) {
                    return $timezone;
                }
            }
        }

        return null;
    }
}
