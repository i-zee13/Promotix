{{-- Global Click/IP details modal for sidebar search --}}
<div
    id="promotix-global-ip-modal"
    x-data="promotixGlobalIpModal()"
    x-cloak
    @promotix-open-ip-modal.window="openFromEvent($event)"
>
    <div
        class="figma-modal-overlay"
        x-show="open"
        x-transition
        @keydown.escape.window="if (open) close()"
        @click.self="close()"
    >
        <div class="figma-modal figma-modal--click-details">
            <header class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="figma-modal-title">Click Details</h3>
                    <p class="mt-1 text-[11px] text-white/55" x-text="subtitle"></p>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="close()" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </header>

            <div class="figma-click-modal-layout">
                <aside class="figma-click-modal-sidebar">
                    <template x-if="loading">
                        <p class="text-sm text-white/50">Loading clicks…</p>
                    </template>
                    <template x-for="(c, idx) in clicks" :key="idx">
                        <button
                            type="button"
                            class="figma-click-modal-tab"
                            :class="idx === activeIndex ? 'is-active' : ''"
                            @click="activeIndex = idx"
                        >
                            <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                            <p class="text-xs text-white/50" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                        </button>
                    </template>
                    <template x-if="!loading && clicks.length === 0">
                        <p class="text-sm text-white/50">No clicks found for this lookup.</p>
                    </template>
                </aside>

                <div class="figma-click-modal-body" x-show="activeClick">
                    <template x-if="activeClick">
                        <div class="figma-click-modal-fields">
                            <div class="figma-click-modal-compact">
                                <div class="figma-modal-field figma-modal-field--full">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">IP</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(ip || activeClick.ip)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono" x-text="ip || activeClick.ip || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Campaign</p>
                                    <p class="figma-modal-value" x-text="activeClick.campaign || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Country</p>
                                    <p class="figma-modal-value" x-text="activeClick.country || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Browser</p>
                                    <p class="figma-modal-value" x-text="activeClick.browser_name || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">OS</p>
                                    <p class="figma-modal-value" x-text="activeClick.os || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Risk / Action</p>
                                    <p class="figma-modal-value">
                                        <span x-text="activeClick.is_invalid ? 'Invalid' : 'Valid'"></span>
                                        ·
                                        <span x-text="activeClick.action_taken || '—'"></span>
                                    </p>
                                </div>
                                <div class="figma-modal-field figma-modal-field--full">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Paid ID (gclid)</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.paid_id)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono" x-text="activeClick.paid_id || '—'"></p>
                                </div>
                                <div class="figma-modal-field figma-modal-field--full">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Path / Behavior</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.path)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--long" x-text="activeClick.path || '—'"></p>
                                </div>
                                <div class="figma-modal-field figma-modal-field--full" x-show="(activeClick.detection_reasons || []).length">
                                    <p class="figma-modal-label">Reasons</p>
                                    <p class="figma-modal-value" x-text="(activeClick.detection_reasons || []).join(' · ')"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('promotixGlobalIpModal', () => ({
        open: false,
        loading: false,
        ip: '',
        subtitle: '',
        clicks: [],
        activeIndex: 0,
        get activeClick() {
            return this.clicks[this.activeIndex] || null;
        },
        formatDateTime(value) {
            if (!value) return '—';
            try {
                return new Date(value).toLocaleString();
            } catch (e) {
                return String(value);
            }
        },
        async copyText(value) {
            if (!value) return;
            try {
                await navigator.clipboard.writeText(String(value));
            } catch (e) {}
        },
        close() {
            this.open = false;
            this.loading = false;
            this.clicks = [];
            this.activeIndex = 0;
            this.ip = '';
            this.subtitle = '';
        },
        async openFromEvent(event) {
            const detail = event.detail || {};
            if (!detail.ip) return;
            await this.openForIp(detail.ip, detail.label || detail.type || 'IP investigation');
        },
        async openForIp(ip, label = 'IP investigation') {
            this.ip = ip;
            this.subtitle = label;
            this.open = true;
            this.loading = true;
            this.clicks = [];
            this.activeIndex = 0;
            try {
                const params = new URLSearchParams();
                params.set('ip', ip);
                try {
                    const range = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
                    if (range.from) params.set('from', range.from);
                    if (range.to) params.set('to', range.to);
                } catch (e) {}
                const res = await fetch(`/paid-marketing/ip-clicks?${params}`, { headers: { Accept: 'application/json' } });
                this.clicks = res.ok ? await res.json() : [];
            } catch (e) {
                this.clicks = [];
            } finally {
                this.loading = false;
            }
        },
    }));
});
</script>
