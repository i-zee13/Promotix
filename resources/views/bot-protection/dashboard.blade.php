@extends('layouts.admin')

@section('title', 'Bot Protection')

@section('content')
    <div class="space-y-6" x-data="botProtectionDashboard()" x-init="init()">
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dark-border bg-dark-card p-4">
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm text-gray-400" for="bp-domain">Domain</label>
                <select id="bp-domain" x-model="filters.domain_id" @change="reload()"
                        class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                    <option value="">All domains</option>
                    @foreach ($domains as $d)
                        <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                    @endforeach
                </select>
                <label class="text-sm text-gray-400" for="bp-from">From</label>
                <input id="bp-from" type="date" x-model="filters.from" @change="reload()"
                       class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                <label class="text-sm text-gray-400" for="bp-to">To</label>
                <input id="bp-to" type="date" x-model="filters.to" @change="reload()"
                       class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('bot-protection.advanced') }}"
                   class="rounded-xl border border-dark-border bg-dark px-4 py-2 text-sm text-gray-200 hover:bg-dark-border">
                    Advanced View
                </a>
                <a href="{{ route('domains.index') }}"
                   class="rounded-xl bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                    Get Protected
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Total Visits</p>
                <p class="mt-2 text-3xl font-bold text-white" x-text="fmt(summary.total_visits)">—</p>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Valid Visits</p>
                <p class="mt-2 text-3xl font-bold text-emerald-400" x-text="fmt(summary.valid_visits)">—</p>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Invalid Bot Visits</p>
                <p class="mt-2 text-3xl font-bold text-amber-400" x-text="fmt(summary.invalid_bot_visits)">—</p>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Known Crawlers</p>
                <p class="mt-2 text-3xl font-bold text-sky-400" x-text="fmt(summary.known_crawlers)">—</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Total Visits Breakdown</h2>
                <p class="mt-1 text-xs text-gray-400">Total / Valid / Invalid per day</p>
                <canvas id="bp-traffic-chart" class="mt-4 h-56 w-full"></canvas>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Threat Groups</h2>
                <p class="mt-1 text-xs text-gray-400">Distribution of detected groups</p>
                <canvas id="bp-threats-chart" class="mt-4 h-56 w-full"></canvas>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Invalid Bot Activity Breakdown</h2>
                <canvas id="bp-bot-chart" class="mt-4 h-48 w-full"></canvas>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Invalid Malicious Breakdown</h2>
                <canvas id="bp-malicious-chart" class="mt-4 h-48 w-full"></canvas>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Country traffic</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="border-b border-dark-border text-left text-gray-400">
                            <th class="py-2 pr-4">Country</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Invalid</th>
                        </tr>
                        </thead>
                        <tbody class="text-white">
                        <template x-for="row in countries" :key="row.country">
                            <tr class="border-b border-dark-border">
                                <td class="py-2 pr-4" x-text="row.country"></td>
                                <td class="py-2 pr-4" x-text="fmt(row.total)"></td>
                                <td class="py-2 pr-4 text-amber-300" x-text="fmt(row.invalid)"></td>
                            </tr>
                        </template>
                        <tr x-show="countries.length === 0">
                            <td class="py-3 pr-4 text-gray-400" colspan="3">No data in this window.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Domain summary</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="border-b border-dark-border text-left text-gray-400">
                            <th class="py-2 pr-4">Domain</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Invalid</th>
                        </tr>
                        </thead>
                        <tbody class="text-white">
                        <template x-for="row in domainsList" :key="row.id">
                            <tr class="border-b border-dark-border">
                                <td class="py-2 pr-4" x-text="row.hostname"></td>
                                <td class="py-2 pr-4 text-gray-300" x-text="row.status"></td>
                                <td class="py-2 pr-4" x-text="fmt(row.total_visits)"></td>
                                <td class="py-2 pr-4 text-amber-300" x-text="fmt(row.invalid_visits)"></td>
                            </tr>
                        </template>
                        <tr x-show="domainsList.length === 0">
                            <td class="py-3 pr-4 text-gray-400" colspan="4">No domains with traffic in this window.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>

    <script>
        function botProtectionDashboard() {
            const drawBars = (id, labels, datasets) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const dpr = window.devicePixelRatio || 1;
                const w = canvas.width = canvas.clientWidth * dpr;
                const h = canvas.height = canvas.clientHeight * dpr;
                const ctx = canvas.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(dpr, dpr);
                ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
                const colors = ['#8B5CF6', '#10B981', '#F59E0B'];
                const all = datasets.flatMap(d => d.values);
                const max = Math.max(...all, 1);
                const groups = labels.length;
                const slot = (canvas.clientWidth - 24) / Math.max(groups, 1);
                datasets.forEach((d, di) => {
                    const barW = Math.max(6, slot / datasets.length - 4);
                    d.values.forEach((v, i) => {
                        const x = 12 + i * slot + di * (barW + 2);
                        const bh = (v / max) * (canvas.clientHeight - 36);
                        const y = canvas.clientHeight - 22 - bh;
                        ctx.fillStyle = colors[di % colors.length];
                        ctx.fillRect(x, y, barW, bh);
                    });
                });
                ctx.fillStyle = '#9CA3AF';
                ctx.font = '10px sans-serif';
                labels.forEach((l, i) => {
                    ctx.fillText(String(l), 12 + i * slot, canvas.clientHeight - 8);
                });
            };

            const drawDonut = (id, labels, values) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const dpr = window.devicePixelRatio || 1;
                const w = canvas.width = canvas.clientWidth * dpr;
                const h = canvas.height = canvas.clientHeight * dpr;
                const ctx = canvas.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(dpr, dpr);
                ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
                const total = values.reduce((a, b) => a + b, 0) || 1;
                const cx = canvas.clientWidth / 2;
                const cy = canvas.clientHeight / 2;
                const r = Math.min(cx, cy) - 16;
                const colors = ['#EF4444', '#8B5CF6', '#F59E0B', '#10B981', '#06B6D4', '#EC4899'];
                let start = -Math.PI / 2;
                values.forEach((v, i) => {
                    const slice = (v / total) * Math.PI * 2;
                    ctx.beginPath();
                    ctx.moveTo(cx, cy);
                    ctx.arc(cx, cy, r, start, start + slice);
                    ctx.closePath();
                    ctx.fillStyle = colors[i % colors.length];
                    ctx.fill();
                    start += slice;
                });
                ctx.fillStyle = '#111827';
                ctx.beginPath();
                ctx.arc(cx, cy, r * 0.55, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#FFFFFF';
                ctx.font = 'bold 14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(total.toLocaleString(), cx, cy + 4);
                ctx.textAlign = 'start';
            };

            return {
                filters: { domain_id: '', from: '', to: '' },
                summary: { total_visits: 0, valid_visits: 0, invalid_bot_visits: 0, invalid_malicious_visits: 0, known_crawlers: 0 },
                countries: [],
                domainsList: [],
                fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
                qs() {
                    const p = new URLSearchParams();
                    if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
                    if (this.filters.from) p.set('from', this.filters.from);
                    if (this.filters.to) p.set('to', this.filters.to);
                    return p.toString();
                },
                async init() {
                    const today = new Date();
                    const start = new Date(today.getTime() - 6 * 86400000);
                    this.filters.from = start.toISOString().slice(0, 10);
                    this.filters.to = today.toISOString().slice(0, 10);
                    await this.reload();
                    window.addEventListener('resize', () => this.renderCharts(this.lastTraffic, this.lastThreats, this.lastBot, this.lastMalicious));
                },
                async reload() {
                    const qs = this.qs();
                    const [s, t, th, ib, c, ds] = await Promise.all([
                        fetch(`/bot-protection/summary?${qs}`).then(r => r.json()),
                        fetch(`/bot-protection/traffic-breakdown?${qs}`).then(r => r.json()),
                        fetch(`/bot-protection/threat-groups?${qs}`).then(r => r.json()),
                        fetch(`/bot-protection/invalid-breakdown?${qs}`).then(r => r.json()),
                        fetch(`/bot-protection/countries?${qs}`).then(r => r.json()),
                        fetch(`/bot-protection/domains-summary?${qs}`).then(r => r.json()),
                    ]);
                    this.summary = s;
                    this.countries = c;
                    this.domainsList = ds;
                    this.lastTraffic = t;
                    this.lastThreats = th;
                    this.lastBot = ib?.invalid_bot ?? { labels: [], values: [] };
                    this.lastMalicious = ib?.invalid_malicious ?? { labels: [], values: [] };
                    this.renderCharts(this.lastTraffic, this.lastThreats, this.lastBot, this.lastMalicious);
                },
                renderCharts(traffic, threats, bot, malicious) {
                    drawBars('bp-traffic-chart', traffic?.labels ?? [], traffic?.datasets ?? []);
                    drawDonut('bp-threats-chart', threats?.labels ?? [], threats?.values ?? []);
                    drawBars('bp-bot-chart', bot?.labels ?? [], [{ name: 'Bot', values: bot?.values ?? [] }]);
                    drawBars('bp-malicious-chart', malicious?.labels ?? [], [{ name: 'Malicious', values: malicious?.values ?? [] }]);
                },
            };
        }
    </script>
@endsection
