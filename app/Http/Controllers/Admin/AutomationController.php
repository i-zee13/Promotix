<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAutomationJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureDefaultJobs($request->user()->id);

        $jobs = AdminAutomationJob::query()
            ->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereNull('user_id'))
            ->withCount('runs')
            ->orderBy('name')
            ->get();

        $items = $jobs->map(fn (AdminAutomationJob $job) => [
            'href' => route('automation.show', $job),
            'title' => $job->name,
            'description' => $job->description,
            'schedule' => $job->schedule_label ?? $job->schedule_cron ?? 'Manual',
            'queue_badge' => $job->status === 'active' ? 'Queue Healthy' : 'Queue Paused',
            'queue_healthy' => $job->status === 'active',
            'status' => ucfirst($job->status),
            'icon' => str_contains($job->slug, 'google') ? 'google' : (str_contains($job->slug, 'retry') ? 'refresh' : 'exclamation'),
            'middle_title' => 'Last run:',
            'middle_bars' => false,
            'middle_badges' => [$job->last_ran_at?->diffForHumans() ?? 'Never run', $job->runs_count . ' runs'],
            'right_title' => 'Next run:',
            'right_pills' => false,
            'right_grid' => true,
        ])->all();

        $total = $jobs->count();
        $from = $total ? 1 : 0;
        $to = $total;

        return view('automation', compact('items', 'total', 'from', 'to'));
    }

    public function superAdminIndex(Request $request): View
    {
        $this->ensureDefaultJobs($request->user()->id);

        $jobs = AdminAutomationJob::query()
            ->withCount('runs')
            ->orderBy('name')
            ->get();

        return view('super-admin.automation.index', [
            'jobs' => $jobs,
        ]);
    }

    public function show(Request $request, AdminAutomationJob $job): View
    {
        abort_unless($job->user_id === $request->user()->id || $job->user_id === null, 403);

        $runs = $job->runs()->latest('id')->paginate(15);

        return view('automation-detail', compact('job', 'runs'));
    }

    private function ensureDefaultJobs(int $userId): void
    {
        foreach ([
            ['slug' => 'sync-google-ads', 'name' => 'Sync Google Ads', 'description' => 'Pull latest campaign and account metadata.', 'schedule_label' => 'Hourly', 'schedule_cron' => '0 * * * *'],
            ['slug' => 'retry-failed-jobs', 'name' => 'Retry Failed Jobs', 'description' => 'Retry failed queue jobs and capture output.', 'schedule_label' => 'Every 30 minutes', 'schedule_cron' => '*/30 * * * *'],
            ['slug' => 'rotate-api-keys', 'name' => 'Rotate API Keys', 'description' => 'Rotate tenant integration keys on schedule.', 'schedule_label' => 'Monthly', 'schedule_cron' => '0 3 1 * *'],
            ['slug' => 'cleanup-old-logs', 'name' => 'Cleanup Old Logs', 'description' => 'Trim old request logs beyond retention.', 'schedule_label' => 'Daily at 03:00', 'schedule_cron' => '0 3 * * *'],
            ['slug' => 'suspend-unpaid-users', 'name' => 'Suspend Unpaid Users', 'description' => 'Suspend users with unpaid invoices.', 'schedule_label' => 'Daily at 02:00', 'schedule_cron' => '0 2 * * *'],
            ['slug' => 'auto-delete-old-data', 'name' => 'Auto-Delete Old Data', 'description' => 'Purge stale analytics and log data past retention.', 'schedule_label' => 'Weekly', 'schedule_cron' => '0 4 * * 0'],
        ] as $job) {
            AdminAutomationJob::query()->firstOrCreate(
                ['user_id' => $userId, 'slug' => $job['slug']],
                array_merge($job, ['user_id' => $userId, 'queue' => 'default', 'status' => 'active'])
            );
        }
    }
}
