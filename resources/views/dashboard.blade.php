@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-9">
            <section class="grid grid-cols-1 gap-4 md:grid-cols-2" aria-label="Summary cards">
                <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                    <h2 class="text-sm font-medium text-gray-400">Paid Advertising summary</h2>
                    <p id="paid-visits" class="mt-2 text-3xl font-bold text-white">Loading...</p>
                    <p id="paid-campaigns" class="mt-1 text-sm text-gray-400"></p>
                </article>
                <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                    <h2 class="text-sm font-medium text-gray-400">Bot Protection summary</h2>
                    <p id="bot-blocked" class="mt-2 text-3xl font-bold text-white">Loading...</p>
                    <p id="bot-domains" class="mt-1 text-sm text-gray-400"></p>
                </article>
            </section>

            <section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-label="Insights panel">
                <h2 class="text-lg font-semibold text-white">Insights</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-dark px-4 py-3">
                        <p class="text-xs uppercase text-gray-500">Total clicks</p>
                        <p id="insight-clicks" class="mt-1 text-xl font-semibold text-white">—</p>
                    </div>
                    <div class="rounded-lg bg-dark px-4 py-3">
                        <p class="text-xs uppercase text-gray-500">Suspicious visits</p>
                        <p id="insight-suspicious" class="mt-1 text-xl font-semibold text-white">—</p>
                    </div>
                    <div class="rounded-lg bg-dark px-4 py-3 sm:col-span-2">
                        <p class="text-xs uppercase text-gray-500">Top campaign</p>
                        <p id="insight-campaign" class="mt-1 text-xl font-semibold text-white">—</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-label="Filters">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="campaign-filter" class="mb-1 block text-sm text-gray-400">Campaign dropdown</label>
                        <select id="campaign-filter" class="w-full rounded-lg border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                            <option value="">All campaigns</option>
                        </select>
                    </div>
                    <div>
                        <label for="path-filter" class="mb-1 block text-sm text-gray-400">Filter by path input</label>
                        <input id="path-filter" type="text" placeholder="/pricing" class="w-full rounded-lg border border-dark-border bg-dark px-3 py-2 text-sm text-white placeholder-gray-500">
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                    <h2 class="text-lg font-semibold text-white">Invalid Visits Trends</h2>
                    <canvas id="trends-chart" class="mt-4 h-48 w-full"></canvas>
                </article>
                <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                    <h2 class="text-lg font-semibold text-white">Threat distribution</h2>
                    <canvas id="threats-chart" class="mt-4 h-48 w-full"></canvas>
                </article>
            </section>

            <section class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Domain performance</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="border-b border-dark-border text-left text-gray-400">
                            <th class="py-2 pr-4">Domain</th>
                            <th class="py-2 pr-4">Visits</th>
                            <th class="py-2 pr-4">Threats</th>
                        </tr>
                        </thead>
                        <tbody id="domain-performance-body" class="text-white">
                        <tr><td colspan="3" class="py-4 text-gray-400">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Tools grid</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <a href="{{ route('analytics') }}" class="rounded-lg border border-dark-border bg-dark px-3 py-2 text-sm text-gray-200">Analytics</a>
                    <a href="{{ route('domains.index') }}" class="rounded-lg border border-dark-border bg-dark px-3 py-2 text-sm text-gray-200">Domains</a>
                    <a href="{{ route('integrations') }}" class="rounded-lg border border-dark-border bg-dark px-3 py-2 text-sm text-gray-200">Integrations</a>
                    <a href="{{ route('security-logs') }}" class="rounded-lg border border-dark-border bg-dark px-3 py-2 text-sm text-gray-200">Security</a>
                </div>
            </section>
        </div>

        <aside class="xl:col-span-3">
            <section class="rounded-xl border border-dark-border bg-dark-card p-6">
                <h2 class="text-lg font-semibold text-white">Notifications</h2>
                <div id="notification-cards" class="mt-4 space-y-3">
                    <div class="rounded-lg border border-dark-border bg-dark px-4 py-3 text-sm text-gray-400">Loading...</div>
                </div>
            </section>
        </aside>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const campaignFilter = document.getElementById('campaign-filter');
            const pathFilter = document.getElementById('path-filter');

            const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));

            const drawBars = (canvasId, labels, values, color) => {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const w = canvas.width = canvas.clientWidth * (window.devicePixelRatio || 1);
                const h = canvas.height = canvas.clientHeight * (window.devicePixelRatio || 1);
                ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
                ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
                const max = Math.max(...values, 1);
                const barW = Math.max(12, (canvas.clientWidth - 24) / Math.max(values.length, 1) - 8);
                values.forEach((v, i) => {
                    const x = 16 + i * (barW + 8);
                    const bh = (v / max) * (canvas.clientHeight - 34);
                    const y = canvas.clientHeight - 22 - bh;
                    ctx.fillStyle = color;
                    ctx.fillRect(x, y, barW, bh);
                    ctx.fillStyle = '#9CA3AF';
                    ctx.font = '10px sans-serif';
                    ctx.fillText(String(labels[i] || ''), x, canvas.clientHeight - 8);
                });
                ctx.fillStyle = '#FFFFFF';
            };

            const loadSummary = async () => {
                const res = await fetch('/overview/summary');
                const data = await res.json();
                document.getElementById('paid-visits').textContent = fmt(data.paidAdvertising.visits);
                document.getElementById('paid-campaigns').textContent = `${fmt(data.paidAdvertising.campaigns)} campaigns`;
                document.getElementById('bot-blocked').textContent = fmt(data.botProtection.blockedHits);
                document.getElementById('bot-domains').textContent = `${fmt(data.botProtection.domainsProtected)} domains protected`;
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
                    body.innerHTML = '<tr><td colspan="3" class="py-4 text-gray-400">No domain performance data yet.</td></tr>';
                    return;
                }
                rows.forEach((r) => {
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-dark-border';
                    tr.innerHTML = `<td class="py-2 pr-4">${r.domain}</td><td class="py-2 pr-4">${fmt(r.visits)}</td><td class="py-2 pr-4">${fmt(r.threats)}</td>`;
                    body.appendChild(tr);
                });
            };

            const loadNotifications = async () => {
                const res = await fetch('/notifications');
                const items = await res.json();
                const wrap = document.getElementById('notification-cards');
                const dismissed = JSON.parse(localStorage.getItem('promotix-dismissed-notifications') || '[]');
                wrap.innerHTML = '';
                items.filter((n) => !dismissed.includes(n.title)).forEach((n) => {
                    const el = document.createElement('article');
                    el.className = 'rounded-lg border border-dark-border bg-dark px-4 py-3';
                    el.innerHTML = `<div class="flex items-start justify-between gap-2"><h3 class="text-sm font-semibold text-white">${n.title}</h3><button class="text-xs text-gray-500 hover:text-white" data-dismiss="${n.title}">Dismiss</button></div><p class="mt-1 text-xs text-gray-400">${n.body}</p>`;
                    wrap.appendChild(el);
                });
                wrap.querySelectorAll('[data-dismiss]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const key = btn.getAttribute('data-dismiss');
                        const next = Array.from(new Set([...dismissed, key]));
                        localStorage.setItem('promotix-dismissed-notifications', JSON.stringify(next));
                        loadNotifications();
                    });
                });
            };

            const loadTrends = async () => {
                const params = new URLSearchParams();
                if (campaignFilter.value) params.set('campaign', campaignFilter.value);
                if (pathFilter.value) params.set('path', pathFilter.value);
                const res = await fetch(`/analytics/trends?${params.toString()}`);
                const d = await res.json();
                drawBars('trends-chart', d.labels || [], d.values || [], '#8B5CF6');
            };

            const loadThreats = async () => {
                const res = await fetch('/analytics/threats');
                const d = await res.json();
                drawBars('threats-chart', d.labels || [], d.values || [], '#EF4444');
            };

            const loadAll = async () => {
                try {
                    await Promise.all([loadSummary(), loadInsights(), loadCampaigns(), loadDomainTable(), loadNotifications()]);
                    await Promise.all([loadTrends(), loadThreats()]);
                } catch (e) {
                    console.error(e);
                }
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
                                document.getElementById('paid-campaigns').textContent = `${fmt(data.paidAdvertising.campaigns)} campaigns`;
                                document.getElementById('bot-blocked').textContent = fmt(data.botProtection.blockedHits);
                                document.getElementById('bot-domains').textContent = `${fmt(data.botProtection.domainsProtected)} domains protected`;
                            }
                            if (Array.isArray(data?.notifications)) {
                                const wrap = document.getElementById('notification-cards');
                                const dismissed = JSON.parse(localStorage.getItem('promotix-dismissed-notifications') || '[]');
                                wrap.innerHTML = '';
                                data.notifications.filter((n) => !dismissed.includes(n.title)).forEach((n) => {
                                    const el = document.createElement('article');
                                    el.className = 'rounded-lg border border-dark-border bg-dark px-4 py-3';
                                    el.innerHTML = `<div class="flex items-start justify-between gap-2"><h3 class="text-sm font-semibold text-white">${n.title}</h3><button class="text-xs text-gray-500 hover:text-white" data-dismiss="${n.title}">Dismiss</button></div><p class="mt-1 text-xs text-gray-400">${n.body}</p>`;
                                    wrap.appendChild(el);
                                });
                                wrap.querySelectorAll('[data-dismiss]').forEach((btn) => {
                                    btn.addEventListener('click', () => {
                                        const key = btn.getAttribute('data-dismiss');
                                        const next = Array.from(new Set([...dismissed, key]));
                                        localStorage.setItem('promotix-dismissed-notifications', JSON.stringify(next));
                                        loadNotifications();
                                    });
                                });
                            }
                        } catch (err) {
                            console.error(err);
                        }
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
