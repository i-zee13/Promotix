{{-- Audience Exclusion wizard (mockup steps 01–04) — opens from Connect / Audience Exclusion --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;"
     x-show="audienceWizard.open" x-cloak role="dialog" aria-modal="true"
     @click.self="closeAudienceWizard()"
     @keydown.escape.window="if (audienceWizard.open) closeAudienceWizard()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeAudienceWizard()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex max-h-[92vh] w-full max-w-[1120px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[12px] pt-[18px]">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/45" x-text="'Step 0' + (audienceWizard.step + 1) + ' / ' + audienceWizard.stepLabels[audienceWizard.step]"></p>
                <h2 class="mt-[2px] text-[18px] font-semibold" x-text="audienceWizard.titles[audienceWizard.step]"></h2>
                <p class="mt-[4px] text-[12px] text-white/55" x-text="audienceWizard.subtitles[audienceWizard.step]"></p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeAudienceWizard()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="shrink-0 border-b border-white/10 px-[22px] py-[12px]">
            <ol class="flex flex-wrap gap-[8px] text-[11px]">
                <template x-for="(label, idx) in audienceWizard.stepLabels" :key="'wiz-'+label">
                    <li class="inline-flex items-center gap-[6px] rounded-full px-[10px] py-[4px]"
                        :class="audienceWizard.step === idx ? 'bg-[var(--brand-primary)] text-white' : (audienceWizard.step > idx ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/5 text-white/55')">
                        <span class="font-semibold" x-text="String(idx + 1).padStart(2, '0')"></span>
                        <span x-text="label"></span>
                    </li>
                </template>
            </ol>
        </div>

        <div class="pi-spec-modal-body min-h-0 flex-1 overflow-y-auto px-[18px] py-[16px]">
            {{-- STEP 01: Connections --}}
            <div x-show="audienceWizard.step === 0" class="space-y-[18px]">
                <section>
                    <h3 class="mb-[10px] text-[13px] font-semibold">1. Connect accounts</h3>
                    <div class="grid gap-[10px] sm:grid-cols-2">
                        <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[14px] py-[12px]">
                            <div class="flex items-center justify-between gap-[8px]">
                                <p class="text-[13px] font-semibold">Google Ads</p>
                                <span class="rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardAdsConnected ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/55'"
                                      x-text="wizardAdsConnected ? 'Account connected' : 'Not connected'"></span>
                            </div>
                            <p class="mt-[6px] font-mono text-[11px] text-white/55" x-text="googleAdsSummary.customer_id || '—'"></p>
                        </div>
                        <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[14px] py-[12px]">
                            <div class="flex items-center justify-between gap-[8px]">
                                <p class="text-[13px] font-semibold">Google Tag Manager</p>
                                <span class="rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardGtmConnected ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/55'"
                                      x-text="wizardGtmConnected ? 'Account connected' : 'Not connected'"></span>
                            </div>
                            <p class="mt-[6px] font-mono text-[11px] text-white/55" x-text="wizardGtmId || '—'"></p>
                            <p class="mt-[4px] text-[10px] text-white/40">GTM is the container. GA4 route needs GTM + GA4 together.</p>
                        </div>
                        <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[14px] py-[12px]">
                            <div class="flex items-center justify-between gap-[8px]">
                                <p class="text-[13px] font-semibold">Google Analytics 4</p>
                                <span class="rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardGa4Connected ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/55'"
                                      x-text="wizardGa4Connected ? 'Account connected' : 'Not connected'"></span>
                            </div>
                            <p class="mt-[6px] font-mono text-[11px] text-white/55" x-text="wizardGa4Id || '—'"></p>
                            <p class="mt-[4px] text-[10px] text-[#ffd0b0]" x-show="wizardGa4Connected && !wizardGtmConnected">GA4 alone cannot power the GA4 audience route without GTM.</p>
                        </div>
                        <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[14px] py-[12px]">
                            <div class="flex items-center justify-between gap-[8px]">
                                <p class="text-[13px] font-semibold">Website script</p>
                                <span class="rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardScriptInstalled ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/55'"
                                      x-text="wizardScriptInstalled ? 'Installed' : 'Not installed'"></span>
                            </div>
                            <p class="mt-[6px] text-[11px] text-white/55" x-text="wizardWebsiteHost || '—'"></p>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="mb-[10px] text-[13px] font-semibold">2. Confirm delivery</h3>
                    <div class="grid gap-[10px] rounded-[10px] border border-white/12 bg-[#0d0d0d] p-[12px] sm:grid-cols-3">
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Website</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#121212] px-[10px] py-[8px]" :value="wizardWebsiteHost" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Delivery method</span>
                            <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#121212] px-[10px] py-[8px]" x-model="audienceWizard.delivery">
                                <option value="gtm">Google Tag Manager</option>
                                <option value="direct">Direct gtag</option>
                            </select>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Published container</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#121212] px-[10px] py-[8px]" :value="wizardGtmId || '—'" readonly>
                        </label>
                    </div>
                    <div class="mt-[10px] grid gap-[8px] sm:grid-cols-3">
                        <div class="rounded-[8px] border border-white/10 bg-[#0a0a0a] px-[10px] py-[8px] text-[11px]">
                            <div class="flex items-center justify-between gap-[6px]">
                                <span class="text-white/70">Container published</span>
                                <span class="rounded-full px-[7px] py-[1px] text-[9px] font-semibold" :class="wizardGtmConnected ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/50'" x-text="wizardGtmConnected ? 'Verified' : 'Pending'"></span>
                            </div>
                        </div>
                        <div class="rounded-[8px] border border-white/10 bg-[#0a0a0a] px-[10px] py-[8px] text-[11px]">
                            <div class="flex items-center justify-between gap-[6px]">
                                <span class="text-white/70">Invalid traffic event</span>
                                <span class="rounded-full bg-white/10 px-[7px] py-[1px] text-[9px] font-semibold text-white/50">Not tested</span>
                            </div>
                        </div>
                        <div class="rounded-[8px] border border-white/10 bg-[#0a0a0a] px-[10px] py-[8px] text-[11px]">
                            <div class="flex items-center justify-between gap-[6px]">
                                <span class="text-white/70">GA4 to Google Ads link</span>
                                <span class="rounded-full px-[7px] py-[1px] text-[9px] font-semibold" :class="wizardAdsConnected && wizardGa4Connected ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/50'" x-text="wizardAdsConnected && wizardGa4Connected ? 'Verified' : 'Pending'"></span>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="mb-[10px] text-[13px] font-semibold">3. Choose source</h3>
                    <div class="grid gap-[10px] sm:grid-cols-2">
                        <button type="button" class="rounded-[10px] border p-[14px] text-left"
                                :class="audienceWizard.source === 'ga4' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/10' : 'border-white/15 bg-[#0d0d0d]'"
                                @click="audienceWizard.source = 'ga4'">
                            <p class="text-[13px] font-semibold">GA4 audience</p>
                            <p class="mt-[6px] text-[11px] text-white/60">Use GA4 audience from your linked property. Requires GTM container + GA4.</p>
                            <p class="mt-[8px] text-[10px] text-rose-300" x-show="!wizardGtmConnected">Blocked until GTM is connected.</p>
                        </button>
                        <button type="button" class="rounded-[10px] border p-[14px] text-left"
                                :class="audienceWizard.source === 'website' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/10' : 'border-white/15 bg-[#0d0d0d]'"
                                @click="audienceWizard.source = 'website'">
                            <p class="text-[13px] font-semibold">Google Ads website audience</p>
                            <p class="mt-[6px] text-[11px] text-white/60">Send fraud signals to Google Ads. Can also be delivered via GTM.</p>
                        </button>
                    </div>
                </section>
            </div>

            {{-- STEP 02: GA4 route --}}
            <div x-show="audienceWizard.step === 1" class="grid gap-[16px] lg:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.75fr)]">
                <div class="space-y-[12px]">
                    <p class="text-[12px] text-white/60">Set up the details for your GA4 invalid-traffic audience. GTM delivers the event into GA4.</p>
                    <div class="grid gap-[10px] sm:grid-cols-2">
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Audience source</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" value="Google Analytics 4" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Delivery method</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" value="Google Tag Manager" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">GA4 measurement</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" :value="wizardGa4Id || '—'" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Event name</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.eventName">
                        </label>
                        <label class="block text-[11px] sm:col-span-2"><span class="mb-[4px] block text-white/55">Audience name</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.ga4Name">
                        </label>
                        <label class="block text-[11px] sm:col-span-2"><span class="mb-[4px] block text-white/55">Inclusion rule</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" value="event_name equals clickronix_invalid_traffic" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Membership duration</span>
                            <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.duration">
                                <option>30 days</option>
                                <option>60 days</option>
                                <option>90 days</option>
                            </select>
                        </label>
                    </div>
                    <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[9px] text-[13px] font-semibold disabled:opacity-40"
                            :disabled="!wizardGtmConnected || audienceWizard.creating"
                            @click="wizardCreateAudience('ga4')">
                        <span x-text="audienceWizard.creating ? 'Creating…' : 'Create GA4 audience'"></span>
                    </button>
                </div>
                <aside class="space-y-[10px] rounded-[10px] border border-white/12 bg-[#0d0d0d] p-[14px] text-[12px]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/45">Route verification</p>
                    <div class="flex justify-between gap-[8px]"><span>GA4 access</span><span :class="wizardGa4Connected ? 'text-emerald-300' : 'text-white/45'" x-text="wizardGa4Connected ? 'Connected' : 'Pending'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>GTM container</span><span :class="wizardGtmConnected ? 'text-emerald-300' : 'text-rose-300'" x-text="wizardGtmConnected ? 'Connected' : 'Required'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>Google Ads link</span><span :class="wizardAdsConnected ? 'text-emerald-300' : 'text-white/45'" x-text="wizardAdsConnected ? 'Verified' : 'Pending'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>Audience creation</span><span :class="audienceWizard.ga4ListId ? 'text-emerald-300' : 'text-amber-300'" x-text="audienceWizard.ga4ListId ? 'Created' : 'Ready'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>Google Ads list</span><span class="text-amber-300" x-text="audienceWizard.ga4ListId ? ('List ' + audienceWizard.ga4ListId) : 'Awaiting creation'"></span></div>
                    <p class="rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">After creation, wait for the shared Google Ads list before applying exclusions. New list is added — old lists are not replaced.</p>
                </aside>
            </div>

            {{-- STEP 03: Ads / website route --}}
            <div x-show="audienceWizard.step === 2" class="grid gap-[16px] lg:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.75fr)]">
                <div class="space-y-[12px]">
                    <p class="text-[12px] text-white/60">Send fraud signals directly to Google Ads. Can be delivered via GTM. Creates a <strong class="text-white">separate</strong> list from the GA4 route.</p>
                    <div class="grid gap-[10px] sm:grid-cols-2">
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Audience source</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" value="Google Ads website" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Delivery method</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" value="Google Tag Manager" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Tag destination</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" :value="trackingInstallation.google_tag?.id || googleAdsSummary.google_tag_id || 'AW-…'" readonly>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">GTM trigger</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.eventName">
                        </label>
                        <label class="block text-[11px] sm:col-span-2"><span class="mb-[4px] block text-white/55">Audience name</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.websiteName">
                        </label>
                        <label class="block text-[11px] sm:col-span-2"><span class="mb-[4px] block text-white/55">Audience rule</span>
                            <input type="text" class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" value="clickronix_invalid equals true" readonly>
                        </label>
                    </div>
                    <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[9px] text-[13px] font-semibold disabled:opacity-40"
                            :disabled="audienceWizard.creating"
                            @click="wizardCreateAudience('website')">
                        <span x-text="audienceWizard.creating ? 'Creating…' : 'Create website audience →'"></span>
                    </button>
                </div>
                <aside class="space-y-[10px] rounded-[10px] border border-white/12 bg-[#0d0d0d] p-[14px] text-[12px]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/45">Verification</p>
                    <div class="flex justify-between gap-[8px]"><span>Google Ads access</span><span :class="wizardAdsConnected ? 'text-emerald-300' : 'text-white/45'" x-text="wizardAdsConnected ? 'Connected' : 'Pending'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>Website audience</span><span :class="audienceWizard.websiteListId ? 'text-emerald-300' : 'text-amber-300'" x-text="audienceWizard.websiteListId ? 'Created' : 'Ready to create'"></span></div>
                    <p class="rounded-[8px] border border-white/15 bg-[#0a0a0a] px-[10px] py-[8px] text-[11px] text-white/65">This route creates a <strong class="text-white">separate</strong> Google Ads list. It does not override the GA4 list or older exclusions.</p>
                </aside>
            </div>

            {{-- STEP 04: Verify & exclude --}}
            <div x-show="audienceWizard.step === 3" class="space-y-[16px]">
                <div class="grid gap-[8px] sm:grid-cols-4">
                    <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <p class="text-white/50">Account access</p>
                        <p class="mt-[4px] font-semibold" :class="wizardAdsConnected ? 'text-emerald-300' : 'text-white/60'" x-text="wizardAdsConnected ? 'Connected' : 'Pending'"></p>
                    </div>
                    <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <p class="text-white/50">Audience lists</p>
                        <p class="mt-[4px] font-semibold text-white" x-text="wizardListCount + ' found'"></p>
                    </div>
                    <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <p class="text-white/50">GA4 list</p>
                        <p class="mt-[4px] font-mono text-[11px]" x-text="audienceWizard.ga4ListId ? ('List ' + audienceWizard.ga4ListId) : '—'"></p>
                    </div>
                    <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <p class="text-white/50">Website list</p>
                        <p class="mt-[4px] font-mono text-[11px]" x-text="audienceWizard.websiteListId ? ('List ' + audienceWizard.websiteListId) : '—'"></p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-[10px] border border-white/12">
                    <table class="min-w-full text-left text-[12px]">
                        <thead class="bg-white/5 text-white/55">
                            <tr>
                                <th class="px-[12px] py-[8px] font-medium">Source</th>
                                <th class="px-[12px] py-[8px] font-medium">Audience</th>
                                <th class="px-[12px] py-[8px] font-medium">Google Ads list</th>
                                <th class="px-[12px] py-[8px] font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-white/10">
                                <td class="px-[12px] py-[10px]">GA4</td>
                                <td class="px-[12px] py-[10px]" x-text="audienceWizard.ga4Name"></td>
                                <td class="px-[12px] py-[10px] font-mono" x-text="audienceWizard.ga4ListId ? ('List ' + audienceWizard.ga4ListId) : '—'"></td>
                                <td class="px-[12px] py-[10px]" :class="audienceWizard.ga4ListId ? 'text-emerald-300' : 'text-white/45'" x-text="audienceWizard.ga4ListId ? 'Created' : 'Missing'"></td>
                            </tr>
                            <tr class="border-t border-white/10">
                                <td class="px-[12px] py-[10px]">Google Ads website</td>
                                <td class="px-[12px] py-[10px]" x-text="audienceWizard.websiteName"></td>
                                <td class="px-[12px] py-[10px] font-mono" x-text="audienceWizard.websiteListId ? ('List ' + audienceWizard.websiteListId) : '—'"></td>
                                <td class="px-[12px] py-[10px]" :class="audienceWizard.websiteListId ? 'text-emerald-300' : 'text-white/45'" x-text="audienceWizard.websiteListId ? 'Created' : 'Missing'"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-[11px] text-white/55">Apply adds the selected list as a <strong class="text-white/80">new</strong> campaign exclusion. Existing exclusion lists on the campaign stay in place — nothing is overridden.</p>
                <div class="flex flex-wrap gap-[8px]">
                    <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[16px] py-[8px] text-[13px] font-semibold"
                            :disabled="!audienceWizard.ga4ListId && !audienceWizard.websiteListId"
                            @click="wizardOpenApply()">Apply exclusions on campaigns →</button>
                    <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="checkGa4SiteStatus(false)">Refresh status</button>
                </div>
            </div>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]"
                    @click="audienceWizard.step === 0 ? closeAudienceWizard() : audienceWizard.step--">
                <span x-text="audienceWizard.step === 0 ? 'Cancel' : 'Back'"></span>
            </button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="openInstallTagsModal('gtm')">Connect GTM</button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold disabled:opacity-40"
                        x-show="audienceWizard.step < 3"
                        :disabled="audienceWizard.step === 0 && audienceWizard.source === 'ga4' && !wizardGtmConnected"
                        @click="wizardNextStep()">
                    <span x-text="wizardPrimaryCta"></span>
                </button>
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]"
                        x-show="audienceWizard.step === 0"
                        @click="audienceWizard.source = 'website'; wizardGoToStep(2)">Configure Ads route →</button>
            </div>
        </footer>
    </div>
</div>
</template>
