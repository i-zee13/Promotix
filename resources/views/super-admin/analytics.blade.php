@extends('layouts.super-admin')

@section('title', 'Analytics')

@section('content')
<x-super-admin.page title="Analytics">
    <div class="figma-sa-analytics space-y-[14px]" x-data="superAnalytics()" x-init="init()">
        <div class="figma-sa-subs-filters">
            <label class="figma-sa-subs-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model="filters.domain" placeholder="Search Domain" autocomplete="off">
            </label>

            <x-super-admin.dashboard-dropdown>
                <x-slot:trigger>
                    <button type="button" @click="open = !open" class="figma-sa-subs-filter-chip">
                        <span x-text="filters.range"></span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-action-item" @click="filters.range = 'All Time'">All Time</button>
                <button type="button" class="figma-sa-users-action-item" @click="filters.range = 'Last 7 Days'">Last 7 Days</button>
                <button type="button" class="figma-sa-users-action-item" @click="filters.range = 'Last 30 Days'">Last 30 Days</button>
            </x-super-admin.dashboard-dropdown>

            <x-super-admin.dashboard-dropdown>
                <x-slot:trigger>
                    <button type="button" @click="open = !open" class="figma-sa-subs-filter-chip figma-sa-subs-filter-chip--wide">
                        <span x-text="filters.status"></span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-action-item" @click="filters.status = 'All Statuses'">All Statuses</button>
                <button type="button" class="figma-sa-users-action-item" @click="filters.status = 'Active'">Active</button>
                <button type="button" class="figma-sa-users-action-item" @click="filters.status = 'Cancelled'">Cancelled</button>
                <button type="button" class="figma-sa-users-action-item" @click="filters.status = 'Paused'">Paused</button>
            </x-super-admin.dashboard-dropdown>

            <div class="figma-sa-subs-actions">
                <x-super-admin.dashboard-dropdown align="right">
                    <x-slot:trigger>
                        <button type="button" @click="open = !open" class="figma-sa-subs-filter-chip">
                            <span>Last 30 Days</span>
                            <span class="figma-sa-subs-chip-chevron">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </button>
                    </x-slot:trigger>
                    <button type="button" class="figma-sa-users-action-item">Last 7 Days</button>
                    <button type="button" class="figma-sa-users-action-item">Last 30 Days</button>
                    <button type="button" class="figma-sa-users-action-item">This Year</button>
                </x-super-admin.dashboard-dropdown>
                <button type="button" onclick="window.print()" class="figma-sa-subs-export-btn" title="Export">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Export
                </button>
            </div>
        </div>

        <section class="grid grid-cols-1 gap-[14px] xl:grid-cols-[minmax(0,1fr)_320px]">
            <article class="figma-sa-analytics-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-[24px] font-normal leading-tight text-white">MRR Growth</h2>
                        <p class="mt-[4px] text-[32px] leading-tight text-white">${{ number_format($mrrCurrent / 100, 0) }}</p>
                        <p class="mt-[2px] text-[13px] text-white/70">Last 30 Days</p>
                    </div>
                    <span class="figma-sa-analytics-badge" :class="{{ $mrrDelta }} >= 0 ? 'is-positive' : 'is-negative'">{{ $mrrDelta >= 0 ? '+' : '' }}{{ $mrrDelta }}%</span>
                </div>
                <div class="figma-sa-dash-chart-wrap mt-[16px]">
                    <canvas id="mrr-chart" class="figma-sa-dash-canvas" style="height:220px"></canvas>
                </div>
                <div class="mt-[10px] flex items-center gap-[8px]">
                    <button type="button" class="rounded-[6px] bg-white/20 px-[16px] py-[8px] text-[13px] font-semibold text-white">MRR</button>
                    <button type="button" class="rounded-[6px] px-[16px] py-[8px] text-[13px] font-semibold text-white/70 hover:bg-white/10">ARR</button>
                </div>
            </article>

            <div class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-1">
                <article class="figma-sa-analytics-card">
                    <h2 class="text-[20px] font-normal text-white">Churn Rate</h2>
                    <p class="mt-[6px] text-[36px] leading-tight text-white">{{ $churnRate }}%</p>
                    <div class="mt-[10px] flex flex-wrap items-center gap-[8px]">
                        <span class="figma-sa-analytics-badge is-negative">{{ $churnedCustomersCount }} lost</span>
                        <span class="figma-sa-analytics-badge" :class="{{ $churnDelta }} >= 0 ? 'is-positive' : 'is-negative'">{{ $churnDelta >= 0 ? '+' : '' }}{{ $churnDelta }}%</span>
                    </div>
                </article>

                <article class="figma-sa-analytics-card">
                    <div class="flex items-start justify-between">
                        <h2 class="text-[20px] font-normal text-white">LTV</h2>
                        <span class="figma-sa-analytics-badge is-positive">+{{ $conversionRate }}%</span>
                    </div>
                    <p class="mt-[6px] text-[36px] leading-tight text-white">${{ number_format($ltv / 100, 0) }}</p>
                    <div class="mt-[10px] flex flex-wrap items-center gap-[8px]">
                        <span class="figma-sa-analytics-badge is-positive">avg / customer</span>
                    </div>
                </article>
            </div>
        </section>

        <section class="figma-sa-analytics-card">
            <div class="flex items-start justify-between">
                <h2 class="text-[24px] font-normal text-white">Active Subscriptions</h2>
                <span class="figma-sa-analytics-badge" :class="{{ $activeSubsDelta }} >= 0 ? 'is-positive' : 'is-negative'">{{ $activeSubsDelta >= 0 ? '+' : '' }}{{ $activeSubsDelta }}%</span>
            </div>
            <p class="mt-[6px] text-[32px] leading-tight text-white">{{ number_format($activeSubsCount) }}</p>
            <div class="mt-[8px] flex flex-wrap items-center gap-[8px]">
                <span class="figma-sa-analytics-badge is-positive">7-day trend</span>
            </div>
            <div class="figma-sa-dash-chart-wrap mt-[14px]">
                <canvas id="active-subs-chart" class="figma-sa-dash-canvas" style="height:180px"></canvas>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-[14px] lg:grid-cols-3">
            <article class="figma-sa-analytics-card">
                <div class="flex items-start justify-between">
                    <h2 class="text-[20px] font-normal text-white">Customer Churn</h2>
                    <span class="figma-sa-dash-info" title="Cancellations this month">i</span>
                </div>
                <p class="mt-[6px] text-[32px] leading-tight text-white">{{ $churnRate }}%</p>
                <div class="mt-[8px] flex items-center gap-[8px]">
                    <span class="figma-sa-analytics-badge is-negative">{{ $churnDelta }}%</span>
                </div>
                <div class="mt-[10px] flex items-center justify-center">
                    <canvas id="churn-donut" width="140" height="140"></canvas>
                </div>
                <p class="text-[14px] text-white/85">Churned Customers:</p>
                <p class="text-[16px] font-medium text-white">{{ $churnedCustomersCount }} Customer{{ $churnedCustomersCount === 1 ? '' : 's' }}</p>
                <div class="figma-sa-analytics-card-footer mt-[10px]">
                    <span>Contraction MRR: <strong>-${{ number_format($contractionMrrCents / 100, 0) }} / mo.</strong></span>
                </div>
            </article>

            <article class="figma-sa-analytics-card">
                <div class="flex items-start justify-between">
                    <h2 class="text-[20px] font-normal text-white">LTV &amp; Conversion Rate</h2>
                    <span class="figma-sa-analytics-badge is-positive">{{ $conversionRate }}%</span>
                </div>
                <div class="mt-[10px] flex items-center gap-[16px]">
                    <canvas id="conversion-donut" width="110" height="110"></canvas>
                    <div class="space-y-[8px]">
                        <p class="text-[14px] text-white/85">Customers: <span class="text-[16px] font-medium text-white">${{ number_format($ltv / 100, 0) }}</span></p>
                        <p class="text-[14px] text-white/85">Trial Conversions <span class="text-[16px] font-medium text-white">{{ $conversionRate }}%</span></p>
                    </div>
                </div>
                <div class="figma-sa-analytics-card-footer mt-[10px] flex items-center justify-between">
                    <span>New Trials: <strong>{{ $newTrialsAvgPerDay }}</strong></span>
                    <span class="figma-sa-analytics-badge is-positive">{{ $newTrialsCount }} new</span>
                </div>
            </article>

            <article class="figma-sa-analytics-card !bg-transparent border-2 border-[#6706b3] p-0">
                <div class="flex items-center justify-between px-[18px] pt-[16px]">
                    <h2 class="text-[18px] font-normal text-[#d9d9d9]">Usage Analytics</h2>
                    <span class="text-[12px] text-white/50">Synced just now</span>
                </div>
                <div class="figma-sa-dash-table-scroll mt-[10px] max-h-[280px] px-[10px] pb-[12px]">
                    <table class="figma-sa-dash-table">
                        <thead>
                            <tr><th>Date</th><th>Active Users</th><th>Events Logged</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($usageRows as $row)
                                <tr>
                                    <td><span class="figma-sa-dash-date-pill">{{ $row['date'] }}</span></td>
                                    <td class="figma-sa-dash-table-muted">{{ number_format($row['active_users']) }}</td>
                                    <td class="figma-sa-dash-table-muted">{{ number_format($row['events_logged']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="figma-sa-dash-table-empty">No usage data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <a href="{{ route('super-admin.users.index') }}" class="figma-sa-analytics-card block text-center text-[14px] font-medium text-white/85 hover:text-white">
            Showing {{ min(5, $totalCustomers) }} of {{ number_format($totalCustomers) }} customers &mdash; view all
        </a>
    </div>

    <script>
        function superAnalytics() {
            const isLight = () => document.documentElement.classList.contains('light-mode');
            const filters = { domain: '', range: 'All Time', status: 'All Statuses' };

            const setupCanvas = (canvas) => {
                const dpr = window.devicePixelRatio || 1;
                const cssW = canvas.clientWidth;
                const cssH = canvas.clientHeight;
                if (cssW === 0 || cssH === 0) return null;
                canvas.width = cssW * dpr;
                canvas.height = cssH * dpr;
                const ctx = canvas.getContext('2d');
                ctx.scale(dpr, dpr);
                return { ctx, cssW, cssH };
            };

            const drawLine = (id, rows) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const setup = setupCanvas(canvas);
                if (!setup) return;
                const { ctx, cssW, cssH } = setup;
                ctx.clearRect(0, 0, cssW, cssH);
                const values = rows.map(r => Number(r.value || 0));
                const labels = rows.map(r => r.label);
                const max = Math.max(...values, 1);
                const pad = { l: 12, r: 12, t: 16, b: 28 };
                const plotW = cssW - pad.l - pad.r;
                const plotH = cssH - pad.t - pad.b;
                const points = values.map((v, i) => ({
                    x: pad.l + (plotW * i) / Math.max(values.length - 1, 1),
                    y: pad.t + plotH - (v / max) * plotH,
                }));
                ctx.setLineDash([4, 4]);
                ctx.strokeStyle = 'rgba(255,255,255,0.25)';
                ctx.lineWidth = 1;
                for (let i = 0; i <= 4; i++) {
                    const y = pad.t + (plotH * i) / 4;
                    ctx.beginPath();
                    ctx.moveTo(pad.l, y);
                    ctx.lineTo(cssW - pad.r, y);
                    ctx.stroke();
                }
                ctx.setLineDash([]);
                if (points.length > 1) {
                    ctx.strokeStyle = '#ffffff';
                    ctx.lineWidth = 2.5;
                    ctx.lineJoin = 'round';
                    ctx.lineCap = 'round';
                    ctx.beginPath();
                    points.forEach((p, i) => (i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y)));
                    ctx.stroke();
                    points.forEach((p) => {
                        ctx.fillStyle = '#ffffff';
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
                        ctx.fill();
                    });
                }
                ctx.fillStyle = '#ffffff';
                ctx.font = '12px Inter, system-ui, sans-serif';
                ctx.textAlign = 'center';
                labels.forEach((label, i) => {
                    const x = pad.l + (plotW * i) / Math.max(labels.length - 1, 1);
                    ctx.fillText(label, x, cssH - 8);
                });
            };

            const drawBars = (id, rows) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const setup = setupCanvas(canvas);
                if (!setup) return;
                const { ctx, cssW, cssH } = setup;
                ctx.clearRect(0, 0, cssW, cssH);
                const values = rows.map(r => Number(r.value || 0));
                const labels = rows.map(r => r.label);
                const max = Math.max(...values, 1);
                const pad = { l: 8, r: 8, t: 12, b: 24 };
                const plotW = cssW - pad.l - pad.r;
                const plotH = cssH - pad.t - pad.b;
                const slot = plotW / Math.max(values.length, 1);
                values.forEach((v, i) => {
                    const h = (v / max) * plotH;
                    const w = Math.max(16, slot * 0.5);
                    const x = pad.l + i * slot + (slot - w) / 2;
                    const y = pad.t + plotH - h;
                    ctx.fillStyle = i % 2 === 0 ? '#9a1aff' : '#d1b8ff';
                    const r = 4;
                    ctx.beginPath();
                    ctx.moveTo(x + r, y);
                    ctx.arcTo(x + w, y, x + w, y + r, r);
                    ctx.lineTo(x + w, y + h);
                    ctx.lineTo(x, y + h);
                    ctx.lineTo(x, y + r);
                    ctx.arcTo(x, y, x + r, y, r);
                    ctx.closePath();
                    ctx.fill();
                    ctx.fillStyle = '#d9d9d9';
                    ctx.font = '11px Inter, system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(labels[i], x + w / 2, cssH - 6);
                });
            };

            const drawDonut = (id, pct, color) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const w = canvas.width, h = canvas.height;
                const cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 8;
                ctx.clearRect(0, 0, w, h);
                ctx.lineWidth = 14;
                ctx.strokeStyle = 'rgba(255,255,255,0.18)';
                ctx.beginPath();
                ctx.arc(cx, cy, r, 0, Math.PI * 2);
                ctx.stroke();
                ctx.strokeStyle = color;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.arc(cx, cy, r, -Math.PI / 2, -Math.PI / 2 + (Math.PI * 2 * Math.min(pct, 100) / 100));
                ctx.stroke();
                ctx.fillStyle = '#ffffff';
                ctx.font = '600 16px Inter, system-ui, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(pct + '%', cx, cy);
            };

            const renderAll = () => {
                drawLine('mrr-chart', @js($mrrTrend));
                drawBars('active-subs-chart', @js($activeSubsTrend));
                drawDonut('churn-donut', {{ $churnRate }}, '#ffffff');
                drawDonut('conversion-donut', {{ $conversionRate }}, '#d1b8ff');
            };

            return {
                filters,
                init() {
                    requestAnimationFrame(renderAll);
                    window.addEventListener('resize', () => requestAnimationFrame(renderAll));
                    document.getElementById('theme-toggle')?.addEventListener('click', () => setTimeout(renderAll, 0));
                },
            };
        }
    </script>
</x-super-admin.page>
@endsection
