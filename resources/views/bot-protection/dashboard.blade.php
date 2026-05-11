@extends('layouts.admin')

@section('title', 'Bot Protection')
@section('subtitle', 'Visit volume, threat groups, and crawler detection')

@section('content')
    <div class="space-y-6" x-data="botProtectionDashboard()" x-init="init()">
        <x-ui.page-header title="Bot Protection" subtitle="Visit volume, threat groups, and crawler detection">
            <x-slot:actions>
                <x-ui.button variant="outline" size="sm" href="{{ route('bot-protection.advanced') }}">Advanced View</x-ui.button>
                <x-ui.button variant="primary" size="sm" href="{{ route('domains.index') }}">Get Protected</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.tab-bar
            :tabs="[
                ['label' => 'Dashboard',     'value' => route('bot-protection.dashboard')],
                ['label' => 'Advanced View', 'value' => route('bot-protection.advanced')],
            ]"
            :active="route('bot-protection.dashboard')"
            as="link"
            param="_tab"
            base="{{ url()->current() }}"
        />

        {{-- Filters --}}
        <x-ui.card title="Filters" subtitle="Scope to domain and time window">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="brand-label mb-1.5" for="bp-domain">Domain</label>
                    <select id="bp-domain" x-model="filters.domain_id" @change="reload()" class="brand-select">
                        <option value="">All domains</option>
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="bp-from">From</label>
                    <input id="bp-from" type="date" x-model="filters.from" @change="reload()" class="brand-input">
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="bp-to">To</label>
                    <input id="bp-to" type="date" x-model="filters.to" @change="reload()" class="brand-input">
                </div>
            </div>
        </x-ui.card>

        {{-- KPIs --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Summary">
            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Total Visits</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/15 text-brand-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value" x-text="fmt(summary.total_visits)">—</p>
            </article>

            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Valid Visits</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value text-emerald-300" x-text="fmt(summary.valid_visits)">—</p>
            </article>

            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Invalid Bot Visits</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/15 text-amber-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v6c0 4.5-3.4 8.4-8 9-4.6-.6-8-4.5-8-9V6l8-3z"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value text-amber-300" x-text="fmt(summary.invalid_bot_visits)">—</p>
            </article>

            <article class="brand-kpi">
                <div class="flex items-start justify-between gap-3">
                    <p class="brand-kpi-label">Known Crawlers</p>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-500/15 text-sky-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 1018 0 9 9 0 00-18 0zm9-9v18m9-9H3"/></svg>
                    </div>
                </div>
                <p class="brand-kpi-value text-sky-300" x-text="fmt(summary.known_crawlers)">—</p>
            </article>
        </section>

        {{-- Charts --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card title="Total Visits Breakdown" subtitle="Total / Valid / Invalid per day">
                <canvas id="bp-traffic-chart" class="mt-2 h-56 w-full"></canvas>
            </x-ui.card>
            <x-ui.card title="Threat Groups" subtitle="Distribution of detected groups">
                <canvas id="bp-threats-chart" class="mt-2 h-56 w-full"></canvas>
            </x-ui.card>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card title="Invalid Bot Activity Breakdown">
                <canvas id="bp-bot-chart" class="mt-2 h-48 w-full"></canvas>
            </x-ui.card>
            <x-ui.card title="Invalid Malicious Breakdown">
                <canvas id="bp-malicious-chart" class="mt-2 h-48 w-full"></canvas>
            </x-ui.card>
        </section>

        {{-- Country / domain tables --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card title="Country traffic" subtitle="Top traffic sources">
                <div class="overflow-x-auto">
                    <table class="brand-table">
                        <thead>
                            <tr><th>Country</th><th>Total</th><th>Invalid</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="row in countries" :key="row.country">
                                <tr>
                                    <td class="font-medium" x-text="row.country"></td>
                                    <td x-text="fmt(row.total)"></td>
                                    <td><span class="brand-pill brand-pill-warning" x-text="fmt(row.invalid)"></span></td>
                                </tr>
                            </template>
                            <tr x-show="countries.length === 0">
                                <td colspan="3" class="py-4 text-center text-night-300">No data in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Domain summary" subtitle="Per-domain ingestion summary">
                <div class="overflow-x-auto">
                    <table class="brand-table">
                        <thead>
                            <tr><th>Domain</th><th>Status</th><th>Total</th><th>Invalid</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="row in domainsList" :key="row.id">
                                <tr>
                                    <td class="font-medium" x-text="row.hostname"></td>
                                    <td>
                                        <span class="brand-pill" :class="row.status === 'verified' ? 'brand-pill-success' : 'brand-pill-neutral'" x-text="row.status"></span>
                                    </td>
                                    <td x-text="fmt(row.total_visits)"></td>
                                    <td><span class="brand-pill brand-pill-warning" x-text="fmt(row.invalid_visits)"></span></td>
                                </tr>
                            </template>
                            <tr x-show="domainsList.length === 0">
                                <td colspan="4" class="py-4 text-center text-night-300">No domains with traffic in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </section>
    </div>

    <script>
        function botProtectionDashboard() {
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
                const colors = ['#7C3AED', '#10B981', '#F59E0B'];
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
                labels.forEach((l, i) => {
                    ctx.fillText(String(l), 12 + i * slot, canvas.clientHeight - 8);
                });
            };

            const drawDonut = (id, labels, values) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const dpr = window.devicePixelRatio || 1;
                canvas.width = canvas.clientWidth * dpr;
                canvas.height = canvas.clientHeight * dpr;
                const ctx = canvas.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(dpr, dpr);
                ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
                const total = values.reduce((a, b) => a + b, 0) || 1;
                const cx = canvas.clientWidth / 2;
                const cy = canvas.clientHeight / 2;
                const r = Math.min(cx, cy) - 16;
                const colors = ['#F43F5E', '#7C3AED', '#F59E0B', '#10B981', '#06B6D4', '#EC4899'];
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
                ctx.fillStyle = '#10142A';
                ctx.beginPath();
                ctx.arc(cx, cy, r * 0.55, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#FFFFFF';
                ctx.font = 'bold 14px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(total.toLocaleString(), cx, cy + 4);
                ctx.textAlign = 'start';
            };

            return {
                filters: { domain_id: '', from: '', to: '' },
                summary: { total_visits: 0, valid_visits: 0, invalid_bot_visits: 0, invalid_malicious_visits: 0, known_crawlers: 0 },
                countries: [],
                domainsList: [],
                lastTraffic: null, lastThreats: null, lastBot: null, lastMalicious: null,
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
                    let t = null;
                    window.addEventListener('resize', () => {
                        clearTimeout(t);
                        t = setTimeout(() => this.renderCharts(this.lastTraffic, this.lastThreats, this.lastBot, this.lastMalicious), 200);
                    });
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
