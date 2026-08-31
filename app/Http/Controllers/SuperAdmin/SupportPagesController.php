<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\AdminIntegrationCatalog;
use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\FeatureFlag;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportPagesController extends Controller
{
    public function domains(Request $request): View
    {
        $domains = Domain::with('user')
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->string('tracking')->toString(), function ($q, string $tracking) {
                $q->where('tag_connected', $tracking === 'enabled');
            })
            ->when($request->string('search')->toString(), function ($q, string $search) {
                $q->where('hostname', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('super-admin.domains', [
            'domains' => $domains,
        ]);
    }

    public function toggleDomainTracking(Domain $domain): RedirectResponse
    {
        $domain->update(['tag_connected' => ! $domain->tag_connected]);

        return back()->with('status', 'Tracker '.($domain->tag_connected ? 'enabled' : 'disabled').' for '.$domain->hostname.'.');
    }

    public function forceVerifyDomain(Domain $domain): RedirectResponse
    {
        $domain->update(['status' => 'connected', 'last_seen_at' => now()]);

        return back()->with('status', 'Domain '.$domain->hostname.' marked as verified.');
    }

    public function regenerateDomainTracker(Domain $domain): RedirectResponse
    {
        $domain->update([
            'domain_key' => Str::uuid()->toString(),
            'secret_key' => Str::uuid()->toString(),
        ]);

        return back()->with('status', 'Tracker code regenerated for '.$domain->hostname.'.');
    }

    public function destroyDomain(Domain $domain): RedirectResponse
    {
        $hostname = $domain->hostname;
        $domain->delete();

        return back()->with('status', "Domain {$hostname} deleted.");
    }

    public function analytics(): View
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subSecond();
        $hasSubs = Schema::hasTable('subscriptions');
        $hasPayments = Schema::hasTable('payments');

        $monthlyAmount = fn ($sub) => $sub->billing_interval === 'yearly'
            ? (int) round($sub->amount_cents / 12)
            : (int) $sub->amount_cents;

        $mrrForMonth = function (Carbon $monthStart) use ($hasSubs, $monthlyAmount): int {
            if (! $hasSubs) {
                return 0;
            }
            $monthEnd = $monthStart->copy()->endOfMonth();

            return Subscription::query()
                ->where('started_at', '<=', $monthEnd)
                ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $monthStart))
                ->get(['amount_cents', 'billing_interval'])
                ->sum($monthlyAmount);
        };

        $months = collect(range(5, 0))->map(fn ($i) => $now->copy()->subMonths($i)->startOfMonth());
        $mrrTrend = $months->map(fn (Carbon $month) => [
            'label' => $month->format('M'),
            'value' => round($mrrForMonth($month) / 100, 2),
        ]);
        $mrrCurrent = $mrrForMonth($monthStart);
        $mrrPrevious = $mrrForMonth($prevMonthStart);
        $mrrDelta = $mrrPrevious > 0 ? round((($mrrCurrent - $mrrPrevious) / $mrrPrevious) * 100, 1) : 0;

        $activeAtStart = $hasSubs
            ? Subscription::where('started_at', '<', $monthStart)
                ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $monthStart))
                ->count()
            : 0;
        $cancelledThisMonth = $hasSubs
            ? Subscription::whereBetween('cancelled_at', [$monthStart, $now])->count()
            : 0;
        $churnRate = $activeAtStart > 0 ? round(($cancelledThisMonth / $activeAtStart) * 100, 1) : 0;
        $cancelledLastMonth = $hasSubs
            ? Subscription::whereBetween('cancelled_at', [$prevMonthStart, $prevMonthEnd])->count()
            : 0;
        $churnDelta = $cancelledLastMonth > 0
            ? round((($cancelledThisMonth - $cancelledLastMonth) / $cancelledLastMonth) * 100, 1)
            : 0;

        $payingCustomers = $hasPayments ? Payment::where('status', 'paid')->distinct('user_id')->count('user_id') : 0;
        $totalRevenue = $hasPayments ? Payment::where('status', 'paid')->sum('amount_cents') : 0;
        $ltv = $payingCustomers > 0 ? (int) round($totalRevenue / $payingCustomers) : 0;

        $activeSubsCount = $hasSubs ? Subscription::where('status', 'active')->count() : 0;
        $days = collect(range(6, 0))->map(fn ($i) => $now->copy()->subDays($i)->startOfDay());
        $activeSubsTrend = $days->map(fn (Carbon $day) => [
            'label' => $day->format('D'),
            'value' => $hasSubs
                ? Subscription::where('started_at', '<=', $day->copy()->endOfDay())
                    ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $day))
                    ->count()
                : 0,
        ]);
        $activeSubsWeekAgo = (int) ($activeSubsTrend->first()['value'] ?? 0);
        $activeSubsDelta = $activeSubsWeekAgo > 0
            ? round((($activeSubsCount - $activeSubsWeekAgo) / $activeSubsWeekAgo) * 100, 1)
            : 0;

        $hadTrial = $hasSubs ? Subscription::whereNotNull('trial_ends_at')->count() : 0;
        $convertedFromTrial = $hasSubs
            ? Subscription::whereNotNull('trial_ends_at')->where('is_trial', false)->count()
            : 0;
        $conversionRate = $hadTrial > 0 ? round(($convertedFromTrial / $hadTrial) * 100, 1) : 0;

        $newTrialsCount = $hasSubs ? Subscription::where('is_trial', true)->whereBetween('created_at', [$monthStart, $now])->count() : 0;
        $daysElapsedThisMonth = max(1, $monthStart->diffInDays($now) + 1);
        $newTrialsAvgPerDay = round($newTrialsCount / $daysElapsedThisMonth, 1);

        $contractionMrrCents = $hasSubs
            ? Subscription::whereBetween('cancelled_at', [$monthStart, $now])->get(['amount_cents', 'billing_interval'])->sum($monthlyAmount)
            : 0;

        $usageRows = collect(range(5, 0))->map(function ($i) use ($now) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $hasVisits = Schema::hasTable('visits');

            return [
                'date' => $day->format('M j'),
                'active_users' => $hasVisits
                    ? DB::table('visits')->whereBetween('visited_at', [$day, $day->copy()->endOfDay()])->distinct('session_id')->count('session_id')
                    : 0,
                'events_logged' => $hasVisits
                    ? DB::table('visits')->whereBetween('visited_at', [$day, $day->copy()->endOfDay()])->count()
                    : 0,
            ];
        })->reverse()->values();

        return view('super-admin.analytics', [
            'mrrTrend' => $mrrTrend,
            'mrrCurrent' => $mrrCurrent,
            'mrrDelta' => $mrrDelta,
            'churnRate' => $churnRate,
            'churnDelta' => $churnDelta,
            'ltv' => $ltv,
            'activeSubsCount' => $activeSubsCount,
            'activeSubsTrend' => $activeSubsTrend,
            'activeSubsDelta' => $activeSubsDelta,
            'conversionRate' => $conversionRate,
            'churnedCustomersCount' => $cancelledThisMonth,
            'contractionMrrCents' => $contractionMrrCents,
            'newTrialsCount' => $newTrialsCount,
            'newTrialsAvgPerDay' => $newTrialsAvgPerDay,
            'usageRows' => $usageRows,
            'totalCustomers' => User::count(),
        ]);
    }

    public function security(Request $request): View
    {
        $blockedIps = Schema::hasTable('ip_logs')
            ? DB::table('ip_logs')->where('is_blocked', true)->pluck('ip')->all()
            : [];

        $loginRows = \App\Models\LoginHistory::with('user')->latest('id')->limit(150)->get()->map(function ($r) use ($blockedIps) {
            $isSuccess = $r->status === 'success';

            return [
                'id' => null,
                'type' => 'Login',
                'icon' => $isSuccess ? 'check' : 'warning',
                'user_name' => $r->user?->name,
                'user_email' => $r->user?->email,
                'details' => $isSuccess ? 'Login Success' : 'Suspicious Login',
                'ip' => $r->ip_address,
                'country' => null,
                'time' => $r->created_at,
                'status' => $isSuccess ? 'Successful' : 'Suspicious',
                'variant' => $isSuccess ? 'success' : 'suspicious',
                'blocked' => in_array($r->ip_address, $blockedIps, true),
            ];
        });

        $detectionRows = Schema::hasTable('detection_logs')
            ? DB::table('detection_logs')
                ->leftJoin('visits', 'visits.id', '=', 'detection_logs.visit_id')
                ->orderByDesc('detection_logs.detected_at')
                ->limit(150)
                ->select('detection_logs.*', 'visits.country as visit_country')
                ->get()
                ->map(function ($r) use ($blockedIps) {
                    $variant = match ($r->action_taken) {
                        'block' => 'banned',
                        'flag', 'challenge' => 'suspicious',
                        default => 'success',
                    };

                    return [
                        'id' => $r->id,
                        'type' => 'Detection',
                        'icon' => match ($variant) { 'banned' => 'ban', 'suspicious' => 'warning', default => 'code' },
                        'user_name' => null,
                        'user_email' => null,
                        'details' => $r->threat_group ? ucwords(str_replace(['_', '-'], ' ', $r->threat_group)) : ucfirst($r->action_taken),
                        'ip' => $r->ip,
                        'country' => $r->visit_country,
                        'time' => Carbon::parse($r->detected_at),
                        'status' => match ($variant) { 'banned' => 'Banned', 'suspicious' => 'Suspicious', default => 'Successful' },
                        'variant' => $variant,
                        'blocked' => in_array($r->ip, $blockedIps, true),
                    ];
                })
            : collect();

        $all = $loginRows->concat($detectionRows)->sortByDesc('time')->values();

        if ($search = $request->string('search')->toString()) {
            $all = $all->filter(fn ($row) => str_contains(strtolower((string) $row['ip']), strtolower($search))
                || str_contains(strtolower((string) $row['user_name']), strtolower($search))
                || str_contains(strtolower((string) $row['user_email']), strtolower($search)))->values();
        }

        if ($type = $request->string('type')->toString()) {
            $all = $all->where('type', $type)->values();
        }

        if ($result = $request->string('result')->toString()) {
            $all = $all->where('status', $result)->values();
        }

        if ($date = $request->string('date')->toString()) {
            $day = Carbon::parse($date)->startOfDay();
            $all = $all->filter(fn ($row) => $row['time'] && $row['time']->between($day, $day->copy()->endOfDay()))->values();
        }

        $perPage = 10;
        $page = (int) $request->get('page', 1);
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();
        $rows = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('super-admin.security', [
            'rows' => $rows,
        ]);
    }

    public function blockIp(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['ip' => ['required', 'ip']]);

        $log = \App\Models\IpLog::query()->firstOrCreate(
            ['ip' => $data['ip']],
            ['hits' => 0, 'last_seen_at' => now()]
        );
        $log->forceFill(['is_blocked' => true, 'intel_status' => 'manual_block'])->save();

        return response()->json(['message' => "IP {$log->ip} blocked.", 'is_blocked' => true]);
    }

    public function unblockIp(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['ip' => ['required', 'ip']]);

        $log = \App\Models\IpLog::query()->firstOrCreate(
            ['ip' => $data['ip']],
            ['hits' => 0, 'last_seen_at' => now()]
        );
        $log->forceFill(['is_blocked' => false, 'intel_status' => 'manual_unblock'])->save();

        return response()->json(['message' => "IP {$log->ip} unblocked.", 'is_blocked' => false]);
    }

    public function flagDetection(int $id): \Illuminate\Http\JsonResponse
    {
        DB::table('detection_logs')->where('id', $id)->update(['action_taken' => 'flag']);

        return response()->json(['message' => 'Event flagged as suspicious.']);
    }

    public function settings(): View
    {
        $settings = AppSetting::query()->orderBy('group')->orderBy('key')->get();
        $grouped = $settings->groupBy('group');

        return view('super-admin.simple.settings', [
            'featureFlags' => FeatureFlag::orderBy('name')->get(),
            'settingsByGroup' => $grouped,
            'plans' => \App\Models\Plan::where('is_active', true)->orderBy('price_cents')->get(['id', 'slug', 'name', 'feature_flags', 'feature_limits']),
            'emailTemplates' => \App\Models\EmailTemplate::orderBy('name')->get(),
            'emailLogs' => Schema::hasTable('email_logs')
                ? \App\Models\EmailLog::query()->latest('id')->limit(40)->get()
                : collect(),
        ]);
    }

    public function updatePlanToggles(Request $request, \App\Models\Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'auto_block_allowed' => ['nullable', 'boolean'],
            'export_enabled' => ['nullable', 'boolean'],
            'export_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'advanced_filters_enabled' => ['nullable', 'boolean'],
        ]);

        $plan->update([
            'feature_flags' => array_merge($plan->feature_flags ?? [], [
                'auto_block_allowed' => (bool) ($data['auto_block_allowed'] ?? false),
                'export_enabled' => (bool) ($data['export_enabled'] ?? false),
                'advanced_filters_enabled' => (bool) ($data['advanced_filters_enabled'] ?? false),
            ]),
            'feature_limits' => array_merge($plan->feature_limits ?? [], [
                'export_days' => (int) ($data['export_days'] ?? 30),
            ]),
        ]);

        return back()->with('status', "Toggles saved for {$plan->name}.");
    }

    public function updateEmailTemplate(Request $request, \App\Models\EmailTemplate $emailTemplate): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $emailTemplate->update([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('status', "\"{$emailTemplate->name}\" template saved.");
    }

    public function restoreEmailTemplate(\App\Models\EmailTemplate $emailTemplate): RedirectResponse
    {
        $default = \App\Support\EmailTemplateDefaults::forKey($emailTemplate->key);

        if (! $default) {
            return back()->withErrors(['email' => 'No default found for this template.']);
        }

        $emailTemplate->update([
            'subject' => $default['subject'],
            'body' => $default['body'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Template restored to default.');
    }

    public function sendTestEmailTemplate(Request $request, \App\Models\EmailTemplate $emailTemplate): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $to = (string) $data['test_email'];

        \App\Services\Mail\SmtpConfigResolver::apply(true);

        if (! \App\Services\Mail\AppMailer::mailIsConfigured()) {
            $mailer = (string) config('mail.default', 'log');
            $note = \App\Services\Mail\SmtpConfigResolver::lastNote();

            return back()
                ->withInput(['test_email' => $to])
                ->with('open_modal', 'email')
                ->withErrors([
                    'email' => trim("Mail is not configured for real delivery (MAIL_MAILER={$mailer}). {$note}"),
                ]);
        }

        $readinessError = \App\Services\Mail\SmtpConfigResolver::readinessError();
        if ($readinessError !== null) {
            return back()
                ->withInput(['test_email' => $to])
                ->with('open_modal', 'email')
                ->withErrors(['email' => $readinessError]);
        }

        if ($emailTemplate->is_active === false) {
            return back()
                ->withInput(['test_email' => $to])
                ->with('open_modal', 'email')
                ->withErrors([
                    'email' => 'This template is inactive. Activate it before sending a test (production sends also skip inactive templates).',
                ]);
        }

        $sample = [
            '{{user_name}}' => (string) ($request->user()->name ?: 'Test User'),
            '{{otp_code}}' => '123456',
            '{{otp_expiry}}' => '10',
            '{{reset_expiry}}' => '10',
            '{{invite_url}}' => url('/register'),
            '{{invite_expires}}' => now()->addDays(7)->toFormattedDateString(),
            '{{plan_name}}' => 'Pro',
            '{{failure_reason}}' => 'Insufficient funds (test)',
            '{{billing_url}}' => url('/billing'),
            '{{cancel_date}}' => now()->toFormattedDateString(),
            '{{alert_title}}' => 'Test security alert',
            '{{alert_message}}' => 'This is a test alert from Settings → Email Templates.',
            '{{event_time}}' => now()->toDateTimeString(),
            '{{ip_address}}' => (string) $request->ip(),
            '{{security_url}}' => url('/profile'),
        ];

        $ok = \App\Services\Mail\AppMailer::sendTemplate(
            $emailTemplate->key,
            $to,
            $sample,
            $emailTemplate->subject,
            $emailTemplate->body
        );

        if (! $ok) {
            $detail = \App\Services\Mail\AppMailer::humanizeSmtpError(\App\Services\Mail\AppMailer::lastError());
            $message = "Could not send test email to {$to}.";
            if ($detail) {
                $message .= ' '.$detail;
            } else {
                $message .= ' Check Integrations → SMTP and storage/logs/laravel.log.';
            }

            return back()
                ->withInput(['test_email' => $to])
                ->with('open_modal', 'email')
                ->withErrors(['email' => $message]);
        }

        return back()
            ->withInput(['test_email' => $to])
            ->with('open_modal', 'email')
            ->with('status', "Test email sent to {$to}.");
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $updated = 0;

        // Indexed rows avoid PHP/Laravel dotted-key ambiguity: settings[bank.bank_name]
        $rows = $request->input('setting_rows', []);
        if (! is_array($rows)) {
            $rows = [];
        }

            foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $value = $row['value'] ?? null;
            if (is_string($key) && str_starts_with($key, 'branding.color_')) {
                $value = $this->normalizeBrandingColor($value);
                if ($value === null) {
                    continue;
                }
            }
            AppSetting::set($key, $value);
            $updated++;
        }

        $payload = $request->input('settings', []);
        if (! is_array($payload)) {
            $payload = [];
        }

        if ($payload !== []) {
            $knownKeys = AppSetting::query()->pluck('key')->all();
            $byUnderscore = [];
            foreach ($knownKeys as $known) {
                $byUnderscore[str_replace('.', '_', $known)] = $known;
            }

            foreach ($payload as $key => $value) {
                $resolved = in_array($key, $knownKeys, true)
                    ? $key
                    : ($byUnderscore[$key] ?? (is_string($key) ? $key : null));

                if (! is_string($resolved) || $resolved === '') {
                    continue;
                }

                if (str_starts_with($resolved, 'branding.color_')) {
                    $value = $this->normalizeBrandingColor($value);
                    if ($value === null) {
                        continue;
                    }
                }

                AppSetting::set($resolved, $value);
                $updated++;
            }
        }

        if ($updated === 0 && $rows === [] && $payload === []) {
            return back()->withErrors(['settings' => 'Invalid payload.']);
        }

        AppSetting::flushCache();

        $redirect = back()->with('status', $updated > 0
            ? "Settings saved ({$updated} updated)."
            : 'Settings saved.');

        if ($request->filled('return_modal')) {
            $redirect->with('open_modal', $request->string('return_modal')->toString());
        }

        return $redirect;
    }

    public function storeFeatureFlag(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:feature_flags,key'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        FeatureFlag::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'enabled' => (bool) ($data['enabled'] ?? true),
        ]);

        return back()->with('status', 'Feature flag created.');
    }

    public function toggleFeatureFlag(FeatureFlag $featureFlag): RedirectResponse
    {
        $featureFlag->update(['enabled' => ! $featureFlag->enabled]);

        return back()->with('status', 'Feature flag updated.');
    }

    public function trafficBotLogs(Request $request): View
    {
        $domainIds = Domain::query()->pluck('id');
        $base = Schema::hasTable('visits') && $domainIds->isNotEmpty()
            ? DB::table('visits')->whereIn('domain_id', $domainIds)
            : null;
        $stats = [
            'total_requests' => $base ? (clone $base)->count() : 0,
            'threat_groups' => $base ? (clone $base)->whereNotNull('threat_group')->distinct('threat_group')->count('threat_group') : 0,
            'blocked_traffic' => $base ? (clone $base)->where('action_taken', 'block')->count() : 0,
            'allow_lists' => \App\Models\IpLog::query()->where('is_blocked', false)->count(),
        ];

        return view('super-admin.traffic.index', [
            'stats' => $stats,
            'domains' => Domain::query()->orderBy('hostname')->get(['id', 'hostname']),
            'crossDomainIntel' => $this->buildCrossDomainIntel(50),
        ]);
    }

    public function crossDomainIntel(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $rows = $this->buildCrossDomainIntel(50);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'note' => 'Scores are evidence signals only — they do not auto-block.',
                'rows' => $rows,
            ]);
        }

        return view('super-admin.traffic.cross-domain', [
            'rows' => $rows,
        ]);
    }

    private function buildCrossDomainIntel(int $limit = 20): array
    {
        return app(\App\Support\CrossDomainIntel::class)->buildForDomainIds(null, $limit);
    }

    public function automation(Request $request): View
    {
        return app(\App\Http\Controllers\Admin\AutomationController::class)->superAdminIndex($request);
    }

    public function integrations(Request $request): View
    {
        $integrations = AdminIntegrationCatalog::listForUser($request->user()->id);
        $guidanceStats = [
            'published_articles' => Schema::hasTable('guidance_articles')
                ? (string) \App\Models\GuidanceArticle::query()->where('is_published', true)->count()
                : '0',
            'open_chat_sessions' => Schema::hasTable('chat_sessions')
                ? (string) \App\Models\ChatSession::query()->where('status', 'open')->count()
                : '0',
            'dashboard_endpoint' => url('/api/admin/guidance/ask'),
        ];
        $crossDomainRows = $this->buildCrossDomainIntel(500);
        $crossDomainStats = [
            'linked_domains' => (string) collect($crossDomainRows)->max('domain_count') ?: '0',
            'cross_sessions_30d' => (string) collect($crossDomainRows)->sum('hits'),
        ];

        $cards = collect($integrations)->map(function (array $row) use ($guidanceStats, $crossDomainStats) {
            $meta = AdminIntegrationCatalog::cardMeta($row['name']);
            if ($row['name'] === 'guidance-chatbot') {
                $row['settings'] = array_merge($row['settings'] ?? [], $guidanceStats);
                $row['manage_url'] = route('super-admin.guidance.index');
                $meta['connected_label'] = Schema::hasTable('guidance_articles') ? 'Synced' : 'Pending';
            }
            if ($row['name'] === 'cross-domain') {
                $row['settings'] = array_merge($row['settings'] ?? [], $crossDomainStats);
                $row['manage_url'] = route('super-admin.traffic.cross-domain');
            }

            return array_merge($row, $meta);
        })->values()->all();

        return view('super-admin.integrations.index', [
            'integrations' => $cards,
        ]);
    }

    private function normalizeBrandingColor(mixed $value): ?string
    {
        $color = trim((string) ($value ?? ''));
        if ($color === '') {
            return null;
        }
        if ($color[0] !== '#') {
            $color = '#'.$color;
        }
        if (preg_match('/^#([A-Fa-f0-9]{3})$/', $color, $m)) {
            $h = $m[1];

            return strtoupper(sprintf('#%s%s%s%s%s%s', $h[0], $h[0], $h[1], $h[1], $h[2], $h[2]));
        }
        if (preg_match('/^#([A-Fa-f0-9]{6})$/', $color)) {
            return strtoupper($color);
        }

        return null;
    }
}
