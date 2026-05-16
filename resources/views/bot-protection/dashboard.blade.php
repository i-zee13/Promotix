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
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Campaigns</span>
                    <select x-model="filters.domain_id" @change="reload()" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option value="">All campaigns</option>
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Filter by path</span>
                    <input x-model.debounce.350ms="filters.path" @input="reload()" placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
                <button type="button" @click="openDatePicker()" class="figma-filter-action flex w-[34px] shrink-0 items-center justify-center bg-[#6400B2] text-white" aria-label="Date range">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M4 11h16M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                </button>
            </div>
        </div>

        <input type="date" x-ref="fromPicker" x-model="filters.from" @change="reload()" class="sr-only" tabindex="-1" aria-hidden="true">
        <input type="date" x-ref="toPicker" x-model="filters.to" @change="reload()" class="sr-only" tabindex="-1" aria-hidden="true">

        <div class="grid grid-cols-1 gap-[12px] xl:grid-cols-[minmax(0,1fr)_220px]">
            {{-- Main column --}}
            <div class="min-w-0 space-y-[12px]">
                {{-- Total Visits Breakdown (area) --}}
                <section class="overflow-hidden rounded-[12px] border border-[#6706b3] bg-gradient-to-b from-[#6400B2] to-[#4a0088] p-[16px] shadow-[0_0_24px_rgba(100,0,179,.35)]">
                    <div class="mb-[10px] flex flex-wrap items-start justify-between gap-[8px]">
                        <div>
                            <h2 class="text-[18px] font-normal text-white sm:text-[20px]">Total Visits Breakdown</h2>
                            <div class="mt-[8px] flex flex-wrap gap-x-[14px] gap-y-[4px] text-[9px] text-white/85">
                                <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-white"></i>Valid Visits</span>
                                <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-[#B893D8]"></i>Bad Bots</span>
                                <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-[#6625F8]"></i>Crawler</span>
                                <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-[#FF4BC1]"></i>Invalid</span>
                                <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-[#D9D9D9]"></i>Total Visits</span>
                            </div>
                        </div>
                        <button type="button" class="text-white/60 hover:text-white" aria-label="More">
                            <svg class="h-[18px] w-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 17a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
                        </button>
                    </div>
                    <canvas id="bp-area-chart" class="h-[240px] w-full sm:h-[260px]"></canvas>
                </section>

                {{-- Invalid Traffic Breakdown --}}
                <section class="relative overflow-hidden rounded-[12px] border border-[#6706b3]/60 bg-[#121212] p-[16px]">
                    <div class="absolute left-1/2 top-[12px] z-10 flex -translate-x-1/2 gap-[6px]">
                        <span class="rounded-[4px] bg-[#2563eb] px-[10px] py-[3px] text-[11px] font-semibold text-white" x-text="fmt(invalidTrends.stats?.pageloads ?? 0)"></span>
                        <span class="rounded-[4px] bg-[#2563eb] px-[10px] py-[3px] text-[11px] font-semibold text-white" x-text="fmt(invalidTrends.stats?.interactions ?? 0)"></span>
                    </div>
                    <div class="mb-[8px] flex flex-wrap items-center justify-between gap-[8px] pt-[28px]">
                        <h2 class="text-[16px] font-normal text-[#a9a9a9] sm:text-[18px]">Invalid Traffic Breakdown</h2>
                        <div class="flex flex-wrap gap-[12px] text-[9px] text-white/80">
                            <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-white"></i>Invalid Pageloads</span>
                            <span class="flex items-center gap-[5px]"><i class="inline-block h-[8px] w-[8px] rounded-[1px] bg-[#FF4BC1]"></i>Invalid Site Interaction</span>
                        </div>
                    </div>
                    <canvas id="bp-invalid-line" class="h-[200px] w-full"></canvas>
                </section>

                {{-- Domain table --}}
                <section class="overflow-hidden rounded-[10px] border border-[#6706b3]">
                    <div class="grid grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))_auto] gap-[8px] bg-[#6400B2] px-[14px] py-[10px] text-[10px] font-medium text-white sm:text-[11px]">
                        <span>Domain</span>
                        <span class="text-center">Total Valid Visits</span>
                        <span class="text-center">Invalid Traffic</span>
                        <span class="text-center">Known Crawlers</span>
                        <span class="w-[88px]"></span>
                    </div>
                    <div class="max-h-[280px] overflow-y-auto">
                        <template x-for="row in domainsList" :key="row.id">
                            <div class="grid grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))_auto] items-center gap-[8px] border-b border-[#6706b3]/30 bg-[#d9d9d9] px-[14px] py-[10px] text-[10px] text-[#121212] last:border-b-0 sm:text-[11px]">
                                <span class="truncate font-medium" x-text="row.hostname"></span>
                                <span class="text-center" x-text="fmt(row.valid_visits)"></span>
                                <span class="text-center" x-text="fmt(row.invalid_visits)"></span>
                                <span class="text-center" x-text="fmt(row.known_crawlers)"></span>
                                <a :href="`{{ url('/domains') }}/${row.id}/setup`" class="inline-flex h-[24px] w-[88px] items-center justify-center rounded-[6px] bg-[#6400B2] text-[9px] font-medium text-white hover:bg-[#7B13C8]">Get Protected</a>
                            </div>
                        </template>
                        <p x-show="domainsList.length === 0" class="px-[14px] py-[20px] text-center text-[11px] text-[#a9a9a9]">No domains in this window.</p>
                    </div>
                </section>
            </div>

            {{-- Right column (in-page, Figma) --}}
            <aside class="min-w-0 space-y-[12px]">
                <div class="grid grid-cols-3 gap-[8px]">
                    <article class="flex min-h-[140px] flex-col rounded-[10px] border border-[#6706b3] bg-[#6400B2] p-[8px] text-center">
                        <p class="text-[8px] leading-tight text-white/90">Total Valid Visits</p>
                        <div class="mx-auto mt-auto flex h-[90px] w-[28px] items-end justify-center rounded-[14px] bg-[#4a0088]/80">
                            <div class="w-full rounded-[14px] bg-[#B893D8]" :style="`height:${barPct(summary.valid_visits)}%`"></div>
                        </div>
                        <p class="mt-[6px] text-[11px] font-semibold text-white" x-text="fmt(summary.valid_visits)"></p>
                    </article>
                    <article class="flex min-h-[140px] flex-col rounded-[10px] border border-[#6706b3] bg-[#6400B2] p-[8px] text-center">
                        <p class="text-[8px] leading-tight text-white/90">Invalid bot Visits</p>
                        <div class="mx-auto mt-auto flex h-[90px] w-[28px] items-end justify-center rounded-[14px] bg-[#4a0088]/80">
                            <div class="w-full rounded-[14px] bg-[#FF4BC1]" :style="`height:${barPct(summary.invalid_bot_visits)}%`"></div>
                        </div>
                        <p class="mt-[6px] text-[11px] font-semibold text-white" x-text="fmt(summary.invalid_bot_visits)"></p>
                    </article>
                    <article class="flex min-h-[140px] flex-col rounded-[10px] border border-[#6706b3] bg-[#6400B2] p-[8px] text-center">
                        <p class="text-[8px] leading-tight text-white/90">Known Crawlers</p>
                        <div class="mx-auto mt-auto flex h-[90px] w-[28px] items-end justify-center rounded-[14px] bg-[#4a0088]/80">
                            <div class="w-full rounded-[14px] bg-white/70" :style="`height:${barPct(summary.known_crawlers)}%`"></div>
                        </div>
                        <p class="mt-[6px] text-[11px] font-semibold text-white" x-text="fmt(summary.known_crawlers)"></p>
                    </article>
                </div>

                <section class="rounded-[10px] border border-[#6706b3] bg-[#6400B2] p-[10px]">
                    <h3 class="mb-[6px] text-center text-[11px] text-white/90">Threat Groups</h3>
                    <canvas id="bp-threat-donut" class="mx-auto h-[100px] w-full max-w-[140px]"></canvas>
                </section>

                <section class="rounded-[10px] border border-[#6706b3] bg-[#6400B2] p-[10px]">
                    <h3 class="mb-[4px] text-center text-[11px] text-white/90">Invalid Bot Activity</h3>
                    <canvas id="bp-bot-donut" class="mx-auto h-[100px] w-full max-w-[140px]"></canvas>
                    <div class="mt-[4px] space-y-[2px] text-[8px] text-white/80" id="bp-bot-legend"></div>
                </section>

                <section class="rounded-[10px] border border-[#6706b3] bg-[#6400B2] p-[10px]">
                    <h3 class="mb-[6px] text-center text-[11px] text-white/90">Invalid Malicious</h3>
                    <canvas id="bp-malicious-donut" class="mx-auto h-[100px] w-full max-w-[140px]"></canvas>
                </section>

                <section class="overflow-hidden rounded-[10px] border border-[#6706b3]">
                    <div class="grid grid-cols-[minmax(0,1fr)_56px_42px] gap-[6px] bg-[#6400B2] px-[10px] py-[8px] text-[9px] font-medium text-white">
                        <span>Country</span>
                        <span class="text-right">Invalid Traffic</span>
                        <span class="text-right">% of All</span>
                    </div>
                    <div class="max-h-[200px] overflow-y-auto bp-country-scroll">
                        <template x-for="row in countries" :key="row.country">
                            <div class="grid grid-cols-[minmax(0,1fr)_56px_42px] items-center gap-[6px] border-b border-white/10 bg-[#1a1a1a] px-[10px] py-[8px] text-[9px] text-[#d9d9d9]">
                                <span class="flex items-center gap-[6px] truncate">
                                    <span class="inline-block h-[10px] w-[14px] shrink-0 rounded-[2px] bg-white/30"></span>
                                    <span x-text="countryLabel(row.country)"></span>
                                </span>
                                <span class="text-right" x-text="fmt(row.invalid)"></span>
                                <span class="text-right" x-text="(row.percent ?? 0) + '%'"></span>
                            </div>
                        </template>
                        <p x-show="countries.length === 0" class="px-[10px] py-[14px] text-center text-[9px] text-[#a9a9a9]">No country data.</p>
                    </div>
                </section>
            </aside>
        </div>

        <p class="mt-[12px] text-right">
            <a href="{{ route('bot-protection.advanced') }}" class="text-[11px] text-[#a9a9a9] hover:text-white hover:underline">Open Advanced View →</a>
        </p>
    </section>
</div>

<style>
.bp-country-scroll { scrollbar-width: thin; scrollbar-color: #6400B2 transparent; }
.bp-country-scroll::-webkit-scrollbar { width: 4px; }
.bp-country-scroll::-webkit-scrollbar-thumb { background: #6400B2; border-radius: 3px; }
</style>

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
        countryLabel(code) { return countryNames[code] || code || 'Unknown'; },
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
        openDatePicker() {
            this.$refs.fromPicker?.showPicker?.() || this.$refs.fromPicker?.click();
        },
        async init() {
            const today = new Date();
            const start = new Date(today.getTime() - 6 * 86400000);
            this.filters.from = start.toISOString().slice(0, 10);
            this.filters.to = today.toISOString().slice(0, 10);
            await this.reload();
            window.addEventListener('resize', () => {
                clearTimeout(window.__bpFigmaResize);
                window.__bpFigmaResize = setTimeout(() => this.render(), 180);
            });
        },
        async reload() {
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
            this.cache = { traffic, th, ib: ib?.invalid_bot ?? { labels: [], values: [] }, mal: ib?.invalid_malicious ?? { labels: [], values: [] } };
            this.$nextTick(() => this.render());
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
            const left = 32, right = 12, top = 12, bottom = 26;
            const max = Math.max(...datasets.flatMap(d => d.values || []), 1);
            const xStep = (w - left - right) / Math.max(labels.length - 1, 1);

            const yAt = v => h - bottom - (Number(v) / max) * (h - top - bottom);
            const xAt = i => left + i * xStep;

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
                if (el) el.innerHTML = labels.slice(0, 3).map((l, i) =>
                    `<div class="truncate">${l}: ${values[i]}</div>`
                ).join('');
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
