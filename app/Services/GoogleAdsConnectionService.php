<?php

namespace App\Services;

use App\Models\GoogleConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsConnectionService
{
    public ?string $lastRefreshError = null;

    public function resolveAccessToken(GoogleConnection $connection, bool $forceRefresh = false): ?string
    {
        if ($forceRefresh) {
            return $this->refreshAccessToken($connection);
        }

        if ($connection->access_token) {
            return $connection->access_token;
        }

        return $this->refreshAccessToken($connection);
    }

    public function refreshAccessToken(GoogleConnection $connection): ?string
    {
        $this->lastRefreshError = null;

        if (! $connection->refresh_token) {
            $this->lastRefreshError = 'No refresh token stored. Reconnect Google in Integrations.';

            return null;
        }

        $clientId = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');
        if ($clientId === '' || $clientSecret === '') {
            $this->lastRefreshError = 'GOOGLE_ADS_CLIENT_ID or GOOGLE_ADS_CLIENT_SECRET is missing in .env.';

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
            $message = (string) ($res->json('error_description') ?? $res->json('error') ?? $res->body());
            $this->lastRefreshError = 'OAuth refresh failed: ' . Str::limit(trim($message), 240);
            Log::warning('Google OAuth token refresh failed', [
                'connection_id' => $connection->id,
                'google_email' => $connection->google_email,
                'status' => $res->status(),
                'error' => $this->lastRefreshError,
            ]);

            return null;
        }

        $token = (string) ($res->json('access_token') ?? '');
        if ($token !== '') {
            $connection->access_token = $token;
            $connection->save();

            return $token;
        }

        $this->lastRefreshError = 'OAuth refresh returned an empty access token.';

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    public function apiHeaders(GoogleConnection $connection, bool $forceRefresh = false): ?array
    {
        $accessToken = $this->resolveAccessToken($connection, $forceRefresh);
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
