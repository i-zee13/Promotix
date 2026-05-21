@extends('layouts.admin')

@section('title', 'Paid Advertising | Advanced View')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="paidMarketingDetailed()" x-init="init()">
    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[20px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Advanced View</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Campaigns</span>
                    <select x-model="filters.platform" @change="scheduleFetch()" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option value="">All campaigns</option>
                        @foreach ($platforms as $platform)
                            <option value="{{ $platform }}">{{ $platform }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Filter by path</span>
                    <input x-model="filters.path" @input="scheduleFetch()" placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
                <div class="figma-filter-action flex w-[34px] items-center justify-center bg-[#6400B2] text-white" aria-hidden="true">
                    <svg class="h-[18px] w-[18px] animate-spin text-white/80" x-show="loading" x-cloak fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg class="h-[18px] w-[18px]" x-show="!loading" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="rounded-[10px] border border-white/40 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.35)]">
            <div class="grid gap-[16px] lg:grid-cols-[1fr_244px]">
                <div class="flex min-h-[91px] flex-col justify-between">
                    <h2 class="text-[20px] font-normal text-[#a9a9a9]">Paid Traffic Trends</h2>
                    <button type="button" @click="window.print()" class="flex w-fit items-center gap-[5px] text-[15px] text-white hover:underline">
                        <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 3v12m0 0l4-4m-4 4l-4-4M4 19h16"/></svg>
                        Download
                    </button>
                </div>

                <div class="space-y-[12px]">
                    <label class="flex h-[26px] items-center rounded-[5px] border border-white/30 bg-[#0f0e0e] px-[10px]">
                        <svg class="mr-[9px] h-[15px] w-[15px] text-[#9d9898]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.ip" @input="scheduleFetch()" placeholder="Filter by IP" class="h-full flex-1 border-0 bg-transparent p-0 text-[14px] font-light text-[#9d9898] placeholder:text-[#9d9898] focus:ring-0">
                    </label>
                    <button type="button" @click="filtersOpen = ! filtersOpen" class="flex h-[49px] w-full items-center rounded-[5px] border border-white/30 bg-[#0f0e0e] px-[13px] text-left text-[14px] text-[#9d9898]">
                        <svg class="mr-[12px] h-[22px] w-[22px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linejoin="round" stroke-width="1.7" d="M4 5h16l-6 7v5l-4 2v-7L4 5z"/></svg>
                        <span>Advanced<br>Filters</span>
                    </button>
                </div>
            </div>

            <div x-show="filtersOpen" x-cloak class="mt-[14px] grid gap-[10px] rounded-[8px] bg-black/20 p-[10px] sm:grid-cols-2">
                <label class="text-[11px] text-white/70">From
                    <input type="date" x-model="filters.from" @change="scheduleFetch()" class="mt-[4px] h-[32px] w-full rounded-[4px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                </label>
                <label class="text-[11px] text-white/70">To
                    <input type="date" x-model="filters.to" @change="scheduleFetch()" class="mt-[4px] h-[32px] w-full rounded-[4px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                </label>
                <div class="flex gap-[8px] sm:col-span-2 sm:justify-end">
                    <button type="button" @click="clearFilters()" class="rounded-[4px] border border-white/30 px-[12px] py-[7px] text-[12px] text-white/80">Clear</button>
                </div>
            </div>
        </div>

        <section class="mt-[8px] overflow-hidden">
            <div class="overflow-x-auto">
                <div class="min-w-[895px]">
                    <div class="figma-data-grid-header grid grid-cols-[22px_115px_70px_135px_112px_120px_118px_1fr] items-center border-b border-white px-[14px] py-[9px] text-[13px] text-white">
                        <span></span>
                        <span>IP Address</span>
                        <span>Visits</span>
                        <span>Campaigns</span>
                        <span>Last Click</span>
                        <span>Threat Group</span>
                        <span>Threat Type</span>
                        <span>Country</span>
                    </div>

                    <div class="max-h-[318px] overflow-y-auto pr-[6px]">
                        <template x-for="visit in rows" :key="visit.id">
                            <button type="button" class="figma-data-row mt-[6px] grid h-[47px] w-full grid-cols-[22px_115px_70px_135px_112px_120px_118px_1fr] items-center rounded-[10px] border-[3px] border-white/40 bg-[#151515] px-[11px] text-left text-[15px] text-[#a9a9a9] transition hover:border-white/70" @click="openClicks(visit)">
                                <span class="text-white/90">&gt;</span>
                                <span x-text="visit.ip"></span>
                                <span x-text="visit.visits"></span>
                                <span x-text="visit.campaign || 'N/A'"></span>
                                <span class="text-[#8d8d8d]" x-text="visit.last_click_label"></span>
                                <span class="text-[#8d8d8d]" x-text="visit.threat_group || '—'"></span>
                                <span class="text-[#8d8d8d]" x-text="visit.threat_type || '—'"></span>
                                <span class="text-[#8d8d8d]" x-text="visit.country || '—'"></span>
                            </button>
                        </template>
                        <div x-show="!loading && rows.length === 0" class="mt-[6px] rounded-[10px] border-[3px] border-white/40 px-[14px] py-[28px] text-center text-[15px] text-[#a9a9a9]">No rows match your filters.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-[20px]">
            <h2 class="mx-auto mb-[18px] flex h-[36px] w-[184px] items-center justify-center rounded-[4px] bg-[#6706B3] text-[24px] font-semibold text-[#a9a9a9]">Paid Stats</h2>
            <div class="grid grid-cols-2 gap-[14px] sm:grid-cols-3 xl:grid-cols-6">
                <template x-for="card in statCards" :key="card.label">
                    <article class="relative h-[228px] overflow-hidden rounded-[10px] border border-white/40 bg-[#6400B2]">
                        <div class="absolute inset-x-0 bottom-0 rounded-t-[10px]" :class="card.fillClass + ' ' + card.toneClass"></div>
                        <div class="relative z-10 pt-[31px] text-center">
                            <p class="mb-[26px] text-[14px] text-[#a9a9a9]" x-text="card.label"></p>
                            <p class="text-[36px] font-medium leading-none text-white" x-text="card.value + '%'"></p>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <div class="brand-modal-overlay"
             x-show="modal.open" x-cloak x-transition
             @keydown.escape.window="closeModal()" @click.self="closeModal()">
            <div class="brand-modal max-w-5xl">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="brand-modal-title">Click Details</h3>
                    <button type="button" class="rounded-lg p-1.5 text-night-300 hover:bg-night-800 hover:text-white" @click="closeModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="grid grid-cols-1 gap-0 lg:grid-cols-4">
                    <aside class="border-b border-night-700/60 p-2 lg:border-b-0 lg:border-r lg:pr-4">
                        <template x-for="(c, idx) in modal.clicks" :key="c.id ?? idx">
                            <button type="button"
                                    class="mb-2 w-full rounded-xl border border-night-700 bg-night-900 px-3 py-2 text-left transition hover:bg-night-800"
                                    :class="idx === modal.activeIndex ? 'ring-2 ring-brand-400' : ''"
                                    @click="modal.activeIndex = idx">
                                <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                                <p class="text-xs text-night-300" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            </button>
                        </template>
                        <template x-if="modal.clicks.length === 0">
                            <p class="text-sm text-night-300">No clicks for this visit.</p>
                        </template>
                    </aside>

                    <div class="p-4 lg:col-span-3 lg:pl-6" x-show="modal.clicks.length > 0">
                        <template x-if="activeClick">
                            <div class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
                                <div><p class="text-xs uppercase tracking-wider text-night-400">IP</p><p class="mt-1 text-sm text-white" x-text="activeClick.ip || modal.visit?.ip || '-'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Browser</p><p class="mt-1 text-sm text-white" x-text="activeClick.browser_name || '-'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Country</p><p class="mt-1 text-sm text-white" x-text="activeClick.country || modal.visit?.country || '-'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Browser version</p><p class="mt-1 text-sm text-white" x-text="activeClick.browser_version || '-'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Last Click</p><p class="mt-1 text-sm text-white" x-text="formatDateTime(activeClick.last_click_at || modal.visit?.last_click_at)"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">OS</p><p class="mt-1 text-sm text-white" x-text="activeClick.os || '-'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Threat Group</p><p class="mt-1 text-sm text-white" x-text="activeClick.threat_group || modal.visit?.threat_group || 'N/A'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Paid ID</p><p class="mt-1 text-sm text-white" x-text="activeClick.paid_id || '-'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Campaign</p><p class="mt-1 text-sm text-white" x-text="activeClick.campaign || modal.visit?.campaign || 'N/A'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Path</p><p class="mt-1 text-sm text-white" x-text="activeClick.path || modal.visit?.last_path || '-'"></p></div>
                                <div><p class="text-xs uppercase tracking-wider text-night-400">Keyword</p><p class="mt-1 text-sm text-white" x-text="activeClick.keyword || 'N/A'"></p></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    function paidMarketingDetailed() {
        return {
            debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
            fetchTimer: null,
            loading: false,
            filtersOpen: false,
            filters: { ip: '', path: '', platform: '', from: '', to: '' },
            rows: [],
            statCards: [],
            modal: { open: false, visit: null, clicks: [], activeIndex: 0 },
            get activeClick() { return this.modal.clicks[this.modal.activeIndex] || null; },
            init() {
                this.syncHeaderDates();
                this.fetchNow();
                window.addEventListener('promotix:date-range', () => {
                    this.syncHeaderDates();
                    this.scheduleFetch();
                });
            },
            syncHeaderDates() {
                try {
                    const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
                    if (r.from) this.filters.from = r.from;
                    if (r.to) this.filters.to = r.to;
                } catch (e) {}
            },
            scheduleFetch() {
                clearTimeout(this.fetchTimer);
                this.fetchTimer = setTimeout(() => this.fetchNow(), this.debounceMs);
            },
            queryString() {
                const p = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (v !== '' && v != null) p.set(k, v);
                });
                return p.toString();
            },
            async fetchNow() {
                this.loading = true;
                try {
                    const qs = this.queryString();
                    const res = await fetch(`{{ route('paid-marketing.detailed-visits') }}${qs ? '?' + qs : ''}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('fetch failed');
                    const data = await res.json();
                    this.rows = data.rows || [];
                    this.statCards = data.stats?.cards || [];
                } catch (e) {
                    console.error(e);
                } finally {
                    this.loading = false;
                }
            },
            clearFilters() {
                this.filters = { ip: '', path: '', platform: '', from: '', to: '' };
                this.syncHeaderDates();
                this.fetchNow();
            },
            openClicks(visit) {
                this.modal.visit = visit;
                this.modal.clicks = (visit.clicks || []).slice();
                this.modal.activeIndex = 0;
                this.modal.open = true;
            },
            closeModal() {
                this.modal.open = false;
                this.modal.visit = null;
                this.modal.clicks = [];
                this.modal.activeIndex = 0;
            },
            formatDateTime(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return String(value);
                return date.toLocaleString();
            },
        };
    }
</script>
@endsection
