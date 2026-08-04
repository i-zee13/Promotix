@extends('layouts.admin')

@section('title', 'Paid Advertising | Advanced View')

@section('header-toolbar')
    @include('partials.paid-marketing-header-timezone')
@endsection

@section('content')
@php
    $initialReportingTz = $reportingTimezone ?? \App\Support\UserTimezone::reportingTimezoneForUser(auth()->user(), $googleAccountTimezone ?? null);
@endphp
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="paidMarketingDetailed(@js([
    'reportingTimezone' => $initialReportingTz,
    'googleAccountTimezone' => $googleAccountTimezone ?? null,
    'domainCatalog' => $domainCatalog ?? [],
    'reportingMode' => $reportingMode ?? 'profile',
    'profileTimezone' => $profileTimezone ?? \App\Support\UserTimezone::forUser(auth()->user()),
    'overrideUrl' => route('paid-marketing.detailed-override'),
    'bulkUrl' => route('paid-marketing.detailed-bulk'),
    'csrf' => csrf_token(),
]))" x-init="init()">
    <section class="mx-auto w-full px-[12px] pb-[20px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <style>
            .pm-adv-kpi-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 18px;
            }
            @media (min-width: 768px) {
                .pm-adv-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            }
            @media (min-width: 1200px) {
                .pm-adv-kpi-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
            }
            .pm-adv-kpi-card {
                display: flex;
                flex-direction: column;
                min-height: 148px;
                border-radius: 10px;
                border: 1px solid rgba(103, 6, 179, 0.55);
                background: #111111;
                padding: 14px 14px 12px;
            }
            .pm-adv-kpi-card__icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 28px;
                border-radius: 7px;
                margin-bottom: 10px;
            }
            .pm-adv-kpi-card__icon.is-purple { background: rgba(100, 0, 178, 0.28); color: #c4b5fd; }
            .pm-adv-kpi-card__icon.is-green { background: rgba(34, 197, 94, 0.18); color: #86efac; }
            .pm-adv-kpi-card__icon.is-rose { background: rgba(244, 63, 94, 0.18); color: #fda4af; }
            .pm-adv-kpi-card__icon.is-amber { background: rgba(245, 158, 11, 0.18); color: #fcd34d; }
            .pm-adv-kpi-card__label {
                font-size: 11px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.55);
                line-height: 1.25;
                margin-bottom: 8px;
            }
            .pm-adv-kpi-card__value {
                font-size: 26px;
                font-weight: 700;
                color: #fff;
                line-height: 1.1;
                letter-spacing: -0.02em;
            }
            .pm-adv-kpi-card__sub {
                margin-top: auto;
                padding-top: 10px;
                font-size: 10px;
                color: rgba(255, 255, 255, 0.42);
            }
            .figma-filter-bar--pm-adv {
                overflow: visible;
                width: fit-content;
                max-width: 100%;
            }
            .figma-filter-bar--pm-adv > label {
                flex: 0 0 auto;
            }
            @media (max-width: 900px) {
                .figma-filter-bar--pm-adv {
                    width: 100%;
                    flex-wrap: wrap;
                }
            }
            .pm-adv-charts {
                display: grid;
                grid-template-columns: 1fr;
                gap: 14px;
            }
            @media (min-width: 900px) {
                .pm-adv-charts { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            }
            .pm-adv-chart-card {
                display: flex;
                flex-direction: column;
                min-height: 280px;
                border-radius: 10px;
                border: 1px solid rgba(103, 6, 179, 0.55);
                background: #111111;
                padding: 16px 16px 12px;
            }
            .pm-adv-chart-card__title {
                margin: 0 0 14px;
                font-size: 14px;
                font-weight: 600;
                color: #fff;
            }
            .pm-adv-chart-card__body {
                display: flex;
                align-items: center;
                gap: 14px;
                flex: 1;
                min-width: 0;
            }
            .pm-adv-donut {
                --pm-donut: conic-gradient(rgba(100,0,178,0.25) 0 100%);
                width: 118px;
                height: 118px;
                border-radius: 999px;
                background: var(--pm-donut);
                display: grid;
                place-items: center;
                flex-shrink: 0;
            }
            .pm-adv-donut__inner {
                width: 78px;
                height: 78px;
                border-radius: 999px;
                background: #111111;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                padding: 4px;
            }
            .pm-adv-donut__value {
                font-size: 16px;
                font-weight: 700;
                color: #fff;
                line-height: 1.1;
            }
            .pm-adv-donut__label {
                margin-top: 2px;
                font-size: 9px;
                color: rgba(255, 255, 255, 0.45);
                line-height: 1.2;
            }
            .pm-adv-legend {
                list-style: none;
                margin: 0;
                padding: 0;
                min-width: 0;
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 7px;
            }
            .pm-adv-legend li {
                display: grid;
                grid-template-columns: 10px minmax(0, 1fr) auto;
                align-items: center;
                gap: 8px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.82);
            }
            .pm-adv-legend__swatch {
                width: 10px;
                height: 10px;
                border-radius: 2px;
            }
            .pm-adv-legend__name {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .pm-adv-legend__meta {
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
                color: rgba(255, 255, 255, 0.7);
            }
            .pm-adv-countries {
                display: flex;
                flex-direction: column;
                gap: 12px;
                flex: 1;
                padding-top: 4px;
            }
            .pm-adv-country-row {
                display: grid;
                grid-template-columns: 22px minmax(72px, 0.9fr) minmax(0, 1.4fr) auto;
                align-items: center;
                gap: 8px;
            }
            .pm-adv-country-row__flag { font-size: 14px; line-height: 1; }
            .pm-adv-country-row__name {
                font-size: 12px;
                color: rgba(255, 255, 255, 0.88);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .pm-adv-country-row__track {
                height: 6px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.08);
                overflow: hidden;
            }
            .pm-adv-country-row__bar {
                display: block;
                height: 100%;
                border-radius: 999px;
                background: #6400B2;
            }
            .pm-adv-country-row__meta {
                font-size: 11px;
                color: rgba(255, 255, 255, 0.75);
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }
            .pm-adv-chart-card__updated {
                margin: 14px 0 0;
                text-align: right;
                font-size: 10px;
                color: rgba(255, 255, 255, 0.38);
            }
            .pm-adv-hip {
                margin-top: 18px;
            }
            .pm-adv-hip__head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 12px;
            }
            .pm-adv-hip__title {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #fff;
            }
            .pm-adv-hip__nav {
                display: flex;
                gap: 8px;
            }
            .pm-adv-hip__btn {
                display: grid;
                place-items: center;
                width: 32px;
                height: 32px;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: #1a1a1a;
                color: rgba(255, 255, 255, 0.75);
                cursor: pointer;
                transition: background .15s ease, color .15s ease, border-color .15s ease;
            }
            .pm-adv-hip__btn:hover {
                background: #222;
                color: #fff;
                border-color: rgba(100, 0, 178, 0.55);
            }
            .pm-adv-hip__btn:disabled {
                opacity: 0.35;
                cursor: default;
            }
            .pm-adv-hip__track {
                display: flex;
                gap: 12px;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                scroll-behavior: smooth;
                padding-bottom: 6px;
                -webkit-overflow-scrolling: touch;
            }
            .pm-adv-hip__track::-webkit-scrollbar {
                height: 4px;
            }
            .pm-adv-hip__track::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.18);
                border-radius: 999px;
            }
            .pm-adv-hip-card {
                flex: 0 0 min(220px, 78vw);
                scroll-snap-align: start;
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding: 16px 14px 14px;
                border-radius: 10px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                background: #161616;
                text-align: left;
                cursor: pointer;
                transition: border-color .15s ease, background .15s ease;
            }
            .pm-adv-hip-card:hover {
                border-color: rgba(100, 0, 178, 0.55);
                background: #1a1a1a;
            }
            .pm-adv-hip-card__ip {
                margin: 0;
                font-size: 18px;
                font-weight: 700;
                color: #fff;
                letter-spacing: 0.01em;
                font-variant-numeric: tabular-nums;
            }
            .pm-adv-hip-card__risk {
                margin: 0;
                font-size: 13px;
                font-weight: 600;
            }
            .pm-adv-hip-card__risk--high { color: #F43F5E; }
            .pm-adv-hip-card__risk--medium { color: #F59E0B; }
            .pm-adv-hip-card__badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                width: fit-content;
                padding: 4px 10px 4px 8px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 255, 255, 0.08);
                font-size: 11px;
                font-weight: 500;
                color: rgba(255, 255, 255, 0.88);
            }
            .pm-adv-hip-card__dot {
                width: 7px;
                height: 7px;
                border-radius: 999px;
                flex-shrink: 0;
            }
            .pm-adv-hip-card__meta {
                margin: 2px 0 0;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.45);
            }
            .pm-adv-hip-card__ago {
                margin: 0;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.38);
            }
            .pm-adv-hip__empty {
                margin: 0;
                padding: 22px 12px;
                text-align: center;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.4);
                border-radius: 10px;
                border: 1px dashed rgba(255, 255, 255, 0.12);
                background: #111;
            }
            /* Click Details modal: wider, not full-page */
            .figma-modal-overlay {
                align-items: center;
                padding: max(28px, env(safe-area-inset-top)) 28px max(28px, env(safe-area-inset-bottom));
            }
            .figma-modal--click-details {
                width: min(1100px, 90vw);
                max-width: min(1100px, 90vw);
                max-height: min(780px, 82dvh);
            }
            @media (min-width: 900px) {
                .figma-modal--click-details .figma-click-modal-compact {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 12px 22px;
                }
            }
            @media (max-width: 520px) {
                .pm-adv-chart-card__body { flex-direction: column; align-items: flex-start; }
                .pm-adv-country-row {
                    grid-template-columns: 22px minmax(0, 1fr) auto;
                    grid-template-areas:
                        "flag name meta"
                        "track track track";
                }
                .pm-adv-country-row__flag { grid-area: flag; }
                .pm-adv-country-row__name { grid-area: name; }
                .pm-adv-country-row__meta { grid-area: meta; }
                .pm-adv-country-row__track { grid-area: track; }
            }
        </style>

        <div class="mb-[18px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[8px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Advanced View</span>
            </div>

            <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--pm-adv ml-auto flex min-h-[54px] w-fit max-w-full flex-nowrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_2px_10px_rgba(0,0,0,.35)]">
                <label class="flex w-[150px] shrink-0 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.domain_id" @change="onDomainChange()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Domains</option>
                            @foreach ($domains as $domain)
                                <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[118px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.traffic_source" @change="scheduleFetch(true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="google_ads">Google Ads</option>
                            <option value="meta_ads" disabled>Meta Ads</option>
                            <option value="microsoft_ads" disabled>Microsoft Ads</option>
                        </select>
                    </div>
                </label>
                <label class="flex w-[150px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Google Ads Account</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.google_ads_account_id" @change="scheduleFetch(true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Accounts</option>
                            @foreach (($googleAdsAccounts ?? []) as $account)
                                <option value="{{ $account->id }}">{{ $account->displayLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="relative flex w-[140px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]" @click.outside="campaignMenuOpen = false">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                    <button type="button" @click="openCampaignMenu()" class="figma-filter-select-wrap flex h-[23px] w-full items-center rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[22px] text-left text-[11px] text-[#8c8787]">
                        <span class="truncate" x-text="filters.campaign || 'All Campaigns'"></span>
                    </button>
                    <div x-show="campaignMenuOpen" x-cloak class="paid-advanced-campaign-menu promotix-slim-scroll !left-[8px] !right-auto !min-w-[180px]">
                        <button type="button" @click="selectCampaign('')" class="paid-advanced-campaign-option" :class="!filters.campaign && 'is-active'">All Campaigns</button>
                        <template x-for="name in campaignOptions" :key="name">
                            <button type="button" @click="selectCampaign(name)" class="paid-advanced-campaign-option" :class="filters.campaign === name && 'is-active'" x-text="name"></button>
                        </template>
                    </div>
                </label>
                <label class="flex w-[128px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleFetch()" placeholder="All Pages" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        {{-- KPI cards (mockup row 3) — no second filter toolbar row --}}
        <div class="pm-adv-kpi-grid">
            <template x-for="card in kpiCards" :key="card.key">
                <article class="pm-adv-kpi-card">
                    <span class="pm-adv-kpi-card__icon" :class="'is-' + (card.tone || 'purple')" aria-hidden="true">
                        <template x-if="card.key === 'total'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V5m4 14V9m4 10V7m4 12v-6m4 6V4"/></svg>
                        </template>
                        <template x-if="card.key === 'valid'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        <template x-if="card.key === 'invalid'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        </template>
                        <template x-if="card.key === 'blocked'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v5c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V6l8-3z"/></svg>
                        </template>
                        <template x-if="card.key === 'waste'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        </template>
                        <template x-if="card.key === 'risk'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                    </span>
                    <p class="pm-adv-kpi-card__label" x-text="card.label"></p>
                    <p class="pm-adv-kpi-card__value" x-text="card.value"></p>
                    <p class="pm-adv-kpi-card__sub" x-text="card.sub"></p>
                </article>
            </template>
            <template x-if="kpiCards.length === 0">
                <article class="pm-adv-kpi-card col-span-full min-h-[80px] items-center justify-center text-center text-[12px] text-white/45" style="grid-column: 1 / -1;">
                    Loading metrics…
                </article>
            </template>
        </div>

        <section class="overflow-visible rounded-[12px] border border-[#6706b3]">
            <div class="flex flex-wrap items-center justify-between gap-[10px] overflow-visible rounded-t-[12px] bg-[#6400B2] px-[16px] py-[12px]">
                <h2 class="text-[18px] font-normal text-white sm:text-[20px]">Advanced View</h2>
                <div class="flex flex-1 flex-wrap items-center justify-end gap-[10px]">
                    <label class="relative flex h-[28px] min-w-[200px] max-w-[280px] flex-1 items-center rounded-[6px] bg-white px-[10px]">
                        <svg class="mr-[6px] h-[14px] w-[14px] shrink-0 text-[#8c8787]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" placeholder="Search for IP Address" x-model="filters.ip" @input="scheduleFetch(true)" class="w-full border-0 bg-transparent text-[11px] text-[#121212] placeholder:text-[#8c8787] focus:ring-0">
                    </label>
                    <div class="relative" @click.outside="dataFilterMenuOpen = false">
                        <button type="button" @click="dataFilterMenuOpen = !dataFilterMenuOpen; filterMenuOpen = false" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            Filters
                            <span class="rounded-[3px] bg-white/15 px-[5px] text-[10px]" x-text="activeDataFilterCount"></span>
                        </button>
                        <div x-show="dataFilterMenuOpen" x-cloak class="paid-advanced-filters-menu promotix-slim-scroll">
                            <div class="mb-[8px] flex items-center justify-between gap-[8px]">
                                <p class="text-[10px] font-semibold uppercase text-white/55">Data filters</p>
                                <button type="button" class="text-[10px] font-semibold text-[#c084fc] hover:underline" @click="clearDataFilters()">Clear all</button>
                            </div>
                            <div class="grid grid-cols-1 gap-[8px] sm:grid-cols-2">
                                <label class="paid-advanced-filter-field">
                                    <span>Country</span>
                                    <input type="text" x-model="filters.country" @input="scheduleFetch()" placeholder="e.g. US / Pakistan">
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Keyword</span>
                                    <input type="text" x-model="filters.keyword" @input="scheduleFetch()" placeholder="Search keyword">
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Ad group</span>
                                    <input type="text" x-model="filters.ad_group" @input="scheduleFetch()" placeholder="Ad group / sub-campaign">
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Source / platform</span>
                                    <input type="text" x-model="filters.source" @input="scheduleFetch()" placeholder="Platform / source">
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Browser</span>
                                    <input type="text" x-model="filters.browser" @input="scheduleFetch()" placeholder="Chrome, Safari…">
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Device / OS</span>
                                    <input type="text" x-model="filters.device" @input="scheduleFetch()" placeholder="Windows, iOS…">
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Detection result</span>
                                    <select x-model="filters.detection" @change="scheduleFetch(true)">
                                        <option value="">All</option>
                                        <option value="valid">Valid</option>
                                        <option value="invalid">Invalid</option>
                                        <option value="vpn">VPN</option>
                                        <option value="proxy">Proxy</option>
                                        <option value="data_center">Data center</option>
                                        <option value="malicious">Malicious</option>
                                    </select>
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Threat group</span>
                                    <input type="text" x-model="filters.threat_group" @input="scheduleFetch()" placeholder="Exact/partial threat">
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Risk level</span>
                                    <select x-model="filters.risk_level" @change="scheduleFetch(true)">
                                        <option value="">All</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </label>
                                <label class="paid-advanced-filter-field">
                                    <span>Block status</span>
                                    <select x-model="filters.block_status" @change="scheduleFetch(true)">
                                        <option value="">All</option>
                                        <option value="blocked">Blocked</option>
                                        <option value="allowed">Not blocked</option>
                                    </select>
                                </label>
                            </div>
                            <p class="mt-[8px] text-[9px] leading-snug text-white/40">Date range, campaign, path, and IP search also apply together. Clear all resets data filters only (keeps dates/domain).</p>
                        </div>
                    </div>
                    <div class="relative" @click.outside="filterMenuOpen = false">
                        <button type="button" @click="filterMenuOpen = !filterMenuOpen; dataFilterMenuOpen = false" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            Columns
                            <span class="rounded-[3px] bg-white/15 px-[5px] text-[10px]" x-text="visibleColumns.length"></span>
                        </button>
                        <div x-show="filterMenuOpen" x-cloak class="paid-advanced-columns-menu promotix-slim-scroll">
                            <p class="mb-[8px] text-[10px] font-semibold uppercase text-white/55">Primary columns</p>
                            <template x-for="col in columnCatalog.filter(c => c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option is-locked">
                                    <input type="checkbox" checked disabled>
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                            <p class="mb-[8px] mt-[10px] text-[10px] font-semibold uppercase text-white/55">Optional columns</p>
                            <p class="mb-[6px] text-[9px] leading-snug text-white/40">IP detection fields show PromoTix intel (VPN, proxy, risk). Enable below.</p>
                            <template x-for="col in columnCatalog.filter(c => !c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option">
                                    <input type="checkbox" :value="col.key" :checked="optionalColumnKeys.includes(col.key)" @change="toggleOptionalColumn(col.key)">
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <button type="button" x-show="activeDataFilterCount > 0 || filters.ip || filters.path || filters.campaign" x-cloak @click="clearAllFilters()" class="inline-flex h-[28px] items-center rounded-[6px] border border-white/30 px-[10px] text-[11px] text-white hover:bg-white/10">
                        Clear filters
                    </button>
                    <div class="relative" @click.outside="exportMenuOpen = false">
                        <button type="button" @click="exportMenuOpen = !exportMenuOpen" class="inline-flex h-[28px] items-center gap-[6px] text-[12px] font-medium text-white hover:underline">
                            <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v-1a4 4 0 014-4h0a4 4 0 014 4v1"/></svg>
                            Export
                        </button>
                        <div x-show="exportMenuOpen" x-cloak class="absolute right-0 top-[calc(100%+6px)] z-50 min-w-[140px] rounded-[8px] border border-white/20 bg-[#0f0e0e] p-[6px] shadow-lg">
                            <a :href="csvHref()" class="block rounded-[6px] px-[10px] py-[6px] text-[11px] text-white hover:bg-white/10">Download CSV</a>
                            <a :href="xlsxHref()" class="block rounded-[6px] px-[10px] py-[6px] text-[11px] text-white hover:bg-white/10">Download XLSX</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-[6px] border-b border-white/10 bg-[#101010] px-[16px] py-[8px]" x-show="activeFilterChips.length" x-cloak>
                <template x-for="chip in activeFilterChips" :key="chip.key">
                    <button type="button" class="inline-flex items-center gap-[4px] rounded-full border border-white/20 bg-white/5 px-[8px] py-[2px] text-[10px] text-white/85 hover:bg-white/10" @click="clearFilterChip(chip.key)">
                        <span x-text="chip.label"></span>
                        <span aria-hidden="true">×</span>
                    </button>
                </template>
            </div>

            <div class="flex flex-wrap gap-[6px] border-b border-white/10 bg-[#101010] px-[16px] py-[8px]" x-show="selectedIds.length" x-cloak>
                <span class="self-center text-[10px] text-white/55" x-text="selectedIds.length + ' selected'"></span>
                <button type="button" class="rounded border border-white/20 px-[8px] py-[3px] text-[10px] text-white/85 hover:bg-white/10" @click="bulkAction('valid')">Mark valid</button>
                <button type="button" class="rounded border border-white/20 px-[8px] py-[3px] text-[10px] text-white/85 hover:bg-white/10" @click="bulkAction('invalid')">Mark invalid</button>
                <button type="button" class="rounded border border-white/20 px-[8px] py-[3px] text-[10px] text-white/85 hover:bg-white/10" @click="bulkAction('allowed')">Allow</button>
                <button type="button" class="rounded border border-white/20 px-[8px] py-[3px] text-[10px] text-white/85 hover:bg-white/10" @click="bulkAction('blocked')">Block</button>
                <button type="button" class="rounded border border-white/20 px-[8px] py-[3px] text-[10px] text-white/55 hover:bg-white/10" @click="selectedIds = []">Clear</button>
                <span class="self-center text-[10px] text-white/40" x-text="bulkMessage"></span>
            </div>

            <div class="pm-adv-table-shell">
                <div class="pm-adv-table-x-scroll">
                    <div class="pm-adv-table-sync" :style="syncStyle">
                        <div class="pm-adv-table-grid pm-adv-table-grid--head text-[10px] font-medium uppercase tracking-wide text-[#a9a9a9] sm:text-[11px]" :style="gridStyle">
                            <label class="flex items-center justify-center">
                                <input type="checkbox" class="rounded border-white/30" :checked="allVisibleSelected" @change="toggleSelectAll($event.target.checked)">
                            </label>
                            <template x-for="col in visibleColumns" :key="'head-' + col.key">
                                <button
                                    type="button"
                                    class="promotix-sortable truncate"
                                    :class="sortClass(col.key)"
                                    :disabled="col.key === 'session_recording'"
                                    @click="setSort(col.key)"
                                    :title="'Sort by ' + col.label"
                                >
                                    <span class="truncate" x-text="col.label"></span>
                                    <span class="promotix-sortable-arrows" aria-hidden="true" x-show="col.key !== 'session_recording'">
                                        <span class="promotix-sortable-up">▲</span>
                                        <span class="promotix-sortable-down">▼</span>
                                    </span>
                                </button>
                            </template>
                        </div>

                        <div class="pm-adv-table-body-scroll">
                            <template x-for="visit in sortedRows" :key="visit.id">
                                <div class="pm-adv-table-grid pm-adv-table-grid--row cursor-pointer text-[10px] sm:text-[11px]" :style="gridStyle" @click="openClicks(visit)">
                                    <label class="flex items-center justify-center" @click.stop>
                                        <input type="checkbox" class="rounded border-white/30" :checked="selectedIds.includes(visit.id)" @change="toggleSelect(visit.id, $event.target.checked)">
                                    </label>
                                    <template x-for="col in visibleColumns" :key="visit.id + '-' + col.key">
                                        <template x-if="col.key !== 'session_recording'">
                                            <span class="truncate" :class="col.key === 'ip' && 'font-medium'" :title="cellValue(visit, col.key)" x-text="cellValue(visit, col.key)"></span>
                                        </template>
                                        <template x-if="col.key === 'session_recording'">
                                            <span class="flex items-center justify-center">
                                                <button type="button" x-show="visit.has_session_recording" @click.stop="openRecording(visit)" class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-full bg-[#6400B2] text-white hover:bg-[#7B13C8]" title="Watch session recording">
                                                    <svg class="h-[11px] w-[11px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                </button>
                                                <span x-show="!visit.has_session_recording" class="text-[#8c8787]">—</span>
                                            </span>
                                        </template>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!loading && sortedRows.length === 0" class="py-[24px] text-center text-[12px] text-[#a9a9a9]">No rows match your filters.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pm-adv-charts mt-[18px]">
            <article class="pm-adv-chart-card">
                <h3 class="pm-adv-chart-card__title">Threat Distribution</h3>
                <div class="pm-adv-chart-card__body">
                    <div class="pm-adv-donut" :style="`--pm-donut: ${chartThreat.gradient || 'conic-gradient(rgba(100,0,178,0.25) 0 100%)'}`">
                        <div class="pm-adv-donut__inner">
                            <p class="pm-adv-donut__value" x-text="chartThreat.total_label || '0'"></p>
                            <p class="pm-adv-donut__label" x-text="chartThreat.center_label || 'Invalid Clicks'"></p>
                        </div>
                    </div>
                    <ul class="pm-adv-legend">
                        <template x-for="item in (chartThreat.items || [])" :key="'threat-' + item.label">
                            <li>
                                <span class="pm-adv-legend__swatch" :style="`background:${item.color}`"></span>
                                <span class="pm-adv-legend__name" x-text="item.label"></span>
                                <span class="pm-adv-legend__meta">
                                    <span x-text="item.pct + '%'"></span>
                                    <span class="opacity-55" x-text="'(' + item.count_label + ')'"></span>
                                </span>
                            </li>
                        </template>
                        <li x-show="!(chartThreat.items || []).length" class="!text-white/40">No invalid threat data in range.</li>
                    </ul>
                </div>
                <p class="pm-adv-chart-card__updated" x-text="chartsUpdatedLabel"></p>
            </article>

            <article class="pm-adv-chart-card">
                <h3 class="pm-adv-chart-card__title">Risk Level Distribution</h3>
                <div class="pm-adv-chart-card__body">
                    <div class="pm-adv-donut" :style="`--pm-donut: ${chartRisk.gradient || 'conic-gradient(rgba(100,0,178,0.25) 0 100%)'}`">
                        <div class="pm-adv-donut__inner">
                            <p class="pm-adv-donut__value" x-text="chartRisk.total_label || '0'"></p>
                            <p class="pm-adv-donut__label" x-text="chartRisk.center_label || 'Unique IPs'"></p>
                        </div>
                    </div>
                    <ul class="pm-adv-legend">
                        <template x-for="item in (chartRisk.items || [])" :key="'risk-' + item.label">
                            <li>
                                <span class="pm-adv-legend__swatch" :style="`background:${item.color}`"></span>
                                <span class="pm-adv-legend__name" x-text="item.label"></span>
                                <span class="pm-adv-legend__meta">
                                    <span x-text="item.pct + '%'"></span>
                                    <span class="opacity-55" x-text="'(' + item.count_label + ')'"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <p class="pm-adv-chart-card__updated" x-text="chartsUpdatedLabel"></p>
            </article>

            <article class="pm-adv-chart-card">
                <h3 class="pm-adv-chart-card__title">Top Countries by Invalid Clicks</h3>
                <div class="pm-adv-countries">
                    <template x-for="row in chartCountries" :key="'country-' + row.name">
                        <div class="pm-adv-country-row">
                            <span class="pm-adv-country-row__flag" x-text="row.flag || '🌐'"></span>
                            <span class="pm-adv-country-row__name" x-text="row.name"></span>
                            <div class="pm-adv-country-row__track">
                                <span class="pm-adv-country-row__bar" :style="`width:${row.bar || 0}%`"></span>
                            </div>
                            <span class="pm-adv-country-row__meta">
                                <span x-text="row.count_label"></span>
                                <span class="opacity-55" x-text="'(' + row.pct + '%)'"></span>
                            </span>
                        </div>
                    </template>
                    <p x-show="chartCountries.length === 0" class="py-[18px] text-center text-[12px] text-white/40">No country invalid-click data in range.</p>
                </div>
                <p class="pm-adv-chart-card__updated" x-text="chartsUpdatedLabel"></p>
            </article>
        </section>

        <section class="pm-adv-hip">
            <div class="pm-adv-hip__head">
                <h2 class="pm-adv-hip__title">Recent High Risk IPs</h2>
                <div class="pm-adv-hip__nav" x-show="highRiskIps.length > 1">
                    <button type="button" class="pm-adv-hip__btn" @click="scrollHighRisk(-1)" aria-label="Previous high risk IPs">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="pm-adv-hip__btn" @click="scrollHighRisk(1)" aria-label="Next high risk IPs">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
            <div class="pm-adv-hip__track" x-ref="highRiskTrack" x-show="highRiskIps.length">
                <template x-for="card in highRiskIps" :key="'hip-' + (card.id || card.ip)">
                    <button type="button" class="pm-adv-hip-card" @click="openHighRiskIp(card)">
                        <p class="pm-adv-hip-card__ip" x-text="card.ip"></p>
                        <p class="pm-adv-hip-card__risk" :class="card.risk_tone === 'high' ? 'pm-adv-hip-card__risk--high' : 'pm-adv-hip-card__risk--medium'">
                            Risk: <span x-text="card.risk"></span>/100
                        </p>
                        <span class="pm-adv-hip-card__badge" :style="card.risk_tone === 'high' ? 'color:#F43F5E' : 'color:#F59E0B'">
                            <span class="pm-adv-hip-card__dot" :style="`background:${card.dot || '#F43F5E'}`"></span>
                            <span x-text="card.category"></span>
                        </span>
                        <p class="pm-adv-hip-card__meta" x-text="card.invalid_label"></p>
                        <p class="pm-adv-hip-card__ago" x-text="card.ago"></p>
                    </button>
                </template>
            </div>
            <p class="pm-adv-hip__empty" x-show="!loading && highRiskIps.length === 0">No high-risk IPs in this range.</p>
        </section>

        <div class="figma-modal-overlay"
             x-show="modal.open" x-cloak x-transition
             @keydown.escape.window="closeModal()" @click.self="closeModal()">
            <div class="figma-modal figma-modal--click-details">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="figma-modal-title">Click Details</h3>
                        <p class="mt-1 text-[11px] text-white/55">Same-IP activity timeline</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="mb-4 rounded-[8px] border border-white/15 bg-black/20 p-[10px]">
                    <button type="button" class="flex w-full items-center justify-between text-left" @click="modal.timelineOpen = !modal.timelineOpen">
                        <span class="text-[12px] font-semibold text-white">
                            IP timeline
                            <span class="ml-1 font-normal text-white/50" x-text="`(${modal.timeline.length} events)`"></span>
                        </span>
                        <span class="text-white/60" x-text="modal.timelineOpen ? '▾' : '▸'"></span>
                    </button>
                    <p class="mt-1 text-[10px] text-white/45" x-show="modal.timelineLoading">Loading same-IP activity…</p>
                    <div x-show="modal.timelineOpen" x-cloak class="mt-3 max-h-[220px] space-y-[8px] overflow-y-auto promotix-slim-scroll">
                        <template x-for="event in modal.timeline" :key="event.id">
                            <div class="rounded-[6px] border border-white/10 bg-white/5 px-[10px] py-[8px]">
                                <div class="flex flex-wrap items-center justify-between gap-[6px]">
                                    <span class="rounded-[4px] px-[6px] py-[1px] text-[9px] font-semibold uppercase"
                                          :class="event.type === 'click' ? 'bg-[#6400B2]/40 text-white' : 'bg-white/15 text-white/80'"
                                          x-text="event.type"></span>
                                    <span class="text-[10px] text-white/55" x-text="formatDateTime(event.at)"></span>
                                </div>
                                <p class="mt-1 truncate text-[11px] text-white" x-text="event.campaign || 'No campaign'"></p>
                                <p class="mt-1 text-[10px] text-white/60">
                                    <span x-text="event.device || 'Device n/a'"></span>
                                    · <span x-text="event.behavior || '—'"></span>
                                </p>
                                <p class="mt-1 text-[10px] text-white/75">
                                    Risk: <span class="font-semibold" x-text="event.risk_decision || '—'"></span>
                                    · Action: <span class="font-semibold" x-text="event.action || '—'"></span>
                                    <template x-if="event.threat_group"><span> · <span x-text="event.threat_group"></span></span></template>
                                </p>
                            </div>
                        </template>
                        <p x-show="!modal.timelineLoading && modal.timeline.length === 0" class="text-[11px] text-white/50">No other same-IP events in this date range.</p>
                    </div>
                </div>

                <div class="figma-click-modal-layout">
                    <aside class="figma-click-modal-sidebar">
                        <template x-for="(c, idx) in modal.clicks" :key="c.id ?? idx">
                            <button type="button"
                                    class="figma-click-modal-tab"
                                    :class="idx === modal.activeIndex ? 'is-active' : ''"
                                    @click="modal.activeIndex = idx">
                                <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                                <p class="text-xs text-white/50" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            </button>
                        </template>
                        <template x-if="modal.clicks.length === 0">
                            <p class="text-sm text-white/50">No clicks for this visit.</p>
                        </template>
                    </aside>

                    <div class="figma-click-modal-body" x-show="modal.clicks.length > 0">
                        <div class="mb-3 rounded-[8px] border border-white/15 bg-black/30 p-[12px]" x-show="modal.visit?.risk_summary">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/55">Traffic Risk</p>
                                    <p class="mt-1 text-[22px] font-semibold text-white">
                                        <span x-text="modal.visit?.risk_summary?.score ?? '—'"></span>
                                        <span class="text-[12px] font-normal text-white/50">/100</span>
                                    </p>
                                </div>
                                <span class="rounded px-[8px] py-[3px] text-[10px] font-semibold uppercase"
                                      :class="(modal.visit?.risk_summary?.level || '').toLowerCase() === 'high' ? 'bg-rose-500/25 text-rose-200' : ((modal.visit?.risk_summary?.level || '').toLowerCase() === 'medium' ? 'bg-amber-500/25 text-amber-100' : 'bg-emerald-500/20 text-emerald-200')"
                                      x-text="modal.visit?.risk_summary?.level || 'Low'"></span>
                            </div>
                            <p class="mt-2 text-[11px] text-white/70">
                                Status: <span class="font-semibold text-white" x-text="modal.visit?.risk_summary?.status || modal.visit?.status || '—'"></span>
                                · Needs block: <span class="font-semibold" x-text="modal.visit?.risk_summary?.needs_block ? 'Yes' : 'No'"></span>
                                · Connection: <span x-text="modal.visit?.risk_summary?.connection || modal.visit?.intel_connection_type || '—'"></span>
                            </p>
                            <ul class="mt-2 space-y-[3px] text-[10px] text-white/60">
                                <template x-for="reason in (modal.visit?.risk_summary?.reasons || [])" :key="reason">
                                    <li>✓ <span x-text="reason"></span></li>
                                </template>
                            </ul>
                            <p class="mt-2 text-[10px] text-white/45" x-show="modal.visit?.session_id || modal.visit?.device_fingerprint">
                                Session: <span class="font-mono text-white/70" x-text="modal.visit?.session_id || '—'"></span>
                                · Fingerprint: <span class="font-mono text-white/70" x-text="modal.visit?.device_fingerprint || '—'"></span>
                            </p>
                        </div>
                        <template x-if="activeClick">
                            <div class="figma-click-modal-fields">
                                <div class="figma-click-modal-compact">
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">IP</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(modal.visit?.ip || activeClick.ip)">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--mono" x-text="modal.visit?.ip || activeClick.ip || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">VPN Hits</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.vpn_hits > 0 ? modal.visit.vpn_hits : '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Data Center</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.data_center_hits > 0 ? modal.visit.data_center_hits : '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Invalid Clicks</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.invalid_clicks ?? 0"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Valid Clicks</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.valid_clicks ?? 0"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Browser</p>
                                        <p class="figma-modal-value" x-text="activeClick.browser_name || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Country</p>
                                        <p class="figma-modal-value inline-flex items-center gap-2">
                                            <img x-show="countryFlagUrl(activeClick.country || modal.visit?.country)"
                                                 :src="countryFlagUrl(activeClick.country || modal.visit?.country)"
                                                 :alt="countryLabel(activeClick.country || modal.visit?.country)"
                                                 class="h-[10px] w-[14px] shrink-0 rounded-[2px] object-cover"
                                                 loading="lazy">
                                            <span x-text="countryLabel(activeClick.country || modal.visit?.country)"></span>
                                        </p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Browser version</p>
                                        <p class="figma-modal-value" x-text="activeClick.browser_version || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Last Click</p>
                                        <p class="figma-modal-value" x-text="formatDateTime(activeClick.last_click_at || modal.visit?.last_click_at)"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">OS</p>
                                        <p class="figma-modal-value" x-text="activeClick.os || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Threat Group</p>
                                        <p class="figma-modal-value" x-text="activeClick.threat_group || modal.visit?.threat_group || 'N/A'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Domain</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.domain || activeClick.domain || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Campaign</p>
                                        <p class="figma-modal-value" x-text="activeClick.campaign || modal.visit?.campaign || 'N/A'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Risk decision</p>
                                        <p class="figma-modal-value">
                                            <span class="risk-badge" :class="modal.visit?.status_badge_class || ''" x-text="activeClick.risk_decision || modal.visit?.status || '—'"></span>
                                        </p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Final action</p>
                                        <p class="figma-modal-value" x-text="activeClick.action || modal.visit?.rule_explanation?.action || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Device</p>
                                        <p class="figma-modal-value" x-text="activeClick.device || modal.visit?.device || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Confidence</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.intel_confidence ?? '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Evidence</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.intel_evidence || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Risk score</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.intel_risk_score ?? '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Keyword</p>
                                        <p class="figma-modal-value" x-text="activeClick.keyword || 'N/A'"></p>
                                    </div>
                                </div>

                                <div class="figma-click-modal-wide">
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">Google Click ID (GCLID)</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.gclid || modal.visit?.gclid || activeClick.paid_id)" x-show="activeClick.gclid || modal.visit?.gclid || activeClick.paid_id">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--long" x-text="activeClick.gclid || modal.visit?.gclid || activeClick.paid_id || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">GBRAID</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.gbraid || modal.visit?.gbraid)" x-show="activeClick.gbraid || modal.visit?.gbraid">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--long" x-text="activeClick.gbraid || modal.visit?.gbraid || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">WBRAID</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.wbraid || modal.visit?.wbraid)" x-show="activeClick.wbraid || modal.visit?.wbraid">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--long" x-text="activeClick.wbraid || modal.visit?.wbraid || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">Path / behavior</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.path || modal.visit?.last_path)" x-show="activeClick.path || modal.visit?.last_path">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--long" x-text="activeClick.path || modal.visit?.last_path || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field figma-modal-field--full space-y-[8px]">
                                        <p class="figma-modal-label">Manual override</p>
                                        <p class="text-[10px] text-white/45" x-show="modal.visit?.manual_decision">
                                            Current: <span class="text-white/80" x-text="modal.visit?.manual_decision"></span>
                                            · original <span x-text="modal.visit?.original_threat_group || 'none'"></span>
                                            · <span x-text="modal.visit?.manual_decision_reason || ''"></span>
                                        </p>
                                        <input type="text" x-model="overrideReason" placeholder="Reason (required)" class="w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[11px] text-white">
                                        <div class="flex flex-wrap gap-[6px]">
                                            <button type="button" :disabled="overrideBusy" class="rounded border border-white/20 px-[8px] py-[4px] text-[10px] text-white/85 hover:bg-white/10" @click="overrideDecision('valid')">Valid</button>
                                            <button type="button" :disabled="overrideBusy" class="rounded border border-white/20 px-[8px] py-[4px] text-[10px] text-white/85 hover:bg-white/10" @click="overrideDecision('invalid')">Invalid</button>
                                            <button type="button" :disabled="overrideBusy" class="rounded border border-white/20 px-[8px] py-[4px] text-[10px] text-white/85 hover:bg-white/10" @click="overrideDecision('allowed')">Allowed</button>
                                            <button type="button" :disabled="overrideBusy" class="rounded border border-white/20 px-[8px] py-[4px] text-[10px] text-white/85 hover:bg-white/10" @click="overrideDecision('blocked')">Blocked</button>
                                        </div>
                                    </div>
                                    <div class="figma-modal-field figma-modal-field--full space-y-[6px]" x-show="modal.visit?.rule_explanation">
                                        <p class="figma-modal-label">Why this click was classified</p>
                                        <p class="text-[11px] text-white/80">
                                            Decision: <span class="risk-badge" :class="modal.visit?.status_badge_class" x-text="modal.visit?.rule_explanation?.decision || modal.visit?.status"></span>
                                            · Action: <span x-text="modal.visit?.rule_explanation?.action || '—'"></span>
                                        </p>
                                        <ul class="space-y-[4px] text-[10px] text-white/60">
                                            <template x-for="reason in (modal.visit?.rule_explanation?.reasons || [])" :key="reason.code">
                                                <li>
                                                    <span class="font-semibold text-white/80" x-text="reason.code"></span>
                                                    — <span x-text="reason.label"></span>
                                                </li>
                                            </template>
                                            <li x-show="!(modal.visit?.rule_explanation?.reasons || []).length" class="text-white/40">No automated reason codes on this row.</li>
                                        </ul>
                                        <p class="text-[10px] text-white/40" x-show="modal.visit?.rule_explanation?.original_decision">
                                            Original automated decision preserved:
                                            <span x-text="modal.visit?.rule_explanation?.original_decision?.threat_group || 'none'"></span>
                                            / <span x-text="modal.visit?.rule_explanation?.original_decision?.threat_type || '—'"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="figma-modal-overlay"
             x-show="recordingModal.open" x-cloak x-transition
             @keydown.escape.window="closeRecording()" @click.self="closeRecording()">
            <div class="figma-modal max-w-[640px]">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="figma-modal-title">Session Recording</h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeRecording()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>
                <p class="mb-3 text-[12px] text-white/70">
                    <span x-text="recordingModal.ip ? `IP: ${recordingModal.ip}` : ''"></span>
                    <span x-show="recordingModal.visit_id" class="text-white/45" x-text="recordingModal.visit_id ? ` · Visit #${recordingModal.visit_id}` : ''"></span>
                </p>
                <div class="overflow-hidden rounded-[8px] border border-white/20 bg-[#101010]">
                    <canvas x-ref="recordingCanvas" width="600" height="320" class="h-auto w-full" @click="seekRecording($event)"></canvas>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-[8px]">
                    <button type="button" class="rounded border border-white/20 px-[8px] py-[4px] text-[10px] text-white/85 hover:bg-white/10" @click="toggleRecordingPlayback()" x-text="recordingPlaying ? 'Pause' : 'Play'"></button>
                    <template x-for="spd in [0.5, 1, 2, 4]" :key="'spd-'+spd">
                        <button type="button" class="rounded border px-[8px] py-[4px] text-[10px]"
                                :class="recordingSpeed === spd ? 'border-[#6400B2] bg-[#6400B2]/30 text-white' : 'border-white/20 text-white/70 hover:bg-white/10'"
                                @click="setRecordingSpeed(spd)" x-text="spd + 'x'"></button>
                    </template>
                    <button type="button" class="rounded border border-red-400/40 px-[8px] py-[4px] text-[10px] text-red-200 hover:bg-red-500/20" @click="deleteRecording()" x-show="recordingModal.id">Delete recording</button>
                </div>
                <p class="mt-2 text-[10px] text-white/40">Pink markers = clicks · cyan = scrolls · click timeline to seek</p>
                <p class="mt-1 text-[11px] text-white/50" x-text="recordingModal.page_url || ''"></p>
            </div>
        </div>
    </section>
</div>

@include('partials.session-recording-player')

<script>
    function paidMarketingDetailed(config = {}) {
        const columnCatalog = [
            { key: 'ip', label: 'IP Address', primary: true, min: 120 },
            { key: 'visits', label: 'Visits', primary: true, min: 44 },
            { key: 'domain', label: 'Domain', primary: true, min: 100 },
            { key: 'campaign', label: 'Campaigns', primary: true, min: 100 },
            { key: 'gclid', label: 'GCLID', primary: true, min: 110 },
            { key: 'gbraid', label: 'GBRAID', primary: false, min: 110 },
            { key: 'wbraid', label: 'WBRAID', primary: false, min: 110 },
            { key: 'session_id', label: 'Session ID', primary: false, min: 100 },
            { key: 'device_fingerprint', label: 'Fingerprint', primary: false, min: 90 },
            { key: 'device', label: 'Device', primary: true, min: 72 },
            { key: 'browser', label: 'Browser', primary: false, min: 80 },
            { key: 'os', label: 'OS', primary: false, min: 72 },
            { key: 'screen_resolution', label: 'Screen', primary: false, min: 72 },
            { key: 'language', label: 'Language', primary: false, min: 64 },
            { key: 'visitor_timezone', label: 'Timezone', primary: false, min: 80 },
            { key: 'last_click_label', label: 'Last Click', primary: true, min: 76 },
            { key: 'threat_group', label: 'Threat Group', primary: true, min: 84 },
            { key: 'threat_type', label: 'Threat Type', primary: true, min: 76 },
            { key: 'country', label: 'Country', primary: true, min: 72 },
            { key: 'invalid_clicks', label: 'Invalid', primary: true, min: 52 },
            { key: 'valid_clicks', label: 'Valid', primary: true, min: 52 },
            { key: 'google_verified_label', label: 'Google Verified', primary: false, min: 88 },
            { key: 'session_recording', label: 'Recording', primary: false, min: 44 },
            { key: 'status', label: 'Status', primary: false, min: 72 },
            { key: 'intel_region', label: 'Region', primary: false, min: 80 },
            { key: 'intel_city', label: 'City', primary: false, min: 80 },
            { key: 'intel_latitude', label: 'Latitude', primary: false, min: 72 },
            { key: 'intel_longitude', label: 'Longitude', primary: false, min: 72 },
            { key: 'intel_asn', label: 'ASN', primary: false, min: 64 },
            { key: 'intel_asn_org', label: 'ASN Organization', primary: false, min: 110 },
            { key: 'intel_isp', label: 'ISP', primary: false, min: 90 },
            { key: 'intel_network_range', label: 'Network Range', primary: false, min: 100 },
            { key: 'intel_routed_prefix', label: 'Routed Prefix', primary: false, min: 100 },
            { key: 'intel_allocated_range', label: 'Allocated Range', primary: false, min: 100 },
            { key: 'intel_range_note', label: 'Range Note', primary: false, min: 90 },
            { key: 'intel_vpn', label: 'VPN', primary: false, min: 48 },
            { key: 'intel_proxy', label: 'Proxy', primary: false, min: 48 },
            { key: 'intel_tor', label: 'Tor', primary: false, min: 48 },
            { key: 'intel_datacenter', label: 'Datacenter', primary: false, min: 72 },
            { key: 'intel_risk_score', label: 'Risk Score', primary: false, min: 72 },
            { key: 'intel_risk_level', label: 'Risk Level', primary: false, min: 72 },
            { key: 'intel_confidence', label: 'Confidence', primary: true, min: 72 },
            { key: 'intel_evidence', label: 'Evidence', primary: true, min: 90 },
            { key: 'intel_checked_at', label: 'Checked At', primary: false, min: 100 },
            { key: 'intel_error', label: 'Error', primary: false, min: 56 },
            { key: 'intel_ip_need_blockation', label: 'IP Need Blockation', primary: false, min: 110 },
            { key: 'intel_blockation_type', label: 'Blockation Type', primary: false, min: 100 },
            { key: 'intel_block_reason', label: 'Block Reason', primary: false, min: 100 },
            { key: 'intel_device_action', label: 'Device Action', primary: false, min: 90 },
            { key: 'intel_provider_type', label: 'Provider Type', primary: false, min: 90 },
            { key: 'intel_matched_provider', label: 'Matched Provider', primary: false, min: 110 },
            { key: 'intel_matched_dataset', label: 'Matched Dataset', primary: false, min: 110 },
            { key: 'intel_cloud_provider', label: 'Cloud Provider', primary: false, min: 100 },
        ];

        let savedOptional = [];
        try {
            savedOptional = JSON.parse(localStorage.getItem('pm-adv-optional-columns') || '[]');
        } catch (e) {}
        const defaultOptionalColumns = [
            'session_recording',
            'google_verified_label',
            'status',
            'intel_vpn',
            'intel_proxy',
            'intel_tor',
            'intel_datacenter',
            'intel_risk_level',
            'intel_risk_score',
            'intel_ip_need_blockation',
            'intel_block_reason',
        ];
        defaultOptionalColumns.forEach((key) => {
            if (!savedOptional.includes(key)) {
                savedOptional.push(key);
            }
        });

        return {
            debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
            fetchTimer: null,
            loading: false,
            filterMenuOpen: false,
            dataFilterMenuOpen: false,
            exportMenuOpen: false,
            campaignMenuOpen: false,
            reportingTimezone: config.reportingTimezone || 'UTC',
            timezoneContext: null,
            domainCatalog: config.domainCatalog || {},
            reportingMode: config.reportingMode || 'profile',
            profileTimezone: config.profileTimezone || 'UTC',
            columnCatalog,
            optionalColumnKeys: Array.isArray(savedOptional) ? savedOptional : [],
            filters: {
                ip: '', path: '', domain_id: '', campaign: '', from: '', to: '',
                traffic_source: 'google_ads', google_ads_account_id: '',
                country: '', keyword: '', ad_group: '', source: '', browser: '', device: '',
                detection: '', threat_group: '', risk_level: '', block_status: '',
            },
            campaignOptions: [],
            rows: [],
            selectedIds: [],
            bulkMessage: '',
            overrideUrl: config.overrideUrl || '',
            bulkUrl: config.bulkUrl || '',
            csrf: config.csrf || '',
            overrideReason: '',
            overrideBusy: false,
            sortKey: 'last_click_at',
            sortDir: 'desc',
            sortNumericKeys: [
                'visits', 'invalid_clicks', 'valid_clicks', 'vpn_hits', 'data_center_hits',
                'intel_risk_score', 'intel_confidence', 'intel_latitude', 'intel_longitude', 'ip_count',
            ],
            statCards: [],
            kpiCards: [],
            chartThreat: { items: [], gradient: '', total_label: '0', center_label: 'Invalid Clicks' },
            chartRisk: { items: [], gradient: '', total_label: '0', center_label: 'Unique IPs' },
            chartCountries: [],
            highRiskIps: [],
            chartsUpdatedAt: null,
            modal: { open: false, visit: null, clicks: [], activeIndex: 0, timeline: [], timelineOpen: true, timelineLoading: false },
            recordingModal: { open: false, id: null, visit_id: null, ip: '', page_url: '', events: [] },
            recordingController: null,
            recordingPlaying: true,
            recordingSpeed: 1,
            recordingStop: null,
            get activeClick() { return this.modal.clicks[this.modal.activeIndex] || null; },
            get allVisibleSelected() {
                return this.sortedRows.length > 0 && this.sortedRows.every((row) => this.selectedIds.includes(row.id));
            },
            toggleSelect(id, on) {
                if (on) {
                    if (!this.selectedIds.includes(id)) this.selectedIds.push(id);
                } else {
                    this.selectedIds = this.selectedIds.filter((x) => x !== id);
                }
            },
            toggleSelectAll(on) {
                if (!on) {
                    this.selectedIds = [];
                    return;
                }
                this.selectedIds = this.sortedRows.map((row) => row.id);
            },
            async bulkAction(action) {
                if (!this.bulkUrl || !this.selectedIds.length) return;
                const reason = window.prompt('Reason for bulk ' + action + ' (required):', 'Bulk ' + action);
                if (reason === null) return;
                if (String(reason).trim().length < 3) {
                    this.bulkMessage = 'Reason too short.';
                    return;
                }
                this.bulkMessage = 'Working…';
                try {
                    const res = await fetch(this.bulkUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ visit_ids: this.selectedIds, action, reason }),
                    });
                    const data = await res.json().catch(() => ({}));
                    this.bulkMessage = data.ok
                        ? `Updated ${data.updated || 0}` + (data.failed ? `, failed ${data.failed}` : '')
                        : (data.message || 'Bulk action failed');
                    await this.fetchNow();
                    this.selectedIds = [];
                } catch (e) {
                    this.bulkMessage = 'Bulk request failed.';
                }
            },
            async overrideDecision(decision) {
                if (!this.overrideUrl || !this.modal.visit?.id) return;
                const reason = String(this.overrideReason || '').trim();
                if (reason.length < 3) {
                    this.bulkMessage = 'Enter an override reason (min 3 chars).';
                    return;
                }
                this.overrideBusy = true;
                try {
                    const res = await fetch(this.overrideUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            visit_id: this.modal.visit.id,
                            decision,
                            reason,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (data.ok && data.visit) {
                        const idx = this.rows.findIndex((r) => r.id === data.visit.id);
                        if (idx >= 0) this.rows[idx] = data.visit;
                        this.modal.visit = data.visit;
                        this.overrideReason = '';
                    }
                } finally {
                    this.overrideBusy = false;
                }
            },
            get dataFilterKeys() {
                return ['country', 'keyword', 'ad_group', 'source', 'browser', 'device', 'detection', 'threat_group', 'risk_level', 'block_status'];
            },
            get activeDataFilterCount() {
                return this.dataFilterKeys.filter((key) => String(this.filters[key] || '').trim() !== '').length;
            },
            get activeFilterChips() {
                const labels = {
                    ip: 'IP', path: 'Path', campaign: 'Campaign', country: 'Country', keyword: 'Keyword',
                    ad_group: 'Ad group', source: 'Source', browser: 'Browser', device: 'Device',
                    detection: 'Detection', threat_group: 'Threat', risk_level: 'Risk', block_status: 'Block',
                };
                return Object.keys(labels)
                    .filter((key) => String(this.filters[key] || '').trim() !== '')
                    .map((key) => ({ key, label: `${labels[key]}: ${this.filters[key]}` }));
            },
            clearDataFilters() {
                this.dataFilterKeys.forEach((key) => { this.filters[key] = ''; });
                this.scheduleFetch(true);
            },
            clearAllFilters() {
                this.dataFilterKeys.forEach((key) => { this.filters[key] = ''; });
                this.filters.ip = '';
                this.filters.path = '';
                this.filters.campaign = '';
                this.scheduleFetch(true);
            },
            clearFilterChip(key) {
                if (Object.prototype.hasOwnProperty.call(this.filters, key)) {
                    this.filters[key] = '';
                    this.scheduleFetch(true);
                }
            },
            get chartsUpdatedLabel() {
                if (!this.chartsUpdatedAt) return 'Updated: —';
                const d = new Date(this.chartsUpdatedAt);
                if (Number.isNaN(d.getTime())) return 'Updated: —';
                const sec = Math.max(0, Math.round((Date.now() - d.getTime()) / 1000));
                if (sec < 60) return `Updated: ${sec}s ago`;
                const min = Math.round(sec / 60);
                if (min < 60) return `Updated: ${min} min${min === 1 ? '' : 's'} ago`;
                const hr = Math.round(min / 60);
                return `Updated: ${hr}h ago`;
            },
            get sortedRows() {
                const api = window.promotixSortable;
                if (!api?.sortRows) return this.rows;
                return api.sortRows(this.rows, this.sortKey, this.sortDir, this.sortNumericKeys);
            },
            setSort(key) {
                if (!key || key === 'session_recording') return;
                const api = window.promotixSortable;
                if (api?.toggleSort) {
                    const next = api.toggleSort(this.sortKey, key, this.sortDir);
                    this.sortKey = next.key;
                    this.sortDir = next.dir;
                    return;
                }
                if (this.sortKey === key) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortKey = key;
                    this.sortDir = 'asc';
                }
            },
            sortClass(key) {
                const api = window.promotixSortable;
                if (api?.sortStateClass) return api.sortStateClass(this.sortKey, key, this.sortDir);
                if (this.sortKey !== key) return 'is-sortable';
                return this.sortDir === 'desc' ? 'is-sortable is-desc' : 'is-sortable is-asc';
            },
            resolveReportingTimezone(googleTz) {
                if (this.reportingMode === 'google' && googleTz) return googleTz;
                if (this.reportingMode === 'utc') return 'UTC';
                return this.profileTimezone;
            },
            applyDomainTimezoneFromCatalog() {
                const id = String(this.filters.domain_id || '');
                const entry = id ? this.domainCatalog[id] : null;
                this.reportingTimezone = this.resolveReportingTimezone(entry?.google_timezone || null);
                this.syncPaidTimezoneHeader();
            },
            syncPaidTimezoneHeader() {
                window.promotixSyncPaidTimezoneHeader?.(this.domainTimezoneChip, this.timezoneContextPanel);
            },
            get domainTimezoneChip() {
                const id = String(this.filters.domain_id || '');
                if (id) {
                    const d = this.domainCatalog[id];
                    if (d) {
                        return {
                            hostname: d.hostname,
                            timezone: d.google_timezone_label || 'Timezone not synced — run Sync Ads in Integrations',
                            account: d.google_account_name || null,
                            hasTimezone: !!d.google_timezone,
                        };
                    }
                    const domainCtx = this.timezoneContext?.domain;
                    if (domainCtx && String(domainCtx.id) === id) {
                        return {
                            hostname: domainCtx.hostname,
                            timezone: domainCtx.google_timezone_label || domainCtx.google_timezone || 'Timezone not synced — run Sync Ads in Integrations',
                            account: domainCtx.google_account_name || null,
                            hasTimezone: !!domainCtx.google_timezone,
                        };
                    }
                }
                const ctx = this.timezoneContext;
                if (!id && ctx?.google_timezone_label) {
                    return {
                        hostname: 'All domains',
                        timezone: ctx.google_timezone_label,
                        account: ctx.domain?.google_account_name || null,
                        hasTimezone: true,
                    };
                }
                return null;
            },
            get timezoneContextPanel() {
                const ctx = this.timezoneContext;
                if (!ctx) return null;
                const visit = ctx.visit_dates || {};
                const google = ctx.google_dates || {};
                const visitRange = visit.from === visit.to ? visit.from : `${visit.from} – ${visit.to}`;
                const visitTz = ctx.reporting_timezone_label || ctx.reporting_timezone;
                const visitLine = `${visitTz} · ${visitRange}`;
                let googleLine = null;
                if (ctx.google_timezone) {
                    const googleRange = google.from === google.to ? google.from : `${google.from} – ${google.to}`;
                    const googleTz = ctx.google_timezone_label || ctx.google_timezone;
                    googleLine = `${googleTz} · ${googleRange}`;
                }
                return {
                    visitLine,
                    googleLine,
                    modeLabel: ctx.reporting_mode_label,
                };
            },
            get visibleColumns() {
                return this.columnCatalog.filter(col => col.primary || this.optionalColumnKeys.includes(col.key));
            },
            get gridStyle() {
                const cols = this.visibleColumns.map(col => this.columnTrack(col)).join(' ');
                return `grid-template-columns: 36px ${cols}`;
            },
            get syncStyle() {
                return `min-width: ${this.tableMinWidth}px`;
            },
            get tableMinWidth() {
                const gap = 8;
                const pad = 24;
                const cols = this.visibleColumns.length + 1;
                const colWidths = this.visibleColumns.reduce((sum, col) => sum + this.columnMinPx(col), 0) + 36;
                return colWidths + Math.max(0, cols - 1) * gap + pad;
            },
            columnMinPx(col) {
                const key = col.key;
                if (key === 'session_recording') return 40;
                if (['visits', 'invalid_clicks', 'valid_clicks', 'invalid_visits', 'valid_visits'].includes(key)) {
                    return 52;
                }
                return col.min || 72;
            },
            columnTrack(col) {
                const min = this.columnMinPx(col);
                const key = col.key;
                if (key === 'session_recording') return `${min}px`;
                if (['visits', 'invalid_clicks', 'valid_clicks', 'invalid_visits', 'valid_visits'].includes(key)) {
                    return `${min}px`;
                }
                if (key === 'ip') return `minmax(${min}px, 1.6fr)`;
                if (key === 'domain' || key === 'campaign' || key === 'path') return `minmax(${min}px, 1.15fr)`;
                if (key === 'country' || key === 'last_click_label' || key === 'last_seen_label') {
                    return `minmax(${min}px, 0.95fr)`;
                }
                if (key === 'threat_group' || key === 'threat_type' || key === 'action_taken' || key === 'status') {
                    return `minmax(${min}px, 0.85fr)`;
                }
                return `minmax(${min}px, 1fr)`;
            },
            init() {
                const params = new URLSearchParams(window.location.search);
                const id = params.get('domain_id');
                if (id) this.filters.domain_id = id;
                const ip = params.get('ip');
                if (ip) this.filters.ip = ip;
                const campaign = params.get('campaign');
                if (campaign) this.filters.campaign = campaign;
                this.applyDomainTimezoneFromCatalog();
                this.syncHeaderDates();
                if (!this.filters.from || !this.filters.to) {
                    const pad = (n) => String(n).padStart(2, '0');
                    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                    const today = new Date();
                    const start = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 6);
                    this.filters.from = fmt(start);
                    this.filters.to = fmt(today);
                }
                this.loadCampaignsForDomain();
                this.fetchNow();
                window.addEventListener('promotix:date-range', () => {
                    this.syncHeaderDates();
                    this.scheduleFetch();
                });
            },
            syncHeaderDates() {
                try {
                    const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
                    if (r.from) this.filters.from = r.from;
                    if (r.to) this.filters.to = r.to;
                } catch (e) {}
            },
            applyPageDates() {
                if (!this.filters.from || !this.filters.to) return;
                try {
                    localStorage.setItem('promotix-date-range', JSON.stringify({
                        from: this.filters.from,
                        to: this.filters.to,
                    }));
                } catch (e) {}
                window.dispatchEvent(new CustomEvent('promotix:date-range', {
                    detail: { from: this.filters.from, to: this.filters.to },
                }));
                this.scheduleFetch();
            },
            scheduleFetch(fast = false) {
                clearTimeout(this.fetchTimer);
                this.fetchTimer = setTimeout(() => this.fetchNow(), fast ? 350 : this.debounceMs);
            },
            async onDomainChange() {
                this.filters.campaign = '';
                this.campaignOptions = [];
                this.campaignMenuOpen = false;
                this.applyDomainTimezoneFromCatalog();
                if (this.filters.domain_id) {
                    await this.loadCampaignsForDomain();
                }
                this.scheduleFetch(true);
            },
            async openCampaignMenu() {
                if (this.filters.domain_id && this.campaignOptions.length === 0) {
                    await this.loadCampaignsForDomain();
                }
                this.campaignMenuOpen = !this.campaignMenuOpen;
            },
            toggleOptionalColumn(key) {
                if (this.optionalColumnKeys.includes(key)) {
                    this.optionalColumnKeys = this.optionalColumnKeys.filter(k => k !== key);
                } else {
                    this.optionalColumnKeys = [...this.optionalColumnKeys, key];
                }
                try {
                    localStorage.setItem('pm-adv-optional-columns', JSON.stringify(this.optionalColumnKeys));
                } catch (e) {}
            },
            cellValue(visit, key) {
                if (key === 'ip') return this.ipLabel(visit);
                if (key === 'campaign') return visit.campaign || 'N/A';
                if (key === 'google_verified_label') {
                    const label = visit.google_verified_label || '—';
                    return label;
                }
                const value = visit[key];
                if (value === 0) return '0';
                if (value === null || value === undefined || value === '') return '—';
                return String(value);
            },
            selectCampaign(name) {
                this.filters.campaign = name;
                this.campaignMenuOpen = false;
                this.scheduleFetch(true);
            },
            async loadCampaignsForDomain() {
                const params = new URLSearchParams();
                if (this.filters.domain_id) params.set('domain_id', this.filters.domain_id);
                if (this.filters.from) params.set('from', this.filters.from);
                if (this.filters.to) params.set('to', this.filters.to);
                try {
                    const rows = await fetch(`/paid-marketing/campaigns?${params}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json());
                    const list = Array.isArray(rows) ? rows : (rows.campaigns || []);
                    this.campaignOptions = [...new Set(list.map(r => r.campaign).filter(Boolean))].sort();
                } catch (e) {
                    this.campaignOptions = [];
                }
            },
            queryString() {
                const p = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (k === 'traffic_source') return; // UI-only until multi-source backend ships
                    if (v !== '' && v != null) p.set(k, v);
                });
                if (this.sortKey) {
                    p.set('sort', this.sortKey);
                    p.set('dir', this.sortDir || 'asc');
                }
                return p.toString();
            },
            csvHref() {
                const qs = this.queryString();
                return `{{ route('paid-marketing.detailed-export') }}${qs ? '?' + qs : ''}`;
            },
            xlsxHref() {
                const qs = this.queryString();
                return `{{ route('paid-marketing.detailed-export-xlsx') }}${qs ? '?' + qs : ''}`;
            },
            async fetchNow() {
                this.loading = true;
                try {
                    const qs = this.queryString();
                    const res = await fetch(`{{ route('paid-marketing.detailed-visits') }}${qs ? '?' + qs : ''}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('fetch failed');
                    const data = await res.json();
                    this.rows = data.rows || [];
                    this.statCards = data.stats?.cards || [];
                    this.kpiCards = data.stats?.kpis || [];
                    const charts = data.stats?.charts || {};
                    this.chartThreat = charts.threat || { items: [], gradient: '', total_label: '0', center_label: 'Invalid Clicks' };
                    this.chartRisk = charts.risk || { items: [], gradient: '', total_label: '0', center_label: 'Unique IPs' };
                    this.chartCountries = charts.countries || [];
                    this.highRiskIps = charts.high_risk_ips || [];
                    this.chartsUpdatedAt = charts.updated_at || new Date().toISOString();
                    this.timezoneContext = data.timezone_context || null;
                    if (this.timezoneContext?.reporting_timezone) {
                        this.reportingTimezone = this.timezoneContext.reporting_timezone;
                    }
                    this.syncPaidTimezoneHeader();
                } catch (e) {
                    console.error(e);
                } finally {
                    this.loading = false;
                    window.promotixPageLoader?.hide();
                }
            },
            scrollHighRisk(dir) {
                const el = this.$refs.highRiskTrack;
                if (!el) return;
                const step = Math.max(240, Math.floor(el.clientWidth * 0.75));
                el.scrollBy({ left: dir * step, behavior: 'smooth' });
            },
            openHighRiskIp(card) {
                if (!card?.id) return;
                const visit = this.rows.find((r) => String(r.id) === String(card.id));
                if (visit) this.openClicks(visit);
            },
            async openClicks(visit) {
                this.modal.visit = visit;
                this.modal.clicks = (visit.clicks || []).slice();
                this.modal.activeIndex = 0;
                this.modal.timeline = [];
                this.modal.timelineOpen = true;
                this.modal.timelineLoading = true;
                this.modal.open = true;

                try {
                    const params = new URLSearchParams();
                    params.set('ip', visit.ip || '');
                    if (this.filters.domain_id) params.set('domain_id', this.filters.domain_id);
                    if (this.filters.from) params.set('from', this.filters.from);
                    if (this.filters.to) params.set('to', this.filters.to);
                    const res = await fetch(`{{ route('paid-marketing.detailed-ip-timeline') }}?${params}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (res.ok) {
                        const data = await res.json();
                        this.modal.timeline = data.events || [];
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    this.modal.timelineLoading = false;
                }
            },
            closeModal() {
                this.modal.open = false;
                this.modal.visit = null;
                this.modal.clicks = [];
                this.modal.activeIndex = 0;
                this.modal.timeline = [];
                this.modal.timelineLoading = false;
            },
            async openRecording(visit) {
                if (!visit?.session_recording_id) return;
                try {
                    const res = await fetch(`{{ route('paid-marketing.session-recording', ['recording' => '__ID__']) }}`.replace('__ID__', visit.session_recording_id), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('recording fetch failed');
                    const data = await res.json();
                    this.recordingModal = {
                        open: true,
                        id: data.id || visit.session_recording_id,
                        visit_id: data.visit_id || null,
                        ip: data.ip || visit.ip,
                        page_url: data.page_url || '',
                        events: data.events || [],
                    };
                    this.recordingPlaying = true;
                    this.recordingSpeed = 1;
                    this.$nextTick(() => this.renderRecording(data.events || []));
                } catch (e) {
                    console.error(e);
                }
            },
            closeRecording() {
                if (this.recordingController?.stop) this.recordingController.stop();
                if (this.recordingStop) {
                    this.recordingStop();
                    this.recordingStop = null;
                }
                this.recordingController = null;
                this.recordingPlaying = false;
                this.recordingModal = { open: false, id: null, visit_id: null, ip: '', page_url: '', events: [] };
            },
            renderRecording(events) {
                if (this.recordingController?.stop) this.recordingController.stop();
                if (this.recordingStop) {
                    this.recordingStop();
                    this.recordingStop = null;
                }
                const canvas = this.$refs.recordingCanvas;
                if (!canvas || !window.PromotixSessionRecordingPlayer) return;
                this.recordingController = window.PromotixSessionRecordingPlayer.play(canvas, events, () => {
                    this.recordingPlaying = false;
                    this.recordingController = null;
                    this.recordingStop = null;
                }, { speed: this.recordingSpeed });
                this.recordingStop = () => this.recordingController?.stop?.();
                this.recordingPlaying = true;
            },
            toggleRecordingPlayback() {
                if (!this.recordingController) return;
                if (this.recordingPlaying) {
                    this.recordingController.pause?.();
                    this.recordingPlaying = false;
                } else {
                    this.recordingController.resume?.();
                    this.recordingPlaying = true;
                }
            },
            setRecordingSpeed(spd) {
                this.recordingSpeed = spd;
                this.recordingController?.setSpeed?.(spd);
            },
            seekRecording(event) {
                const canvas = this.$refs.recordingCanvas;
                if (!canvas || !this.recordingController?.seek) return;
                const rect = canvas.getBoundingClientRect();
                const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
                const duration = this.recordingController.duration || 1;
                this.recordingController.seek(ratio * duration);
                this.recordingPlaying = true;
            },
            async deleteRecording() {
                if (!this.recordingModal?.id) return;
                if (!window.confirm('Delete this session recording permanently?')) return;
                try {
                    const res = await fetch(`{{ route('paid-marketing.session-recording.destroy', ['recording' => '__ID__']) }}`.replace('__ID__', this.recordingModal.id), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (data.ok) {
                        const id = this.recordingModal.id;
                        this.closeRecording();
                        this.rows = this.rows.map((row) => {
                            if (row.session_recording_id === id) {
                                return { ...row, has_session_recording: false, session_recording_id: null };
                            }
                            return row;
                        });
                    }
                } catch (e) {
                    console.error(e);
                }
            },
            formatDateTime(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return String(value);
                const tz = this.reportingTimezone || document.querySelector('meta[name="user-timezone"]')?.content || undefined;
                return date.toLocaleString(undefined, tz ? { timeZone: tz } : undefined);
            },
            ipLabel(visit) {
                const raw = String(visit?.ip || '');
                const parts = Array.isArray(visit?.ip_parts) && visit.ip_parts.length
                    ? visit.ip_parts
                    : raw.split(',').map((p) => p.trim()).filter(Boolean);
                const first = parts[0] || raw || '—';
                if (first.length > 20) return first.slice(0, 18) + '…';
                return first;
            },
            countryCode(value) {
                const raw = String(value || '').trim();
                if (/^[a-z]{2}$/i.test(raw)) return raw.toUpperCase();
                const names = {
                    'united states': 'US', 'usa': 'US', 'pakistan': 'PK', 'dominican republic': 'DO',
                    'united kingdom': 'GB', 'canada': 'CA', 'germany': 'DE', 'france': 'FR',
                    'india': 'IN', 'uae': 'AE', 'united arab emirates': 'AE', 'mexico': 'MX',
                };
                return names[raw.toLowerCase()] || '';
            },
            countryLabel(value) {
                const labels = {
                    US: 'United States', PK: 'Pakistan', DO: 'Dominican Republic', GB: 'United Kingdom',
                    CA: 'Canada', DE: 'Germany', FR: 'France', IN: 'India', AE: 'UAE', MX: 'Mexico',
                };
                const code = this.countryCode(value);
                if (code && labels[code]) return labels[code];
                const raw = String(value || '').trim();
                return raw || '—';
            },
            countryFlagUrl(value) {
                const code = this.countryCode(value).toLowerCase();
                if (!/^[a-z]{2}$/.test(code)) return '';
                return `https://flagcdn.com/w20/${code}.png`;
            },
            async copyText(value) {
                const text = String(value || '').trim();
                if (!text) return;
                try {
                    await navigator.clipboard.writeText(text);
                } catch (e) {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
            },
        };
    }
</script>
@endsection
