@extends('layouts.super-admin')

@section('title', 'Automation')

@section('content')
@php
    $jobPayload = $jobs->map(fn ($job) => [
        'id' => $job->id,
        'name' => $job->name,
        'slug' => $job->slug,
        'description' => $job->description,
        'schedule' => $job->schedule_label ?? $job->schedule_cron ?? 'Manual',
        'status' => $job->status,
        'queue_badge' => $job->status === 'active' ? 'Queue Healthy' : 'Queue Paused',
        'last_run' => $job->last_ran_at?->diffForHumans() ?? 'Never run',
        'runs_count' => $job->runs_count,
        'next_run' => $job->next_run_at?->diffForHumans() ?? '—',
        'href' => route('automation.show', $job),
        'icon' => str_contains($job->slug, 'google') ? 'google' : (str_contains($job->slug, 'delete') ? 'trash' : (str_contains($job->slug, 'retry') ? 'refresh' : (str_contains($job->slug, 'suspend') ? 'warning' : (str_contains($job->slug, 'key') ? 'key' : 'exclamation')))),
    ])->values();
    $total = $jobs->count();
@endphp

<x-super-admin.page title="Automation">
    <div class="space-y-[16px]"
        x-data="superAdminAutomation({
            jobs: @js($jobPayload),
            csrf: '{{ csrf_token() }}',
            scheduleUrl: '{{ url('api/admin/jobs') }}',
        })">

        <div class="figma-sa-users-toolbar">
            <div class="figma-sa-users-search-wrap flex-1">
                <svg class="figma-sa-users-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" placeholder="Search automations" class="figma-sa-users-search-input" x-model="query">
            </div>
        </div>

        <template x-if="toast">
            <div class="figma-sa-msg border-emerald-500/30 bg-emerald-500/10 text-emerald-200" x-text="toast"></div>
        </template>

        <div class="space-y-[14px]">
            <template x-for="job in filteredJobs" :key="job.id">
                <div class="figma-sa-automation-row">
                    <div class="figma-sa-automation-card">
                        <span class="figma-sa-automation-icon" aria-hidden="true">
                            <svg x-show="job.icon === 'trash'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-8 0v12a2 2 0 002 2h6a2 2 0 002-2V7"/></svg>
                            <svg x-show="job.icon === 'refresh'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-5M20 15a8 8 0 01-14 5"/></svg>
                            <svg x-show="job.icon === 'warning'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86l-8.18 14.18A1.5 1.5 0 003.5 20.5h17a1.5 1.5 0 001.39-2.46L13.7 3.86a1.5 1.5 0 00-2.42 0z"/></svg>
                            <svg x-show="job.icon === 'key'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a4 4 0 11-4 4M11 11L3 19v2h2l8-8"/></svg>
                            <svg x-show="job.icon === 'google'" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10c5.52 0 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                            <svg x-show="job.icon === 'exclamation'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M12 21a9 9 0 100-18 9 9 0 000 18z"/></svg>
                        </span>
                        <div class="figma-sa-automation-body">
                            <h3 class="figma-sa-automation-title" x-text="job.name"></h3>
                            <p class="figma-sa-automation-desc" x-text="job.description"></p>
                            <div class="figma-sa-automation-meta">
                                <span class="figma-sa-automation-schedule" x-text="job.schedule"></span>
                                <span class="figma-sa-automation-queue-pill" :class="{ 'is-paused': job.status !== 'active' }" x-text="job.queue_badge"></span>
                            </div>
                        </div>
                        <div class="figma-sa-automation-side">
                            <button type="button" class="figma-sa-automation-toggle" :class="{ 'is-on': job.status === 'active' }" @click="toggleJob(job)" :aria-pressed="job.status === 'active'" aria-label="Toggle automation">
                                <span class="figma-sa-automation-toggle-dot"></span>
                            </button>
                            <span class="figma-sa-automation-status" :class="{ 'is-paused': job.status !== 'active' }" x-text="job.status === 'active' ? 'Active' : 'Paused'"></span>
                        </div>
                    </div>
                    <div class="figma-sa-automation-stat">
                        <p class="figma-sa-automation-stat-label">Last run</p>
                        <div class="figma-sa-automation-stat-badges">
                            <span class="figma-sa-automation-stat-badge" x-text="job.last_run"></span>
                            <span class="figma-sa-automation-stat-badge" x-text="job.runs_count + ' runs'"></span>
                        </div>
                    </div>
                    <div class="figma-sa-automation-stat">
                        <p class="figma-sa-automation-stat-label">Next run</p>
                        <p class="figma-sa-automation-stat-value" x-text="job.next_run"></p>
                        <a :href="job.href" class="figma-sa-automation-view-btn">View</a>
                    </div>
                </div>
            </template>
        </div>

        <p class="text-[13px] text-[#8c8787]">Showing {{ $total ? 1 : 0 }}–{{ $total }} of {{ $total }}</p>
    </div>
</x-super-admin.page>

<script>
function superAdminAutomation(initial) {
    return {
        jobs: initial.jobs,
        query: '',
        toast: '',
        csrf: initial.csrf,
        scheduleUrl: initial.scheduleUrl,
        get filteredJobs() {
            const q = this.query.trim().toLowerCase();
            if (!q) return this.jobs;
            return this.jobs.filter((j) =>
                j.name.toLowerCase().includes(q) || j.description.toLowerCase().includes(q)
            );
        },
        async toggleJob(job) {
            const status = job.status === 'active' ? 'paused' : 'active';
            try {
                const res = await fetch(`${this.scheduleUrl}/${job.id}/schedule`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({ status }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Could not update job');
                job.status = status;
                job.queue_badge = status === 'active' ? 'Queue Healthy' : 'Queue Paused';
                this.toast = 'Automation updated.';
                setTimeout(() => (this.toast = ''), 3000);
            } catch (e) {
                this.toast = e.message;
                setTimeout(() => (this.toast = ''), 4000);
            }
        },
    };
}
</script>
@endsection
