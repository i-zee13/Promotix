@extends('layouts.admin')

@section('title', 'Bot Protection — Advanced View')

@section('content')
    <div class="space-y-6" x-data="botProtectionAdvanced()" x-init="init()">
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dark-border bg-dark-card p-4">
            <div class="flex flex-wrap items-center gap-2">
                <select x-model="filters.domain_id" @change="reload(true)"
                        class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                    <option value="">All domains</option>
                    @foreach ($domains as $d)
                        <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                    @endforeach
                </select>
                <input type="search" placeholder="IP search" x-model="filters.ip" @keydown.enter="reload(true)"
                       class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white placeholder-gray-500">
                <input type="text" placeholder="Country (2-letter)" maxlength="2" x-model="filters.country" @keydown.enter="reload(true)"
                       class="w-32 rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm uppercase text-white placeholder-gray-500">
                <select x-model="filters.action" @change="reload(true)"
                        class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                    <option value="">All actions</option>
                    <option value="allow">Allow</option>
                    <option value="flag">Flag</option>
                    <option value="block">Block</option>
                </select>
                <select x-model="filters.threat_group" @change="reload(true)"
                        class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                    <option value="">All threat groups</option>
                    <option value="data_center">Data center</option>
                    <option value="vpn">VPN</option>
                    <option value="malicious">Malicious</option>
                    <option value="abnormal_rate_limit">Abnormal rate limit</option>
                    <option value="out_of_geo">Out of geo</option>
                </select>
                <label class="inline-flex items-center gap-2 text-xs text-gray-300">
                    <input type="checkbox" x-model="filters.only_invalid" @change="reload(true)"> Only invalid
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-gray-300">
                    <input type="checkbox" x-model="filters.only_paid" @change="reload(true)"> Only paid
                </label>
                <input type="date" x-model="filters.from" @change="reload(true)"
                       class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                <input type="date" x-model="filters.to" @change="reload(true)"
                       class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="reload(true)"
                        class="rounded-xl bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                    Apply
                </button>
                <a :href="csvHref()"
                   class="rounded-xl border border-dark-border bg-dark px-4 py-2 text-sm text-gray-200 hover:bg-dark-border">
                    Export CSV
                </a>
            </div>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-sm">
                    <thead>
                    <tr class="border-b border-dark-border bg-dark text-left text-xs uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Visited</th>
                        <th class="px-4 py-3">Domain</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Country</th>
                        <th class="px-4 py-3">Browser</th>
                        <th class="px-4 py-3">OS</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Threat</th>
                        <th class="px-4 py-3">Score</th>
                        <th class="px-4 py-3">URL</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border text-white">
                    <template x-for="row in rows" :key="row.id">
                        <tr class="hover:bg-dark/50">
                            <td class="px-4 py-2 text-gray-300" x-text="row.visited_at"></td>
                            <td class="px-4 py-2 text-gray-300" x-text="row.hostname"></td>
                            <td class="px-4 py-2 font-medium text-white" x-text="row.ip"></td>
                            <td class="px-4 py-2 text-gray-300" x-text="row.country || '—'"></td>
                            <td class="px-4 py-2 text-gray-300" x-text="row.browser || '—'"></td>
                            <td class="px-4 py-2 text-gray-300" x-text="row.os || '—'"></td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                      :class="actionClass(row.action_taken)" x-text="row.action_taken"></span>
                            </td>
                            <td class="px-4 py-2 text-gray-300" x-text="row.threat_group || '—'"></td>
                            <td class="px-4 py-2 text-gray-300" x-text="row.threat_score"></td>
                            <td class="px-4 py-2 text-gray-300 truncate max-w-[280px]" :title="row.url" x-text="row.url || '—'"></td>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0">
                        <td colspan="10" class="px-4 py-8 text-center text-gray-400">No matching visits.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-dark-border px-4 py-3 text-xs text-gray-400">
                <span x-text="`${rows.length ? ((meta.page - 1) * meta.per_page + 1) : 0}–${Math.min(meta.total, meta.page * meta.per_page)} of ${meta.total}`"></span>
                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-md border border-dark-border px-3 py-1 disabled:opacity-50"
                            :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">Prev</button>
                    <button type="button" class="rounded-md border border-dark-border px-3 py-1 disabled:opacity-50"
                            :disabled="meta.page * meta.per_page >= meta.total" @click="changePage(meta.page + 1)">Next</button>
                </div>
            </div>
        </section>
    </div>

    <script>
        function botProtectionAdvanced() {
            return {
                filters: {
                    domain_id: '',
                    ip: '',
                    country: '',
                    action: '',
                    threat_group: '',
                    only_invalid: false,
                    only_paid: false,
                    from: '',
                    to: '',
                },
                rows: [],
                meta: { total: 0, page: 1, per_page: 25 },
                actionClass(action) {
                    if (action === 'block') return 'bg-red-500/20 text-red-300';
                    if (action === 'flag') return 'bg-amber-500/20 text-amber-300';
                    return 'bg-emerald-500/20 text-emerald-300';
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
