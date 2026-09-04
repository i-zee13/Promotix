@props([
    'rows' => [],
    'scrollable' => false,
    'showViewAll' => false,
    'fullPage' => false,
])

<div
    @class([
        'figma-sa-subs-panel figma-sa-cross-domain-panel',
        'is-fullpage' => $fullPage,
        'is-scrollable' => $scrollable && ! $fullPage,
    ])
    x-data="crossDomainTable({ rows: {{ \Illuminate\Support\Js::from($rows) }} })"
>
    <div class="figma-sa-cross-domain-head">
        <div>
            <h2 class="figma-sa-cross-domain-title">Cross-domain intelligence</h2>
            <p class="figma-sa-cross-domain-subtitle">Evidence scores only — never auto-block from this panel.</p>
        </div>
        <div class="figma-sa-cross-domain-tools">
            <label class="figma-sa-subs-search figma-sa-cross-domain-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model="q" @input="page = 1" placeholder="Search IP or domain" autocomplete="off">
            </label>
            @if ($showViewAll)
                <a href="{{ route('super-admin.traffic.cross-domain') }}" class="figma-sa-cross-domain-view-all">View all</a>
            @endif
        </div>
    </div>

    <div class="figma-sa-subs-table-scroll figma-sa-cross-domain-scroll">
        <table class="figma-sa-subs-table figma-sa-cross-domain-table">
            <thead>
                <tr>
                    <th>IP</th>
                    <th>Domains</th>
                    <th>Domain similarity</th>
                    <th>Hits</th>
                    <th>Evidence</th>
                    <th>Bot max</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in paged" :key="row.ip">
                    <tr class="figma-sa-subs-row">
                        <td><span class="figma-sa-subs-plan-tier" x-text="row.ip"></span></td>
                        <td>
                            <span class="figma-sa-subs-billing" x-text="(row.domain_count || 0) + ' domains'"></span>
                            <span class="figma-sa-subs-date" x-text="(row.domains || []).join(', ')"></span>
                        </td>
                        <td>
                            <span class="figma-sa-subs-plan-detail" x-show="(row.domain_similarity_label || '—') === '—'">—</span>
                            <div x-show="(row.domain_similarity_label || '—') !== '—'">
                                <span
                                    class="figma-sa-cross-domain-sim"
                                    :class="'figma-sa-cross-domain-sim--' + String(row.domain_similarity_label || '').toLowerCase()"
                                    x-text="row.domain_similarity + '% · ' + row.domain_similarity_label"
                                ></span>
                                <span
                                    class="figma-sa-subs-date"
                                    x-show="row.domain_similarity_pair && row.domain_similarity_pair.length"
                                    x-text="row.domain_similarity_pair ? (row.domain_similarity_pair[0] + ' ↔ ' + row.domain_similarity_pair[1]) : ''"
                                ></span>
                            </div>
                        </td>
                        <td x-text="Number(row.hits || 0).toLocaleString()"></td>
                        <td>
                            <span class="figma-sa-subs-plan-tier" x-text="row.evidence_score"></span>
                            <span class="figma-sa-subs-plan-detail">no auto-block</span>
                        </td>
                        <td x-text="row.max_bot_score"></td>
                    </tr>
                </template>
                <tr x-show="filtered.length === 0">
                    <td colspan="6" class="figma-sa-subs-empty">No multi-domain IPs in the last 30 days.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="figma-sa-subs-pagination" x-show="filtered.length > 0">
        <p class="figma-sa-subs-pagination-meta" x-text="rangeLabel"></p>
        <div class="figma-sa-subs-pagination-controls">
            <label class="sr-only" for="cross-domain-per-page">Rows per page</label>
            <select id="cross-domain-per-page" class="figma-sa-subs-perpage-select" x-model.number="perPage" @change="page = 1">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <div class="figma-sa-subs-page-btns">
                <button type="button" class="figma-sa-subs-page-btn" :class="{ 'figma-sa-subs-page-btn--disabled': page <= 1 }" :disabled="page <= 1" @click="page = Math.max(1, page - 1)" aria-label="Previous page">&lt;</button>
                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--current" x-text="page"></span>
                <button type="button" class="figma-sa-subs-page-btn" :class="{ 'figma-sa-subs-page-btn--disabled': page >= pageCount }" :disabled="page >= pageCount" @click="page = Math.min(pageCount, page + 1)" aria-label="Next page">&gt;</button>
            </div>
        </div>
    </div>
</div>

@once
<script>
function crossDomainTable(config) {
    return {
        rows: Array.isArray(config.rows) ? config.rows : [],
        q: '',
        page: 1,
        perPage: 25,
        get filtered() {
            const q = String(this.q || '').trim().toLowerCase();
            if (!q) return this.rows;
            return this.rows.filter((row) => {
                const domains = Array.isArray(row.domains) ? row.domains.join(' ') : '';
                return String(row.ip || '').toLowerCase().includes(q)
                    || domains.toLowerCase().includes(q)
                    || String(row.domain_similarity_label || '').toLowerCase().includes(q);
            });
        },
        get pageCount() {
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
        },
        get paged() {
            const page = Math.min(this.page, this.pageCount);
            const start = (page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },
        get rangeLabel() {
            const total = this.filtered.length;
            if (!total) return 'Showing 0 of 0';
            const start = (Math.min(this.page, this.pageCount) - 1) * this.perPage + 1;
            const end = Math.min(total, start + this.perPage - 1);
            return `Showing ${start} to ${end} of ${total} results`;
        },
    };
}
</script>
@endonce
