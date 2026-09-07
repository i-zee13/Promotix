{{-- Spec Image 10: Google Ads tracking template --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;" x-show="trackingTemplateModal.open" x-cloak role="dialog" aria-modal="true" @click.self="closeTrackingTemplateModal()" @keydown.escape.window="if (trackingTemplateModal.open) closeTrackingTemplateModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeTrackingTemplateModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[980px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[10px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Google Ads tracking template</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Draft/test first. Apply is certification-gated — OAuth is not certification.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeTrackingTemplateModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="flex shrink-0 flex-wrap gap-[4px] border-b border-white/10 px-[16px] pt-[8px]">
            <template x-for="tab in trackingTemplateModal.tabs" :key="tab.id">
                <button type="button" class="rounded-t-[8px] px-[14px] py-[10px] text-[12px] font-semibold"
                    :class="trackingTemplateModal.tab === tab.id ? 'bg-[var(--brand-primary)] text-white' : 'text-white/55 hover:text-white'"
                    @click="trackingTemplateModal.tab = tab.id" x-text="tab.label"></button>
            </template>
        </div>
        <div class="pi-spec-modal-body">
            <div class="grid gap-0 lg:grid-cols-[minmax(0,1.3fr)_minmax(240px,0.7fr)]" x-show="trackingTemplateModal.tab === 'template'">
                <div class="space-y-[12px] border-b border-white/10 px-[18px] py-[16px] lg:border-b-0 lg:border-r">
                    <label class="block text-[12px]"><span class="mb-[4px] block text-white/60">Account</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" :value="googleAdsSummary.customer_id || '—'" readonly>
                    </label>
                    <label class="block text-[12px]"><span class="mb-[4px] block text-white/60">Scope</span>
                        <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="trackingTemplateModal.scope">
                            <option>Search campaigns</option>
                            <option>All eligible campaigns</option>
                        </select>
                    </label>
                    <label class="block text-[12px]"><span class="mb-[4px] block text-white/60">Current template</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="trackingTemplateModal.current" readonly>
                    </label>
                    <label class="block text-[12px]"><span class="mb-[4px] block text-white/60">Proposed template</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] font-mono text-[11px]" x-model="trackingTemplateModal.proposed">
                    </label>
                    <label class="block text-[12px]"><span class="mb-[4px] block text-white/60">Final URL</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="trackingTemplateModal.finalUrl">
                    </label>
                    <label class="block text-[12px]"><span class="mb-[4px] block text-white/60">Final URL suffix</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="trackingTemplateModal.suffix">
                    </label>
                    <div class="flex items-center justify-between gap-[8px] text-[12px]"><span>Preserve auto-tagging (gclid)</span>
                        <button type="button" class="relative h-[22px] w-[40px] rounded-full transition" :class="trackingTemplateModal.preserveGclid ? 'bg-[var(--brand-primary)]' : 'bg-white/20'" @click="trackingTemplateModal.preserveGclid = !trackingTemplateModal.preserveGclid">
                            <span class="absolute top-[2px] h-[18px] w-[18px] rounded-full bg-white transition" :class="trackingTemplateModal.preserveGclid ? 'left-[20px]' : 'left-[2px]'"></span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-[8px] text-[12px]"><span>Preserve existing parameters</span>
                        <button type="button" class="relative h-[22px] w-[40px] rounded-full transition" :class="trackingTemplateModal.preserveParams ? 'bg-[var(--brand-primary)]' : 'bg-white/20'" @click="trackingTemplateModal.preserveParams = !trackingTemplateModal.preserveParams">
                            <span class="absolute top-[2px] h-[18px] w-[18px] rounded-full bg-white transition" :class="trackingTemplateModal.preserveParams ? 'left-[20px]' : 'left-[2px]'"></span>
                        </button>
                    </div>
                </div>
                <aside class="space-y-[10px] bg-[#161616] px-[16px] py-[14px] text-[12px]">
                    <p class="text-[11px] font-semibold uppercase text-white/50">Validation checklist</p>
                    <template x-for="check in trackingTemplateModal.checks" :key="check.label">
                        <div class="flex items-center justify-between gap-[8px] rounded-[6px] border border-white/10 bg-[#0d0d0d] px-[10px] py-[8px]">
                            <span x-text="check.label"></span>
                            <span :class="check.status === 'Passed' ? 'text-emerald-300' : (check.status === 'Failed' ? 'text-rose-300' : 'text-amber-300')" x-text="check.status"></span>
                        </div>
                    </template>
                    <p class="rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">
                        Certification: <strong x-text="trackingTemplateModal.certification"></strong> — Apply disabled until Verified + tests pass.
                        OAuth/API connection is not third-party click-tracker certification.
                    </p>
                </aside>
            </div>
            <div class="space-y-[10px] px-[18px] py-[16px]" x-show="trackingTemplateModal.tab === 'tests'" x-cloak>
                <template x-for="check in trackingTemplateModal.checks" :key="'t-'+check.label">
                    <div class="flex items-center justify-between rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <span x-text="check.label"></span>
                        <span :class="check.status === 'Passed' ? 'text-emerald-300' : 'text-amber-300'" x-text="check.status"></span>
                    </div>
                </template>
                <p class="text-[11px] text-white/50">Path matrix: geo / device / error / fallback should pass before Verify.</p>
            </div>
            <div class="space-y-[10px] px-[18px] py-[16px]" x-show="trackingTemplateModal.tab === 'history'" x-cloak>
                <p class="text-[12px] text-white/60">No applied template history yet. Rollback reference appears after a verified Apply.</p>
                <button type="button" class="rounded-[6px] border border-white/30 px-[14px] py-[8px] text-[12px]" @click="showMenuToast('Review package export queued for Clickronix certification.', 'info')">Export review package</button>
            </div>
        </div>
        <footer class="flex shrink-0 flex-wrap items-center justify-end gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeTrackingTemplateModal()">Cancel</button>
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="saveTrackingTemplateDraft()">Save draft</button>
            <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white" @click="testTrackingTemplate()">Test template</button>
            <button type="button" class="rounded-[6px] border border-white/20 px-[18px] py-[8px] text-[13px] disabled:opacity-40"
                    :disabled="!trackingTemplateCanApply"
                    @click="applyTrackingTemplate()">Apply</button>
        </footer>
    </div>
</div>
</template>
