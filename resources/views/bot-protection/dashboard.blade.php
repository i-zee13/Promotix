@extends('layouts.admin')

@section('title', 'Bot Protection | Dashboard')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="botProtectionFigma(@js(['useDemo' => $useDemo]))" x-init="init()">
    <section class="mx-auto w-full px-[12px] pb-[24px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        {{-- Header --}}
        <div class="mb-[18px] flex flex-col gap-[14px] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Bot Protection</h1>
                <span class="hidden h-[34px] w-[2px] bg-[#a9a9a9] sm:block sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Dashboard</span>
                <span x-show="useDemo" x-cloak class="figma-bp-demo-badge">Sample data</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)]">
                <label class="flex min-w-0 flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domains</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.domain_id" @change="reload()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
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
                        <input x-model="filters.path" @input="scheduleReload()" placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        <div class="figma-bp-dashboard">
            {{-- Row 1: Area chart + vertical pill bars --}}
            <div class="figma-bp-visits-row">
                <section class="figma-bp-visits-card min-w-0">
                    <div class="figma-bp-visits-head">
                    <h2 class="figma-bp-visits-title">Total Visits Breakdown</h2>
                        <div class="figma-bp-visits-head__meta">
                            <div class="figma-bp-legend figma-bp-legend--inline">
                                <template x-for="ds in areaLegendItems()" :key="ds.name">
                                    <button
                                        type="button"
                                        class="chart-legend-item"
                                        :class="{ 'is-hidden': isSeriesHidden('area', ds.name) }"
                                        @click="toggleChartSeries('area', ds.name)"
                                    >
                                        <i :style="legendSwatchStyle(ds)"></i>
                                        <span x-text="ds.name"></span>
                                    </button>
                                </template>
                            </div>
                            <div class="relative" x-data="{ cardMenu: false }">
                                <button type="button" @click.stop="cardMenu = !cardMenu" class="figma-bp-kebab" aria-label="Chart options">
                                    <span class="flex flex-col items-center gap-[2px]" aria-hidden="true">
                                        <span class="block h-[3px] w-[3px] rounded-full bg-current"></span>
                                        <span class="block h-[3px] w-[3px] rounded-full bg-current"></span>
                                        <span class="block h-[3px] w-[3px] rounded-full bg-current"></span>
                                    </span>
                                </button>
                                <div x-show="cardMenu" x-cloak @click.outside="cardMenu = false" class="figma-bp-card-menu">
                                    <button type="button" @click="reload(); cardMenu = false">Refresh data</button>
                                    <a href="{{ route('bot-protection.advanced') }}">Advanced view</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="bp-area-chart" class="figma-bp-area-canvas"></div>
                </section>
                <div class="figma-bp-bars-col">
                    <article class="figma-bp-pill-card">
                        <p class="figma-bp-pill-label">Total Valid Visits</p>
                        <div class="figma-bp-pill-track" aria-hidden="true">
                            <div class="figma-bp-pill-fill figma-bp-pill-fill--valid" :style="`height:${barPct('valid')}%`"></div>
                        </div>
                    </article>
                    <article class="figma-bp-pill-card">
                        <p class="figma-bp-pill-label">Invalid bot Visits</p>
                        <div class="figma-bp-pill-track" aria-hidden="true">
                            <div class="figma-bp-pill-fill figma-bp-pill-fill--invalid" :style="`height:${barPct('invalid')}%`"></div>
                        </div>
                    </article>
                    <article class="figma-bp-pill-card">
                        <p class="figma-bp-pill-label">Known Crawlers</p>
                        <div class="figma-bp-pill-track" aria-hidden="true">
                            <div class="figma-bp-pill-fill figma-bp-pill-fill--crawler" :style="`height:${barPct('crawler')}%`"></div>
                        </div>
                    </article>
                </div>
            </div>

            {{-- Row 2–3: Chart + side (donuts + country) | full-width domain --}}
            <div class="figma-bp-mid-section">
                <section class="figma-bp-invalid-card min-w-0">
                    <h2 class="figma-bp-invalid-title">Invalid Traffic Breakdown</h2>
                    <div class="figma-bp-invalid-legend">
                        <template x-for="ds in invalidLegendItems()" :key="ds.name">
                            <button
                                type="button"
                                class="chart-legend-item"
                                :class="{ 'is-hidden': isSeriesHidden('invalid', ds.name) }"
                                @click="toggleChartSeries('invalid', ds.name)"
                            >
                                <i :style="legendSwatchStyle(ds)"></i>
                                <span x-text="ds.name"></span>
                            </button>
                        </template>
                    </div>
                    <div class="figma-bp-invalid-chart-wrap">
                        <div id="bp-invalid-line" class="figma-bp-invalid-canvas"></div>
                    </div>
                </section>

                <div class="figma-bp-side-col">
                <div class="figma-bp-donuts-col">
                    <section class="figma-bp-donut-card">
                        <h3>Threat Groups</h3>
                            <div class="figma-bp-donut-ring" :style="donutRingStyle(cache.th?.values)" role="img" aria-label="Threat groups chart">
                                <span class="figma-bp-donut-hole"><span class="figma-bp-donut-hole__text" x-text="donutTotal(cache.th?.values)"></span></span>
                            </div>
                            <p class="figma-bp-donut-legend text-center" x-text="donutFooter(cache.th)"></p>
                    </section>
                    <section class="figma-bp-donut-card">
                            <h3>Invalid Bot Activity</h3>
                            <div class="figma-bp-donut-ring" :style="donutRingStyle(cache.ib?.values, 'ib')" role="img" aria-label="Invalid bot activity chart">
                                <span class="figma-bp-donut-hole"><span class="figma-bp-donut-hole__text" x-text="donutTotal(cache.ib?.values)"></span></span>
                            </div>
                            <div class="figma-bp-donut-legend">
                                <template x-for="(label, i) in (cache.ib?.labels || []).slice(0, 3)" :key="label + i">
                                    <button
                                        type="button"
                                        class="chart-legend-item truncate text-left"
                                        :class="{ 'is-hidden': isDonutSegmentHidden('ib', i) }"
                                        @click="toggleDonutSegment('ib', i)"
                                        x-text="donutLegendLine(label, cache.ib?.values?.[i])"
                                    ></button>
                                </template>
                            </div>
                    </section>
                    <section class="figma-bp-donut-card">
                        <h3>Invalid Malicious</h3>
                            <div class="figma-bp-donut-ring" :style="donutRingStyle(cache.mal?.values, 'mal')" role="img" aria-label="Invalid malicious chart">
                                <span class="figma-bp-donut-hole"><span class="figma-bp-donut-hole__text" x-text="donutTotal(cache.mal?.values)"></span></span>
                            </div>
                            <div class="figma-bp-donut-legend" x-show="(cache.mal?.labels || []).length">
                                <template x-for="(label, i) in (cache.mal?.labels || []).slice(0, 2)" :key="label + i">
                                    <button
                                        type="button"
                                        class="chart-legend-item truncate text-left"
                                        :class="{ 'is-hidden': isDonutSegmentHidden('mal', i) }"
                                        @click="toggleDonutSegment('mal', i)"
                                        x-text="donutLegendLine(label, cache.mal?.values?.[i])"
                                    ></button>
                                </template>
                            </div>
                        </section>
                    </div>

                    <section class="figma-bp-table figma-bp-country-panel min-w-0">
                        <div class="figma-bp-table-head figma-bp-table-head--country">
                            <span>Country</span>
                            <span class="text-right">Visits</span>
                            <span class="text-right">Invalid</span>
                            <span class="text-right">%</span>
                        </div>
                        <div class="figma-bp-table-body figma-bp-table-body--country promotix-slim-scroll">
                            <template x-for="row in countries" :key="row.country">
                                <div class="figma-bp-table-row--country cursor-pointer transition hover:bg-white/5" @click="openCountryIps(row.country)">
                                    <span class="flex min-w-0 items-center gap-[4px] truncate">
                                        <img
                                            x-show="countryFlagUrl(row.country)"
                                            :src="countryFlagUrl(row.country)"
                                            :alt="countryLabel(row.country)"
                                            class="h-[8px] w-[12px] shrink-0 rounded-[1px] object-cover"
                                            loading="lazy"
                                        >
                                        <span x-text="countryLabel(row.country)"></span>
                                    </span>
                                    <span class="text-right" x-text="fmt(row.total ?? 0)"></span>
                                    <span class="text-right" x-text="fmt(row.invalid)"></span>
                                    <span class="text-right" x-text="(row.percent ?? 0) + '%'"></span>
                                </div>
                            </template>
                            <p x-show="countries.length === 0" class="px-[10px] py-[16px] text-center text-[10px] text-[#a9a9a9]">No country data.</p>
                        </div>
                    </section>
            </div>

                <section class="figma-bp-table figma-bp-domain-panel min-w-0">
                    <div class="figma-bp-table-head figma-bp-table-head--domain">
                        <span>Domain</span>
                        <span class="text-center">Total Valid Visits</span>
                        <span class="text-center">Invalid Traffic</span>
                        <span class="text-center">Known Crawlers</span>
                        <span class="sr-only">Action</span>
                    </div>
                    <div class="figma-bp-table-body promotix-slim-scroll">
                        <template x-for="row in domainsList" :key="row.id">
                            <div class="figma-bp-table-row--domain">
                                <span class="flex min-w-0 items-center gap-[6px] truncate font-medium">
                                    <svg class="h-[12px] w-[12px] shrink-0 text-[#6400b2]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 4v5c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V7l7-4z"/></svg>
                                    <span class="truncate" x-text="row.hostname"></span>
                                </span>
                                <span class="text-center" x-text="fmt(row.valid_visits)"></span>
                                <span class="text-center" x-text="fmt(row.invalid_visits)"></span>
                                <span class="text-center" x-text="fmt(row.known_crawlers)"></span>
                                <a href="{{ route('paid-marketing.detection-settings') }}" class="figma-bp-protect-btn">Get Protected</a>
                            </div>
                        </template>
                        <p x-show="domainsList.length === 0" class="px-[14px] py-[20px] text-center text-[11px] text-[#a9a9a9]">No domains in this window.</p>
                    </div>
                </section>
            </div>
        </div>

        <p class="mt-[12px] text-right">
            <a href="{{ route('bot-protection.advanced') }}" class="text-[11px] text-[#a9a9a9] hover:text-white hover:underline">Open Advanced View →</a>
        </p>

        <div class="figma-modal-overlay"
             x-show="countryModal.open" x-cloak x-transition
             @keydown.escape.window="closeCountryModal()" @click.self="closeCountryModal()">
            <div class="figma-modal max-w-[520px]">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="figma-modal-title" x-text="`IPs from ${countryLabel(countryModal.country)}`"></h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeCountryModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>
                <p x-show="countryModal.loading" class="text-[12px] text-white/60">Loading IPs…</p>
                <div class="max-h-[320px] overflow-y-auto promotix-slim-scroll" x-show="!countryModal.loading">
                    <template x-for="row in countryModal.rows" :key="row.ip">
                        <div class="mb-[6px] flex items-center justify-between rounded-[6px] bg-white/5 px-[10px] py-[8px] text-[11px] text-white">
                            <span class="font-mono" x-text="row.ip"></span>
                            <span class="text-white/60" x-text="`${fmt(row.invalid)} invalid / ${fmt(row.total)} total`"></span>
                        </div>
                    </template>
                    <p x-show="!countryModal.rows.length" class="text-[12px] text-white/50">No IPs for this country.</p>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
function buildBpDemoPayload() {
    const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const wave = (base, amp, phase = 0) => dayLabels.map((_, i) => {
        const v = base + Math.sin((i + phase) * 0.9) * amp + (Math.random() - 0.5) * amp * 0.35;
        return Math.max(0, Math.round(v));
    });
    const valid = wave(3600, 700, 0);
    const badBots = wave(520, 200, 1);
    const crawlers = wave(980, 280, 2);
    const invalid = wave(1200, 350, 0.5);
    const total = dayLabels.map((_, i) => Math.max(valid[i], invalid[i] + crawlers[i]) + 400);
    const thisWeek = wave(4200, 1100, 0);
    const siteInteraction = wave(1600, 520, 1.1);

    return {
        summary: {
            total_visits: 48200,
            valid_visits: 29400,
            invalid_bot_visits: 12800,
            invalid_malicious_visits: 2100,
            known_crawlers: 5900,
        },
        domainsList: [
            { id: 1, hostname: 'www.example.com', valid_visits: 29400, invalid_visits: 12800, known_crawlers: 5900 },
            { id: 2, hostname: 'www.infinitdigi.com', valid_visits: 8200, invalid_visits: 2100, known_crawlers: 340 },
        ],
        countries: [
            { country: 'US', total: 12400, invalid: 4200, percent: 34 },
            { country: 'GB', total: 6800, invalid: 2100, percent: 17 },
            { country: 'DE', total: 5200, invalid: 1800, percent: 15 },
            { country: 'PK', total: 4100, invalid: 1200, percent: 10 },
            { country: 'AE', total: 2900, invalid: 980, percent: 8 },
        ],
        invalidTrends: {
            labels: dayLabels,
            datasets: [
                { name: 'Invalid Pageloads', values: thisWeek, color: '#6625F8' },
                { name: 'Invalid Site Interaction', values: siteInteraction, color: '#FF4BC1', dashed: true },
            ],
            stats: { pageloads: thisWeek.reduce((a, b) => a + b, 0), interactions: siteInteraction.reduce((a, b) => a + b, 0) },
        },
        cache: {
            traffic: {
                labels: dayLabels,
                datasets: [
                    { name: 'Valid Visits', values: valid, color: '#FFFFFF' },
                    { name: 'Bad Bots', values: badBots, color: '#0D0D0D' },
                    { name: 'Crawler', values: crawlers, color: '#6625F8' },
                    { name: 'Invalid', values: invalid, color: '#FF4BC1' },
                    { name: 'Total Visits', values: total, color: '#B893D8', line: true },
                ],
            },
            th: { labels: ['vpn', 'data_center', 'abnormal_rate_limit'], values: [12, 7, 3] },
            ib: { labels: ['automation_tool', 'scrapers'], values: [19, 3] },
            mal: { labels: ['malicious'], values: [14] },
        },
    };
}

function botProtectionFigma(config = {}) {
    const countryNames = { US: 'United states', GB: 'United Kingdom', DE: 'Germany', PK: 'Pakistan', AE: 'UAE', CA: 'Canada', FR: 'France', IN: 'India' };

    return {
        useDemo: Boolean(config.useDemo),
        filters: { domain_id: '', path: '', from: '', to: '' },
        summary: { total_visits: 0, valid_visits: 0, invalid_bot_visits: 0, known_crawlers: 0 },
        countries: [],
        countryModal: { open: false, country: '', rows: [], loading: false },
        domainsList: [],
        invalidTrends: { labels: [], datasets: [], stats: { pageloads: 0, interactions: 0 } },
        cache: {},
        donutPalette: ['#FFFFFF', '#B893D8', '#6625F8', '#FF4BC1'],
        charts: {},
        hiddenSeries: { area: {}, invalid: {} },
        hiddenDonutSegments: { ib: {}, mal: {} },
        invalidHoverIndex: null,
        fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
        fmtCompact(n) {
            const v = Number(n || 0);
            if (v >= 1000) return Math.round(v / 1000) + 'k';
            return this.fmt(v);
        },
        countryLabel(code) { return countryNames[code] || code || 'Unknown'; },
        countryFlagUrl(code) {
            const c = String(code || '').trim().toLowerCase();
            if (!/^[a-z]{2}$/.test(c)) return '';
            return `https://flagcdn.com/w20/${c}.png`;
        },
        async openCountryIps(country) {
            if (!country) return;
            this.countryModal = { open: true, country, rows: [], loading: true };
            try {
                const p = new URLSearchParams(this.qs());
                p.set('country', country);
                this.countryModal.rows = await fetch(`/bot-protection/country-ips?${p}`).then(r => r.json());
            } catch (e) {
                this.countryModal.rows = [];
            } finally {
                this.countryModal.loading = false;
            }
        },
        closeCountryModal() {
            this.countryModal = { open: false, country: '', rows: [], loading: false };
        },
        threatLabel(key) {
            const map = { vpn: 'VPN', data_center: 'Data center', abnormal_rate_limit: 'Rate limit', malicious: 'Malicious' };
            const k = String(key || '').toLowerCase();
            return map[k] || k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
        },
        get thisWeekInvalid() {
            const ds = (this.invalidTrends.datasets || []).find(d => !d.dashed);
            return (ds?.values || []).reduce((a, b) => a + Number(b || 0), 0);
        },
        get lastWeekInvalid() {
            const ds = (this.invalidTrends.datasets || []).find(d => d.dashed);
            return (ds?.values || []).reduce((a, b) => a + Number(b || 0), 0);
        },
        get botActivityTotal() {
            const vals = this.cache.ib?.values || [];
            return vals.reduce((a, b) => a + Number(b || 0), 0);
        },
        barPct(kind) {
            // Figma export (v266_674 230px, v266_676 270px, v290_369 127px in 287px cards)
            const figmaPct = { valid: 80, invalid: 94, crawler: 44 };
            const total = Math.max(Number(this.summary.total_visits || 0), 1);
            const map = {
                valid: Number(this.summary.valid_visits || 0),
                invalid: Number(this.summary.invalid_bot_visits || 0),
                crawler: Number(this.summary.known_crawlers || 0),
            };
            const live = Math.round((map[kind] / total) * 100);
            return Math.max(12, Math.min(94, Math.round((figmaPct[kind] * 0.65) + (live * 0.35))));
        },
        qs() {
            const p = new URLSearchParams();
            if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
            if (this.filters.path) p.set('path', this.filters.path);
            if (this.filters.from) p.set('from', this.filters.from);
            if (this.filters.to) p.set('to', this.filters.to);
            return p.toString();
        },
        reloadTimer: null,
        debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
        scheduleReload() {
            clearTimeout(this.reloadTimer);
            this.reloadTimer = setTimeout(() => this.reload(), this.debounceMs);
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
            this.reload();
        },
        async init() {
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const today = new Date();
                const start = new Date(today.getTime() - 6 * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = today.toISOString().slice(0, 10);
            }
            await this.reload();
            window.addEventListener('promotix:date-range', () => {
                this.syncHeaderDates();
                this.scheduleReload();
            });
            window.addEventListener('resize', () => {
                clearTimeout(window.__bpFigmaResize);
                window.__bpFigmaResize = setTimeout(() => this.renderCharts(), 180);
            });
        },
        applyDemoPayload() {
            const demo = buildBpDemoPayload();
            this.summary = demo.summary;
            this.invalidTrends = demo.invalidTrends;
            this.countries = demo.countries;
            this.domainsList = demo.domainsList || [];
            this.cache = demo.cache;
        },
        donutTotal(values) {
            return (values || []).reduce((a, b) => a + Number(b || 0), 0);
        },
        areaLegendItems() {
            const defaults = [
                { name: 'Valid Visits', color: '#FFFFFF' },
                { name: 'Bad Bots', color: '#0D0D0D' },
                { name: 'Crawler', color: '#6625F8' },
                { name: 'Invalid', color: '#FF4BC1' },
                { name: 'Total Visits', color: '#B893D8', line: true },
            ];
            const datasets = this.cache?.traffic?.datasets || [];
            return datasets.length ? datasets : defaults;
        },
        invalidLegendItems() {
            const defaults = [
                { name: 'Invalid Pageloads', color: '#6625F8' },
                { name: 'Invalid Site Interaction', color: '#FF4BC1', dashed: true },
            ];
            const datasets = this.invalidTrends?.datasets || [];
            return datasets.length ? datasets : defaults;
        },
        legendSwatchStyle(ds) {
            const color = ds?.color || '#FFFFFF';
            if (String(color).toLowerCase() === '#0d0d0d') {
                return 'background:#0D0D0D;border:1px solid rgba(255,255,255,.25)';
            }
            return `background:${color}`;
        },
        isSeriesHidden(chartKey, name) {
            return Boolean(this.hiddenSeries?.[chartKey]?.[name]);
        },
        toggleChartSeries(chartKey, name) {
            const chart = this.charts?.[chartKey];
            if (!chart || !name) return;
            if (!this.hiddenSeries[chartKey]) this.hiddenSeries[chartKey] = {};
            this.hiddenSeries[chartKey][name] = !this.hiddenSeries[chartKey][name];
            chart.toggleSeries(name);
        },
        applyHiddenSeries(chartKey) {
            const chart = this.charts?.[chartKey];
            const hidden = this.hiddenSeries?.[chartKey] || {};
            if (!chart) return;
            Object.entries(hidden).forEach(([name, isHidden]) => {
                if (!isHidden) return;
                const idx = chart.w?.globals?.seriesNames?.indexOf(name);
                const collapsed = chart.w?.globals?.collapsedSeriesIndices || [];
                if (idx >= 0 && !collapsed.includes(idx)) {
                    chart.toggleSeries(name);
                }
            });
        },
        isDonutSegmentHidden(group, index) {
            return Boolean(this.hiddenDonutSegments?.[group]?.[index]);
        },
        toggleDonutSegment(group, index) {
            if (!this.hiddenDonutSegments[group]) this.hiddenDonutSegments[group] = {};
            this.hiddenDonutSegments[group][index] = !this.hiddenDonutSegments[group][index];
        },
        visibleDonutValues(values, group) {
            const hidden = this.hiddenDonutSegments?.[group] || {};
            return (values || []).map((v, i) => (hidden[i] ? 0 : Number(v || 0)));
        },
        donutRingStyle(values, group = null) {
            const data = group
                ? this.visibleDonutValues(values, group)
                : (values || []).map(v => Number(v || 0));
            const total = data.reduce((a, b) => a + b, 0);
            if (!total) {
                return { background: 'conic-gradient(rgba(255,255,255,0.35) 0deg 360deg)' };
            }
            let deg = 0;
            const stops = data.map((v, i) => {
                const span = (v / total) * 360;
                const start = deg;
                deg += span;
                const color = this.donutPalette[i % this.donutPalette.length];
                return `${color} ${start}deg ${deg}deg`;
            });
            return { background: `conic-gradient(${stops.join(', ')})` };
        },
        donutFooter(block) {
            const total = this.donutTotal(block?.values);
            if (!total) return 'No data';
            const labels = block?.labels || [];
            if (labels.length === 1) return `${this.threatLabel(labels[0])}: ${total}`;
            return `Total ${this.fmt(total)}`;
        },
        donutLegendLine(label, value) {
            const name = this.threatLabel(label);
            return `${name}: ${Number(value || 0)}`;
        },
        dataIsEmpty() {
            const traffic = this.cache?.traffic?.datasets || [];
            const hasTraffic = traffic.some(d => (d.values || []).some(v => Number(v) > 0));
            const trends = this.invalidTrends?.datasets || [];
            const hasTrends = trends.some(d => (d.values || []).some(v => Number(v) > 0));
            return !hasTraffic && !hasTrends;
        },
        async reload() {
            try {
                if (this.useDemo) {
                    this.applyDemoPayload();
                    this.$nextTick(() => this.renderCharts());
                    return;
                }
            const qs = this.qs();
            const [s, traffic, trends, th, ib, c, ds] = await Promise.all([
                fetch(`/bot-protection/summary?${qs}`).then(r => r.json()),
                fetch(`/bot-protection/traffic-breakdown?${qs}`).then(r => r.json()),
                fetch(`/bot-protection/invalid-traffic-trends?${qs}`).then(r => r.json()),
                fetch(`/bot-protection/threat-groups?${qs}`).then(r => r.json()),
                fetch(`/bot-protection/invalid-breakdown?${qs}`).then(r => r.json()),
                fetch(`/bot-protection/countries?${qs}`).then(r => r.json()),
                fetch(`/bot-protection/domains-summary?${qs}`).then(r => r.json()),
            ]);
            this.summary = s;
            this.invalidTrends = trends;
            this.countries = c;
                this.domainsList = Array.isArray(ds) ? ds : [];
                this.cache = {
                    traffic,
                    th,
                    ib: ib?.invalid_bot ?? { labels: [], values: [] },
                    mal: ib?.invalid_malicious ?? { labels: [], values: [] },
                };
                if (this.useDemo && this.dataIsEmpty()) {
                    this.applyDemoPayload();
                }
                this.$nextTick(() => this.renderCharts());
            } finally {
                window.promotixPageLoader?.hide();
            }
        },
        destroyChart(key) {
            if (this.charts[key]) {
                this.charts[key].destroy();
                delete this.charts[key];
            }
        },
        bpApexGrid() {
            return {
                borderColor: 'rgba(255,255,255,0.08)',
                strokeDashArray: 4,
                xaxis: { lines: { show: true } },
                yaxis: { lines: { show: true } },
                padding: { top: 0, right: 8, bottom: 0, left: 8 },
            };
        },
        bpYAxisCap(peak) {
            const value = Math.max(Number(peak) || 0, 1);
            if (value <= 10) return 10;
            if (value <= 50) return Math.ceil(value / 5) * 5;
            if (value <= 200) return Math.ceil(value / 20) * 20;
            if (value <= 1000) return Math.ceil(value / 100) * 100;
            if (value <= 5000) return Math.ceil(value / 500) * 500;
            return Math.ceil(value / 1000) * 1000;
        },
        bpYAxisStep(cap) {
            if (cap <= 10) return 2;
            if (cap <= 50) return 10;
            if (cap <= 200) return 40;
            if (cap <= 1000) return 200;
            if (cap <= 5000) return 1000;
            return Math.max(1000, cap / 5);
        },
        bpApexYAxis(maxY) {
            const cap = this.bpYAxisCap(maxY);
            const step = this.bpYAxisStep(cap);
            return {
                min: 0,
                max: cap,
                tickAmount: Math.max(2, Math.round(cap / step)),
                labels: {
                    style: { colors: 'rgba(255,255,255,0.45)', fontSize: '8px' },
                    formatter: (v) => (v >= 1000 ? `${Math.round(v / 1000)}k` : Math.round(v)),
                },
            };
        },
        alignSeriesValues(values, length) {
            const out = (values || []).map(v => Number(v || 0));
            while (out.length < length) out.push(0);
            return out.slice(0, length);
        },
        renderAreaChart() {
            const el = document.getElementById('bp-area-chart');
            if (!el || !window.ApexCharts) return;
            this.destroyChart('area');

            const traffic = this.cache?.traffic ?? { labels: [], datasets: [] };
            const labels = traffic.labels || [];
            const datasets = traffic.datasets || [];
            if (!labels.length) return;

            const areas = datasets.filter(d => !d.line);
            const lineDs = datasets.find(d => d.line);
            const allValues = datasets.flatMap(d => this.alignSeriesValues(d.values, labels.length));
            const maxY = Math.max(...allValues, 0);
            const sparse = labels.length <= 4;
            const fillOpacity = {
                '#FFFFFF': 0.42,
                '#0D0D0D': 0.58,
                '#6625F8': 0.52,
                '#FF4BC1': 0.46,
            };

            const series = areas.map(ds => ({
                name: ds.name,
                data: this.alignSeriesValues(ds.values, labels.length),
            }));

            const colors = areas.map(ds => ds.color);
            const opacities = areas.map(ds => fillOpacity[ds.color] ?? 0.45);

            if (lineDs) {
                series.push({ name: lineDs.name, data: this.alignSeriesValues(lineDs.values, labels.length) });
                colors.push(lineDs.color || '#B893D8');
                opacities.push(0);
            }

            const strokeWidths = [...areas.map(() => 1.5), ...(lineDs ? [2.5] : [])];

            this.charts.area = new ApexCharts(el, {
                chart: {
                    type: 'area',
                    height: 177,
                    fontFamily: 'inherit',
                    background: 'transparent',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: true, speed: 450 },
                },
                series,
                colors,
                dataLabels: { enabled: false },
                stroke: {
                    curve: sparse ? 'straight' : 'smooth',
                    width: strokeWidths,
                    lineCap: 'round',
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        type: 'vertical',
                        shadeIntensity: 0.35,
                        opacityFrom: 0.65,
                        opacityTo: 0.04,
                        stops: [0, 88, 100],
                    },
                    opacity: opacities,
                },
                grid: this.bpApexGrid(),
                xaxis: {
                    categories: labels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: 'rgba(255,255,255,0.55)', fontSize: '9px' },
                    },
                    crosshairs: {
                        show: true,
                        stroke: { color: 'rgba(255,255,255,0.25)', width: 1, dashArray: 4 },
                    },
                },
                yaxis: this.bpApexYAxis(maxY),
                legend: { show: false },
                tooltip: {
                    theme: 'dark',
                    shared: true,
                    intersect: false,
                    style: { fontSize: '11px' },
                    y: { formatter: (v) => this.fmt(Math.round(v || 0)) },
                },
                markers: {
                    size: 0,
                    strokeWidth: 0,
                    hover: { size: 4, sizeOffset: 1 },
                },
            });
            this.charts.area.render();
            this.applyHiddenSeries('area');
        },
        renderInvalidChart() {
            const el = document.getElementById('bp-invalid-line');
            if (!el || !window.ApexCharts) return;
            this.destroyChart('invalid');

            const labels = this.invalidTrends.labels ?? [];
            const datasets = (this.invalidTrends.datasets ?? []).map(d => ({
                ...d,
                values: this.alignSeriesValues(d.values, labels.length),
            }));
            if (!labels.length) return;

            const primary = datasets.find(d => !d.dashed) || datasets[0];
            const compare = datasets.find(d => d.dashed) || datasets[1];
            const maxY = Math.max(...datasets.flatMap(d => d.values), 0);
            const sparse = labels.length <= 6;
            const showMarkers = labels.length <= 8;

            const series = [];
            const colors = [];
            const strokeWidths = [];
            const dashArrays = [];
            const fillOpacities = [];

            if (primary) {
                series.push({ name: primary.name || 'Invalid Pageloads', data: primary.values });
                colors.push(primary.color || '#6625F8');
                strokeWidths.push(2);
                dashArrays.push(0);
                fillOpacities.push(0.48);
            }
            if (compare) {
                series.push({ name: compare.name || 'Invalid Site Interaction', data: compare.values });
                colors.push(compare.color || '#FF4BC1');
                strokeWidths.push(2);
                dashArrays.push(6);
                fillOpacities.push(0);
            }

            const fmtCompact = this.fmtCompact.bind(this);

            this.charts.invalid = new ApexCharts(el, {
                chart: {
                    type: 'area',
                    height: 210,
                    fontFamily: 'inherit',
                    background: 'transparent',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: true, speed: 450 },
                },
                series,
                colors,
                dataLabels: { enabled: false },
                plotOptions: {
                    area: { fillTo: 'origin' },
                },
                stroke: {
                    curve: sparse ? 'straight' : 'smooth',
                    width: strokeWidths,
                    dashArray: dashArrays,
                    lineCap: 'round',
                    show: true,
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        type: 'vertical',
                        shadeIntensity: 0.4,
                        opacityFrom: 0.55,
                        opacityTo: 0.03,
                        stops: [0, 92, 100],
                    },
                    opacity: fillOpacities,
                },
                grid: this.bpApexGrid(),
                xaxis: {
                    categories: labels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: '#9D9D9D', fontSize: '9px' },
                    },
                    crosshairs: {
                        show: true,
                        stroke: { color: 'rgba(255,255,255,0.45)', width: 1, dashArray: 4 },
                    },
                },
                yaxis: this.bpApexYAxis(maxY),
                legend: { show: false },
                tooltip: {
                    theme: 'dark',
                    shared: true,
                    intersect: false,
                    custom: ({ series, dataPointIndex, w }) => {
                        const label = w.globals.labels[dataPointIndex] || '';
                        const rows = (w.globals.seriesNames || []).map((name, idx) => {
                            const color = colors[idx] || '#6625F8';
                            const val = Number(series[idx]?.[dataPointIndex] || 0);
                            return `<span><i style="background:${color}"></i>${name}: ${fmtCompact(val)}</span>`;
                        }).join('');
                        return `<div class="figma-bp-apex-tooltip"><strong>${label}</strong>${rows}</div>`;
                    },
                },
                markers: {
                    size: showMarkers ? 4 : 0,
                    strokeWidth: 2,
                    strokeColors: colors,
                    fillOpacity: 1,
                    hover: { size: 7, sizeOffset: 2 },
                },
            });
            this.charts.invalid.render();
            this.applyHiddenSeries('invalid');
        },
        renderCharts() {
            if (!window.ApexCharts) return;
            this.renderAreaChart();
            this.renderInvalidChart();
        },
    };
}
</script>
@endsection
