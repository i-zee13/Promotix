<?php

namespace App\Services;

use App\Models\GoogleConnection;
use Illuminate\Support\Facades\Http;

class GoogleAdsConnectionService
{
    public function resolveAccessToken(GoogleConnection $connection): ?string
    {
        if ($connection->access_token) {
            return $connection->access_token;
        }

        return $this->refreshAccessToken($connection);
    }

    public function refreshAccessToken(GoogleConnection $connection): ?string
    {
        if (! $connection->refresh_token) {
            return null;
        }

        $clientId = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $res = Http::asForm()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if (! $res->successful()) {
            return null;
        }

        $token = (string) ($res->json('access_token') ?? '');
        if ($token !== '') {
            $connection->access_token = $token;
            $connection->save();

            return $token;
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    public function apiHeaders(GoogleConnection $connection): ?array
    {
        $accessToken = $this->resolveAccessToken($connection);
        $developerToken = (string) config('services.google_ads.developer_token');
        if (! $accessToken || $developerToken === '') {
            return null;
        }

        return [
            'Authorization' => 'Bearer ' . $accessToken,
            'developer-token' => $developerToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return list<string>
     */
    public function apiVersions(): array
    {
        $configured = trim((string) config('services.google_ads.api_versions', 'v24,v23,v22,v21,v20'));
        $versions = collect(explode(',', $configured))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();

        return ! empty($versions) ? $versions : ['v24', 'v23', 'v22', 'v21', 'v20'];
    }

    public function loginCustomerId(): string
    {
        return preg_replace('/\D+/', '', (string) config('services.google_ads.login_customer_id'));
    }
}
