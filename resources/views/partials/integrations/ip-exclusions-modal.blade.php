{{-- Spec Image 8: Google Ads IP exclusions --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;" x-show="ipExclusionsModal.open" x-cloak role="dialog" aria-modal="true" @click.self="closeIpExclusionsModal()" @keydown.escape.window="if (ipExclusionsModal.open) closeIpExclusionsModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeIpExclusionsModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[1100px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[10px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Google Ads IP exclusions</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Selected campaign scope par IP add/remove. Website block, Google IP exclusion aur audience alag statuses hain.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeIpExclusionsModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="flex shrink-0 flex-wrap gap-[4px] border-b border-white/10 px-[16px] pt-[8px]">
            <template x-for="tab in ipExclusionsModal.tabs" :key="tab.id">
                <button type="button" class="rounded-t-[8px] px-[14px] py-[10px] text-[12px] font-semibold"
                    :class="ipExclusionsModal.tab === tab.id ? 'bg-[var(--brand-primary)] text-white' : 'text-white/55 hover:text-white'"
                    @click="ipExclusionsModal.tab = tab.id" x-text="tab.label"></button>
            </template>
        </div>
        <div class="pi-spec-modal-body grid gap-0 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,0.8fr)]">
            <div class="border-b border-white/10 px-[16px] py-[14px] lg:border-b-0 lg:border-r">
                <template x-if="ipExclusionsModal.tab !== 'policy'">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-[12px]">
                            <thead class="text-[10px] uppercase tracking-wide text-white/45">
                                <tr>
                                    <th class="px-[6px] py-[8px]"><input type="checkbox" @change="toggleAllIpRows($event.target.checked)"></th>
                                    <th class="px-[6px] py-[8px]">IP</th>
                                    <th class="px-[6px] py-[8px]">Risk</th>
                                    <th class="px-[6px] py-[8px]">Reason</th>
                                    <th class="px-[6px] py-[8px]">Campaign scope</th>
                                    <th class="px-[6px] py-[8px]">Expires</th>
                                    <th class="px-[6px] py-[8px]">Google status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in filteredIpExclusionRows" :key="row.id">
                                    <tr class="border-t border-white/10" :class="row.selected ? 'bg-[var(--brand-primary)]/15' : ''">
                                        <td class="px-[6px] py-[10px]"><input type="checkbox" x-model="row.selected"></td>
                                        <td class="px-[6px] py-[10px] font-mono" x-text="row.ip"></td>
                                        <td class="px-[6px] py-[10px]" x-text="row.risk"></td>
                                        <td class="px-[6px] py-[10px] text-white/75" x-text="row.reason"></td>
                                        <td class="px-[6px] py-[10px] text-white/75" x-text="row.scope"></td>
                                        <td class="px-[6px] py-[10px] text-white/65" x-text="row.expires"></td>
                                        <td class="px-[6px] py-[10px]">
                                            <span :class="row.google_status === 'Applied' ? 'text-emerald-300' : (row.google_status === 'Failed' ? 'text-rose-300' : 'text-amber-300')" x-text="row.google_status"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p class="mt-[12px] text-[12px] text-white/45" x-show="filteredIpExclusionRows.length === 0">No IP exclusions in this tab yet.</p>
                    </div>
                </template>
                <template x-if="ipExclusionsModal.tab === 'policy'">
                    <ul class="space-y-[10px] text-[12px] text-white/80">
                        <li>Auto exclude: high confidence + repeat/strong evidence</li>
                        <li>VPN/proxy only: review by default</li>
                        <li>Scope: explicit eligible campaigns</li>
                        <li>Expiry: short duration; Clickronix-owned only auto-remove</li>
                        <li>Customer entries: preserve; owner=customer</li>
                        <li>Status flow: Queued → Sent → Applied/Failed</li>
                        <li>Proof: Google mutation request + later read-back</li>
                    </ul>
                </template>
                <p class="mt-[14px] rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">
                    Note: IP blocking affects future eligible exposure and does not refund a charged click.
                </p>
            </div>
            <aside class="space-y-[14px] bg-[#161616] px-[16px] py-[14px]">
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase tracking-wide text-white/50">Google Ads API read-back</p>
                    <p class="text-[12px]"><span class="text-white/50">Status:</span> <span class="text-emerald-300" x-text="ipReadback.status"></span></p>
                    <p class="mt-[4px] font-mono text-[11px] text-white/70" x-text="'Request: ' + (ipReadback.request_id || '—')"></p>
                    <p class="mt-[4px] text-[11px] text-white/55" x-text="'Last verified: ' + (ipReadback.verified_at || '—')"></p>
                </div>
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase tracking-wide text-white/50">Policy</p>
                    <label class="mb-[8px] flex items-center justify-between gap-[8px] text-[12px]"><span>Auto-apply only high confidence and repeat evidence</span><input type="checkbox" x-model="ipExclusionsModal.policy.highOnly" class="accent-[var(--brand-primary)]"></label>
                    <label class="mb-[8px] flex items-center justify-between gap-[8px] text-[12px]"><span>VPN alone requires review</span><input type="checkbox" x-model="ipExclusionsModal.policy.vpnReview" class="accent-[var(--brand-primary)]"></label>
                    <label class="mb-[8px] flex items-center justify-between gap-[8px] text-[12px]"><span>Preserve customer exclusions</span><input type="checkbox" x-model="ipExclusionsModal.policy.preserveCustomer" class="accent-[var(--brand-primary)]"></label>
                    <label class="flex items-center justify-between gap-[8px] text-[12px]"><span>Auto-expire Clickronix-owned entries</span><input type="checkbox" x-model="ipExclusionsModal.policy.autoExpire" class="accent-[var(--brand-primary)]"></label>
                </div>
            </aside>
        </div>
        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeIpExclusionsModal()">Close</button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="removeSelectedIpExclusions()">Remove selected</button>
                <a :href="googleAdsSummary.protection_url || '#'" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white no-underline">Add manual exclusion</a>
            </div>
        </footer>
    </div>
</div>
</template>
