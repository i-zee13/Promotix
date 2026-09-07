{{-- Spec: Create invalid-traffic audience (Source → Rule → Validate → Apply) --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;"
     x-show="createAudienceModal.open" x-cloak role="dialog" aria-modal="true"
     @click.self="closeCreateAudienceModal()"
     @keydown.escape.window="if (createAudienceModal.open) closeCreateAudienceModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeCreateAudienceModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[1040px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[12px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Create invalid-traffic audience</h2>
                <p class="mt-[4px] text-[12px] text-white/55">GA4 custom-event audience — not a conversion, not an IP list.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeCreateAudienceModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="shrink-0 border-b border-white/10 px-[22px] py-[12px]">
            <ol class="flex flex-wrap gap-[8px] text-[11px]">
                <template x-for="(step, idx) in createAudienceModal.steps" :key="step">
                    <li class="inline-flex items-center gap-[6px] rounded-full px-[10px] py-[4px]"
                        :class="createAudienceModal.step === idx ? 'bg-[var(--brand-primary)] text-white' : (createAudienceModal.step > idx ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/5 text-white/55')">
                        <span class="font-semibold" x-text="(idx + 1)"></span>
                        <span x-text="step"></span>
                    </li>
                </template>
            </ol>
        </div>

        <div class="pi-spec-modal-body grid gap-0 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,0.75fr)]">
            <div class="space-y-[16px] border-b border-white/10 px-[18px] py-[16px] lg:border-b-0 lg:border-r">
                <section>
                    <p class="mb-[8px] text-[12px] font-semibold text-white">1. Source</p>
                    <div class="grid gap-[10px] sm:grid-cols-2">
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">GA4 property</span>
                            <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="createAudienceModal.ga4_property">
                                <option value="">Select GA4 property</option>
                                <template x-for="p in createAudienceModal.ga4Options" :key="p.id">
                                    <option :value="p.id" x-text="p.label"></option>
                                </template>
                            </select>
                        </label>
                        <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Linked Google Ads account</span>
                            <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="createAudienceModal.ads_account">
                                <option value="">Select Customer ID</option>
                                <template x-for="a in createAudienceModal.adsOptions" :key="a.id">
                                    <option :value="a.id" x-text="a.label"></option>
                                </template>
                            </select>
                        </label>
                    </div>
                </section>

                <section>
                    <p class="mb-[8px] text-[12px] font-semibold text-white">2. Audience details</p>
                    <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Audience name</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="createAudienceModal.name">
                        <span class="mt-[4px] block text-[10px] text-white/45">Must be unique within this Google Ads account. Versionable: keep v1 / v2.</span>
                    </label>
                </section>

                <section>
                    <p class="mb-[8px] text-[12px] font-semibold text-white">3. Audience definition</p>
                    <p class="mb-[6px] text-[11px] text-white/55">Include users when</p>
                    <div class="space-y-[6px]">
                        <template x-for="(rule, idx) in createAudienceModal.includeRules" :key="'inc-'+idx">
                            <div class="flex flex-wrap items-center gap-[6px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[8px] py-[8px] text-[11px]">
                                <span class="rounded bg-white/10 px-[6px] py-[3px]" x-text="rule.field"></span>
                                <span class="text-white/40" x-show="rule.param" x-text="rule.param"></span>
                                <span class="text-white/50" x-text="rule.op"></span>
                                <code class="text-[#ffd0b0]" x-text="rule.value"></code>
                                <span class="ml-auto text-[10px] font-semibold text-white/40" x-show="idx < createAudienceModal.includeRules.length - 1">AND</span>
                            </div>
                        </template>
                    </div>
                    <p class="mb-[6px] mt-[12px] text-[11px] text-white/55">Exclude users when</p>
                    <div class="space-y-[6px]">
                        <template x-for="(rule, idx) in createAudienceModal.excludeRules" :key="'exc-'+idx">
                            <div class="flex flex-wrap items-center gap-[6px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[8px] py-[8px] text-[11px]">
                                <span class="rounded bg-white/10 px-[6px] py-[3px]" x-text="rule.field"></span>
                                <span class="text-white/50" x-text="rule.op"></span>
                                <code class="text-[#ffd0b0]" x-text="rule.value"></code>
                            </div>
                        </template>
                    </div>
                </section>

                <section class="grid gap-[10px] sm:grid-cols-2">
                    <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Membership duration</span>
                        <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="createAudienceModal.duration">
                            <option>7 days</option>
                            <option>14 days</option>
                            <option>30 days</option>
                            <option>60 days</option>
                            <option>90 days</option>
                        </select>
                        <span class="mt-[4px] block text-[10px] text-white/45">Users remain for the selected duration — not forever.</span>
                    </label>
                    <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Evaluation</span>
                        <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="createAudienceModal.evaluation">
                            <option>User scoped from first matching event</option>
                            <option>User scoped — any matching event</option>
                        </select>
                    </label>
                </section>
            </div>

            <aside class="space-y-[14px] px-[18px] py-[16px] text-[12px]">
                <div>
                    <p class="mb-[8px] text-[11px] font-semibold uppercase text-white/50">5. Test evidence</p>
                    <ul class="space-y-[8px]">
                        <template x-for="ev in createAudienceModal.evidence" :key="ev.key">
                            <li class="flex items-start gap-[8px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[10px] py-[8px]">
                                <span class="mt-[2px] inline-flex h-[16px] w-[16px] shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                      :class="ev.ok ? 'bg-emerald-500/25 text-emerald-300' : 'bg-white/10 text-white/40'"
                                      x-text="ev.ok ? '✓' : '·'"></span>
                                <div class="min-w-0">
                                    <p class="font-medium text-white" x-text="ev.label"></p>
                                    <p class="text-[11px] text-white/55" x-text="ev.detail"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <button type="button" class="mt-[10px] text-[11px] font-semibold text-[#ffd0b0] hover:underline" @click="simulateAudienceTestEvidence()">Refresh / mark safe test evidence</button>
                </div>
                <p class="rounded-[8px] border border-[var(--brand-primary)]/40 bg-[var(--brand-primary)]/10 px-[10px] py-[8px] text-[11px] text-[#ffd0b0]">
                    Important: This audience can take 24–48 hours to populate after creation. Definitions cannot be edited after creation — use a new version when changes are required.
                </p>
                <p class="rounded-[8px] border border-rose-400/35 bg-rose-500/10 px-[10px] py-[8px] text-[11px] text-rose-100">
                    Critical: Audience event is <strong>not</strong> a conversion. Never fire as Lead / Purchase / Qualified Lead.
                </p>
            </aside>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeCreateAudienceModal()">Cancel</button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="saveCreateAudienceDraft()">Save draft</button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white disabled:opacity-40"
                        :disabled="!createAudienceReady"
                        @click="createGa4Audience()">
                    Create GA4 audience
                </button>
            </div>
        </footer>
    </div>
</div>
</template>
