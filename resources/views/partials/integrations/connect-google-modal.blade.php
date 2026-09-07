{{-- Spec Image 2: Connect Google Ads account --}}
<template x-teleport="body">
<div
    class="pi-spec-modal-root"
    style="position:fixed;inset:0;z-index:2147483000;display:none;"
    x-show="connectGoogleModal.open"
    x-cloak
    role="dialog"
    aria-modal="true"
    @click.self="closeConnectGoogleModal()"
    @keydown.escape.window="if (connectGoogleModal.open) closeConnectGoogleModal()"
>
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" aria-hidden="true" @click="closeConnectGoogleModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[920px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 bg-[#121212] px-[22px] pb-[14px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Connect Google Ads account</h2>
                <p class="mt-[4px] text-[12px] text-white/60">Bind OAuth login to Customer ID and verified domain. Read and write capabilities are tested separately.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10 hover:text-white" @click="closeConnectGoogleModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="shrink-0 border-b border-white/10 bg-[#121212] px-[22px] py-[12px]">
            <ol class="flex flex-wrap gap-[8px] text-[11px]">
                <template x-for="(step, idx) in connectGoogleModal.steps" :key="step">
                    <li class="inline-flex items-center gap-[6px] rounded-full px-[10px] py-[4px]"
                        :class="connectGoogleModal.step === idx ? 'bg-[var(--brand-primary)] text-white' : 'bg-white/5 text-white/55'">
                        <span class="font-semibold" x-text="(idx + 1)"></span>
                        <span x-text="step"></span>
                    </li>
                </template>
            </ol>
        </div>

        <div class="pi-spec-modal-body grid gap-[0] bg-[#121212] lg:grid-cols-2">
            <div class="space-y-[12px] border-b border-white/10 bg-[#121212] px-[22px] py-[18px] lg:border-b-0 lg:border-r">
                <label class="block">
                    <span class="mb-[4px] block text-[11px] font-semibold text-white/70">Google login</span>
                    <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" :value="googleAdsSummary.email || 'Not connected'" readonly>
                </label>
                <label class="block">
                    <span class="mb-[4px] block text-[11px] font-semibold text-white/70">Manager account (optional)</span>
                    <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" x-model="connectGoogleModal.manager_id">
                        <option value="">None</option>
                        <template x-for="acc in connectGoogleModal.accounts" :key="'m-'+acc.id">
                            <option :value="acc.id" x-text="acc.label + (acc.customer_id ? ' · ' + acc.customer_id : '')"></option>
                        </template>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-[4px] block text-[11px] font-semibold text-white/70">Google Ads Customer ID</span>
                    <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" placeholder="123-456-7890" x-model="connectGoogleModal.customer_id">
                    <span class="mt-[4px] block text-[10px] text-white/45">10-digit Customer ID — not AW tag ID.</span>
                </label>
                <label class="block">
                    <span class="mb-[4px] block text-[11px] font-semibold text-white/70">Domain</span>
                    <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" x-model="connectGoogleModal.domain_id">
                        <option value="">Select verified domain</option>
                        @foreach ($manualDomains as $domain)
                            <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-[4px] block text-[11px] font-semibold text-white/70">Google Tag ID (AW-…)</span>
                    <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" placeholder="AW-123456789" x-model="connectGoogleModal.google_tag_id">
                </label>
                <label class="block">
                    <span class="mb-[4px] block text-[11px] font-semibold text-white/70">GTM Container (optional)</span>
                    <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" placeholder="GTM-XXXXXXX" x-model="connectGoogleModal.gtm_id">
                </label>
            </div>

            <div class="space-y-[14px] bg-[#121212] px-[22px] py-[18px]">
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase tracking-wide text-white/55">Account identifiers</p>
                    <dl class="space-y-[6px] text-[12px]">
                        <div class="flex justify-between gap-[8px]"><dt class="text-white/55">Ads Customer ID</dt><dd class="font-mono text-white/90" x-text="connectGoogleModal.customer_id || '—'"></dd></div>
                        <div class="flex justify-between gap-[8px]"><dt class="text-white/55">Google Tag ID</dt><dd class="font-mono text-white/90" x-text="connectGoogleModal.google_tag_id || '—'"></dd></div>
                        <div class="flex justify-between gap-[8px]"><dt class="text-white/55">GTM Container</dt><dd class="font-mono text-white/90" x-text="connectGoogleModal.gtm_id || '—'"></dd></div>
                    </dl>
                </div>
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase tracking-wide text-white/55">Permissions requested</p>
                    <ul class="space-y-[6px]">
                        <template x-for="perm in connectGoogleModal.permissions" :key="perm.key">
                            <li class="flex items-center justify-between gap-[8px] rounded-[6px] border border-white/10 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]">
                                <span x-text="perm.label"></span>
                                <span class="inline-flex items-center gap-[4px]"
                                      :class="perm.status === 'passed' ? 'text-emerald-300' : (perm.status === 'failed' ? 'text-rose-300' : 'text-amber-300')">
                                    <span x-text="perm.status === 'passed' ? 'Passed' : (perm.status === 'failed' ? 'Failed' : 'Pending')"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <p class="rounded-[8px] border border-[var(--brand-primary)]/40 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">
                    OAuth connection does not mean protection is active.
                </p>
            </div>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[10px] border-t border-white/15 bg-[#121212] px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeConnectGoogleModal()">Cancel</button>
            <div class="flex flex-wrap gap-[8px]">
                <a :href="googleAdsSummary.oauth_url || '#'" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px] text-white no-underline hover:bg-white/10">
                    <span x-text="googleAdsSummary.connected ? 'Re-run Google login' : 'Start Google login'"></span>
                </a>
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="runPermissionTest()" :disabled="connectGoogleModal.testing">
                    <span x-text="connectGoogleModal.testing ? 'Testing…' : 'Run permission test'"></span>
                </button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white disabled:opacity-40"
                        @click="saveGoogleAccountDraft()"
                        :disabled="!canSaveGoogleAccount">
                    Save account
                </button>
            </div>
        </footer>
    </div>
</div>
</template>
