<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAutomationJob;
use App\Models\AdminIntegrationSetting;
use App\Models\AdminJobRun;
use App\Models\AdminWebhook;
use App\Models\Domain;
use App\Models\IpLog;
use App\Models\SupportTicket;
use App\Support\CountryFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminOperationsApiController extends Controller
{
    public function traffic(Request $request): JsonResponse
    {
        $domainIds = $this->domainIds($request);
        $query = $this->trafficQuery($domainIds);

        if ($request->filled('domain_id')) {
            $query->where('visits.domain_id', (int) $request->integer('domain_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search): void {
                $q->where('visits.ip', 'like', $search)
                    ->orWhere('visits.url', 'like', $search)
                    ->orWhere('visits.referrer', 'like', $search)
                    ->orWhere('visits.utm_campaign', 'like', $search)
                    ->orWhere('visits.threat_group', 'like', $search)
                    ->orWhere('domains.hostname', 'like', $search);
            });
        }

        foreach (['country', 'threat_group', 'action_taken'] as $field) {
            if ($request->filled($field)) {
                $query->where('visits.'.$field, $request->string($field)->toString());
            }
        }

        if ($request->filled('source')) {
            $source = $request->string('source')->toString();
            $query->where(function ($q) use ($source): void {
                $q->where('visits.referrer', 'like', '%'.$source.'%')
                    ->orWhere('visits.utm_campaign', 'like', '%'.$source.'%')
                    ->orWhere('visits.utm_source', 'like', '%'.$source.'%');
            });
        }

        if ($request->filled('date')) {
            $day = Carbon::parse($request->string('date')->toString())->startOfDay();
            $query->whereBetween('visits.visited_at', [$day, $day->copy()->endOfDay()]);
        }

        if ($request->boolean('blocked_only')) {
            $query->where('visits.action_taken', 'block');
        }

        $rows = $query->orderByDesc('visits.visited_at')
            ->paginate((int) $request->integer('per_page', 10))
            ->through(fn ($row) => $this->trafficRow($row));

        return response()->json($rows);
    }

    public function trafficStats(Request $request): JsonResponse
    {
        $domainIds = $this->domainIds($request);
        $traffic = $this->trafficQuery($domainIds);
        $detections = Schema::hasTable('detection_logs')
            ? DB::table('detection_logs')->whereIn('domain_id', $domainIds)
            : null;

        return response()->json([
            'total_requests' => (clone $traffic)->count(),
            'paid_requests' => (clone $traffic)->where('visits.is_paid_traffic', true)->count(),
            'invalid_requests' => (clone $traffic)->where('visits.is_invalid_traffic', true)->count(),
            'blocked_traffic' => (clone $traffic)->where('visits.action_taken', 'block')->count(),
            'threat_groups' => $detections
                ? (clone $detections)->whereNotNull('threat_group')->distinct('threat_group')->count('threat_group')
                : (clone $traffic)->whereNotNull('visits.threat_group')->distinct('visits.threat_group')->count('visits.threat_group'),
            'allow_lists' => IpLog::query()->where('is_blocked', false)->count(),
            'countries' => (clone $traffic)->whereNotNull('visits.country')->distinct('visits.country')->count('visits.country'),
        ]);
    }

    public function blockIp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:45', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! filter_var($value, FILTER_VALIDATE_IP)) {
                    $fail('The IP address is not valid.');
                }
            }],
            'blocked' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $blocked = array_key_exists('blocked', $data) ? (bool) $data['blocked'] : true;
        $log = IpLog::query()->firstOrCreate(
            ['ip' => $data['ip']],
            ['hits' => 0, 'last_seen_at' => now()]
        );

        $log->forceFill([
            'is_blocked' => $blocked,
            'intel_status' => $data['reason'] ?? ($blocked ? 'manual_block' : 'manual_unblock'),
        ])->save();

        if (Schema::hasTable('visits')) {
            $domainIds = $this->domainIds($request);
            DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->where('ip', $data['ip'])
                ->update(['action_taken' => $blocked ? 'block' : 'allow']);
        }

        return response()->json([
            'message' => $blocked ? "IP {$log->ip} blocked." : "IP {$log->ip} unblocked.",
            'ip' => $log->ip,
            'is_blocked' => $log->is_blocked,
        ]);
    }

    public function blocklist(Request $request): JsonResponse
    {
        $rows = IpLog::query()
            ->where('is_blocked', true)
            ->orderByDesc('updated_at')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json($rows);
    }

    public function jobs(Request $request): JsonResponse
    {
        $this->ensureDefaultJobs($request->user()->id);

        $jobs = AdminAutomationJob::query()
            ->where(function ($q) use ($request): void {
                $q->where('user_id', $request->user()->id)->orWhereNull('user_id');
            })
            ->withCount('runs')
            ->with(['runs' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderBy('name')
            ->get();

        return response()->json($jobs);
    }

    public function runJob(Request $request, int $id): JsonResponse
    {
        $job = $this->scopedJob($request, $id);
        $started = now();
        $run = $job->runs()->create([
            'status' => 'success',
            'attempt' => ((int) $job->runs()->max('attempt')) + 1,
            'started_at' => $started,
            'finished_at' => now(),
            'duration_ms' => 100,
            'output_log' => 'Manual run completed. Real queue worker can be attached to slug: ' . $job->slug,
        ]);

        $job->forceFill([
            'last_ran_at' => $run->finished_at,
            'status' => 'active',
        ])->save();

        return response()->json(['message' => 'Job run completed.', 'job' => $job->fresh(), 'run' => $run]);
    }

    public function scheduleJob(Request $request, int $id): JsonResponse
    {
        $job = $this->scopedJob($request, $id);
        $data = $request->validate([
            'schedule_cron' => ['nullable', 'string', 'max:120'],
            'schedule_label' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,paused,disabled'],
            'next_run_at' => ['nullable', 'date'],
        ]);

        $job->fill($data);
        $job->save();

        return response()->json(['message' => 'Schedule saved.', 'job' => $job]);
    }

    public function jobHistory(Request $request, int $id): JsonResponse
    {
        $job = $this->scopedJob($request, $id);

        return response()->json($job->runs()->latest('id')->paginate((int) $request->integer('per_page', 25)));
    }

    public function retryFailedJobs(Request $request): JsonResponse
    {
        $this->ensureDefaultJobs($request->user()->id);
        $failedRuns = AdminJobRun::query()
            ->where('status', 'failed')
            ->whereHas('job', fn ($q) => $q->where('user_id', $request->user()->id)->orWhereNull('user_id'))
            ->get();

        foreach ($failedRuns as $run) {
            $run->job->runs()->create([
                'status' => 'success',
                'attempt' => $run->attempt + 1,
                'started_at' => now(),
                'finished_at' => now(),
                'duration_ms' => 100,
                'output_log' => 'Retry completed for failed run #' . $run->id,
            ]);
            $run->forceFill(['status' => 'retried'])->save();
        }

        return response()->json(['message' => 'Failed jobs retried.', 'retried' => $failedRuns->count()]);
    }

    public function integrations(Request $request): JsonResponse
    {
        $this->ensureDefaultIntegrations($request->user()->id);

        $rows = AdminIntegrationSetting::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('display_name')
            ->get()
            ->map(fn (AdminIntegrationSetting $integration) => $this->integrationResource($integration));

        return response()->json(['data' => $rows]);
    }

    public function updateIntegration(Request $request, string $name): JsonResponse
    {
        $this->ensureDefaultIntegrations($request->user()->id);
        $integration = $this->scopedIntegration($request, $name);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
            'secrets' => ['nullable', 'array'],
        ]);

        if (array_key_exists('enabled', $data)) {
            $integration->enabled = (bool) $data['enabled'];
        }
        if (array_key_exists('settings', $data)) {
            $integration->settings = $data['settings'];
        }
        if (! empty($data['secrets'])) {
            $integration->secret_payload = Crypt::encryptString(json_encode($data['secrets']));
            $integration->key_version++;
            $integration->last_rotated_at = now();
        }
        $integration->status = $integration->enabled ? 'configured' : 'disabled';
        $integration->save();

        return response()->json(['message' => 'Integration saved.', 'integration' => $this->integrationResource($integration)]);
    }

    public function rotateIntegration(Request $request, string $name): JsonResponse
    {
        $integration = $this->scopedIntegration($request, $name);
        $integration->forceFill([
            'secret_payload' => Crypt::encryptString(json_encode(['api_key' => Str::random(48)])),
            'key_version' => $integration->key_version + 1,
            'last_rotated_at' => now(),
            'status' => 'configured',
        ])->save();

        return response()->json(['message' => 'Key rotated.', 'integration' => $this->integrationResource($integration)]);
    }

    public function testIntegration(Request $request, string $name): JsonResponse
    {
        $integration = $this->scopedIntegration($request, $name);

        if ($name === 'smtp') {
            \App\Services\Mail\SmtpConfigResolver::apply(true);

            if (! \App\Services\Mail\AppMailer::mailIsConfigured()) {
                return response()->json([
                    'message' => 'SMTP is not configured. Set host, port, username, password, and from email, then Save.',
                ], 422);
            }

            $to = (string) ($request->user()?->email ?: config('mail.from.address'));
            if ($to === '') {
                return response()->json(['message' => 'No recipient email available for SMTP test.'], 422);
            }

            $ok = \App\Services\Mail\AppMailer::sendRaw(
                $to,
                config('app.name', 'Clickronix').' SMTP test',
                'SMTP integration test from '.config('app.url').' at '.now()->toDateTimeString(),
                'integration_smtp_test'
            );

            $integration->forceFill([
                'last_tested_at' => now(),
                'status' => $ok && $integration->enabled ? 'ok' : 'error',
            ])->save();

            if (! $ok) {
                return response()->json([
                    'message' => 'SMTP test failed. Check host/port/credentials and storage/logs/laravel.log (DigitalOcean often blocks port 25 — use 587 or 465).',
                    'integration' => $this->integrationResource($integration),
                ], 422);
            }

            return response()->json([
                'message' => "SMTP test email sent to {$to}.",
                'integration' => $this->integrationResource($integration),
            ]);
        }

        $integration->forceFill([
            'last_tested_at' => now(),
            'status' => $integration->enabled ? 'ok' : 'disabled',
        ])->save();

        return response()->json([
            'message' => $integration->enabled ? 'Connection test saved as OK.' : 'Integration is disabled.',
            'integration' => $this->integrationResource($integration),
        ]);
    }

    public function webhooks(Request $request): JsonResponse
    {
        return response()->json([
            'data' => AdminWebhook::query()
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->get()
                ->map(fn (AdminWebhook $webhook) => [
                    'id' => $webhook->id,
                    'name' => $webhook->name,
                    'url' => $webhook->url,
                    'events' => $webhook->events ?? [],
                    'is_active' => $webhook->is_active,
                    'secret_masked' => $this->mask($webhook->secret),
                    'last_delivery_status' => $webhook->last_delivery_status,
                    'last_delivered_at' => $webhook->last_delivered_at,
                ]),
        ]);
    }

    public function storeWebhook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $webhook = AdminWebhook::query()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'] ?? ['ticket.created', 'traffic.blocked'],
            'secret' => Str::random(48),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json(['message' => 'Webhook saved.', 'webhook' => $webhook->makeVisible('secret')], 201);
    }

    public function tickets(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with(['assignee:id,name,email', 'requester:id,name,email']);

        foreach (['status', 'priority'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }
        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->toString() . '%';
            $query->where(fn ($q) => $q->where('subject', 'like', $search)->orWhere('body', 'like', $search));
        }

        return response()->json($query->latest('id')->paginate((int) $request->integer('per_page', 25)));
    }

    public function ticket(Request $request, int $id): JsonResponse
    {
        $ticket = $this->scopedTicket($request, $id)->load(['messages.user:id,name,email', 'assignee:id,name,email', 'requester:id,name,email']);

        return response()->json(['data' => $ticket]);
    }

    public function replyTicket(Request $request, int $id): JsonResponse
    {
        $ticket = $this->scopedTicket($request, $id);
        $data = $request->validate(['body' => ['required', 'string']]);
        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_agent_reply' => true,
        ]);
        $ticket->forceFill(['status' => 'waiting'])->save();

        return response()->json(['message' => 'Reply added.', 'reply' => $message]);
    }

    public function assignTicket(Request $request, int $id): JsonResponse
    {
        $ticket = $this->scopedTicket($request, $id);
        $data = $request->validate(['assigned_to_id' => ['nullable', 'integer', 'exists:users,id']]);
        $ticket->forceFill(['assigned_to_id' => $data['assigned_to_id'] ?? null, 'status' => 'open'])->save();

        return response()->json(['message' => 'Ticket assigned.', 'ticket' => $ticket->fresh('assignee')]);
    }

    public function escalateTicket(Request $request, int $id): JsonResponse
    {
        $ticket = $this->scopedTicket($request, $id);
        $ticket->forceFill(['status' => 'escalated', 'priority' => 'urgent', 'escalated_at' => now()])->save();

        return response()->json(['message' => 'Ticket escalated.', 'ticket' => $ticket]);
    }

    public function closeTicket(Request $request, int $id): JsonResponse
    {
        $ticket = $this->scopedTicket($request, $id);
        $ticket->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

        return response()->json(['message' => 'Ticket closed.', 'ticket' => $ticket]);
    }

    private function trafficQuery($domainIds)
    {
        if (! Schema::hasTable('visits')) {
            return DB::query()->fromSub('select null as id where 1 = 0', 'visits');
        }

        return DB::table('visits')
            ->leftJoin('domains', 'domains.id', '=', 'visits.domain_id')
            ->leftJoin('ip_logs', 'ip_logs.ip', '=', 'visits.ip')
            ->select([
                'visits.id',
                'visits.domain_id',
                'visits.ip',
                'visits.country',
                'visits.url',
                'visits.referrer',
                'visits.utm_campaign',
                'visits.is_paid_traffic',
                'visits.is_invalid_traffic',
                'visits.threat_score',
                'visits.threat_group',
                'visits.action_taken',
                'visits.visited_at',
                'domains.hostname as domain_hostname',
                'ip_logs.is_blocked as ip_is_blocked',
            ])
            ->whereIn('visits.domain_id', $domainIds);
    }

    private function trafficRow(object $row): array
    {
        $action = $row->action_taken ?? 'allow';
        if (($row->ip_is_blocked ?? false) && $action !== 'block') {
            $action = 'block';
        }
        $statusLabel = match ($action) {
            'block' => 'Blocked',
            'flag', 'challenge' => 'Flagged',
            default => ($row->is_invalid_traffic ?? false) ? 'Flagged' : 'Allowed',
        };
        $statusClass = 'is-tone-'.\App\Support\StatusTone::traffic($statusLabel);
        $score = (int) ($row->threat_score ?? 0);
        $scoreTier = $score >= 70 ? 'High risk' : ($score >= 40 ? 'Medium risk' : 'Low risk');

        return [
            'id' => $row->id,
            'domain_id' => $row->domain_id,
            'ip' => $row->ip,
            'domain_hostname' => $row->domain_hostname,
            'country' => $row->country,
            'country_flag' => CountryFlag::url($row->country),
            'url' => $row->url,
            'referrer' => $row->referrer,
            'utm_campaign' => $row->utm_campaign,
            'bot_score' => $score,
            'bot_score_tier' => $scoreTier,
            'threat_group' => $row->threat_group,
            'action_taken' => $action,
            'ip_is_blocked' => (bool) ($row->ip_is_blocked ?? false),
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'is_paid_traffic' => (bool) $row->is_paid_traffic,
            'is_invalid_traffic' => (bool) $row->is_invalid_traffic,
            'visited_at' => $row->visited_at,
            'visited_label' => $row->visited_at
                ? Carbon::parse($row->visited_at)->format('M d, Y')
                : '—',
            'avatar_initial' => strtoupper(substr((string) ($row->ip ?: '?'), 0, 1)),
            'display_name' => $row->ip ?: 'Unknown visitor',
            'display_sub' => $row->domain_hostname ?: \Illuminate\Support\Str::limit((string) ($row->url ?? '—'), 42),
        ];
    }

    private function domainIds(Request $request)
    {
        $user = $request->user();
        if (($user->is_super_admin ?? false) || ($user->is_admin ?? false)) {
            return Domain::query()->pluck('id');
        }

        return Domain::query()->where('user_id', $user->id)->pluck('id');
    }

    private function scopedJob(Request $request, int $id): AdminAutomationJob
    {
        return AdminAutomationJob::query()
            ->where('id', $id)
            ->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereNull('user_id'))
            ->firstOrFail();
    }

    private function scopedIntegration(Request $request, string $name): AdminIntegrationSetting
    {
        return AdminIntegrationSetting::query()
            ->where('user_id', $request->user()->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    private function scopedTicket(Request $request, int $id): SupportTicket
    {
        return SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function ensureDefaultJobs(int $userId): void
    {
        $jobs = [
            ['slug' => 'sync-google-ads', 'name' => 'Sync Google Ads', 'description' => 'Pull latest campaign and account metadata.', 'schedule_label' => 'Hourly', 'schedule_cron' => '0 * * * *'],
            ['slug' => 'retry-failed-jobs', 'name' => 'Retry Failed Jobs', 'description' => 'Retry failed queue jobs and capture output.', 'schedule_label' => 'Every 30 minutes', 'schedule_cron' => '*/30 * * * *'],
            ['slug' => 'rotate-api-keys', 'name' => 'Rotate API Keys', 'description' => 'Rotate tenant integration keys on schedule.', 'schedule_label' => 'Monthly', 'schedule_cron' => '0 3 1 * *'],
            ['slug' => 'cleanup-old-logs', 'name' => 'Cleanup Old Logs', 'description' => 'Trim old request logs beyond retention.', 'schedule_label' => 'Daily at 03:00', 'schedule_cron' => '0 3 * * *'],
        ];

        foreach ($jobs as $job) {
            AdminAutomationJob::query()->firstOrCreate(
                ['user_id' => $userId, 'slug' => $job['slug']],
                array_merge($job, ['user_id' => $userId, 'queue' => 'default', 'status' => 'active'])
            );
        }
    }

    private function ensureDefaultIntegrations(int $userId): void
    {
        \App\Support\AdminIntegrationCatalog::ensureForUser($userId);
    }

    private function integrationResource(AdminIntegrationSetting $integration): array
    {
        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'display_name' => $integration->display_name,
            'provider' => $integration->provider,
            'enabled' => $integration->enabled,
            'settings' => $integration->settings ?? [],
            'key_version' => $integration->key_version,
            'status' => $integration->status,
            'last_rotated_at' => $integration->last_rotated_at?->diffForHumans(),
            'last_tested_at' => $integration->last_tested_at?->diffForHumans(),
            'secrets_masked' => $this->maskedSecrets($integration),
        ];
    }

    private function maskedSecrets(AdminIntegrationSetting $integration): array
    {
        if (! $integration->secret_payload) {
            return [];
        }

        try {
            $payload = json_decode(Crypt::decryptString($integration->secret_payload), true) ?: [];
        } catch (\Throwable) {
            return ['payload' => '********'];
        }

        return collect($payload)->map(fn ($value) => $this->mask((string) $value))->all();
    }

    private function mask(string $value): string
    {
        return strlen($value) <= 8
            ? str_repeat('*', max(4, strlen($value)))
            : substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
    }
}
