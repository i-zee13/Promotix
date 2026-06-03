@extends('layouts.admin')

@section('title', 'Bot Protection | Dashboard')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="botProtectionFigma()" x-init="init()">
    <section class="mx-auto w-full px-[12px] pb-[24px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        {{-- Header --}}
        <div class="mb-[18px] flex flex-col gap-[14px] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Bot Protection</h1>
                <span class="hidden h-[34px] w-[2px] bg-[#a9a9a9] sm:block sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Dashboard</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)]">
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaigns</span>
                    <select x-model="filters.domain_id" @change="reload()" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option value="">All campaigns</option>
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <input x-model="filters.path" @input="scheduleReload()" placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
                <button type="button" @click="window.dispatchEvent(new CustomEvent('promotix:open-date-calendar'))" class="figma-filter-action flex w-[34px] shrink-0 items-center justify-center bg-[#6400B2] text-white" aria-label="Date range">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M4 11h16M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                </button>
            </div>
        </div>

        <div class="figma-bp-dashboard">
            {{-- Row 1: Area chart + vertical pill bars (Figma) --}}
            <div class="figma-bp-visits-row">
                <section class="figma-bp-visits-card min-w-0">
                    <h2 class="figma-bp-visits-title">Total Visits Breakdown</h2>
                    <div class="figma-bp-legend">
                        <span><i style="background:#fff"></i>Valid Visits</span>
                        <span><i style="background:#B893D8"></i>Bad Bots</span>
                        <span><i style="background:#6625F8"></i>Crawler</span>
                        <span><i style="background:#FF4BC1"></i>Invalid</span>
                        <span><i style="background:#D9D9D9"></i>Total Visits</span>
                    </div>
                    <canvas id="bp-area-chart" class="figma-bp-area-canvas"></canvas>
                </section>
                <div class="figma-bp-bars-col">
                    <article class="figma-bp-pill-card">
                        <p>Total Valid Visits</p>
                        <div class="figma-bp-pill-track">
                            <div class="figma-bp-pill-fill bg-[#B893D8]" :style="`height:${barPct(summary.valid_visits)}%`"></div>
                        </div>
                        <p class="figma-bp-pill-value" x-text="fmt(summary.valid_visits)"></p>
                    </article>
                    <article class="figma-bp-pill-card">
                        <p>Invalid bot Visits</p>
                        <div class="figma-bp-pill-track">
                            <div class="figma-bp-pill-fill bg-[#FF4BC1]" :style="`height:${barPct(summary.invalid_bot_visits)}%`"></div>
                        </div>
                        <p class="figma-bp-pill-value" x-text="fmt(summary.invalid_bot_visits)"></p>
                    </article>
                    <article class="figma-bp-pill-card">
                        <p>Known Crawlers</p>
                        <div class="figma-bp-pill-track">
                            <div class="figma-bp-pill-fill bg-white/75" :style="`height:${barPct(summary.known_crawlers)}%`"></div>
                        </div>
                        <p class="figma-bp-pill-value" x-text="fmt(summary.known_crawlers)"></p>
                    </article>
                </div>
            </div>

            {{-- Row 2: Invalid line chart + 3 donuts --}}
            <div class="figma-bp-invalid-row">
                <section class="figma-bp-invalid-card min-w-0">
                    <h2 class="figma-bp-invalid-title">Invalid Traffic Breakdown</h2>
                    <div class="figma-bp-week-stats">
                        <div>
                            <span class="flex items-center gap-[6px] text-[9px] text-white/75"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-white"></i>This Week</span>
                            <strong x-text="fmtCompact(thisWeekInvalid)"></strong>
                        </div>
                        <div>
                            <span class="flex items-center gap-[6px] text-[9px] text-white/75"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-[#FF4BC1]"></i>Last Week</span>
                            <strong x-text="fmtCompact(lastWeekInvalid)"></strong>
                        </div>
                    </div>
                    <div class="mt-[6px] flex flex-wrap gap-[12px] text-[9px] text-white/80">
                        <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-white"></i>Invalid Pageloads</span>
                        <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-[#FF4BC1]"></i>Invalid Site Interaction</span>
                    </div>
                    <canvas id="bp-invalid-line" class="figma-bp-invalid-canvas"></canvas>
                </section>
                <div class="figma-bp-donuts-col">
                    <section class="figma-bp-donut-card">
                        <h3>Threat Groups</h3>
                        <canvas id="bp-threat-donut" class="figma-bp-donut-canvas"></canvas>
                    </section>
                    <section class="figma-bp-donut-card">
                        <h3 x-text="`Invalid Bot Activity ${botActivityTotal}`"></h3>
                        <canvas id="bp-bot-donut" class="figma-bp-donut-canvas"></canvas>
                        <div class="figma-bp-donut-legend" id="bp-bot-legend"></div>
                    </section>
                    <section class="figma-bp-donut-card">
                        <h3>Invalid Malicious</h3>
                        <canvas id="bp-malicious-donut" class="figma-bp-donut-canvas"></canvas>
                    </section>
                </div>
            </div>

            {{-- Traffic detail pills: VPN, data center, invalid traffic --}}
            <div class="figma-bp-details-row mb-[12px] grid gap-[10px] sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-[8px] border border-white/15 bg-[#1a1a1a] px-[12px] py-[10px]">
                    <p class="text-[9px] uppercase text-white/55">Invalid pageloads</p>
                    <p class="mt-[4px] text-[18px] font-semibold text-white" x-text="fmt(invalidTrends.stats?.pageloads ?? 0)"></p>
                </article>
                <article class="rounded-[8px] border border-white/15 bg-[#1a1a1a] px-[12px] py-[10px]">
                    <p class="text-[9px] uppercase text-white/55">Invalid interactions</p>
                    <p class="mt-[4px] text-[18px] font-semibold text-white" x-text="fmt(invalidTrends.stats?.interactions ?? 0)"></p>
                </article>
                <article class="rounded-[8px] border border-white/15 bg-[#1a1a1a] px-[12px] py-[10px] sm:col-span-2">
                    <p class="mb-[6px] text-[9px] uppercase text-white/55">Invalid bot breakdown (VPN, data center, rate limit)</p>
                    <div class="flex flex-wrap gap-[8px]">
                        <template x-for="(label, idx) in (cache.ib?.labels ?? [])" :key="label">
                            <span class="rounded bg-[#6400B2]/40 px-[8px] py-[4px] text-[10px] text-white">
                                <span x-text="threatLabel(label)"></span>: <strong x-text="fmt((cache.ib?.values ?? [])[idx] ?? 0)"></strong>
                            </span>
                        </template>
                        <span x-show="!(cache.ib?.labels?.length)" class="text-[10px] text-white/45">No VPN / data center signals in this range.</span>
                    </div>
                </article>
            </div>

            <div class="figma-bp-details-row mb-[12px] grid gap-[10px] lg:grid-cols-2">
                <section class="rounded-[8px] border border-white/15 bg-[#1a1a1a] p-[12px]">
                    <h3 class="mb-[8px] text-[11px] font-semibold uppercase text-white/70">Threat groups</h3>
                    <template x-for="(label, idx) in (cache.th?.labels ?? [])" :key="'th-'+label">
                        <div class="flex items-center justify-between border-b border-white/5 py-[6px] text-[11px] text-white/85">
                            <span x-text="threatLabel(label)"></span>
                            <strong x-text="fmt((cache.th?.values ?? [])[idx] ?? 0)"></strong>
                        </div>
                    </template>
                    <p x-show="!(cache.th?.labels?.length)" class="text-[10px] text-white/45">No threat group data.</p>
                </section>
                <section class="rounded-[8px] border border-white/15 bg-[#1a1a1a] p-[12px]">
                    <h3 class="mb-[8px] text-[11px] font-semibold uppercase text-white/70">Invalid malicious activity</h3>
                    <template x-for="(label, idx) in (cache.mal?.labels ?? [])" :key="'mal-'+label">
                        <div class="flex items-center justify-between border-b border-white/5 py-[6px] text-[11px] text-white/85">
                            <span x-text="threatLabel(label)"></span>
                            <strong x-text="fmt((cache.mal?.values ?? [])[idx] ?? 0)"></strong>
                        </div>
                    </template>
                    <p x-show="!(cache.mal?.labels?.length)" class="text-[10px] text-white/45">No malicious activity logged.</p>
                </section>
            </div>

            {{-- Row 3: Domain + Country tables side by side (Figma) --}}
            <div class="figma-bp-tables-row">
                <section class="figma-bp-table min-w-0">
                    <div class="figma-bp-table-head figma-bp-table-head--domain">
                        <span>Domain</span>
                        <span class="text-center">Total Valid Visits</span>
                        <span class="text-center">Invalid Traffic</span>
                        <span class="text-center">Known Crawlers</span>
                        <span></span>
                    </div>
                    <div class="figma-bp-table-body">
                        <template x-for="row in domainsList" :key="row.id">
                            <div class="figma-bp-table-row--domain">
                                <span class="truncate font-medium" x-text="row.hostname"></span>
                                <span class="text-center" x-text="fmt(row.valid_visits)"></span>
                                <span class="text-center" x-text="fmt(row.invalid_visits)"></span>
                                <span class="text-center" x-text="fmt(row.known_crawlers)"></span>
                                <a :href="`{{ url('/domains') }}/${row.id}/setup`" class="figma-bp-protect-btn">Get Protected</a>
                            </div>
                        </template>
                        <p x-show="domainsList.length === 0" class="px-[14px] py-[20px] text-center text-[11px] text-[#a9a9a9]">No domains in this window.</p>
                    </div>
                </section>
                <section class="figma-bp-table min-w-0">
                    <div class="figma-bp-table-head figma-bp-table-head--country" style="grid-template-columns: 1.4fr 0.8fr 0.8fr 0.6fr;">
                        <span>Country</span>
                        <span class="text-right">Visits</span>
                        <span class="text-right">Invalid</span>
                        <span class="text-right">%</span>
                    </div>
                    <div class="figma-bp-table-body">
                        <template x-for="row in countries" :key="row.country">
                            <div class="figma-bp-table-row--country" style="grid-template-columns: 1.4fr 0.8fr 0.8fr 0.6fr;">
                                <span class="flex items-center gap-[6px] truncate">
                                    <span class="inline-block h-[10px] w-[14px] shrink-0 rounded-[2px] bg-white/30"></span>
                                    <span x-text="countryLabel(row.country)"></span>
                                </span>
                                <span class="text-right" x-text="fmt(row.total ?? 0)"></span>
                                <span class="text-right" x-text="fmt(row.invalid)"></span>
                                <span class="text-right" x-text="(row.percent ?? 0) + '%'"></span>
                            </div>
                        </template>
                        <p x-show="countries.length === 0" class="px-[12px] py-[16px] text-center text-[10px] text-[#a9a9a9]">No country data.</p>
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
function botProtectionFigma() {
    const countryNames = { US: 'United states', GB: 'United Kingdom', DE: 'Germany', PK: 'Pakistan', AE: 'UAE', CA: 'Canada', FR: 'France', IN: 'India' };

    return {
        filters: { domain_id: '', path: '', from: '', to: '' },
        summary: { total_visits: 0, valid_visits: 0, invalid_bot_visits: 0, known_crawlers: 0 },
        countries: [],
        domainsList: [],
        invalidTrends: { labels: [], datasets: [], stats: { pageloads: 0, interactions: 0 } },
        cache: {},
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
        barPct(n) {
            const max = Math.max(this.summary.valid_visits, this.summary.invalid_bot_visits, this.summary.known_crawlers, 1);
            return Math.max(8, Math.round((Number(n || 0) / max) * 100));
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
        async init() {
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const t = new Date().toISOString().slice(0, 10);
                this.filters.from = t;
                this.filters.to = t;
            }
            await this.reload();
            window.addEventListener('promotix:date-range', () => {
                this.syncHeaderDates();
                this.scheduleReload();
            });
            window.addEventListener('resize', () => {
                clearTimeout(window.__bpFigmaResize);
                window.__bpFigmaResize = setTimeout(() => this.render(), 180);
            });
        },
        async reload() {
            try {
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
                this.domainsList = ds;
                this.cache = {
                    traffic,
                    th,
                    ib: ib?.invalid_bot ?? { labels: [], values: [] },
                    mal: ib?.invalid_malicious ?? { labels: [], values: [] },
                };
                this.$nextTick(() => this.render());
            } finally {
                window.promotixPageLoader?.hide();
            }
        },
        canvas(id) {
            const el = document.getElementById(id);
            if (!el) return null;
            const dpr = window.devicePixelRatio || 1;
            const w = el.clientWidth, h = el.clientHeight;
            el.width = Math.max(1, w * dpr);
            el.height = Math.max(1, h * dpr);
            const ctx = el.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);
            return { ctx, w, h };
        },
        drawStackedArea(id, labels, datasets) {
            const c = this.canvas(id);
            if (!c || !labels.length) return;
            const { ctx, w, h } = c;
            const areas = datasets.filter(d => !d.line);
            const lineDs = datasets.find(d => d.line);
            const left = 44, right = 12, top = 12, bottom = 26;
            const max = Math.max(...datasets.flatMap(d => d.values || []), 1);
            const xStep = (w - left - right) / Math.max(labels.length - 1, 1);

            const yAt = v => h - bottom - (Number(v) / max) * (h - top - bottom);
            const xAt = i => left + i * xStep;

            ctx.strokeStyle = 'rgba(255,255,255,.08)';
            ctx.fillStyle = 'rgba(255,255,255,.45)';
            ctx.font = '8px Inter, sans-serif';
            for (let i = 0; i <= 4; i++) {
                const y = top + i * ((h - top - bottom) / 4);
                ctx.beginPath();
                ctx.moveTo(left, y);
                ctx.lineTo(w - right, y);
                ctx.stroke();
                const val = Math.round(max - (max / 4) * i);
                const label = val >= 1000 ? Math.round(val / 1000) + 'k' : String(val);
                ctx.fillText(label, 4, y + 3);
            }

            let baseline = labels.map(() => 0);
            areas.forEach(ds => {
                const pts = ds.values.map((v, i) => ({ x: xAt(i), y: yAt(baseline[i] + Number(v || 0)), v: Number(v || 0) }));
                const topPts = [...pts].reverse().map((p, ri) => {
                    const i = labels.length - 1 - ri;
                    const y = yAt(baseline[i]);
                    return { x: p.x, y };
                });
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                topPts.forEach(p => ctx.lineTo(p.x, p.y));
                ctx.closePath();
                ctx.fillStyle = ds.color + '99';
                ctx.fill();
                baseline = baseline.map((b, i) => b + Number(ds.values[i] || 0));
            });

            if (lineDs) {
                const pts = lineDs.values.map((v, i) => ({ x: xAt(i), y: yAt(v) }));
                ctx.strokeStyle = lineDs.color || '#D9D9D9';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                ctx.stroke();
            }

            ctx.fillStyle = 'rgba(255,255,255,.5)';
            ctx.font = '9px Inter, sans-serif';
            labels.forEach((l, i) => {
                if (i % Math.ceil(labels.length / 6 || 1) === 0) ctx.fillText(String(l), xAt(i) - 12, h - 6);
            });
        },
        drawTrendLine(id, labels, datasets) {
            const c = this.canvas(id);
            if (!c) return;
            const { ctx, w, h } = c;
            const series = datasets.map(d => ({ ...d, values: d.values || [] }));
            const max = Math.max(...series.flatMap(d => d.values), 1);
            const left = 28, right = 10, top = 8, bottom = 24;
            const xStep = (w - left - right) / Math.max(labels.length - 1, 1);
            const yAt = v => h - bottom - (Number(v) / max) * (h - top - bottom);

            ctx.strokeStyle = 'rgba(255,255,255,.12)';
            for (let i = 0; i < 5; i++) {
                const y = top + i * ((h - top - bottom) / 4);
                ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(w - right, y); ctx.stroke();
            }

            series.forEach(ds => {
                const pts = ds.values.map((v, i) => ({ x: left + i * xStep, y: yAt(v) }));
                ctx.strokeStyle = ds.color || '#fff';
                ctx.lineWidth = ds.dashed ? 1 : 1.5;
                if (ds.dashed) ctx.setLineDash([4, 4]); else ctx.setLineDash([]);
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                ctx.stroke();
                ctx.setLineDash([]);
            });

            ctx.fillStyle = '#9D9D9D';
            ctx.font = '9px Inter, sans-serif';
            labels.forEach((l, i) => ctx.fillText(String(l).slice(0, 3), left + i * xStep - 8, h - 6));
        },
        drawDonut(id, labels, values, showLegendId) {
            const c = this.canvas(id);
            if (!c) return;
            const { ctx, w, h } = c;
            const total = values.reduce((a, b) => a + b, 0) || 1;
            const cx = w / 2, cy = h / 2, r = Math.min(cx, cy) - 6;
            const palette = ['#FFFFFF', '#B893D8', '#6625F8', '#FF4BC1', '#10B981'];
            let start = -Math.PI / 2;
            values.forEach((v, i) => {
                const slice = (v / total) * Math.PI * 2;
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, r, start, start + slice);
                ctx.closePath();
                ctx.fillStyle = palette[i % palette.length];
                ctx.fill();
                start += slice;
            });
            ctx.fillStyle = '#4a0088';
            ctx.beginPath();
            ctx.arc(cx, cy, r * 0.55, 0, Math.PI * 2);
            ctx.fill();
            if (showLegendId && labels.length) {
                const el = document.getElementById(showLegendId);
                if (el) {
                    el.innerHTML = labels.slice(0, 3).map((l, i) => {
                        const name = String(l).replace(/_/g, ' ');
                        const titled = name.charAt(0).toUpperCase() + name.slice(1);
                        return `<div class="truncate">${titled}: ${values[i]}</div>`;
                    }).join('');
                }
            }
        },
        render() {
            const { traffic, th, ib, mal } = this.cache;
            this.drawStackedArea('bp-area-chart', traffic?.labels ?? [], traffic?.datasets ?? []);
            this.drawTrendLine('bp-invalid-line', this.invalidTrends.labels ?? [], this.invalidTrends.datasets ?? []);
            this.drawDonut('bp-threat-donut', th?.labels ?? [], th?.values ?? []);
            this.drawDonut('bp-bot-donut', ib?.labels ?? [], ib?.values ?? [], 'bp-bot-legend');
            this.drawDonut('bp-malicious-donut', mal?.labels ?? [], mal?.values ?? []);
        },
    };
}
</script>
@endsection
