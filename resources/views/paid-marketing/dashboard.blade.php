@extends('layouts.admin')

@section('title', 'Paid Advertising')
@section('subtitle', 'Live campaign performance and detection results')

@section('content')
    <div class="space-y-6" x-data="paidAdvertisingDashboard()" x-init="init()">
        <x-ui.page-header
            title="Paid Advertising"
            subtitle="Live campaign performance and detection results">
            <x-slot:actions>
                <x-ui.button variant="outline" size="sm" href="{{ route('integrations') }}">Platform Integrate</x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('paid-marketing.detection-settings') }}">Detection Panel</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Tabs --}}
        <x-ui.tab-bar
            :tabs="[
                ['label' => 'Dashboard',     'value' => route('paid-marketing.dashboard')],
                ['label' => 'Advanced View', 'value' => route('paid-marketing.detailed')],
            ]"
            :active="route('paid-marketing.dashboard')"
            as="link"
            param="_tab"
            base="{{ url()->current() }}"
        />

        {{-- Filters --}}
        <x-ui.card title="Filters" subtitle="Scope dashboards by domain and time window">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="brand-label mb-1.5" for="pa-domain">Domain</label>
                    <select id="pa-domain" x-model="filters.domain_id" @change="reload()" class="brand-select">
                        <option value="">All domains</option>
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="pa-from">From</label>
                    <input id="pa-from" type="date" x-model="filters.from" @change="reload()" class="brand-input">
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="pa-to">To</label>
                    <input id="pa-to" type="date" x-model="filters.to" @change="reload()" class="brand-input">
                </div>
            </div>
        </x-ui.card>

        {{-- KPI cards --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Summary">
            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Paid Traffic</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/15 text-brand-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3v18h18M7 14l3-3 4 4 5-6"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value" x-text="fmt(summary.paid_visits)">—</p>
                <p class="mt-2 text-xs text-night-400">Total paid visits in window</p>
            </article>

            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Invalid Paid Traffic</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/15 text-amber-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value text-amber-300" x-text="fmt(summary.invalid_paid_visits)">—</p>
                <p class="mt-2 text-xs text-night-400">Bot, malicious, or out-of-geo</p>
            </article>

            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Blocking Activity</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/15 text-rose-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v6c0 4.5-3.4 8.4-8 9-4.6-.6-8-4.5-8-9V6l8-3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.5 9.5l5 5M14.5 9.5l-5 5"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value text-rose-300" x-text="fmt(summary.blocked_paid_visits)">—</p>
                <p class="mt-2 text-xs text-night-400">
                    Blocked: <span class="text-rose-300" x-text="fmt(summary.blocked_paid_visits)"></span>
                    · Flagged: <span class="text-amber-300" x-text="fmt(summary.flagged_paid_visits)"></span>
                </p>
            </article>

            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Valid Paid</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value text-emerald-300" x-text="fmt(summary.valid_paid_visits)">—</p>
                <p class="mt-2 text-xs text-night-400">After detection</p>
            </article>
        </section>

        {{-- Charts row --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card title="Paid Traffic Trends" subtitle="Paid vs invalid per day">
                <canvas id="pa-trends-chart" class="mt-2 h-56 w-full"></canvas>
            </x-ui.card>
            <x-ui.card title="Blocking Activity" subtitle="Blocked vs flagged paid visits">
                <canvas id="pa-blocking-chart" class="mt-2 h-56 w-full"></canvas>
            </x-ui.card>
        </section>

        {{-- Campaigns + keywords --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card title="Campaign Breakdown" subtitle="Top sources by total visits">
                <div class="overflow-x-auto">
                    <table class="brand-table">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Total</th>
                                <th>Valid</th>
                                <th>Invalid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in campaignsList" :key="row.campaign">
                                <tr>
                                    <td class="max-w-[260px] truncate font-medium" :title="row.campaign" x-text="row.campaign"></td>
                                    <td x-text="fmt(row.total)"></td>
                                    <td><span class="brand-pill brand-pill-success" x-text="fmt(row.valid)"></span></td>
                                    <td><span class="brand-pill brand-pill-warning" x-text="fmt(row.invalid)"></span></td>
                                </tr>
                            </template>
                            <tr x-show="campaignsList.length === 0">
                                <td colspan="4" class="py-4 text-center text-night-300">No campaigns in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Keywords" subtitle="Search terms driving traffic">
                <div class="overflow-x-auto">
                    <table class="brand-table">
                        <thead>
                            <tr>
                                <th>Keyword</th>
                                <th>Total</th>
                                <th>Invalid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in keywordsList" :key="row.keyword">
                                <tr>
                                    <td class="max-w-[260px] truncate font-medium" :title="row.keyword" x-text="row.keyword"></td>
                                    <td x-text="fmt(row.total)"></td>
                                    <td><span class="brand-pill brand-pill-warning" x-text="fmt(row.invalid)"></span></td>
                                </tr>
                            </template>
                            <tr x-show="keywordsList.length === 0">
                                <td colspan="3" class="py-4 text-center text-night-300">No keywords in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </section>

        {{-- Heatmap --}}
        <x-ui.card title="Activity Heatmap" subtitle="Paid visits by day-of-week × hour-of-day">
            <div class="overflow-x-auto">
                <table class="text-xs text-night-200">
                    <thead>
                        <tr>
                            <th class="px-2 py-1 text-left text-night-400"></th>
                            <template x-for="h in heatmap.hours" :key="h">
                                <th class="px-1 py-1 text-center text-night-400" x-text="h"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, di) in heatmap.matrix" :key="di">
                            <tr>
                                <td class="px-2 py-1 text-left text-night-300" x-text="heatmap.days[di]"></td>
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
        </x-ui.card>

        {{-- Country + IPs --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card title="Country Breakdown" subtitle="Top traffic sources by geography">
                <div class="overflow-x-auto">
                    <table class="brand-table">
                        <thead>
                            <tr><th>Country</th><th>Total</th><th>Invalid</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="row in countriesList" :key="row.country">
                                <tr>
                                    <td class="font-medium" x-text="row.country"></td>
                                    <td x-text="fmt(row.total)"></td>
                                    <td><span class="brand-pill brand-pill-warning" x-text="fmt(row.invalid)"></span></td>
                                </tr>
                            </template>
                            <tr x-show="countriesList.length === 0">
                                <td colspan="3" class="py-4 text-center text-night-300">No countries in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="IP Addresses" subtitle="Highest-volume IPs in window">
                <div class="overflow-x-auto">
                    <table class="brand-table">
                        <thead>
                            <tr><th>IP</th><th>Country</th><th>Total</th><th>Invalid</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="row in ipsList" :key="row.ip">
                                <tr>
                                    <td class="font-medium" x-text="row.ip"></td>
                                    <td class="text-night-300" x-text="row.country || '—'"></td>
                                    <td x-text="fmt(row.total)"></td>
                                    <td><span class="brand-pill brand-pill-warning" x-text="fmt(row.invalid)"></span></td>
                                </tr>
                            </template>
                            <tr x-show="ipsList.length === 0">
                                <td colspan="4" class="py-4 text-center text-night-300">No IPs in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
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
                const colors = ['#7C3AED', '#F59E0B', '#10B981', '#F43F5E'];
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
                        const grad = ctx.createLinearGradient(0, y, 0, y + bh);
                        grad.addColorStop(0, colors[di % colors.length]);
                        grad.addColorStop(1, colors[di % colors.length] + '55');
                        ctx.fillStyle = grad;
                        ctx.beginPath();
                        const r = 4;
                        ctx.moveTo(x + r, y);
                        ctx.lineTo(x + barW - r, y);
                        ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
                        ctx.lineTo(x + barW, y + bh);
                        ctx.lineTo(x, y + bh);
                        ctx.lineTo(x, y + r);
                        ctx.quadraticCurveTo(x, y, x + r, y);
                        ctx.closePath();
                        ctx.fill();
                    });
                });
                ctx.fillStyle = '#9FA1C2';
                ctx.font = '10px Inter, sans-serif';
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
                heatmapMax: 1,
                lastTrends: null,
                lastBlocking: null,
                fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
                heatColor(v) {
                    const max = this.heatmapMax || 1;
                    const ratio = Math.min(1, v / max);
                    const a = ratio === 0 ? 0.06 : 0.15 + ratio * 0.7;
                    return `rgba(124, 58, 237, ${a.toFixed(3)})`;
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
                    let resizeTimer = null;
                    window.addEventListener('resize', () => {
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(() => this.renderCharts(this.lastTrends, this.lastBlocking), 200);
                    });
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
