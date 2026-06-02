<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        $alreadyExists = Domain::query()
            ->where('user_id', $request->user()->id)
            ->where('hostname', $hostname)
            ->exists();
        if ($alreadyExists) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Domain already exists.'], 409);
            }
            return back()->withErrors(['hostname' => 'Domain already exists.']);
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

        $exists = Domain::query()
            ->where('user_id', $request->user()->id)
            ->where('hostname', $hostname)
            ->exists();

        if ($exists) {
            return response()->json(['valid' => false, 'message' => 'Domain already exists.'], 409);
        }

        return response()->json(['valid' => true, 'hostname' => $hostname]);
    }

    public function bulkAdd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hostnames' => ['required', 'array', 'min:1'],
            'hostnames.*' => ['required', 'string', 'max:255'],
        ]);

        $domainLimit = (int) env('DOMAIN_LIMIT', 50);
        $currentCount = Domain::query()->where('user_id', $request->user()->id)->manual()->count();
        $added = [];
        $skipped = [];

        foreach ($data['hostnames'] as $raw) {
            if ($currentCount >= $domainLimit) {
                $skipped[] = ['hostname' => (string) $raw, 'reason' => 'Domain limit reached'];
                continue;
            }
            $hostname = $this->normalizeHostname((string) $raw);
            if (! $this->isValidHostname($hostname)) {
                $skipped[] = ['hostname' => (string) $raw, 'reason' => 'Invalid hostname'];
                continue;
            }
            $domain = Domain::query()->firstOrCreate(
                ['user_id' => $request->user()->id, 'hostname' => $hostname],
                [
                    'source' => Domain::SOURCE_MANUAL,
                    'domain_key' => Str::uuid()->toString(),
                    'secret_key' => Str::uuid()->toString(),
                    'authentication_key' => Str::uuid()->toString(),
                    'status' => 'pending',
                ]
            );

            if ($domain->wasRecentlyCreated) {
                $currentCount++;
                $added[] = $hostname;
            } else {
                $skipped[] = ['hostname' => $hostname, 'reason' => 'Duplicate'];
            }
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
            $duplicate = Domain::query()
                ->where('user_id', $request->user()->id)
                ->where('hostname', $hostname)
                ->where('id', '!=', $domain->id)
                ->exists();
            if ($duplicate) {
                return response()->json(['message' => 'Domain already exists.'], 409);
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
        $domain->delete();

        return response()->json(['ok' => true]);
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
            'head_script' => '<script async src="' . $scriptUrl . '" class="pm_tag"></script>',
            'body_script' => '<noscript><iframe src="' . $noscriptUrl . '" width="0" height="0" style="display:none"></iframe></noscript>',
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
            . "Head script:\n<script async src=\"{$scriptUrl}\" class=\"pm_tag\"></script>\n\n"
            . "Body script:\n<noscript><iframe src=\"{$noscriptUrl}\" width=\"0\" height=\"0\" style=\"display:none\"></iframe></noscript>\n\n"
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
        $message = 'Could not reach WordPress verification endpoint.';
        $hosts = array_values(array_unique(array_filter([
            $domain->hostname,
            str_starts_with($domain->hostname, 'www.') ? substr($domain->hostname, 4) : 'www.' . $domain->hostname,
        ])));

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
                        $message = (string) ($response->json('message') ?? 'Verification response received.');
                        break 2;
                    }
                    $message = 'WordPress returned HTTP ' . $response->status() . ' on ' . $host . '.';
                } catch (\Throwable $e) {
                    $message = 'Could not reach ' . $scheme . '://' . $host . ' (check SSL, firewall, or REST API).';
                }
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
        ]);
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

