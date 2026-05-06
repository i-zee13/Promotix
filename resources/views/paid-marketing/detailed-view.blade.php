@extends('layouts.admin')

@section('title', 'Paid Marketing — Detailed View')

@section('content')
    <div class="space-y-6" x-data="paidMarketingDetailed()">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('paid-marketing.detailed') }}" class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">Detailed View</a>
            <a href="{{ route('integrations') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Platform Connections</a>
            <a href="{{ route('paid-marketing.detection-settings') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detection Settings</a>
        </div>

        <section class="flex flex-col gap-4 rounded-xl border border-dark-border bg-dark-card p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="sr-only" for="filter-ip">Filter by IP</label>
                        <input id="filter-ip" type="search" placeholder="Filter by IP" x-model="filters.ip"
                               class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    </div>
                    <div>
                        <label class="sr-only" for="filter-path">Filter by path</label>
                        <input id="filter-path" type="search" placeholder="Filter by path" x-model="filters.path"
                               class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    </div>
                    <div>
                        <label class="sr-only" for="filter-platform">Platforms</label>
                        <select id="filter-platform" x-model="filters.platform"
                                class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                            <option value="">All platforms</option>
                            @foreach ($platforms as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="sr-only" for="filter-from">From</label>
                            <input id="filter-from" type="date" x-model="filters.from"
                                   class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-3 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                        </div>
                        <div>
                            <label class="sr-only" for="filter-to">To</label>
                            <input id="filter-to" type="date" x-model="filters.to"
                                   class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-3 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" @click="applyFilters()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 018 17v-3.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                        Advanced Filters
                    </button>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-dark-border bg-accent">
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">IP Address</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Last Seen</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Threat Group</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Threat Type</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Action Taken</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Country</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Domain</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">URL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                        @forelse ($visits as $visit)
                            <tr class="cursor-pointer transition hover:bg-gray-800/50" @click="openClicks(@js($visit))">
                                <td class="px-4 py-3 font-semibold text-white">
                                    <div class="flex flex-col gap-1">
                                        <span>{{ $visit->ip }}</span>
                                        <span class="text-xs text-gray-400">Clicks: {{ $visit->clicks?->count() ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ $visit->last_click_at?->format('m/d/y H:i:s') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $visit->threat_group ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $visit->threat_type ?? 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $hasThreat = filled($visit->threat_group) || filled($visit->threat_type);
                                        $blocked = (bool) ($visit->ip_is_blocked ?? false);
                                        $action = $blocked ? 'Blocked' : ($hasThreat ? 'Detected' : '—');
                                        $cls = $blocked ? 'bg-red-500/20 text-red-300' : ($hasThreat ? 'bg-amber-500/20 text-amber-300' : 'bg-gray-500/20 text-gray-300');
                                    @endphp
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium {{ $cls }}">{{ $action }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-300">
                                    <span class="inline-flex items-center gap-2">
                                        @if ($visit->country)
                                            <span>{{ $visit->country }}</span>
                                        @else
                                            <span>—</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ $visit->domain?->hostname ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $visit->last_path ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-400">No rows yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($visits->hasPages())
                <div class="border-t border-dark-border px-4 py-3">
                    {{ $visits->links() }}
                </div>
            @endif
        </section>

        {{-- Click Details Modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
             x-show="modal.open"
             x-cloak
             x-transition
             @keydown.escape.window="closeModal()"
             @click.self="closeModal()"
        >
            <div class="w-full max-w-5xl rounded-xl border border-dark-border bg-dark-card shadow-xl">
                <div class="flex items-center justify-between border-b border-dark-border px-6 py-4">
                    <h3 class="text-lg font-semibold text-white">Click Details</h3>
                    <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-dark-border hover:text-white" @click="closeModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-0 lg:grid-cols-4">
                    <aside class="border-b border-dark-border lg:border-b-0 lg:border-r border-dark-border p-4">
                        <template x-for="(c, idx) in modal.clicks" :key="c.id ?? idx">
                            <button type="button"
                                    class="mb-2 w-full rounded-lg border border-dark-border bg-dark px-3 py-2 text-left hover:bg-dark-border"
                                    :class="idx === modal.activeIndex ? 'ring-1 ring-accent' : ''"
                                    @click="modal.activeIndex = idx"
                            >
                                <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                                <p class="text-xs text-gray-400" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            </button>
                        </template>
                        <template x-if="modal.clicks.length === 0">
                            <p class="text-sm text-gray-400">No clicks for this visit.</p>
                        </template>
                    </aside>

                    <div class="lg:col-span-3 p-6" x-show="modal.clicks.length > 0">
                        <template x-if="activeClick">
                            <div class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
                                <div>
                                    <p class="text-xs text-gray-500">IP</p>
                                    <p class="text-sm text-white" x-text="activeClick.ip || modal.visit.ip"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Browser Name</p>
                                    <p class="text-sm text-white" x-text="activeClick.browser_name || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Country</p>
                                    <p class="text-sm text-white" x-text="activeClick.country || modal.visit.country || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Browser Version</p>
                                    <p class="text-sm text-white" x-text="activeClick.browser_version || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Last Click</p>
                                    <p class="text-sm text-white" x-text="formatDateTime(activeClick.last_click_at || modal.visit.last_click_at)"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">OS</p>
                                    <p class="text-sm text-white" x-text="activeClick.os || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Threat Group</p>
                                    <p class="text-sm text-white" x-text="activeClick.threat_group || modal.visit.threat_group || 'N/A'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Paid ID</p>
                                    <p class="text-sm text-white truncate" x-text="activeClick.paid_id || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Campaign</p>
                                    <p class="text-sm text-white" x-text="activeClick.campaign || modal.visit.campaign || 'N/A'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Path</p>
                                    <p class="text-sm text-white break-words" x-text="activeClick.path || modal.visit.last_path || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Campaignr</p>
                                    <p class="text-sm text-white" x-text="activeClick.campaignr || 'N/A'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Keyword</p>
                                    <p class="text-sm text-white" x-text="activeClick.keyword || 'N/A'"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function paidMarketingDetailed() {
            const params = new URLSearchParams(window.location.search);
            return {
                filters: {
                    ip: params.get('ip') || '',
                    path: params.get('path') || '',
                    platform: params.get('platform') || '',
                    from: params.get('from') || '',
                    to: params.get('to') || '',
                },
                modal: {
                    open: false,
                    visit: null,
                    clicks: [],
                    activeIndex: 0,
                },
                get activeClick() {
                    return this.modal.clicks[this.modal.activeIndex] || null;
                },
                applyFilters() {
                    const p = new URLSearchParams(window.location.search);
                    const setOrDelete = (k, v) => v ? p.set(k, v) : p.delete(k);
                    setOrDelete('ip', this.filters.ip);
                    setOrDelete('path', this.filters.path);
                    setOrDelete('platform', this.filters.platform);
                    setOrDelete('from', this.filters.from);
                    setOrDelete('to', this.filters.to);
                    p.delete('page');
                    window.location.search = p.toString();
                },
                openClicks(visit) {
                    this.modal.visit = visit;
                    this.modal.clicks = (visit.clicks || []).slice();
                    this.modal.activeIndex = 0;
                    this.modal.open = true;
                },
                closeModal() {
                    this.modal.open = false;
                    this.modal.visit = null;
                    this.modal.clicks = [];
                    this.modal.activeIndex = 0;
                },
                formatDateTime(v) {
                    if (!v) return '—';
                    const d = new Date(v);
                    if (Number.isNaN(d.getTime())) return String(v);
                    return d.toLocaleString();
                },
            };
        }
    </script>
@endsection

