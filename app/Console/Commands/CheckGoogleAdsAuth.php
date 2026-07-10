<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\GoogleConnection;
use App\Services\GoogleAdsConnectionService;
use App\Services\GoogleAdsMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckGoogleAdsAuth extends Command
{
    protected $signature = 'google-ads:check-auth
        {--domain= : Test API call for this domain ID only}';

    protected $description = 'Verify Google Ads OAuth tokens and API credentials';

    public function handle(GoogleAdsConnectionService $connectionApi, GoogleAdsMetricsService $metrics): int
    {
        $clientId = (string) config('services.google_ads.client_id');
        $developerToken = (string) config('services.google_ads.developer_token');
        $loginCustomerId = $connectionApi->loginCustomerId();

        $this->line('Environment');
        $this->line('  GOOGLE_ADS_CLIENT_ID: ' . ($clientId !== '' ? 'set' : 'MISSING'));
        $this->line('  GOOGLE_ADS_CLIENT_SECRET: ' . (config('services.google_ads.client_secret') ? 'set' : 'MISSING'));
        $this->line('  GOOGLE_ADS_DEVELOPER_TOKEN: ' . ($developerToken !== '' ? 'set' : 'MISSING'));
        $this->line('  GOOGLE_ADS_LOGIN_CUSTOMER_ID: ' . ($loginCustomerId !== '' ? $loginCustomerId : 'not set'));
        $this->line('  GOOGLE_ADS_REDIRECT_URI: ' . (config('services.google_ads.redirect_uri') ?: 'not set'));
        $this->newLine();

        if ($clientId === '' || $developerToken === '') {
            $this->error('Fix missing GOOGLE_ADS_* values in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        $connections = GoogleConnection::query()->orderBy('id')->get();
        if ($connections->isEmpty()) {
            $this->warn('No google_connections rows. Connect Google in Admin → Integrations.');

            return self::FAILURE;
        }

        $ok = true;

        foreach ($connections as $connection) {
            $this->line("Connection #{$connection->id} ({$connection->google_email})");

            $token = $connectionApi->refreshAccessToken($connection);
            if (! $token) {
                $ok = false;
                $this->error('  Token refresh failed: ' . ($connectionApi->lastRefreshError ?? 'unknown'));
                $this->line('  → Reconnect this Gmail at Integrations on THIS server (production URL + same OAuth client).');

                continue;
            }

            $this->info('  Access token refreshed OK.');

            $probe = Http::timeout(10)
                ->withToken($token)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if ($probe->successful()) {
                $this->line('  Userinfo probe: OK (' . ($probe->json('email') ?? 'email n/a') . ')');
            } else {
                $ok = false;
                $this->warn('  Userinfo probe failed: HTTP ' . $probe->status());
            }
        }

        $domainQuery = Domain::query()
            ->whereNotNull('google_ads_account_id')
            ->with(['googleAdsAccount.connection'])
            ->orderBy('id');

        if ($this->option('domain')) {
            $domainQuery->where('id', (int) $this->option('domain'));
        }

        $domains = $domainQuery->get()->filter(
            fn (Domain $domain) => $domain->googleAdsAccount && ! $domain->googleAdsAccount->is_manager
        );

        if ($domains->isEmpty()) {
            $this->warn('No domains linked to client Google Ads accounts.');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('Google Ads API probe (1-day metrics)');

        $version = $connectionApi->apiVersions()[0] ?? 'v24';
        $today = now()->toDateString();

        foreach ($domains as $domain) {
            $account = $domain->googleAdsAccount;
            $connection = $account?->connection;
            if (! $account || ! $connection) {
                $this->warn("  Domain #{$domain->id} ({$domain->hostname}): missing account/connection");
                $ok = false;

                continue;
            }

            $headers = $connectionApi->apiHeaders($connection, forceRefresh: true);
            if (! $headers) {
                $ok = false;
                $this->error("  Domain #{$domain->id} ({$domain->hostname}): " . ($connectionApi->lastRefreshError ?? 'no API headers'));

                continue;
            }

            $loginId = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: $loginCustomerId));
            if ($loginId !== '') {
                $headers['login-customer-id'] = $loginId;
            }

            $rows = $metrics->dailyCampaignMetrics($account, $version, $headers, $today, $today, null);
            if ($rows !== []) {
                $this->info("  Domain #{$domain->id} ({$domain->hostname}): OK (" . count($rows) . ' row(s) today)');

                continue;
            }

            $ok = false;
            $apiError = $metrics->lastApiError ?? 'no rows (may be normal if no spend today)';
            $this->error("  Domain #{$domain->id} ({$domain->hostname}): {$apiError}");
        }

        $this->newLine();

        if ($ok) {
            $this->info('Google Ads auth looks good. Run: php artisan google-ads:sync-all');

            return self::SUCCESS;
        }

        $this->warn('Auth/API check failed. Reconnect Google on this server, verify .env, then: php artisan config:clear');

        return self::FAILURE;
    }
}
