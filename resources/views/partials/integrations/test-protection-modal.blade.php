{{-- Spec Image 11: Test protection and sync --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;" x-show="testProtectionModal.open" x-cloak role="dialog" aria-modal="true" @click.self="closeTestProtectionModal()" @keydown.escape.window="if (testProtectionModal.open) closeTestProtectionModal()">
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
        <div class="pi-spec-modal-body grid gap-0 lg:grid-cols-2">
            <div class="space-y-[8px] border-b border-white/10 px-[18px] py-[14px] lg:border-b-0 lg:border-r">
                <template x-for="check in testProtectionModal.checks" :key="check.key">
                    <div class="flex items-center justify-between gap-[8px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <span x-text="check.label"></span>
                        <span :class="check.ok ? 'text-emerald-300' : (check.warn ? 'text-amber-300' : 'text-rose-300')" x-text="check.state"></span>
                    </div>
                </template>
                <p class="mt-[10px] rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[12px] text-[#ffd0b0]"
                   x-text="'Overall Protection ' + (testReadyCount === testProtectionModal.checks.length ? 'Active' : 'not active') + ' · ' + testReadyCount + ' of ' + testProtectionModal.checks.length + ' ready'"></p>
            </div>
            <aside class="space-y-[12px] px-[18px] py-[14px] text-[12px]">
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase text-white/50">Recovery steps</p>
                    <ol class="list-decimal space-y-[8px] pl-[18px] text-white/75">
                        <li>Install Google tag / publish GTM</li>
                        <li>Send consent-gated invalid-traffic test event</li>
                        <li>Create GA4 audience and attach exclusion</li>
                    </ol>
                </div>
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase text-white/50">State flow</p>
                    <p class="text-white/70">Detected → Queued → Sent → Applied / Failed</p>
                </div>
            </aside>
        </div>
        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeTestProtectionModal()">Close</button>
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="showMenuToast('Diagnostics export coming in OBS-01.', 'info')">Download diagnostics</button>
            <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white" @click="fixNextRequirement()">Fix next requirement</button>
        </footer>
    </div>
</div>
</template>
