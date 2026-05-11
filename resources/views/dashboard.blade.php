@extends('layouts.admin')

@section('title', 'Overview')
@section('subtitle', 'Live snapshot of your traffic, threats, and campaigns')

@section('content')
    <x-ui.page-header
        title="Overview"
        subtitle="Live snapshot of your traffic, threats, and campaigns">
        <x-slot:actions>
            <x-ui.button variant="outline" size="sm" href="{{ route('domains.index') }}">
                Manage domains
            </x-ui.button>
            <x-ui.button variant="primary" size="sm" href="{{ route('paid-marketing.dashboard') }}">
                Paid Advertising
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-9">
            {{-- KPI cards --}}
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Summary">
                <x-ui.kpi-card label="Paid visits" id="paid-visits" value="—" hint="—">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3v18h18M7 14l3-3 4 4 5-6"/></svg>
                    </x-slot:icon>
                </x-ui.kpi-card>

                <x-ui.kpi-card label="Bot blocked" id="bot-blocked" value="—" hint="—">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v6c0 4.5-3.4 8.4-8 9-4.6-.6-8-4.5-8-9V6l8-3z"/></svg>
                    </x-slot:icon>
                </x-ui.kpi-card>

                <x-ui.kpi-card label="Total clicks" id="insight-clicks" value="—">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                    </x-slot:icon>
                </x-ui.kpi-card>

                <x-ui.kpi-card label="Suspicious visits" id="insight-suspicious" value="—" trend="down">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    </x-slot:icon>
                </x-ui.kpi-card>
            </section>

            {{-- Top campaign --}}
            <x-ui.card title="Top campaign" subtitle="Highest performing source in the selected window">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500/15 text-brand-200">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 11l18-7-7 18-2-8-9-3z"/></svg>
                    </div>
                    <p id="insight-campaign" class="text-xl font-semibold text-white">—</p>
                </div>
            </x-ui.card>

            {{-- Filters --}}
            <x-ui.card title="Filters">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="campaign-filter" class="brand-label mb-1.5">Campaign</label>
                        <select id="campaign-filter" class="brand-select">
                            <option value="">All campaigns</option>
                        </select>
                    </div>
                    <div>
                        <label for="path-filter" class="brand-label mb-1.5">Filter by path</label>
                        <input id="path-filter" type="text" placeholder="/pricing" class="brand-input">
                    </div>
                </div>
            </x-ui.card>

            {{-- Charts --}}
            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <x-ui.card title="Invalid Visits Trends" subtitle="Suspicious + blocked over time">
                    <canvas id="trends-chart" class="mt-2 h-48 w-full"></canvas>
                </x-ui.card>
                <x-ui.card title="Threat distribution" subtitle="Top threat groups detected">
                    <canvas id="threats-chart" class="mt-2 h-48 w-full"></canvas>
                </x-ui.card>
            </section>

            {{-- Domain performance table --}}
            <x-ui.card title="Domain performance" subtitle="Visits and threats per registered domain">
                <div class="overflow-x-auto">
                    <table class="brand-table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Visits</th>
                                <th>Threats</th>
                            </tr>
                        </thead>
                        <tbody id="domain-performance-body">
                            <tr><td colspan="3" class="py-4 text-center text-night-300">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            {{-- Tools --}}
            <x-ui.card title="Quick links">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <a href="{{ route('paid-marketing.dashboard') }}" class="brand-card-flat flex items-center gap-3 hover:border-brand-400">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/15 text-brand-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3v18h18M7 14l3-3 4 4 5-6"/></svg>
                        </span>
                        <span class="text-sm font-medium text-night-100">Paid Ads</span>
                    </a>
                    <a href="{{ route('domains.index') }}" class="brand-card-flat flex items-center gap-3 hover:border-brand-400">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/15 text-brand-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="1.8"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
                        </span>
                        <span class="text-sm font-medium text-night-100">Domains</span>
                    </a>
                    <a href="{{ route('integrations') }}" class="brand-card-flat flex items-center gap-3 hover:border-brand-400">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/15 text-brand-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 2v4M15 2v4M7 8h10v4a5 5 0 11-10 0V8zM12 17v5"/></svg>
                        </span>
                        <span class="text-sm font-medium text-night-100">Integrations</span>
                    </a>
                    <a href="{{ route('bot-protection.dashboard') }}" class="brand-card-flat flex items-center gap-3 hover:border-brand-400">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/15 text-brand-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v6c0 4.5-3.4 8.4-8 9-4.6-.6-8-4.5-8-9V6l8-3z"/></svg>
                        </span>
                        <span class="text-sm font-medium text-night-100">Bot Protection</span>
                    </a>
                </div>
            </x-ui.card>
        </div>

        {{-- Right rail --}}
        <aside class="space-y-6 xl:col-span-3">
            <x-ui.card title="Notifications" subtitle="Recent alerts and reminders">
                <div id="notification-cards" class="space-y-3">
                    <div class="brand-card-flat text-sm text-night-300">Loading…</div>
                </div>
            </x-ui.card>

            <x-ui.card title="System status" subtitle="Pipeline + tracking health">
                <x-ui.stat-row label="Tracking script" value="OK" tone="success" pill="Healthy" />
                <x-ui.stat-row label="Ingestion API" value="OK" tone="success" pill="Healthy" />
                <x-ui.stat-row label="Hourly aggregator" value="Cron" tone="purple" pill="Scheduled" />
            </x-ui.card>
        </aside>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const campaignFilter = document.getElementById('campaign-filter');
            const pathFilter = document.getElementById('path-filter');
            const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));

            const drawBars = (canvasId, labels, values, color) => {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                canvas.width = canvas.clientWidth * (window.devicePixelRatio || 1);
                canvas.height = canvas.clientHeight * (window.devicePixelRatio || 1);
                ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
                ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
                const max = Math.max(...values, 1);
                const barW = Math.max(12, (canvas.clientWidth - 24) / Math.max(values.length, 1) - 8);
                values.forEach((v, i) => {
                    const x = 16 + i * (barW + 8);
                    const bh = (v / max) * (canvas.clientHeight - 34);
                    const y = canvas.clientHeight - 22 - bh;
                    const grad = ctx.createLinearGradient(0, y, 0, canvas.clientHeight - 22);
                    grad.addColorStop(0, color);
                    grad.addColorStop(1, color + '55');
                    ctx.fillStyle = grad;
                    ctx.beginPath();
                    const r = 6;
                    ctx.moveTo(x + r, y);
                    ctx.lineTo(x + barW - r, y);
                    ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
                    ctx.lineTo(x + barW, y + bh);
                    ctx.lineTo(x, y + bh);
                    ctx.lineTo(x, y + r);
                    ctx.quadraticCurveTo(x, y, x + r, y);
                    ctx.closePath();
                    ctx.fill();
                    ctx.fillStyle = '#9FA1C2';
                    ctx.font = '10px Inter, sans-serif';
                    ctx.fillText(String(labels[i] || ''), x, canvas.clientHeight - 8);
                });
            };

            const loadSummary = async () => {
                const res = await fetch('/overview/summary');
                const data = await res.json();
                document.getElementById('paid-visits').textContent = fmt(data.paidAdvertising.visits);
                document.getElementById('bot-blocked').textContent = fmt(data.botProtection.blockedHits);
            };

            const loadInsights = async () => {
                const res = await fetch('/insights');
                const d = await res.json();
                document.getElementById('insight-clicks').textContent = fmt(d.totalClicks);
                document.getElementById('insight-suspicious').textContent = fmt(d.suspiciousVisits);
                document.getElementById('insight-campaign').textContent = `${d.topCampaign} (${fmt(d.topCampaignClicks)} clicks)`;
            };

            const loadCampaigns = async () => {
                const res = await fetch('/campaigns');
                const list = await res.json();
                list.forEach((c) => {
                    const opt = document.createElement('option');
                    opt.value = c;
                    opt.textContent = c;
                    campaignFilter.appendChild(opt);
                });
            };

            const loadDomainTable = async () => {
                const res = await fetch('/domains/performance');
                const rows = await res.json();
                const body = document.getElementById('domain-performance-body');
                body.innerHTML = '';
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="3" class="py-4 text-center text-night-300">No domain performance data yet.</td></tr>';
                    return;
                }
                rows.forEach((r) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td class="font-medium">${r.domain}</td><td>${fmt(r.visits)}</td><td><span class="brand-pill brand-pill-warning">${fmt(r.threats)}</span></td>`;
                    body.appendChild(tr);
                });
            };

            const renderNotifications = (items) => {
                const wrap = document.getElementById('notification-cards');
                const dismissed = JSON.parse(localStorage.getItem('promotix-dismissed-notifications') || '[]');
                wrap.innerHTML = '';
                const visible = items.filter((n) => !dismissed.includes(n.title));
                if (!visible.length) {
                    wrap.innerHTML = '<div class="brand-card-flat text-sm text-night-300">All caught up.</div>';
                    return;
                }
                visible.forEach((n) => {
                    const el = document.createElement('article');
                    el.className = 'brand-card-flat';
                    el.innerHTML = `<div class="flex items-start justify-between gap-2"><h3 class="text-sm font-semibold text-white">${n.title}</h3><button class="text-xs text-night-400 hover:text-white" data-dismiss="${n.title}">Dismiss</button></div><p class="mt-1 text-xs text-night-300">${n.body}</p>`;
                    wrap.appendChild(el);
                });
                wrap.querySelectorAll('[data-dismiss]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const key = btn.getAttribute('data-dismiss');
                        const next = Array.from(new Set([...dismissed, key]));
                        localStorage.setItem('promotix-dismissed-notifications', JSON.stringify(next));
                        renderNotifications(items);
                    });
                });
            };

            const loadNotifications = async () => {
                const res = await fetch('/notifications');
                const items = await res.json();
                renderNotifications(items);
            };

            const loadTrends = async () => {
                const params = new URLSearchParams();
                if (campaignFilter.value) params.set('campaign', campaignFilter.value);
                if (pathFilter.value) params.set('path', pathFilter.value);
                const res = await fetch(`/analytics/trends?${params.toString()}`);
                const d = await res.json();
                drawBars('trends-chart', d.labels || [], d.values || [], '#7C3AED');
            };

            const loadThreats = async () => {
                const res = await fetch('/analytics/threats');
                const d = await res.json();
                drawBars('threats-chart', d.labels || [], d.values || [], '#F43F5E');
            };

            const loadAll = async () => {
                try {
                    await Promise.all([loadSummary(), loadInsights(), loadCampaigns(), loadDomainTable(), loadNotifications()]);
                    await Promise.all([loadTrends(), loadThreats()]);
                } catch (e) { console.error(e); }
            };

            const wireLiveStream = () => {
                if (!window.EventSource) return;
                let es = null;
                let reconnectTimer = null;
                const connect = () => {
                    if (es) es.close();
                    es = new EventSource('/dashboard/live-stream');
                    es.addEventListener('snapshot', (evt) => {
                        try {
                            const data = JSON.parse(evt.data || '{}');
                            if (data?.paidAdvertising && data?.botProtection) {
                                document.getElementById('paid-visits').textContent = fmt(data.paidAdvertising.visits);
                                document.getElementById('bot-blocked').textContent = fmt(data.botProtection.blockedHits);
                            }
                            if (Array.isArray(data?.notifications)) {
                                renderNotifications(data.notifications);
                            }
                        } catch (err) { console.error(err); }
                    });
                    es.onerror = () => {
                        es?.close();
                        clearTimeout(reconnectTimer);
                        reconnectTimer = setTimeout(connect, 2000);
                    };
                };
                connect();
            };

            campaignFilter.addEventListener('change', loadTrends);
            pathFilter.addEventListener('input', () => {
                clearTimeout(window.__pathFilterTimer);
                window.__pathFilterTimer = setTimeout(loadTrends, 350);
            });
            window.addEventListener('resize', () => {
                loadTrends();
                loadThreats();
            });

            loadAll();
            wireLiveStream();
        });
    </script>
@endsection
