@extends('layouts.admin')

@section('title', 'Bot Protection — Advanced View')
@section('subtitle', 'Per-visit detection log with full filter controls')

@section('content')
    <div class="space-y-6" x-data="botProtectionAdvanced()" x-init="init()">
        <x-ui.page-header title="Advanced View" subtitle="Per-visit detection log with full filter controls">
            <x-slot:actions>
                <x-ui.button variant="outline" size="sm" href="{{ route('bot-protection.dashboard') }}">Dashboard</x-ui.button>
                <a :href="csvHref()" class="brand-btn-soft">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v-1a4 4 0 014-4h0a4 4 0 014 4v1"/></svg>
                    Export CSV
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.tab-bar
            :tabs="[
                ['label' => 'Dashboard',     'value' => route('bot-protection.dashboard')],
                ['label' => 'Advanced View', 'value' => route('bot-protection.advanced')],
            ]"
            :active="route('bot-protection.advanced')"
            as="link"
            param="_tab"
            base="{{ url()->current() }}"
        />

        {{-- Filters --}}
        <x-ui.card title="Filters" subtitle="Refine the visit log by IP, country, action, and threat group">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="brand-label mb-1.5">Domain</label>
                    <select x-model="filters.domain_id" @change="reload(true)" class="brand-select">
                        <option value="">All domains</option>
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="brand-label mb-1.5">IP search</label>
                    <input type="search" placeholder="1.2.3.4" x-model="filters.ip" @keydown.enter="reload(true)" class="brand-input">
                </div>
                <div>
                    <label class="brand-label mb-1.5">Country (2-letter)</label>
                    <input type="text" placeholder="US" maxlength="2" x-model="filters.country" @keydown.enter="reload(true)" class="brand-input uppercase">
                </div>
                <div>
                    <label class="brand-label mb-1.5">Action</label>
                    <select x-model="filters.action" @change="reload(true)" class="brand-select">
                        <option value="">All actions</option>
                        <option value="allow">Allow</option>
                        <option value="flag">Flag</option>
                        <option value="block">Block</option>
                    </select>
                </div>
                <div>
                    <label class="brand-label mb-1.5">Threat group</label>
                    <select x-model="filters.threat_group" @change="reload(true)" class="brand-select">
                        <option value="">All threat groups</option>
                        <option value="data_center">Data center</option>
                        <option value="vpn">VPN</option>
                        <option value="malicious">Malicious</option>
                        <option value="abnormal_rate_limit">Abnormal rate limit</option>
                        <option value="out_of_geo">Out of geo</option>
                    </select>
                </div>
                <div>
                    <label class="brand-label mb-1.5">From</label>
                    <input type="date" x-model="filters.from" @change="reload(true)" class="brand-input">
                </div>
                <div>
                    <label class="brand-label mb-1.5">To</label>
                    <input type="date" x-model="filters.to" @change="reload(true)" class="brand-input">
                </div>
                <div class="flex flex-col justify-end gap-2 pt-1">
                    <label class="inline-flex items-center gap-2 text-sm text-night-100">
                        <input type="checkbox" x-model="filters.only_invalid" @change="reload(true)"
                               class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                        Only invalid
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-night-100">
                        <input type="checkbox" x-model="filters.only_paid" @change="reload(true)"
                               class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                        Only paid
                    </label>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <x-ui.button type="button" variant="primary" size="sm" @click="reload(true)">Apply filters</x-ui.button>
            </div>
        </x-ui.card>

        {{-- Visits table --}}
        <x-ui.card variant="flat">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[1100px]">
                    <thead>
                        <tr>
                            <th>Visited</th>
                            <th>Domain</th>
                            <th>IP</th>
                            <th>Country</th>
                            <th>Browser</th>
                            <th>OS</th>
                            <th>Action</th>
                            <th>Threat</th>
                            <th>Score</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <td class="text-night-200" x-text="row.visited_at"></td>
                                <td class="text-night-200" x-text="row.hostname"></td>
                                <td class="font-medium" x-text="row.ip"></td>
                                <td class="text-night-200" x-text="row.country || '—'"></td>
                                <td class="text-night-200" x-text="row.browser || '—'"></td>
                                <td class="text-night-200" x-text="row.os || '—'"></td>
                                <td>
                                    <span class="brand-pill" :class="actionPillClass(row.action_taken)" x-text="row.action_taken"></span>
                                </td>
                                <td class="text-night-200" x-text="row.threat_group || '—'"></td>
                                <td class="text-night-200" x-text="row.threat_score"></td>
                                <td class="max-w-[280px] truncate text-night-200" :title="row.url" x-text="row.url || '—'"></td>
                            </tr>
                        </template>
                        <tr x-show="rows.length === 0">
                            <td colspan="10" class="py-10 text-center text-night-300">No matching visits.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-night-700/60 pt-4 text-xs text-night-300">
                <span x-text="`${rows.length ? ((meta.page - 1) * meta.per_page + 1) : 0}–${Math.min(meta.total, meta.page * meta.per_page)} of ${meta.total}`"></span>
                <div class="flex items-center gap-2">
                    <button type="button" class="brand-btn-outline px-3 py-1 text-xs disabled:opacity-50"
                            :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">Prev</button>
                    <button type="button" class="brand-btn-outline px-3 py-1 text-xs disabled:opacity-50"
                            :disabled="meta.page * meta.per_page >= meta.total" @click="changePage(meta.page + 1)">Next</button>
                </div>
            </div>
        </x-ui.card>
    </div>

    <script>
        function botProtectionAdvanced() {
            return {
                filters: {
                    domain_id: '', ip: '', country: '', action: '', threat_group: '',
                    only_invalid: false, only_paid: false, from: '', to: '',
                },
                rows: [],
                meta: { total: 0, page: 1, per_page: 25 },
                actionPillClass(action) {
                    if (action === 'block') return 'brand-pill-danger';
                    if (action === 'flag')  return 'brand-pill-warning';
                    return 'brand-pill-success';
                },
                qs(extra = {}) {
                    const p = new URLSearchParams();
                    Object.entries({ ...this.filters, ...extra }).forEach(([k, v]) => {
                        if (v === false || v === '' || v === null || v === undefined) return;
                        p.set(k, v === true ? 1 : v);
                    });
                    return p.toString();
                },
                csvHref() { return `/bot-protection/export.csv?${this.qs()}`; },
                async init() {
                    const today = new Date();
                    const start = new Date(today.getTime() - 6 * 86400000);
                    this.filters.from = start.toISOString().slice(0, 10);
                    this.filters.to = today.toISOString().slice(0, 10);
                    await this.reload(true);
                },
                async reload(resetPage = false) {
                    if (resetPage) this.meta.page = 1;
                    const qs = this.qs({ page: this.meta.page, per_page: this.meta.per_page });
                    const res = await fetch(`/bot-protection/visits?${qs}`).then(r => r.json());
                    this.rows = res.data || [];
                    this.meta = { ...this.meta, ...(res.meta || {}) };
                },
                async changePage(p) { this.meta.page = Math.max(1, p); await this.reload(false); },
            };
        }
    </script>
@endsection
