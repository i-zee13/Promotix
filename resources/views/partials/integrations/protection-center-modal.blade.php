{{-- Spec: Protection Center — IP, audience, placement, Pixel Guard --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;"
     x-show="protectionCenter.open" x-cloak role="dialog" aria-modal="true"
     @click.self="closeProtectionCenter()"
     @keydown.escape.window="if (protectionCenter.open) closeProtectionCenter()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeProtectionCenter()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[920px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[12px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Protection Center</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Account Connected is not Protection Active. Configure IP, audience, placement, and Pixel Guard separately.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeProtectionCenter()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="flex shrink-0 flex-wrap gap-[4px] border-b border-white/10 px-[16px] pt-[8px]">
            <template x-for="tab in protectionCenter.tabs" :key="tab.id">
                <button type="button" class="rounded-t-[8px] px-[14px] py-[10px] text-[12px] font-semibold"
                    :class="protectionCenter.tab === tab.id ? 'bg-[var(--brand-primary)] text-white' : 'text-white/55 hover:text-white'"
                    @click="protectionCenter.tab = tab.id" x-text="tab.label"></button>
            </template>
        </div>
        <div class="pi-spec-modal-body px-[22px] py-[18px]">
            <div class="grid gap-[12px] sm:grid-cols-2" x-show="protectionCenter.tab === 'overview'">
                <button type="button" class="rounded-[10px] border border-white/15 bg-[#0d0d0d] p-[14px] text-left hover:border-[var(--brand-primary)]/50" @click="closeProtectionCenter(); openIpExclusionsModal()">
                    <p class="text-[13px] font-semibold">IP exclusions</p>
                    <p class="mt-[6px] text-[11px] text-white/55">Queue high-confidence IPs to Google Ads IP exclusions with read-back.</p>
                </button>
                <button type="button" class="rounded-[10px] border border-white/15 bg-[#0d0d0d] p-[14px] text-left hover:border-[var(--brand-primary)]/50" @click="closeProtectionCenter(); openAudienceMethodModal()">
                    <p class="text-[13px] font-semibold">Audience exclusion</p>
                    <p class="mt-[6px] text-[11px] text-white/55">GA4 custom-event audience wizard — method → rule → apply.</p>
                </button>
                <button type="button" class="rounded-[10px] border border-white/15 bg-[#0d0d0d] p-[14px] text-left hover:border-[var(--brand-primary)]/50" @click="closeProtectionCenter(); openPlacementModal()">
                    <p class="text-[13px] font-semibold">Placement exclusions</p>
                    <p class="mt-[6px] text-[11px] text-white/55">Recommend and apply placement blocks with allowlist rules.</p>
                </button>
                <button type="button" class="rounded-[10px] border border-white/15 bg-[#0d0d0d] p-[14px] text-left hover:border-[var(--brand-primary)]/50" @click="closeProtectionCenter(); openPixelGuardModal()">
                    <p class="text-[13px] font-semibold">Pixel Guard</p>
                    <p class="mt-[6px] text-[11px] text-white/55">Consent-gated conversion / event firing policy.</p>
                </button>
            </div>
            <div x-show="protectionCenter.tab === 'pixel'" x-cloak class="space-y-[12px]">
                <p class="text-[12px] text-white/70">Pixel Guard blocks conversion / remarketing pixels when traffic is confirmed invalid and consent allows analytics/ad storage.</p>
                <ul class="list-disc space-y-[6px] pl-[18px] text-[12px] text-white/65">
                    <li>Never fire conversions on invalid verdicts</li>
                    <li>Audience signal is a separate non-conversion event</li>
                    <li>Keep false Applied badges off until Google read-back succeeds</li>
                </ul>
                <div class="flex flex-wrap gap-[8px]">
                    <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[16px] py-[8px] text-[13px] font-semibold" @click="closeProtectionCenter(); openPixelGuardModal()">Open Pixel Guard</button>
                    <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="openTestProtectionModal()">Run Full Test</button>
                </div>
            </div>
            <div x-show="protectionCenter.tab === 'ip'" x-cloak>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[16px] py-[8px] text-[13px] font-semibold" @click="closeProtectionCenter(); openIpExclusionsModal()">Open IP exclusions</button>
                <p class="mt-[10px] text-[11px] text-white/50">IP exclusion is separate from GA4 audience — do not call both the same “list”.</p>
            </div>
            <div x-show="protectionCenter.tab === 'audience'" x-cloak class="space-y-[8px]">
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[16px] py-[8px] text-[13px] font-semibold" @click="closeProtectionCenter(); openAudienceMethodModal()">Choose audience method</button>
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeProtectionCenter(); openCreateAudienceModal()">Create GA4 audience</button>
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeProtectionCenter(); openApplyAudienceModal()">Apply audience exclusion</button>
            </div>
            <div x-show="protectionCenter.tab === 'placement'" x-cloak>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[16px] py-[8px] text-[13px] font-semibold" @click="closeProtectionCenter(); openPlacementModal()">Open Placement exclusions</button>
            </div>
        </div>
        <footer class="flex shrink-0 justify-end border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeProtectionCenter()">Close</button>
        </footer>
    </div>
</div>
</template>
