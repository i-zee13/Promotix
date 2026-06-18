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
                                <span><i style="background:#fff"></i>Valid Visits</span>
                                <span><i style="background:#0D0D0D;border:1px solid rgba(255,255,255,.25)"></i>Bad Bots</span>
                                <span><i style="background:#6625F8"></i>Crawler</span>
                                <span><i style="background:#FF4BC1"></i>Invalid</span>
                                <span><i style="background:#B893D8"></i>Total Visits</span>
                            </div>
                            <div class="relative" x-data="{ cardMenu: false }">
                                <button type="button" @click.stop="cardMenu = !cardMenu" class="figma-platform-kebab" aria-label="Chart options">
                                    <span class="flex flex-col items-center gap-[4px]" aria-hidden="true">
                                        <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
                                        <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
                                        <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
                                    </span>
                                </button>
                                <div x-show="cardMenu" x-cloak @click.outside="cardMenu = false" class="figma-bp-card-menu">
                                    <button type="button" @click="reload(); cardMenu = false">Refresh data</button>
                                    <a href="{{ route('bot-protection.advanced') }}">Advanced view</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <canvas id="bp-area-chart" class="figma-bp-area-canvas"></canvas>
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
                        <span><i style="background:#6625F8"></i>Invalid Pageloads</span>
                        <span><i style="background:#FF4BC1"></i>Invalid Site Interaction</span>
                    </div>
                    <div class="figma-bp-invalid-chart-wrap">
                        <canvas id="bp-invalid-line" class="figma-bp-invalid-canvas"></canvas>
                        <div id="bp-invalid-tooltip" class="figma-bp-chart-tooltip" hidden></div>
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
                            <div class="figma-bp-donut-ring" :style="donutRingStyle(cache.ib?.values)" role="img" aria-label="Invalid bot activity chart">
                                <span class="figma-bp-donut-hole"><span class="figma-bp-donut-hole__text" x-text="donutTotal(cache.ib?.values)"></span></span>
                            </div>
                            <div class="figma-bp-donut-legend">
                                <template x-for="(label, i) in (cache.ib?.labels || []).slice(0, 3)" :key="label + i">
                                    <div class="truncate" x-text="donutLegendLine(label, cache.ib?.values?.[i])"></div>
                                </template>
                            </div>
                        </section>
                        <section class="figma-bp-donut-card">
                            <h3>Invalid Malicious</h3>
                            <div class="figma-bp-donut-ring" :style="donutRingStyle(cache.mal?.values)" role="img" aria-label="Invalid malicious chart">
                                <span class="figma-bp-donut-hole"><span class="figma-bp-donut-hole__text" x-text="donutTotal(cache.mal?.values)"></span></span>
                            </div>
                            <div class="figma-bp-donut-legend" x-show="(cache.mal?.labels || []).length">
                                <template x-for="(label, i) in (cache.mal?.labels || []).slice(0, 2)" :key="label + i">
                                    <div class="truncate" x-text="donutLegendLine(label, cache.mal?.values?.[i])"></div>
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
                                <div class="figma-bp-table-row--country">
                                    <span class="flex min-w-0 items-center gap-[4px] truncate">
                                        <span class="inline-block h-[8px] w-[12px] shrink-0 rounded-[2px] bg-white/35"></span>
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
                                <a :href="`{{ url('/domains') }}/${row.id}/setup`" class="figma-bp-protect-btn">Get Protected</a>
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
    const lastWeek = wave(2800, 800, 1.2);

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
                { name: 'This Week', values: thisWeek, color: '#6625F8' },
                { name: 'Last Week', values: lastWeek, color: '#FF4BC1', dashed: true },
            ],
            stats: { pageloads: thisWeek.reduce((a, b) => a + b, 0), interactions: 1840 },
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
        domainsList: [],
        invalidTrends: { labels: [], datasets: [], stats: { pageloads: 0, interactions: 0 } },
        cache: {},
        donutPalette: ['#FFFFFF', '#B893D8', '#6625F8', '#FF4BC1'],
        charts: {},
        invalidHoverIndex: null,
        fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
        fmtCompact(n) {
            const v = Number(n || 0);
            if (v >= 1000) return Math.round(v / 1000) + 'k';
            return this.fmt(v);
        },
        countryLabel(code) { return countryNames[code] || code || 'Unknown'; },
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
        donutRingStyle(values) {
            const data = (values || []).map(v => Number(v || 0));
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
        hexAlpha(hex, alpha) {
            const h = String(hex || '#ffffff').replace('#', '');
            const full = h.length === 3 ? h.split('').map(c => c + c).join('') : h;
            const n = parseInt(full, 16);
            const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
            return `rgba(${r},${g},${b},${alpha})`;
        },
        areaGradient(chart, color, topAlpha = 0.55) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return color;
            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            g.addColorStop(0, this.hexAlpha(color, topAlpha));
            g.addColorStop(1, this.hexAlpha(color, 0.02));
            return g;
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
        bpChartScales(maxY) {
            const cap = this.bpYAxisCap(maxY);
            const step = this.bpYAxisStep(cap);
            return {
                x: {
                    grid: { color: 'rgba(255,255,255,0.06)', drawBorder: false },
                    ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 }, maxRotation: 0 },
                },
                y: {
                    min: 0,
                    max: cap,
                    grid: { color: 'rgba(255,255,255,0.08)', drawBorder: false },
                    ticks: {
                        color: 'rgba(255,255,255,0.45)',
                        font: { size: 8 },
                        stepSize: step,
                        callback: (v) => (v >= 1000 ? `${Math.round(v / 1000)}k` : v),
                    },
                },
            };
        },
        renderAreaChart() {
            const el = document.getElementById('bp-area-chart');
            if (!el || !window.Chart) return;
            this.destroyChart('area');
            const traffic = this.cache?.traffic ?? { labels: [], datasets: [] };
            const labels = traffic.labels || [];
            const datasets = traffic.datasets || [];
            if (!labels.length) return;

            const areas = datasets.filter(d => !d.line);
            const lineDs = datasets.find(d => d.line);
            const allValues = datasets.flatMap(d => d.values || []);
            const maxY = Math.max(...allValues, 0);
            const areaAlpha = {
                '#FFFFFF': 0.42,
                '#0D0D0D': 0.55,
                '#6625F8': 0.5,
                '#FF4BC1': 0.45,
            };

            const chartSets = areas.map((ds, idx) => ({
                label: ds.name,
                data: ds.values || [],
                borderColor: this.hexAlpha(ds.color, 0.85),
                backgroundColor: (ctx) => this.areaGradient(ctx.chart, ds.color, areaAlpha[ds.color] ?? 0.4),
                fill: 'origin',
                tension: 0.42,
                pointRadius: 0,
                borderWidth: 1,
                order: idx + 2,
            }));

            if (lineDs) {
                chartSets.push({
                    label: lineDs.name,
                    data: lineDs.values || [],
                    type: 'line',
                    borderColor: lineDs.color || '#B893D8',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.42,
                    pointRadius: 0,
                    borderWidth: 2,
                    order: 0,
                });
            }

            this.charts.area = new Chart(el, {
                type: 'line',
                data: { labels, datasets: chartSets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.9)',
                            titleColor: '#fff',
                            bodyColor: 'rgba(255,255,255,0.85)',
                            padding: 10,
                        },
                    },
                    scales: this.bpChartScales(maxY),
                },
            });
        },
        renderInvalidChart() {
            const el = document.getElementById('bp-invalid-line');
            const tip = document.getElementById('bp-invalid-tooltip');
            if (!el || !window.Chart) return;
            this.destroyChart('invalid');

            const labels = this.invalidTrends.labels ?? [];
            const datasets = (this.invalidTrends.datasets ?? []).map(d => ({ ...d, values: d.values || [] }));
            if (!labels.length) return;

            const primary = datasets.find(d => !d.dashed) || datasets[0];
            const compare = datasets.find(d => d.dashed) || datasets[1];
            const maxY = Math.max(...datasets.flatMap(d => d.values), 0);
            const yCap = this.bpYAxisCap(maxY);
            const yStep = this.bpYAxisStep(yCap);

            const chartSets = [];
            if (primary) {
                chartSets.push({
                    label: primary.name || 'This Week',
                    data: primary.values,
                    borderColor: primary.color || '#6625F8',
                    backgroundColor: (ctx) => {
                        const { chart } = ctx;
                        const { chartArea } = chart;
                        if (!chartArea) return 'rgba(102,37,248,0.2)';
                        const g = chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        g.addColorStop(0, 'rgba(102,37,248,0.42)');
                        g.addColorStop(1, 'rgba(102,37,248,0.02)');
                        return g;
                    },
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    borderWidth: 1.5,
                    order: 1,
                });
            }
            if (compare) {
                chartSets.push({
                    label: compare.name || 'Last Week',
                    data: compare.values,
                    borderColor: compare.color || '#FF4BC1',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    borderWidth: 1.5,
                    borderDash: [6, 4],
                    order: 0,
                });
            }

            this.charts.invalid = new Chart(el, {
                type: 'line',
                data: { labels, datasets: chartSets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false },
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255,255,255,0.06)' },
                            ticks: { color: '#9D9D9D', font: { size: 9 } },
                        },
                        y: {
                            min: 0,
                            max: yCap,
                            grid: { color: 'rgba(255,255,255,0.08)' },
                            ticks: {
                                color: 'rgba(255,255,255,0.45)',
                                font: { size: 8 },
                                stepSize: yStep,
                                callback: (v) => (v >= 1000 ? `${Math.round(v / 1000)}k` : v),
                            },
                        },
                    },
                    onHover: (evt, elements) => {
                        if (!tip || !elements?.length) {
                            if (tip) tip.hidden = true;
                            return;
                        }
                        const idx = elements[0].index;
                        const label = labels[idx] || '';
                        const thisVal = Number(primary?.values?.[idx] || 0);
                        const lastVal = Number(compare?.values?.[idx] || 0);
                        tip.hidden = false;
                        tip.innerHTML = `<strong>${label}</strong><span><i style="background:#6625F8"></i>This Week: ${this.fmtCompact(thisVal)}</span><span><i style="background:#FF4BC1"></i>Last Week: ${this.fmtCompact(lastVal)}</span>`;
                        const rect = el.getBoundingClientRect();
                        const x = evt.x - rect.left;
                        tip.style.left = `${Math.min(Math.max(x, 56), rect.width - 56)}px`;
                        tip.style.top = '14px';
                    },
                },
            });

            el.onmouseleave = () => {
                if (tip) tip.hidden = true;
            };
        },
        renderCharts() {
            if (!window.Chart) return;
            this.renderAreaChart();
            this.renderInvalidChart();
        },
    };
}
</script>
@endsection
