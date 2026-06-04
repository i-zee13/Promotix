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

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                <label class="flex min-w-0 flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaigns</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.campaign" @change="scheduleFetch()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All campaigns</option>
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign }}">{{ $campaign }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[178px] shrink-0 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleFetch()" placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
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

            <div x-show="filtersOpen" x-cloak class="mt-[14px] flex justify-end rounded-[8px] bg-black/20 p-[10px]">
                <button type="button" @click="clearFilters()" class="rounded-[4px] border border-white/30 px-[12px] py-[7px] text-[12px] text-white/80">Clear filters</button>
            </div>
        </div>

        <section class="mt-[8px] overflow-hidden">
            <div class="promotix-slim-scroll paid-detailed-scroll max-h-[360px]">
                <table class="paid-detailed-table">
                    <thead>
                        <tr>
                            <th class="w-[22px]"></th>
                            <th class="w-[130px]">IP Address</th>
                            <th class="w-[52px]">Visits</th>
                            <th class="w-[110px]">Campaigns</th>
                            <th class="w-[88px]">Last Click</th>
                            <th class="w-[100px]">Threat Group</th>
                            <th class="w-[90px]">Threat Type</th>
                            <th class="w-[72px]">Country</th>
                            <th class="w-[48px]">VPN</th>
                            <th class="w-[72px]">Data Center</th>
                            <th class="w-[72px]">Invalid Click</th>
                            <th class="w-[68px]">Valid Click</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="visit in rows" :key="visit.id">
                            <tr @click="openClicks(visit)">
                                <td>&gt;</td>
                                <td class="cell-ip" :title="visit.ip">
                                    <span x-text="ipLabel(visit)"></span>
                                    <span x-show="visit.ip_count > 1" class="cell-ip-badge" x-text="'+' + (visit.ip_count - 1)"></span>
                                </td>
                                <td x-text="visit.visits"></td>
                                <td class="cell-muted" :title="visit.campaign || 'N/A'" x-text="visit.campaign || 'N/A'"></td>
                                <td class="cell-muted" x-text="visit.last_click_label"></td>
                                <td class="cell-muted" :title="visit.threat_group || '—'" x-text="visit.threat_group || '—'"></td>
                                <td class="cell-muted" :title="visit.threat_type || '—'" x-text="visit.threat_type || '—'"></td>
                                <td class="cell-muted" x-text="visit.country || '—'"></td>
                                <td x-text="visit.vpn_hits > 0 ? visit.vpn_hits : '—'"></td>
                                <td x-text="visit.data_center_hits > 0 ? visit.data_center_hits : '—'"></td>
                                <td x-text="visit.invalid_clicks ?? 0"></td>
                                <td x-text="visit.valid_clicks ?? 0"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="!loading && rows.length === 0" class="mt-[6px] rounded-[10px] border-[3px] border-white/40 px-[14px] py-[28px] text-center text-[15px] text-[#a9a9a9]">No rows match your filters.</div>
            </div>
        </section>

        <section class="mt-[20px]">
            <h2 class="mx-auto mb-[18px] flex h-[36px] w-[184px] items-center justify-center rounded-[4px] bg-[#6706B3] text-[24px] font-semibold text-[#a9a9a9]">Paid Stats</h2>
            <div class="grid grid-cols-2 gap-[14px] sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-7">
                <template x-for="card in statCards" :key="card.label">
                    <article class="relative h-[228px] overflow-hidden rounded-[10px] border border-white/40 bg-[#6400B2]">
                        <div class="absolute inset-x-0 bottom-0 rounded-t-[10px] opacity-40" :class="card.fillClass + ' ' + card.toneClass"></div>
                        <div class="relative z-10 pt-[31px] text-center">
                            <p class="mb-[26px] text-[14px] text-[#a9a9a9]" x-text="card.label"></p>
                            <p class="text-[36px] font-medium leading-none text-white" x-text="card.value + '%'"></p>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <div class="figma-modal-overlay"
             x-show="modal.open" x-cloak x-transition
             @keydown.escape.window="closeModal()" @click.self="closeModal()">
            <div class="figma-modal max-w-5xl">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="figma-modal-title">Click Details</h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="grid grid-cols-1 gap-0 lg:grid-cols-4">
                    <aside class="border-b border-white/10 p-2 lg:border-b-0 lg:border-r lg:pr-4">
                        <template x-for="(c, idx) in modal.clicks" :key="c.id ?? idx">
                            <button type="button"
                                    class="mb-2 w-full rounded-xl border border-white/15 bg-[#0d0d0d] px-3 py-2 text-left transition hover:bg-[#1a1a1a]"
                                    :class="idx === modal.activeIndex ? 'ring-2 ring-[#6400B2]' : ''"
                                    @click="modal.activeIndex = idx">
                                <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                                <p class="text-xs text-white/50" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            </button>
                        </template>
                        <template x-if="modal.clicks.length === 0">
                            <p class="text-sm text-white/50">No clicks for this visit.</p>
                        </template>
                    </aside>

                    <div class="p-4 lg:col-span-3 lg:pl-6" x-show="modal.clicks.length > 0">
                        <template x-if="activeClick">
                            <div class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
                                <div class="md:col-span-2"><p class="figma-modal-label">IP</p><p class="figma-modal-value break-all font-mono text-[12px]" x-text="modal.visit?.ip || activeClick.ip || '-'"></p></div>
                                <div><p class="figma-modal-label">VPN Hits</p><p class="figma-modal-value" x-text="modal.visit?.vpn_hits > 0 ? modal.visit.vpn_hits : '—'"></p></div>
                                <div><p class="figma-modal-label">Data Center</p><p class="figma-modal-value" x-text="modal.visit?.data_center_hits > 0 ? modal.visit.data_center_hits : '—'"></p></div>
                                <div><p class="figma-modal-label">Invalid Clicks</p><p class="figma-modal-value" x-text="modal.visit?.invalid_clicks ?? 0"></p></div>
                                <div><p class="figma-modal-label">Valid Clicks</p><p class="figma-modal-value" x-text="modal.visit?.valid_clicks ?? 0"></p></div>
                                <div><p class="figma-modal-label">Browser</p><p class="figma-modal-value" x-text="activeClick.browser_name || '-'"></p></div>
                                <div><p class="figma-modal-label">Country</p><p class="figma-modal-value" x-text="activeClick.country || modal.visit?.country || '-'"></p></div>
                                <div><p class="figma-modal-label">Browser version</p><p class="figma-modal-value" x-text="activeClick.browser_version || '-'"></p></div>
                                <div><p class="figma-modal-label">Last Click</p><p class="figma-modal-value" x-text="formatDateTime(activeClick.last_click_at || modal.visit?.last_click_at)"></p></div>
                                <div><p class="figma-modal-label">OS</p><p class="figma-modal-value" x-text="activeClick.os || '-'"></p></div>
                                <div><p class="figma-modal-label">Threat Group</p><p class="figma-modal-value" x-text="activeClick.threat_group || modal.visit?.threat_group || 'N/A'"></p></div>
                                <div><p class="figma-modal-label">Paid ID</p><p class="figma-modal-value" x-text="activeClick.paid_id || '-'"></p></div>
                                <div><p class="figma-modal-label">Campaign</p><p class="figma-modal-value" x-text="activeClick.campaign || modal.visit?.campaign || 'N/A'"></p></div>
                                <div><p class="figma-modal-label">Path</p><p class="figma-modal-value" x-text="activeClick.path || modal.visit?.last_path || '-'"></p></div>
                                <div><p class="figma-modal-label">Keyword</p><p class="figma-modal-value" x-text="activeClick.keyword || 'N/A'"></p></div>
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
            filters: { ip: '', path: '', campaign: '', from: '', to: '' },
            rows: [],
            statCards: [],
            modal: { open: false, visit: null, clicks: [], activeIndex: 0 },
            get activeClick() { return this.modal.clicks[this.modal.activeIndex] || null; },
            init() {
                this.syncHeaderDates();
                if (!this.filters.from || !this.filters.to) {
                    const today = new Date();
                    const start = new Date(today.getTime() - 6 * 86400000);
                    this.filters.from = start.toISOString().slice(0, 10);
                    this.filters.to = today.toISOString().slice(0, 10);
                }
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
                this.scheduleFetch();
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
                    window.promotixPageLoader?.hide();
                }
            },
            clearFilters() {
                this.filters = { ip: '', path: '', campaign: '', from: '', to: '' };
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
            ipLabel(visit) {
                const raw = String(visit?.ip || '');
                const parts = Array.isArray(visit?.ip_parts) && visit.ip_parts.length
                    ? visit.ip_parts
                    : raw.split(',').map((p) => p.trim()).filter(Boolean);
                const first = parts[0] || raw || '—';
                if (first.length > 20) return first.slice(0, 18) + '…';
                return first;
            },
        };
    }
</script>
@endsection
