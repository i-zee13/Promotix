@extends('layouts.admin')

@section('title', 'Paid Advertising | Dashboard')
@section('subtitle', 'Live campaign performance and detection results')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="paidAdvertisingFigma()" x-init="init()">
    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[22px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[25px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Advertising</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Dashboard</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)]">
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaigns</span>
                    <select x-model="filters.domain_id" @change="reload()" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option value="">All campaigns</option>
                        @foreach ($domains as $domain)
                            <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <input x-model.debounce.350ms="filters.path" @input="reload()" placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-[14px] md:grid-cols-2 xl:grid-cols-4">
            <article class="min-h-[158px] rounded-[10px] border border-white/40 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.35)]">
                <div class="flex items-start justify-between">
                    <h2 class="text-[13px] font-normal text-white">Total Traffic</h2>
                    <button class="text-white/75" aria-label="Refresh" @click="reload()">Refresh</button>
                </div>
                <div class="mt-[12px] grid grid-cols-2 text-center">
                    <div>
                        <p class="text-[10px] text-white/75">Paid Traffic</p>
                        <p class="text-[24px] font-semibold leading-none text-white" x-text="fmt(summary.paid_visits)"></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-white/75">Invalid Paid Traffic</p>
                        <p class="text-[24px] font-semibold leading-none text-white" x-text="fmt(summary.invalid_paid_visits)"></p>
                    </div>
                </div>
                <div class="mt-[11px] grid grid-cols-3 border-t border-white/25 pt-[7px] text-[9px] text-white/80">
                    <span>Traffic</span><span>Valid</span><span>Invalid</span>
                    <span class="text-white/60">Organic</span><span x-text="fmt(summary.valid_paid_visits)"></span><span x-text="fmt(summary.invalid_paid_visits)"></span>
                </div>
            </article>

            <article class="min-h-[158px] rounded-[10px] border border-white/40 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.35)]">
                <h2 class="text-[13px] font-normal text-white">Bot Protection</h2>
                <div class="grid grid-cols-[70px_1fr] items-end gap-[10px]">
                    <div class="pt-[15px]">
                        <p class="text-[30px] font-normal leading-none text-white"><span x-text="botRate"></span>%</p>
                        <p class="text-[18px] leading-none text-white">Bots</p>
                    </div>
                    <canvas id="bot-bars" class="h-[80px] w-full"></canvas>
                </div>
            </article>

            <article class="min-h-[158px] rounded-[10px] border border-white/40 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.35)]">
                <div class="flex items-start justify-between">
                    <h2 class="text-[13px] font-normal text-white">Blocking Activity</h2>
                    <span class="text-[12px] text-white/75">i</span>
                </div>
                <div class="mt-[10px] grid grid-cols-2 text-center">
                    <div>
                        <p class="text-[9px] text-white/70">Invalid Total Traffic</p>
                        <p class="text-[24px] font-semibold leading-none text-white" x-text="fmt(summary.invalid_paid_visits)"></p>
                    </div>
                    <div>
                        <p class="text-[9px] text-white/70">Total Blocked</p>
                        <p class="text-[24px] font-semibold leading-none text-white" x-text="fmt(summary.blocked_paid_visits)"></p>
                    </div>
                </div>
                <div class="mt-[7px] space-y-[3px] text-[9px] text-white/85">
                    <div class="flex justify-between border-b border-white/15"><span>IP Range</span><span x-text="fmt(summary.flagged_paid_visits)"></span></div>
                    <div class="flex justify-between border-b border-white/15"><span>Events</span><span x-text="fmt(summary.invalid_paid_visits)"></span></div>
                    <div class="flex justify-between"><span>Audiences</span><a href="{{ route('paid-marketing.detection-settings') }}" class="rounded bg-white px-[8px] py-[1px] text-[8px] text-[#6400B2]">Set up</a></div>
                </div>
            </article>

            <article class="min-h-[158px] rounded-[10px] border border-white/40 bg-[#6400B2] p-[16px] text-center shadow-[0_0_18px_rgba(100,0,179,.35)]">
                <h2 class="text-left text-[13px] font-normal text-white">Campaigns Breakdown</h2>
                <div class="mx-auto mt-[14px] flex h-[62px] w-[62px] items-center justify-center rounded-full border border-white/55">
                    <svg class="h-[34px] w-[34px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.4" d="M4 14l16-8-8 16-2-6-6-2z"/></svg>
                </div>
                <a href="{{ route('paid-marketing.detection-settings') }}" class="mt-[10px] inline-block text-[9px] text-white/85 hover:underline">Set Tracking Parameter</a>
            </article>
        </div>

        <div class="mt-[15px] grid grid-cols-1 gap-[17px] xl:grid-cols-[minmax(0,589px)_minmax(260px,1fr)]">
            <section class="min-h-[341px] rounded-[12px] border border-[#6400B2] bg-[#6400B2] p-[20px] shadow-[0_0_24px_rgba(100,0,179,.45)]">
                <div class="mb-[8px] flex flex-wrap items-center justify-between gap-[8px]">
                    <div class="flex items-center gap-[10px]">
                        <h2 class="text-[20px] font-normal text-[#a9a9a9]">Paid Traffic Trends</h2>
                        <span class="text-[12px] text-white"><i class="mr-[4px] inline-block h-[12px] w-[12px] rounded-[1px] bg-white"></i>Last Week</span>
                        <span class="text-[12px] text-white"><i class="mr-[4px] inline-block h-[12px] w-[12px] rounded-[1px] bg-[#6625F8]"></i>This Week</span>
                    </div>
                    <select x-model="filters.window" @change="setWindow()" class="h-[41px] rounded-full border border-white/30 bg-[#101010] px-[20px] text-[14px] text-white focus:border-[#9a1aff] focus:ring-1 focus:ring-[#9a1aff]/40">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <canvas id="paid-trends" class="h-[270px] w-full"></canvas>
            </section>

            <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-2 xl:grid-cols-2">
                <section class="min-h-[158px] rounded-[10px] border border-white/40 bg-[#6400B2] p-[14px]">
                    <h2 class="text-[16px] font-normal text-[#a9a9a9]">Heatmap</h2>
                    <div id="heatmap-grid" class="mt-[10px] grid grid-cols-8 gap-[3px]"></div>
                </section>

                <section class="min-h-[158px] rounded-[10px] border border-white/40 bg-[#6400B2] p-[14px]">
                    <h2 class="text-[16px] font-normal text-[#a9a9a9]">Keyword</h2>
                    <div id="keyword-list" class="mt-[10px] space-y-[6px]"></div>
                </section>

                <section class="min-h-[158px] rounded-[10px] border border-[#5a2a99] bg-[#090909] p-[14px] sm:col-span-2 xl:col-span-2">
                    <h2 class="text-[16px] font-normal text-[#a9a9a9]">Invalid Traffic Protection</h2>
                    <canvas id="invalid-protection" class="mt-[8px] h-[105px] w-full"></canvas>
                </section>
            </div>
        </div>

        <div class="mt-[15px] grid grid-cols-1 gap-[17px] xl:grid-cols-[minmax(0,585px)_minmax(260px,1fr)]">
            <section class="min-h-[451px] rounded-[10px] border border-[#5a2a99] bg-[#111111] p-[18px]">
                <div class="mb-[10px] flex flex-wrap items-center justify-between gap-[10px]">
                    <h2 class="text-[24px] font-semibold leading-none text-[#a9a9a9]">IP Address</h2>
                    <div class="flex h-[28px] items-center gap-[8px] rounded-[3px] bg-[#6400B2] px-[9px] text-[10px] text-white">
                        <span>Campaigns</span>
                        <select x-model="filters.domain_id" @change="reload()" class="h-[18px] rounded-[2px] border-0 bg-[#0B0B0B] px-[8px] py-0 text-[9px] text-white focus:ring-0">
                            <option value="">Select</option>
                            @foreach ($domains as $domain)
                                <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="max-h-[365px] overflow-auto rounded-[4px] border border-white/15">
                    <table class="min-w-[520px] w-full text-left text-[15px] text-[#a9a9a9]">
                        <thead class="sticky top-0 bg-[#6400B2]">
                            <tr>
                                <th class="px-[10px] py-[7px] font-normal">Address</th>
                                <th class="px-[10px] py-[7px] font-normal">Campaign</th>
                                <th class="px-[10px] py-[7px] font-normal">Last Click</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/15">
                            <template x-for="row in ips" :key="row.ip">
                                <tr>
                                    <td class="px-[10px] py-[7px]" x-text="row.ip"></td>
                                    <td class="px-[10px] py-[7px]">N/A</td>
                                    <td class="px-[10px] py-[7px]" x-text="dateLabel(row.last_seen)"></td>
                                </tr>
                            </template>
                            <tr x-show="ips.length === 0"><td colspan="3" class="px-[10px] py-[12px] text-center text-white/60">No IP data yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="relative min-h-[329px] overflow-hidden rounded-[10px] border border-[#6400B2] bg-[linear-gradient(180deg,rgba(217,217,217,.7)_21%,#6625F8_67%,rgba(0,0,0,.95)_100%)] p-[12px]">
                <div class="flex items-center justify-between">
                    <h2 class="text-[14px] font-normal text-[#343434]">Country Breakdown</h2>
                    <span class="text-[#343434]">i</span>
                </div>
                <div id="country-list" class="mt-[18px] space-y-[8px] text-[10px] text-white"></div>
                <div class="pointer-events-none absolute inset-x-0 bottom-[66px] mx-auto flex h-[118px] w-[118px] items-center justify-center rounded-[14px] border-2 border-white/90 bg-white/10 shadow-[0_12px_35px_rgba(0,0,0,.4)]">
                    <div class="h-[72px] w-[72px] rounded-b-full rounded-t-[18px] border border-white/80 bg-[#6400B2]/20"></div>
                </div>
                <a href="{{ route('upgrade-plan') }}" class="absolute bottom-[32px] left-1/2 -translate-x-1/2 rounded-[7px] bg-white px-[42px] py-[9px] text-[15px] text-[#6400B2] shadow-lg">Get Started</a>
            </section>
        </div>
    </section>
</div>

<script>
function paidAdvertisingFigma() {
    return {
        filters: { domain_id: '', path: '', window: 'weekly', from: '', to: '' },
        summary: { paid_visits: 0, invalid_paid_visits: 0, blocked_paid_visits: 0, flagged_paid_visits: 0, valid_paid_visits: 0 },
        trends: { labels: [], datasets: [] },
        blocking: { labels: [], datasets: [] },
        campaigns: [],
        keywords: [],
        countries: [],
        ips: [],
        heatmap: { days: [], hours: [], matrix: [] },
        get botRate() {
            const paid = Number(this.summary.paid_visits || 0);
            const invalid = Number(this.summary.invalid_paid_visits || 0);
            return paid ? Math.round((invalid / paid) * 100) : 0;
        },
        fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
        dateLabel(value) {
            if (!value) return 'N/A';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return 'N/A';
            return d.toLocaleDateString(undefined, {month: '2-digit', day: '2-digit', year: '2-digit'});
        },
        setWindow() {
            const today = new Date();
            const days = this.filters.window === 'monthly' ? 29 : 6;
            const start = new Date(today.getTime() - days * 86400000);
            this.filters.from = start.toISOString().slice(0, 10);
            this.filters.to = today.toISOString().slice(0, 10);
            this.reload();
        },
        qs() {
            const p = new URLSearchParams();
            if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
            if (this.filters.path) p.set('path', this.filters.path);
            if (this.filters.from) p.set('from', this.filters.from);
            if (this.filters.to) p.set('to', this.filters.to);
            return p.toString();
        },
        async init() {
            this.setWindow();
            window.addEventListener('resize', () => {
                clearTimeout(window.__paidFigmaResize);
                window.__paidFigmaResize = setTimeout(() => this.render(), 180);
            });
        },
        async reload() {
            const qs = this.qs();
            const [summary, trends, blocking, campaigns, keywords, countries, ips, heatmap] = await Promise.all([
                fetch(`/paid-marketing/summary?${qs}`).then(r => r.json()),
                fetch(`/paid-marketing/trends?${qs}`).then(r => r.json()),
                fetch(`/paid-marketing/blocking-activity?${qs}`).then(r => r.json()),
                fetch(`/paid-marketing/campaigns?${qs}`).then(r => r.json()),
                fetch(`/paid-marketing/keywords?${qs}`).then(r => r.json()),
                fetch(`/paid-marketing/countries?${qs}`).then(r => r.json()),
                fetch(`/paid-marketing/ips?${qs}`).then(r => r.json()),
                fetch(`/paid-marketing/heatmap?${qs}`).then(r => r.json()),
            ]);
            this.summary = summary;
            this.trends = trends;
            this.blocking = blocking;
            this.campaigns = campaigns;
            this.keywords = keywords;
            this.countries = countries;
            this.ips = ips;
            this.heatmap = heatmap;
            this.$nextTick(() => this.render());
        },
        render() {
            this.drawLine('paid-trends', this.trends.labels || [], this.trends.datasets || []);
            this.drawBars('bot-bars', this.trends.datasets?.[1]?.values || []);
            this.drawLine('invalid-protection', this.blocking.labels || [], this.blocking.datasets || [], true);
            this.renderHeatmap();
            this.renderKeywords();
            this.renderCountries();
        },
        canvas(id) {
            const canvas = document.getElementById(id);
            if (!canvas) return null;
            const dpr = window.devicePixelRatio || 1;
            const w = canvas.clientWidth;
            const h = canvas.clientHeight;
            canvas.width = Math.max(1, w * dpr);
            canvas.height = Math.max(1, h * dpr);
            const ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);
            return {ctx, w, h};
        },
        drawLine(id, labels, datasets, dark = false) {
            const c = this.canvas(id);
            if (!c) return;
            const {ctx, w, h} = c;
            const series = datasets.map(d => d.values || []);
            const max = Math.max(...series.flat(), 1);
            const left = 36, right = 14, top = 16, bottom = 28;
            ctx.strokeStyle = dark ? 'rgba(255,255,255,.16)' : 'rgba(255,255,255,.14)';
            ctx.lineWidth = 1;
            for (let i = 0; i < 6; i++) {
                const y = top + i * ((h - top - bottom) / 5);
                ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(w - right, y); ctx.stroke();
            }
            const colors = ['#FFFFFF', '#FF4BC1', '#B893D8'];
            series.forEach((values, si) => {
                const pts = values.map((v, i) => ({
                    x: left + i * ((w - left - right) / Math.max(values.length - 1, 1)),
                    y: h - bottom - (Number(v || 0) / max) * (h - top - bottom),
                }));
                if (si === 0 && !dark) {
                    const grad = ctx.createLinearGradient(0, top, 0, h - bottom);
                    grad.addColorStop(0, 'rgba(255,255,255,.75)');
                    grad.addColorStop(1, 'rgba(255,255,255,.08)');
                    ctx.beginPath();
                    pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                    ctx.lineTo(pts.at(-1)?.x || left, h - bottom);
                    ctx.lineTo(left, h - bottom);
                    ctx.closePath();
                    ctx.fillStyle = grad;
                    ctx.fill();
                }
                ctx.strokeStyle = colors[si % colors.length];
                ctx.lineWidth = si === 0 ? 1.5 : 1;
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                ctx.stroke();
            });
            ctx.fillStyle = dark ? '#9D9D9D' : '#D9D9D9';
            ctx.font = '10px Inter, sans-serif';
            labels.forEach((l, i) => {
                if (i % Math.ceil(labels.length / 7 || 1) === 0) {
                    const x = left + i * ((w - left - right) / Math.max(labels.length - 1, 1));
                    ctx.fillText(String(l).slice(0, 3), x - 8, h - 8);
                }
            });
        },
        drawBars(id, values) {
            const c = this.canvas(id);
            if (!c) return;
            const {ctx, w, h} = c;
            const max = Math.max(...values, 1);
            const barW = Math.max(7, (w - 16) / Math.max(values.length, 1) - 5);
            values.forEach((v, i) => {
                const bh = (Number(v || 0) / max) * (h - 12);
                const x = 8 + i * (barW + 5);
                ctx.fillStyle = i % 2 ? '#B893D8' : '#FFFFFF99';
                ctx.fillRect(x, h - bh, barW, bh);
            });
        },
        renderHeatmap() {
            const el = document.getElementById('heatmap-grid');
            if (!el) return;
            const flat = (this.heatmap.matrix || []).flat();
            const max = Math.max(...flat, 1);
            const cells = flat.slice(0, 56);
            el.innerHTML = cells.map(v => {
                const alpha = max ? 0.12 + (Number(v || 0) / max) * 0.75 : 0.12;
                return `<span class="h-[13px] rounded-[2px]" style="background: rgba(255,255,255,${alpha})"></span>`;
            }).join('');
        },
        renderKeywords() {
            const el = document.getElementById('keyword-list');
            if (!el) return;
            const rows = (this.keywords || []).slice(0, 5);
            el.innerHTML = rows.length ? rows.map(row => `
                <div class="flex items-center justify-between rounded-full bg-white px-[10px] py-[3px] text-[10px] text-[#6400B2]">
                    <span class="truncate">${row.keyword}</span><span>${row.invalid}</span>
                </div>
            `).join('') : '<p class="text-[10px] text-white/70">No keyword data.</p>';
        },
        renderCountries() {
            const el = document.getElementById('country-list');
            if (!el) return;
            const rows = (this.countries || []).slice(0, 7);
            el.innerHTML = rows.length ? rows.map(row => {
                const rate = row.total ? Math.round((row.invalid / row.total) * 100) : 0;
                return `<div class="grid grid-cols-[1fr_60px_42px] items-center gap-[8px]">
                    <span><i class="mr-[6px] inline-block h-[8px] w-[12px] bg-white/55"></i>${row.country}</span>
                    <span class="h-[5px] rounded bg-white/30"><i class="block h-full rounded bg-white/70" style="width:${rate}%"></i></span>
                    <span>${rate}%</span>
                </div>`;
            }).join('') : '<p class="text-[10px] text-white/70">No country data.</p>';
        },
    };
}
</script>
@endsection
