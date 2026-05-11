@extends('layouts.admin')

@section('title', 'Job Detail')
@section('subtitle', $job->name)

@section('content')
<div class="space-y-6"
    x-data="automationDetail({
        urls: {
            run: '{{ url('api/admin/jobs/'.$job->id.'/run') }}',
            schedule: '{{ url('api/admin/jobs/'.$job->id.'/schedule') }}',
            history: '{{ url('api/admin/jobs/'.$job->id.'/history') }}',
            retryFailed: '{{ url('api/admin/jobs/retry-failed') }}',
        },
        csrf: '{{ csrf_token() }}',
        initial: {
            schedule_label: @js($job->schedule_label),
            schedule_cron: @js($job->schedule_cron),
            status: @js($job->status),
            runs: @js($runs->getCollection()->map(fn ($run) => [
                'id' => $run->id,
                'status' => $run->status,
                'attempt' => $run->attempt,
                'started_at' => $run->started_at?->diffForHumans() ?? '—',
                'finished_at' => $run->finished_at?->diffForHumans() ?? '—',
                'output' => $run->output_log ?? $run->error_message ?? '—',
            ])->values()),
        },
    })">
    <x-ui.page-header :title="$job->name" subtitle="Schedule editor, manual run, run history, and job output logs">
        <x-slot:actions>
            <a href="{{ route('automation') }}" class="brand-btn-secondary">Back to jobs</a>
            <button type="button" class="brand-btn-primary" @click="runNow()" :disabled="loading.run">
                <span x-show="!loading.run">Run now</span>
                <span x-show="loading.run">Running...</span>
            </button>
            <button type="button" class="brand-btn-outline" @click="retryFailed()" :disabled="loading.retry">
                <span x-show="!loading.retry">Retry failed jobs</span>
                <span x-show="loading.retry">Retrying...</span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <template x-if="toast.message">
        <div class="rounded-xl2 border px-4 py-3 text-sm"
            :class="toast.type === 'error' ? 'border-rose-500/40 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
            <span x-text="toast.message"></span>
        </div>
    </template>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card class="xl:col-span-1" title="Schedule Editor" subtitle="Update cron, label, status, and next run time">
            <form @submit.prevent="saveSchedule()" class="space-y-4">
                <div>
                    <label class="brand-label">Schedule label</label>
                    <input x-model="schedule_label" class="brand-input mt-1" placeholder="Hourly">
                </div>
                <div>
                    <label class="brand-label">Cron expression</label>
                    <input x-model="schedule_cron" class="brand-input mt-1 font-mono" placeholder="0 * * * *">
                </div>
                <div>
                    <label class="brand-label">Status</label>
                    <select x-model="status" class="brand-select mt-1">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>
                <button type="submit" class="brand-btn-primary w-full" :disabled="loading.schedule">
                    <span x-show="!loading.schedule">Save schedule</span>
                    <span x-show="loading.schedule">Saving...</span>
                </button>
            </form>
        </x-ui.card>

        <x-ui.card class="xl:col-span-2 !p-0 overflow-hidden" title="Run History">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Attempt</th>
                            <th>Started</th>
                            <th>Finished</th>
                            <th>Output</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="run in runs" :key="run.id">
                            <tr>
                                <td>
                                    <span class="brand-pill"
                                        :class="run.status === 'success' ? 'brand-pill-success' : (run.status === 'failed' ? 'brand-pill-danger' : 'brand-pill-neutral')"
                                        x-text="run.status.charAt(0).toUpperCase() + run.status.slice(1)"></span>
                                </td>
                                <td x-text="run.attempt"></td>
                                <td x-text="run.started_at"></td>
                                <td x-text="run.finished_at"></td>
                                <td class="max-w-md truncate" :title="run.output" x-text="run.output"></td>
                            </tr>
                        </template>
                        <tr x-show="runs.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-night-300">No run history yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</div>

<script>
function automationDetail(initial) {
    return {
        urls: initial.urls,
        csrf: initial.csrf,
        schedule_label: initial.initial.schedule_label || '',
        schedule_cron: initial.initial.schedule_cron || '',
        status: initial.initial.status || 'active',
        runs: initial.initial.runs,
        loading: { run: false, schedule: false, retry: false },
        toast: { message: '', type: 'success' },
        notify(message, type = 'success') {
            this.toast = { message, type };
            setTimeout(() => (this.toast.message = ''), 4000);
        },
        async request(url, method = 'POST', body = null) {
            const opts = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            };
            if (body) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
            const res = await fetch(url, opts);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        },
        async runNow() {
            this.loading.run = true;
            try {
                const data = await this.request(this.urls.run, 'POST');
                if (data.run) {
                    this.runs.unshift({
                        id: data.run.id,
                        status: data.run.status,
                        attempt: data.run.attempt,
                        started_at: 'just now',
                        finished_at: 'just now',
                        output: data.run.output_log || '—',
                    });
                }
                this.notify(data.message || 'Job ran.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.run = false;
            }
        },
        async saveSchedule() {
            this.loading.schedule = true;
            try {
                const data = await this.request(this.urls.schedule, 'PATCH', {
                    schedule_label: this.schedule_label,
                    schedule_cron: this.schedule_cron,
                    status: this.status,
                });
                this.notify(data.message || 'Schedule saved.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.schedule = false;
            }
        },
        async retryFailed() {
            if (!confirm('Retry all failed jobs for this tenant?')) return;
            this.loading.retry = true;
            try {
                const data = await this.request(this.urls.retryFailed, 'POST');
                this.notify(data.message || 'Failed jobs retried.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.retry = false;
            }
        },
    };
}
</script>
@endsection
