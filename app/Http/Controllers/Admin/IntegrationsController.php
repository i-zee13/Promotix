<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleAdsConnectionService;
use App\Services\GoogleAdsAccountTimezoneService;
use App\Services\GoogleAdsDomainSync;
use App\Services\GoogleAdsMetricsService;
use App\Models\DirectAdsIntegration;
use App\Models\Domain;
use App\Models\DomainGoogleAdsMapping;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleConnection;
use App\Models\IntegrationSyncLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminIntegrationCatalog;
use App\Support\AudienceExclusionAudiences;
use App\Support\GoogleAdsApiHealth;
use App\Support\UserTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            ->manual()
            ->orderBy('hostname')
            ->get();

        $manualDomains = Domain::query()
            ->where('user_id', $user->id)
            ->forBotProtection()
            ->with(['googleAdsAccount', 'googleAdsMappings.account'])
            ->withCount('googleAdsMappings')
            ->orderBy('hostname')
            ->get();

        $domainConnections = $manualDomains->map(fn (Domain $d) => [
            'id' => $d->id,
            'hostname' => $d->hostname,
            // Tag script only — never treat as Google Ads API / OAuth.
            'tag_connected' => (bool) $d->tag_connected,
            'gtm_container_id' => filled($d->gtm_container_id) ? strtoupper(trim((string) $d->gtm_container_id)) : '',
            'gtm_connected' => filled($d->gtm_container_id),
            'google_connected' => false,
            'google_ads_connected' => $d->google_ads_account_id !== null || (int) ($d->google_ads_mappings_count ?? 0) > 0,
            'last_seen_at' => $d->last_seen_at
                ? \Illuminate\Support\Carbon::parse((string) $d->last_seen_at)->toIso8601String()
                : null,
            'steps' => [
                ['label' => 'Tag Manager', 'done' => (bool) $d->tag_connected],
                ['label' => 'GTM', 'done' => filled($d->gtm_container_id)],
                ['label' => 'Paid Marketing', 'done' => (bool) $d->paid_marketing_connected || $d->google_ads_account_id !== null],
                ['label' => 'Analytics', 'done' => (bool) $d->tag_connected || (bool) $d->bot_mitigation_connected],
                ['label' => 'Google Ads', 'done' => $d->google_ads_account_id !== null || (int) ($d->google_ads_mappings_count ?? 0) > 0],
            ],
        ])->values()->all();

        $accounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $user->id))
            ->synced()
            ->with(['advertisedHosts' => fn ($q) => $q->orderBy('hostname')])
            ->orderBy('account_name')
            ->get();

        $mappings = DomainGoogleAdsMapping::query()
            ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id))
            ->with(['domain', 'account.connection'])
            ->latest('id')
            ->get();

        $directAds = DirectAdsIntegration::query()
            ->where('user_id', $user->id)
            ->orderBy('platform')
            ->get();

        $tagReady = $manualDomains->contains(fn (Domain $d) => (bool) $d->tag_connected);
        $paidReady = $paidMarketingDomains->isNotEmpty();
        $botReady = $manualDomains->contains(
            fn (Domain $d) => (bool) $d->tag_connected || (bool) $d->bot_mitigation_connected
        );
        $platformReady = $botReady && $paidReady;

        $requirementSteps = [
            ['label' => 'Tag Manager', 'done' => $tagReady],
            ['label' => 'Paid Marketing', 'done' => $paidReady],
            ['label' => 'Analytics', 'done' => $botReady],
            ['label' => 'Google Ads', 'done' => $connections->isNotEmpty() && $accounts->isNotEmpty()],
        ];

        $primary = $connections->first();
        $connectionHealth = [
            'oauth_connected' => $connections->isNotEmpty(),
            'email' => $primary?->google_email,
            'health_status' => GoogleAdsApiHealth::status($primary, $accounts->count()),
            'last_sync_at' => optional($primary?->last_sync_at)->toIso8601String(),
            'last_sync_status' => $primary?->last_sync_status,
            'last_sync_message' => $primary?->last_sync_message,
            'accounts' => $accounts->count(),
            'tracking_active' => $tagReady,
            'events_today' => Schema::hasTable('visits')
                ? (int) DB::table('visits')
                    ->whereIn('domain_id', $manualDomains->pluck('id'))
                    ->whereDate('visited_at', now()->toDateString())
                    ->count()
                : 0,
            'last_event_at' => $manualDomains->max('last_seen_at'),
        ];

        $firstDomain = $manualDomains->first();
        $firstAccount = $accounts->first();
        $hasFirstClick = ((int) ($connectionHealth['events_today'] ?? 0) > 0)
            || filled($connectionHealth['last_event_at'] ?? null);
        $adsCustomerId = $firstAccount
            ? $firstAccount->formattedCustomerId()
            : '';

        $domainVisitTotals = collect();
        if (Schema::hasTable('visits') && $manualDomains->isNotEmpty()) {
            $domainVisitTotals = DB::table('visits')
                ->whereIn('domain_id', $manualDomains->pluck('id'))
                ->selectRaw('domain_id, COUNT(*) as total')
                ->groupBy('domain_id')
                ->pluck('total', 'domain_id');
        }

        $googleEmail = $primary?->google_email ?: '';
        $googleConnected = $connections->isNotEmpty();

        $buildSetupProgressForDomain = function (?Domain $domain) use (
            $googleConnected,
            $googleEmail,
            $domainVisitTotals
        ): array {
            if (! $domain) {
                return [];
            }

            $linkedAccount = $domain->googleAdsAccount
                ?: $domain->googleAdsMappings->first()?->account;
            $adsDone = $googleConnected && (
                $linkedAccount !== null
                || $domain->google_ads_account_id !== null
                || (int) ($domain->google_ads_mappings_count ?? 0) > 0
            );
            $adsDetail = 'Link an ads account';
            if (! $googleConnected) {
                $adsDetail = 'Connect Google first';
            } elseif ($linkedAccount) {
                $cid = $linkedAccount->formattedCustomerId();
                $adsDetail = $cid !== '' ? $cid : ($googleEmail ?: 'Connected');
            }

            $trackingDone = (bool) $domain->tag_connected;
            $gtmId = strtoupper(trim((string) ($domain->gtm_container_id ?: '')));
            $gtmDone = $gtmId !== '' && preg_match('/^GTM-[A-Z0-9]+$/', $gtmId);
            $visitCount = (int) ($domainVisitTotals[$domain->id] ?? 0);
            $clickDone = $visitCount > 0 || filled($domain->last_seen_at);
            $clickDetail = 'Waiting for traffic';
            if ($clickDone) {
                $clickDetail = filled($domain->last_seen_at)
                    ? \Illuminate\Support\Carbon::parse((string) $domain->last_seen_at)->diffForHumans()
                    : number_format($visitCount).' clicks';
            }

            $protectionDone = (bool) $domain->bot_mitigation_connected
                || (bool) $domain->paid_marketing_connected;

            $scriptKey = (string) ($domain->domain_key ?: '');
            $crx = $scriptKey !== '' ? 'CRX-'.strtoupper(substr($scriptKey, 0, 6)) : '';
            if ($trackingDone && $gtmDone) {
                $scriptDetail = trim($crx.' · '.$gtmId);
            } elseif ($trackingDone) {
                $scriptDetail = ($crx !== '' ? $crx.' · ' : '').'GTM needed for this domain';
            } elseif ($gtmDone) {
                $scriptDetail = $gtmId.' · Script needed';
            } else {
                $scriptDetail = 'Install script + GTM on this domain';
            }

            // Spec: Account Connected ≠ Protection Active. Protection only when policy modules are on.
            $protectionDetail = $protectionDone ? 'Active' : 'Action needed';

            return [
                [
                    'key' => 'domain',
                    'label' => 'Domain Verified',
                    'done' => true,
                    'detail' => $domain->hostname ?: 'Domain verified',
                ],
                [
                    'key' => 'ads',
                    'label' => 'Ads Account Connected',
                    'done' => $adsDone,
                    'detail' => $adsDone
                        ? ($adsDetail !== 'Link an ads account' ? $adsDetail : ($googleEmail ?: 'Connected'))
                        : ($googleConnected ? 'Select Customer ID' : 'Connect Google first'),
                ],
                [
                    'key' => 'tracking',
                    'label' => 'Script Active',
                    // Per-domain only — never inherit another domain's script/GTM.
                    'done' => $trackingDone,
                    'detail' => $scriptDetail,
                    'gtm_done' => $gtmDone,
                    'gtm_id' => $gtmDone ? $gtmId : '',
                ],
                [
                    'key' => 'click',
                    'label' => 'First Click Received',
                    'done' => $clickDone,
                    'detail' => $clickDetail,
                ],
                [
                    'key' => 'protection',
                    'label' => $protectionDone ? 'Protection Active' : 'Protection Setup Required',
                    'done' => $protectionDone,
                    'detail' => $protectionDetail,
                ],
            ];
        };

        $setupProgressByDomain = [];
        foreach ($manualDomains as $domain) {
            $setupProgressByDomain[(string) $domain->id] = $buildSetupProgressForDomain($domain);
        }

        $adsAccountDone = $googleConnected && (
            $accounts->isNotEmpty() || $manualDomains->contains(
                fn (Domain $d) => $d->google_ads_account_id !== null || (int) ($d->google_ads_mappings_count ?? 0) > 0
            )
        );
        $protectionActive = $manualDomains->contains(
            fn (Domain $d) => (bool) $d->bot_mitigation_connected || (bool) $d->paid_marketing_connected
        );
        $scriptKey = (string) ($firstDomain?->domain_key ?: '');
        $domainTotal = $manualDomains->count();
        $domainsWithScript = $manualDomains->filter(fn (Domain $d) => (bool) $d->tag_connected)->count();
        $domainsWithGtm = $manualDomains->filter(fn (Domain $d) => filled($d->gtm_container_id))->count();
        // All Domains view: Script Active only when EVERY domain has its own script — not inherited.
        $trackingDoneAll = $domainTotal > 0 && $domainsWithScript === $domainTotal;
        $trackingAggregateDetail = 'Install Clickronix script';
        if ($domainTotal > 0) {
            if ($trackingDoneAll && $domainsWithGtm === $domainTotal) {
                $trackingAggregateDetail = 'All domains · script + GTM';
            } elseif ($trackingDoneAll) {
                $trackingAggregateDetail = $domainsWithGtm.'/'.$domainTotal.' GTM — each domain needs its own GTM';
            } else {
                $trackingAggregateDetail = $domainsWithScript.'/'.$domainTotal.' domains tracked — GTM/script is per domain';
            }
        }

        // "All Domains" aggregate: Ads/Protection can be "any"; tracking must not inherit across domains.
        $setupProgress = [
            [
                'key' => 'domain',
                'label' => 'Domain Verified',
                'done' => $manualDomains->isNotEmpty(),
                'detail' => $firstDomain?->hostname ?: 'Add a domain',
            ],
            [
                'key' => 'ads',
                'label' => 'Ads Account Connected',
                'done' => $adsAccountDone,
                'detail' => (! $googleConnected)
                    ? 'Connect Google first'
                    : ($adsCustomerId !== '' ? $adsCustomerId : 'Select Customer ID'),
            ],
            [
                'key' => 'tracking',
                'label' => 'Script Active',
                'done' => $trackingDoneAll,
                'detail' => $trackingAggregateDetail,
            ],
            [
                'key' => 'click',
                'label' => 'First Click Received',
                'done' => $hasFirstClick,
                'detail' => $hasFirstClick
                    ? (filled($connectionHealth['last_event_at'])
                        ? \Illuminate\Support\Carbon::parse((string) $connectionHealth['last_event_at'])->diffForHumans()
                        : 'Today')
                    : 'Waiting for traffic',
            ],
            [
                'key' => 'protection',
                'label' => $protectionActive ? 'Protection Active' : 'Protection Setup Required',
                'done' => $protectionActive,
                'detail' => $protectionActive ? 'Active' : 'Action needed',
            ],
        ];

        $domainVisitCounts = $domainVisitTotals;

        $platformRows = collect();
        $apiHealthy = GoogleAdsApiHealth::status($primary, $accounts->count()) === 'ok';

        foreach ($mappings as $mapping) {
            $customerId = $mapping->account?->formattedCustomerId() ?: '';
            $googleTagId = $mapping->account?->resolvedGoogleTagId() ?: '';
            $domain = $mapping->domain;
            $scriptActive = (bool) ($domain?->tag_connected);
            $gtmId = (string) ($domain?->gtm_container_id ?: '');
            $scriptKey = (string) ($domain?->domain_key ?: '');
            $protectionDone = (bool) ($domain?->bot_mitigation_connected || $domain?->paid_marketing_connected);
            $protectionLabel = $protectionDone
                ? ($mapping->protection_type === 'pixel_guard' ? 'Pixel Guard' : 'Audience Exclusion')
                : 'Not configured';
            $lastSyncAt = $mapping->account?->connection?->last_sync_at;
            $lastEventAt = $domain?->last_seen_at
                ? \Illuminate\Support\Carbon::parse((string) $domain->last_seen_at)
                : null;
            $clicks = (int) ($domainVisitCounts[$mapping->domain_id] ?? 0);

            $platformRows->push([
                'key' => 'ads-' . $mapping->id,
                'kind' => 'google_ads',
                'platform' => 'Google Ads',
                'domain_id' => $mapping->domain_id,
                'account_id' => $mapping->google_ads_account_id,
                'account_primary' => $mapping->account?->displayLabel() ?: ($domain?->hostname ?: 'Google Ads'),
                'account_secondary' => $domain?->hostname ?: '—',
                'customer_id' => $customerId !== '' ? $customerId : '—',
                'google_tag_id' => $googleTagId !== '' ? $googleTagId : '—',
                'gtm_id' => $gtmId !== '' ? $gtmId : '—',
                'script_id' => $scriptKey !== '' ? 'CRX-'.strtoupper(substr($scriptKey, 0, 6)) : '—',
                'api_status' => $apiHealthy ? 'Connected' : ($googleConnected ? 'Pending' : 'Offline'),
                'api_ok' => $apiHealthy,
                'script_status' => $scriptActive ? 'Active' : 'Missing',
                'script_ok' => $scriptActive,
                'last_event' => $lastEventAt ? $lastEventAt->diffForHumans() : '—',
                'last_event_at' => optional($lastEventAt)->toIso8601String(),
                'protection' => $protectionLabel,
                'protection_ok' => $protectionDone,
                'protection_tone' => $protectionDone ? 'audience' : 'track',
                'entity_id' => $customerId !== '' ? $customerId : '—',
                'status' => 'Account Connected',
                'last_sync' => $lastSyncAt ? $lastSyncAt->diffForHumans() : '—',
                'last_sync_at' => optional($lastSyncAt)->toIso8601String(),
                'clicks' => $clicks,
                'clicks_label' => number_format($clicks),
                'clicks_caption' => 'Tracked visits',
                'action_label' => 'Manage',
                'action_url' => route('paid-marketing.detection-settings', ['domain_id' => $mapping->domain_id]),
                'edit_url' => route('integrations.google.redirect', [
                    'domain_id' => $mapping->domain_id,
                    'context' => 'paid_domain',
                ]),
                'edit_label' => 'Edit Connection',
                'delete_url' => route('integrations.destroy-mapping', $mapping),
                'menu_id' => 'mapping-' . $mapping->id,
                'search' => strtolower(trim(implode(' ', [
                    'google ads',
                    $mapping->account?->displayLabel(),
                    $domain?->hostname,
                    $customerId,
                    $googleTagId,
                    $gtmId,
                    $protectionLabel,
                ]))),
            ]);
        }

        // Domains linked via FK only (no DomainGoogleAdsMapping row yet).
        foreach ($manualDomains as $domain) {
            if (! $domain->google_ads_account_id) {
                continue;
            }
            $alreadyMapped = $mappings->contains(
                fn ($m) => (int) $m->domain_id === (int) $domain->id
                    && (int) $m->google_ads_account_id === (int) $domain->google_ads_account_id
            );
            if ($alreadyMapped) {
                continue;
            }
            $account = $domain->googleAdsAccount;
            if (! $account) {
                continue;
            }
            $customerId = $account->formattedCustomerId();
            $googleTagId = $account->resolvedGoogleTagId();
            $gtmId = (string) ($domain->gtm_container_id ?: '');
            $scriptKey = (string) ($domain->domain_key ?: '');
            $scriptActive = (bool) $domain->tag_connected;
            $protectionDone = (bool) $domain->bot_mitigation_connected || (bool) $domain->paid_marketing_connected;
            $clicks = (int) ($domainVisitCounts[$domain->id] ?? 0);
            $lastSyncAt = $account->connection?->last_sync_at;
            $lastEventAt = $domain->last_seen_at
                ? \Illuminate\Support\Carbon::parse((string) $domain->last_seen_at)
                : null;

            $platformRows->push([
                'key' => 'ads-fk-' . $domain->id,
                'kind' => 'google_ads',
                'platform' => 'Google Ads',
                'domain_id' => $domain->id,
                'account_id' => $account->id,
                'account_primary' => $account->displayLabel() ?: $domain->hostname,
                'account_secondary' => $domain->hostname,
                'customer_id' => $customerId !== '' ? $customerId : '—',
                'google_tag_id' => $googleTagId !== '' ? $googleTagId : '—',
                'gtm_id' => $gtmId !== '' ? $gtmId : '—',
                'script_id' => $scriptKey !== '' ? 'CRX-'.strtoupper(substr($scriptKey, 0, 6)) : '—',
                'api_status' => $apiHealthy ? 'Connected' : ($googleConnected ? 'Pending' : 'Offline'),
                'api_ok' => $apiHealthy,
                'script_status' => $scriptActive ? 'Active' : 'Missing',
                'script_ok' => $scriptActive,
                'last_event' => $lastEventAt ? $lastEventAt->diffForHumans() : '—',
                'last_event_at' => optional($lastEventAt)->toIso8601String(),
                'protection' => $protectionDone ? 'Audience Exclusion' : 'Not configured',
                'protection_ok' => $protectionDone,
                'protection_tone' => $protectionDone ? 'audience' : 'track',
                'entity_id' => $customerId !== '' ? $customerId : '—',
                'status' => 'Account Connected',
                'last_sync' => $lastSyncAt ? $lastSyncAt->diffForHumans() : '—',
                'last_sync_at' => optional($lastSyncAt)->toIso8601String(),
                'clicks' => $clicks,
                'clicks_label' => number_format($clicks),
                'clicks_caption' => 'Tracked visits',
                'action_label' => 'Manage',
                'action_url' => route('paid-marketing.detection-settings', ['domain_id' => $domain->id]),
                'edit_url' => route('integrations.google.redirect', [
                    'domain_id' => $domain->id,
                    'context' => 'paid_domain',
                ]),
                'edit_label' => 'Edit Connection',
                'delete_url' => null,
                'menu_id' => 'ads-fk-' . $domain->id,
                'search' => strtolower(trim(implode(' ', [
                    'google ads',
                    $account->displayLabel(),
                    $domain->hostname,
                    $customerId,
                    $googleTagId,
                    $gtmId,
                ]))),
            ]);
        }

        // Spec: one unique account/domain row — do not list separate GTM / Direct Ads rows.
        $platformRows = $platformRows->values();

        $buildTrackingInstallationForDomain = function (?Domain $domain) use ($tagReady): array {
            if (! $domain) {
                return [
                    'domain_id' => null,
                    'hostname' => '',
                    'google_tag' => ['id' => '—', 'status' => 'Not detected', 'ok' => false],
                    'gtm' => ['id' => '—', 'status' => 'Offline', 'ok' => false, 'unpublished' => false],
                    'script' => ['id' => '—', 'status' => 'Missing', 'ok' => false],
                    'ga4' => ['id' => '—', 'status' => 'Not detected', 'ok' => false],
                    'setup_url' => route('domains.index'),
                ];
            }

            $domain->loadMissing(['googleAdsAccount', 'googleAdsMappings.account']);
            $linkedAccounts = collect([$domain->googleAdsAccount])
                ->merge($domain->googleAdsMappings->pluck('account'))
                ->filter();

            $googleTagId = '';
            $ga4MeasurementId = '';
            foreach ($linkedAccounts as $account) {
                $tag = trim((string) ($account->resolvedGoogleTagId() ?: $account->google_tag_id ?: ''));
                if ($tag === '') {
                    continue;
                }
                if ($googleTagId === '' && preg_match('/^AW-/i', $tag)) {
                    $googleTagId = strtoupper($tag);
                }
                if ($ga4MeasurementId === '' && preg_match('/^G-[A-Z0-9]+$/i', $tag)) {
                    $ga4MeasurementId = strtoupper($tag);
                }
                if ($googleTagId === '' && ! preg_match('/^G-/i', $tag)) {
                    $googleTagId = $tag;
                }
            }

            $gtmContainerId = strtoupper(trim((string) ($domain->gtm_container_id ?: '')));
            if ($gtmContainerId !== '' && ! preg_match('/^GTM-[A-Z0-9]+$/', $gtmContainerId)) {
                $gtmContainerId = '';
            }
            $scriptKey = (string) ($domain->domain_key ?: '');
            $clickronixScriptId = $scriptKey !== '' ? 'CRX-'.strtoupper(substr($scriptKey, 0, 6)) : '';
            $scriptOk = (bool) $domain->tag_connected;
            $gtmOk = $gtmContainerId !== '';

            return [
                'domain_id' => $domain->id,
                'hostname' => (string) $domain->hostname,
                'google_tag' => [
                    'id' => $googleTagId !== '' ? $googleTagId : '—',
                    'status' => $googleTagId !== '' && $scriptOk ? 'Detected' : 'Not detected',
                    'ok' => $googleTagId !== '' && $scriptOk,
                ],
                'gtm' => [
                    'id' => $gtmOk ? $gtmContainerId : '—',
                    'status' => $gtmOk ? ($scriptOk ? 'Connected' : 'Offline') : 'Offline',
                    'ok' => $gtmOk && $scriptOk,
                    'unpublished' => $gtmOk && ! $scriptOk,
                ],
                'script' => [
                    'id' => $clickronixScriptId !== '' ? $clickronixScriptId : '—',
                    'status' => $scriptOk ? 'Active' : 'Missing',
                    'ok' => $scriptOk,
                ],
                'ga4' => [
                    'id' => $ga4MeasurementId !== '' ? $ga4MeasurementId : '—',
                    'status' => $ga4MeasurementId !== '' ? 'Detected' : 'Not detected',
                    'ok' => $ga4MeasurementId !== '',
                ],
                'setup_url' => route('domains.setup', $domain),
            ];
        };

        $trackingInstallationByDomain = [];
        foreach ($manualDomains as $domain) {
            $trackingInstallationByDomain[(string) $domain->id] = $buildTrackingInstallationForDomain($domain);
        }

        $domainsWithGtm = $manualDomains->filter(fn (Domain $d) => filled($d->gtm_container_id))->count();
        $domainTotal = max(1, $manualDomains->count());
        $allHaveGtm = $manualDomains->isNotEmpty() && $domainsWithGtm === $manualDomains->count();
        $allHaveScript = $manualDomains->isNotEmpty()
            && $manualDomains->every(fn (Domain $d) => (bool) $d->tag_connected);

        // All Domains card: never show one domain's GTM as globally connected.
        $trackingInstallation = [
            'domain_id' => null,
            'hostname' => 'All Domains',
            'google_tag' => [
                'id' => '—',
                'status' => $allHaveScript ? 'Per domain' : 'Not detected',
                'ok' => false,
            ],
            'gtm' => [
                'id' => '—',
                'status' => $manualDomains->isEmpty()
                    ? 'Offline'
                    : ($allHaveGtm ? 'All domains' : ($domainsWithGtm.'/'.$manualDomains->count().' domains')),
                'ok' => $allHaveGtm && $allHaveScript,
                'unpublished' => $domainsWithGtm > 0 && ! $allHaveGtm,
            ],
            'script' => [
                'id' => '—',
                'status' => $allHaveScript ? 'All domains' : ($manualDomains->filter(fn ($d) => $d->tag_connected)->count().'/'.$manualDomains->count().' domains'),
                'ok' => $allHaveScript,
            ],
            'ga4' => [
                'id' => '—',
                'status' => 'Per domain',
                'ok' => false,
            ],
            'setup_url' => $firstDomain
                ? route('domains.setup', $firstDomain)
                : route('domains.index'),
        ];

        $firstAccountTag = $firstAccount?->resolvedGoogleTagId() ?: '';
        $googleAdsSummary = [
            'connected' => $googleConnected,
            'account_connected' => $adsAccountDone,
            'protection_active' => $protectionActive,
            'email' => $googleEmail,
            'customer_id' => $adsCustomerId,
            'google_tag_id' => $firstAccountTag !== '' ? $firstAccountTag : '—',
            'label' => $firstAccount?->displayLabel() ?: 'Google Ads',
            'oauth_url' => route('integrations.google.redirect'),
            'sync_url' => $primary ? route('integrations.google.sync-accounts', $primary) : null,
            'protection_url' => route('paid-marketing.detection-settings'),
        ];

        $connectionHealth['audience_protection'] = $protectionActive ? 'Active' : 'Not configured';
        $connectionHealth['google_tag_ok'] = $allHaveScript;
        $connectionHealth['script_ok'] = $allHaveScript;
        $connectionHealth['api_ok'] = $apiHealthy;
        $connectionHealth['sync_ok'] = filled($connectionHealth['last_sync_at'])
            && in_array((string) ($connectionHealth['last_sync_status'] ?? ''), ['ok', 'success', 'synced'], true);

        // Only accounts explicitly linked to a domain (paid advertising pick) — not every Ads account under the Gmail OAuth.
        $trackingIds = collect();
        foreach ($manualDomains as $domain) {
            $linkedAccounts = collect([$domain->googleAdsAccount])
                ->merge($domain->googleAdsMappings->pluck('account'))
                ->filter(fn ($account) => $account instanceof GoogleAdsAccount)
                ->unique('id')
                ->values();

            foreach ($linkedAccounts as $account) {
                $trackingIds->push([
                    'id' => $account->id.'-'.$domain->id,
                    'account_id' => $account->id,
                    'domain_id' => $domain->id,
                    'domain' => $domain->hostname,
            'label' => $account->displayLabel(),
            'customer_id' => $account->formattedCustomerId(),
            'google_tag_id' => $account->resolvedGoogleTagId(),
                ]);
            }
        }
        $trackingIds = $trackingIds->values();

        $syncLogs = Schema::hasTable('integration_sync_logs')
            ? IntegrationSyncLog::query()
                ->where('user_id', $user->id)
                ->with(['domain:id,hostname', 'connection:id,google_email'])
                ->latest('id')
                ->limit(25)
                ->get()
            : collect();

        $enabledAdPlatforms = AdminIntegrationCatalog::enabledAdPlatforms();
        $enabledTenantIntegrations = AdminIntegrationCatalog::enabledTenantIntegrations();
        $tagSetupUrl = $firstDomain
            ? route('domains.setup', $firstDomain)
            : route('domains.index');

        $ipExclusionRows = collect();
        if (Schema::hasTable('google_ads_ip_exclusions') && $manualDomains->isNotEmpty()) {
            $ipQuery = DB::table('google_ads_ip_exclusions')
                ->whereIn('domain_id', $manualDomains->pluck('id'))
                ->orderByDesc('id')
                ->limit(40);
            if (Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
                // keep both active and inactive for History/Pending tabs
            }
            $ipExclusionRows = $ipQuery->get()->map(function ($row) {
                $status = strtolower((string) ($row->sync_status ?? 'pending'));
                $googleStatus = match (true) {
                    in_array($status, ['synced', 'applied', 'ok', 'success'], true) => 'Applied',
                    in_array($status, ['failed', 'error'], true) => 'Failed',
                    in_array($status, ['disabled', 'removed'], true) => 'Removed',
                    default => 'Queued',
                };
                $threat = (string) ($row->threat_group ?? '');
                $risk = match (true) {
                    str_contains($threat, 'vpn') => 72,
                    str_contains($threat, 'datacenter') || str_contains($threat, 'bot') => 90,
                    default => 80,
                };
                $expiresRaw = null;
                foreach (['expires_at', 'expire_at', 'expires_on'] as $col) {
                    if (isset($row->{$col}) && filled($row->{$col})) {
                        $expiresRaw = (string) $row->{$col};
                        break;
                    }
                }
                $requestId = '';
                foreach (['google_request_id', 'mutation_id', 'last_request_id'] as $col) {
                    if (isset($row->{$col}) && filled($row->{$col})) {
                        $requestId = (string) $row->{$col};
                        break;
                    }
                }

                return [
                    'id' => (int) $row->id,
                    'ip' => (string) $row->ip,
                    'risk' => $risk,
                    'reason' => $threat !== '' ? str_replace('_', ' ', $threat) : 'Clickronix exclusion',
                    'scope' => 'Eligible campaigns',
                    'expires' => $expiresRaw
                        ? \Illuminate\Support\Carbon::parse($expiresRaw)->diffForHumans()
                        : '—',
                    'google_status' => $googleStatus,
                    'selected' => false,
                    'tab' => $googleStatus === 'Applied' ? 'active' : (in_array($googleStatus, ['Queued'], true) ? 'pending' : 'history'),
                    'request_id' => $requestId,
                    'verified_at' => filled($row->synced_at ?? null)
                        ? \Illuminate\Support\Carbon::parse((string) $row->synced_at)->diffForHumans()
                        : null,
                ];
            })->values();
        }

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
            'domainConnections',
            'connectionHealth',
            'setupProgress',
            'setupProgressByDomain',
            'domainVisitCounts',
            'platformRows',
            'trackingIds',
            'syncLogs',
            'enabledAdPlatforms',
            'enabledTenantIntegrations',
            'tagSetupUrl',
            'googleAdsSummary',
            'trackingInstallation',
            'trackingInstallationByDomain',
            'ipExclusionRows',
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
        $domainId = (int) $request->query('domain_id', 0);
        $context = $request->string('context')->toString();
        if ($context === 'auth') {
            $oauthContext = 'auth';
        } elseif ($domainId > 0) {
            $oauthContext = 'paid_domain';
            $domain = Domain::query()
                ->where('user_id', $request->user()->id)
                ->manual()
                ->where('id', $domainId)
                ->first();
            if (! $domain) {
                return redirect()->route('domains.index')->with('status', 'Domain not found.');
            }
            $request->session()->put('google_oauth_domain_id', $domain->id);
        } else {
            $oauthContext = $context === 'integrations' ? 'integrations' : 'integrations';
        }

        if ($oauthContext !== 'auth' && ! $request->user()) {
            return redirect()->route('login')->with('status', 'Please sign in first.');
        }
        $request->session()->put('google_oauth_context', $oauthContext);

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
        $picture = trim((string) ($info['picture'] ?? ''));
        if ($email === '') {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'Google profile email not available.');
        }

        $paidDomainId = (int) $request->session()->pull('google_oauth_domain_id', 0);
        $request->session()->forget('google_oauth_context');
        $isAuthFlow = $oauthContext === 'auth';
        $isPaidDomainFlow = $oauthContext === 'paid_domain' && $paidDomainId > 0;
        $user = $request->user();

        if ($isAuthFlow && ! $user) {
            $defaultRole = Role::query()->where('slug', 'default-user')->first();
            $newUserData = [
                    'name' => $name !== '' ? $name : Str::before($email, '@'),
                    'password' => Hash::make(Str::random(40)),
                    'role_id' => $defaultRole?->id,
                    'email_verified_at' => now(),
            ];
            if (Schema::hasColumn('users', 'google_avatar_url')) {
                $newUserData['google_avatar_url'] = $picture !== '' ? $picture : null;
            }

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                $newUserData
            );

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('auth.two_factor_passed', true);
        }

        if (! $user) {
            return $this->redirectAfterGoogleOAuth($request, $oauthContext, 'No authenticated user for Google connection.');
        }

        // Prefer custom upload; otherwise keep Gmail/Google picture in sync.
        if (
            $picture !== ''
            && Schema::hasColumn('users', 'google_avatar_url')
            && (! Schema::hasColumn('users', 'avatar_path') || ! filled($user->avatar_path))
            && (string) $user->google_avatar_url !== $picture
        ) {
            $user->forceFill(['google_avatar_url' => $picture])->save();
        }

        $connection = $this->upsertGoogleConnection($user->id, $email, $sub, $token, $accessToken);

        $this->recordSyncLog($user->id, $connection->id, $isPaidDomainFlow ? $paidDomainId : null, 'oauth_connect', 'ok', 'Google account connected: '.$email);

        $syncResult = $this->syncGoogleAdsAccountsForConnection($connection, $user->id);

        if ($syncResult['error']) {
            $this->markConnectionHealth($connection, 'error', $syncResult['error']);
            $this->recordSyncLog($user->id, $connection->id, $isPaidDomainFlow ? $paidDomainId : null, 'sync_accounts', 'error', $syncResult['error']);
        } else {
            $syncMessage = $this->googleAdsSyncStatusMessage($syncResult, 'Google Ads accounts synced.');
            $this->markConnectionHealth($connection, 'ok', $syncMessage);
            $this->recordSyncLog($user->id, $connection->id, $isPaidDomainFlow ? $paidDomainId : null, 'sync_accounts', 'ok', $syncMessage, [
                'synced' => $syncResult['synced'] ?? 0,
                'skipped' => $syncResult['skipped'] ?? 0,
                'domains' => $syncResult['domains'] ?? 0,
            ]);
        }

        if ($isPaidDomainFlow) {
            $request->session()->put('pick_google_ads_accounts', [
                'domain_id' => $paidDomainId,
                'connection_id' => $connection->id,
            ]);

            $status = $syncResult['error']
                ?? ($syncResult['synced'] > 0
                    ? "Google connected. Found {$syncResult['synced']} ad account(s)—pick one for this domain."
                    : 'Google connected, but no accessible ad accounts for this Gmail. Try the account owner email or check MCC access in .env.');

            return redirect()
                ->route('domains.index', ['pick_paid_domain' => $paidDomainId])
                ->with('status', $status);
        }

        $status = $syncResult['error']
            ?? $this->googleAdsSyncStatusMessage($syncResult, 'Google account connected successfully.');

        return $this->redirectAfterGoogleOAuth($request, $oauthContext, $status);
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

        $result = $this->syncGoogleAdsAccountsForConnection($connection, $request->user()->id);

        if ($result['error']) {
            $this->markConnectionHealth($connection, 'error', $result['error']);
            $this->recordSyncLog($request->user()->id, $connection->id, null, 'sync_accounts', 'error', $result['error']);

            return back()->with('status', $result['error']);
        }

        $message = $this->googleAdsSyncStatusMessage($result, 'Google Ads accounts synced.');
        $this->markConnectionHealth($connection, 'ok', $message);
        $this->recordSyncLog($request->user()->id, $connection->id, null, 'sync_accounts', 'ok', $message, [
            'synced' => $result['synced'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
            'domains' => $result['domains'] ?? 0,
        ]);

        return back()->with('status', $message);
    }

    /**
     * @return array{synced: int, skipped: int, domains: int, error: ?string}
     */
    private function syncGoogleAdsAccountsForConnection(GoogleConnection $connection, int $userId): array
    {
        $empty = ['synced' => 0, 'skipped' => 0, 'domains' => 0, 'error' => null];

        $developerToken = (string) config('services.google_ads.developer_token');
        if ($developerToken === '') {
            return array_merge($empty, ['error' => 'Missing GOOGLE_ADS_DEVELOPER_TOKEN.']);
        }

        $accessToken = $this->resolveAccessToken($connection);
        if (! $accessToken) {
            return array_merge($empty, ['error' => 'Could not resolve Google access token. Reconnect Google.']);
        }

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'developer-token' => $developerToken,
            'Accept' => 'application/json',
        ];

        $loginCustomerId = preg_replace('/\D+/', '', (string) config('services.google_ads.login_customer_id'));

        $versions = $this->googleAdsApiVersions();
        $usedVersion = null;
        $listRes = null;
        $attemptedVersions = [];

        foreach ($versions as $version) {
            $usedVersion = $version;
            $attemptedVersions[] = $version;
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

        if ($listRes && $listRes->successful()) {
            $resourceNames = (array) ($listRes->json('resourceNames') ?? []);
            Log::info('Google Ads API ← listAccessibleCustomers success', [
                'connection_id' => $connection->id,
                'status' => $listRes->status(),
                'account_count' => count($resourceNames),
                'body_preview' => Str::limit((string) $listRes->body(), 2000),
            ]);
        }

        if (! $listRes || ! $listRes->successful()) {
            $reason = $this->extractApiError($listRes);
            $all404 = $listRes && $listRes->status() === 404;
            Log::warning('Google Ads listAccessibleCustomers failed', [
                'user_id' => $userId,
                'connection_id' => $connection->id,
                'status' => $listRes?->status(),
                'version_tried' => $usedVersion,
                'versions_configured' => $versions,
                'versions_attempted' => $attemptedVersions,
                'body' => $listRes ? Str::limit($listRes->body(), 2000) : null,
            ]);

            if ($all404) {
                return array_merge($empty, ['error' => 'Google Ads API version not found. Set GOOGLE_ADS_API_VERSIONS=v24,v23,v22,v21,v20 in .env, run php artisan config:clear, then sync again.']);
            }

            return array_merge($empty, ['error' => 'Google Ads account listing failed: ' . str($reason)->limit(220)]);
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
                'SELECT customer.id, customer.descriptive_name, customer.manager, customer.time_zone FROM customer LIMIT 1',
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
            $display = GoogleAdsAccount::formatCustomerId($customerId);
            $googleTagId = $customerId !== '' ? 'AW-'.$customerId : '';
            $name = trim((string) ($customer['descriptiveName'] ?? $customer['descriptive_name'] ?? ''));
            if ($name === '') {
                $name = 'Google Ads '.($display !== '' ? $display : $customerId);
            }
            $isManager = (bool) ($customer['manager'] ?? false);
            $timeZone = trim((string) ($customer['timeZone'] ?? $customer['time_zone'] ?? ''));
            $timeZone = UserTimezone::isValid($timeZone) ? $timeZone : null;

            $accountAttributes = [
                'display_customer_id' => $display,
                'account_name' => $name,
                'is_manager' => $isManager,
                'manager_customer_id' => null,
                'google_tag_id' => $googleTagId,
                'is_active' => true,
            ];
            if ($timeZone !== null) {
                $accountAttributes['time_zone'] = $timeZone;
            }

            $adsAccount = GoogleAdsAccount::updateOrCreate(
                [
                    'google_connection_id' => $connection->id,
                    'customer_id' => $customerId,
                ],
                $accountAttributes
            );
            $synced++;

            if (! $adsAccount->time_zone) {
                app(GoogleAdsAccountTimezoneService::class)->refreshForAccount($adsAccount, (string) $usedVersion, $baseDetailHeaders);
            }

            if ($isManager) {
                $childResult = $this->syncManagerChildAccounts(
                    $userId,
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
                $userId,
                $adsAccount,
                $customerId,
                (string) $usedVersion,
                $accountHeaders
            );
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'domains' => $domainsSynced,
            'error' => null,
        ];
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

        $account = GoogleAdsAccount::query()
            ->where('google_connection_id', $connection->id)
            ->where('customer_id', $data['customer_id'])
            ->first();

        if ($account && ! $account->time_zone && ! $account->is_manager) {
            app(GoogleAdsAccountTimezoneService::class)->refreshForAccount($account);
        }

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
            $childDisplay = GoogleAdsAccount::formatCustomerId($clientId);
            if ($childName === '') {
                $childName = 'Google Ads '.($childDisplay !== '' ? $childDisplay : $clientId);
            }

            $adsAccount = GoogleAdsAccount::updateOrCreate(
                [
                    'google_connection_id' => $connection->id,
                    'customer_id' => $clientId,
                ],
                [
                    'display_customer_id' => $childDisplay,
                    'account_name' => $childName,
                    'is_manager' => false,
                    'manager_customer_id' => $managerCustomerId,
                    'google_tag_id' => $clientId !== '' ? 'AW-'.$clientId : null,
                    'is_active' => true,
                ]
            );
            $synced++;

            if (! $adsAccount->time_zone) {
                app(GoogleAdsAccountTimezoneService::class)->refreshForAccount($adsAccount, $apiVersion, $managerHeaders);
            }

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
        $response = $this->postGoogleAdsSearchStream(
            $apiVersion,
            $customerId,
            $query,
            $this->googleAdsDetailHeaders($baseHeaders, $loginCustomerId),
            'integrations',
            $loginCustomerId
        );

        if ($response->successful() || $loginCustomerId === null || $loginCustomerId === '') {
            return $response;
        }

        return $this->postGoogleAdsSearchStream(
            $apiVersion,
            $customerId,
            $query,
            $this->googleAdsDetailHeaders($baseHeaders, null),
            'integrations_retry_no_mcc',
            null
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function postGoogleAdsSearchStream(
        string $apiVersion,
        string $customerId,
        string $query,
        array $headers,
        string $context,
        ?string $loginCustomerId
    ) {
        $safeHeaders = $headers;
        unset($safeHeaders['Authorization']);

        Log::info('Google Ads API → request', [
            'context' => $context,
            'customer_id' => $customerId,
            'api_version' => $apiVersion,
            'login_customer_id' => $loginCustomerId,
            'query' => Str::limit($query, 600),
        ]);

        $response = Http::timeout(20)
            ->withHeaders($headers)
            ->post($this->googleAdsUrl($apiVersion, "customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ]);

        $body = (string) $response->body();

        if ($response->successful()) {
            Log::info('Google Ads API ← success response', [
                'context' => $context,
                'customer_id' => $customerId,
                'status' => $response->status(),
                'body_preview' => Str::limit($body, 2500),
            ]);
        } else {
            Log::warning('Google Ads API ← error response', [
                'context' => $context,
                'customer_id' => $customerId,
                'status' => $response->status(),
                'error_summary' => $this->extractApiError($response),
                'body' => Str::limit($body, 4000),
            ]);
        }

        return $response;
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

    public function campaignMetricsForHost(Request $request): JsonResponse
    {
        $hostname = strtolower(trim((string) $request->query('hostname', '')));
        $accountId = (int) $request->query('google_ads_account_id', 0);
        if ($hostname === '' || $accountId <= 0) {
            return response()->json(['campaigns' => [], 'hostname' => $hostname]);
        }

        $account = GoogleAdsAccount::query()
            ->where('id', $accountId)
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('connection')
            ->first();

        if (! $account || ! $account->connection) {
            return response()->json(['campaigns' => [], 'error' => 'Account not found'], 404);
        }

        $api = app(GoogleAdsConnectionService::class);
        $headers = $api->apiHeaders($account->connection);
        if (! $headers) {
            return response()->json(['campaigns' => [], 'error' => 'Reconnect Google Ads'], 401);
        }

        $loginId = $account->manager_customer_id ?: $api->loginCustomerId();
        if ($loginId !== '') {
            $headers['login-customer-id'] = $loginId;
        }

        [$from, $to] = $this->adsDateRange($request);
        $version = $api->apiVersions()[0] ?? 'v24';
        $metrics = app(GoogleAdsMetricsService::class)->campaignMetrics(
            $account,
            $version,
            $headers,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $hostname
        );

        return response()->json([
            'hostname' => $hostname,
            'account' => $account->displayLabel(),
            'customer_id' => $account->display_customer_id ?: $account->customer_id,
            'campaigns' => $metrics,
        ]);
    }

    /**
     * Real campaign list for Apply audience exclusion modal (names + channel type).
     */
    public function audienceCampaigns(Request $request): JsonResponse
    {
        $user = $request->user();
        $domainId = (int) $request->query('domain_id', 0);
        $accountId = (int) $request->query('google_ads_account_id', 0);

        $domain = null;
        if ($domainId > 0) {
            $domain = Domain::query()
                ->where('user_id', $user->id)
                ->where('id', $domainId)
                ->with(['googleAdsAccount.connection', 'googleAdsMappings.account.connection'])
                ->first();
        }

        $account = null;
        if ($accountId > 0) {
            $account = GoogleAdsAccount::query()
                ->where('id', $accountId)
                ->whereHas('connection', fn ($q) => $q->where('user_id', $user->id))
                ->with('connection')
                ->first();
        }

        if (! $account && $domain) {
            $account = $domain->googleAdsAccount;
            if (! $account) {
                $account = $domain->googleAdsMappings->pluck('account')->filter()->first();
            }
        }

        if (! $account && $domainId <= 0 && $accountId <= 0) {
            // Prefer first linked domain account when filters are "All".
            $domain = Domain::query()
                ->where('user_id', $user->id)
                ->manual()
                ->whereNotNull('google_ads_account_id')
                ->with('googleAdsAccount.connection')
                ->orderBy('hostname')
                ->first();
            $account = $domain?->googleAdsAccount;
        }

        if (! $account || ! $account->connection || (bool) $account->is_manager) {
            return response()->json([
                'campaigns' => [],
                'source' => 'none',
                'error' => 'Link a Google Ads customer account to a domain first.',
            ]);
        }

        $api = app(GoogleAdsConnectionService::class);
        $headers = $api->apiHeaders($account->connection);
        $campaigns = [];
        $source = 'stored';

        if ($headers) {
            $loginId = preg_replace('/\D+/', '', (string) ($account->manager_customer_id ?: $api->loginCustomerId()));
            $customerId = preg_replace('/\D+/', '', (string) $account->customer_id);
            if ($loginId !== '' && $loginId !== $customerId) {
                $headers['login-customer-id'] = $loginId;
            }

            $version = $api->apiVersions()[0] ?? 'v24';
            $query = "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type FROM campaign WHERE campaign.status IN ('ENABLED', 'PAUSED') ORDER BY campaign.name";
            $res = Http::timeout(30)
                ->withHeaders($headers)
                ->post('https://googleads.googleapis.com/'.$version.'/customers/'.$customerId.'/googleAds:searchStream', [
                    'query' => $query,
                ]);

            if ($res->successful()) {
                $source = 'live';
                foreach ($this->parseAudienceCampaignRows($res->json()) as $row) {
                    $campaigns[] = $row;
                }
            }
        }

        if ($campaigns === [] && Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $metricsQuery = DB::table('google_ads_campaign_daily_metrics')
                ->where('google_ads_account_id', $account->id)
                ->when($domain, fn ($q) => $q->where('domain_id', $domain->id))
                ->whereNotNull('campaign_id')
                ->where('campaign_id', '!=', '')
                ->select('campaign_id', DB::raw('MAX(campaign_name) as campaign_name'), DB::raw('MAX(status) as status'))
                ->groupBy('campaign_id')
                ->orderBy('campaign_name')
                ->limit(100);

            foreach ($metricsQuery->get() as $row) {
                $campaigns[] = [
                    'id' => (string) $row->campaign_id,
                    'name' => (string) ($row->campaign_name ?: ('Campaign '.$row->campaign_id)),
                    'channel' => 'UNKNOWN',
                    'status' => (string) ($row->status ?: ''),
                ];
            }
            $source = $campaigns !== [] ? 'stored' : $source;
        }

        return response()->json([
            'campaigns' => $campaigns,
            'source' => $source,
            'account' => $account->displayLabel(),
            'customer_id' => $account->formattedCustomerId() ?: $account->customer_id,
            'domain_id' => $domain?->id,
            'hostname' => $domain?->hostname,
        ]);
    }

    /**
     * Create Ads-side audience (user list) for exclusion. Real Google Ads API call — not demo.
     * Requires GA4/GTM detected on the domain website (or linked measurement ID / GTM in portal).
     */
    public function createAudience(Request $request, \App\Services\GoogleAdsAudienceAssociationService $associations, \App\Services\Ga4SitePresenceService $ga4Presence): JsonResponse
    {
        $data = $request->validate([
            'domain_id' => ['required', 'integer'],
            'audience_name' => ['required', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:40'],
            'event_name' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'in:ga4,website'],
            'google_ads_account_id' => ['nullable', 'integer'],
            'force_reuse' => ['sometimes', 'boolean'],
            'skip_ga4_check' => ['sometimes', 'boolean'],
        ]);

        $domain = Domain::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $data['domain_id'])
            ->firstOrFail();

        if (! empty($data['google_ads_account_id'])) {
            $account = GoogleAdsAccount::query()
                ->where('id', $data['google_ads_account_id'])
                ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
                ->first();
            if ($account && (int) $domain->google_ads_account_id !== (int) $account->id) {
                $domain->google_ads_account_id = $account->id;
                $domain->save();
            }
        }

        $method = (string) ($data['method'] ?? 'ga4');
        $detection = $ga4Presence->detect($domain);
        if ($method === 'ga4' && ! $request->boolean('skip_ga4_check')) {
            if (! ($detection['has_ga4'] ?? false) || ! ($detection['present'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'message' => $detection['message'] ?? 'GA4 (G-…) not detected on the website.',
                    'ga4_detection' => $detection,
                    'user_list_id' => null,
                    'user_list_name' => null,
                    'created' => false,
                ], 422);
            }
            // GA4 route needs GTM as delivery container (GA4 alone is not enough).
            if (empty($detection['has_gtm'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'GTM is required for the GA4 audience route. Connect a GTM container that fires GA4, or use the Google Ads website audience route instead.',
                    'ga4_detection' => $detection,
                    'user_list_id' => null,
                    'user_list_name' => null,
                    'created' => false,
                ], 422);
            }
        }

        $defaultName = $method === 'website'
            ? 'Clickronix | Invalid Traffic | Google Ads'
            : 'Clickronix | Invalid Traffic | GA4';
        $audienceName = trim((string) $data['audience_name']) !== ''
            ? (string) $data['audience_name']
            : $defaultName;

        // Always allow many lists: if name already exists, append a unique suffix unless force_reuse.
        $forceReuse = $request->boolean('force_reuse');
        $result = $associations->createAudienceList(
            $domain,
            $audienceName,
            (string) ($data['duration'] ?? '30 days'),
            (string) ($data['event_name'] ?: \App\Services\AudienceSignalService::DEFAULT_EVENT),
            $method,
            forceNew: ! $forceReuse,
        );

        return response()->json($result + ['ga4_detection' => $detection], $result['ok'] ? 200 : 422);
    }

    /**
     * Live GA4/GTM presence check for a domain (Create audience gate).
     */
    public function ga4SiteStatus(Request $request, \App\Services\Ga4SitePresenceService $ga4Presence): JsonResponse
    {
        $data = $request->validate([
            'domain_id' => ['required', 'integer'],
        ]);

        $domain = Domain::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $data['domain_id'])
            ->firstOrFail();

        $detection = $ga4Presence->detect($domain);

        return response()->json([
            'ok' => true,
            'domain_id' => $domain->id,
            'hostname' => $domain->hostname,
            'detection' => $detection,
        ]);
    }

    /**
     * Apply step: attach Invalid Traffic audience (user list) as negative exclusion on selected campaigns.
     * Does not push IPs — membership is Client ID + clickronix_invalid_traffic from the tag.
     */
    public function applyAudienceExclusion(Request $request, \App\Services\GoogleAdsAudienceAssociationService $associations, \App\Services\Ga4SitePresenceService $ga4Presence): JsonResponse
    {
        $data = $request->validate([
            'domain_id' => ['required', 'integer'],
            'campaign_ids' => ['nullable', 'array', 'max:200'],
            'campaign_ids.*' => ['string', 'max:40'],
            'ad_group_ids' => ['nullable', 'array', 'max:200'],
            'ad_group_ids.*' => ['string', 'max:40'],
            'scope' => ['nullable', 'string', 'in:campaign,adgroup'],
            'audience_name' => ['nullable', 'string', 'max:255'],
            'user_list_id' => ['nullable', 'string', 'max:40'],
            'event_name' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'in:ga4,website'],
            'route' => ['nullable', 'string', 'in:ga4,website'],
        ]);

        $domain = Domain::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $data['domain_id'])
            ->firstOrFail();

        $detection = $ga4Presence->detect($domain);
        $route = (string) ($data['route'] ?? $data['method'] ?? 'ga4');
        // Website audience route does not require GA4. GA4 route needs a real G- measurement ID.
        if ($route === 'ga4' && (! ($detection['present'] ?? false) || empty($detection['has_ga4']))) {
            return response()->json([
                'ok' => false,
                'message' => 'GA4 not detected on the website — install GA4 (G-…) first, then Apply exclusion. '.$detection['message'],
                'attached' => [],
                'failed' => [],
                'ga4_detection' => $detection,
            ], 422);
        }

        $defaultName = $route === 'website'
            ? 'Clickronix | Invalid Traffic | Google Ads'
            : 'Clickronix | Invalid Traffic | GA4';

        $result = $associations->applyToCampaigns(
            $domain,
            $data['campaign_ids'] ?? [],
            (string) ($data['audience_name'] ?: $defaultName),
            $data['user_list_id'] ?? null,
            (string) ($data['event_name'] ?: \App\Services\AudienceSignalService::DEFAULT_EVENT),
            $data['ad_group_ids'] ?? [],
            (string) ($data['scope'] ?? 'campaign'),
            $route,
        );

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'attached' => $result['attached'],
            'failed' => $result['failed'],
            'stored' => $result['stored'],
            'user_list_id' => $result['user_list_id'] ?? null,
            'user_list_name' => $result['user_list_name'] ?? null,
            'ga4_detection' => $detection,
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * @return list<array{id: string, name: string, channel: string, status: string}>
     */
    private function parseAudienceCampaignRows(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $out = [];
        foreach ($payload as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            foreach (($chunk['results'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $campaign = $row['campaign'] ?? [];
                if (! is_array($campaign)) {
                    continue;
                }
                $id = preg_replace('/\D+/', '', (string) ($campaign['id'] ?? ''));
                $name = trim((string) ($campaign['name'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $out[] = [
                    'id' => $id,
                    'name' => $name !== '' ? $name : ('Campaign '.$id),
                    'channel' => strtoupper((string) ($campaign['advertisingChannelType'] ?? $campaign['advertising_channel_type'] ?? 'UNKNOWN')),
                    'status' => strtoupper((string) ($campaign['status'] ?? '')),
                ];
            }
        }

        return $out;
    }

    public function pickAccountsJson(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id && $domain->isManual(), 403);

        $connectionId = (int) $request->query('connection_id', 0);
        $connection = GoogleConnection::query()
            ->where('user_id', $request->user()->id)
            ->when($connectionId > 0, fn ($q) => $q->where('id', $connectionId))
            ->latest('id')
            ->first();

        if (! $connection) {
            return response()->json(['accounts' => [], 'message' => 'Connect Google first.']);
        }

        $syncError = null;
        if ($this->pickableAdsAccounts($connection)->isEmpty()) {
            $syncResult = $this->syncGoogleAdsAccountsForConnection($connection, $request->user()->id);
            $syncError = $syncResult['error'];
        }

        $accounts = $this->pickableAdsAccounts($connection)
            ->map(fn ($a) => [
                'id' => $a->id,
                'account_name' => $a->displayLabel(),
                'customer_id' => $a->display_customer_id ?: $a->customer_id,
                'hostnames' => $a->advertisedHosts()->pluck('hostname')->values(),
                'matches_domain' => $a->advertisedHosts()->where('hostname', $domain->hostname)->exists(),
            ]);

        return response()->json([
            'domain' => ['id' => $domain->id, 'hostname' => $domain->hostname],
            'connection_id' => $connection->id,
            'google_email' => $connection->google_email,
            'accounts' => $accounts,
            'sync_error' => $syncError,
            'message' => $accounts->isEmpty()
                ? ($syncError ?: 'No ad accounts found for this Google login.')
                : null,
        ]);
    }

    private function pickableAdsAccounts(GoogleConnection $connection)
    {
        return GoogleAdsAccount::query()
            ->where('google_connection_id', $connection->id)
            ->synced()
            ->where('is_manager', false)
            ->orderBy('account_name')
            ->get();
    }

    public function linkDomainPaidAccount(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id && $domain->isManual(), 403);

        $data = $request->validate([
            'google_ads_account_id' => ['required', 'integer'],
            'protection_type' => ['nullable', 'in:ip_blocking,pixel_guard'],
        ]);

        $account = GoogleAdsAccount::query()
            ->where('id', $data['google_ads_account_id'])
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        if (! $account->time_zone && ! $account->is_manager) {
            app(GoogleAdsAccountTimezoneService::class)->refreshForAccount($account);
            $account->refresh();
        }

        $domain->google_ads_account_id = $account->id;
        $domain->paid_marketing_connected = true;
        $domain->ads_synced_at = now();
        $domain->save();

        DomainGoogleAdsMapping::updateOrCreate(
            [
                'domain_id' => $domain->id,
                'google_ads_account_id' => $account->id,
            ],
            [
                'protection_type' => $data['protection_type'] ?? 'ip_blocking',
                'audience_exclusion_enabled' => true,
                'settings' => ['linked_at' => now()->toISOString(), 'via' => 'domain_paid_setup'],
            ]
        );

        $metricsSaved = 0;
        $metricsMessage = null;
        try {
            Log::info('Google Ads link domain → starting metrics sync', [
                'domain_id' => $domain->id,
                'hostname' => $domain->hostname,
                'google_ads_account_id' => $account->id,
                'customer_id' => $account->customer_id,
            ]);

            $sync = app(\App\Services\GoogleAdsDomainMetricsSync::class);
            $syncResult = $sync->syncDomain($domain->fresh());
            $metricsSaved = (int) ($syncResult['saved'] ?? 0);
            $metricsMessage = $syncResult['message'] ?? $sync->lastMessage;

            Log::info('Google Ads link domain ← metrics sync finished', [
                'domain_id' => $domain->id,
                'metrics_rows_saved' => $metricsSaved,
                'metrics_message' => $metricsMessage,
            ]);
        } catch (\Throwable $e) {
            $metricsMessage = $e->getMessage();
            Log::warning('Google Ads metrics sync on domain link failed', [
                'domain_id' => $domain->id,
                'message' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 1500),
            ]);
        }

        $request->session()->forget('pick_google_ads_accounts');

        $this->recordSyncLog(
            $request->user()->id,
            $account->google_connection_id,
            $domain->id,
            'link_domain',
            $metricsMessage && $metricsSaved === 0 ? 'error' : 'ok',
            $metricsMessage ?: ('Linked '.$account->displayLabel().' to '.$domain->hostname),
            [
                'account_id' => $account->id,
                'metrics_rows_saved' => $metricsSaved,
            ]
        );

        if ($account->connection) {
            $hardFail = $metricsSaved === 0
                && filled($metricsMessage)
                && GoogleAdsApiHealth::isHardFailure((string) $metricsMessage);
            $this->markConnectionHealth(
                $account->connection,
                $hardFail ? 'error' : 'ok',
                $metricsMessage ?: ('Linked '.$account->displayLabel())
            );
        }

        return response()->json([
            'ok' => true,
            'domain_id' => $domain->id,
            'account_name' => $account->displayLabel(),
            'metrics_rows_saved' => $metricsSaved,
            'metrics_message' => $metricsMessage,
            'metrics_table' => 'google_ads_campaign_daily_metrics',
        ]);
    }

    private function adsDateRange(Request $request): array
    {
        return UserTimezone::dateRangeFromRequest($request, $request->user());
    }

    private function upsertGoogleConnection(int $userId, string $email, string $sub, array $token, string $accessToken): GoogleConnection
    {
        return GoogleConnection::updateOrCreate(
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

    /**
     * @param array{synced: int, skipped: int, domains: int, error: ?string} $result
     */
    private function googleAdsSyncStatusMessage(array $result, string $fallback): string
    {
        $synced = (int) ($result['synced'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $domainsSynced = (int) ($result['domains'] ?? 0);

        $domainNote = $domainsSynced > 0
            ? " Discovered {$domainsSynced} advertised hostname(s) from Google Ads (link manual domains on Site Management)."
            : '';

        if ($synced === 0 && $skipped === 0) {
            return $fallback;
        }

        if ($synced === 0 && $skipped > 0) {
            return "No accessible Google Ads accounts were synced. {$skipped} account(s) were skipped (disabled, deactivated, or no permission). Check GOOGLE_ADS_LOGIN_CUSTOMER_ID if you use an MCC.{$domainNote}";
        }

        if ($skipped > 0) {
            return "Synced {$synced} Google Ads account(s) with names and timezones. {$skipped} inaccessible account(s) were skipped.{$domainNote}";
        }

        return "Synced {$synced} Google Ads account(s) with timezones.{$domainNote}";
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
        $connection = GoogleConnection::query()->where('user_id', $userId)->latest('id')->first();
        $googleConnected = $connection !== null;
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

        $domains = Domain::query()->where('user_id', $userId)->get(['id', 'tag_connected', 'last_seen_at']);
        $trackingActive = $domains->contains(fn (Domain $d) => (bool) $d->tag_connected);
        $lastSeen = $domains->max('last_seen_at');

        return response()->json([
            'google' => [
                'connected' => $googleConnected,
                'email' => $connection?->google_email,
                'accounts' => $googleAccountsCount,
                'oauth_configured' => $oauthConfigured,
                'developer_token_configured' => $devTokenConfigured,
                'health_status' => GoogleAdsApiHealth::status($connection, $googleAccountsCount),
                'last_sync_at' => optional($connection?->last_sync_at)->toIso8601String(),
                'last_sync_status' => $connection?->last_sync_status,
                'last_sync_message' => $connection?->last_sync_message,
            ],
            'tracking' => [
                'active' => $trackingActive,
                'last_event_at' => $lastSeen
                    ? \Carbon\Carbon::parse((string) $lastSeen)->toIso8601String()
                    : null,
            ],
            'direct' => [
                'connected' => $directCount > 0,
                'count' => $directCount,
            ],
            'domain_mappings' => $mappingsCount,
        ]);
    }

    public function logsJson(Request $request): JsonResponse
    {
        if (! Schema::hasTable('integration_sync_logs')) {
            return response()->json(['logs' => []]);
        }

        $logs = IntegrationSyncLog::query()
            ->where('user_id', $request->user()->id)
            ->with(['domain:id,hostname'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (IntegrationSyncLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'status' => $log->status,
                'message' => $log->message,
                'domain' => $log->domain?->hostname,
                'created_at' => optional($log->created_at)->toIso8601String(),
                'meta' => $log->meta,
            ]);

        return response()->json(['logs' => $logs]);
    }

    public function reconnectAllDomains(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $isSuper = (bool) ($user->is_super_admin ?? false);

        $connections = GoogleConnection::query()
            ->when(! $isSuper, fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->get();

        $refreshed = 0;
        $failed = [];

        foreach ($connections as $connection) {
            $token = app(GoogleAdsConnectionService::class)->resolveAccessToken($connection, true);
            if ($token) {
                $this->markConnectionHealth($connection, 'ok', 'Token refreshed via reconnect-all');
                $refreshed++;
                continue;
            }

            $error = app(GoogleAdsConnectionService::class)->lastRefreshError ?: 'Token refresh failed';
            $this->markConnectionHealth($connection, 'error', $error);
            $failed[] = ($connection->google_email ?: ('#'.$connection->id)).': '.$error;
        }

        $message = "Reconnect all: {$refreshed} refreshed";
        if ($failed !== []) {
            $message .= '; '.count($failed).' need OAuth reconnect ('.Str::limit(implode(' | ', $failed), 280).')';
        }

        return back()->with('status', $message);
    }

    public function testConnection(Request $request, GoogleConnection $connection): JsonResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        $token = $this->resolveAccessToken($connection);
        if (! $token) {
            $this->markConnectionHealth($connection, 'error', 'Token refresh failed. Reconnect Google.');
            $this->recordSyncLog($request->user()->id, $connection->id, null, 'health_check', 'error', 'Token refresh failed. Reconnect Google.');

            return response()->json(['ok' => false, 'message' => 'Token refresh failed. Reconnect Google.'], 422);
        }

        $this->markConnectionHealth($connection, 'ok', 'Connection healthy');
        $this->recordSyncLog($request->user()->id, $connection->id, null, 'health_check', 'ok', 'Connection healthy');

        return response()->json([
            'ok' => true,
            'message' => 'Connection healthy',
            'email' => $connection->google_email,
            'last_sync_at' => optional($connection->fresh()->last_sync_at)->toIso8601String(),
            'health_status' => 'ok',
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordSyncLog(
        int $userId,
        ?int $connectionId,
        ?int $domainId,
        string $action,
        string $status,
        ?string $message = null,
        array $meta = [],
    ): void {
        if (! Schema::hasTable('integration_sync_logs')) {
            return;
        }

        IntegrationSyncLog::query()->create([
            'user_id' => $userId,
            'google_connection_id' => $connectionId,
            'domain_id' => $domainId,
            'action' => $action,
            'status' => $status,
            'message' => $message ? Str::limit($message, 500) : null,
            'meta' => $meta !== [] ? $meta : null,
            'created_at' => now(),
        ]);
    }

    private function markConnectionHealth(GoogleConnection $connection, string $status, ?string $message = null): void
    {
        $payload = [
            'last_health_check_at' => now(),
            'health_status' => $status,
            'last_sync_at' => now(),
            'last_sync_status' => $status,
            'last_sync_message' => $message ? Str::limit($message, 500) : null,
        ];

        // Only set columns that exist (pre-migration safety).
        $data = [];
        foreach ($payload as $column => $value) {
            if (Schema::hasColumn('google_connections', $column)) {
                $data[$column] = $value;
            }
        }

        if ($data !== []) {
            $connection->forceFill($data)->save();
        }
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

    public function audienceExclusionGet(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $mappings = DomainGoogleAdsMapping::query()
            ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
            ->with(['domain:id,hostname,domain_key,tag_connected', 'account:id,customer_id,display_customer_id,account_name,google_tag_id'])
            ->orderByDesc('id')
            ->get();

        $tags = Domain::query()
            ->where('user_id', $userId)
            ->orderBy('hostname')
            ->get(['id', 'hostname', 'domain_key', 'tag_connected'])
            ->map(fn (Domain $d) => [
                'id' => $d->id,
                'hostname' => $d->hostname,
                'domain_key' => $d->domain_key,
                'tag_connected' => (bool) $d->tag_connected,
                'label' => $d->hostname.($d->tag_connected ? ' (Installed)' : ' (Not detected)'),
            ])
            ->values();

        $primary = $mappings->first();
        $settings = (array) ($primary?->settings ?? []);
        $audiences = AudienceExclusionAudiences::normalize((array) ($settings['conversion_audiences'] ?? []));

        return response()->json([
            'ok' => true,
            'mapping_id' => $primary?->id,
            'enabled' => (bool) ($primary?->audience_exclusion_enabled ?? true),
            'audiences' => $audiences !== [] ? $audiences : [[
                'conversion_id' => '',
                'conversion_label' => '',
                'tag' => '',
                'domain_id' => null,
            ]],
            'tags' => $tags,
            'mappings' => $mappings->map(fn (DomainGoogleAdsMapping $m) => [
                'id' => $m->id,
                'domain_id' => $m->domain_id,
                'hostname' => $m->domain?->hostname,
                'account' => $m->account?->displayLabel() ?? $m->account?->customer_id,
                'enabled' => (bool) $m->audience_exclusion_enabled,
            ])->values(),
            'guidelines' => [
                'Create a Google Ads conversion named “promo for ppc - invalid Users”.',
                'Paste Conversion ID and Conversion Label below and match them to the relevant domain tag.',
                'After Clickpromo setup, create the audience in Google Ads using the same conversion.',
            ],
        ]);
    }

    public function audienceExclusionSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mapping_id' => ['nullable', 'integer'],
            'domain_id' => ['nullable', 'integer'],
            'enabled' => ['sometimes', 'boolean'],
            'audiences' => ['sometimes', 'array', 'max:20'],
            'audiences.*.conversion_id' => ['nullable', 'string', 'max:120'],
            'audiences.*.conversion_label' => ['nullable', 'string', 'max:255'],
            'audiences.*.tag' => ['nullable', 'string', 'max:120'],
            'audiences.*.domain_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;
        $mapping = null;

        if (! empty($data['mapping_id'])) {
        $mapping = DomainGoogleAdsMapping::query()
            ->where('id', $data['mapping_id'])
                ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
                ->first();
        }

        if ($mapping === null && ! empty($data['domain_id'])) {
            $mapping = DomainGoogleAdsMapping::query()
                ->where('domain_id', $data['domain_id'])
                ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
                ->orderByDesc('id')
                ->first();
        }

        if ($mapping === null) {
            $mapping = DomainGoogleAdsMapping::query()
                ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
                ->orderByDesc('id')
                ->first();
        }

        if ($mapping === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Link a domain to Google Ads first, then configure Audience Exclusion.',
            ], 422);
        }

        $audiences = AudienceExclusionAudiences::normalize((array) ($data['audiences'] ?? []));
        if (array_key_exists('audiences', $data)) {
            $errors = AudienceExclusionAudiences::validationErrors($audiences);
            if ($errors !== []) {
                return response()->json([
                    'ok' => false,
                    'message' => $errors[0],
                    'errors' => $errors,
                ], 422);
            }

            // Ensure selected tags belong to this workspace.
            $allowedDomainIds = Domain::query()->where('user_id', $userId)->pluck('id')->all();
            $allowedKeys = Domain::query()->where('user_id', $userId)->pluck('domain_key', 'id')->all();
            foreach ($audiences as &$row) {
                if ($row['domain_id'] && ! in_array($row['domain_id'], $allowedDomainIds, true)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Selected tag/domain is not in your workspace.',
                    ], 422);
                }
                if ($row['domain_id'] && empty($row['tag'])) {
                    $row['tag'] = (string) ($allowedKeys[$row['domain_id']] ?? '');
                }
            }
            unset($row);
        }

        if (array_key_exists('enabled', $data)) {
        $mapping->audience_exclusion_enabled = (bool) $data['enabled'];
        } else {
            $mapping->audience_exclusion_enabled = true;
        }

        $settings = (array) ($mapping->settings ?? []);
        if (array_key_exists('audiences', $data)) {
            $settings['conversion_audiences'] = $audiences;
        }
        $settings['audience_exclusion_updated_at'] = now()->toIso8601String();
        $mapping->settings = $settings;
        $mapping->save();

        return response()->json([
            'ok' => true,
            'mapping_id' => $mapping->id,
            'enabled' => (bool) $mapping->audience_exclusion_enabled,
            'audiences' => (array) ($settings['conversion_audiences'] ?? []),
            'message' => 'Audience Exclusion saved.',
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

    public function directAdsDestroy(Request $request, DirectAdsIntegration $integration): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($integration->user_id === $request->user()->id, 403);
        $integration->delete();

        if ($request->expectsJson() || $request->wantsJson()) {
        return response()->json(['ok' => true, 'id' => $integration->id]);
        }

        return back()->with('status', 'Direct Ads connection removed.');
    }

    public function destroyGtm(Request $request, Domain $domain): \Illuminate\Http\RedirectResponse
    {
        abort_unless((int) $domain->user_id === (int) $request->user()->id || (bool) ($request->user()->is_super_admin ?? false), 403);

        $domain->forceFill([
            'tag_connected' => false,
            'gtm_container_id' => null,
        ])->save();

        return back()->with('status', 'GTM / tracking connection removed for '.$domain->hostname.'.');
    }
}
