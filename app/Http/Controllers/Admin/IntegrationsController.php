<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleAdsDomainSync;
use App\Models\DirectAdsIntegration;
use App\Models\Domain;
use App\Models\DomainGoogleAdsMapping;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleConnection;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IntegrationsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $connections = GoogleConnection::query()
            ->where('user_id', $user->id)
            ->with(['adsAccounts.domainMappings.domain'])
            ->latest('id')
            ->get();

        $domains = Domain::query()
            ->where('user_id', $user->id)
            ->orderBy('hostname')
            ->get();

        $paidMarketingDomains = Domain::query()
            ->where('user_id', $user->id)
            ->forPaidMarketing()
            ->orderBy('hostname')
            ->get();

        $manualDomains = Domain::query()
            ->where('user_id', $user->id)
            ->forBotProtection()
            ->orderBy('hostname')
            ->get();

        $accounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $user->id))
            ->synced()
            ->with(['syncedDomains' => fn ($q) => $q->where('user_id', $user->id)->orderBy('hostname')])
            ->orderBy('account_name')
            ->get();

        $mappings = DomainGoogleAdsMapping::query()
            ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id))
            ->with(['domain', 'account.connection'])
            ->latest('id')
            ->paginate(15);

        $directAds = DirectAdsIntegration::query()
            ->where('user_id', $user->id)
            ->orderBy('platform')
            ->get();

        $tagReady = $manualDomains->contains(fn (Domain $d) => (bool) $d->tag_connected);
        $paidReady = $paidMarketingDomains->isNotEmpty();
        $botReady = $manualDomains->contains(
            fn (Domain $d) => $d->tag_connected && $d->bot_mitigation_connected
        );
        $platformReady = $botReady && $paidReady;

        $requirementSteps = [
            ['label' => 'Tag Manager', 'done' => $tagReady],
            ['label' => 'Paid Marketing', 'done' => $paidReady],
            ['label' => 'Bot Protection', 'done' => $botReady],
            ['label' => 'Google Ads', 'done' => $connections->isNotEmpty() && $accounts->isNotEmpty()],
        ];

        return view('integrations', compact(
            'connections',
            'domains',
            'paidMarketingDomains',
            'manualDomains',
            'accounts',
            'mappings',
            'directAds',
            'tagReady',
            'paidReady',
            'botReady',
            'platformReady',
            'requirementSteps',
        ));
    }

    public function googleRedirect(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.google_ads.client_id');
        $redirectUri = (string) config('services.google_ads.redirect_uri');
        if ($clientId === '' || $redirectUri === '') {
            return back()->with('status', 'Google Ads OAuth is not configured. Set GOOGLE_ADS_CLIENT_ID and GOOGLE_ADS_REDIRECT_URI.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $context = $request->string('context')->toString() === 'auth' ? 'auth' : 'integrations';
        if ($context === 'integrations' && ! $request->user()) {
            return redirect()->route('login')->with('status', 'Please sign in first.');
        }
        $request->session()->put('google_oauth_context', $context);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', [
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/adwords',
            ]),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $oauthContext = (string) $request->session()->get('google_oauth_context', '');
        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $state = (string) $request->string('state')->toString();
        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Invalid OAuth state. Please try again.');
        }

        if ($request->filled('error')) {
            return $this->redirectAfterGoogleOAuth(
                $request,
                $oauthContext,
                'Google OAuth denied: ' . $request->string('error')->toString()
            );
        }

        $code = (string) $request->string('code')->toString();
        if ($code === '') {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Missing Google OAuth code.');
        }

        $clientId = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');
        $redirectUri = (string) config('services.google_ads.redirect_uri');

        $tokenRes = Http::asForm()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

        if (! $tokenRes->successful()) {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Google token exchange failed.');
        }

        $token = (array) $tokenRes->json();
        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Google access token missing.');
        }

        $userInfoRes = Http::timeout(15)
            ->withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $userInfoRes->successful()) {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Unable to fetch Google profile.');
        }

        $info = (array) $userInfoRes->json();
        $email = (string) ($info['email'] ?? '');
        $sub = (string) ($info['sub'] ?? '');
        $name = trim((string) ($info['name'] ?? ''));
        if ($email === '') {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Google profile email not available.');
        }

        $request->session()->forget('google_oauth_context');
        $isAuthFlow = $oauthContext === 'auth';
        $user = $request->user();

        if ($isAuthFlow && ! $user) {
            $defaultRole = Role::query()->where('slug', 'default-user')->first();
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name !== '' ? $name : Str::before($email, '@'),
                    'password' => Hash::make(Str::random(40)),
                    'role_id' => $defaultRole?->id,
                    'email_verified_at' => now(),
                ]
            );

            Auth::login($user);
            $request->session()->regenerate();
        }

        if (! $user) {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'No authenticated user for Google connection.');
        }

        $this->upsertGoogleConnection($user->id, $email, $sub, $token, $accessToken);

        return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Google account connected successfully.');
    }

    public function disconnect(Request $request, GoogleConnection $connection): RedirectResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);
        $connection->delete();

        return back()->with('status', 'Google connection removed.');
    }

    public function syncAccounts(Request $request, GoogleConnection $connection): RedirectResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);
        $developerToken = (string) config('services.google_ads.developer_token');
        if ($developerToken === '') {
            return back()->with('status', 'Missing GOOGLE_ADS_DEVELOPER_TOKEN.');
        }

        $accessToken = $this->resolveAccessToken($connection);
        if (! $accessToken) {
            return back()->with('status', 'Could not resolve Google access token. Reconnect Google.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'developer-token' => $developerToken,
            'Accept' => 'application/json',
        ];

        // login-customer-id is used for per-customer calls below, not listAccessibleCustomers.
        $loginCustomerId = preg_replace('/\D+/', '', (string) config('services.google_ads.login_customer_id'));

        $versions = $this->googleAdsApiVersions();
        $usedVersion = null;
        $listRes = null;
        $attemptedVersions = [];

        foreach ($versions as $version) {
            $usedVersion = $version;
            $attemptedVersions[] = $version;
            // REST: GET https://googleads.googleapis.com/v{VERSION}/customers:listAccessibleCustomers
            $listRes = Http::timeout(20)
                ->withHeaders($headers)
                ->get($this->googleAdsUrl($version, 'customers:listAccessibleCustomers'));

            if ($listRes->status() !== 404) {
                break;
            }
        }

        if ($listRes && $listRes->status() === 401 && $connection->refresh_token) {
            $refreshed = $this->refreshAccessToken($connection);
            if ($refreshed) {
                $headers['Authorization'] = 'Bearer ' . $refreshed;
                $listRes = Http::timeout(20)
                    ->withHeaders($headers)
                    ->get($this->googleAdsUrl((string) $usedVersion, 'customers:listAccessibleCustomers'));
            }
        }

        if (! $listRes || ! $listRes->successful()) {
            $reason = $this->extractApiError($listRes);
            $all404 = $listRes && $listRes->status() === 404;
            Log::warning('Google Ads listAccessibleCustomers failed', [
                'user_id' => $request->user()->id,
                'connection_id' => $connection->id,
                'status' => $listRes?->status(),
                'version_tried' => $usedVersion,
                'versions_configured' => $versions,
                'versions_attempted' => $attemptedVersions,
                'body' => $listRes ? Str::limit($listRes->body(), 2000) : null,
            ]);

            if ($all404) {
                return back()->with('status', 'Google Ads API version not found. Set GOOGLE_ADS_API_VERSIONS=v24,v23,v22,v21,v20 in .env, run php artisan config:clear, then sync again.');
            }

            return back()->with('status', 'Google Ads account listing failed: ' . str($reason)->limit(220));
        }

        $baseDetailHeaders = array_merge($headers, [
            'Content-Type' => 'application/json',
        ]);

        $resources = (array) ($listRes->json('resourceNames') ?? []);
        $synced = 0;
        $skipped = 0;
        $domainsSynced = 0;
        $domainSync = app(GoogleAdsDomainSync::class);

        GoogleAdsAccount::query()
            ->where('google_connection_id', $connection->id)
            ->update(['is_active' => false]);

        foreach ($resources as $resource) {
            $customerId = preg_replace('/\D+/', '', (string) $resource);
            if (! $customerId) {
                continue;
            }

            $detailRes = $this->googleAdsSearchStream(
                (string) $usedVersion,
                $customerId,
                'SELECT customer.id, customer.descriptive_name, customer.manager FROM customer LIMIT 1',
                $baseDetailHeaders,
                $loginCustomerId !== '' ? $loginCustomerId : null
            );

            if (! $detailRes->successful()) {
                $skipped++;
                Log::info('Google Ads customer skipped (not accessible)', [
                    'connection_id' => $connection->id,
                    'customer_id' => $customerId,
                    'status' => $detailRes->status(),
                    'reason' => $this->extractApiError($detailRes),
                    'version' => $usedVersion,
                ]);
                continue;
            }

            $customer = $this->parseCustomerFromSearchStream($detailRes->json());
            if ($customer === null) {
                $skipped++;
                continue;
            }

            $customerId = preg_replace('/\D+/', '', (string) ($customer['id'] ?? $customerId));
            $display = 'AW-' . $customerId;
            $name = trim((string) ($customer['descriptiveName'] ?? $customer['descriptive_name'] ?? ''));
            if ($name === '') {
                $name = 'Google Ads ' . $display;
            }
            $isManager = (bool) ($customer['manager'] ?? false);

            $adsAccount = GoogleAdsAccount::updateOrCreate(
                [
                    'google_connection_id' => $connection->id,
                    'customer_id' => $customerId,
                ],
                [
                    'display_customer_id' => $display,
                    'account_name' => $name,
                    'is_manager' => $isManager,
                    'manager_customer_id' => null,
                    'google_tag_id' => $display,
                    'is_active' => true,
                ]
            );
            $synced++;

            if ($isManager) {
                $childResult = $this->syncManagerChildAccounts(
                    $request->user()->id,
                    $connection,
                    $customerId,
                    (string) $usedVersion,
                    $baseDetailHeaders,
                    $domainSync
                );
                $synced += $childResult['synced'];
                $skipped += $childResult['skipped'];
                $domainsSynced += $childResult['domains'];

                continue;
            }

            $accountHeaders = $this->googleAdsDetailHeaders($baseDetailHeaders, $loginCustomerId !== '' ? $loginCustomerId : null);
            $domainsSynced += $domainSync->syncForAccount(
                $request->user()->id,
                $adsAccount,
                $customerId,
                (string) $usedVersion,
                $accountHeaders
            );
        }

        $domainNote = $domainsSynced > 0
            ? " Discovered {$domainsSynced} advertised domain(s) from your campaigns."
            : '';

        if ($synced === 0 && $skipped > 0) {
            return back()->with('status', "No accessible Google Ads accounts were synced. {$skipped} account(s) were skipped (disabled, deactivated, or no permission). Check GOOGLE_ADS_LOGIN_CUSTOMER_ID if you use an MCC.{$domainNote}");
        }

        if ($skipped > 0) {
            return back()->with('status', "Synced {$synced} Google Ads account(s) with names. {$skipped} inaccessible account(s) were skipped.{$domainNote}");
        }

        return back()->with('status', "Synced {$synced} Google Ads account(s).{$domainNote}");
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'google_connection_id' => ['required', 'integer'],
            'customer_id' => ['required', 'string', 'max:64'],
            'display_customer_id' => ['nullable', 'string', 'max:64'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'manager_customer_id' => ['nullable', 'string', 'max:64'],
            'is_manager' => ['nullable', 'boolean'],
            'google_tag_id' => ['nullable', 'string', 'max:64'],
        ]);

        $connection = GoogleConnection::query()
            ->where('id', $data['google_connection_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        GoogleAdsAccount::updateOrCreate(
            [
                'google_connection_id' => $connection->id,
                'customer_id' => $data['customer_id'],
            ],
            [
                'display_customer_id' => $data['display_customer_id'] ?? null,
                'account_name' => $data['account_name'] ?? null,
                'manager_customer_id' => $data['manager_customer_id'] ?? null,
                'is_manager' => (bool) ($data['is_manager'] ?? false),
                'google_tag_id' => $data['google_tag_id'] ?? null,
                'is_active' => true,
            ]
        );

        return back()->with('status', 'Google Ads account saved.');
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain_id' => ['required', 'integer'],
            'google_ads_account_id' => ['required', 'integer'],
            'protection_type' => ['required', 'in:ip_blocking,pixel_guard'],
            'audience_exclusion_enabled' => ['nullable', 'boolean'],
        ]);

        $domain = Domain::query()
            ->where('id', $data['domain_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $account = GoogleAdsAccount::query()
            ->where('id', $data['google_ads_account_id'])
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        DomainGoogleAdsMapping::updateOrCreate(
            [
                'domain_id' => $domain->id,
                'google_ads_account_id' => $account->id,
            ],
            [
                'protection_type' => $data['protection_type'],
                'audience_exclusion_enabled' => (bool) ($data['audience_exclusion_enabled'] ?? true),
                'settings' => [
                    'linked_at' => now()->toISOString(),
                ],
            ]
        );

        $domain->google_ads_account_id = $account->id;
        $domain->paid_marketing_connected = true;
        $domain->save();

        return back()->with('status', 'Domain linked to Google Ads.');
    }

    public function destroyMapping(Request $request, DomainGoogleAdsMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->domain && $mapping->domain->user_id === $request->user()->id, 403);
        $mapping->delete();

        return back()->with('status', 'Mapping removed.');
    }

    private function resolveAccessToken(GoogleConnection $connection): ?string
    {
        if ($connection->access_token) {
            return $connection->access_token;
        }

        return $this->refreshAccessToken($connection);
    }

    private function refreshAccessToken(GoogleConnection $connection): ?string
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

    private function googleAdsApiVersions(): array
    {
        $configured = trim((string) config('services.google_ads.api_versions', 'v24,v23,v22,v21,v20'));
        $versions = collect(explode(',', $configured))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();

        $fallback = ['v24', 'v23', 'v22', 'v21', 'v20'];

        return ! empty($versions) ? $versions : $fallback;
    }

    private function googleAdsUrl(string $version, string $path): string
    {
        $version = trim($version);
        $path = ltrim($path, '/');
        return "https://googleads.googleapis.com/{$version}/{$path}";
    }

    private function extractApiError($response): string
    {
        if (! $response) {
            return 'No response from Google Ads API.';
        }

        $jsonMessage = $response->json('error.message');
        if (is_string($jsonMessage) && $jsonMessage !== '') {
            return $jsonMessage;
        }

        return trim(strip_tags(Str::limit($response->body(), 500)));
    }

    /**
     * @param mixed $payload
     * @return array<string, mixed>|null
     */
    private function parseCustomerFromSearchStream($payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach ($payload as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            foreach (($chunk['results'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $customer = $row['customer'] ?? null;
                if (is_array($customer) && $customer !== []) {
                    return $customer;
                }
            }
        }

        return null;
    }

    /**
     * @return array{synced: int, skipped: int, domains: int}
     */
    private function syncManagerChildAccounts(
        int $userId,
        GoogleConnection $connection,
        string $managerCustomerId,
        string $apiVersion,
        array $baseDetailHeaders,
        GoogleAdsDomainSync $domainSync
    ): array {
        $managerHeaders = $this->googleAdsDetailHeaders($baseDetailHeaders, $managerCustomerId);
        $listRes = $this->googleAdsSearchStream(
            $apiVersion,
            $managerCustomerId,
            'SELECT customer_client.client_customer, customer_client.descriptive_name, customer_client.manager, customer_client.status FROM customer_client WHERE customer_client.manager = FALSE AND customer_client.hidden = FALSE',
            $baseDetailHeaders,
            $managerCustomerId
        );

        if (! $listRes->successful()) {
            Log::warning('Google Ads MCC child listing failed', [
                'connection_id' => $connection->id,
                'manager_customer_id' => $managerCustomerId,
                'status' => $listRes->status(),
                'reason' => $this->extractApiError($listRes),
            ]);

            return ['synced' => 0, 'skipped' => 0, 'domains' => 0];
        }

        $synced = 0;
        $skipped = 0;
        $domains = 0;

        foreach ($this->parseCustomerClientRows($listRes->json()) as $client) {
            $clientId = preg_replace('/\D+/', '', (string) ($client['clientCustomer'] ?? $client['client_customer'] ?? ''));
            if ($clientId === '' || $clientId === $managerCustomerId) {
                continue;
            }

            if ((bool) ($client['manager'] ?? false)) {
                continue;
            }

            $status = strtoupper((string) ($client['status'] ?? 'ENABLED'));
            if ($status !== '' && $status !== 'ENABLED') {
                $skipped++;
                continue;
            }

            $childName = trim((string) ($client['descriptiveName'] ?? $client['descriptive_name'] ?? ''));
            if ($childName === '') {
                $childName = 'Google Ads AW-' . $clientId;
            }

            $adsAccount = GoogleAdsAccount::updateOrCreate(
                [
                    'google_connection_id' => $connection->id,
                    'customer_id' => $clientId,
                ],
                [
                    'display_customer_id' => 'AW-' . $clientId,
                    'account_name' => $childName,
                    'is_manager' => false,
                    'manager_customer_id' => $managerCustomerId,
                    'google_tag_id' => 'AW-' . $clientId,
                    'is_active' => true,
                ]
            );
            $synced++;

            $domains += $domainSync->syncForAccount(
                $userId,
                $adsAccount,
                $clientId,
                $apiVersion,
                $managerHeaders
            );
        }

        Log::info('Google Ads MCC child sync finished', [
            'connection_id' => $connection->id,
            'manager_customer_id' => $managerCustomerId,
            'child_accounts_synced' => $synced,
            'domains_synced' => $domains,
            'skipped' => $skipped,
        ]);

        return ['synced' => $synced, 'skipped' => $skipped, 'domains' => $domains];
    }

    /**
     * @param array<string, string> $baseHeaders
     * @return array<string, string>
     */
    private function googleAdsDetailHeaders(array $baseHeaders, ?string $loginCustomerId): array
    {
        $headers = $baseHeaders;
        unset($headers['login-customer-id']);

        if ($loginCustomerId !== null && $loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        return $headers;
    }

    /**
     * @param array<string, string> $baseHeaders
     */
    private function googleAdsSearchStream(
        string $apiVersion,
        string $customerId,
        string $query,
        array $baseHeaders,
        ?string $loginCustomerId
    ) {
        $response = Http::timeout(20)
            ->withHeaders($this->googleAdsDetailHeaders($baseHeaders, $loginCustomerId))
            ->post($this->googleAdsUrl($apiVersion, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        if ($response->successful() || $loginCustomerId === null || $loginCustomerId === '') {
            return $response;
        }

        return Http::timeout(20)
            ->withHeaders($this->googleAdsDetailHeaders($baseHeaders, null))
            ->post($this->googleAdsUrl($apiVersion, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);
    }

    /**
     * @param mixed $payload
     * @return list<array<string, mixed>>
     */
    private function parseCustomerClientRows($payload): array
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
                if (! is_array($row)) {
                    continue;
                }
                $client = $row['customerClient'] ?? null;
                if (is_array($client) && $client !== []) {
                    $rows[] = $client;
                }
            }
        }

        return $rows;
    }

    private function upsertGoogleConnection(int $userId, string $email, string $sub, array $token, string $accessToken): void
    {
        GoogleConnection::updateOrCreate(
            [
                'user_id' => $userId,
                'google_email' => $email,
            ],
            [
                'google_sub' => $sub !== '' ? $sub : null,
                'refresh_token' => $token['refresh_token'] ?? null,
                'access_token' => $accessToken,
                'scopes' => $token['scope'] ?? null,
                'connected_at' => now(),
            ]
        );
    }

    private function redirectAfterGoogleOAuth(Request $request, string $context, string $message): RedirectResponse
    {
        if ($context === 'auth' && Auth::check()) {
            if ($request->user()?->is_admin) {
                return redirect()->intended(route('dashboard', [], false))->with('status', $message);
            }

            return redirect()->intended('/')->with('status', $message);
        }

        if ($context === 'auth') {
            return redirect()->route('login')->with('status', $message);
        }

        return redirect()->route('integrations')->with('status', $message);
    }

    public function connectedJson(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $google = GoogleConnection::query()
            ->where('user_id', $userId)
            ->with(['adsAccounts'])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'platform' => 'google',
                'email' => $c->google_email,
                'connected_at' => optional($c->connected_at)->toIso8601String(),
                'accounts' => $c->adsAccounts
                    ->filter(fn ($a) => $a->is_active && filled($a->account_name))
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'customer_id' => $a->customer_id,
                        'display_customer_id' => $a->display_customer_id,
                        'account_name' => $a->account_name,
                        'google_tag_id' => $a->google_tag_id,
                        'is_manager' => (bool) $a->is_manager,
                        'is_active' => (bool) $a->is_active,
                    ])->values(),
            ])->values();

        $direct = DirectAdsIntegration::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'platform' => $d->platform,
                'account_label' => $d->account_label,
                'account_id' => $d->account_id,
                'tag_id' => $d->tag_id,
                'connected_at' => optional($d->connected_at)->toIso8601String(),
            ])->values();

        return response()->json([
            'google' => $google,
            'direct' => $direct,
        ]);
    }

    public function statusJson(Request $request): JsonResponse
    { 
        $userId = $request->user()->id;
        $googleConnected = GoogleConnection::where('user_id', $userId)->exists();
        $googleAccountsCount = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $userId))
            ->synced()
            ->count();
        $directCount = DirectAdsIntegration::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->count();
        $mappingsCount = DomainGoogleAdsMapping::query()
            ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
            ->count();

        $devTokenConfigured = (string) config('services.google_ads.developer_token') !== '';
        $oauthConfigured = (string) config('services.google_ads.client_id') !== ''
            && (string) config('services.google_ads.client_secret') !== '';

        return response()->json([
            'google' => [
                'connected' => $googleConnected,
                'accounts' => $googleAccountsCount,
                'oauth_configured' => $oauthConfigured,
                'developer_token_configured' => $devTokenConfigured,
            ],
            'direct' => [
                'connected' => $directCount > 0,
                'count' => $directCount,
            ],
            'domain_mappings' => $mappingsCount,
        ]);
    }

    public function allJson(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'google_connections' => GoogleConnection::where('user_id', $userId)->count(),
            'google_ads_accounts' => GoogleAdsAccount::query()
                ->whereHas('connection', fn ($q) => $q->where('user_id', $userId))
                ->synced()
                ->get(['id', 'customer_id', 'display_customer_id', 'account_name', 'google_tag_id', 'is_manager', 'is_active']),
            'direct_ads' => DirectAdsIntegration::where('user_id', $userId)
                ->get(['id', 'platform', 'account_label', 'account_id', 'tag_id', 'is_active']),
            'domain_mappings' => DomainGoogleAdsMapping::query()
                ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
                ->with('domain:id,hostname', 'account:id,customer_id,display_customer_id,google_tag_id')
                ->get(),
        ]);
    }

    public function googleOauthUrl(Request $request): JsonResponse
    {
        $clientId = (string) config('services.google_ads.client_id');
        $redirectUri = (string) config('services.google_ads.redirect_uri');

        if ($clientId === '' || $redirectUri === '') {
            return response()->json([
                'configured' => false,
                'message' => 'Set GOOGLE_ADS_CLIENT_ID and GOOGLE_ADS_REDIRECT_URI in .env.',
            ], 200);
        }

        return response()->json([
            'configured' => true,
            'url' => route('integrations.google.redirect'),
        ]);
    }

    public function pixelGuardGet(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $accounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $userId))
            ->synced()
            ->get(['id', 'customer_id', 'display_customer_id', 'account_name', 'google_tag_id', 'is_active']);

        $mappings = DomainGoogleAdsMapping::query()
            ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
            ->where('protection_type', 'pixel_guard')
            ->with(['domain:id,hostname', 'account:id,customer_id,display_customer_id,google_tag_id'])
            ->get();

        return response()->json([
            'accounts' => $accounts,
            'mappings' => $mappings,
        ]);
    }

    public function pixelGuardSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'google_tag_id' => ['required', 'string', 'max:120'],
        ]);

        $account = GoogleAdsAccount::query()
            ->where('id', $data['account_id'])
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $account->google_tag_id = trim($data['google_tag_id']);
        $account->save();

        return response()->json([
            'ok' => true,
            'account' => [
                'id' => $account->id,
                'customer_id' => $account->customer_id,
                'google_tag_id' => $account->google_tag_id,
            ],
        ]);
    }

    public function audienceExclusionSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mapping_id' => ['required', 'integer'],
            'enabled' => ['required', 'boolean'],
        ]);

        $mapping = DomainGoogleAdsMapping::query()
            ->where('id', $data['mapping_id'])
            ->whereHas('domain', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $mapping->audience_exclusion_enabled = (bool) $data['enabled'];
        $settings = (array) ($mapping->settings ?? []);
        $settings['audience_exclusion_updated_at'] = now()->toIso8601String();
        $mapping->settings = $settings;
        $mapping->save();

        return response()->json([
            'ok' => true,
            'mapping_id' => $mapping->id,
            'enabled' => (bool) $mapping->audience_exclusion_enabled,
        ]);
    }

    public function directAdsList(Request $request): JsonResponse
    {
        $items = DirectAdsIntegration::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('platform')
            ->get(['id', 'platform', 'account_label', 'account_id', 'tag_id', 'is_active', 'connected_at']);

        return response()->json($items);
    }

    public function directAdsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'max:40'],
            'account_label' => ['nullable', 'string', 'max:255'],
            'account_id' => ['nullable', 'string', 'max:120'],
            'tag_id' => ['nullable', 'string', 'max:120'],
        ]);

        $integration = DirectAdsIntegration::create([
            'user_id' => $request->user()->id,
            'platform' => strtolower(trim($data['platform'])),
            'account_label' => $data['account_label'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'tag_id' => $data['tag_id'] ?? null,
            'is_active' => true,
            'connected_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'integration' => $integration,
        ]);
    }

    public function directAdsDestroy(Request $request, DirectAdsIntegration $integration): JsonResponse
    {
        abort_unless($integration->user_id === $request->user()->id, 403);
        $integration->delete();

        return response()->json(['ok' => true, 'id' => $integration->id]);
    }
}
