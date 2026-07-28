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
    x-init="loadStats(); loadTraffic();"
    @traffic-block-ip.window="blockIp($event.detail.ip, $event.detail.blocked)">

    <div class="flex flex-wrap items-center gap-[10px]">
        <label class="figma-sa-traffic-date">
            <input type="date" class="figma-sa-traffic-date-input" x-model="filters.date" @change="loadTraffic(1)">
        </label>
        <a href="{{ route('super-admin.domains.index') }}" class="figma-sa-traffic-add-btn ml-auto">
            <span class="figma-sa-traffic-add-icon">+</span>
            Add Tracker
        </a>
    </div>

    <div class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-4">
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                <x-stat-icon name="globe" class="h-[22px] w-[22px] text-white/95" />
            </div>
            <p class="figma-sa-traffic-stat-label">Total Requests</p>
            <p class="figma-sa-traffic-stat-value" x-text="formatNumber(stats.total_requests)">{{ number_format($stats['total_requests'] ?? 0) }}</p>
            <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
        </article>
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                <x-stat-icon name="shield" class="h-[22px] w-[22px] text-white/95" />
            </div>
            <p class="figma-sa-traffic-stat-label">Threat Groups</p>
            <p class="figma-sa-traffic-stat-value" x-text="formatNumber(stats.threat_groups)">{{ number_format($stats['threat_groups'] ?? 0) }}</p>
            <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
        </article>
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                <x-stat-icon name="ban" class="h-[22px] w-[22px] text-white/95" />
            </div>
            <p class="figma-sa-traffic-stat-label">Blocked Traffic</p>
            <p class="figma-sa-traffic-stat-value" x-text="formatNumber(stats.blocked_traffic)">{{ number_format($stats['blocked_traffic'] ?? 0) }}</p>
            <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
        </article>
        <article class="figma-sa-traffic-stat">
            <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                <x-stat-icon name="clipboard-list" class="h-[22px] w-[22px] text-white/95" />
            </div>
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
                <button type="button" class="figma-sa-users-filter-btn">
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
                <button type="button" class="figma-sa-users-filter-btn">
                    <span x-text="statusLabel"></span>
                    <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </x-slot:trigger>
            <button type="button" class="figma-sa-users-filter-option" @click="setStatus('')">All Statuses</button>
            <button type="button" class="figma-sa-users-filter-option" @click="setStatus('allow')">Allowed</button>
            <button type="button" class="figma-sa-users-filter-option" @click="setStatus('flag')">Flagged</button>
            <button type="button" class="figma-sa-users-filter-option" @click="setStatus('block')">Blocked</button>
        </x-super-admin.dashboard-dropdown>

        <x-super-admin.dashboard-dropdown align="left">
            <x-slot:trigger>
                <button type="button" class="figma-sa-users-filter-btn">
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
            <input type="text" placeholder="Source Filter" class="figma-sa-traffic-source-input" x-model="filters.source" @input="scheduleLoadTraffic()">
        </label>

        <x-super-admin.dashboard-dropdown align="right">
            <x-slot:trigger>
                <button type="button" class="figma-sa-users-filter-btn">
                    <span>Advanced Filters</span>
                    <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </x-slot:trigger>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="toggleBlockedOnly()">Blocked traffic only</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="showBlocklist = true; loadBlocklist();">Manage blocklist</button>
            <button type="button" class="figma-sa-dash-dropdown-item" @click="resetFilters(); loadTraffic(1);">Reset filters</button>
        </x-super-admin.dashboard-dropdown>
    </div>

    <div class="figma-sa-subs-panel">
        <div class="figma-sa-subs-table-scroll">
            <table class="figma-sa-subs-table">
                <thead>
                    <tr>
                        <th class="figma-sa-subs-th-check"><input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select all"></th>
                        <th>User</th>
                        <th>Bot Score</th>
                        <th>Status</th>
                        <th>Country</th>
                        <th>Threat Group</th>
                        <th class="figma-sa-subs-th-action">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in traffic" :key="row.id">
                        <tr class="figma-sa-subs-row">
                            <td class="figma-sa-subs-td-check"><input type="checkbox" class="figma-sa-subs-checkbox" :aria-label="'Select '+row.display_name"></td>
                            <td>
                                <div class="figma-sa-subs-user">
                                    <span class="figma-sa-subs-avatar" aria-hidden="true"></span>
                                    <span class="figma-sa-subs-user-text">
                                        <span class="figma-sa-subs-user-name" x-text="row.display_name"></span>
                                        <span class="figma-sa-subs-user-email" x-text="row.display_sub"></span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="figma-sa-subs-plan-tier" x-text="row.bot_score"></span>
                                <span class="figma-sa-subs-plan-detail" x-text="row.bot_score_tier"></span>
                            </td>
                            <td><span class="figma-sa-subs-status-pill" :class="row.status_class" x-text="row.status_label"></span></td>
                            <td><span class="figma-sa-traffic-country" x-text="row.country || 'Unknown'"></span></td>
                            <td>
                                <span class="figma-sa-subs-billing" x-text="row.threat_group || '—'"></span>
                                <span class="figma-sa-subs-date" x-text="row.visited_label"></span>
                            </td>
                            <td class="figma-sa-subs-td-action">
                                <x-super-admin.dashboard-dropdown align="right">
                                    <x-slot:trigger>
                                        <button type="button" class="figma-sa-subs-kebab" aria-label="Row actions">
                                            <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                        </button>
                                    </x-slot:trigger>
                                    <button type="button" class="figma-sa-users-action-item w-full text-left" @click="$dispatch('traffic-block-ip', { ip: row.ip, blocked: true })">Block IP</button>
                                    <button type="button" class="figma-sa-users-action-item w-full text-left" @click="$dispatch('traffic-block-ip', { ip: row.ip, blocked: false })">Unblock IP</button>
                                    <button type="button" class="figma-sa-users-action-item w-full text-left" x-show="row.url" @click="navigator.clipboard.writeText(row.url)">Copy URL</button>
                                </x-super-admin.dashboard-dropdown>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading.traffic && traffic.length === 0">
                        <td colspan="7" class="figma-sa-subs-empty">No requests match these filters yet.</td>
                    </tr>
                    <tr x-show="loading.traffic">
                        <td colspan="7" class="figma-sa-subs-empty">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="figma-sa-subs-pagination">
            <p class="figma-sa-subs-pagination-meta">
                Showing <span x-text="meta.from || 0"></span>–<span x-text="meta.to || 0"></span> of <span x-text="meta.total || 0"></span>
            </p>
            <div class="figma-sa-subs-pagination-controls">
                <label class="sr-only" for="traffic-per-page">Rows per page</label>
                <select id="traffic-per-page" class="figma-sa-subs-perpage-select" x-model.number="perPage" @change="loadTraffic(1)">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <div class="figma-sa-subs-page-btns">
                    <button type="button" class="figma-sa-subs-page-btn" :class="{ 'figma-sa-subs-page-btn--disabled': meta.current_page <= 1 }" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)" aria-label="Previous page">&lt;</button>
                    <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--current" x-text="meta.current_page || 1"></span>
                    <button type="button" class="figma-sa-subs-page-btn" :class="{ 'figma-sa-subs-page-btn--disabled': meta.current_page >= meta.last_page }" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)" aria-label="Next page">&gt;</button>
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
