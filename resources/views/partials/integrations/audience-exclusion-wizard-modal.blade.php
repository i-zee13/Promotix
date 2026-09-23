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
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/45"
                   x-text="'Step ' + String(wizardDisplayStepIndex + 1).padStart(2, '0') + ' / ' + wizardCurrentStepLabel"></p>
                <h2 class="mt-[2px] text-[18px] font-semibold" x-text="audienceWizard.titles[audienceWizard.step]"></h2>
                <p class="mt-[4px] text-[12px] text-white/55" x-text="audienceWizard.subtitles[audienceWizard.step]"></p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeAudienceWizard()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="shrink-0 border-b border-white/10 px-[22px] py-[12px]">
            <ol class="flex flex-wrap gap-[8px] text-[11px]">
                <template x-for="(stepItem, displayIdx) in wizardVisibleSteps" :key="'wiz-'+stepItem.id">
                    <li class="inline-flex items-center gap-[6px] rounded-full px-[10px] py-[4px]"
                        :class="audienceWizard.step === stepItem.id
                            ? 'bg-[var(--brand-primary)] text-white'
                            : (audienceWizard.step > stepItem.id ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/5 text-white/55')">
                        <span class="font-semibold" x-text="String(displayIdx + 1).padStart(2, '0')"></span>
                        <span x-text="stepItem.label"></span>
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
                            <div class="flex items-start justify-between gap-[8px]">
                                <div class="flex min-w-0 items-center gap-[10px]">
                                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-[8px] bg-white/5 ring-1 ring-white/10">
                                        <img src="{{ asset('images/google-ads.svg') }}" alt="" class="h-[20px] w-[20px]" width="20" height="20">
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-semibold">Google Ads</p>
                                        <p class="mt-[4px] font-mono text-[11px] text-white/55 truncate" x-text="googleAdsSummary.customer_id || '—'"></p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardAdsConnected ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/55'"
                                      x-text="wizardAdsConnected ? 'Account connected' : 'Not connected'"></span>
                            </div>
                            <button type="button" class="mt-[10px] rounded-[6px] border border-[var(--brand-primary)]/50 px-[10px] py-[5px] text-[11px] font-semibold text-[#ffd0b0] hover:bg-[var(--brand-primary)]/15"
                                    x-show="!wizardAdsConnected"
                                    @click="openConnectGoogleFromWizard()">Connect Google Ads</button>
                        </div>
                        <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[14px] py-[12px]">
                            <div class="flex items-start justify-between gap-[8px]">
                                <div class="flex min-w-0 items-center gap-[10px]">
                                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-[8px] bg-white/5 ring-1 ring-white/10">
                                        <img src="{{ asset('images/google-tag-manager.svg') }}" alt="" class="h-[20px] w-[20px]" width="20" height="20">
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-semibold">Google Tag Manager</p>
                                        <p class="mt-[4px] font-mono text-[11px] text-white/55 truncate" x-text="wizardGtmId || '—'"></p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardGtmConnected ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/55'"
                                      x-text="wizardGtmConnected ? 'Account connected' : 'Not connected'"></span>
                            </div>
                            <p class="mt-[6px] text-[10px] text-white/40">GTM is the container. GA4 route needs GTM + GA4 together.</p>
                            <button type="button" class="mt-[8px] rounded-[6px] border border-[var(--brand-primary)]/50 px-[10px] py-[5px] text-[11px] font-semibold text-[#ffd0b0] hover:bg-[var(--brand-primary)]/15"
                                    x-show="!wizardGtmConnected"
                                    @click="openInstallTagsFromWizard('gtm')">Connect GTM</button>
                        </div>
                        <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[14px] py-[12px]">
                            <div class="flex items-start justify-between gap-[8px]">
                                <div class="flex min-w-0 items-center gap-[10px]">
                                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-[8px] bg-white/5 ring-1 ring-white/10">
                                        <img src="{{ asset('images/google-analytics-4.svg') }}" alt="" class="h-[20px] w-[20px]" width="20" height="20">
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-semibold">Google Analytics 4</p>
                                        <p class="mt-[4px] font-mono text-[11px] text-white/55 truncate" x-text="wizardGa4Id || (createAudienceModal.ga4Checking ? 'Checking…' : '—')"></p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardGa4Connected ? 'bg-emerald-500/20 text-emerald-200' : (createAudienceModal.ga4Checking ? 'bg-amber-500/20 text-amber-200' : 'bg-white/10 text-white/55')"
                                      x-text="wizardGa4Connected ? 'Account connected' : (createAudienceModal.ga4Checking ? 'Checking…' : 'Not connected')"></span>
                            </div>
                            <p class="mt-[4px] text-[10px] text-[#ffd0b0]" x-show="wizardGa4Connected && !wizardGtmConnected">GA4 alone cannot power the GA4 audience route without GTM.</p>
                            <p class="mt-[4px] text-[10px] text-white/40" x-show="!wizardGa4Connected && wizardGtmConnected">If GA4 loads only through GTM, use Detect — we scan the published container for a G- ID.</p>
                            <div class="mt-[8px] flex flex-wrap gap-[6px]" x-show="!wizardGa4Connected">
                                <button type="button" class="rounded-[6px] border border-[var(--brand-primary)]/50 px-[10px] py-[5px] text-[11px] font-semibold text-[#ffd0b0] hover:bg-[var(--brand-primary)]/15"
                                        :disabled="createAudienceModal.ga4Checking"
                                        @click="wizardConnectGa4()">Detect GA4</button>
                                <button type="button" class="rounded-[6px] border border-white/25 px-[10px] py-[5px] text-[11px] text-white/70 hover:bg-white/5"
                                        @click="openInstallTagsFromWizard('gtm')">Install via GTM</button>
                            </div>
                        </div>
                        <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[14px] py-[12px]">
                            <div class="flex items-start justify-between gap-[8px]">
                                <div class="flex min-w-0 items-center gap-[10px]">
                                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-[8px] bg-white/5 ring-1 ring-white/10 text-white/80">
                                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 9l-3 3 3 3m8-6l3 3-3 3M13 5l-2 14"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-semibold">Website script</p>
                                        <p class="mt-[4px] text-[11px] text-white/55 truncate" x-text="wizardWebsiteHost || '—'"></p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                      :class="wizardScriptInstalled ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/55'"
                                      x-text="wizardScriptInstalled ? 'Installed' : 'Not installed'"></span>
                            </div>
                            <button type="button" class="mt-[10px] rounded-[6px] border border-[var(--brand-primary)]/50 px-[10px] py-[5px] text-[11px] font-semibold text-[#ffd0b0] hover:bg-[var(--brand-primary)]/15"
                                    x-show="!wizardScriptInstalled"
                                    @click="openInstallTagsFromWizard('script')">Install script</button>
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
                                <span class="rounded-full px-[7px] py-[1px] text-[9px] font-semibold"
                                      :class="wizardInvalidEventReady ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/50'"
                                      x-text="wizardInvalidEventReady ? 'Verified' : 'Not tested'"></span>
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
                            <div class="flex items-center gap-[8px]">
                                <img src="{{ asset('images/google-analytics-4.svg') }}" alt="" class="h-[18px] w-[18px]" width="18" height="18">
                                <p class="text-[13px] font-semibold">GA4 audience</p>
                            </div>
                            <p class="mt-[6px] text-[11px] text-white/60">Use GA4 audience from your linked property. Requires GTM container + GA4.</p>
                            <p class="mt-[8px] text-[10px] text-rose-300" x-show="!wizardGtmConnected">Blocked until GTM is connected.</p>
                        </button>
                        <button type="button" class="rounded-[10px] border p-[14px] text-left"
                                :class="audienceWizard.source === 'website' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/10' : 'border-white/15 bg-[#0d0d0d]'"
                                @click="audienceWizard.source = 'website'">
                            <div class="flex items-center gap-[8px]">
                                <img src="{{ asset('images/google-ads.svg') }}" alt="" class="h-[18px] w-[18px]" width="18" height="18">
                                <p class="text-[13px] font-semibold">Google Ads website audience</p>
                            </div>
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
                        <label class="block text-[11px] sm:col-span-2"><span class="mb-[4px] block text-white/55">Rule mode</span>
                            <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.matchMode">
                                <option value="any">Match ANY (OR)</option>
                                <option value="all">Match ALL (AND)</option>
                            </select>
                        </label>
                        <div class="sm:col-span-2 space-y-[8px]">
                            <div class="flex items-center justify-between gap-[8px]">
                                <span class="text-[11px] text-white/55">Exclusion conditions</span>
                                <button type="button"
                                        class="inline-flex items-center rounded-[5px] bg-[var(--brand-primary)] px-[9px] py-[4px] text-[11px] font-semibold text-white hover:opacity-90"
                                        @click="addAudienceRuleCondition()">+ Add condition</button>
                            </div>
                            <template x-for="(row, idx) in audienceWizard.ruleConditions" :key="'aw-rule-'+idx">
                                <div class="grid grid-cols-12 items-center gap-[6px] rounded-[7px] border border-white/15 bg-[#0a0a0a] px-[8px] py-[7px]">
                                    <select class="ae-field col-span-5 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]"
                                            x-model="row.param" @change="syncAudienceRuleOps(row)">
                                        <template x-for="p in (audienceWizard.ruleCatalog?.parameters || [])" :key="p.param">
                                            <option :value="p.param" x-text="p.label"></option>
                                        </template>
                                    </select>
                                    <select class="ae-field col-span-2 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]" x-model="row.op">
                                        <template x-for="op in audienceRuleOpsFor(row.param)" :key="op">
                                            <option :value="op" x-text="op"></option>
                                        </template>
                                    </select>
                                    <template x-if="(audienceRuleMeta(row.param)?.values || []).length">
                                        <select class="ae-field col-span-4 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]" x-model="row.value">
                                            <template x-for="v in (audienceRuleMeta(row.param)?.values || [])" :key="v">
                                                <option :value="v" x-text="v"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="!(audienceRuleMeta(row.param)?.values || []).length">
                                        <input class="ae-field col-span-4 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]" x-model="row.value" placeholder="Value">
                                    </template>
                                    <button type="button"
                                            class="col-span-1 inline-flex h-[28px] w-full items-center justify-center rounded-[5px] text-[#f87171] hover:bg-rose-500/15 hover:text-[#ef4444]"
                                            title="Remove condition"
                                            aria-label="Remove condition"
                                            @click="removeAudienceRuleCondition(idx)">
                                        <svg class="h-[15px] w-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6h18M8 6V4h8v2m-9 0v14a2 2 0 002 2h6a2 2 0 002-2V6M10 11v6M14 11v6"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <p class="rounded-[8px] border border-white/10 bg-[#0a0a0a] px-[10px] py-[8px] text-[11px] text-white/70" x-text="audienceRuleSummary()"></p>
                        </div>
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
                    <div x-show="audienceWizard.createError" x-cloak
                         class="rounded-[8px] border border-red-400/40 bg-red-500/15 px-[12px] py-[10px] text-[12px] text-red-100"
                         x-text="audienceWizard.createError"></div>
                </div>
                <aside class="space-y-[10px] rounded-[10px] border border-white/12 bg-[#0d0d0d] p-[14px] text-[12px]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/45">Route verification</p>
                    <div class="flex justify-between gap-[8px]"><span>GA4 access</span><span :class="wizardGa4Connected ? 'text-emerald-300' : 'text-white/45'" x-text="wizardGa4Connected ? 'Connected' : 'Pending'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>GTM container</span><span :class="wizardGtmConnected ? 'text-emerald-300' : 'text-rose-300'" x-text="wizardGtmConnected ? 'Connected' : 'Required'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>Google Ads link</span><span :class="wizardAdsConnected ? 'text-emerald-300' : 'text-white/45'" x-text="wizardAdsConnected ? 'Verified' : 'Pending'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>Audience creation</span><span :class="audienceWizard.ga4ListId ? 'text-emerald-300' : 'text-amber-300'" x-text="audienceWizard.ga4ListId ? 'Created' : 'Ready'"></span></div>
                    <div class="flex justify-between gap-[8px]"><span>Google Ads list</span><span class="text-amber-300" x-text="audienceWizard.ga4ListId ? ('List ' + audienceWizard.ga4ListId) : 'Awaiting creation'"></span></div>
                    <p x-show="audienceWizard.createError" x-cloak class="rounded-[8px] border border-red-400/40 bg-red-500/15 px-[10px] py-[8px] text-[11px] text-red-100" x-text="audienceWizard.createError"></p>
                    <p class="rounded-[8px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">After creation, wait for the shared Google Ads list before applying exclusions. New list is added — old lists are not replaced. Creating the audience does <strong class="text-white">not</strong> upload existing Device IDs; membership starts from future <code class="text-white/90">cr_invalid_traffic</code> browser events.</p>
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
                        <label class="block text-[11px] sm:col-span-2"><span class="mb-[4px] block text-white/55">Rule mode</span>
                            <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.matchMode">
                                <option value="any">Match ANY (OR)</option>
                                <option value="all">Match ALL (AND)</option>
                            </select>
                        </label>
                        <div class="sm:col-span-2 space-y-[8px]">
                            <div class="flex items-center justify-between gap-[8px]">
                                <span class="text-[11px] text-white/55">Exclusion conditions</span>
                                <button type="button"
                                        class="inline-flex items-center rounded-[5px] bg-[var(--brand-primary)] px-[9px] py-[4px] text-[11px] font-semibold text-white hover:opacity-90"
                                        @click="addAudienceRuleCondition()">+ Add condition</button>
                            </div>
                            <template x-for="(row, idx) in audienceWizard.ruleConditions" :key="'aw-web-rule-'+idx">
                                <div class="grid grid-cols-12 items-center gap-[6px] rounded-[7px] border border-white/15 bg-[#0a0a0a] px-[8px] py-[7px]">
                                    <select class="ae-field col-span-5 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]"
                                            x-model="row.param" @change="syncAudienceRuleOps(row)">
                                        <template x-for="p in (audienceWizard.ruleCatalog?.parameters || [])" :key="'w-'+p.param">
                                            <option :value="p.param" x-text="p.label"></option>
                                        </template>
                                    </select>
                                    <select class="ae-field col-span-2 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]" x-model="row.op">
                                        <template x-for="op in audienceRuleOpsFor(row.param)" :key="'wop-'+op">
                                            <option :value="op" x-text="op"></option>
                                        </template>
                                    </select>
                                    <template x-if="(audienceRuleMeta(row.param)?.values || []).length">
                                        <select class="ae-field col-span-4 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]" x-model="row.value">
                                            <template x-for="v in (audienceRuleMeta(row.param)?.values || [])" :key="'wv-'+v">
                                                <option :value="v" x-text="v"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="!(audienceRuleMeta(row.param)?.values || []).length">
                                        <input class="ae-field col-span-4 rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[8px] py-[7px] text-[11px]" x-model="row.value" placeholder="Value">
                                    </template>
                                    <button type="button"
                                            class="col-span-1 inline-flex h-[28px] w-full items-center justify-center rounded-[5px] text-[#f87171] hover:bg-rose-500/15 hover:text-[#ef4444]"
                                            title="Remove condition"
                                            aria-label="Remove condition"
                                            @click="removeAudienceRuleCondition(idx)">
                                        <svg class="h-[15px] w-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6h18M8 6V4h8v2m-9 0v14a2 2 0 002 2h6a2 2 0 002-2V6M10 11v6M14 11v6"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <p class="rounded-[8px] border border-white/10 bg-[#0a0a0a] px-[10px] py-[8px] text-[11px] text-white/70" x-text="audienceRuleSummary()"></p>
                        </div>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/55">Membership duration</span>
                            <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="audienceWizard.duration">
                                <option>30 days</option>
                                <option>60 days</option>
                                <option>90 days</option>
                            </select>
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

            {{-- STEP 04: Verify & exclude — only the route the user came from --}}
            <div x-show="audienceWizard.step === 3" class="space-y-[16px]">
                <div class="grid gap-[8px] sm:grid-cols-4">
                    <template x-for="m in audiencePipelineMetrics()" :key="m.label">
                        <div class="rounded-[10px] border border-[var(--brand-primary)]/35 bg-[var(--brand-primary)]/10 px-[12px] py-[10px] text-[12px]">
                            <p class="text-white/50" x-text="m.label"></p>
                            <p class="mt-[4px] font-semibold" :class="m.ok ? 'text-emerald-300' : 'text-white/60'" x-text="m.value"></p>
                        </div>
                    </template>
                </div>

                <div class="grid gap-[8px] sm:grid-cols-3">
                    <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <p class="text-white/50">Account access</p>
                        <p class="mt-[4px] font-semibold" :class="wizardAdsConnected ? 'text-emerald-300' : 'text-white/60'" x-text="wizardAdsConnected ? 'Connected' : 'Pending'"></p>
                    </div>
                    <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <p class="text-white/50">Route</p>
                        <p class="mt-[4px] font-semibold text-white" x-text="audienceWizard.source === 'website' ? 'Ads route' : 'GA4 route'"></p>
                    </div>
                    <div class="rounded-[10px] border border-white/12 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <p class="text-white/50" x-text="audienceWizard.source === 'website' ? 'Website list' : 'GA4 list'"></p>
                        <p class="mt-[4px] font-mono text-[11px]"
                           x-text="(audienceWizard.source === 'website' ? audienceWizard.websiteListId : audienceWizard.ga4ListId)
                               ? ('List ' + (audienceWizard.source === 'website' ? audienceWizard.websiteListId : audienceWizard.ga4ListId))
                               : '—'"></p>
                    </div>
                </div>

                <p class="rounded-[8px] border border-white/10 bg-[#0a0a0a] px-[10px] py-[8px] text-[11px] text-white/70" x-text="audienceRuleSummary()"></p>

                <div class="overflow-x-auto rounded-[10px] border border-white/12">
                    <table class="ae-verify-table min-w-full text-left text-[12px]">
                        <thead class="bg-[var(--brand-primary)]/10 text-white/70">
                            <tr>
                                <th class="px-[12px] py-[8px] font-medium">Source</th>
                                <th class="px-[12px] py-[8px] font-medium">Audience</th>
                                <th class="px-[12px] py-[8px] font-medium">Google Ads list</th>
                                <th class="px-[12px] py-[8px] font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/85">
                            <tr class="border-t border-white/10" x-show="audienceWizard.source === 'ga4'">
                                <td class="px-[12px] py-[10px]">GA4</td>
                                <td class="px-[12px] py-[10px]" x-text="audienceWizard.ga4Name"></td>
                                <td class="px-[12px] py-[10px] font-mono" x-text="audienceWizard.ga4ListId ? ('List ' + audienceWizard.ga4ListId) : '—'"></td>
                                <td class="px-[12px] py-[10px]">
                                    <span class="inline-flex rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                          :class="{
                                              'bg-emerald-500/20 text-emerald-200': wizardAttachmentTone('ga4') === 'ok',
                                              'bg-amber-500/20 text-amber-200': wizardAttachmentTone('ga4') === 'warn',
                                              'bg-white/10 text-white/55': wizardAttachmentTone('ga4') === 'muted',
                                          }"
                                          x-text="wizardAttachmentLabel('ga4')"></span>
                                </td>
                            </tr>
                            <tr class="border-t border-white/10" x-show="audienceWizard.source === 'website'">
                                <td class="px-[12px] py-[10px]">Google Ads website</td>
                                <td class="px-[12px] py-[10px]" x-text="audienceWizard.websiteName"></td>
                                <td class="px-[12px] py-[10px] font-mono" x-text="audienceWizard.websiteListId ? ('List ' + audienceWizard.websiteListId) : '—'"></td>
                                <td class="px-[12px] py-[10px]">
                                    <span class="inline-flex rounded-full px-[8px] py-[2px] text-[10px] font-semibold"
                                          :class="{
                                              'bg-emerald-500/20 text-emerald-200': wizardAttachmentTone('website') === 'ok',
                                              'bg-amber-500/20 text-amber-200': wizardAttachmentTone('website') === 'warn',
                                              'bg-white/10 text-white/55': wizardAttachmentTone('website') === 'muted',
                                          }"
                                          x-text="wizardAttachmentLabel('website')"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-[11px] text-white/55">Apply adds the selected list as a <strong class="text-white/80">new</strong> campaign exclusion. Existing exclusion lists on the campaign stay in place — nothing is overridden. Status wording = “Audience signal sent”, not individual Google membership.</p>
                <div class="flex flex-wrap gap-[8px]">
                    <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[16px] py-[8px] text-[13px] font-semibold disabled:opacity-40"
                            :disabled="audienceWizard.source === 'website' ? !audienceWizard.websiteListId : !audienceWizard.ga4ListId"
                            @click="wizardOpenApply()">Apply exclusions on campaigns →</button>
                    <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]"
                            :disabled="audienceWizard.creating"
                            x-show="audienceWizard.source === 'ga4' && !audienceWizard.ga4ListId"
                            @click="wizardCreateAudience('ga4')">Create GA4 list</button>
                    <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]"
                            :disabled="audienceWizard.creating"
                            x-show="audienceWizard.source === 'website' && !audienceWizard.websiteListId"
                            @click="wizardCreateAudience('website')">Create Ads list</button>
                    <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="checkGa4SiteStatus(false)">Refresh status</button>
                </div>
            </div>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]"
                    @click="audienceWizard.step === 0 ? closeAudienceWizard() : (audienceWizard.step === 3 ? wizardGoToStep(audienceWizard.source === 'website' ? 2 : 1) : audienceWizard.step--)">
                <span x-text="audienceWizard.step === 0 ? 'Cancel' : 'Back'"></span>
            </button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="openInstallTagsFromWizard('gtm')">Connect GTM</button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold disabled:opacity-40"
                        x-show="audienceWizard.step < 3"
                        :disabled="audienceWizard.creating || (audienceWizard.step === 0 && audienceWizard.source === 'ga4' && !wizardGtmConnected)"
                        @click="wizardNextStep()">
                    <span x-text="audienceWizard.creating ? 'Creating…' : wizardPrimaryCta"></span>
                </button>
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]"
                        x-show="audienceWizard.step === 0"
                        @click="audienceWizard.source = 'website'; wizardGoToStep(2)">Configure Ads route →</button>
            </div>
        </footer>
    </div>
</div>
</template>
