{{-- Spec: Apply audience exclusion to Google Ads (separate from create) --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;"
     x-show="applyAudienceModal.open" x-cloak role="dialog" aria-modal="true"
     @click.self="closeApplyAudienceModal()"
     @keydown.escape.window="if (applyAudienceModal.open) closeApplyAudienceModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closeApplyAudienceModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[980px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[12px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Apply Google Ads audience exclusion</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Attach a negative audience to eligible campaigns / ad groups. Create and apply are separate steps.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closeApplyAudienceModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="pi-spec-modal-body space-y-[16px] px-[18px] py-[16px]">
            <section class="rounded-[10px] border px-[14px] py-[10px] text-[12px]"
                     :class="applyAudienceModal.ga4Present === true
                        ? 'border-emerald-400/35 bg-emerald-500/10 text-emerald-100'
                        : (applyAudienceModal.ga4Present === false
                            ? 'border-rose-400/35 bg-rose-500/10 text-rose-100'
                            : 'border-white/10 bg-[#0d0d0d] text-white/60')">
                <p class="font-semibold" x-text="applyAudienceModal.ga4Present === true
                    ? 'GA4/GTM on website — exclusion attach enabled'
                    : (applyAudienceModal.ga4Present === false
                        ? 'GA4/GTM missing on website — Apply is blocked'
                        : 'Checking GA4/GTM on website…')"></p>
                <p class="mt-[3px] text-[11px] opacity-90" x-show="applyAudienceModal.ga4Message" x-text="applyAudienceModal.ga4Message"></p>
            </section>

            <section class="rounded-[10px] border border-white/10 bg-[#0d0d0d] p-[14px]">
                <p class="mb-[8px] text-[11px] font-semibold uppercase text-white/50">Audience summary</p>
                <p class="text-[14px] font-semibold" x-text="applyAudienceModal.audienceName"></p>
                <div class="mt-[10px] grid gap-[8px] text-[12px] sm:grid-cols-4">
                    <div><span class="text-white/50">Source</span><p class="mt-[2px] font-medium" x-text="applyAudienceModal.source"></p></div>
                    <div><span class="text-white/50">Status</span>
                        <p class="mt-[2px] font-medium" :class="applyAudienceModal.status === 'Populating' ? 'text-amber-300' : 'text-emerald-300'" x-text="applyAudienceModal.status"></p>
                    </div>
                    <div><span class="text-white/50">Search size</span>
                        <p class="mt-[2px] font-medium text-amber-200" x-text="applyAudienceModal.searchSize"></p>
                    </div>
                    <div><span class="text-white/50">Display size</span>
                        <p class="mt-[2px] font-medium text-emerald-300" x-text="applyAudienceModal.displaySize"></p>
                    </div>
                </div>
            </section>

            <div class="overflow-x-auto rounded-[10px] border border-white/10">
                <table class="min-w-full text-left text-[12px]">
                    <thead class="bg-black/40 text-[11px] uppercase text-white/50">
                        <tr>
                            <th class="px-[12px] py-[10px]">Campaign</th>
                            <th class="px-[12px] py-[10px]">Type</th>
                            <th class="px-[12px] py-[10px]">Eligibility</th>
                            <th class="px-[12px] py-[10px]">Current state</th>
                            <th class="px-[12px] py-[10px]">Select</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr x-show="applyAudienceModal.loading" x-cloak>
                            <td colspan="5" class="px-[12px] py-[16px] text-white/55">Loading campaigns from Google Ads…</td>
                        </tr>
                        <tr x-show="!applyAudienceModal.loading && applyAudienceModal.campaigns.length === 0" x-cloak>
                            <td colspan="5" class="px-[12px] py-[16px] text-amber-200/90" x-text="applyAudienceModal.error || 'No campaigns found for the selected account.'"></td>
                        </tr>
                        <template x-for="row in applyAudienceModal.campaigns" :key="row.id">
                            <tr class="border-t border-white/10" x-show="!applyAudienceModal.loading">
                                <td class="px-[12px] py-[10px] font-medium" x-text="row.name"></td>
                                <td class="px-[12px] py-[10px] text-white/70" x-text="row.type"></td>
                                <td class="px-[12px] py-[10px]">
                                    <span :class="{
                                        'text-emerald-300': row.eligibility === 'Eligible',
                                        'text-amber-300': row.eligibility !== 'Eligible' && row.eligibility !== 'Unsupported',
                                        'text-rose-300': row.eligibility === 'Unsupported'
                                    }" x-text="row.eligibility"></span>
                                </td>
                                <td class="px-[12px] py-[10px] text-white/60" x-text="row.state"></td>
                                <td class="px-[12px] py-[10px]">
                                    <input type="checkbox" class="h-[14px] w-[14px] accent-[var(--brand-primary)]"
                                           :disabled="!row.canSelect"
                                           x-model="row.selected">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center gap-[18px] text-[12px]">
                <div class="flex items-center gap-[12px]">
                    <span class="text-white/55">Scope</span>
                    <label class="inline-flex items-center gap-[6px]"><input type="radio" value="campaign" x-model="applyAudienceModal.scope" class="accent-[var(--brand-primary)]"> Campaign</label>
                    <label class="inline-flex items-center gap-[6px]"><input type="radio" value="adgroup" x-model="applyAudienceModal.scope" class="accent-[var(--brand-primary)]"> Ad group</label>
                </div>
                <label class="inline-flex items-center gap-[8px]">
                    <span class="text-white/55">Safeguard: preserve existing audience settings</span>
                    <button type="button" class="relative h-[22px] w-[40px] rounded-full transition"
                            :class="applyAudienceModal.preserve ? 'bg-[var(--brand-primary)]' : 'bg-white/20'"
                            @click="applyAudienceModal.preserve = !applyAudienceModal.preserve">
                        <span class="absolute top-[2px] h-[18px] w-[18px] rounded-full bg-white transition"
                              :class="applyAudienceModal.preserve ? 'left-[20px]' : 'left-[2px]'"></span>
                    </button>
                </label>
            </div>

            <p class="text-[12px] text-white/70">
                Change preview:
                <strong class="text-white" x-text="applyAudienceSelectedCount + ' exclusion will be added'"></strong>
                · <span class="text-white/55">0 removed</span>
            </p>
            <p class="text-[11px] text-white/45">Apply finds or creates the Google Ads audience (user list), then <strong class="text-white/70">adds</strong> it as a negative exclusion on selected campaigns. Existing exclusion lists on those campaigns are left alone — we do not replace or override older lists. Membership still comes from <code class="text-white/70">clickronix_invalid_traffic</code> + Client ID when GA4/GTM is live.</p>
            <label class="block text-[12px]">
                <span class="text-white/55">Google Ads user list ID (optional — leave blank to auto-find / create by audience name)</span>
                <input type="text" x-model="applyAudienceModal.userListId"
                       class="mt-[6px] w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white"
                       placeholder="Auto if empty">
            </label>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closeApplyAudienceModal()">Cancel</button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="showMenuToast('Export preview coming soon.', 'info')">Export preview</button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white disabled:opacity-40"
                        :disabled="applyAudienceSelectedCount < 1 || !applyAudienceModal.sourceLinked || applyAudienceModal.applying || applyAudienceModal.ga4Present === false"
                        @click="applyEligibleAudienceExclusion()">
                    <span x-text="applyAudienceModal.applying
                        ? 'Attaching in Google Ads…'
                        : (applyAudienceModal.ga4Present === false ? 'Install GA4/GTM first' : 'Apply eligible exclusion')"></span>
                </button>
            </div>
        </footer>
    </div>
</div>
</template>
