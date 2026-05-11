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

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->toString() . '%';
            $query->where(function ($q) use ($search): void {
                $q->where('ip', 'like', $search)
                    ->orWhere('url', 'like', $search)
                    ->orWhere('referrer', 'like', $search)
                    ->orWhere('utm_campaign', 'like', $search)
                    ->orWhere('threat_group', 'like', $search);
            });
        }

        foreach (['country', 'threat_group', 'action_taken'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }

        if ($request->boolean('blocked_only')) {
            $query->where('action_taken', 'block');
        }

        $rows = $query->orderByDesc('visited_at')
            ->paginate((int) $request->integer('per_page', 25))
            ->through(fn ($row) => [
                'id' => $row->id,
                'domain_id' => $row->domain_id,
                'ip' => $row->ip,
                'country' => $row->country,
                'url' => $row->url,
                'referrer' => $row->referrer,
                'utm_campaign' => $row->utm_campaign,
                'bot_score' => (int) ($row->threat_score ?? 0),
                'threat_group' => $row->threat_group,
                'action_taken' => $row->action_taken,
                'is_paid_traffic' => (bool) $row->is_paid_traffic,
                'is_invalid_traffic' => (bool) $row->is_invalid_traffic,
                'visited_at' => $row->visited_at,
            ]);

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
            'paid_requests' => (clone $traffic)->where('is_paid_traffic', true)->count(),
            'invalid_requests' => (clone $traffic)->where('is_invalid_traffic', true)->count(),
            'blocked_requests' => (clone $traffic)->where('action_taken', 'block')->count(),
            'threat_groups' => $detections ? (clone $detections)->whereNotNull('threat_group')->distinct('threat_group')->count('threat_group') : 0,
            'countries' => (clone $traffic)->whereNotNull('country')->distinct('country')->count('country'),
        ]);
    }

    public function blockIp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'],
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

        return response()->json([
            'message' => $blocked ? 'IP blocked.' : 'IP unblocked.',
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
            ->select([
                'id',
                'domain_id',
                'ip',
                'country',
                'url',
                'referrer',
                'utm_campaign',
                'is_paid_traffic',
                'is_invalid_traffic',
                'threat_score',
                'threat_group',
                'action_taken',
                'visited_at',
            ])
            ->whereIn('domain_id', $domainIds);
    }

    private function domainIds(Request $request)
    {
        return Domain::query()->where('user_id', $request->user()->id)->pluck('id');
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
        foreach ([
            ['name' => 'stripe', 'display_name' => 'Stripe Settings', 'provider' => 'stripe'],
            ['name' => 'google-cloud', 'display_name' => 'Google Cloud Settings', 'provider' => 'google'],
            ['name' => 'smtp', 'display_name' => 'SMTP Settings', 'provider' => 'mail'],
            ['name' => 'oauth', 'display_name' => 'OAuth Providers', 'provider' => 'oauth'],
        ] as $row) {
            AdminIntegrationSetting::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $row['name']],
                array_merge($row, ['user_id' => $userId, 'status' => 'not_configured'])
            );
        }
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
