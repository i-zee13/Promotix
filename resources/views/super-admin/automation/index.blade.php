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
        'icon' => str_contains($job->slug, 'google') ? 'google' : (str_contains($job->slug, 'delete') ? 'trash' : (str_contains($job->slug, 'retry') ? 'refresh' : 'exclamation')),
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
                <div class="grid grid-cols-1 gap-[14px] xl:grid-cols-12">
                    <article class="figma-sa-dash-kpi xl:col-span-7 !min-h-0 p-[18px]">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex gap-4">
                                <span class="flex h-[48px] w-[48px] shrink-0 items-center justify-center rounded-[8px] bg-[#6400B2] text-white text-lg font-bold">
                                    <span x-show="job.icon === 'google'">G</span>
                                    <span x-show="job.icon === 'trash'">🗑</span>
                                    <span x-show="job.icon === 'refresh'">↻</span>
                                    <span x-show="job.icon === 'exclamation'">!</span>
                                </span>
                                <div>
                                    <h3 class="text-[18px] font-medium text-[#d9d9d9]" x-text="job.name"></h3>
                                    <p class="mt-1 text-[14px] text-[#8c8787]" x-text="job.description"></p>
                                    <p class="mt-2 text-[13px] text-[#a9a9a9]" x-text="job.schedule"></p>
                                    <span class="figma-sa-pill figma-sa-pill-purple mt-2 inline-flex" x-text="job.queue_badge"></span>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <button type="button" class="relative inline-flex h-6 w-11 rounded-full transition"
                                    :class="job.status === 'active' ? 'bg-[#6400B2]' : 'bg-[#5c5c5c]'"
                                    @click="toggleJob(job)"
                                    :aria-pressed="job.status === 'active'">
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow transition"
                                        :class="job.status === 'active' ? 'translate-x-6' : 'translate-x-1'"></span>
                                </button>
                                <span class="figma-sa-pill"
                                    :class="job.status === 'active' ? 'figma-sa-pill-success' : 'figma-sa-pill-warning'"
                                    x-text="job.status === 'active' ? 'Active' : 'Paused'"></span>
                            </div>
                        </div>
                    </article>
                    <article class="figma-sa-dash-kpi xl:col-span-3 !min-h-0 p-[16px]">
                        <p class="figma-sa-label">Last run</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="figma-sa-pill figma-sa-pill-neutral" x-text="job.last_run"></span>
                            <span class="figma-sa-pill figma-sa-pill-neutral" x-text="job.runs_count + ' runs'"></span>
                        </div>
                    </article>
                    <article class="figma-sa-dash-kpi xl:col-span-2 !min-h-0 p-[16px]">
                        <p class="figma-sa-label">Next run</p>
                        <p class="mt-2 text-[14px] text-[#d9d9d9]" x-text="job.next_run"></p>
                        <a :href="job.href" class="figma-sa-btn figma-sa-btn-outline mt-3 w-full text-xs">View</a>
                    </article>
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
