@extends('layouts.admin')

@section('title', 'Paid Advertising — Dashboard')

@section('content')
    <div class="space-y-6" x-data="paidAdvertisingDashboard()" x-init="init()">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('paid-marketing.dashboard') }}" class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">Dashboard</a>
            <a href="{{ route('paid-marketing.detailed') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detailed View</a>
            <a href="{{ route('integrations') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Platform Connections</a>
            <a href="{{ route('paid-marketing.detection-settings') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detection Settings</a>
        </div>

        <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dark-border bg-dark-card p-4">
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm text-gray-400" for="pa-domain">Domain</label>
                <select id="pa-domain" x-model="filters.domain_id" @change="reload()"
                        class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                    <option value="">All domains</option>
                    @foreach ($domains as $d)
                        <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                    @endforeach
                </select>
                <label class="text-sm text-gray-400" for="pa-from">From</label>
                <input id="pa-from" type="date" x-model="filters.from" @change="reload()"
                       class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                <label class="text-sm text-gray-400" for="pa-to">To</label>
                <input id="pa-to" type="date" x-model="filters.to" @change="reload()"
                       class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Paid Traffic</p>
                <p class="mt-2 text-3xl font-bold text-white" x-text="fmt(summary.paid_visits)">—</p>
                <p class="mt-1 text-xs text-gray-400">Total paid visits in window</p>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Invalid Paid Traffic</p>
                <p class="mt-2 text-3xl font-bold text-amber-400" x-text="fmt(summary.invalid_paid_visits)">—</p>
                <p class="mt-1 text-xs text-gray-400">Detected as bot / malicious / out-of-geo</p>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Blocking Activity</p>
                <p class="mt-2 text-3xl font-bold text-red-400" x-text="fmt(summary.blocked_paid_visits)">—</p>
                <p class="mt-1 text-xs text-gray-400">Blocked: <span x-text="fmt(summary.blocked_paid_visits)"></span> · Flagged: <span x-text="fmt(summary.flagged_paid_visits)"></span></p>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Valid Paid</p>
                <p class="mt-2 text-3xl font-bold text-emerald-400" x-text="fmt(summary.valid_paid_visits)">—</p>
                <p class="mt-1 text-xs text-gray-400">After detection</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Paid Traffic Trends</h2>
                <p class="mt-1 text-xs text-gray-400">Paid vs invalid per day</p>
                <canvas id="pa-trends-chart" class="mt-4 h-56 w-full"></canvas>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Blocking Activity</h2>
                <p class="mt-1 text-xs text-gray-400">Blocked vs flagged paid visits</p>
                <canvas id="pa-blocking-chart" class="mt-4 h-56 w-full"></canvas>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Campaign Breakdown</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="border-b border-dark-border text-left text-gray-400">
                            <th class="py-2 pr-4">Campaign</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Valid</th>
                            <th class="py-2 pr-4">Invalid</th>
                        </tr>
                        </thead>
                        <tbody class="text-white">
                        <template x-for="row in campaignsList" :key="row.campaign">
                            <tr class="border-b border-dark-border">
                                <td class="py-2 pr-4 truncate max-w-[260px]" :title="row.campaign" x-text="row.campaign"></td>
                                <td class="py-2 pr-4" x-text="fmt(row.total)"></td>
                                <td class="py-2 pr-4 text-emerald-300" x-text="fmt(row.valid)"></td>
                                <td class="py-2 pr-4 text-amber-300" x-text="fmt(row.invalid)"></td>
                            </tr>
                        </template>
                        <tr x-show="campaignsList.length === 0">
                            <td colspan="4" class="py-3 text-gray-400">No campaigns in this window.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Keywords</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="border-b border-dark-border text-left text-gray-400">
                            <th class="py-2 pr-4">Keyword</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Invalid</th>
                        </tr>
                        </thead>
                        <tbody class="text-white">
                        <template x-for="row in keywordsList" :key="row.keyword">
                            <tr class="border-b border-dark-border">
                                <td class="py-2 pr-4 truncate max-w-[260px]" :title="row.keyword" x-text="row.keyword"></td>
                                <td class="py-2 pr-4" x-text="fmt(row.total)"></td>
                                <td class="py-2 pr-4 text-amber-300" x-text="fmt(row.invalid)"></td>
                            </tr>
                        </template>
                        <tr x-show="keywordsList.length === 0">
                            <td colspan="3" class="py-3 text-gray-400">No keywords in this window.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card p-6">
            <h2 class="text-lg font-semibold text-white">Activity Heatmap</h2>
            <p class="mt-1 text-xs text-gray-400">Paid visits by day of week × hour of day</p>
            <div class="mt-4 overflow-x-auto">
                <table class="text-xs text-gray-300">
                    <thead>
                    <tr>
                        <th class="px-2 py-1 text-left text-gray-500"></th>
                        <template x-for="h in heatmap.hours" :key="h">
                            <th class="px-1 py-1 text-center text-gray-500" x-text="h"></th>
                        </template>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(row, di) in heatmap.matrix" :key="di">
                        <tr>
                            <td class="px-2 py-1 text-left text-gray-400" x-text="heatmap.days[di]"></td>
                            <template x-for="(v, hi) in row" :key="hi">
                                <td class="p-0.5">
                                    <div class="h-5 w-5 rounded"
                                         :style="`background-color: ${heatColor(v)}`"
                                         :title="`${heatmap.days[di]} ${hi}:00 — ${fmt(v)}`"></div>
                                </td>
                            </template>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Country Breakdown</h2>
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
                        <template x-for="row in countriesList" :key="row.country">
                            <tr class="border-b border-dark-border">
                                <td class="py-2 pr-4" x-text="row.country"></td>
                                <td class="py-2 pr-4" x-text="fmt(row.total)"></td>
                                <td class="py-2 pr-4 text-amber-300" x-text="fmt(row.invalid)"></td>
                            </tr>
                        </template>
                        <tr x-show="countriesList.length === 0">
                            <td colspan="3" class="py-3 text-gray-400">No countries in this window.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </article>
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">IP Addresses</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="border-b border-dark-border text-left text-gray-400">
                            <th class="py-2 pr-4">IP</th>
                            <th class="py-2 pr-4">Country</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Invalid</th>
                        </tr>
                        </thead>
                        <tbody class="text-white">
                        <template x-for="row in ipsList" :key="row.ip">
                            <tr class="border-b border-dark-border">
                                <td class="py-2 pr-4 font-medium" x-text="row.ip"></td>
                                <td class="py-2 pr-4 text-gray-300" x-text="row.country || '—'"></td>
                                <td class="py-2 pr-4" x-text="fmt(row.total)"></td>
                                <td class="py-2 pr-4 text-amber-300" x-text="fmt(row.invalid)"></td>
                            </tr>
                        </template>
                        <tr x-show="ipsList.length === 0">
                            <td colspan="4" class="py-3 text-gray-400">No IPs in this window.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>

    <script>
        function paidAdvertisingDashboard() {
            const drawBars = (id, labels, datasets) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const dpr = window.devicePixelRatio || 1;
                canvas.width = canvas.clientWidth * dpr;
                canvas.height = canvas.clientHeight * dpr;
                const ctx = canvas.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(dpr, dpr);
                ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
                const colors = ['#8B5CF6', '#F59E0B', '#10B981', '#EF4444'];
                const all = (datasets || []).flatMap(d => d.values || []);
                const max = Math.max(...all, 1);
                const groups = (labels || []).length;
                const slot = (canvas.clientWidth - 24) / Math.max(groups, 1);
                (datasets || []).forEach((d, di) => {
                    const barW = Math.max(6, slot / Math.max(datasets.length, 1) - 4);
                    (d.values || []).forEach((v, i) => {
                        const x = 12 + i * slot + di * (barW + 2);
                        const bh = (v / max) * (canvas.clientHeight - 36);
                        const y = canvas.clientHeight - 22 - bh;
                        ctx.fillStyle = colors[di % colors.length];
                        ctx.fillRect(x, y, barW, bh);
                    });
                });
                ctx.fillStyle = '#9CA3AF';
                ctx.font = '10px sans-serif';
                (labels || []).forEach((l, i) => {
                    ctx.fillText(String(l), 12 + i * slot, canvas.clientHeight - 8);
                });
            };

            return {
                filters: { domain_id: '', from: '', to: '' },
                summary: { paid_visits: 0, invalid_paid_visits: 0, blocked_paid_visits: 0, flagged_paid_visits: 0, valid_paid_visits: 0 },
                campaignsList: [],
                keywordsList: [],
                countriesList: [],
                ipsList: [],
                heatmap: { days: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], hours: Array.from({length:24}, (_,i)=>i), matrix: [] },
                fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
                heatColor(v) {
                    const max = this.heatmapMax || 1;
                    const ratio = Math.min(1, v / max);
                    const a = ratio === 0 ? 0.06 : 0.15 + ratio * 0.7;
                    return `rgba(139, 92, 246, ${a.toFixed(3)})`;
                },
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
                    window.addEventListener('resize', () => this.renderCharts(this.lastTrends, this.lastBlocking));
                },
                async reload() {
                    const qs = this.qs();
                    const [s, t, b, c, k, co, ip, hm] = await Promise.all([
                        fetch(`/paid-marketing/summary?${qs}`).then(r => r.json()),
                        fetch(`/paid-marketing/trends?${qs}`).then(r => r.json()),
                        fetch(`/paid-marketing/blocking-activity?${qs}`).then(r => r.json()),
                        fetch(`/paid-marketing/campaigns?${qs}`).then(r => r.json()),
                        fetch(`/paid-marketing/keywords?${qs}`).then(r => r.json()),
                        fetch(`/paid-marketing/countries?${qs}`).then(r => r.json()),
                        fetch(`/paid-marketing/ips?${qs}`).then(r => r.json()),
                        fetch(`/paid-marketing/heatmap?${qs}`).then(r => r.json()),
                    ]);
                    this.summary = s;
                    this.lastTrends = t;
                    this.lastBlocking = b;
                    this.campaignsList = c;
                    this.keywordsList = k;
                    this.countriesList = co;
                    this.ipsList = ip;
                    this.heatmap = hm;
                    const flat = (hm.matrix || []).flat();
                    this.heatmapMax = Math.max(...(flat.length ? flat : [1]));
                    this.renderCharts(t, b);
                },
                renderCharts(trends, blocking) {
                    drawBars('pa-trends-chart', trends?.labels ?? [], trends?.datasets ?? []);
                    drawBars('pa-blocking-chart', blocking?.labels ?? [], blocking?.datasets ?? []);
                },
            };
        }
    </script>
@endsection
