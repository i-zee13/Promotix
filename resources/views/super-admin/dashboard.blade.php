@extends('layouts.super-admin')

@section('title', 'Dashboard')

@section('content')
@php
    $failedRows = $failedPayments->map(function ($payment) {
        $at = $payment->created_at;

        return [
            'email' => $payment->user?->email ?? '—',
            'date' => $at?->format('M j, Y') ?? '—',
            'time' => $at?->format('g:i A') ?? '—',
            'url' => route('super-admin.payments.index', ['search' => $payment->invoice_number ?? $payment->id]),
        ];
    });
@endphp

<x-super-admin.page title="Dashboard">
    <div class="figma-sa-dashboard space-y-[18px]" x-data="superDashboard()" x-init="init()">
        <div class="figma-sa-dashboard-top">
            <section class="figma-sa-dashboard-kpis grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-3">
                <x-super-admin.dashboard-kpi label="Total users" :value="number_format($kpis['total_users'])" :progress="$kpiProgress['total_users']" />
                <x-super-admin.dashboard-kpi label="Active Subscriptions" :value="number_format($kpis['active_subscriptions'])" :progress="$kpiProgress['active_subscriptions']" />
                <x-super-admin.dashboard-kpi label="Monthly Revenue" :value="'$'.number_format($kpis['monthly_revenue_cents'] / 100, 0)" :progress="$kpiProgress['monthly_revenue']" />
                <x-super-admin.dashboard-kpi label="Failed Payments" :value="number_format($kpis['failed_payments'])" :progress="$kpiProgress['failed_payments']" />
                <x-super-admin.dashboard-kpi label="Active Domains" :value="number_format($kpis['active_domains'])" :progress="$kpiProgress['active_domains']" />
                <x-super-admin.dashboard-kpi label="Total Events Today" :value="number_format($kpis['total_events_today'])" :progress="$kpiProgress['total_events_today']" />
            </section>

            <x-super-admin.dashboard-failed-feed
                :rows="$failedRows"
                :load-more-route="route('super-admin.payments.index', ['status' => 'failed'])"
            />
        </div>

        <section class="grid grid-cols-1 gap-[14px] xl:grid-cols-2">
            <article class="figma-sa-dash-chart figma-sa-dash-chart--revenue">
                <div class="figma-sa-dash-chart-head">
                    <h2 class="figma-sa-dash-chart-title figma-sa-dash-chart-title--light">
                        <span class="figma-sa-dash-dot figma-sa-dash-dot--white" aria-hidden="true"></span>
                        Revenue trend
                    </h2>
                    <x-super-admin.dashboard-dropdown>
                        <x-slot:trigger>
                            <button type="button" @click="open = !open" class="figma-sa-dash-chart-menu-btn" aria-label="Chart options">
                                <svg class="h-[18px] w-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                            </button>
                        </x-slot:trigger>
                        <button type="button" class="figma-sa-dash-dropdown-item">Date range</button>
                        <button type="button" class="figma-sa-dash-dropdown-item">Domain filter</button>
                        <button type="button" class="figma-sa-dash-dropdown-item">Plan filter</button>
                        <button type="button" class="figma-sa-dash-dropdown-item">Region filter</button>
                    </x-super-admin.dashboard-dropdown>
                </div>
                <div class="figma-sa-dash-chart-wrap">
                    <canvas id="revenue-chart" class="figma-sa-dash-canvas"></canvas>
                </div>
            </article>

            <article class="figma-sa-dash-chart figma-sa-dash-chart--growth">
                <div class="figma-sa-dash-chart-head">
                    <h2 class="figma-sa-dash-chart-title">
                        <span class="figma-sa-dash-dot figma-sa-dash-dot--purple" aria-hidden="true"></span>
                        User Growth
                    </h2>
                    <x-super-admin.dashboard-dropdown>
                        <x-slot:trigger>
                            <button type="button" @click="open = !open" class="figma-sa-dash-chart-menu-btn figma-sa-dash-chart-menu-btn--dark" aria-label="Chart options">
                                <svg class="h-[18px] w-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                            </button>
                        </x-slot:trigger>
                        <button type="button" class="figma-sa-dash-dropdown-item">Date range</button>
                        <button type="button" class="figma-sa-dash-dropdown-item">Domain filter</button>
                        <button type="button" class="figma-sa-dash-dropdown-item">Plan filter</button>
                        <button type="button" class="figma-sa-dash-dropdown-item">Region filter</button>
                    </x-super-admin.dashboard-dropdown>
                </div>
                <div class="figma-sa-dash-chart-wrap figma-sa-dash-chart-wrap--y">
                    <div class="figma-sa-dash-y-axis" id="users-chart-y"></div>
                    <canvas id="users-chart" class="figma-sa-dash-canvas"></canvas>
                </div>
            </article>
        </section>

        <section class="figma-sa-dash-table-panel" x-data="{ q: '' }">
            <div class="figma-sa-dash-table-toolbar">
                <h2 class="figma-sa-dash-section-title">Active subscriptions</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="figma-sa-dash-search">
                        <svg class="h-[16px] w-[16px] shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" x-model="q" placeholder="Search" class="figma-sa-dash-search-input">
                    </label>
                    <x-super-admin.dashboard-dropdown>
                        <x-slot:trigger>
                            <button type="button" @click="open = !open" class="figma-sa-dash-filters">
                                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h10M4 17h6"/></svg>
                                Filters
                            </button>
                        </x-slot:trigger>
                        <a href="{{ route('super-admin.subscriptions.index') }}" class="figma-sa-dash-dropdown-item block text-left">All subscriptions</a>
                        <button type="button" class="figma-sa-dash-dropdown-item">Active only</button>
                        <button type="button" class="figma-sa-dash-dropdown-item">Trialing</button>
                    </x-super-admin.dashboard-dropdown>
                </div>
            </div>

            <div class="figma-sa-dash-table-scroll">
                <table class="figma-sa-dash-table">
                    <thead>
                        <tr>
                            <th class="w-[40px]"><input type="checkbox" class="figma-sa-checkbox rounded" aria-label="Select all"></th>
                            <th>Active</th>
                            <th>Mail</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th class="w-[52px] text-right">
                                <x-super-admin.dashboard-dropdown align="right">
                                    <x-slot:trigger>
                                        <button type="button" @click="open = !open" class="figma-sa-dash-info figma-sa-dash-info--btn" title="Table actions">i</button>
                                    </x-slot:trigger>
                                    <button type="button" class="figma-sa-dash-dropdown-item" onclick="window.location.reload()">Refresh</button>
                                    <a href="{{ route('super-admin.subscriptions.index') }}" class="figma-sa-dash-dropdown-item block text-left">Export</a>
                                    <button type="button" class="figma-sa-dash-dropdown-item">Customize</button>
                                    <button type="button" class="figma-sa-dash-dropdown-item">System Status</button>
                                </x-super-admin.dashboard-dropdown>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activeSubscriptions as $sub)
                            @php
                                $at = $sub->started_at ?? $sub->created_at;
                                $price = $sub->amount_cents
                                    ? format_money_cents((int) $sub->amount_cents, $sub->currency ?? 'USD')
                                    : ($sub->plan?->price_cents ? format_money_cents((int) $sub->plan->price_cents, $sub->plan->currency ?? 'USD') : '—');
                                $haystack = strtolower(($sub->user?->name ?? '').' '.($sub->user?->email ?? ''));
                            @endphp
                            <tr
                                data-search="{{ $haystack }}"
                                x-show="!q || $el.dataset.search.includes(q.toLowerCase())"
                                x-cloak
                            >
                                <td><input type="checkbox" class="figma-sa-checkbox rounded" aria-label="Select row"></td>
                                <td><p class="figma-sa-dash-table-name">{{ $sub->user?->name ?? '—' }}</p></td>
                                <td class="figma-sa-dash-table-mail">{{ $sub->user?->email ?? '—' }}</td>
                                <td><span class="figma-sa-dash-date-pill">{{ $at?->format('n/j/Y') ?? '—' }}</span></td>
                                <td class="figma-sa-dash-table-muted">{{ $at?->format('g:i A') ?? '—' }}</td>
                                <td class="figma-sa-dash-table-muted">{{ $price }}</td>
                                <td><span class="figma-sa-dash-status-pill">{{ ucfirst($sub->status) }}</span></td>
                                <td class="text-right">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" @click="open = !open" class="figma-sa-dash-row-menu" aria-label="Row actions">⋯</button>
                                        </x-slot:trigger>
                                        @if ($sub->user_id)
                                            <a href="{{ route('super-admin.users.show', $sub->user_id) }}" class="figma-sa-dash-dropdown-item block text-left">View user</a>
                                        @endif
                                        <a href="{{ route('super-admin.subscriptions.index') }}" class="figma-sa-dash-dropdown-item block text-left">Manage subscription</a>
                                    </x-super-admin.dashboard-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="figma-sa-dash-table-empty">No active subscriptions.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        function superDashboard() {
            const isLight = () => document.documentElement.classList.contains('light-mode');

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
                ctx.strokeStyle = isLight() ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.2)';
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

                ctx.fillStyle = isLight() ? '#f7f4fb' : '#ffffff';
                ctx.font = '12px Inter, system-ui, sans-serif';
                ctx.textAlign = 'center';
                labels.forEach((label, i) => {
                    const x = pad.l + (plotW * i) / Math.max(labels.length - 1, 1);
                    ctx.fillText(label, x, cssH - 8);
                });
            };

            const drawBars = (id, rows, yAxisId) => {
                const canvas = document.getElementById(id);
                const yAxis = document.getElementById(yAxisId);
                if (!canvas) return;
                const setup = setupCanvas(canvas);
                if (!setup) return;
                const { ctx, cssW, cssH } = setup;
                ctx.clearRect(0, 0, cssW, cssH);

                const values = rows.map(r => Number(r.value || 0));
                const labels = rows.map(r => r.label);
                const max = Math.max(...values, 1);
                const niceMax = Math.ceil(max / 2000) * 2000 || 2000;
                const pad = { l: 8, r: 8, t: 12, b: 28 };
                const plotW = cssW - pad.l - pad.r;
                const plotH = cssH - pad.t - pad.b;
                const slot = plotW / Math.max(values.length, 1);

                if (yAxis) {
                    yAxis.innerHTML = '';
                    for (let step = 0; step <= 5; step++) {
                        const val = Math.round((niceMax * (5 - step)) / 5);
                        const label = val >= 1000 ? `${Math.round(val / 1000)}k` : String(val);
                        const el = document.createElement('span');
                        el.textContent = label;
                        yAxis.appendChild(el);
                    }
                }

                ctx.setLineDash([4, 4]);
                ctx.strokeStyle = isLight() ? 'rgba(100,0,178,0.15)' : 'rgba(255,255,255,0.12)';
                for (let i = 0; i <= 4; i++) {
                    const y = pad.t + (plotH * i) / 4;
                    ctx.beginPath();
                    ctx.moveTo(pad.l, y);
                    ctx.lineTo(cssW - pad.r, y);
                    ctx.stroke();
                }
                ctx.setLineDash([]);

                values.forEach((v, i) => {
                    const h = (v / niceMax) * plotH;
                    const w = Math.max(20, slot * 0.55);
                    const x = pad.l + i * slot + (slot - w) / 2;
                    const y = pad.t + plotH - h;
                    ctx.fillStyle = i % 2 === 0 ? '#9a1aff' : '#6400b2';
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
                    ctx.fillStyle = isLight() ? '#5c5470' : '#d9d9d9';
                    ctx.font = '12px Inter, system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(labels[i], x + w / 2, cssH - 8);
                });
            };

            const renderAll = () => {
                drawLine('revenue-chart', @js($revenueTrend));
                drawBars('users-chart', @js($userGrowth), 'users-chart-y');
            };

            return {
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
