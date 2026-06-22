@extends('layouts.admin')

@section('title', 'Bot Protection | Advanced View')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="botProtectionAdvancedFigma()" x-init="init()">
    <section class="mx-auto w-full px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[10px] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-[8px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Bot Protection</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Advanced View</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                <label class="flex min-w-0 flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domains</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.domain_id" @change="reload(true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All domains</option>
                            @foreach ($domains as $d)
                                <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[178px] shrink-0 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleReload(true)" placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        <section class="overflow-visible rounded-[12px] border border-[#6706b3]">
            <div class="flex flex-wrap items-center justify-between gap-[10px] overflow-visible rounded-t-[12px] bg-[#6400B2] px-[16px] py-[12px]">
                <h2 class="text-[18px] font-normal text-white sm:text-[20px]">Advanced View</h2>
                <div class="flex flex-1 flex-wrap items-center justify-end gap-[10px]">
                    <div class="relative" @click.outside="filterMenuOpen = false">
                        <button type="button" @click="filterMenuOpen = !filterMenuOpen" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            Advanced Filter
                            <span class="rounded-[3px] bg-white/15 px-[5px] text-[10px]" x-text="visibleColumns.length"></span>
                        </button>
                        <div x-show="filterMenuOpen" x-cloak class="paid-advanced-columns-menu promotix-slim-scroll">
                            <p class="mb-[8px] text-[10px] font-semibold uppercase text-white/55">Primary columns</p>
                            <template x-for="col in columnCatalog.filter(c => c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option is-locked">
                                    <input type="checkbox" checked disabled>
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                            <p class="mb-[8px] mt-[10px] text-[10px] font-semibold uppercase text-white/55">Optional columns</p>
                            <template x-for="col in columnCatalog.filter(c => !c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option">
                                    <input type="checkbox" :value="col.key" :checked="optionalColumnKeys.includes(col.key)" @change="toggleOptionalColumn(col.key)">
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="relative" @click.outside="moreFiltersOpen = false">
                        <button type="button" @click="moreFiltersOpen = !moreFiltersOpen" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            More filters
                            <svg class="h-[12px] w-[12px] transition-transform" :class="moreFiltersOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="moreFiltersOpen" x-cloak x-transition class="bp-adv-filters-menu promotix-slim-scroll">
                            <div class="grid grid-cols-1 gap-[10px] sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-[4px] block text-[10px] uppercase text-white/70">Country</span>
                                    <input type="text" maxlength="2" placeholder="US" x-model="filters.country" @input="scheduleReload(true)" class="h-[32px] w-full rounded-[6px] border border-white/20 bg-[#101010] px-[10px] text-white uppercase">
                                </label>
                                <label class="block">
                                    <span class="mb-[4px] block text-[10px] uppercase text-white/70">Action</span>
                                    <select x-model="filters.action" @change="reload(true)" class="figma-panel-select w-full">
                                        <option value="">All</option>
                                        <option value="allow">Allow</option>
                                        <option value="flag">Flag</option>
                                        <option value="block">Block</option>
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="mb-[4px] block text-[10px] uppercase text-white/70">Threat group</span>
                                    <select x-model="filters.threat_group" @change="reload(true)" class="figma-panel-select w-full">
                                        <option value="">All</option>
                                        <option value="data_center">Data center</option>
                                        <option value="vpn">VPN</option>
                                        <option value="malicious">Malicious</option>
                                        <option value="abnormal_rate_limit">Abnormal rate limit</option>
                                        <option value="out_of_geo">Out of geo</option>
                                    </select>
                                </label>
                                <label class="inline-flex items-center gap-[8px] text-[11px] text-white">
                                    <x-figma-toggle x-model="filters.only_invalid" @change="reload(true)" :show-labels="false" />
                                    Only invalid
                                </label>
                                <label class="inline-flex items-center gap-[8px] text-[11px] text-white">
                                    <x-figma-toggle x-model="filters.only_paid" @change="reload(true)" :show-labels="false" />
                                    Only paid
                                </label>
                            </div>
                        </div>
                    </div>
                    <label class="relative flex h-[28px] min-w-[200px] max-w-[280px] flex-1 items-center rounded-[6px] bg-white px-[10px]">
                        <svg class="mr-[6px] h-[14px] w-[14px] shrink-0 text-[#8c8787]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" placeholder="Search for IP Address" x-model="filters.ip" @input="scheduleReload(true)" class="w-full border-0 bg-transparent text-[11px] text-[#121212] placeholder:text-[#8c8787] focus:ring-0">
                    </label>
                    <a :href="csvHref()" class="inline-flex items-center gap-[6px] text-[12px] font-medium text-white hover:underline">
                        <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v-1a4 4 0 014-4h0a4 4 0 014 4v1"/></svg>
                        Download CSV
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="min-w-max">
                    <div class="grid gap-[8px] bg-[#1a1a1a] px-[12px] py-[10px] text-[10px] font-medium uppercase tracking-wide text-[#a9a9a9] sm:text-[11px]" :style="gridStyle">
                        <template x-for="col in visibleColumns" :key="'head-' + col.key">
                            <span class="truncate" x-text="col.label"></span>
                        </template>
                    </div>

                    <div class="max-h-[420px] overflow-y-auto bp-adv-scroll px-[10px] py-[8px]">
                        <template x-for="row in rows" :key="row.id">
                            <div class="mb-[8px] grid gap-[8px] rounded-[10px] bg-[#d9d9d9] px-[12px] py-[10px] text-[10px] text-[#121212] sm:text-[11px]" :style="gridStyle">
                                <template x-for="col in visibleColumns" :key="row.id + '-' + col.key">
                                    <template x-if="col.key !== 'session_recording'">
                                        <span class="truncate" :class="col.key === 'ip' && 'font-medium'" :title="cellValue(row, col.key)" x-text="cellValue(row, col.key)"></span>
                                    </template>
                                    <template x-if="col.key === 'session_recording'">
                                        <span class="flex items-center justify-center">
                                            <button type="button" x-show="row.has_session_recording" @click.stop="openRecording(row)" class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-full bg-[#6400B2] text-white hover:bg-[#7B13C8]" title="Watch session recording">
                                                <svg class="h-[11px] w-[11px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </button>
                                            <span x-show="!row.has_session_recording" class="text-[#8c8787]">—</span>
                                        </span>
                                    </template>
                                </template>
                            </div>
                        </template>
                        <p x-show="rows.length === 0" class="py-[24px] text-center text-[12px] text-[#a9a9a9]">No matching visits in this window.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-[#6706b3]/40 px-[14px] py-[10px] text-[10px] text-[#a9a9a9]">
                <span x-text="paginationLabel()"></span>
                <div class="flex gap-[8px]">
                    <button type="button" class="rounded-[6px] border border-[#6706b3] px-[12px] py-[4px] text-[10px] text-white disabled:opacity-40" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">Prev</button>
                    <button type="button" class="rounded-[6px] border border-[#6706b3] px-[12px] py-[4px] text-[10px] text-white disabled:opacity-40" :disabled="meta.page * meta.per_page >= meta.total" @click="changePage(meta.page + 1)">Next</button>
                </div>
            </div>
        </section>

        <div class="figma-modal-overlay"
             x-show="recordingModal.open" x-cloak x-transition
             @keydown.escape.window="closeRecording()" @click.self="closeRecording()">
            <div class="figma-modal max-w-[640px]">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="figma-modal-title">Session Recording</h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeRecording()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>
                <p class="mb-3 text-[12px] text-white/70" x-text="recordingModal.ip ? `IP: ${recordingModal.ip}` : ''"></p>
                <div class="overflow-hidden rounded-[8px] border border-white/20 bg-[#101010]">
                    <canvas x-ref="recordingCanvas" width="600" height="320" class="h-auto w-full"></canvas>
                </div>
            </div>
        </div>

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

        <p class="mt-[12px] text-right">
            <a href="{{ route('bot-protection.dashboard') }}" class="text-[11px] text-[#a9a9a9] hover:text-white hover:underline">&larr; Back to Dashboard</a>
        </p>
    </section>
<style>
.bp-adv-scroll { scrollbar-width: thin; scrollbar-color: #6400B2 transparent; }
.bp-adv-scroll::-webkit-scrollbar { width: 5px; }
.bp-adv-scroll::-webkit-scrollbar-thumb { background: #6400B2; border-radius: 4px; }
.bp-adv-filters-menu {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    z-index: 50;
    width: min(calc(100vw - 32px), 420px);
    max-height: 320px;
    overflow: auto;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 8px;
    background: #0f0e0e;
    padding: 12px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45);
}
</style>

<script>
function botProtectionAdvancedFigma() {
    const columnCatalog = [
        { key: 'ip', label: 'IP Address', primary: true, min: 120 },
        { key: 'visits', label: 'Visits', primary: true, min: 44 },
        { key: 'domain', label: 'Domain', primary: true, min: 100 },
        { key: 'path', label: 'Path', primary: true, min: 100 },
        { key: 'last_seen_label', label: 'Last Seen', primary: true, min: 76 },
        { key: 'threat_group', label: 'Threat Group', primary: true, min: 84 },
        { key: 'threat_type', label: 'Threat Type', primary: true, min: 76 },
        { key: 'action_taken', label: 'Action Taken', primary: true, min: 76 },
        { key: 'country', label: 'Country', primary: true, min: 72 },
        { key: 'invalid_visits', label: 'Invalid', primary: true, min: 52 },
        { key: 'valid_visits', label: 'Valid', primary: true, min: 52 },
        { key: 'session_recording', label: 'Recording', primary: false, min: 64 },
        { key: 'status', label: 'Status', primary: false, min: 72 },
        { key: 'browser', label: 'Browser', primary: false, min: 80 },
        { key: 'os', label: 'OS', primary: false, min: 72 },
        { key: 'referrer', label: 'Referrer', primary: false, min: 100 },
        { key: 'threat_score', label: 'Threat Score', primary: false, min: 72 },
        { key: 'utm_source', label: 'UTM Source', primary: false, min: 80 },
        { key: 'utm_medium', label: 'UTM Medium', primary: false, min: 80 },
        { key: 'utm_campaign', label: 'UTM Campaign', primary: false, min: 90 },
        { key: 'intel_region', label: 'Region', primary: false, min: 80 },
        { key: 'intel_city', label: 'City', primary: false, min: 80 },
        { key: 'intel_latitude', label: 'Latitude', primary: false, min: 72 },
        { key: 'intel_longitude', label: 'Longitude', primary: false, min: 72 },
        { key: 'intel_asn', label: 'ASN', primary: false, min: 64 },
        { key: 'intel_asn_org', label: 'ASN Organization', primary: false, min: 110 },
        { key: 'intel_isp', label: 'ISP', primary: false, min: 90 },
        { key: 'intel_network_range', label: 'Network Range', primary: false, min: 100 },
        { key: 'intel_routed_prefix', label: 'Routed Prefix', primary: false, min: 100 },
        { key: 'intel_allocated_range', label: 'Allocated Range', primary: false, min: 100 },
        { key: 'intel_range_note', label: 'Range Note', primary: false, min: 90 },
        { key: 'intel_vpn', label: 'VPN', primary: false, min: 48 },
        { key: 'intel_proxy', label: 'Proxy', primary: false, min: 48 },
        { key: 'intel_tor', label: 'Tor', primary: false, min: 48 },
        { key: 'intel_datacenter', label: 'Datacenter', primary: false, min: 72 },
        { key: 'intel_risk_score', label: 'Risk Score', primary: false, min: 72 },
        { key: 'intel_risk_level', label: 'Risk Level', primary: false, min: 72 },
        { key: 'intel_confidence', label: 'Confidence', primary: false, min: 72 },
        { key: 'intel_evidence', label: 'Evidence', primary: false, min: 90 },
        { key: 'intel_checked_at', label: 'Checked At', primary: false, min: 100 },
        { key: 'intel_error', label: 'Error', primary: false, min: 56 },
        { key: 'intel_ip_need_blockation', label: 'IP Need Blockation', primary: false, min: 110 },
        { key: 'intel_blockation_type', label: 'Blockation Type', primary: false, min: 100 },
        { key: 'intel_block_reason', label: 'Block Reason', primary: false, min: 100 },
        { key: 'intel_device_action', label: 'Device Action', primary: false, min: 90 },
        { key: 'intel_provider_type', label: 'Provider Type', primary: false, min: 90 },
        { key: 'intel_matched_provider', label: 'Matched Provider', primary: false, min: 110 },
        { key: 'intel_matched_dataset', label: 'Matched Dataset', primary: false, min: 110 },
        { key: 'intel_cloud_provider', label: 'Cloud Provider', primary: false, min: 100 },
    ];

    let savedOptional = [];
    try {
        savedOptional = JSON.parse(localStorage.getItem('bp-adv-optional-columns') || '[]');
    } catch (e) {}
    if (!savedOptional.includes('session_recording')) {
        savedOptional = [...savedOptional, 'session_recording'];
    }

    return {
        columnCatalog,
        optionalColumnKeys: Array.isArray(savedOptional) ? savedOptional : [],
        recordingModal: { open: false, ip: '', page_url: '', events: [] },
        filterMenuOpen: false,
        get visibleColumns() {
            return this.columnCatalog.filter(col => col.primary || this.optionalColumnKeys.includes(col.key));
        },
        get gridStyle() {
            const cols = this.visibleColumns.map(col => `${col.min || 80}px`).join(' ');
            return `grid-template-columns: ${cols}`;
        },
        filters: {
            domain_id: '', path: '', ip: '', country: '', action: '', threat_group: '',
            only_invalid: false, only_paid: false, from: '', to: '',
        },
        rows: [],
        meta: { total: 0, page: 1, per_page: 25 },
        moreFiltersOpen: false,
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
        reloadTimer: null,
        debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
        scheduleReload(resetPage = false) {
            clearTimeout(this.reloadTimer);
            this.reloadTimer = setTimeout(() => this.reload(resetPage), this.debounceMs);
        },
        syncHeaderDates() {
            try {
                const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
                if (r.from) this.filters.from = r.from;
                if (r.to) this.filters.to = r.to;
            } catch (e) {}
        },
        applyPageDates() {
            if (!this.filters.from || !this.filters.to) return;
            try {
                localStorage.setItem('promotix-date-range', JSON.stringify({
                    from: this.filters.from,
                    to: this.filters.to,
                }));
            } catch (e) {}
            window.dispatchEvent(new CustomEvent('promotix:date-range', {
                detail: { from: this.filters.from, to: this.filters.to },
            }));
            this.reload(true);
        },
        async init() {
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const today = new Date();
                const start = new Date(today.getTime() - 6 * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = today.toISOString().slice(0, 10);
            }
            window.addEventListener('promotix:date-range', () => {
                this.syncHeaderDates();
                this.scheduleReload(true);
            });
            await this.reload(true);
        },
        async reload(resetPage = false) {
            if (resetPage) this.meta.page = 1;
            try {
                const qs = this.qs({ page: this.meta.page, per_page: this.meta.per_page });
                const [visits, stats] = await Promise.all([
                    fetch(`/bot-protection/visits?${qs}`).then(r => r.json()),
                    fetch(`/bot-protection/bot-stats?${this.qs()}`).then(r => r.json()),
                ]);
                this.rows = visits.data || [];
                this.meta = { ...this.meta, ...(visits.meta || {}) };
                this.stats = stats;
            } finally {
                window.promotixPageLoader?.hide();
            }
        },
        async changePage(p) {
            this.meta.page = Math.max(1, p);
            await this.reload(false);
        },
        paginationLabel() {
            const start = this.rows.length ? ((this.meta.page - 1) * this.meta.per_page + 1) : 0;
            const end = Math.min(this.meta.total, this.meta.page * this.meta.per_page);
            return `${start}-${end} of ${this.meta.total}`;
        },
        toggleOptionalColumn(key) {
            if (this.optionalColumnKeys.includes(key)) {
                this.optionalColumnKeys = this.optionalColumnKeys.filter(k => k !== key);
            } else {
                this.optionalColumnKeys = [...this.optionalColumnKeys, key];
            }
            try {
                localStorage.setItem('bp-adv-optional-columns', JSON.stringify(this.optionalColumnKeys));
            } catch (e) {}
        },
        cellValue(row, key) {
            if (key === 'ip') return this.ipLabel(row);
            if (key === 'threat_group') return row.threat_group_label || row.threat_group || '—';
            if (key === 'threat_type') return row.threat_type || row.threat_type_label || '—';
            if (key === 'country') return row.country_label || row.country || '—';
            if (key === 'action_taken') {
                const v = row.action_taken;
                return v ? String(v).charAt(0).toUpperCase() + String(v).slice(1) : '—';
            }
            const value = row[key];
            if (value === 0) return '0';
            if (value === null || value === undefined || value === '') return '—';
            return String(value);
        },
        ipLabel(row) {
            const raw = String(row?.ip || '');
            if (!raw) return '—';
            if (raw.length > 20) return raw.slice(0, 18) + '…';
            return raw;
        },
        countryFlagUrl(code) {
            const c = String(code || '').trim().toLowerCase();
            if (!/^[a-z]{2}$/.test(c)) return '';
            return `https://flagcdn.com/w20/${c}.png`;
        },
        async openRecording(row) {
            if (!row?.session_recording_id) return;
            try {
                const res = await fetch(`{{ route('paid-marketing.session-recording', ['recording' => '__ID__']) }}`.replace('__ID__', row.session_recording_id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('recording fetch failed');
                const data = await res.json();
                this.recordingModal = { open: true, ip: data.ip || row.ip, page_url: data.page_url || '', events: data.events || [] };
                this.$nextTick(() => this.renderRecording(data.events || []));
            } catch (e) { console.error(e); }
        },
        closeRecording() {
            this.recordingModal = { open: false, ip: '', page_url: '', events: [] };
        },
        renderRecording(events) {
            const canvas = this.$refs.recordingCanvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const w = canvas.width; const h = canvas.height;
            ctx.fillStyle = '#0d0d0d'; ctx.fillRect(0, 0, w, h);
            const moves = events.filter(e => e.type === 'mousemove' && typeof e.x === 'number');
            if (!moves.length) { ctx.fillStyle = '#a9a9a9'; ctx.fillText('No movement captured', 20, 40); return; }
            const maxX = Math.max(...moves.map(e => e.x), 1);
            const maxY = Math.max(...moves.map(e => e.y), 1);
            ctx.strokeStyle = '#B893D8'; ctx.lineWidth = 2; ctx.beginPath();
            moves.forEach((e, i) => {
                const x = (e.x / maxX) * (w - 20) + 10;
                const y = (e.y / maxY) * (h - 20) + 10;
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.stroke();
        },
    };
}
</script>
@endsection

