@extends('layouts.super-admin')

@section('title', 'Traffic & Bot Logs')

@section('content')
<x-super-admin.page title="Traffic & Bot Logs">
<div class="figma-sa-traffic space-y-[14px]"
    x-data="trafficLogs({
        urls: {
            traffic: '{{ url('api/admin/traffic') }}',
            stats:   '{{ url('api/admin/traffic/stats') }}',
            block:   '{{ url('api/admin/traffic/block-ip') }}',
            blocklist: '{{ url('api/admin/traffic/blocklist') }}',
        },
        csrf: '{{ csrf_token() }}',
        domains: @js($domains->map(fn ($d) => ['id' => $d->id, 'hostname' => $d->hostname])->values()),
        initialStats: @js($stats),
    })"
    x-init="loadStats(); loadTraffic();">

    <div class="flex flex-wrap items-center gap-[10px]">
        <label class="figma-sa-traffic-date">
            <svg class="h-[16px] w-[16px] shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <input type="date" class="figma-sa-traffic-date-input" x-model="filters.date" @change="loadTraffic(1)">
        </label>
        <a href="{{ route('super-admin.domains.index') }}" class="figma-sa-traffic-add-btn ml-auto">
            <span class="figma-sa-traffic-add-icon">+</span>
            Add Tracker
        </a>
    </div>

    <div class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-4">
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">🌐</div>
            <p class="figma-sa-traffic-stat-label">Total Requests</p>
            <p class="figma-sa-traffic-stat-value" x-text="formatNumber(stats.total_requests)">{{ number_format($stats['total_requests'] ?? 0) }}</p>
            <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
        </article>
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">🛡</div>
            <p class="figma-sa-traffic-stat-label">Threat Groups</p>
            <p class="figma-sa-traffic-stat-value" x-text="formatNumber(stats.threat_groups)">{{ number_format($stats['threat_groups'] ?? 0) }}</p>
            <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
        </article>
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">⛔</div>
            <p class="figma-sa-traffic-stat-label">Blocked Traffic</p>
            <p class="figma-sa-traffic-stat-value" x-text="formatNumber(stats.blocked_traffic)">{{ number_format($stats['blocked_traffic'] ?? 0) }}</p>
            <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
        </article>
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">📋</div>
            <p class="figma-sa-traffic-stat-label">Allow Lists</p>
            <p class="figma-sa-traffic-stat-value" x-text="formatNumber(stats.allow_lists)">{{ number_format($stats['allow_lists'] ?? 0) }}</p>
            <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
        </article>
    </div>

    <template x-if="toast.message">
        <div class="figma-sa-msg" :class="toast.type === 'error' ? 'figma-sa-msg--danger' : ''">
            <span x-text="toast.message"></span>
        </div>
    </template>

    <div class="figma-sa-traffic-filters">
        <label class="figma-sa-dash-search figma-sa-traffic-search">
            <svg class="h-[18px] w-[18px] shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" placeholder="Search domains" class="figma-sa-dash-search-input" x-model="filters.search" @input="scheduleLoadTraffic()">
        </label>

        <x-super-admin.dashboard-dropdown align="left">
            <x-slot:trigger>
                <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                    <span x-text="trackerLabel"></span>
                    <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </x-slot:trigger>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setTracker('')">All Trackers</button>
            <template x-for="domain in domains" :key="domain.id">
                <button type="button" class="figma-sa-dash-dropdown-item" @click="setTracker(domain.id)" x-text="domain.hostname"></button>
            </template>
        </x-super-admin.dashboard-dropdown>

        <x-super-admin.dashboard-dropdown align="left">
            <x-slot:trigger>
                <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                    <span x-text="statusLabel"></span>
                    <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </x-slot:trigger>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setStatus('')">All Statuses</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setStatus('allow')">Allowed</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setStatus('flag')">Flagged</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setStatus('block')">Blocked</button>
        </x-super-admin.dashboard-dropdown>

        <x-super-admin.dashboard-dropdown align="left">
            <x-slot:trigger>
                <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                    <span x-text="countryLabel"></span>
                    <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </x-slot:trigger>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setCountry('')">All Countries</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setCountry('US')">United States</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setCountry('PK')">Pakistan</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setCountry('IN')">India</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="setCountry('TR')">Turkey</button>
        </x-super-admin.dashboard-dropdown>

        <label class="figma-sa-users-filter-btn !min-w-[150px]">
            <span class="sr-only">Source filter</span>
            <input type="text" placeholder="Source Filter" class="w-full bg-transparent text-[14px] text-white placeholder:text-white/55 focus:outline-none" x-model="filters.source" @input="scheduleLoadTraffic()">
        </label>

        <x-super-admin.dashboard-dropdown align="right">
            <x-slot:trigger>
                <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                    <span>Advanced Filters</span>
                    <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </x-slot:trigger>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="toggleBlockedOnly()">Blocked traffic only</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="showBlocklist = true; loadBlocklist();">Manage blocklist</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="resetFilters(); loadTraffic(1);">Reset filters</button>
        </x-super-admin.dashboard-dropdown>
    </div>

    <div class="figma-sa-subs-panel overflow-hidden rounded-[6px] bg-[#6400b3]">
        <div class="overflow-x-auto">
            <table class="figma-sa-subs-table min-w-[960px] w-full">
                <thead>
                    <tr>
                        <th class="w-[48px]"></th>
                        <th>User</th>
                        <th>Bot Score</th>
                        <th>Status</th>
                        <th>Country</th>
                        <th>Threat Group</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in traffic" :key="row.id">
                        <tr :class="index % 2 === 1 ? 'figma-sa-subs-row is-alt' : 'figma-sa-subs-row'">
                            <td><input type="checkbox" class="figma-sa-checkbox rounded" :aria-label="'Select '+row.display_name"></td>
                            <td>
                                <div class="flex items-center gap-[10px]">
                                    <span class="figma-sa-subs-avatar" x-text="row.avatar_initial"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-[16px] font-medium text-white" x-text="row.display_name"></p>
                                        <p class="truncate text-[13px] font-medium text-white/80" x-text="row.display_sub"></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <p class="text-[16px] font-medium text-white" x-text="row.bot_score"></p>
                                <p class="text-[13px] text-white/75" x-text="row.bot_score_tier"></p>
                            </td>
                            <td><span class="figma-sa-subs-status" :class="row.status_class" x-text="row.status_label"></span></td>
                            <td>
                                <span class="figma-sa-traffic-country">
                                    <span x-text="row.country_flag"></span>
                                    <span x-text="row.country || 'Unknown'"></span>
                                </span>
                            </td>
                            <td>
                                <p class="text-[14px] font-medium text-white" x-text="row.threat_group || '—'"></p>
                                <p class="text-[12px] text-white/75" x-text="row.visited_label"></p>
                            </td>
                            <td class="text-right">
                                <x-super-admin.dashboard-dropdown align="right">
                                    <x-slot:trigger>
                                        <button type="button" @click="open = !open" class="figma-sa-dash-row-menu" aria-label="Row actions">⋯</button>
                                    </x-slot:trigger>
                                    <button type="button" class="figma-sa-dash-dropdown-item block w-full text-left" @click="blockIp(row.ip, true)">Block IP</button>
                                    <button type="button" class="figma-sa-dash-dropdown-item block w-full text-left" @click="blockIp(row.ip, false)">Unblock IP</button>
                                    <button type="button" class="figma-sa-dash-dropdown-item block w-full text-left" x-show="row.url" @click="navigator.clipboard.writeText(row.url)">Copy URL</button>
                                </x-super-admin.dashboard-dropdown>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading.traffic && traffic.length === 0">
                        <td colspan="7" class="px-4 py-12 text-center text-white/70">No requests match these filters yet.</td>
                    </tr>
                    <tr x-show="loading.traffic">
                        <td colspan="7" class="px-4 py-12 text-center text-white/70">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="figma-sa-subs-pagination flex flex-wrap items-center justify-between gap-[10px] px-[24px] py-[16px]">
            <p class="text-[16px] font-medium text-white/90">
                Showing <span x-text="meta.from || 0"></span>-<span x-text="meta.to || 0"></span> of <span x-text="meta.total || 0"></span>
            </p>
            <div class="flex flex-wrap items-center gap-[10px]">
                <label class="flex items-center gap-2 text-[14px] text-white/90">
                    <span>Rows per page</span>
                    <select class="figma-sa-traffic-per-page" x-model.number="perPage" @change="loadTraffic(1)">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
                <div class="flex items-center gap-1">
                    <button type="button" class="figma-sa-traffic-page-btn" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)" aria-label="Previous page">‹</button>
                    <span class="figma-sa-traffic-page-current" x-text="meta.current_page || 1"></span>
                    <button type="button" class="figma-sa-traffic-page-btn" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)" aria-label="Next page">›</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showBlocklist" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @keydown.escape.window="showBlocklist = false">
        <div class="w-full max-w-3xl rounded-[10px] border border-white/10 bg-[#1a1a1a] p-5 shadow-2xl" @click.outside="showBlocklist = false">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-white">IP Blocklist</h2>
                <button type="button" class="figma-sa-dash-row-menu" @click="showBlocklist = false" aria-label="Close">×</button>
            </div>
            <form class="mb-4 grid gap-3 md:grid-cols-3" @submit.prevent="manualBlock()">
                <input type="text" class="figma-input" placeholder="IPv4 / IPv6" x-model="manual.ip" required>
                <input type="text" class="figma-input" placeholder="Reason (optional)" x-model="manual.reason">
                <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Block IP</button>
            </form>
            <div class="max-h-[360px] overflow-auto rounded-[6px] bg-[#6400b3]">
                <table class="figma-sa-subs-table min-w-full">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Hits</th>
                            <th>Reason</th>
                            <th class="text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in blocklist" :key="row.id">
                            <tr class="figma-sa-subs-row">
                                <td class="font-mono text-sm text-white" x-text="row.ip"></td>
                                <td class="text-white" x-text="row.hits || 0"></td>
                                <td class="text-white/80" x-text="row.intel_status || '—'"></td>
                                <td class="text-right">
                                    <button type="button" class="figma-sa-btn figma-sa-btn-outline !py-1 !px-3 text-xs" @click="blockIp(row.ip, false)">Unblock</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading.blocklist && blocklist.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-white/70">No IPs are currently blocked.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('partials.super-admin.traffic-logs-script')
</x-super-admin.page>
@endsection
