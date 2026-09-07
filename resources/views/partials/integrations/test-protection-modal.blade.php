{{-- Spec Image 11: Test protection and sync --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;" x-show="testProtectionModal.open" x-cloak role="dialog" aria-modal="true" @click.self="closeTestProtectionModal()" @keydown.escape.window="if (testProtectionModal.open) closeTestProtectionModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeTestProtectionModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[980px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[10px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Test protection and sync</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Readiness is eight checks — not one Connected badge.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeTestProtectionModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="flex shrink-0 flex-wrap gap-[4px] border-b border-white/10 px-[16px] pt-[8px]">
            <template x-for="tab in testProtectionModal.tabs" :key="tab.id">
                <button type="button" class="rounded-t-[8px] px-[14px] py-[10px] text-[12px] font-semibold"
                    :class="testProtectionModal.tab === tab.id ? 'bg-[var(--brand-primary)] text-white' : 'text-white/55 hover:text-white'"
                    @click="testProtectionModal.tab = tab.id" x-text="tab.label"></button>
            </template>
        </div>
        <div class="shrink-0 flex flex-wrap gap-[14px] border-b border-white/10 px-[18px] py-[10px] text-[11px] text-white/70">
            <span>Account: <strong class="text-white" x-text="googleAdsSummary.customer_id || '—'"></strong></span>
            <span>Domain: <strong class="text-white" x-text="activeDomainLabel"></strong></span>
            <span>Mode: <strong class="text-[#ffd0b0]">Safe synthetic mode</strong></span>
        </div>
        <div class="pi-spec-modal-body">
            <div class="grid gap-0 lg:grid-cols-2" x-show="testProtectionModal.tab === 'tests'">
                <div class="space-y-[8px] border-b border-white/10 px-[18px] py-[14px] lg:border-b-0 lg:border-r">
                    <template x-for="check in testProtectionModal.checks" :key="check.key">
                        <div class="flex items-center justify-between gap-[8px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                            <span class="inline-flex items-center gap-[8px]">
                                <span class="inline-flex h-[16px] w-[16px] items-center justify-center rounded-full text-[10px] font-bold"
                                      :class="check.ok ? 'bg-emerald-500/25 text-emerald-300' : (check.warn ? 'bg-amber-500/25 text-amber-300' : 'bg-rose-500/25 text-rose-300')"
                                      x-text="check.ok ? '✓' : '!'"></span>
                                <span x-text="check.label"></span>
                            </span>
                            <span :class="check.ok ? 'text-emerald-300' : (check.warn ? 'text-amber-300' : 'text-rose-300')" x-text="check.state"></span>
                        </div>
                    </template>
                    <p class="mt-[10px] rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[12px] text-[#ffd0b0]"
                       x-text="'Overall Protection ' + (testReadyCount === testProtectionModal.checks.length && testProtectionModal.checks.length ? 'Active' : 'not active') + ' · ' + testReadyCount + ' of ' + testProtectionModal.checks.length + ' ready'"></p>
                </div>
                <aside class="space-y-[14px] px-[18px] py-[14px] text-[12px]">
                    <div>
                        <p class="mb-[8px] text-[11px] font-semibold uppercase text-white/50">Recovery steps</p>
                        <ol class="space-y-[10px] text-white/75">
                            <li><strong class="text-white">Install Google tag</strong> — Add the Google tag and verify it loads.</li>
                            <li><strong class="text-white">Send test invalid event</strong> — Trigger consent-gated <code class="text-[#ffd0b0]">clickronix_invalid_traffic</code>.</li>
                            <li><strong class="text-white">Create audience</strong> — Create/select invalid-traffic audience, then apply exclusion.</li>
                        </ol>
                    </div>
                    <div>
                        <p class="mb-[8px] text-[11px] font-semibold uppercase text-white/50">State flow</p>
                        <div class="grid grid-cols-2 gap-[8px] text-[11px] sm:grid-cols-4">
                            <div class="rounded-[8px] border border-emerald-500/30 bg-emerald-500/10 px-[8px] py-[8px] text-center text-emerald-200">Detected</div>
                            <div class="rounded-[8px] border border-amber-500/30 bg-amber-500/10 px-[8px] py-[8px] text-center text-amber-200">Queued</div>
                            <div class="rounded-[8px] border border-amber-500/30 bg-amber-500/10 px-[8px] py-[8px] text-center text-amber-200">Sent</div>
                            <div class="rounded-[8px] border border-white/15 bg-white/5 px-[8px] py-[8px] text-center text-white/70">Applied / Failed</div>
                        </div>
                    </div>
                    <p class="text-[11px] text-white/45">Protection Active = Account connected + script active + selected modules configured + permissions pass + latest reconciliation confirms actual state.</p>
                </aside>
            </div>
            <div class="space-y-[14px] px-[18px] py-[16px]" x-show="testProtectionModal.tab === 'sync'" x-cloak>
                <p class="text-[13px] text-white/75">Sync preview — import/read campaign scope. This does not claim Protection Active.</p>
                <dl class="grid gap-[8px] text-[12px] sm:grid-cols-2">
                    <div class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px]"><dt class="text-white/50">Account</dt><dd class="mt-[4px] font-mono text-white" x-text="googleAdsSummary.customer_id || '—'"></dd></div>
                    <div class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px]"><dt class="text-white/50">Last sync</dt><dd class="mt-[4px] text-white" x-text="relativeAgo(connectionHealth.last_sync_at)"></dd></div>
                    <div class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px]"><dt class="text-white/50">Status</dt><dd class="mt-[4px] text-white" x-text="connectionHealth.last_sync_status || '—'"></dd></div>
                    <div class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px]"><dt class="text-white/50">Allowed sync states</dt><dd class="mt-[4px] text-white">Preview · Conflict · Running · Applied · Partial · Failed</dd></div>
                </dl>
                <form method="POST" :action="googleAdsSummary.sync_url || '#'" x-show="googleAdsSummary.sync_url">
                    @csrf
                    <button type="submit" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white">Run Campaign Sync</button>
                </form>
                <p class="text-[11px] text-white/45" x-show="!googleAdsSummary.sync_url">Connect Google Ads first to enable sync.</p>
            </div>
            <div class="space-y-[10px] px-[18px] py-[16px]" x-show="testProtectionModal.tab === 'log'" x-cloak>
                <template x-for="log in (syncLogs || []).slice(0, 12)" :key="log.id">
                    <div class="flex items-start justify-between gap-[10px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-white" x-text="log.action || 'sync'"></p>
                            <p class="mt-[2px] truncate text-white/55" x-text="log.message || log.domain || '—'"></p>
                        </div>
                        <span class="shrink-0 text-[11px]" :class="log.status === 'ok' || log.status === 'success' ? 'text-emerald-300' : 'text-amber-300'" x-text="log.status || '—'"></span>
                    </div>
                </template>
                <p class="text-[12px] text-white/45" x-show="!(syncLogs || []).length">No activity yet.</p>
            </div>
        </div>
        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeTestProtectionModal()">Close</button>
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="showMenuToast('Diagnostics export queued (secrets redacted).', 'info')">Download diagnostics</button>
            <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white" @click="fixNextRequirement()">Fix next requirement</button>
        </footer>
    </div>
</div>
</template>
