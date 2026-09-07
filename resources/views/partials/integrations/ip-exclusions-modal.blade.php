{{-- Spec Image 8: Google Ads IP exclusions --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;" x-show="ipExclusionsModal.open" x-cloak role="dialog" aria-modal="true" @click.self="closeIpExclusionsModal()" @keydown.escape.window="if (ipExclusionsModal.open) closeIpExclusionsModal()">
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
                                        <td class="px-[6px] py-[10px] font-semibold text-[var(--brand-primary)]" x-text="row.risk"></td>
                                        <td class="px-[6px] py-[10px] text-white/75" x-text="row.reason"></td>
                                        <td class="px-[6px] py-[10px] text-white/75" x-text="row.scope"></td>
                                        <td class="px-[6px] py-[10px] text-white/65" x-text="row.expires"></td>
                                        <td class="px-[6px] py-[10px]">
                                            <span class="rounded-full px-[8px] py-[3px] text-[10px] font-semibold"
                                                  :class="{
                                                    'bg-emerald-500/25 text-emerald-300': row.google_status === 'Applied',
                                                    'bg-amber-500/25 text-amber-200': row.google_status === 'Review required' || row.google_status === 'Queued' || row.google_status === 'Sent',
                                                    'bg-rose-500/25 text-rose-300': row.google_status === 'Failed',
                                                    'bg-white/10 text-white/60': !['Applied','Review required','Queued','Sent','Failed'].includes(row.google_status)
                                                  }"
                                                  x-text="row.google_status"></span>
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
                        <li><strong>Auto exclude:</strong> High confidence + repeat/strong evidence</li>
                        <li><strong>VPN/proxy only:</strong> Review by default</li>
                        <li><strong>Scope:</strong> Explicit eligible campaigns</li>
                        <li><strong>Expiry:</strong> Short duration; Clickronix-owned only auto-remove</li>
                        <li><strong>Customer entries:</strong> Preserve; owner=customer</li>
                        <li><strong>Idempotency:</strong> decision + customer + campaign + IP</li>
                        <li><strong>Status:</strong> Queued → Sent → Applied/Failed</li>
                        <li><strong>Proof:</strong> Google mutation request + later read-back</li>
                    </ul>
                </template>
                <p class="mt-[14px] rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">
                    Reality check: IP exclusion future eligible exposure affect karta hai — already charged click refund nahi. Dynamic/shared IP ki wajah se audience exclusion complementary hai, replacement nahi.
                </p>
            </div>
            <aside class="space-y-[14px] bg-[#161616] px-[16px] py-[14px]">
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase tracking-wide text-white/50">Google Ads API read-back</p>
                    <p class="inline-flex items-center gap-[6px] text-[12px]">
                        <span class="inline-flex h-[16px] w-[16px] items-center justify-center rounded-full bg-emerald-500/25 text-[10px] text-emerald-300">✓</span>
                        <span class="text-white/50">Status:</span>
                        <span class="font-semibold text-emerald-300" x-text="ipReadback.status"></span>
                    </p>
                    <p class="mt-[6px] font-mono text-[11px] text-white/70" x-text="'Mutation request ID: ' + (ipReadback.request_id || '—')"></p>
                    <p class="mt-[4px] text-[11px] text-white/55" x-text="'Last verified: ' + (ipReadback.verified_at || '—')"></p>
                </div>
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase tracking-wide text-white/50">Policy</p>
                    <template x-for="item in [
                        { key: 'highOnly', label: 'Auto-apply only high confidence and repeat evidence' },
                        { key: 'vpnReview', label: 'VPN alone requires review' },
                        { key: 'preserveCustomer', label: 'Preserve customer exclusions' },
                        { key: 'autoExpire', label: 'Auto-expire Clickronix-owned entries' },
                    ]" :key="item.key">
                        <div class="mb-[10px] flex items-center justify-between gap-[8px] text-[12px]">
                            <span x-text="item.label"></span>
                            <button type="button" class="relative h-[22px] w-[40px] shrink-0 rounded-full transition"
                                    :class="ipExclusionsModal.policy[item.key] ? 'bg-[var(--brand-primary)]' : 'bg-white/20'"
                                    @click="ipExclusionsModal.policy[item.key] = !ipExclusionsModal.policy[item.key]">
                                <span class="absolute top-[2px] h-[18px] w-[18px] rounded-full bg-white transition"
                                      :class="ipExclusionsModal.policy[item.key] ? 'left-[20px]' : 'left-[2px]'"></span>
                            </button>
                        </div>
                    </template>
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
