@extends('layouts.super-admin')

@section('title', 'Admin Dashboard')
@section('subtitle', 'Real platform data across users, billing, domains, and events')

@section('content')
    <div class="space-y-6" x-data="superDashboard()" x-init="init()">
        {{-- KPI grid --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <x-ui.kpi-card label="Total Users" :value="number_format($kpis['total_users'])" />
            <x-ui.kpi-card label="Active Subscriptions" :value="number_format($kpis['active_subscriptions'])" />
            <x-ui.kpi-card label="Monthly Revenue" :value="'$'.number_format($kpis['monthly_revenue_cents'] / 100, 2)" />
            <x-ui.kpi-card label="Failed Payments" :value="number_format($kpis['failed_payments'])" />
            <x-ui.kpi-card label="Active Domains" :value="number_format($kpis['active_domains'])" />
            <x-ui.kpi-card label="Total Events" :value="number_format($kpis['total_events'])" />
        </section>

        {{-- Revenue + Health --}}
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <x-ui.card class="xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-night-100">Revenue Trend</h2>
                    <span class="brand-pill brand-pill-purple">Last 6 months</span>
                </div>
                <canvas id="revenue-chart" class="mt-4 h-64 w-full"></canvas>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-base font-semibold text-night-100">System Health</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($systemHealth as $label => $value)
                        <div class="flex items-center justify-between rounded-xl border border-night-700/60 bg-night-800/60 px-4 py-3">
                            <span class="capitalize text-sm text-night-200">{{ $label }}</span>
                            <span class="brand-pill brand-pill-success">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </section>

        {{-- Growth + Usage --}}
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <x-ui.card>
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-night-100">User Growth</h2>
                    <span class="brand-pill brand-pill-neutral">Monthly</span>
                </div>
                <canvas id="users-chart" class="mt-4 h-56 w-full"></canvas>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-base font-semibold text-night-100">Usage by Product</h2>
                <div class="mt-4 space-y-4">
                    @php $maxUsage = max(1, collect($usageByProduct)->max('value')); @endphp
                    @foreach ($usageByProduct as $row)
                        <div>
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <span class="text-night-200">{{ $row['label'] }}</span>
                                <span class="font-semibold text-night-100">{{ number_format($row['value']) }}</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-night-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600" style="width: {{ min(100, ($row['value'] / $maxUsage) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </section>
    </div>

    <script>
        function superDashboard() {
            const isLight = () => document.documentElement.classList.contains('light-mode');

            const draw = (id, rows, color) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const dpr = window.devicePixelRatio || 1;
                const cssW = canvas.clientWidth;
                const cssH = canvas.clientHeight;
                if (cssW === 0 || cssH === 0) return;
                canvas.width  = cssW * dpr;
                canvas.height = cssH * dpr;
                const ctx = canvas.getContext('2d');
                ctx.scale(dpr, dpr);
                ctx.clearRect(0, 0, cssW, cssH);

                const labels = rows.map(r => r.label);
                const values = rows.map(r => Number(r.value || 0));
                const max = Math.max(...values, 1);
                const slot = (cssW - 24) / Math.max(values.length, 1);
                const labelColor = isLight() ? '#52557E' : '#94a3b8';

                values.forEach((v, i) => {
                    const h = (v / max) * (cssH - 36);
                    const x = 12 + i * slot + 8;
                    const y = cssH - 24 - h;
                    const w = Math.max(16, slot - 16);

                    const grad = ctx.createLinearGradient(0, y, 0, y + h);
                    grad.addColorStop(0, color);
                    grad.addColorStop(1, color + '99');
                    ctx.fillStyle = grad;
                    const r = Math.min(8, w / 2);
                    ctx.beginPath();
                    ctx.moveTo(x + r, y);
                    ctx.arcTo(x + w, y, x + w, y + r, r);
                    ctx.lineTo(x + w, y + h);
                    ctx.lineTo(x, y + h);
                    ctx.lineTo(x, y + r);
                    ctx.arcTo(x, y, x + r, y, r);
                    ctx.closePath();
                    ctx.fill();

                    ctx.fillStyle = labelColor;
                    ctx.font = '11px Inter, system-ui, sans-serif';
                    ctx.fillText(labels[i], x - 2, cssH - 8);
                });
            };

            const renderAll = () => {
                draw('revenue-chart', @js($revenueTrend), '#7c3aed');
                draw('users-chart',   @js($userGrowth),   '#a78bfa');
            };

            return {
                init() {
                    requestAnimationFrame(renderAll);
                    window.addEventListener('resize', () => requestAnimationFrame(renderAll));
                    document.getElementById('theme-toggle')?.addEventListener('click', () => {
                        setTimeout(renderAll, 0);
                    });
                }
            }
        }
    </script>
@endsection
