{{-- Spec Image 4 / Page 10: Choose audience creation method --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;"
     x-show="audienceMethodModal.open" x-cloak role="dialog" aria-modal="true"
     @click.self="closeAudienceMethodModal()"
     @keydown.escape.window="if (audienceMethodModal.open) closeAudienceMethodModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeAudienceMethodModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[980px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[12px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Choose audience creation method</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Select how you want to build an invalid traffic audience in Google Ads.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeAudienceMethodModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="pi-spec-modal-body grid gap-0 lg:grid-cols-[minmax(0,1.35fr)_minmax(240px,0.75fr)]">
            <div class="space-y-[10px] border-b border-white/10 px-[18px] py-[16px] lg:border-b-0 lg:border-r">
                <button type="button" class="w-full rounded-[10px] border p-[14px] text-left transition"
                        :class="audienceMethodModal.method === 'ga4' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/10' : 'border-white/15 bg-[#0d0d0d]'"
                        @click="audienceMethodModal.method = 'ga4'">
                    <div class="flex items-center gap-[8px]">
                        <span class="text-[13px] font-semibold">GA4 custom-event audience</span>
                        <span class="rounded-full bg-[var(--brand-primary)] px-[8px] py-[2px] text-[9px] font-bold uppercase text-white">Recommended</span>
                    </div>
                    <ul class="mt-[10px] space-y-[4px] text-[11px] text-white/70">
                        <li>✓ GA4 property linked to Google Ads</li>
                        <li>✓ Personalized advertising enabled</li>
                        <li>✓ Marketer access</li>
                        <li>✓ Event: <code class="text-[#ffd0b0]">clickronix_invalid_traffic</code></li>
                    </ul>
                </button>
                <button type="button" class="w-full rounded-[10px] border p-[14px] text-left transition"
                        :class="audienceMethodModal.method === 'website' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/10' : 'border-white/15 bg-[#0d0d0d]'"
                        @click="audienceMethodModal.method = 'website'">
                    <p class="text-[13px] font-semibold">Google Ads website data segment</p>
                    <ul class="mt-[10px] space-y-[4px] text-[11px] text-white/70">
                        <li>✓ Google tag <span class="font-mono" x-text="trackingInstallation.google_tag.id || 'AW-…'"></span></li>
                        <li>✓ Website visitor data source</li>
                        <li>✓ Custom parameter <code class="text-[#ffd0b0]">traffic_status=invalid</code></li>
                    </ul>
                </button>
                <div class="w-full rounded-[10px] border border-white/10 bg-[#0a0a0a] p-[14px] opacity-55">
                    <div class="flex items-center gap-[8px]">
                        <p class="text-[13px] font-semibold text-white/70">Customer Match upload</p>
                        <span class="rounded-full bg-white/10 px-[8px] py-[2px] text-[9px] font-semibold uppercase text-white/55">Not recommended for fingerprints or IPs</span>
                    </div>
                    <ul class="mt-[10px] space-y-[4px] text-[11px] text-white/45">
                        <li>Hashed identifiers (email/phone)</li>
                        <li>User-provided data — not valid for this protection path</li>
                    </ul>
                </div>
            </div>
            <aside class="space-y-[14px] px-[18px] py-[16px] text-[12px]">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/50">How it works</p>
                <ol class="space-y-[12px] text-white/70">
                    <li>Clickronix detects invalid traffic using IP, device, and behavior signals.</li>
                    <li>Clickronix fires a consent-gated invalid-traffic event to your GA4 property or Google tag.</li>
                    <li>Google recognizes the browser/user using its own tag signals.</li>
                    <li>Raw IP addresses and fingerprints are <strong class="text-white">not</strong> uploaded to Google.</li>
                </ol>
                <p class="rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">
                    Event rules: <code>traffic_status=invalid</code> + <code>risk_confidence=high</code> only.
                    V1 = GA4 event audience + separate IP exclusion — don&apos;t call both the same list.
                </p>
            </aside>
        </div>
        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeAudienceMethodModal()">Cancel</button>
            <div class="flex flex-wrap gap-[8px]">
                <a href="https://support.google.com/analytics/answer/9267735" target="_blank" rel="noopener"
                   class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px] text-white no-underline hover:bg-white/10">View prerequisites</a>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white disabled:opacity-40"
                        :disabled="audienceMethodModal.method === 'match'"
                        @click="continueAudienceMethod()">
                    <span x-text="audienceMethodModal.method === 'website' ? 'Continue with website segment' : 'Continue with GA4'"></span>
                </button>
            </div>
        </footer>
    </div>
</div>
</template>
