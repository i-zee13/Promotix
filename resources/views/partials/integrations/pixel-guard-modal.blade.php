{{-- Spec Image 7: Google Ads Pixel Guard --}}
<template x-teleport="body">
<div class="pi-spec-modal-root" style="position:fixed;inset:0;z-index:2147483000;display:none;"
     x-show="pixelGuardModal.open" x-cloak role="dialog" aria-modal="true"
     @click.self="closePixelGuardModal()"
     @keydown.escape.window="if (pixelGuardModal.open) closePixelGuardModal()">
    <div class="pi-spec-modal-backdrop absolute inset-0 bg-black/80" @click="closePixelGuardModal()"></div>
    <div class="pi-spec-modal-panel relative z-[1] flex w-full max-w-[980px] flex-col overflow-hidden rounded-[12px] border border-white/20 bg-[#121212] text-white shadow-2xl" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-[12px] border-b border-white/15 px-[22px] pb-[12px] pt-[22px]">
            <div>
                <h2 class="text-[18px] font-semibold">Google Ads Pixel Guard</h2>
                <p class="mt-[4px] text-[12px] text-white/55">Gate real conversions by traffic verdict. Invalid → suppress conversion; audience signal stays a separate non-conversion event.</p>
            </div>
            <button type="button" class="rounded p-[6px] text-white/60 hover:bg-white/10" @click="closePixelGuardModal()" aria-label="Close">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="flex shrink-0 flex-wrap gap-[4px] border-b border-white/10 px-[16px] pt-[8px]">
            <template x-for="tab in pixelGuardModal.tabs" :key="tab.id">
                <button type="button" class="rounded-t-[8px] px-[14px] py-[10px] text-[12px] font-semibold"
                    :class="pixelGuardModal.tab === tab.id ? 'bg-[var(--brand-primary)] text-white' : 'text-white/55 hover:text-white'"
                    @click="pixelGuardModal.tab = tab.id" x-text="tab.label"></button>
            </template>
        </div>

        <div class="pi-spec-modal-body px-[18px] py-[16px]">
            <div class="space-y-[16px]" x-show="pixelGuardModal.tab === 'mapping'">
                <div class="grid gap-[12px] sm:grid-cols-2">
                    <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Google Ads conversion action</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="pixelGuardModal.conversion_action" placeholder="Qualified Lead">
                    </label>
                    <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Google Tag ID</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] font-mono" x-model="pixelGuardModal.google_tag_id" placeholder="AW-…">
                    </label>
                    <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Conversion label</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="pixelGuardModal.conversion_label">
                    </label>
                    <label class="block text-[11px]"><span class="mb-[4px] block text-white/60">Trigger source</span>
                        <select class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="pixelGuardModal.trigger_source">
                            <option>Server-accepted-lead</option>
                            <option>Form submit (client)</option>
                            <option>Purchase confirmation</option>
                        </select>
                    </label>
                    <label class="block text-[11px] sm:col-span-2"><span class="mb-[4px] block text-white/60">Unique event key</span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px]" x-model="pixelGuardModal.unique_key" placeholder="lead_id">
                    </label>
                </div>
            </div>

            <div class="space-y-[14px]" x-show="pixelGuardModal.tab === 'policy'" x-cloak>
                <div class="overflow-x-auto rounded-[10px] border border-white/10">
                    <table class="min-w-full text-left text-[11px]">
                        <thead class="bg-black/40 text-white/50">
                            <tr>
                                <th class="px-[10px] py-[8px]">Input state</th>
                                <th class="px-[10px] py-[8px]">Conversion action</th>
                                <th class="px-[10px] py-[8px]">Audience signal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-white/10"><td class="px-[10px] py-[8px]">Valid + real server accepted lead</td><td class="px-[10px] py-[8px] text-emerald-300">Send once</td><td class="px-[10px] py-[8px]">No invalid event</td></tr>
                            <tr class="border-t border-white/10"><td class="px-[10px] py-[8px]">Confirmed invalid + real action</td><td class="px-[10px] py-[8px] text-rose-300">Suppress</td><td class="px-[10px] py-[8px]">Send once if consent</td></tr>
                            <tr class="border-t border-white/10"><td class="px-[10px] py-[8px]">Pending decision</td><td class="px-[10px] py-[8px] text-amber-300">Wait max ~800–900 ms</td><td class="px-[10px] py-[8px]">No</td></tr>
                            <tr class="border-t border-white/10"><td class="px-[10px] py-[8px]">Timeout</td><td class="px-[10px] py-[8px]">Fail-open + review</td><td class="px-[10px] py-[8px]">No</td></tr>
                            <tr class="border-t border-white/10"><td class="px-[10px] py-[8px]">Duplicate lead_id</td><td class="px-[10px] py-[8px]">Suppress duplicate</td><td class="px-[10px] py-[8px]">Do not duplicate signal</td></tr>
                            <tr class="border-t border-white/10"><td class="px-[10px] py-[8px]">No real business action</td><td class="px-[10px] py-[8px]">No conversion</td><td class="px-[10px] py-[8px]">Invalid signal only if confirmed</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-white/50">Required IDs: conversion action, AW-… tag, conversion label, unique business key (lead_id/order_id/call_id), decision_id. Dedup by business key — not IP/fingerprint.</p>
                <ul class="space-y-[8px] text-[12px]">
                    <template x-for="row in pixelGuardModal.policyRows" :key="row.verdict">
                        <li class="flex flex-wrap items-center justify-between gap-[8px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px]">
                            <span class="inline-flex items-center gap-[8px]">
                                <span class="inline-flex h-[20px] w-[20px] items-center justify-center rounded-full text-[11px] font-bold"
                                      :class="{
                                        'bg-emerald-500/25 text-emerald-300': row.tone === 'ok',
                                        'bg-rose-500/25 text-rose-300': row.tone === 'bad',
                                        'bg-amber-500/25 text-amber-300': row.tone === 'wait',
                                        'bg-white/10 text-white/55': row.tone === 'neutral'
                                      }" x-text="row.icon"></span>
                                <span class="font-medium" x-text="row.verdict"></span>
                            </span>
                            <span class="text-white/65" x-text="row.action"></span>
                        </li>
                    </template>
                </ul>
                <div class="rounded-[10px] border border-white/10 bg-[#0d0d0d] p-[14px]">
                    <div class="flex flex-wrap items-center justify-between gap-[10px]">
                        <div>
                            <p class="text-[13px] font-semibold">Send invalid traffic audience signal</p>
                            <p class="mt-[4px] text-[11px] text-white/55">Non-conversion event when traffic is confirmed invalid.</p>
                        </div>
                        <button type="button" class="relative h-[22px] w-[40px] rounded-full transition"
                                :class="pixelGuardModal.sendAudienceSignal ? 'bg-[var(--brand-primary)]' : 'bg-white/20'"
                                @click="pixelGuardModal.sendAudienceSignal = !pixelGuardModal.sendAudienceSignal">
                            <span class="absolute top-[2px] h-[18px] w-[18px] rounded-full bg-white transition"
                                  :class="pixelGuardModal.sendAudienceSignal ? 'left-[20px]' : 'left-[2px]'"></span>
                        </button>
                    </div>
                    <label class="mt-[12px] block text-[11px]"><span class="mb-[4px] block text-white/60">Event name <span class="ml-[6px] rounded bg-white/10 px-[6px] py-[1px] text-[9px] uppercase text-white/55">Non-conversion event</span></span>
                        <input class="ae-field w-full rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] font-mono" x-model="pixelGuardModal.audience_event" :disabled="!pixelGuardModal.sendAudienceSignal">
                    </label>
                </div>
            </div>

            <div class="space-y-[10px]" x-show="pixelGuardModal.tab === 'tests'" x-cloak>
                <template x-for="t in pixelGuardModal.tests" :key="t.label">
                    <div class="flex items-center justify-between gap-[8px] rounded-[8px] border border-white/10 bg-[#0d0d0d] px-[12px] py-[10px] text-[12px]">
                        <span x-text="t.label"></span>
                        <span class="inline-flex items-center gap-[6px] rounded-full px-[8px] py-[3px] text-[10px] font-semibold"
                              :class="t.ok ? 'bg-emerald-500/20 text-emerald-300' : 'bg-white/10 text-white/50'"
                              x-text="t.ok ? 'Passed' : 'Pending'"></span>
                    </div>
                </template>
                <button type="button" class="text-[11px] font-semibold text-[#ffd0b0] hover:underline" @click="runPixelGuardTests()">Re-run Pixel Guard tests</button>
            </div>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-[8px] border-t border-white/15 px-[22px] py-[14px]">
            <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="closePixelGuardModal()">Cancel</button>
            <div class="flex flex-wrap gap-[8px]">
                <button type="button" class="rounded-[6px] border border-white/30 px-[16px] py-[8px] text-[13px]" @click="savePixelGuardPolicy()">Save policy</button>
                <button type="button" class="rounded-[6px] bg-[var(--brand-primary)] px-[18px] py-[8px] text-[13px] font-semibold text-white" @click="activatePixelGuard()">Activate Pixel Guard</button>
            </div>
        </footer>
    </div>
</div>
</template>
