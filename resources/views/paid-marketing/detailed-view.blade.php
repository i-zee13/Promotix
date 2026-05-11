@extends('layouts.admin')

@section('title', 'Paid Advertising — Advanced View')
@section('subtitle', 'Detailed visit and click data with threat metadata')

@section('content')
    <div class="space-y-6" x-data="paidMarketingDetailed()">
        <x-ui.page-header
            title="Advanced View"
            subtitle="Detailed visit and click data with threat metadata">
            <x-slot:actions>
                <x-ui.button variant="outline" size="sm" href="{{ route('paid-marketing.dashboard') }}">Dashboard</x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('paid-marketing.detection-settings') }}">Detection Panel</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.tab-bar
            :tabs="[
                ['label' => 'Dashboard',     'value' => route('paid-marketing.dashboard')],
                ['label' => 'Advanced View', 'value' => route('paid-marketing.detailed')],
            ]"
            :active="route('paid-marketing.detailed')"
            as="link"
            param="_tab"
            base="{{ url()->current() }}"
        />

        {{-- Filters --}}
        <x-ui.card title="Filters" subtitle="Drill into specific IPs, paths, and platforms">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="brand-label mb-1.5" for="filter-ip">IP</label>
                    <input id="filter-ip" type="search" placeholder="1.2.3.4" x-model="filters.ip" class="brand-input">
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="filter-path">Path</label>
                    <input id="filter-path" type="search" placeholder="/pricing" x-model="filters.path" class="brand-input">
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="filter-platform">Platform</label>
                    <select id="filter-platform" x-model="filters.platform" class="brand-select">
                        <option value="">All platforms</option>
                        @foreach ($platforms as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="filter-from">From</label>
                    <input id="filter-from" type="date" x-model="filters.from" class="brand-input">
                </div>
                <div>
                    <label class="brand-label mb-1.5" for="filter-to">To</label>
                    <input id="filter-to" type="date" x-model="filters.to" class="brand-input">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <x-ui.button type="button" variant="primary" size="sm" @click="applyFilters()">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 018 17v-3.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                    Apply filters
                </x-ui.button>
            </div>
        </x-ui.card>

        {{-- Visits table --}}
        <x-ui.card variant="flat">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[1000px]">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Last Seen</th>
                            <th>Threat Group</th>
                            <th>Threat Type</th>
                            <th>Action</th>
                            <th>Country</th>
                            <th>Domain</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($visits as $visit)
                            <tr class="cursor-pointer" @click="openClicks(@js($visit))">
                                <td>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-white">{{ $visit->ip }}</span>
                                        <span class="text-xs text-night-400">Clicks: {{ $visit->clicks?->count() ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="text-night-200">{{ $visit->last_click_at?->format('m/d/y H:i:s') ?? '—' }}</td>
                                <td class="text-night-200">{{ $visit->threat_group ?? 'N/A' }}</td>
                                <td class="text-night-200">{{ $visit->threat_type ?? 'N/A' }}</td>
                                <td>
                                    @php
                                        $hasThreat = filled($visit->threat_group) || filled($visit->threat_type);
                                        $blocked = (bool) ($visit->ip_is_blocked ?? false);
                                        $action = $blocked ? 'Blocked' : ($hasThreat ? 'Detected' : '—');
                                        $tone = $blocked ? 'danger' : ($hasThreat ? 'warning' : 'neutral');
                                    @endphp
                                    <x-ui.pill :tone="$tone">{{ $action }}</x-ui.pill>
                                </td>
                                <td class="text-night-200">{{ $visit->country ?? '—' }}</td>
                                <td class="text-night-200">{{ $visit->domain?->hostname ?? '—' }}</td>
                                <td class="text-night-200">{{ $visit->last_path ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center text-night-300">No rows yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($visits->hasPages())
                <div class="mt-4 border-t border-night-700/60 pt-4">
                    {{ $visits->links() }}
                </div>
            @endif
        </x-ui.card>

        {{-- Click details modal --}}
        <div class="brand-modal-overlay"
             x-show="modal.open" x-cloak x-transition
             @keydown.escape.window="closeModal()" @click.self="closeModal()">
            <div class="brand-modal max-w-5xl">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="brand-modal-title">Click Details</h3>
                    <button type="button" class="rounded-lg p-1.5 text-night-300 hover:bg-night-800 hover:text-white" @click="closeModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="grid grid-cols-1 gap-0 lg:grid-cols-4">
                    <aside class="border-b border-night-700/60 p-2 lg:border-b-0 lg:border-r lg:pr-4">
                        <template x-for="(c, idx) in modal.clicks" :key="c.id ?? idx">
                            <button type="button"
                                    class="mb-2 w-full rounded-xl border border-night-700 bg-night-900 px-3 py-2 text-left transition hover:bg-night-800"
                                    :class="idx === modal.activeIndex ? 'ring-2 ring-brand-400' : ''"
                                    @click="modal.activeIndex = idx">
                                <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                                <p class="text-xs text-night-300" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            </button>
                        </template>
                        <template x-if="modal.clicks.length === 0">
                            <p class="text-sm text-night-300">No clicks for this visit.</p>
                        </template>
                    </aside>

                    <div class="p-4 lg:col-span-3 lg:pl-6" x-show="modal.clicks.length > 0">
                        <template x-if="activeClick">
                            <div class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
                                @php
                                    $rows = [
                                        ['IP',              'activeClick.ip || modal.visit.ip'],
                                        ['Browser',         "activeClick.browser_name || '—'"],
                                        ['Country',         "activeClick.country || modal.visit.country || '—'"],
                                        ['Browser version', "activeClick.browser_version || '—'"],
                                        ['Last Click',      'formatDateTime(activeClick.last_click_at || modal.visit.last_click_at)'],
                                        ['OS',              "activeClick.os || '—'"],
                                        ['Threat Group',    "activeClick.threat_group || modal.visit.threat_group || 'N/A'"],
                                        ['Paid ID',         "activeClick.paid_id || '—'"],
                                        ['Campaign',        "activeClick.campaign || modal.visit.campaign || 'N/A'"],
                                        ['Path',            "activeClick.path || modal.visit.last_path || '—'"],
                                        ['Campaignr',       "activeClick.campaignr || 'N/A'"],
                                        ['Keyword',         "activeClick.keyword || 'N/A'"],
                                    ];
                                @endphp
                                @foreach ($rows as [$label, $expr])
                                    <div>
                                        <p class="text-xs uppercase tracking-wider text-night-400">{{ $label }}</p>
                                        <p class="mt-1 break-words text-sm text-white" x-text="{{ $expr }}"></p>
                                    </div>
                                @endforeach
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
                modal: { open: false, visit: null, clicks: [], activeIndex: 0 },
                get activeClick() { return this.modal.clicks[this.modal.activeIndex] || null; },
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
