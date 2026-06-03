@extends('layouts.admin')

@section('title', 'Overview')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]">
    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[22px] pt-[18px] sm:px-[18px] xl:max-w-none xl:px-[22px] xl:pt-[20px]">
        <div class="mb-[10px] flex flex-col gap-[9px] sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-[31px] font-normal leading-none text-white">Overview</h1>
            <div class="flex h-[42px] w-full max-w-[330px] overflow-hidden rounded-[8px] border border-white/25 bg-white text-[10px] text-black shadow-[0_2px_10px_rgba(0,0,0,.35)]">
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[10px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                    <select id="campaign-filter" class="h-[20px] rounded-[3px] border-0 bg-[#0B0B0B] px-[6px] py-0 text-[10px] text-white focus:ring-0">
                        <option value="">All campaigns</option>
                    </select>
                </label>
                <label class="flex w-[125px] flex-col justify-center px-[10px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <input id="path-filter" value="" placeholder="/pricing" class="h-[20px] rounded-[3px] border-0 bg-[#0B0B0B] px-[6px] py-0 text-[10px] text-white placeholder:text-white/60 focus:ring-0">
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-[15px] lg:grid-cols-[minmax(0,1.05fr)_minmax(0,.95fr)]">
            <div class="rounded-[10px] border-[2px] border-[#6400B2] bg-[#6400B2] p-[12px] shadow-[0_0_24px_rgba(100,0,179,.45)]">
                <div class="mb-[12px] flex h-[34px] items-center justify-between rounded-[8px] border border-white/30 bg-[#6400B2] px-[14px]">
                    <span class="text-[13px] font-medium text-white">Your Promo Suite</span>
                    <span class="text-[9px] text-white/70">Showing data for the last 7 days</span>
                </div>

                <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-2">
                    <article class="min-h-[136px] rounded-[12px] border border-white/30 bg-[#6400B2] px-[15px] py-[14px] text-center shadow-[inset_0_0_0_1px_rgba(255,255,255,.08)]">
                        <div class="mx-auto mb-[8px] flex h-[30px] w-[30px] items-center justify-center rounded-[4px] bg-white text-[#6400B2]">
                            @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[20px] w-[20px]'])
                        </div>
                        <h2 class="text-[14px] font-normal text-white">Paid Advertising Protection</h2>
                        <div class="mt-[9px] grid grid-cols-3 divide-x divide-white/25 text-[9px] text-white">
                            <span>Invalid Visits</span>
                            <span id="suite-paid-visits">--</span>
                            <span>Invalids <b id="suite-paid-rate">0.00%</b></span>
                        </div>
                        <p class="mt-[12px] text-[9px] text-white/70">Connection status</p>
                        <a href="{{ route('paid-marketing.dashboard') }}" class="mt-[8px] inline-block text-[11px] text-white hover:underline">Go To Dashboard</a>
                    </article>

                    <article class="min-h-[136px] rounded-[12px] border border-white/30 bg-[#6400B2] px-[15px] py-[14px] text-center shadow-[inset_0_0_0_1px_rgba(255,255,255,.08)]">
                        <div class="mx-auto mb-[8px] flex h-[30px] w-[30px] items-center justify-center rounded-[4px] bg-white text-[#6400B2]">
                            @include('partials.sidebar-icon', ['name' => 'globe', 'class' => 'h-[20px] w-[20px]'])
                        </div>
                        <h2 class="text-[14px] font-normal text-white">Bot Detection</h2>
                        <div class="mt-[9px] grid grid-cols-3 divide-x divide-white/25 text-[9px] text-white">
                            <span>Invalid Visits</span>
                            <span id="suite-bot-blocked">--</span>
                            <span>Invalids <b id="suite-bot-rate">0.00%</b></span>
                        </div>
                        <p class="mt-[12px] text-[9px] text-white/70">Connection status</p>
                        <a href="{{ route('bot-protection.dashboard') }}" class="mt-[8px] inline-block text-[11px] text-white hover:underline">Go To Dashboard</a>
                    </article>
                </div>
            </div>

            <div class="rounded-[8px] border border-[#6400B2] bg-[#6400B2] p-[12px]">
                <div class="mb-[10px] flex items-center justify-between">
                    <h2 class="text-[13px] font-normal text-white">Insights</h2>
                    <span class="text-[9px] text-white/70">Showing data for the last 7 days</span>
                    <button class="text-[10px] text-white hover:underline">Load More</button>
                </div>
                <div id="insight-list" class="space-y-[9px] text-[10px] text-white/75">
                    <div class="text-white/60">Loading insights…</div>
                </div>
            </div>
        </div>

        <section class="mt-[15px] rounded-[8px] border border-[#6400B2] bg-[#6400B2] p-[13px] shadow-[0_0_28px_rgba(100,0,179,.45)]">
            <div class="mb-[5px] flex items-center justify-between">
                <div>
                    <h2 class="text-[13px] font-normal leading-none text-white">Invalid Visits Trends &amp; Threat groups</h2>
                    <div class="mt-[7px] flex items-center gap-[18px] border-b border-white/70 pb-[4px] text-[15px] leading-none text-white/95">
                        <span>Paid Advertising</span>
                        <span class="text-white/60">Bot Protection</span>
                    </div>
                </div>
                <button class="text-white/80" aria-label="More">
                    <svg class="h-[16px] w-[16px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 17a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
                </button>
            </div>
            <div class="figma-dash-threats-charts">
                <div class="figma-dash-threats-charts__area">
                    <canvas id="trends-chart" class="figma-dash-threats-charts__canvas"></canvas>
                </div>
                <div class="figma-dash-threats-charts__donut">
                    <canvas id="threats-chart" class="figma-dash-threats-charts__canvas figma-dash-threats-charts__canvas--donut"></canvas>
                </div>
            </div>
            <div id="chart-legend" class="mt-[6px] flex flex-wrap justify-center gap-x-[42px] gap-y-[5px] text-[10px] text-white/85"></div>
        </section>

        <section class="mt-[15px] rounded-[8px] border border-[#6400B2] bg-[#6400B2] p-[13px]">
            <div class="mb-[9px] flex flex-col gap-[8px] sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-[13px] font-normal text-white">Overall Domain Performance</h2>
                    <div class="mt-[5px] flex gap-[16px] border-b border-white/60 pb-[4px] text-[10px] text-white/85">
                        <button type="button" id="domain-tab-all" class="border-b-2 border-white pb-[2px] text-white">All</button>
                        <button type="button" id="domain-tab-invalid" class="pb-[2px] text-white/60 hover:text-white">Invalid</button>
                        <button type="button" id="domain-tab-pending" class="pb-[2px] text-white/60 hover:text-white">Pending</button>
                    </div>
                </div>
                <div class="relative w-full sm:w-[126px]">
                    <span class="absolute left-[7px] top-1/2 -translate-y-1/2 text-white/70">
                        <svg class="h-[9px] w-[9px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input id="domain-search" type="search" placeholder="Search domain" autocomplete="off" class="h-[24px] w-full rounded-[3px] border border-black/40 bg-[#0B0B0B] pl-[22px] pr-[6px] text-[10px] text-white focus:border-white/60 focus:ring-0">
                </div>
            </div>
            <div class="overflow-x-auto rounded-[4px] border border-white/15">
                <table class="min-w-[520px] w-full text-left text-[11px] text-white">
                    <thead class="bg-[#4D008E] text-white/85">
                        <tr>
                            <th class="px-[10px] py-[7px] font-normal">Domain</th>
                            <th class="px-[10px] py-[7px] font-normal">Visits</th>
                            <th class="px-[10px] py-[7px] font-normal">Threats</th>
                        </tr>
                    </thead>
                    <tbody id="domain-performance-body" class="divide-y divide-white/10 bg-[#6400B2]">
                        <tr><td colspan="3" class="px-[8px] py-[8px] text-center text-white/75">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-[15px] grid grid-cols-1 gap-[15px] lg:grid-cols-3">
            <section class="rounded-[8px] border border-[#6400B2] bg-[#6400B2] p-[13px] lg:col-span-2">
                <div class="mb-[10px] flex items-center justify-between">
                    <h2 class="text-[13px] font-normal text-white">Campaign Performance</h2>
                    <span class="rounded-[4px] bg-[#0B0B0B]/80 px-[8px] py-[4px] text-[10px] text-white/75">Live</span>
                </div>
                <div class="grid grid-cols-1 gap-[10px] sm:grid-cols-3">
                    <article class="rounded-[8px] border border-white/20 bg-[#4D008E]/70 p-[12px]">
                        <p class="text-[10px] text-white/70">Paid clicks</p>
                        <p id="bottom-paid-clicks" class="mt-[6px] text-[24px] font-semibold leading-none text-white">--</p>
                    </article>
                    <article class="rounded-[8px] border border-white/20 bg-[#4D008E]/70 p-[12px]">
                        <p class="text-[10px] text-white/70">Suspicious visits</p>
                        <p id="bottom-suspicious" class="mt-[6px] text-[24px] font-semibold leading-none text-white">--</p>
                    </article>
                    <article class="rounded-[8px] border border-white/20 bg-[#4D008E]/70 p-[12px]">
                        <p class="text-[10px] text-white/70">Top campaign</p>
                        <p id="bottom-top-campaign" class="mt-[6px] truncate text-[16px] font-semibold leading-none text-white">--</p>
                    </article>
                </div>
            </section>

            <section class="rounded-[8px] border border-[#6400B2] bg-[#6400B2] p-[13px]">
                <h2 class="text-[13px] font-normal text-white">Connection Status</h2>
                <div class="mt-[12px] space-y-[8px] text-[11px] text-white/85">
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Tracking script</span><span id="conn-tracking" class="text-emerald-200">—</span>
                    </div>
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Ingestion</span><span id="conn-ingestion" class="text-emerald-200">—</span>
                    </div>
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Protection</span><span id="conn-protection" class="text-amber-100">—</span>
                    </div>
                </div>
            </section>
        </div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));
    const css = getComputedStyle(document.documentElement);
    const purple = '#6400B2';

    function retina(canvas) {
        const dpr = window.devicePixelRatio || 1;
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return {ctx, w, h};
    }

    function drawTrend(labels, values) {
        const canvas = document.getElementById('trends-chart');
        if (!canvas) return;
        const {ctx, w, h} = retina(canvas);
        ctx.clearRect(0, 0, w, h);
        const max = Math.max(...values, 1);
        const left = 30, right = 12, top = 18, bottom = 26;
        ctx.strokeStyle = 'rgba(255,255,255,.16)';
        ctx.lineWidth = 1;
        for (let i = 0; i < 6; i++) {
            const y = top + i * ((h - top - bottom) / 5);
            ctx.beginPath();
            ctx.moveTo(left, y);
            ctx.lineTo(w - right, y);
            ctx.stroke();
        }
        const points = values.map((value, i) => {
            const x = left + i * ((w - left - right) / Math.max(values.length - 1, 1));
            const y = h - bottom - (value / max) * (h - top - bottom);
            return {x, y};
        });
        const grad = ctx.createLinearGradient(0, top, 0, h - bottom);
        grad.addColorStop(0, 'rgba(255,255,255,.72)');
        grad.addColorStop(1, 'rgba(255,255,255,.08)');
        ctx.beginPath();
        points.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
        ctx.lineTo(points.at(-1)?.x || left, h - bottom);
        ctx.lineTo(left, h - bottom);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,.72)';
        ctx.beginPath();
        points.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
        ctx.stroke();
    }

    function drawDonut(labels, values) {
        const canvas = document.getElementById('threats-chart');
        if (!canvas) return;
        const {ctx, w, h} = retina(canvas);
        ctx.clearRect(0, 0, w, h);
        const total = values.reduce((sum, n) => sum + Number(n || 0), 0) || 1;
        const cx = w / 2, cy = h / 2;
        const ring = Math.max(14, Math.round(Math.min(w, h) * 0.075));
        const radius = Math.min(w, h) / 2 - ring - 6;
        let start = -Math.PI / 2;
        ['#D9D9D9', '#FFFFFF', '#B893D8', '#8C8C8C'].forEach((color, i) => {
            const slice = ((values[i] || total / 4) / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(cx, cy, radius, start, start + slice);
            ctx.lineWidth = ring;
            ctx.strokeStyle = color;
            ctx.stroke();
            start += slice;
        });
        ctx.beginPath();
        ctx.arc(cx, cy, radius - ring, 0, Math.PI * 2);
        ctx.fillStyle = purple;
        ctx.fill();
    }

    async function json(url) {
        const res = await fetch(url, {headers: {'Accept': 'application/json'}});
        if (!res.ok) throw new Error(url);
        return res.json();
    }

    function dateParams() {
        try {
            const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
            const p = new URLSearchParams();
            if (r.from) p.set('from', r.from);
            if (r.to) p.set('to', r.to);
            return p;
        } catch (e) {
            return new URLSearchParams();
        }
    }

    function apiUrl(path) {
        const qs = dateParams().toString();
        return qs ? `${path}?${qs}` : path;
    }

    async function loadSummary() {
        const data = await json(apiUrl('/overview/summary'));
        document.getElementById('suite-paid-visits').textContent = fmt(data.paidAdvertising?.invalidVisits ?? data.paidAdvertising?.visits);
        document.getElementById('suite-bot-blocked').textContent = fmt(data.botProtection?.blockedHits);
        const paidRate = Number(data.paidAdvertising?.invalidRate ?? 0).toFixed(2);
        const botRate = Number(data.botProtection?.invalidRate ?? 0).toFixed(2);
        document.getElementById('suite-paid-rate').textContent = `${paidRate}%`;
        document.getElementById('suite-bot-rate').textContent = `${botRate}%`;
        const conn = data.connectionStatus || {};
        const setConn = (id, text) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = text || '—';
            el.className = /healthy|online|active/i.test(text || '') ? 'text-emerald-200' : 'text-amber-100';
        };
        setConn('conn-tracking', conn.tracking);
        setConn('conn-ingestion', conn.ingestion);
        setConn('conn-protection', conn.protection);
    }

    let domainRows = [];
    let domainFilter = 'all';
    let domainSearch = '';

    function renderDomainTable() {
        const body = document.getElementById('domain-performance-body');
        const q = domainSearch.trim().toLowerCase();
        const filtered = domainRows.filter((row) => {
            if (q && !(row.domain || '').toLowerCase().includes(q)) return false;
            if (domainFilter === 'invalid') return row.threats > 0;
            if (domainFilter === 'pending') return row.pending;
            return true;
        });
        body.innerHTML = filtered.length ? filtered.map((row) => `
            <tr>
                <td class="px-[8px] py-[6px]">${row.domain}${row.pending ? ' <span class="text-[9px] text-amber-200">(pending)</span>' : ''}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.visits)}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.threats)}</td>
            </tr>
        `).join('') : '<tr><td colspan="3" class="px-[8px] py-[8px] text-center text-white/75">No domains match this filter.</td></tr>';
    }

    function setDomainTab(tab) {
        domainFilter = tab;
        ['all', 'invalid', 'pending'].forEach((name) => {
            const btn = document.getElementById(`domain-tab-${name}`);
            if (!btn) return;
            const active = name === tab;
            btn.classList.toggle('border-b-2', active);
            btn.classList.toggle('border-white', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('text-white/60', !active);
        });
        renderDomainTable();
    }

    async function loadInsights() {
        const d = await json(apiUrl('/insights'));
        const today = new Date();
        const rows = [
            ['Paid Advertising: detection on example domain', d.totalClicks],
            ['Paid Advertising: invalid traffic found', d.suspiciousVisits],
            [`Top campaign: ${d.topCampaign || 'N/A'}`, d.topCampaignClicks],
            ['Bot Protection: suspicious sessions blocked', d.suspiciousVisits],
        ];
        document.getElementById('insight-list').innerHTML = rows.map(([text, count], index) => `
            <article class="flex h-[30px] items-center gap-[9px] rounded-[6px] bg-[#0D0D0D]/82 px-[10px] text-[10px] text-white">
                <span class="rounded-[3px] bg-[#6400B2] px-[7px] py-[3px]">${today.toLocaleDateString(undefined, {month: 'short', day: 'numeric'})}</span>
                <span class="flex-1">${text}</span>
                <span>${fmt(count)}</span>
            </article>
        `).join('');
        document.getElementById('bottom-paid-clicks').textContent = fmt(d.totalClicks);
        document.getElementById('bottom-suspicious').textContent = fmt(d.suspiciousVisits);
        document.getElementById('bottom-top-campaign').textContent = d.topCampaign || 'N/A';
    }

    async function loadCampaigns() {
        const list = await json('/campaigns');
        const select = document.getElementById('campaign-filter');
        list.forEach((campaign) => {
            const option = document.createElement('option');
            option.value = campaign;
            option.textContent = campaign;
            select.appendChild(option);
        });
    }

    async function loadDomainTable() {
        const params = dateParams();
        const q = domainSearch.trim();
        if (q) params.set('search', q);
        const qs = params.toString();
        domainRows = await json(qs ? `/domains/performance?${qs}` : '/domains/performance');
        renderDomainTable();
    }

    async function loadCharts() {
        const params = dateParams();
        const campaign = document.getElementById('campaign-filter').value;
        const path = document.getElementById('path-filter').value;
        if (campaign) params.set('campaign', campaign);
        if (path) params.set('path', path);
        const qs = params.toString();
        const trends = await json(qs ? `/analytics/trends?${qs}` : '/analytics/trends');
        const threats = await json(qs ? `/analytics/threats?${qs}` : '/analytics/threats');
        drawTrend(trends.labels || [], trends.values || []);
        drawDonut(threats.labels || [], threats.values || []);
        const legend = document.getElementById('chart-legend');
        if (legend && (threats.labels || []).length) {
            const colors = ['#B893D8', '#FFFFFF', '#D9D9D9', '#8C8C8C'];
            legend.innerHTML = (threats.labels || []).map((label, i) =>
                `<span><i class="mr-[5px] inline-block h-[7px] w-[7px] rounded-[2px]" style="background:${colors[i % colors.length]}"></i>${label} (${fmt((threats.values || [])[i])})</span>`
            ).join('');
        } else if (legend) {
            legend.innerHTML = '<span class="text-white/60">No threat groups in range yet.</span>';
        }
    }

    function syncHeaderDatesFromStorage() {
        try {
            const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
            const fromEl = document.querySelector('[x-ref="fromDate"]');
            const toEl = document.querySelector('[x-ref="toDate"]');
            if (r.from && fromEl) fromEl.value = r.from;
            if (r.to && toEl) toEl.value = r.to;
        } catch (e) {}
    }

    async function loadAll() {
        syncHeaderDatesFromStorage();
        try {
            await Promise.all([loadSummary(), loadInsights(), loadCampaigns(), loadDomainTable()]);
            await loadCharts();
        } catch (error) {
            console.error(error);
        } finally {
            window.promotixPageLoader?.hide();
        }
    }

    document.getElementById('domain-tab-all')?.addEventListener('click', () => setDomainTab('all'));
    document.getElementById('domain-tab-invalid')?.addEventListener('click', () => setDomainTab('invalid'));
    document.getElementById('domain-tab-pending')?.addEventListener('click', () => setDomainTab('pending'));
    const FILTER_DEBOUNCE_MS = window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500;

    document.getElementById('domain-search')?.addEventListener('input', (e) => {
        domainSearch = e.target.value;
        renderDomainTable();
        clearTimeout(window.__domainSearchTimer);
        window.__domainSearchTimer = setTimeout(() => loadDomainTable(), FILTER_DEBOUNCE_MS);
    });
    window.addEventListener('promotix:date-range', loadAll);

    document.getElementById('campaign-filter').addEventListener('change', loadCharts);
    document.getElementById('path-filter').addEventListener('input', () => {
        clearTimeout(window.__figmaPathTimer);
        window.__figmaPathTimer = setTimeout(loadCharts, FILTER_DEBOUNCE_MS);
    });
    window.addEventListener('resize', loadCharts);
    loadAll();
});
</script>
@endsection
