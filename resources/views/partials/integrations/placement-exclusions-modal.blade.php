{{-- Spec Image 9: Google Ads placement exclusions --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;" x-show="placementModal.open" x-cloak role="dialog" aria-modal="true" @click.self="closePlacementModal()" @keydown.escape.window="if (placementModal.open) closePlacementModal()">
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
                    <span class="rounded border border-white/20 px-[8px] py-[4px] text-white/70">Campaigns</span>
                    <span class="rounded border border-white/20 px-[8px] py-[4px] text-white/70">Display + Performance Max</span>
                    <span class="rounded border border-white/20 px-[8px] py-[4px] text-white/70">Last 30 days</span>
                    <span class="rounded border border-white/20 px-[8px] py-[4px] text-white/70">High confidence</span>
                </div>
                <div class="overflow-x-auto">
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
                            <template x-for="row in placementModal.rows" :key="row.id">
                                <tr class="border-t border-white/10" :class="row.selected ? 'bg-[var(--brand-primary)]/15' : ''">
                                    <td class="px-[6px] py-[10px]"><input type="checkbox" x-model="row.selected"></td>
                                    <td class="px-[6px] py-[10px] font-mono" x-text="row.placement"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.source"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.clicks"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.invalid_rate + '%'"></td>
                                    <td class="px-[6px] py-[10px]" x-text="row.leads"></td>
                                    <td class="px-[6px] py-[10px]" :class="row.confidence === 'High' ? 'text-emerald-300' : 'text-amber-300'" x-text="row.confidence"></td>
                                    <td class="px-[6px] py-[10px]"><button type="button" class="text-[11px] font-semibold text-[var(--brand-primary)]" @click="row.selected = true">Exclude</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="mt-[10px] text-[11px] text-white/45" x-text="'Showing ' + placementModal.rows.length + ' of ' + placementModal.rows.length + ' placements'"></p>
            </div>
            <aside class="space-y-[12px] bg-[#161616] px-[16px] py-[14px] text-[12px]">
                <p><span class="text-white/50">Selected:</span> <span x-text="placementSelectedCount + ' placement(s)'"></span></p>
                <div>
                    <p class="mb-[6px] text-[11px] font-semibold uppercase text-white/50">Reasoning</p>
                    <p class="text-white/75">High invalid rate with weak/no qualified leads. Known placement IDs only.</p>
                </div>
                <label class="flex items-center justify-between gap-[8px]"><span>Preserve customer placements</span><input type="checkbox" x-model="placementModal.preserve" class="accent-[var(--brand-primary)]"></label>
                <label class="flex items-center justify-between gap-[8px]"><span>Never auto-exclude unknown inventory</span><input type="checkbox" x-model="placementModal.neverUnknown" class="accent-[var(--brand-primary)]"></label>
                <div>
                    <p class="mb-[6px] text-[11px] font-semibold uppercase text-white/50">Evidence source</p>
                    <p class="text-white/65">Google Ads placement report · ValueTrack</p>
                </div>
            </aside>
        </div>
        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closePlacementModal()">Cancel</button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="showMenuToast('Evidence export coming in OBS-01.', 'info')">Export evidence</button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white" @click="showMenuToast('Placement exclude is feature-flagged (P2).', 'info')">Review and exclude</button>
            </div>
        </footer>
    </div>
</div>
</template>
