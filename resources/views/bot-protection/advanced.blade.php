@extends('layouts.admin')

@section('title', 'Bot Protection | Advanced View')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="botProtectionAdvancedFigma()" x-init="init()">
    <section class="mx-auto w-full px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[18px] flex flex-col gap-[14px] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Bot Protection</h1>
                <span class="hidden h-[34px] w-[2px] bg-[#a9a9a9] sm:block sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Advanced View</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Campaigns</span>
                    <select x-model="filters.domain_id" @change="reload(true)" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option value="">All campaigns</option>
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Filter by path</span>
                    <input x-model.debounce.350ms="filters.path" @input="reload(true)" placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
                <button type="button" @click="openDatePicker()" class="figma-filter-action flex w-[34px] shrink-0 items-center justify-center bg-[#6400B2] text-white" aria-label="Date range">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M4 11h16M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                </button>
            </div>
        </div>

        <input type="date" x-ref="fromPicker" x-model="filters.from" @change="reload(true)" class="sr-only" tabindex="-1" aria-hidden="true">
        <input type="date" x-ref="toPicker" x-model="filters.to" @change="reload(true)" class="sr-only" tabindex="-1" aria-hidden="true">

        <section class="overflow-hidden rounded-[12px] border border-[#6706b3]">
            <div class="flex flex-wrap items-center justify-between gap-[10px] bg-[#6400B2] px-[16px] py-[12px]">
                <h2 class="text-[18px] font-normal text-white sm:text-[20px]">Advanced View</h2>
                <div class="flex flex-1 flex-wrap items-center justify-end gap-[10px]">
                    <label class="relative flex h-[28px] min-w-[200px] max-w-[280px] flex-1 items-center rounded-[6px] bg-white px-[10px]">
                        <svg class="mr-[6px] h-[14px] w-[14px] shrink-0 text-[#8c8787]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" placeholder="Search for IP Address" x-model="filters.ip" @keydown.enter="reload(true)" @input.debounce.400ms="reload(true)" class="w-full border-0 bg-transparent text-[11px] text-[#121212] placeholder:text-[#8c8787] focus:ring-0">
                    </label>
                    <a :href="csvHref()" class="inline-flex items-center gap-[6px] text-[12px] font-medium text-white hover:underline">
                        <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v-1a4 4 0 014-4h0a4 4 0 014 4v1"/></svg>
                        Download
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-[minmax(100px,1fr)_minmax(110px,1fr)_minmax(100px,1fr)_minmax(90px,1fr)_minmax(90px,1fr)_minmax(90px,1fr)_minmax(120px,1.2fr)] gap-[8px] bg-[#1a1a1a] px-[12px] py-[10px] text-[10px] font-medium uppercase tracking-wide text-[#a9a9a9] sm:text-[11px]">
                <span>IP Address</span>
                <span>Last Seen</span>
                <span>Threat Group</span>
                <span>Threat Type</span>
                <span>Action Taken</span>
                <span>Country</span>
                <span>Domain Url</span>
            </div>

            <div class="max-h-[420px] overflow-y-auto bp-adv-scroll px-[10px] py-[8px]">
                <template x-for="row in rows" :key="row.id">
                    <div class="mb-[8px] grid grid-cols-[minmax(100px,1fr)_minmax(110px,1fr)_minmax(100px,1fr)_minmax(90px,1fr)_minmax(90px,1fr)_minmax(90px,1fr)_minmax(120px,1.2fr)] gap-[8px] rounded-[10px] bg-[#d9d9d9] px-[12px] py-[10px] text-[10px] text-[#121212] sm:text-[11px]">
                        <span class="truncate font-medium" x-text="row.ip"></span>
                        <span class="truncate" x-text="row.visited_at"></span>
                        <span class="truncate" x-text="row.threat_group_label"></span>
                        <span class="truncate" x-text="row.threat_type_label"></span>
                        <span class="truncate capitalize" x-text="row.action_taken"></span>
                        <span class="truncate" x-text="row.country_label"></span>
                        <span class="truncate" x-text="row.domain_url || row.hostname || 'â€”'"></span>
                    </div>
                </template>
                <p x-show="rows.length === 0" class="py-[24px] text-center text-[12px] text-[#a9a9a9]">No matching visits in this window.</p>
            </div>

            <div class="flex items-center justify-between border-t border-[#6706b3]/40 px-[14px] py-[10px] text-[10px] text-[#a9a9a9]">
                <span x-text="`${rows.length ? ((meta.page - 1) * meta.per_page + 1) : 0}â€“${Math.min(meta.total, meta.page * meta.per_page)} of ${meta.total}`"></span>
                <div class="flex gap-[8px]">
                    <button type="button" class="rounded-[6px] border border-[#6706b3] px-[12px] py-[4px] text-[10px] text-white disabled:opacity-40" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">Prev</button>
                    <button type="button" class="rounded-[6px] border border-[#6706b3] px-[12px] py-[4px] text-[10px] text-white disabled:opacity-40" :disabled="meta.page * meta.per_page >= meta.total" @click="changePage(meta.page + 1)">Next</button>
                </div>
            </div>
        </section>

        <section class="mt-[28px]">
            <h2 class="mb-[20px] text-center text-[24px] font-semibold leading-none text-[#a9a9a9]">Bot Stats</h2>
            <div class="grid grid-cols-2 gap-x-[14px] gap-y-[28px] sm:grid-cols-3 xl:grid-cols-6">
                <template x-for="stat in statCards" :key="stat.key">
                    <div class="flex flex-col items-center">
                        <p class="mb-[10px] w-full text-center text-[14px] leading-tight text-[#a9a9a9]" x-text="stat.label"></p>
                        <article class="relative h-[228px] w-full max-w-[150px] overflow-hidden rounded-[10px] border border-white/40 bg-[#6400B2] shadow-[inset_0_1px_0_rgba(255,255,255,.08)]">
                            <div
                                x-show="stat.value > 0"
                                class="absolute inset-x-0 bottom-0 rounded-[10px] border border-white/40 transition-all duration-500 ease-out"
                                :class="stat.value >= 40 ? 'bg-[#9a1aff]' : 'bg-[#ffffff8f]'"
                                :style="`height: ${Math.min(100, Math.max(stat.value, 2))}%`"
                            ></div>
                            <span class="absolute inset-0 z-10 flex items-center justify-center text-[36px] font-medium leading-[43px] text-white" x-text="stat.value + '%'"></span>
                        </article>
                    </div>
                </template>
            </div>
        </section>

        <details class="mt-[16px] rounded-[10px] border border-white/10 bg-[#151515] p-[12px] text-[11px] text-[#a9a9a9]">
            <summary class="cursor-pointer font-medium text-white">More filters</summary>
            <div class="mt-[12px] grid grid-cols-1 gap-[10px] sm:grid-cols-2 lg:grid-cols-4">
                <label class="block">
                    <span class="mb-[4px] block text-[10px] uppercase">Country</span>
                    <input type="text" maxlength="2" placeholder="US" x-model="filters.country" @keydown.enter="reload(true)" class="h-[32px] w-full rounded-[6px] border border-white/20 bg-[#101010] px-[10px] text-white uppercase">
                </label>
                <label class="block">
                    <span class="mb-[4px] block text-[10px] uppercase">Action</span>
                    <select x-model="filters.action" @change="reload(true)" class="h-[32px] w-full rounded-[6px] border border-white/20 bg-[#101010] px-[10px] text-white">
                        <option value="">All</option>
                        <option value="allow">Allow</option>
                        <option value="flag">Flag</option>
                        <option value="block">Block</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-[4px] block text-[10px] uppercase">Threat group</span>
                    <select x-model="filters.threat_group" @change="reload(true)" class="h-[32px] w-full rounded-[6px] border border-white/20 bg-[#101010] px-[10px] text-white">
                        <option value="">All</option>
                        <option value="data_center">Data center</option>
                        <option value="vpn">VPN</option>
                        <option value="malicious">Malicious</option>
                        <option value="abnormal_rate_limit">Abnormal rate limit</option>
                        <option value="out_of_geo">Out of geo</option>
                    </select>
                </label>
                <div class="flex flex-col justify-end gap-[6px]">
                    <label class="inline-flex items-center gap-[8px] text-white">
                        <input type="checkbox" x-model="filters.only_invalid" @change="reload(true)" class="rounded border-white/30 bg-[#101010] text-[#6400B2]">
                        Only invalid
                    </label>
                    <label class="inline-flex items-center gap-[8px] text-white">
                        <input type="checkbox" x-model="filters.only_paid" @change="reload(true)" class="rounded border-white/30 bg-[#101010] text-[#6400B2]">
                        Only paid
                    </label>
                </div>
            </div>
        </details>

        <p class="mt-[12px] text-right">
            <a href="{{ route('bot-protection.dashboard') }}" class="text-[11px] text-[#a9a9a9] hover:text-white hover:underline">â† Back to Dashboard</a>
        </p>
    </section>
<style>
.bp-adv-scroll { scrollbar-width: thin; scrollbar-color: #6400B2 transparent; }
.bp-adv-scroll::-webkit-scrollbar { width: 5px; }
.bp-adv-scroll::-webkit-scrollbar-thumb { background: #6400B2; border-radius: 4px; }
</style>

<script>
function botProtectionAdvancedFigma() {
    return {
        filters: {
            domain_id: '', path: '', ip: '', country: '', action: '', threat_group: '',
            only_invalid: false, only_paid: false, from: '', to: '',
        },
        rows: [],
        meta: { total: 0, page: 1, per_page: 25 },
        stats: { blocked: 0, invalid_traffic: 0, paid_traffic: 0, bot_detection: 0, country: 0, overall: 0 },
        get statCards() {
            return [
                { key: 'blocked', label: 'Blocked', value: this.stats.blocked ?? 0 },
                { key: 'invalid_traffic', label: 'Invalid Traffic', value: this.stats.invalid_traffic ?? 0 },
                { key: 'paid_traffic', label: 'Paid Traffic', value: this.stats.paid_traffic ?? 0 },
                { key: 'bot_detection', label: 'Bot Detection', value: this.stats.bot_detection ?? 0 },
                { key: 'country', label: 'Country', value: this.stats.country ?? 0 },
                { key: 'overall', label: 'Overall', value: this.stats.overall ?? 0 },
            ];
        },
        qs(extra = {}) {
            const p = new URLSearchParams();
            Object.entries({ ...this.filters, ...extra }).forEach(([k, v]) => {
                if (v === false || v === '' || v === null || v === undefined) return;
                p.set(k, v === true ? '1' : v);
            });
            return p.toString();
        },
        csvHref() { return `/bot-protection/export.csv?${this.qs()}`; },
        openDatePicker() {
            this.$refs.fromPicker?.showPicker?.() || this.$refs.fromPicker?.click();
        },
        async init() {
            const today = new Date();
            const start = new Date(today.getTime() - 6 * 86400000);
            this.filters.from = start.toISOString().slice(0, 10);
            this.filters.to = today.toISOString().slice(0, 10);
            await this.reload(true);
        },
        async reload(resetPage = false) {
            if (resetPage) this.meta.page = 1;
            const qs = this.qs({ page: this.meta.page, per_page: this.meta.per_page });
            const [visits, stats] = await Promise.all([
                fetch(`/bot-protection/visits?${qs}`).then(r => r.json()),
                fetch(`/bot-protection/bot-stats?${this.qs()}`).then(r => r.json()),
            ]);
            this.rows = visits.data || [];
            this.meta = { ...this.meta, ...(visits.meta || {}) };
            this.stats = stats;
        },
        async changePage(p) {
            this.meta.page = Math.max(1, p);
            await this.reload(false);
        },
    };
}
</script>
@endsection

