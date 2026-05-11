@extends('layouts.admin')

@section('title', 'Traffic & Bot Logs')

@section('content')
<div class="space-y-6"
    x-data="trafficLogs({
        urls: {
            traffic: '{{ url('api/admin/traffic') }}',
            stats:   '{{ url('api/admin/traffic/stats') }}',
            block:   '{{ url('api/admin/traffic/block-ip') }}',
            blocklist: '{{ url('api/admin/traffic/blocklist') }}',
        },
        csrf: '{{ csrf_token() }}',
        initialStats: @js([
            'total_requests' => $stats['total_requests'] ?? 0,
            'threat_groups' => $stats['threat_groups'] ?? 0,
            'blocked_traffic' => $stats['blocked_traffic'] ?? 0,
            'allow_lists' => $stats['allow_lists'] ?? 0,
        ]),
    })"
    x-init="loadStats(); loadTraffic();">
    <x-ui.page-header
        title="Traffic & bot logs"
        subtitle="Inspect every request hitting your domains, drill into threat groups, and block IPs in one click.">
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.kpi-card label="Total Requests" :value="number_format($stats['total_requests'] ?? 0)">
            <x-slot:icon>@include('partials.sidebar-icon', ['name' => 'globe', 'class' => 'h-4 w-4'])</x-slot:icon>
        </x-ui.kpi-card>
        <x-ui.kpi-card label="Threat Groups" :value="number_format($stats['threat_groups'] ?? 0)">
            <x-slot:icon>@include('partials.sidebar-icon', ['name' => 'shield', 'class' => 'h-4 w-4'])</x-slot:icon>
        </x-ui.kpi-card>
        <x-ui.kpi-card label="Blocked Traffic" :value="number_format($stats['blocked_traffic'] ?? 0)">
            <x-slot:icon>@include('partials.sidebar-icon', ['name' => 'shield-x', 'class' => 'h-4 w-4'])</x-slot:icon>
        </x-ui.kpi-card>
        <x-ui.kpi-card label="Allow Lists" :value="number_format($stats['allow_lists'] ?? 0)">
            <x-slot:icon>@include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-4 w-4'])</x-slot:icon>
        </x-ui.kpi-card>
    </div>

    <div class="brand-tab-bar inline-flex gap-2 rounded-full bg-night-900/60 p-1 text-xs uppercase tracking-wide">
        <button type="button"
            @click="tab = 'logs'"
            :class="tab === 'logs' ? 'bg-brand-500 text-white shadow-card' : 'text-slate-400 hover:text-white'"
            class="rounded-full px-4 py-2 font-semibold transition">Request log</button>
        <button type="button"
            @click="tab = 'blocklist'; loadBlocklist();"
            :class="tab === 'blocklist' ? 'bg-brand-500 text-white shadow-card' : 'text-slate-400 hover:text-white'"
            class="rounded-full px-4 py-2 font-semibold transition">Blocklist</button>
    </div>

    <template x-if="toast.message">
        <div class="rounded-xl2 border px-4 py-3 text-sm"
            :class="toast.type === 'error' ? 'border-rose-500/40 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
            <span x-text="toast.message"></span>
        </div>
    </template>

    {{-- LOGS TAB --}}
    <div x-show="tab === 'logs'" class="space-y-4">
        <x-ui.card class="p-4">
            <div class="grid gap-3 md:grid-cols-5">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Search</label>
                    <input type="search" class="brand-input mt-1 w-full" placeholder="IP, URL, campaign..." x-model.debounce.400ms="filters.search" @input="loadTraffic()">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Threat group</label>
                    <select class="brand-select mt-1 w-full" x-model="filters.threat_group" @change="loadTraffic()">
                        <option value="">All groups</option>
                        <option value="datacenter">Datacenter</option>
                        <option value="proxy">Proxy / VPN</option>
                        <option value="bot">Known bot</option>
                        <option value="scraper">Scraper</option>
                        <option value="manual">Manual block</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Action</label>
                    <select class="brand-select mt-1 w-full" x-model="filters.action_taken" @change="loadTraffic()">
                        <option value="">Any action</option>
                        <option value="allow">Allowed</option>
                        <option value="flag">Flagged</option>
                        <option value="block">Blocked</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Country (ISO)</label>
                    <input type="text" maxlength="3" class="brand-input mt-1 w-full uppercase" placeholder="US" x-model.debounce.400ms="filters.country" @input="loadTraffic()">
                </div>
            </div>
            <div class="mt-3 flex items-center gap-3">
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="checkbox" class="brand-checkbox" x-model="filters.blocked_only" @change="loadTraffic()">
                    Blocked only
                </label>
                <button type="button" class="brand-btn-secondary" @click="resetFilters(); loadTraffic();">Reset filters</button>
            </div>
        </x-ui.card>

        <x-ui.card class="p-0">
            <div class="overflow-x-auto">
                <table class="brand-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">IP</th>
                            <th class="px-4 py-3 text-left">URL</th>
                            <th class="px-4 py-3 text-left">Country</th>
                            <th class="px-4 py-3 text-left">Bot score</th>
                            <th class="px-4 py-3 text-left">Threat group</th>
                            <th class="px-4 py-3 text-left">Action</th>
                            <th class="px-4 py-3 text-left">Time</th>
                            <th class="px-4 py-3 text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in traffic" :key="row.id">
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-white" x-text="row.ip"></td>
                                <td class="px-4 py-3 max-w-xs truncate text-slate-300" :title="row.url" x-text="row.url"></td>
                                <td class="px-4 py-3 text-slate-300" x-text="row.country || '—'"></td>
                                <td class="px-4 py-3">
                                    <span class="brand-pill"
                                        :class="row.bot_score >= 70 ? 'brand-pill-danger' : (row.bot_score >= 40 ? 'brand-pill-warning' : 'brand-pill-success')"
                                        x-text="row.bot_score"></span>
                                </td>
                                <td class="px-4 py-3 text-slate-300" x-text="row.threat_group || '—'"></td>
                                <td class="px-4 py-3">
                                    <span class="brand-pill"
                                        :class="row.action_taken === 'block' ? 'brand-pill-danger' : (row.action_taken === 'flag' ? 'brand-pill-warning' : 'brand-pill-success')"
                                        x-text="row.action_taken || 'allow'"></span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-400" x-text="row.visited_at"></td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="brand-btn-secondary" @click="blockIp(row.ip, true)">Block IP</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading.traffic && traffic.length === 0">
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-400">No requests match these filters yet.</td>
                        </tr>
                        <tr x-show="loading.traffic">
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-400">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-night-800 px-4 py-3 text-xs text-slate-400">
                <span>Showing <span x-text="meta.from || 0"></span>–<span x-text="meta.to || 0"></span> of <span x-text="meta.total || 0"></span></span>
                <div class="flex items-center gap-2">
                    <button type="button" class="brand-btn-secondary" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">Prev</button>
                    <span x-text="`Page ${meta.current_page || 1} of ${meta.last_page || 1}`"></span>
                    <button type="button" class="brand-btn-secondary" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Next</button>
                </div>
            </div>
        </x-ui.card>
    </div>

    {{-- BLOCKLIST TAB --}}
    <div x-show="tab === 'blocklist'" class="space-y-4" style="display: none;">
        <x-ui.card class="p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Add IP to blocklist</h3>
            <form class="mt-3 grid gap-3 md:grid-cols-3" @submit.prevent="manualBlock()">
                <input type="text" class="brand-input md:col-span-1" placeholder="IPv4 / IPv6" x-model="manual.ip" required>
                <input type="text" class="brand-input md:col-span-1" placeholder="Reason (optional)" x-model="manual.reason">
                <button type="submit" class="brand-btn-primary md:col-span-1">Block IP</button>
            </form>
        </x-ui.card>

        <x-ui.card class="p-0">
            <div class="overflow-x-auto">
                <table class="brand-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">IP</th>
                            <th class="px-4 py-3 text-left">Hits</th>
                            <th class="px-4 py-3 text-left">Reason</th>
                            <th class="px-4 py-3 text-left">Last seen</th>
                            <th class="px-4 py-3 text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in blocklist" :key="row.id">
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-white" x-text="row.ip"></td>
                                <td class="px-4 py-3 text-slate-300" x-text="row.hits || 0"></td>
                                <td class="px-4 py-3 text-slate-300" x-text="row.intel_status || '—'"></td>
                                <td class="px-4 py-3 text-xs text-slate-400" x-text="row.last_seen_at || '—'"></td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="brand-btn-secondary" @click="blockIp(row.ip, false)">Unblock</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading.blocklist && blocklist.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No IPs are currently blocked.</td>
                        </tr>
                        <tr x-show="loading.blocklist">
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</div>

<script>
function trafficLogs(initial) {
    return {
        urls: initial.urls,
        csrf: initial.csrf,
        tab: 'logs',
        toast: { message: '', type: 'success' },
        loading: { traffic: false, blocklist: false },
        filters: {
            search: '',
            threat_group: '',
            action_taken: '',
            country: '',
            blocked_only: false,
        },
        manual: { ip: '', reason: '' },
        traffic: [],
        meta: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 },
        blocklist: [],
        notify(message, type = 'success') {
            this.toast = { message, type };
            setTimeout(() => (this.toast.message = ''), 4000);
        },
        async request(url, method = 'GET', body = null) {
            const opts = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            };
            if (body) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
            const res = await fetch(url, opts);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        },
        resetFilters() {
            this.filters = { search: '', threat_group: '', action_taken: '', country: '', blocked_only: false };
        },
        async loadStats() {
            try {
                const data = await this.request(this.urls.stats);
                // Update KPI cards inline if needed (server-rendered first paint already shown).
                this.statsLive = data;
            } catch (e) { /* silent */ }
        },
        async loadTraffic(page = 1) {
            this.loading.traffic = true;
            try {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (v === '' || v === false || v === null || v === undefined) return;
                    params.append(k, v === true ? '1' : v);
                });
                params.append('page', page);
                const data = await this.request(`${this.urls.traffic}?${params.toString()}`);
                this.traffic = data.data || [];
                this.meta = {
                    current_page: data.current_page || 1,
                    last_page: data.last_page || 1,
                    total: data.total || 0,
                    from: data.from || 0,
                    to: data.to || 0,
                };
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.traffic = false;
            }
        },
        goToPage(page) {
            if (page < 1 || page > (this.meta.last_page || 1)) return;
            this.loadTraffic(page);
        },
        async loadBlocklist() {
            this.loading.blocklist = true;
            try {
                const data = await this.request(this.urls.blocklist);
                this.blocklist = data.data || [];
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.blocklist = false;
            }
        },
        async blockIp(ip, blocked) {
            try {
                const data = await this.request(this.urls.block, 'POST', { ip, blocked, reason: blocked ? 'manual_block_from_logs' : 'manual_unblock' });
                this.notify(data.message || (blocked ? 'IP blocked.' : 'IP unblocked.'));
                this.loadTraffic(this.meta.current_page);
                if (this.tab === 'blocklist') this.loadBlocklist();
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
        async manualBlock() {
            if (!this.manual.ip) return;
            try {
                await this.request(this.urls.block, 'POST', { ip: this.manual.ip, blocked: true, reason: this.manual.reason || 'manual_block' });
                this.manual = { ip: '', reason: '' };
                this.notify('IP added to blocklist.');
                this.loadBlocklist();
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
    };
}
</script>
@endsection
