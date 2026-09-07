{{-- Spec Image 3: Install tracking and audience tags --}}
<template x-teleport="body">
<div
    class="pi-spec-modal-root"
    style="position:fixed;inset:0;z-index:2147483000;display:none;"
    x-show="installTagsModal.open"
    x-cloak
    role="dialog"
    aria-modal="true"
    @click.self="closeInstallTagsModal()"
    @keydown.escape.window="if (installTagsModal.open) closeInstallTagsModal()"
>
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" aria-hidden="true" @click="closeInstallTagsModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[980px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 bg-[#121212] px-[22px] pb-[14px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Install tracking and audience tags</h2>
                <p class="mt-[4px] text-[12px] text-white/60">Clickronix Script, Google Tag, and GTM publication are separate. GTM connected ≠ Google tag installed.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10 hover:text-white" @click="closeInstallTagsModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="flex shrink-0 flex-wrap gap-[4px] border-b border-white/10 bg-[#121212] px-[16px] pt-[10px]">
            <template x-for="tab in installTagsModal.tabs" :key="tab.id">
                <button type="button"
                        class="rounded-t-[8px] px-[14px] py-[10px] text-[12px] font-semibold"
                        :class="installTagsModal.tab === tab.id ? 'bg-[var(--brand-primary)] text-white' : 'text-white/55 hover:text-white'"
                        @click="installTagsModal.tab = tab.id"
                        x-text="tab.label"></button>
            </template>
        </div>

        <div class="pi-spec-modal-body grid gap-0 bg-[#121212] lg:grid-cols-[240px_minmax(0,1fr)]">
            <aside class="space-y-[10px] border-b border-white/10 bg-[#161616] px-[16px] py-[16px] lg:border-b-0 lg:border-r">
                <div class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[10px] py-[10px]">
                    <div class="flex items-center justify-between gap-[6px]">
                        <p class="text-[12px] font-semibold">Clickronix Script</p>
                        <span class="text-[10px] font-semibold" :class="trackingInstallation.script.ok ? 'text-emerald-300' : 'text-rose-300'" x-text="trackingInstallation.script.ok ? 'Active' : 'Missing'"></span>
                    </div>
                    <p class="mt-[4px] text-[10px] text-white/50" x-text="trackingInstallation.script.ok ? 'Installed and running.' : 'Install collector first.'"></p>
                </div>
                <div class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[10px] py-[10px]">
                    <div class="flex items-center justify-between gap-[6px]">
                        <p class="text-[12px] font-semibold truncate">Google Tag</p>
                        <span class="text-[10px] font-semibold" :class="trackingInstallation.google_tag.ok ? 'text-emerald-300' : 'text-rose-300'" x-text="trackingInstallation.google_tag.ok ? 'Detected' : 'Missing'"></span>
                    </div>
                    <p class="mt-[2px] font-mono text-[10px] text-white/45" x-text="trackingInstallation.google_tag.id"></p>
                    <p class="mt-[4px] text-[10px] text-white/50">Install via GTM or direct.</p>
                </div>
                <div class="rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[10px] py-[10px]">
                    <div class="flex items-center justify-between gap-[6px]">
                        <p class="text-[12px] font-semibold truncate">Google Tag Manager</p>
                        <span class="text-[10px] font-semibold"
                              :class="trackingInstallation.gtm.ok ? 'text-emerald-300' : (trackingInstallation.gtm.unpublished ? 'text-amber-300' : 'text-rose-300')"
                              x-text="trackingInstallation.gtm.ok ? 'Connected' : (trackingInstallation.gtm.unpublished ? 'Unpublished' : 'Offline')"></span>
                    </div>
                    <p class="mt-[2px] font-mono text-[10px] text-white/45" x-text="trackingInstallation.gtm.id"></p>
                    <p class="mt-[4px] text-[10px] text-white/50">Publish your GTM changes.</p>
                </div>
                <p class="rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[8px] py-[8px] text-[10px] leading-relaxed text-[#ffd0b0]">
                    GTM installed does not automatically mean Google tag is installed.
                </p>
            </aside>

            <div class="bg-[#121212] px-[18px] py-[16px] pb-[20px]">
                <template x-if="installTagsModal.tab === 'script'">
                    <div class="space-y-[12px]">
                        <p class="text-[13px] text-white/80">Install the Clickronix collector on every landing page. Heartbeat + last event + domain match are success evidence.</p>
                        <a :href="trackingInstallation.setup_url || '#'" class="pi-primary-btn inline-flex no-underline">Open tracking setup →</a>
                    </div>
                </template>

                <template x-if="installTagsModal.tab === 'google_tag'">
                    <div class="space-y-[12px]">
                        <p class="text-[13px] text-white/80">Google Tag (<span class="font-mono" x-text="trackingInstallation.google_tag.id"></span>) is the Ads website data destination. Detect it on the production page before claiming protection.</p>
                        <label class="block max-w-[320px]">
                            <span class="mb-[4px] block text-[11px] font-semibold text-white/70">Google Tag ID</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" x-model="installTagsModal.google_tag_id" placeholder="AW-…">
                        </label>
                    </div>
                </template>

                <template x-if="installTagsModal.tab === 'gtm'">
                    <div class="space-y-[14px]">
                        <div class="grid gap-[10px] sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white/70">GTM Container ID</span>
                                <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" x-model="installTagsModal.gtm_id" placeholder="GTM-…">
                            </label>
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white/70">Workspace</span>
                                <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[12px]" x-model="installTagsModal.workspace">
                                    <option>Default Workspace</option>
                                </select>
                            </label>
                        </div>
                        <div>
                            <p class="mb-[8px] text-[12px] font-semibold text-white">Create and configure the following tags in GTM</p>
                            <ul class="space-y-[6px]">
                                <template x-for="tag in installTagsModal.requiredTags" :key="tag.name">
                                    <li class="flex items-center justify-between gap-[8px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                                        <div>
                                            <p class="font-semibold" x-text="tag.name"></p>
                                            <p class="text-[10px] text-white/50" x-text="tag.meta"></p>
                                        </div>
                                        <span class="text-[10px] font-semibold text-emerald-300">Required</span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div class="text-[12px] text-white/70">
                            <p><span class="text-white/45">Trigger:</span> Initialization - All Pages</p>
                            <p class="mt-[4px]"><span class="text-white/45">Consent:</span> Require ad_storage and analytics_storage</p>
                        </div>
                        <div>
                            <p class="mb-[6px] text-[11px] font-semibold text-white/55">Generated dataLayer signal (example)</p>
                            <pre class="overflow-x-auto rounded-[8px] border border-white/10 bg-[#0a0a0a] p-[12px] font-mono text-[11px] text-emerald-200">window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
  event: 'clickronix_invalid_traffic',
  traffic_status: 'invalid',
  risk_confidence: 'high'
});</pre>
                        </div>
                    </div>
                </template>

                <template x-if="installTagsModal.tab === 'direct'">
                    <div class="space-y-[12px]">
                        <p class="text-[13px] text-white/80">Direct gtag install without GTM. Use only when Tag Manager is not available for the site.</p>
                        <a :href="trackingInstallation.setup_url || '#'" class="pi-primary-btn inline-flex no-underline">Open Direct Install guide →</a>
                    </div>
                </template>
            </div>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center justify-end gap-[8px] border-t border-white/15 bg-[#121212] px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeInstallTagsModal()">Cancel</button>
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="saveInstallTagsDraft()">Save draft</button>
            <a href="https://tagmanager.google.com/" target="_blank" rel="noopener noreferrer" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white no-underline">Preview in GTM</a>
        </footer>
    </div>
</div>
</template>
