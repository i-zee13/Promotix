@extends('layouts.admin')

@section('title', 'Overview')

@section('content')
@include('partials.promotix-page-loader')
<div class="brand-page-bg min-h-[calc(100vh-49px)]">
    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[22px] pt-[18px] sm:px-[18px] xl:max-w-none xl:px-[22px] xl:pt-[20px]">
        <div class="mb-[10px] flex flex-col gap-[9px] lg:flex-row lg:items-start lg:justify-between">
            <h1 class="text-[31px] font-normal leading-none text-white">Overview</h1>
            <div class="figma-filter-bar figma-filter-bar--overview flex min-h-[54px] w-full max-w-[920px] flex-wrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_2px_10px_rgba(0,0,0,.35)]">
                <label class="flex min-w-[140px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                    <div class="figma-filter-select-wrap">
                        <select id="domain-filter" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Domains</option>
                            @foreach ($domains as $domain)
                                <option value="{{ $domain->id }}" @selected((string) request('domain_id') === (string) $domain->id)>{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex min-w-[130px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                    <div class="figma-filter-select-wrap">
                        <select id="traffic-source-filter" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="google_ads" selected>Google Ads</option>
                            <option value="meta_ads" disabled>Meta Ads</option>
                            <option value="microsoft_ads" disabled>Microsoft Ads</option>
                        </select>
                    </div>
                </label>
                <label class="flex min-w-[160px] flex-[1.2] flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                    <div class="figma-filter-select-wrap">
                        <select id="campaign-filter" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Campaigns</option>
                        </select>
                    </div>
                </label>
                <label class="flex min-w-[150px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input id="path-filter" value="{{ request('path', '') }}" placeholder="Landing page" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        <div class="grid grid-cols-1 gap-[15px] lg:grid-cols-[minmax(0,1.05fr)_minmax(0,.95fr)]">
            <div class="rounded-[10px] border-[2px] border-[var(--brand-primary,#6400B2)] bg-[var(--brand-primary,#6400B2)] p-[12px] shadow-[0_0_24px_rgba(100,0,179,.45)]">
                <div class="mb-[12px] flex h-[34px] items-center justify-between rounded-[8px] border border-white/30 bg-[var(--brand-primary,#6400B2)] px-[14px]">
                    <span class="text-[13px] font-medium text-white">Your Promo Suite</span>
                    <span id="suite-range-label" class="text-[9px] text-white/70">Showing selected date range</span>
                </div>

                <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-2">
                    <article class="min-h-[136px] rounded-[12px] border border-white/30 bg-[var(--brand-primary,#6400B2)] px-[15px] py-[14px] text-center shadow-[inset_0_0_0_1px_rgba(255,255,255,.08)]">
                        <div class="mx-auto mb-[8px] flex h-[30px] w-[30px] items-center justify-center rounded-[4px] bg-white text-[var(--brand-primary,#6400B2)]">
                            @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[20px] w-[20px]'])
                        </div>
                        <h2 class="text-[14px] font-normal text-white">Paid Advertising Protection</h2>
                        <div class="mt-[9px] grid grid-cols-2 gap-y-[6px] divide-x-0 text-[9px] text-white sm:grid-cols-4 sm:divide-x sm:divide-white/25">
                            <div class="px-[4px]"><div class="text-white/65">Total Clicks</div><div id="suite-paid-clicks" class="mt-[2px] text-[12px] font-semibold">--</div></div>
                            <div class="px-[4px]"><div class="text-white/65">Valid Clicks</div><div id="suite-paid-valid" class="mt-[2px] text-[12px] font-semibold">--</div></div>
                            <div class="px-[4px]"><div class="text-white/65">Invalid Clicks</div><div id="suite-paid-visits" class="mt-[2px] text-[12px] font-semibold">--</div></div>
                            <div class="px-[4px]"><div class="text-white/65">Protection Rate</div><div id="suite-paid-rate" class="mt-[2px] text-[12px] font-semibold">0.00%</div></div>
                        </div>
                        <p class="mt-[12px] text-[9px] text-white/70">Connection status</p>
                        <p id="suite-paid-conn" class="mt-[2px] text-[10px] font-medium text-white/90">—</p>
                        <a href="{{ route('paid-marketing.dashboard') }}" class="mt-[8px] inline-block text-[11px] text-white hover:underline">Go To Dashboard</a>
                    </article>

                    <article class="min-h-[136px] rounded-[12px] border border-white/30 bg-[var(--brand-primary,#6400B2)] px-[15px] py-[14px] text-center shadow-[inset_0_0_0_1px_rgba(255,255,255,.08)]">
                        <div class="mx-auto mb-[8px] flex h-[30px] w-[30px] items-center justify-center rounded-[4px] bg-white text-[var(--brand-primary,#6400B2)]">
                            @include('partials.sidebar-icon', ['name' => 'globe', 'class' => 'h-[20px] w-[20px]'])
                        </div>
                        <h2 class="text-[14px] font-normal text-white">Bot Detection</h2>
                        <div class="mt-[9px] grid grid-cols-2 gap-y-[6px] text-[9px] text-white sm:grid-cols-4 sm:divide-x sm:divide-white/25">
                            <div class="px-[4px]"><div class="text-white/65">Total Visitors</div><div id="suite-bot-visitors" class="mt-[2px] text-[12px] font-semibold">--</div></div>
                            <div class="px-[4px]"><div class="text-white/65">Bots Detected</div><div id="suite-bot-detected" class="mt-[2px] text-[12px] font-semibold">--</div></div>
                            <div class="px-[4px]"><div class="text-white/65">Blocked Bots</div><div id="suite-bot-blocked" class="mt-[2px] text-[12px] font-semibold">--</div></div>
                            <div class="px-[4px]"><div class="text-white/65">Detection Rate</div><div id="suite-bot-rate" class="mt-[2px] text-[12px] font-semibold">0.00%</div></div>
                        </div>
                        <p class="mt-[12px] text-[9px] text-white/70">Connection status</p>
                        <p id="suite-bot-conn" class="mt-[2px] text-[10px] font-medium text-white/90">—</p>
                        <a href="{{ route('bot-protection.dashboard') }}" class="mt-[8px] inline-block text-[11px] text-white hover:underline">Go To Dashboard</a>
                    </article>
                </div>
            </div>

            <div class="rounded-[8px] border border-[var(--brand-primary,#6400B2)] bg-[var(--brand-primary,#6400B2)] p-[12px]">
                <div class="mb-[10px] flex items-center justify-between gap-2">
                    <h2 class="text-[13px] font-normal text-white">Live Security Feed</h2>
                    <span class="text-[9px] text-white/70">Recent detections</span>
                    <a href="{{ route('paid-marketing.detailed') }}" class="text-[10px] text-white hover:underline">View All</a>
                </div>
                <div id="insight-list" class="max-h-[280px] space-y-[9px] overflow-y-auto text-[10px] text-white/75 promotix-slim-scroll">
                    <div class="text-white/60">Loading insights…</div>
                </div>
            </div>
        </div>

        <section class="mt-[15px] rounded-[8px] border border-[#6400B2] bg-[#6400B2] p-[13px] shadow-[0_0_28px_rgba(100,0,179,.45)]">
            <div class="mb-[5px] flex items-center justify-between">
                <div>
                    <h2 class="text-[13px] font-normal leading-none text-white">Invalid Traffic Trends &amp; Threat Groups</h2>
                    <div class="mt-[7px] flex items-center gap-[18px] border-b border-white/70 pb-[4px] text-[15px] leading-none text-white/95">
                        <button type="button" id="insights-tab-paid" class="border-b-2 border-white pb-[2px] text-white">Google Ads</button>
                        <button type="button" id="insights-tab-bot" class="pb-[2px] text-white/60 hover:text-white">Organic / Bot</button>
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
                    <div id="threats-donut-center" class="figma-dash-threats-donut-center" aria-hidden="true">
                        <span id="threats-donut-pct" class="figma-dash-threats-donut-center__pct">—</span>
                        <span id="threats-donut-label" class="figma-dash-threats-donut-center__label"></span>
                    </div>
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
                <table class="min-w-[720px] w-full text-left text-[11px] text-white">
                    <thead class="bg-[#4D008E] text-white/85">
                        <tr>
                            <th class="px-[10px] py-[7px] font-normal">Domain</th>
                            <th class="px-[10px] py-[7px] font-normal">Clicks</th>
                            <th class="px-[10px] py-[7px] font-normal">Visitors</th>
                            <th class="px-[10px] py-[7px] font-normal">Invalid %</th>
                            <th class="px-[10px] py-[7px] font-normal">Risk</th>
                            <th class="px-[10px] py-[7px] font-normal">Status</th>
                        </tr>
                    </thead>
                    <tbody id="domain-performance-body" class="divide-y divide-white/10 bg-[#6400B2]">
                        <tr><td colspan="6" class="px-[8px] py-[8px] text-center text-white/75">Loading...</td></tr>
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
                <div class="overflow-x-auto rounded-[4px] border border-white/15">
                    <table class="min-w-[640px] w-full text-left text-[11px] text-white">
                        <thead class="bg-[#4D008E] text-white/85">
                            <tr>
                                <th class="px-[10px] py-[7px] font-normal">Campaign</th>
                                <th class="px-[10px] py-[7px] font-normal">Clicks</th>
                                <th class="px-[10px] py-[7px] font-normal">Valid</th>
                                <th class="px-[10px] py-[7px] font-normal">Invalid</th>
                                <th class="px-[10px] py-[7px] font-normal">Risk %</th>
                                <th class="px-[10px] py-[7px] font-normal">Cost Saved</th>
                            </tr>
                        </thead>
                        <tbody id="campaign-performance-body" class="divide-y divide-white/10 bg-[#4D008E]/55">
                            <tr><td colspan="6" class="px-[8px] py-[8px] text-center text-white/75">Loading...</td></tr>
                        </tbody>
                    </table>
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
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Last event</span><span id="conn-last-event" class="text-white/80">—</span>
                    </div>
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Tracking version</span><span id="conn-tracking-version" class="text-white/80">—</span>
                    </div>
                    <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                        <span>Events today</span><span id="conn-events-today" class="text-white/80">—</span>
                    </div>
                </div>
            </section>
        </div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));

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

    function renderDonut(labels, values) {
        const canvas = document.getElementById('threats-chart');
        if (!canvas || !window.Chart) return;

        if (threatsChart) {
            threatsChart.destroy();
            threatsChart = null;
        }

        const safeLabels = labels || [];
        const safeValues = (values || []).map((value) => Number(value || 0));
        const total = safeValues.reduce((sum, value) => sum + value, 0);
        const chartLabels = total > 0 ? safeLabels : ['No data'];
        const chartValues = total > 0 ? safeValues : [1];
        const chartColors = total > 0
            ? chartLabels.map((_, index) => donutColors[index % donutColors.length])
            : ['rgba(255,255,255,0.15)'];

        threatsChart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartValues,
                    backgroundColor: chartColors,
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    hoverBorderColor: '#ffffff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        filter: (item) => !item.hidden,
                        callbacks: {
                            label: (ctx) => total > 0
                                ? `${ctx.label}: ${fmt(ctx.raw)}`
                                : 'No threat groups in range',
                        },
                    },
                },
            },
        });

        if (total > 0) {
            Object.entries(hiddenThreatSlices).forEach(([index, hidden]) => {
                if (hidden) {
                    threatsChart.toggleDataVisibility(Number(index));
                }
            });
            threatsChart.update();
        }

        updateDonutCenter(safeLabels, safeValues);
    }

    function renderThreatLegend(labels, values) {
        lastThreatLegend = { labels: labels || [], values: values || [] };
        const legend = document.getElementById('chart-legend');
        if (!legend) return;
        if (!labels?.length) {
            legend.innerHTML = '<span class="text-white/60">No threat groups in range yet.</span>';
            return;
        }
        legend.innerHTML = labels.map((label, i) => {
            const hidden = hiddenThreatSlices[i];
            return `<button type="button" class="chart-legend-item${hidden ? ' is-hidden' : ''}" data-slice="${i}"><i class="mr-[5px] inline-block h-[7px] w-[7px] rounded-[2px]" style="background:${donutColors[i % donutColors.length]}"></i>${label} (${fmt((values || [])[i])})</button>`;
        }).join('');
        legend.querySelectorAll('[data-slice]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const i = Number(btn.dataset.slice);
                hiddenThreatSlices[i] = !hiddenThreatSlices[i];
                if (threatsChart) {
                    threatsChart.toggleDataVisibility(i);
                    threatsChart.update();
                }
                renderThreatLegend(lastThreatLegend.labels, lastThreatLegend.values);
                updateDonutCenter(lastThreatLegend.labels, lastThreatLegend.values);
            });
        });
    }

    async function json(url) {
        const res = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
        if (!res.ok) throw new Error(`${url} (${res.status})`);
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error(`${url} (non-json)`);
        return res.json();
    }

    function dateParams() {
        try {
            const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
            const c = JSON.parse(localStorage.getItem('promotix-date-compare') || '{}');
            const p = new URLSearchParams();
            if (r.from) p.set('from', r.from);
            if (r.to) p.set('to', r.to);
            if (c.enabled) p.set('compare', '1');
            return p;
        } catch (e) {
            return new URLSearchParams();
        }
    }

    function deltaLabel(delta) {
        if (delta == null || Number.isNaN(Number(delta))) return '';
        const n = Number(delta);
        const sign = n > 0 ? '+' : '';
        return `<span class="ml-1 text-[9px] ${n > 0 ? 'text-rose-200' : (n < 0 ? 'text-emerald-200' : 'text-white/45')}">${sign}${n}</span>`;
    }

    function setMetric(id, value, delta) {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = `${fmt(value)}${deltaLabel(delta)}`;
    }

    function filterParams() {
        const params = dateParams();
        const domainId = document.getElementById('domain-filter')?.value || '';
        const path = document.getElementById('path-filter')?.value || '';
        const campaign = document.getElementById('campaign-filter')?.value || '';
        const trafficSource = document.getElementById('traffic-source-filter')?.value || '';
        if (domainId) params.set('domain_id', domainId);
        if (path) params.set('path', path);
        if (campaign) params.set('campaign', campaign);
        if (trafficSource) params.set('traffic_source', trafficSource);
        return params;
    }

    function apiUrl(path) {
        const qs = filterParams().toString();
        return qs ? `${path}?${qs}` : path;
    }

    function drawTrendDual(labels, datasets) {
        const canvas = document.getElementById('trends-chart');
        if (!canvas) return;
        const {ctx, w, h} = retina(canvas);
        ctx.clearRect(0, 0, w, h);
        const series = (datasets || []).map(d => ({ ...d, values: d.values || [] }));
        const max = Math.max(...series.flatMap(d => d.values), 1);
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
        series.forEach((ds, si) => {
            const values = ds.values;
            const points = values.map((value, i) => ({
                x: left + i * ((w - left - right) / Math.max(values.length - 1, 1)),
                y: h - bottom - (Number(value || 0) / max) * (h - top - bottom),
            }));
            if (si === 0) {
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
            }
            ctx.strokeStyle = ds.color || (si === 0 ? 'rgba(255,255,255,.72)' : '#FF4BC1');
            ctx.lineWidth = ds.dashed ? 1 : 1.5;
            if (ds.dashed) ctx.setLineDash([4, 4]); else ctx.setLineDash([]);
            ctx.beginPath();
            points.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
            ctx.stroke();
            ctx.setLineDash([]);
        });
    }

    let insightsTab = 'paid';
    let threatsChart = null;
    let hiddenThreatSlices = {};
    let lastThreatLegend = { labels: [], values: [] };
    let donutCenterMeta = { pct: 0, label: '', show: false };
    const donutColors = ['#D9D9D9', '#FFFFFF', '#B893D8', '#8C8C8C'];

    function updateDonutCenter(labels, values) {
        const visible = (values || []).map((value, index) => (
            hiddenThreatSlices[index] ? 0 : Number(value || 0)
        ));
        const total = visible.reduce((sum, value) => sum + value, 0);
        const pctEl = document.getElementById('threats-donut-pct');
        const labelEl = document.getElementById('threats-donut-label');
        const wrapEl = document.getElementById('threats-donut-center');
        if (!pctEl || !labelEl || !wrapEl) return;

        if (total <= 0 || !labels?.length) {
            donutCenterMeta = { pct: 0, label: '', show: false };
            pctEl.textContent = '—';
            labelEl.textContent = '';
            wrapEl.classList.add('is-empty');
            return;
        }

        let maxIdx = 0;
        let maxVal = 0;
        visible.forEach((value, index) => {
            if (value > maxVal) {
                maxVal = value;
                maxIdx = index;
            }
        });

        donutCenterMeta = {
            pct: Math.round((maxVal / total) * 100),
            label: labels[maxIdx] || '',
            show: true,
        };
        pctEl.textContent = `${donutCenterMeta.pct}%`;
        labelEl.textContent = donutCenterMeta.label.replace(/_/g, ' ');
        wrapEl.classList.remove('is-empty');
    }

    function setInsightsTab(tab) {
        insightsTab = tab;
        ['paid', 'bot'].forEach((name) => {
            const btn = document.getElementById(`insights-tab-${name}`);
            if (!btn) return;
            const active = name === tab;
            btn.classList.toggle('border-b-2', active);
            btn.classList.toggle('border-white', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('text-white/60', !active);
        });
        loadCharts();
    }

    async function loadSummary() {
        const data = await json(apiUrl('/overview/summary'));
        const paid = data.paidAdvertising || {};
        const bot = data.botProtection || {};
        const compare = data.compare || {};
        const paidDelta = compare.paidAdvertising || {};
        const botDelta = compare.botProtection || {};
        setMetric('suite-paid-clicks', paid.googleAdsClicks ?? paid.visits, paidDelta.googleAdsClicks ?? paidDelta.visits);
        setMetric('suite-paid-valid', paid.validClicks ?? Math.max(0, Number(paid.visits || 0) - Number(paid.invalidVisits || 0)), paidDelta.validClicks);
        setMetric('suite-paid-visits', paid.invalidClicks ?? paid.invalidVisits, paidDelta.invalidClicks ?? paidDelta.invalidVisits);
        setMetric('suite-bot-visitors', bot.totalVisitors, botDelta.totalVisitors);
        setMetric('suite-bot-detected', bot.botsDetected ?? bot.blockedHits, botDelta.botsDetected ?? botDelta.blockedHits);
        setMetric('suite-bot-blocked', bot.blockedHits, botDelta.blockedHits);
        const paidRate = Number(paid.protectionRate ?? paid.invalidRate ?? 0).toFixed(2);
        const botRate = Number(bot.detectionRate ?? bot.invalidRate ?? 0).toFixed(2);
        const paidRateEl = document.getElementById('suite-paid-rate');
        const botRateEl = document.getElementById('suite-bot-rate');
        if (paidRateEl) {
            paidRateEl.innerHTML = `${paidRate}%${deltaLabel(paidDelta.protectionRate ?? paidDelta.invalidRate)}`;
        }
        if (botRateEl) {
            botRateEl.innerHTML = `${botRate}%${deltaLabel(botDelta.detectionRate ?? botDelta.invalidRate)}`;
        }
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
        const suitePaidConn = document.getElementById('suite-paid-conn');
        if (suitePaidConn) {
            suitePaidConn.textContent = conn.protection || conn.tracking || '—';
            suitePaidConn.className = /healthy|online|active/i.test(suitePaidConn.textContent)
                ? 'mt-[2px] text-[10px] font-medium text-emerald-200'
                : 'mt-[2px] text-[10px] font-medium text-amber-100';
        }
        const suiteBotConn = document.getElementById('suite-bot-conn');
        if (suiteBotConn) {
            suiteBotConn.textContent = conn.tracking || '—';
            suiteBotConn.className = /healthy|online|active/i.test(suiteBotConn.textContent)
                ? 'mt-[2px] text-[10px] font-medium text-emerald-200'
                : 'mt-[2px] text-[10px] font-medium text-amber-100';
        }
        const lastEventEl = document.getElementById('conn-last-event');
        if (lastEventEl) {
            lastEventEl.textContent = conn.lastEventAt
                ? new Date(conn.lastEventAt).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                : '—';
            lastEventEl.className = 'text-white/80';
        }
        const versionEl = document.getElementById('conn-tracking-version');
        if (versionEl) {
            versionEl.textContent = conn.trackingVersion || '—';
            versionEl.className = 'text-white/80';
        }
        const eventsEl = document.getElementById('conn-events-today');
        if (eventsEl) {
            eventsEl.textContent = fmt(conn.eventsToday ?? 0);
            eventsEl.className = 'text-white/80';
        }
        if (data.dateRange?.from && data.dateRange?.to) {
            const label = document.getElementById('suite-range-label');
            if (label) {
                const cmp = data.compareRange ? ` · vs ${data.compareRange.from} → ${data.compareRange.to}` : '';
                label.textContent = `Showing ${data.dateRange.from} → ${data.dateRange.to}${cmp}`;
            }
        }
    }

    let domainRows = [];
    let domainFilter = 'all';
    let domainSearch = '';

    function riskTone(level) {
        const l = String(level || '').toLowerCase();
        if (l === 'high') return 'text-rose-200';
        if (l === 'medium') return 'text-amber-200';
        return 'text-emerald-200';
    }

    function renderDomainTable() {
        const body = document.getElementById('domain-performance-body');
        const q = domainSearch.trim().toLowerCase();
        const filtered = domainRows.filter((row) => {
            if (q && !(row.domain || '').toLowerCase().includes(q)) return false;
            if (domainFilter === 'invalid') return (row.threats || 0) > 0 || (row.invalidPct || 0) > 0;
            if (domainFilter === 'pending') return row.pending;
            return true;
        });
        body.innerHTML = filtered.length ? filtered.map((row) => `
            <tr>
                <td class="px-[8px] py-[6px]">${row.domain}${row.pending ? ' <span class="text-[9px] text-amber-200">(pending)</span>' : ''}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.clicks ?? row.visits)}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.visitors ?? row.visits)}</td>
                <td class="px-[8px] py-[6px]">${Number(row.invalidPct || 0).toFixed(1)}%</td>
                <td class="px-[8px] py-[6px] ${riskTone(row.riskLevel)}">${row.riskLevel || 'Low'} (${fmt(row.risk || 0)})</td>
                <td class="px-[8px] py-[6px]">${row.status || (row.pending ? 'Pending' : 'Active')}</td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="px-[8px] py-[8px] text-center text-white/75">No domains match this filter.</td></tr>';
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
        const list = document.getElementById('insight-list');
        try {
            const d = await json(apiUrl('/insights'));
            const feed = Array.isArray(d.feed) ? d.feed : [];
            if (!feed.length) {
                list.innerHTML = `
                    <article class="rounded-[6px] bg-[#0D0D0D]/82 px-[10px] py-[10px] text-[10px] text-white/70">
                        No high-risk detections in this range yet.
                    </article>`;
            } else {
                list.innerHTML = feed.map((item) => {
                    const severity = item.severity || 'medium';
                    const bar = severity === 'high' ? '#ef4444' : (severity === 'medium' ? '#f59e0b' : '#60a5fa');
                    const reasons = (item.reasons || []).slice(0, 3).map((r) => String(r).replace(/_/g, ' ')).join(' · ') || 'Review signals';
                    const time = item.at ? new Date(item.at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
                    const ip = String(item.ip || '').replace(/"/g, '&quot;');
                    const advancedHref = ip
                        ? `{{ route('paid-marketing.detailed') }}?ip=${encodeURIComponent(ip)}`
                        : `{{ route('paid-marketing.detailed') }}`;
                    return `
                    <article class="relative overflow-hidden rounded-[8px] bg-[#0D0D0D]/82 pl-[12px] pr-[10px] py-[10px] text-[10px] text-white cursor-pointer transition hover:bg-[#161616]"
                             style="border-left:3px solid ${bar}"
                             data-ip="${ip}"
                             onclick="window.dispatchEvent(new CustomEvent('promotix-open-ip-modal', { detail: { ip: this.dataset.ip } }))">
                        <div class="mb-[4px] flex items-center justify-between gap-2">
                            <span class="font-semibold text-white">${item.title || 'High Risk Click Detected'}</span>
                            <span class="rounded-[999px] px-[7px] py-[2px] text-[9px] uppercase"
                                  style="background:${bar}33;color:${bar}">${severity}</span>
                        </div>
                        <div class="space-y-[2px] text-white/75">
                            <div><span class="text-white/45">Campaign:</span> ${item.campaign || '—'}</div>
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <span><span class="text-white/45">IP:</span> ${item.ip || '—'}</span>
                                <span><span class="text-white/45">Risk:</span> ${Number(item.risk || 0)}%</span>
                                <span><span class="text-white/45">Action:</span> ${item.action || '—'}</span>
                            </div>
                            <div><span class="text-white/45">Reasons:</span> ${reasons}</div>
                        </div>
                        <div class="mt-[6px] flex items-center justify-between gap-2">
                            ${time ? `<div class="text-[9px] text-white/40">${time}</div>` : '<span></span>'}
                            <a href="${advancedHref}" class="text-[9px] text-[#B893D8] hover:text-white" onclick="event.stopPropagation()">Investigate →</a>
                        </div>
                    </article>`;
                }).join('');
            }
        } catch (error) {
            console.error(error);
            if (list) {
                list.innerHTML = `
                    <article class="rounded-[6px] bg-[#0D0D0D]/82 px-[10px] py-[10px] text-[10px] text-amber-100">
                        Couldn’t load live feed. Refresh or try another date range.
                    </article>`;
            }
        }
    }

    async function loadCampaignPerformance() {
        const body = document.getElementById('campaign-performance-body');
        if (!body) return;
        const rows = await json(apiUrl('/campaigns/performance'));
        body.innerHTML = (rows || []).length ? rows.map((row) => `
            <tr>
                <td class="px-[8px] py-[6px]">${row.campaign || '—'}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.clicks)}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.valid)}</td>
                <td class="px-[8px] py-[6px]">${fmt(row.invalid)}</td>
                <td class="px-[8px] py-[6px]">${Number(row.riskPct || 0).toFixed(1)}%</td>
                <td class="px-[8px] py-[6px]">$${Number(row.costSaved || 0).toFixed(2)}</td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="px-[8px] py-[8px] text-center text-white/75">No campaigns in this range.</td></tr>';
    }

    async function loadCampaignOptions(preserveValue = true) {
        const select = document.getElementById('campaign-filter');
        if (!select) return;
        const current = preserveValue ? (select.value || new URLSearchParams(location.search).get('campaign') || '') : '';
        const params = new URLSearchParams();
        const domainId = document.getElementById('domain-filter')?.value || '';
        if (domainId) params.set('domain_id', domainId);
        const rows = await json(params.toString() ? `/campaigns?${params}` : '/campaigns');
        const options = [`<option value="">All Campaigns</option>`].concat(
            (rows || []).map((row) => {
                const name = typeof row === 'string' ? row : (row.name || '');
                const label = typeof row === 'string' ? row : (row.label || row.name || '');
                const selected = current && current === name ? ' selected' : '';
                return `<option value="${String(name).replace(/"/g, '&quot;')}"${selected}>${label}</option>`;
            })
        );
        select.innerHTML = options.join('');
        if (current) select.value = current;
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
        hiddenThreatSlices = {};
        const qs = filterParams().toString();

        if (insightsTab === 'bot') {
            const trends = await json(qs ? `/bot-protection/invalid-traffic-trends?${qs}` : '/bot-protection/invalid-traffic-trends');
            const threats = await json(qs ? `/bot-protection/threat-groups?${qs}` : '/bot-protection/threat-groups');
            drawTrendDual(trends.labels || [], trends.datasets || []);
            renderDonut(threats.labels || [], threats.values || []);
            renderThreatLegend(threats.labels || [], threats.values || []);
            return;
        }

        const trends = await json(qs ? `/analytics/trends?${qs}` : '/analytics/trends');
        const threats = await json(qs ? `/analytics/threats?${qs}` : '/analytics/threats');
        drawTrend(trends.labels || [], trends.values || []);
        renderDonut(threats.labels || [], threats.values || []);
        renderThreatLegend(threats.labels || [], threats.values || []);
    }

    function syncHeaderDatesFromStorage() {
        /* Dates come from header range calendar via localStorage + promotix:date-range */
    }

    async function loadAll() {
        syncHeaderDatesFromStorage();
        try {
            try {
                await loadCampaignOptions(true);
            } catch (error) {
                console.error('campaign options', error);
            }
            const results = await Promise.allSettled([
                loadSummary(),
                loadInsights(),
                loadDomainTable(),
                loadCampaignPerformance(),
            ]);
            results.forEach((result, index) => {
                if (result.status === 'rejected') {
                    console.error(['summary', 'insights', 'domains', 'campaigns'][index], result.reason);
                }
            });
            if (results[0].status === 'rejected') {
                ['suite-paid-clicks', 'suite-paid-valid', 'suite-paid-visits', 'suite-bot-visitors', 'suite-bot-detected', 'suite-bot-blocked']
                    .forEach((id) => setMetric(id, 0));
                const paidRateEl = document.getElementById('suite-paid-rate');
                const botRateEl = document.getElementById('suite-bot-rate');
                if (paidRateEl) paidRateEl.textContent = '0.00%';
                if (botRateEl) botRateEl.textContent = '0.00%';
            }
            try {
                await loadCharts();
            } catch (error) {
                console.error('charts', error);
            }
        } catch (error) {
            console.error(error);
        } finally {
            window.promotixPageLoader?.hide();
        }
    }

    document.getElementById('insights-tab-paid')?.addEventListener('click', () => setInsightsTab('paid'));
    document.getElementById('insights-tab-bot')?.addEventListener('click', () => setInsightsTab('bot'));
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

    document.getElementById('domain-filter')?.addEventListener('change', async () => {
        await loadCampaignOptions(false);
        loadAll();
    });
    document.getElementById('campaign-filter')?.addEventListener('change', loadAll);
    document.getElementById('traffic-source-filter')?.addEventListener('change', loadAll);
    document.getElementById('path-filter')?.addEventListener('input', () => {
        clearTimeout(window.__figmaPathTimer);
        window.__figmaPathTimer = setTimeout(loadAll, FILTER_DEBOUNCE_MS);
    });

    const bootstrapParams = new URLSearchParams(location.search);
    if (bootstrapParams.get('domain_id')) {
        const domainEl = document.getElementById('domain-filter');
        if (domainEl) domainEl.value = bootstrapParams.get('domain_id');
    }
    if (bootstrapParams.get('path')) {
        const pathEl = document.getElementById('path-filter');
        if (pathEl) pathEl.value = bootstrapParams.get('path');
    }
    if (bootstrapParams.get('campaign')) {
        window.__pendingCampaign = bootstrapParams.get('campaign');
    }

    window.addEventListener('resize', () => loadCharts());
    loadAll().then(() => {
        if (window.__pendingCampaign) {
            const campaignEl = document.getElementById('campaign-filter');
            if (campaignEl) {
                campaignEl.value = window.__pendingCampaign;
                loadAll();
            }
            window.__pendingCampaign = null;
        }
    });
});
</script>
@endsection
