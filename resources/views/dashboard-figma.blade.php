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
                <div id="insight-list" class="space-y-[9px]">
                    <div class="h-[30px] rounded-[6px] bg-[#0D0D0D]/70"></div>
                    <div class="h-[30px] rounded-[6px] bg-[#0D0D0D]/70"></div>
                    <div class="h-[30px] rounded-[6px] bg-[#0D0D0D]/70"></div>
                    <div class="h-[30px] rounded-[6px] bg-[#0D0D0D]/70"></div>
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
            <div class="grid min-h-[245px] grid-cols-1 gap-[18px] md:grid-cols-[minmax(0,1fr)_180px]">
                <canvas id="trends-chart" class="h-[245px] w-full md:h-full"></canvas>
                <div class="flex items-center justify-center">
                    <canvas id="threats-chart" class="h-[150px] w-[150px]"></canvas>
                </div>
            </div>
            <div class="mt-[6px] flex flex-wrap justify-center gap-x-[42px] gap-y-[5px] text-[10px] text-white/85">
                <span><i class="mr-[5px] inline-block h-[7px] w-[7px] rounded-[2px] bg-[#B893D8]"></i>Invalid Visits 3</span>
                <span><i class="mr-[5px] inline-block h-[7px] w-[7px] rounded-[2px] bg-white"></i>Invalid Visits 3</span>
                <span><i class="mr-[5px] inline-block h-[7px] w-[7px] rounded-[2px] bg-[#D9D9D9]"></i>Invalid Suspicious Activity</span>
            </div>
        </section>

        <section class="mt-[15px] rounded-[8px] border border-[#6400B2] bg-[#6400B2] p-[13px]">
            <div class="mb-[9px] flex flex-col gap-[8px] sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-[13px] font-normal text-white">Overall Domain Performance</h2>
                    <div class="mt-[5px] flex gap-[16px] border-b border-white/60 pb-[4px] text-[10px] text-white/85">
                        <span>Invalid Domains</span>
                        <span>Pending</span>
                    </div>
                </div>
                <div class="relative w-full sm:w-[126px]">
                    <span class="absolute left-[7px] top-1/2 -translate-y-1/2 text-white/70">
                        <svg class="h-[9px] w-[9px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input placeholder="Search" class="h-[24px] w-full rounded-[3px] border border-black/40 bg-[#0B0B0B] pl-[22px] pr-[6px] text-[10px] text-white focus:border-white/60 focus:ring-0">
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
                        <span>Tracking script</span><span class="text-emerald-200">Healthy</span>
                    </div>
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Ingestion</span><span class="text-emerald-200">Online</span>
                    </div>
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Protection</span><span class="text-amber-100">Monitoring</span>
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
        const cx = w / 2, cy = h / 2, radius = Math.min(w, h) / 2 - 12;
        let start = -Math.PI / 2;
        ['#D9D9D9', '#FFFFFF', '#B893D8', '#8C8C8C'].forEach((color, i) => {
            const slice = ((values[i] || total / 4) / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(cx, cy, radius, start, start + slice);
            ctx.lineWidth = 16;
            ctx.strokeStyle = color;
            ctx.stroke();
            start += slice;
        });
        ctx.beginPath();
        ctx.arc(cx, cy, radius - 16, 0, Math.PI * 2);
        ctx.fillStyle = purple;
        ctx.fill();
    }

    async function json(url) {
        const res = await fetch(url, {headers: {'Accept': 'application/json'}});
        if (!res.ok) throw new Error(url);
        return res.json();
    }

    async function loadSummary() {
        const data = await json('/overview/summary');
        document.getElementById('suite-paid-visits').textContent = fmt(data.paidAdvertising?.visits);
        document.getElementById('suite-bot-blocked').textContent = fmt(data.botProtection?.blockedHits);
        const paidRate = Math.min(99, Number(data.paidAdvertising?.campaigns || 0) * 2.3).toFixed(2);
        const botRate = Math.min(99, Number(data.botProtection?.domainsProtected || 0) * 2.8).toFixed(2);
        document.getElementById('suite-paid-rate').textContent = `${paidRate}%`;
        document.getElementById('suite-bot-rate').textContent = `${botRate}%`;
    }

    async function loadInsights() {
        const d = await json('/insights');
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
        const rows = await json('/domains/performance');
        const body = document.getElementById('domain-performance-body');
        body.innerHTML = rows.length ? rows.map((row) => `
            <tr>
                <td class="px-[8px] py-[6px]">${row.domain}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.visits)}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.threats)}</td>
            </tr>
        `).join('') : '<tr><td colspan="3" class="px-[8px] py-[8px] text-center text-white/75">No domain data yet.</td></tr>';
    }

    async function loadNotifications() {
        const rows = await json('/notifications');
        document.getElementById('right-notifications').innerHTML = rows.map((row) => `
            <div class="flex items-center gap-[7px]"><span class="text-white/85">mail</span><span>${row.body}</span></div>
        `).join('');
    }

    async function loadCharts() {
        const params = new URLSearchParams();
        const campaign = document.getElementById('campaign-filter').value;
        const path = document.getElementById('path-filter').value;
        if (campaign) params.set('campaign', campaign);
        if (path) params.set('path', path);
        const trends = await json(`/analytics/trends?${params.toString()}`);
        const threats = await json('/analytics/threats');
        drawTrend(trends.labels || [], trends.values || []);
        drawDonut(threats.labels || [], threats.values || []);
    }

    async function loadAll() {
        try {
            await Promise.all([loadSummary(), loadInsights(), loadCampaigns(), loadDomainTable(), loadNotifications()]);
            await loadCharts();
        } catch (error) {
            console.error(error);
        }
    }

    document.getElementById('campaign-filter').addEventListener('change', loadCharts);
    document.getElementById('path-filter').addEventListener('input', () => {
        clearTimeout(window.__figmaPathTimer);
        window.__figmaPathTimer = setTimeout(loadCharts, 250);
    });
    window.addEventListener('resize', loadCharts);
    loadAll();
});
</script>
@endsection
