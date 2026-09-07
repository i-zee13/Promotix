{{-- Spec Image 9: Google Ads placement exclusions --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;" x-show="placementModal.open" x-cloak role="dialog" aria-modal="true" @click.self="closePlacementModal()" @keydown.escape.window="if (placementModal.open) closePlacementModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closePlacementModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[1100px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[10px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Google Ads placement exclusions</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Known Display/PMax placements only — unknown inventory never auto-excluded.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closePlacementModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="flex shrink-0 flex-wrap gap-[4px] border-b border-white/10 px-[16px] pt-[8px]">
            <template x-for="tab in placementModal.tabs" :key="tab.id">
                <button type="button" class="rounded-t-[8px] px-[14px] py-[10px] text-[12px] font-semibold"
                    :class="placementModal.tab === tab.id ? 'bg-[var(--brand-primary)] text-white' : 'text-white/55 hover:text-white'"
                    @click="placementModal.tab = tab.id" x-text="tab.label"></button>
            </template>
        </div>
        <div class="pi-spec-modal-body grid gap-0 lg:grid-cols-[minmax(0,1.45fr)_minmax(240px,0.75fr)]">
            <div class="border-b border-white/10 px-[16px] py-[14px] lg:border-b-0 lg:border-r">
                <div class="mb-[12px] flex flex-wrap gap-[8px] text-[11px]">
                    <select class="rounded border border-white/20 bg-[#0d0d0d] px-[8px] py-[4px] text-white/80" x-model="placementModal.filterSource">
                        <option>Display + Performance Max</option>
                        <option>Display only</option>
                        <option>Performance Max only</option>
                    </select>
                    <select class="rounded border border-white/20 bg-[#0d0d0d] px-[8px] py-[4px] text-white/80" x-model="placementModal.filterRange">
                        <option>Last 30 days</option>
                        <option>Last 7 days</option>
                        <option>Last 90 days</option>
                    </select>
                    <select class="rounded border border-white/20 bg-[#0d0d0d] px-[8px] py-[4px] text-white/80" x-model="placementModal.filterConfidence">
                        <option>High confidence</option>
                        <option>Medium+</option>
                        <option>All</option>
                    </select>
                </div>
                <div class="overflow-x-auto" x-show="placementModal.tab === 'recommendations'">
                    <table class="w-full min-w-[640px] text-left text-[12px]">
                        <thead class="text-[10px] uppercase tracking-wide text-white/45">
                            <tr>
                                <th class="px-[6px] py-[8px]"></th>
                                <th class="px-[6px] py-[8px]">Placement</th>
                                <th class="px-[6px] py-[8px]">Source</th>
                                <th class="px-[6px] py-[8px]">Clicks</th>
                                <th class="px-[6px] py-[8px]">Invalid rate</th>
                                <th class="px-[6px] py-[8px]">Leads</th>
                                <th class="px-[6px] py-[8px]">Confidence</th>
                                <th class="px-[6px] py-[8px]">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in filteredPlacementRows" :key="row.id">
                                <tr class="border-t border-white/10" :class="row.selected ? 'bg-[var(--brand-primary)]/15' : ''">
                                    <td class="px-[6px] py-[10px]"><input type="checkbox" x-model="row.selected" :disabled="row.confidence === 'Low' && placementModal.neverUnknown"></td>
                                    <td class="px-[6px] py-[10px] font-mono" x-text="row.placement"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.source"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.clicks"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.invalid_rate + '%'"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.leads === null || row.leads === undefined ? '—' : row.leads"></td>
                                    <td class="px-[6px] py-[10px]">
                                        <span class="rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                              :class="{
                                                'bg-emerald-500/25 text-emerald-300': row.confidence === 'High',
                                                'bg-amber-500/25 text-amber-200': row.confidence === 'Medium',
                                                'bg-white/10 text-white/55': row.confidence === 'Low'
                                              }" x-text="row.confidence"></span>
                                    </td>
                                    <td class="px-[6px] py-[10px]">
                                        <button type="button" class="text-[11px] font-semibold text-[var(--brand-primary)] disabled:opacity-40"
                                                :disabled="row.source === 'Unknown' && placementModal.neverUnknown"
                                                @click="row.selected = true">Exclude</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div x-show="placementModal.tab !== 'recommendations'" x-cloak class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[14px] text-[12px] text-white/65">
                    <p x-show="placementModal.tab === 'applied'">Applied exclusions appear after Google mutation + read-back (Queued → Sent → Applied).</p>
                    <p x-show="placementModal.tab === 'allowlist'">Customer-approved placements are preserved and never auto-excluded.</p>
                    <p x-show="placementModal.tab === 'rules'">Primary source: Google Ads placement report. ValueTrack {placement} helper. Referrer supporting only.</p>
                </div>
                <p class="mt-[10px] text-[11px] text-white/45" x-show="placementModal.tab === 'recommendations'" x-text="'Showing ' + filteredPlacementRows.length + ' of ' + placementModal.rows.length + ' placements'"></p>
            </div>
            <aside class="space-y-[12px] bg-[#161616] px-[16px] py-[14px] text-[12px]">
                <p><span class="text-white/50">Selected:</span> <span x-text="placementSelectedCount + ' placement(s)'"></span></p>
                <p><span class="text-white/50">Campaign scope:</span> <span x-text="placementModal.campaignScope"></span></p>
                <div>
                    <p class="mb-[6px] text-[11px] font-semibold uppercase text-white/50">Reason</p>
                    <p class="text-white/75" x-text="placementSelectedReason"></p>
                </div>
                <p class="text-[11px] text-white/55">Review cycle: Review in 30 days</p>
                <div class="space-y-[10px]">
                    <div class="flex items-center justify-between gap-[8px]"><span>Preserve customer placements</span>
                        <button type="button" class="relative h-[22px] w-[40px] rounded-full transition" :class="placementModal.preserve ? 'bg-[var(--brand-primary)]' : 'bg-white/20'" @click="placementModal.preserve = !placementModal.preserve">
                            <span class="absolute top-[2px] h-[18px] w-[18px] rounded-full bg-white transition" :class="placementModal.preserve ? 'left-[20px]' : 'left-[2px]'"></span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-[8px]"><span>Never auto-exclude unknown inventory</span>
                        <button type="button" class="relative h-[22px] w-[40px] rounded-full transition" :class="placementModal.neverUnknown ? 'bg-[var(--brand-primary)]' : 'bg-white/20'" @click="placementModal.neverUnknown = !placementModal.neverUnknown">
                            <span class="absolute top-[2px] h-[18px] w-[18px] rounded-full bg-white transition" :class="placementModal.neverUnknown ? 'left-[20px]' : 'left-[2px]'"></span>
                        </button>
                    </div>
                </div>
                <div>
                    <p class="mb-[6px] text-[11px] font-semibold uppercase text-white/50">Evidence source</p>
                    <p class="text-white/65">Google Ads placement report · ValueTrack {placement}; referrer alone not guaranteed.</p>
                </div>
            </aside>
        </div>
        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closePlacementModal()">Cancel</button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="showMenuToast('Evidence export package queued.', 'info')">Export evidence</button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white disabled:opacity-40"
                        :disabled="placementSelectedCount < 1"
                        @click="reviewAndExcludePlacements()">Review and exclude</button>
            </div>
        </footer>
    </div>
</div>
</template>
