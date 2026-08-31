<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Plan;
use App\Support\DomainKeyHostGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DomainManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $domainLimit = (int) env('DOMAIN_LIMIT', 50);
        $domainsQuery = Domain::query()
            ->select('domains.*')
            ->where('user_id', $request->user()->id)
            ->manual()
            ->with('googleAdsAccount')
            ->when($search !== '', fn ($q) => $q->where('hostname', 'like', '%' . $search . '%'))
            ->orderBy('hostname');

        if (\Illuminate\Support\Facades\Schema::hasTable('visits')) {
            $domainsQuery
                ->selectSub(
                    \Illuminate\Support\Facades\DB::table('visits')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('visits.domain_id', 'domains.id')
                        ->where('is_paid_traffic', true)
                        ->where('is_invalid_traffic', false),
                    'valid_visits_count'
                )
                ->selectSub(
                    \Illuminate\Support\Facades\DB::table('visits')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('visits.domain_id', 'domains.id')
                        ->where('is_invalid_traffic', true),
                    'invalid_visits_count'
                );
        }

        $domains = $domainsQuery->paginate(25);

        $user = $request->user();
        $domainCount = Domain::query()->where('user_id', $user->id)->manual()->count();
        $limit = $user->domainLimit();
        $limitDisplay = $limit === INF ? '∞' : (string) (int) $limit;

        $pickPaidDomainId = (int) $request->query('pick_paid_domain', 0);
        $pickDomain = $pickPaidDomainId > 0
            ? Domain::query()->where('user_id', $user->id)->manual()->find($pickPaidDomainId)
            : null;

        return view('domains.index', [
            'domains' => $domains,
            'search' => $search,
            'domainLimit' => $domainLimit,
            'domainCount' => $domainCount,
            'domainLimitDisplay' => $limitDisplay,
            'canAddDomain' => $user->canAddDomain(),
            'currentPlan' => $user->currentPlan(),
            'planTiers' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'pickPaidDomain' => $pickDomain,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:255'],
        ]);

        $hostname = $this->normalizeHostname($validated['hostname']);
        if (! $this->isValidHostname($hostname)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please enter a valid domain hostname (e.g. example.com).'], 422);
            }
            return back()->withErrors(['hostname' => 'Please enter a valid domain hostname (e.g. example.com).']);
        }
        if ($this->manualDomainExists($request->user()->id, $hostname)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This domain is already in your manual site list.'], 409);
            }

            return back()->withErrors(['hostname' => 'This domain is already in your manual site list.']);
        }

        $user = $request->user();
        if (! $user->canAddDomain()) {
            $limit = $user->domainLimit();
            $message = sprintf(
                'Your current plan allows %s domain%s. Upgrade your plan to connect more.',
                $limit === INF ? 'unlimited' : (int) $limit,
                $limit === 1 ? '' : 's'
            );
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'upgrade_url' => route('upgrade-plan'),
                ], 422);
            }
            return redirect()
                ->route('upgrade-plan')
                ->with('status', $message);
        }

        $domain = $this->promoteLegacySyncedToManual($request->user()->id, $hostname);
        if (! $domain) {
            $domain = Domain::create([
                'user_id' => $request->user()->id,
                'hostname' => $hostname,
                'source' => Domain::SOURCE_MANUAL,
                'domain_key' => Str::uuid()->toString(),
                'secret_key' => Str::uuid()->toString(),
                'authentication_key' => Str::uuid()->toString(),
                'status' => 'pending',
                'tracking_params' => [
                    'utm_source' => true,
                    'utm_medium' => true,
                    'utm_campaign' => true,
                    'utm_term' => true,
                ],
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'domain' => $domain]);
        }

        return back()->with('status', 'Domain saved.');
    }

    public function list(Request $request): JsonResponse
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('hostname')
            ->get();

        return response()->json($domains);
    }

    public function validateDomain(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255'],
        ]);
        $hostname = $this->normalizeHostname($data['hostname']);

        if (! $this->isValidHostname($hostname)) {
            return response()->json(['valid' => false, 'message' => 'Invalid domain format.'], 422);
        }

        if ($this->manualDomainExists($request->user()->id, $hostname)) {
            return response()->json(['valid' => false, 'message' => 'This domain is already in your manual site list.'], 409);
        }

        return response()->json(['valid' => true, 'hostname' => $hostname]);
    }

    public function bulkAdd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hostnames' => ['required', 'array', 'min:1'],
            'hostnames.*' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $currentCount = $user->domainsUsed();
        $added = [];
        $skipped = [];

        foreach ($data['hostnames'] as $raw) {
            if (! $user->canAddDomain()) {
                $skipped[] = ['hostname' => (string) $raw, 'reason' => 'Domain limit reached'];
                continue;
            }
            $hostname = $this->normalizeHostname((string) $raw);
            if (! $this->isValidHostname($hostname)) {
                $skipped[] = ['hostname' => (string) $raw, 'reason' => 'Invalid hostname'];
                continue;
            }
            if ($this->manualDomainExists($request->user()->id, $hostname)) {
                $skipped[] = ['hostname' => $hostname, 'reason' => 'Already in manual list'];

                continue;
            }

            $domain = $this->promoteLegacySyncedToManual($request->user()->id, $hostname);
            if (! $domain) {
                $domain = Domain::create([
                    'user_id' => $request->user()->id,
                    'hostname' => $hostname,
                    'source' => Domain::SOURCE_MANUAL,
                    'domain_key' => Str::uuid()->toString(),
                    'secret_key' => Str::uuid()->toString(),
                    'authentication_key' => Str::uuid()->toString(),
                    'status' => 'pending',
                ]);
            }

            $currentCount++;
            $added[] = $hostname;
        }

        return response()->json(compact('added', 'skipped'));
    }

    public function update(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'hostname' => ['sometimes', 'string', 'max:255'],
            'paid_marketing_connected' => ['sometimes', 'boolean'],
            'bot_mitigation_connected' => ['sometimes', 'boolean'],
            'monitoring_only_mode' => ['sometimes', 'boolean'],
            'tag_connected' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['hostname'])) {
            $hostname = $this->normalizeHostname($data['hostname']);
            if (! $this->isValidHostname($hostname)) {
                return response()->json(['message' => 'Invalid domain hostname.'], 422);
            }
            if ($this->manualDomainExists($request->user()->id, $hostname, $domain->id)) {
                return response()->json(['message' => 'This domain is already in your manual site list.'], 409);
            }
            $domain->hostname = $hostname;
            unset($data['hostname']);
        }

        $domain->fill($data);
        $domain->save();

        return response()->json(['ok' => true, 'domain' => $domain->fresh()]);
    }

    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        // Plan slots are permanent: customers cannot free a domain slot by deleting.
        // Super Admin can still remove domains from Super Admin → Domains.
        return response()->json([
            'message' => 'Domains cannot be removed once added. This keeps your plan domain slots from being reused. Contact support if a hostname must be changed, or upgrade your plan for more domains.',
        ], 403);
    }

    public function updateStatus(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'status' => ['required', 'in:pending,connected,disabled'],
        ]);

        $domain->status = $data['status'];
        if ($data['status'] === 'connected') {
            $domain->tag_connected = true;
        } elseif ($data['status'] === 'disabled') {
            $domain->tag_connected = false;
        }
        $domain->save();

        return response()->json(['ok' => true, 'status' => $domain->status]);
    }

    public function trackingScript(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $scriptUrl = url('/tag/' . $domain->domain_key . '.js');
        $noscriptUrl = url('/tag/' . $domain->domain_key . '.html');

        return response()->json([
            'head_script' => '<script src="' . $scriptUrl . '" class="pm_tag"></script>',
            'body_script' => '<noscript><iframe src="' . $noscriptUrl . '" width="0" height="0" style="display:none"></iframe></noscript>',
            'instructions' => 'Paste head_script into Header tags and body_script into Body tags. Do not edit header.php.',
        ]);
    }

    public function apiKey(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        return response()->json([
            'server_url' => rtrim((string) config('app.url'), '/'),
            'domain_key' => $domain->domain_key,
            'secret_key' => $domain->secret_key,
            'authentication_key' => $domain->authentication_key,
            'hostname' => $domain->hostname,
        ]);
    }

    public function updateGtm(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'gtm_container_id' => ['nullable', 'string', 'max:32', 'regex:/^GTM-[A-Z0-9]+$/'],
        ]);
        $domain->gtm_container_id = $data['gtm_container_id'] ?? null;
        $domain->save();

        return response()->json(['ok' => true, 'gtm_container_id' => $domain->gtm_container_id]);
    }

    public function updateTrackingParams(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'tracking_params' => ['required', 'array'],
        ]);
        $domain->tracking_params = $data['tracking_params'];
        $domain->save();

        return response()->json(['ok' => true, 'tracking_params' => $domain->tracking_params]);
    }

    public function emailDeveloper(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);
        $scriptUrl = url('/tag/' . $domain->domain_key . '.js');
        $noscriptUrl = url('/tag/' . $domain->domain_key . '.html');

        $body = "Install tracking for {$domain->hostname}\n\n"
            . "Use your site's Header tags / Body tags areas (WPCode, Insert Headers and Footers, theme Header Scripts, etc.).\n"
            . "Do NOT edit theme files like header.php — page builders may not load them.\n\n"
            . "1) Header tags — paste at the top:\n<script src=\"{$scriptUrl}\" class=\"pm_tag\"></script>\n\n"
            . "2) Body tags — paste at the top:\n<noscript><iframe src=\"{$noscriptUrl}\" width=\"0\" height=\"0\" style=\"display:none\"></iframe></noscript>\n\n"
            . "Clear cache after saving, then verify in PromoTix.\n\n"
            . "Domain key: {$domain->domain_key}\nSecret key: {$domain->secret_key}\nAuth key: {$domain->authentication_key}\n";

        Mail::raw($body, function ($message) use ($data, $domain) {
            $message->to($data['email'])
                ->subject('Promotix installation instructions - ' . $domain->hostname);
        });

        return response()->json(['ok' => true]);
    }

    public function verifyWordpress(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $verified = false;
        $message = 'Could not verify tag installation.';
        $method = null;

        $wpResult = $this->verifyWordpressPlugin($domain);
        if ($wpResult['verified']) {
            $verified = true;
            $message = $wpResult['message'];
            $method = 'wordpress';
        }

        if (! $verified) {
            $htmlResult = $this->verifyTagInPageSource($domain);
            if ($htmlResult['verified']) {
                $verified = true;
                $message = $htmlResult['message'];
                $method = 'html';
            } elseif ($htmlResult['message'] !== '') {
                $message = $htmlResult['message'];
            }
        }

        if (! $verified) {
            $activityResult = $this->verifyRecentTagActivity($domain);
            if ($activityResult['verified']) {
                $verified = true;
                $message = $activityResult['message'];
                $method = 'activity';
            } elseif ($activityResult['message'] !== '') {
                $message = $activityResult['message'];
            }
        }

        if ($verified) {
            $domain->tag_connected = true;
            $domain->status = 'connected';
            $domain->save();
        }

        return response()->json([
            'verified' => $verified,
            'message' => $message,
            'method' => $method,
        ]);
    }

    public function checkTagConnectivity(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $wpResult = $this->verifyWordpressPlugin($domain);
        $htmlResult = $this->verifyTagInPageSource($domain);
        $activityResult = $this->verifyRecentTagActivity($domain);

        $recentVisitCount = 0;
        if (Schema::hasTable('visits')) {
            $recentRows = DB::table('visits')
                ->where('domain_id', $domain->id)
                ->where('visited_at', '>=', now()->subDays(7))
                ->limit(50)
                ->get(['url', 'referrer']);
            foreach ($recentRows as $row) {
                $urlHost = DomainKeyHostGuard::hostFromUrl((string) ($row->url ?? ''));
                $refHost = DomainKeyHostGuard::hostFromUrl((string) ($row->referrer ?? ''));
                if (
                    ($urlHost !== '' && DomainKeyHostGuard::hostsMatch($urlHost, (string) $domain->hostname))
                    || ($refHost !== '' && DomainKeyHostGuard::hostsMatch($refHost, (string) $domain->hostname))
                ) {
                    $recentVisitCount++;
                }
            }
        }

        $tagInstalled = $wpResult['verified'] || $htmlResult['verified'];
        $receivingTraffic = $activityResult['verified'] || $recentVisitCount > 0;

        $checks = [
            [
                'id' => 'html',
                'label' => 'Tag in page HTML',
                'passed' => $htmlResult['verified'],
                'detail' => $htmlResult['message'],
            ],
            [
                'id' => 'plugin',
                'label' => 'WordPress plugin',
                'passed' => $wpResult['verified'],
                'detail' => $wpResult['message'],
            ],
            [
                'id' => 'activity',
                'label' => 'Receiving visits (last 7 days)',
                'passed' => $receivingTraffic,
                'detail' => $receivingTraffic
                    ? ($recentVisitCount > 0
                        ? "{$recentVisitCount} visit(s) recorded in the last 7 days."
                        : $activityResult['message'])
                    : ($domain->last_seen_at
                        ? 'Last tag ping: ' . $domain->last_seen_at->diffForHumans() . '. No visits in the last 7 days.'
                        : 'No visits recorded yet. Install the tag and open the site.'),
            ],
        ];

        if ($tagInstalled && $receivingTraffic) {
            $status = 'working';
            $message = 'Tag is installed and actively receiving visits.';
        } elseif ($tagInstalled) {
            $status = 'installed_no_traffic';
            $message = 'Tag appears installed on the site, but PromoTix has not received visits recently. Clear site cache and open the homepage, or check Header tags / plugin settings.';
        } elseif ($receivingTraffic) {
            $status = 'traffic_stale_install';
            $message = 'Recent visits exist but the tag was not found in live page HTML. Re-install via Header tags or the WordPress plugin.';
        } else {
            $status = 'not_connected';
            $message = 'Tag not detected and no recent visits. Install the tracking tag from Setup.';
        }

        $working = $status === 'working';

        if ($working) {
            $domain->tag_connected = true;
            $domain->status = 'connected';
            $domain->save();
        }

        return response()->json([
            'ok' => true,
            'working' => $working,
            'status' => $status,
            'message' => $message,
            'checks' => $checks,
            'last_seen_at' => $domain->last_seen_at?->toIso8601String(),
            'recent_visit_count' => $recentVisitCount,
            'tag_connected' => (bool) $domain->tag_connected,
            'setup_url' => route('domains.setup', $domain),
        ]);
    }

    /**
     * @return array{verified: bool, message: string}
     */
    private function verifyWordpressPlugin(Domain $domain): array
    {
        $message = 'Could not reach WordPress verification endpoint.';
        $hosts = $this->verificationHosts($domain->hostname);

        foreach (['https', 'http'] as $scheme) {
            foreach ($hosts as $host) {
                $endpoint = $scheme . '://' . $host . '/wp-json/promotix/v1/verify';
                try {
                    $response = Http::timeout(12)->get($endpoint, [
                        'domain_key' => $domain->domain_key,
                        'secret_key' => $domain->secret_key,
                    ]);
                    if ($response->successful()) {
                        $verified = (bool) $response->json('verified');

                        return [
                            'verified' => $verified,
                            'message' => (string) ($response->json('message') ?? ($verified
                                ? 'WordPress plugin verified.'
                                : 'WordPress plugin keys do not match or plugin is disabled.')),
                        ];
                    }
                    $message = 'WordPress returned HTTP ' . $response->status() . ' on ' . $host . '.';
                } catch (\Throwable) {
                    $message = 'Could not reach ' . $scheme . '://' . $host . ' (check SSL, firewall, or REST API).';
                }
            }
        }

        return ['verified' => false, 'message' => $message];
    }

    /**
     * @return array{verified: bool, message: string}
     */
    private function verifyTagInPageSource(Domain $domain): array
    {
        $domainKey = (string) $domain->domain_key;
        $needle = '/tag/' . $domainKey . '.js';
        $hosts = $this->verificationHosts($domain->hostname);
        $paths = ['/', '/index.html'];
        $message = 'Tag script was not found in the page HTML.';

        foreach (['https', 'http'] as $scheme) {
            foreach ($hosts as $host) {
                foreach ($paths as $path) {
                    $url = $scheme . '://' . $host . $path;
                    try {
                        $response = Http::timeout(15)
                            ->withHeaders([
                                'User-Agent' => 'PromoTix-Tag-Verifier/1.0',
                                'Accept' => 'text/html,application/xhtml+xml',
                            ])
                            ->get($url);

                        if (! $response->successful()) {
                            $message = 'Site returned HTTP ' . $response->status() . ' on ' . $host . $path . '.';
                            continue;
                        }

                        $html = strtolower($response->body());
                        if (
                            str_contains($html, strtolower($needle))
                            || str_contains($html, strtolower($domainKey))
                        ) {
                            return [
                                'verified' => true,
                                'message' => 'Tracking tag found in page source on ' . $scheme . '://' . $host . $path . '.',
                            ];
                        }

                        $message = 'Tag script not found in HTML on ' . $scheme . '://' . $host . $path . '. Check domain key matches this site.';
                    } catch (\Throwable) {
                        $message = 'Could not fetch ' . $scheme . '://' . $host . $path . ' to inspect page source.';
                    }
                }
            }
        }

        return ['verified' => false, 'message' => $message];
    }

    /**
     * @return array{verified: bool, message: string}
     */
    private function verifyRecentTagActivity(Domain $domain): array
    {
        if (! Schema::hasTable('visits')) {
            if (! $domain->last_seen_at) {
                return [
                    'verified' => false,
                    'message' => 'No visit received yet. Open the site in a browser after installing the tag, then verify again.',
                ];
            }

            return [
                'verified' => false,
                'message' => 'Could not verify hostname-bound tag activity. Open '.$domain->hostname.' with this domain’s own keys installed.',
            ];
        }

        $since = now()->subDays(7);
        $recent = DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('visited_at', '>=', $since)
            ->orderByDesc('visited_at')
            ->limit(40)
            ->get(['url', 'referrer', 'visited_at']);

        $matched = 0;
        foreach ($recent as $row) {
            $urlHost = DomainKeyHostGuard::hostFromUrl((string) ($row->url ?? ''));
            $refHost = DomainKeyHostGuard::hostFromUrl((string) ($row->referrer ?? ''));
            $ok = ($urlHost !== '' && DomainKeyHostGuard::hostsMatch($urlHost, (string) $domain->hostname))
                || ($refHost !== '' && DomainKeyHostGuard::hostsMatch($refHost, (string) $domain->hostname));
            if ($ok) {
                $matched++;
            }
        }

        if ($matched > 0) {
            return [
                'verified' => true,
                'message' => "Tag is active on {$domain->hostname} — {$matched} matching visit(s) in the last 7 days.",
            ];
        }

        if ($domain->last_seen_at && $domain->last_seen_at->gte($since)) {
            return [
                'verified' => false,
                'message' => 'Recent pings were received but none matched hostname '.$domain->hostname.'. Keys from another domain cannot activate this site — install this domain’s own keys and open '.$domain->hostname.'.',
            ];
        }

        return [
            'verified' => false,
            'message' => 'No visit received yet from '.$domain->hostname.'. Install this domain’s keys on that site only, open it in a browser, then verify again.',
        ];
    }

    /**
     * @return list<string>
     */
    private function verificationHosts(string $hostname): array
    {
        $hostname = strtolower(trim($hostname));

        return array_values(array_unique(array_filter([
            $hostname,
            str_starts_with($hostname, 'www.') ? substr($hostname, 4) : 'www.' . $hostname,
        ])));
    }

    public function wordpressPlugin(): BinaryFileResponse
    {
        $zipPath = $this->buildWordpressPluginZip();
        return response()->download($zipPath, 'promotix-tag.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function setup(Request $request, Domain $domain): View
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        return view('domains.setup', [
            'domain' => $domain,
        ]);
    }

    public function downloadWpPlugin(Request $request, Domain $domain): BinaryFileResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $zipPath = $this->buildWordpressPluginZip($domain);

        return response()->download($zipPath, 'promotix-tag-' . $domain->domain_key . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function buildWordpressPluginZip(?Domain $domain = null): string
    {
        $slug = 'promotix-tag';
        $baseDir = base_path('resources/wp-plugin/' . $slug);
        $mainFile = $baseDir . DIRECTORY_SEPARATOR . $slug . '.php';

        if (! file_exists($mainFile)) {
            abort(404, 'Plugin source not found.');
        }

        $zipName = $domain ? $slug . '-' . $domain->domain_key : $slug;
        $zipPath = storage_path('app/' . $zipName . '.zip');
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new \ZipArchive();
        $ok = $zip->open($zipPath, \ZipArchive::CREATE);
        if ($ok !== true) {
            abort(500, 'Unable to create plugin zip.');
        }

        // Add files under a top-level "{slug}/" folder (WordPress expects this).
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }
            $relative = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), $slug . '/' . str_replace('\\', '/', $relative));
        }

        if ($domain) {
            $config = json_encode([
                'server_url' => rtrim((string) config('app.url'), '/'),
                'domain_key' => $domain->domain_key,
                'secret_key' => $domain->secret_key,
                'authentication_key' => $domain->authentication_key,
                'enabled' => '1',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $zip->addFromString($slug . '/promotix-tag-config.json', $config);
        }

        $zip->close();
        return $zipPath;
    }

    private function manualDomainExists(int $userId, string $hostname, ?int $exceptDomainId = null): bool
    {
        return Domain::query()
            ->where('user_id', $userId)
            ->manual()
            ->where('hostname', $hostname)
            ->when($exceptDomainId, fn ($q) => $q->where('id', '!=', $exceptDomainId))
            ->exists();
    }

    /**
     * Old Google Ads sync rows (source=google_ads) can be claimed as manual when the user adds the same hostname.
     */
    private function promoteLegacySyncedToManual(int $userId, string $hostname): ?Domain
    {
        $legacy = Domain::query()
            ->where('user_id', $userId)
            ->where('hostname', $hostname)
            ->where('source', Domain::SOURCE_GOOGLE_ADS)
            ->first();

        if (! $legacy) {
            return null;
        }

        $legacy->source = Domain::SOURCE_MANUAL;
        if (! $legacy->domain_key) {
            $legacy->domain_key = Str::uuid()->toString();
        }
        if (! $legacy->secret_key) {
            $legacy->secret_key = Str::uuid()->toString();
        }
        if (! $legacy->authentication_key) {
            $legacy->authentication_key = Str::uuid()->toString();
        }
        $legacy->save();

        return $legacy->fresh();
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('#^https?://#', '', $hostname);
        $hostname = explode('/', $hostname)[0] ?? $hostname;
        $hostname = rtrim($hostname, '.');
        return $hostname;
    }

    private function isValidHostname(string $hostname): bool
    {
        if ($hostname === '' || ! str_contains($hostname, '.')) {
            return false;
        }
        return filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

}

