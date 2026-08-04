@extends('layouts.admin')

@section('title', 'Bot Protection | Advanced View')

@section('content')
<div class="brand-page-bg min-h-[calc(100vh-49px)]" x-data="botProtectionAdvancedFigma()" x-init="init()">
    <section class="mx-auto w-full min-w-0 px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <style>
            .bp-adv-page-head {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                margin-bottom: 18px;
                min-width: 0;
            }
            @media (min-width: 1100px) {
                .bp-adv-page-head {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                    gap: 14px;
                }
            }
            .figma-filter-bar--bp-adv.ov-filter-bar,
            .figma-filter-bar--bp-adv {
                width: fit-content !important;
                max-width: 100% !important;
                min-width: 0 !important;
                margin-left: auto !important;
                align-self: flex-end;
                flex: 0 0 auto !important;
                display: inline-flex !important;
                flex-wrap: nowrap !important;
                align-items: stretch;
                gap: 0 !important;
                overflow: visible;
                box-sizing: border-box;
            }
            .figma-filter-bar--bp-adv > label {
                flex: 0 0 auto !important;
                margin: 0 !important;
                padding-left: 6px !important;
                padding-right: 6px !important;
            }
            .figma-filter-bar--bp-adv > label.bp-adv-f-domain { width: 128px !important; }
            .figma-filter-bar--bp-adv > label.bp-adv-f-traffic { width: 108px !important; }
            .figma-filter-bar--bp-adv > label.bp-adv-f-account { width: 128px !important; }
            .figma-filter-bar--bp-adv > label.bp-adv-f-campaign { width: 118px !important; }
            .figma-filter-bar--bp-adv > label.bp-adv-f-path { width: 112px !important; }
            .figma-filter-bar--bp-adv .figma-filter-calendar-host {
                display: flex !important;
                flex: 0 0 auto !important;
                align-items: center;
                justify-content: center;
                align-self: stretch;
                border-left: 1px solid rgba(0, 0, 0, 0.2);
                padding: 6px 8px !important;
                margin: 0 !important;
            }
            @media (max-width: 900px) {
                .figma-filter-bar--bp-adv {
                    width: 100% !important;
                    align-self: stretch;
                    margin-left: 0 !important;
                    flex-wrap: wrap !important;
                    display: flex !important;
                }
                .figma-filter-bar--bp-adv > label {
                    flex: 1 1 130px !important;
                    width: auto !important;
                }
                .figma-filter-bar--bp-adv .figma-filter-calendar-host {
                    flex: 1 1 100% !important;
                    justify-content: flex-start;
                    border-left: 0;
                    border-top: 1px solid rgba(0, 0, 0, 0.12);
                }
            }
            .bp-adv-kpi-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-top: 22px;
                margin-bottom: 8px;
            }
            @media (min-width: 768px) {
                .bp-adv-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            }
            @media (min-width: 1200px) {
                .bp-adv-kpi-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
            }
            .bp-adv-kpi-card {
                display: flex;
                flex-direction: column;
                min-height: 148px;
                border-radius: 10px;
                border: 1px solid rgba(103, 6, 179, 0.55);
                background: #111111;
                padding: 14px 14px 12px;
            }
            .bp-adv-kpi-card__icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 28px;
                border-radius: 7px;
                margin-bottom: 10px;
            }
            .bp-adv-kpi-card__icon.is-purple { background: rgba(100, 0, 178, 0.28); color: #c4b5fd; }
            .bp-adv-kpi-card__icon.is-green { background: rgba(34, 197, 94, 0.18); color: #86efac; }
            .bp-adv-kpi-card__icon.is-rose { background: rgba(244, 63, 94, 0.18); color: #fda4af; }
            .bp-adv-kpi-card__icon.is-amber { background: rgba(245, 158, 11, 0.18); color: #fcd34d; }
            .bp-adv-kpi-card__label {
                font-size: 11px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.55);
                line-height: 1.25;
                margin-bottom: 8px;
            }
            .bp-adv-kpi-card__value {
                font-size: 26px;
                font-weight: 700;
                color: #fff;
                letter-spacing: -0.02em;
                line-height: 1.1;
            }
            .bp-adv-kpi-card__sub {
                margin-top: auto;
                padding-top: 10px;
                font-size: 10px;
                color: rgba(255, 255, 255, 0.42);
            }
            .bp-adv-filters-menu {
                position: absolute;
                top: calc(100% + 6px);
                left: 0;
                z-index: 50;
                width: min(calc(100vw - 32px), 420px);
                max-height: 320px;
                overflow: auto;
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-radius: 8px;
                background: #0f0e0e;
                padding: 12px;
                box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45);
            }
            html.light-mode .bp-adv-kpi-card {
                background: #fff;
                border-color: #d4c4e8;
                box-shadow: 0 1px 0 rgba(100, 0, 178, 0.06);
            }
            html.light-mode .bp-adv-kpi-card__label,
            html.light-mode .bp-adv-kpi-card__sub { color: #6b6280; }
            html.light-mode .bp-adv-kpi-card__value { color: #1a1a1a; }
            html.light-mode .bp-adv-kpi-card__icon.is-purple { background: rgba(100, 0, 178, 0.12); color: #6400B2; }
            html.light-mode .bp-adv-kpi-card__icon.is-green { background: rgba(34, 197, 94, 0.14); color: #15803d; }
            html.light-mode .bp-adv-kpi-card__icon.is-rose { background: rgba(244, 63, 94, 0.12); color: #be123c; }
            html.light-mode .bp-adv-kpi-card__icon.is-amber { background: rgba(245, 158, 11, 0.14); color: #b45309; }
            html.light-mode .bp-adv-filters-menu {
                background: #fff;
                border-color: #d4c4e8;
            }
            html.light-mode .bp-adv-filters-menu .text-white,
            html.light-mode .bp-adv-filters-menu label { color: #2d2d3a !important; }
            html.light-mode .bp-adv-filters-menu .text-white\/70,
            html.light-mode .bp-adv-filters-menu span.text-white\/70 { color: #6b6578 !important; }
            html.light-mode .bp-adv-filters-menu input,
            html.light-mode .bp-adv-filters-menu select {
                background: #f7f5fa !important;
                color: #2d2d3a !important;
                border-color: #d4c4e8 !important;
            }

            .bp-adv-charts {
                display: grid;
                grid-template-columns: 1fr;
                gap: 14px;
                margin-top: 18px;
            }
            @media (min-width: 900px) {
                .bp-adv-charts { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            }
            .bp-adv-chart-card {
                display: flex;
                flex-direction: column;
                min-height: 280px;
                border-radius: 10px;
                border: 1px solid rgba(103, 6, 179, 0.55);
                background: #111111;
                padding: 16px 16px 12px;
            }
            .bp-adv-chart-card__title {
                margin: 0 0 14px;
                font-size: 14px;
                font-weight: 600;
                color: #fff;
            }
            .bp-adv-chart-card__body {
                display: flex;
                align-items: center;
                gap: 14px;
                flex: 1;
                min-width: 0;
            }
            .bp-adv-donut {
                --bp-donut: conic-gradient(rgba(100,0,178,0.25) 0 100%);
                width: 118px;
                height: 118px;
                border-radius: 999px;
                background: var(--bp-donut);
                display: grid;
                place-items: center;
                flex-shrink: 0;
            }
            .bp-adv-donut__inner {
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
            .bp-adv-donut__value {
                font-size: 16px;
                font-weight: 700;
                color: #fff;
                line-height: 1.1;
            }
            .bp-adv-donut__label {
                margin-top: 2px;
                font-size: 9px;
                color: rgba(255, 255, 255, 0.45);
                line-height: 1.2;
            }
            .bp-adv-legend {
                list-style: none;
                margin: 0;
                padding: 0;
                min-width: 0;
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 7px;
                max-height: 190px;
                overflow-y: auto;
            }
            .bp-adv-legend li {
                display: grid;
                grid-template-columns: 10px minmax(0, 1fr) auto;
                align-items: center;
                gap: 8px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.82);
            }
            .bp-adv-legend__swatch {
                width: 10px;
                height: 10px;
                border-radius: 2px;
            }
            .bp-adv-legend__name {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .bp-adv-legend__meta {
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
                color: rgba(255, 255, 255, 0.7);
            }
            .bp-adv-countries {
                display: flex;
                flex-direction: column;
                gap: 12px;
                flex: 1;
                padding-top: 4px;
            }
            .bp-adv-country-row {
                display: grid;
                grid-template-columns: 22px minmax(72px, 0.9fr) minmax(0, 1.4fr) auto;
                align-items: center;
                gap: 8px;
            }
            .bp-adv-country-row__flag { font-size: 14px; line-height: 1; }
            .bp-adv-country-row__name {
                font-size: 12px;
                color: rgba(255, 255, 255, 0.88);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .bp-adv-country-row__track {
                height: 6px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.08);
                overflow: hidden;
            }
            .bp-adv-country-row__bar {
                display: block;
                height: 100%;
                border-radius: 999px;
                background: #6400B2;
            }
            .bp-adv-country-row__meta {
                font-size: 11px;
                color: rgba(255, 255, 255, 0.75);
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }
            .bp-adv-chart-card__updated {
                margin: 14px 0 0;
                text-align: right;
                font-size: 10px;
                color: rgba(255, 255, 255, 0.38);
            }
            .bp-adv-hip { margin-top: 18px; }
            .bp-adv-hip__head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 12px;
            }
            .bp-adv-hip__title {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #fff;
            }
            .bp-adv-hip__nav { display: flex; gap: 8px; }
            .bp-adv-hip__btn {
                display: grid;
                place-items: center;
                width: 32px;
                height: 32px;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: #1a1a1a;
                color: rgba(255, 255, 255, 0.75);
                cursor: pointer;
            }
            .bp-adv-hip__btn:hover {
                background: #222;
                color: #fff;
                border-color: rgba(100, 0, 178, 0.55);
            }
            .bp-adv-hip__track {
                display: flex;
                gap: 12px;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                scroll-behavior: smooth;
                padding-bottom: 6px;
            }
            .bp-adv-hip-card {
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
            }
            .bp-adv-hip-card:hover {
                border-color: rgba(100, 0, 178, 0.55);
                background: #1a1a1a;
            }
            .bp-adv-hip-card__ip {
                margin: 0;
                font-size: 18px;
                font-weight: 700;
                color: #fff;
                font-variant-numeric: tabular-nums;
            }
            .bp-adv-hip-card__risk { margin: 0; font-size: 13px; font-weight: 600; }
            .bp-adv-hip-card__risk--high { color: #F43F5E; }
            .bp-adv-hip-card__risk--medium { color: #F59E0B; }
            .bp-adv-hip-card__badge {
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
            .bp-adv-hip-card__dot {
                width: 7px;
                height: 7px;
                border-radius: 999px;
                flex-shrink: 0;
            }
            .bp-adv-hip-card__meta {
                margin: 2px 0 0;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.45);
            }
            .bp-adv-hip-card__ago {
                margin: 0;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.38);
            }
            .bp-adv-hip__empty {
                margin: 0;
                padding: 22px 12px;
                text-align: center;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.4);
                border: 1px dashed rgba(255, 255, 255, 0.18);
                border-radius: 10px;
                background: #111111;
            }
            html.light-mode .bp-adv-chart-card,
            html.light-mode .bp-adv-hip-card {
                background: #fff;
                border-color: #d4c4e8;
            }
            html.light-mode .bp-adv-donut__inner { background: #fff; }
            html.light-mode .bp-adv-chart-card__title,
            html.light-mode .bp-adv-hip__title,
            html.light-mode .bp-adv-hip-card__ip,
            html.light-mode .bp-adv-legend li,
            html.light-mode .bp-adv-country-row__name { color: #1a1a1a; }
            html.light-mode .bp-adv-donut__label,
            html.light-mode .bp-adv-chart-card__updated,
            html.light-mode .bp-adv-hip-card__meta,
            html.light-mode .bp-adv-hip-card__ago,
            html.light-mode .bp-adv-hip__empty { color: #6b6280; }
            html.light-mode .bp-adv-country-row__track { background: rgba(100, 0, 178, 0.1); }
            html.light-mode .bp-adv-hip__empty {
                background: #faf8fc;
                border-color: #d4c4e8;
            }
            html.light-mode .bp-adv-hip__btn {
                background: #fff;
                border-color: #d4c4e8;
                color: #5c5470;
            }
            @media (max-width: 520px) {
                .bp-adv-chart-card__body { flex-direction: column; align-items: flex-start; }
                .bp-adv-country-row {
                    grid-template-columns: 22px minmax(0, 1fr) auto;
                    grid-template-areas:
                        "flag name meta"
                        "track track track";
                }
                .bp-adv-country-row__flag { grid-area: flag; }
                .bp-adv-country-row__name { grid-area: name; }
                .bp-adv-country-row__meta { grid-area: meta; }
                .bp-adv-country-row__track { grid-area: track; }
            }
        </style>

        <div class="bp-adv-page-head">
            <div class="flex flex-wrap items-center gap-[8px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Bot Protection</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Advanced View</span>
            </div>

            <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--bp-adv ov-filter-bar ml-auto flex min-h-[54px] w-fit max-w-full flex-nowrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_2px_10px_rgba(0,0,0,.35)]">
                <label class="bp-adv-f-domain flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.domain_id" @change="reload(true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Domains</option>
                            @foreach ($domains as $d)
                                <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="bp-adv-f-traffic flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.traffic_source" @change="reload(true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="google_ads">Google Ads</option>
                            <option value="meta_ads" disabled>Meta Ads</option>
                            <option value="microsoft_ads" disabled>Microsoft Ads</option>
                        </select>
                    </div>
                </label>
                <label class="bp-adv-f-account flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Google Ads Account</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.google_ads_account_id" @change="reload(true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Accounts</option>
                            @foreach (($googleAdsAccounts ?? []) as $account)
                                <option value="{{ $account->id }}">{{ $account->displayLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="bp-adv-f-campaign flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.campaign" @change="reload(true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Campaigns</option>
                        </select>
                    </div>
                </label>
                <label class="bp-adv-f-path flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleReload(true)" placeholder="All Pages" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        <div class="bp-adv-kpi-grid">
            <template x-for="card in statCards" :key="card.key">
                <article class="bp-adv-kpi-card">
                    <span class="bp-adv-kpi-card__icon" :class="'is-' + (card.tone || 'purple')" aria-hidden="true">
                        <template x-if="card.key === 'blocked'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v5c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V6l8-3z"/></svg>
                        </template>
                        <template x-if="card.key === 'invalid_traffic'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        </template>
                        <template x-if="card.key === 'paid_traffic'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        </template>
                        <template x-if="card.key === 'bot_detection'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </template>
                        <template x-if="card.key === 'country'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        <template x-if="card.key === 'overall'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                    </span>
                    <p class="bp-adv-kpi-card__label" x-text="card.label"></p>
                    <p class="bp-adv-kpi-card__value" x-text="card.value + '%'"></p>
                    <p class="bp-adv-kpi-card__sub" x-text="card.sub"></p>
                </article>
            </template>
        </div>

        <section class="overflow-visible rounded-[12px] border border-[#6706b3]">
            <div class="flex flex-wrap items-center justify-between gap-[10px] overflow-visible rounded-t-[12px] bg-[#6400B2] px-[16px] py-[12px]">
                <h2 class="text-[18px] font-normal text-white sm:text-[20px]">Advanced View</h2>
                <div class="flex flex-1 flex-wrap items-center justify-end gap-[10px]">
                    <div class="relative" @click.outside="filterMenuOpen = false">
                        <button type="button" @click="filterMenuOpen = !filterMenuOpen" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            Advanced Filter
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
                            <template x-for="col in columnCatalog.filter(c => !c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option">
                                    <input type="checkbox" :value="col.key" :checked="optionalColumnKeys.includes(col.key)" @change="toggleOptionalColumn(col.key)">
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="relative" @click.outside="moreFiltersOpen = false">
                        <button type="button" @click="moreFiltersOpen = !moreFiltersOpen" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            More filters
                            <svg class="h-[12px] w-[12px] transition-transform" :class="moreFiltersOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="moreFiltersOpen" x-cloak x-transition class="bp-adv-filters-menu promotix-slim-scroll">
                            <div class="grid grid-cols-1 gap-[10px] sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-[4px] block text-[10px] uppercase text-white/70">Country</span>
                                    <input type="text" maxlength="2" placeholder="US" x-model="filters.country" @input="scheduleReload(true)" class="h-[32px] w-full rounded-[6px] border border-white/20 bg-[#101010] px-[10px] text-white uppercase">
                                </label>
                                <label class="block">
                                    <span class="mb-[4px] block text-[10px] uppercase text-white/70">Action</span>
                                    <select x-model="filters.action" @change="reload(true)" class="figma-panel-select w-full">
                                        <option value="">All</option>
                                        <option value="allow">Allow</option>
                                        <option value="flag">Flag</option>
                                        <option value="block">Block</option>
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="mb-[4px] block text-[10px] uppercase text-white/70">Threat group</span>
                                    <select x-model="filters.threat_group" @change="reload(true)" class="figma-panel-select w-full">
                                        <option value="">All</option>
                                        <option value="data_center">Data center</option>
                                        <option value="vpn">VPN</option>
                                        <option value="malicious">Malicious</option>
                                        <option value="abnormal_rate_limit">Abnormal rate limit</option>
                                        <option value="out_of_geo">Out of geo</option>
                                    </select>
                                </label>
                                <label class="inline-flex items-center gap-[8px] text-[11px] text-white">
                                    <x-figma-toggle x-model="filters.only_invalid" @change="reload(true)" :show-labels="false" />
                                    Only invalid
                                </label>
                                <label class="inline-flex items-center gap-[8px] text-[11px] text-white">
                                    <x-figma-toggle x-model="filters.only_paid" @change="reload(true)" :show-labels="false" />
                                    Only paid
                                </label>
                            </div>
                        </div>
                    </div>
                    <label class="relative flex h-[28px] min-w-[200px] max-w-[280px] flex-1 items-center rounded-[6px] bg-white px-[10px]">
                        <svg class="mr-[6px] h-[14px] w-[14px] shrink-0 text-[#8c8787]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" placeholder="Search for IP Address" x-model="filters.ip" @input="scheduleReload(true)" class="w-full border-0 bg-transparent text-[11px] text-[#121212] placeholder:text-[#8c8787] focus:ring-0">
                    </label>
                    <a :href="csvHref()" class="inline-flex items-center gap-[6px] text-[12px] font-medium text-white hover:underline">
                        <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v-1a4 4 0 014-4h0a4 4 0 014 4v1"/></svg>
                        Download CSV
                    </a>
                </div>
            </div>

            <div class="pm-adv-table-shell">
                <div class="pm-adv-table-x-scroll">
                    <div class="pm-adv-table-sync" :style="syncStyle">
                        <div class="pm-adv-table-grid pm-adv-table-grid--head text-[10px] font-medium uppercase tracking-wide text-[#a9a9a9] sm:text-[11px]" :style="gridStyle">
                            <template x-for="col in visibleColumns" :key="'head-' + col.key">
                                <span class="truncate" x-text="col.label"></span>
                            </template>
                        </div>

                        <div class="pm-adv-table-body-scroll">
                            <template x-for="row in rows" :key="row.id">
                                <div class="pm-adv-table-grid pm-adv-table-grid--row text-[10px] sm:text-[11px]" :style="gridStyle">
                                    <template x-for="col in visibleColumns" :key="row.id + '-' + col.key">
                                        <template x-if="col.key !== 'session_recording'">
                                            <span class="truncate" :class="col.key === 'ip' && 'font-medium'" :title="cellValue(row, col.key)" x-text="cellValue(row, col.key)"></span>
                                        </template>
                                        <template x-if="col.key === 'session_recording'">
                                            <span class="flex items-center justify-center">
                                                <button type="button" x-show="row.has_session_recording" @click.stop="openRecording(row)" class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-full bg-[#6400B2] text-white hover:bg-[#7B13C8]" title="Watch session recording">
                                                    <svg class="h-[11px] w-[11px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                </button>
                                                <span x-show="!row.has_session_recording" class="text-[#8c8787]">—</span>
                                            </span>
                                        </template>
                                    </template>
                                </div>
                            </template>
                            <p x-show="rows.length === 0" class="py-[24px] text-center text-[12px] text-[#a9a9a9]">No matching visits in this window.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-[#6706b3]/40 px-[14px] py-[10px] text-[10px] text-[#a9a9a9]">
                <span x-text="paginationLabel()"></span>
                <div class="flex gap-[8px]">
                    <button type="button" class="rounded-[6px] border border-[#6706b3] px-[12px] py-[4px] text-[10px] text-white disabled:opacity-40" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">Prev</button>
                    <button type="button" class="rounded-[6px] border border-[#6706b3] px-[12px] py-[4px] text-[10px] text-white disabled:opacity-40" :disabled="meta.page * meta.per_page >= meta.total" @click="changePage(meta.page + 1)">Next</button>
                </div>
            </div>
        </section>

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
                <p class="mb-3 text-[12px] text-white/70" x-text="recordingModal.ip ? `IP: ${recordingModal.ip}` : ''"></p>
                <div class="overflow-hidden rounded-[8px] border border-white/20 bg-[#101010]">
                    <canvas x-ref="recordingCanvas" width="600" height="320" class="h-auto w-full"></canvas>
                </div>
            </div>
        </div>

        <section class="bp-adv-charts">
            <article class="bp-adv-chart-card">
                <h3 class="bp-adv-chart-card__title">Threat Distribution</h3>
                <div class="bp-adv-chart-card__body">
                    <div class="bp-adv-donut" :style="`--bp-donut: ${chartThreat.gradient || 'conic-gradient(rgba(100,0,178,0.25) 0 100%)'}`">
                        <div class="bp-adv-donut__inner">
                            <p class="bp-adv-donut__value" x-text="chartThreat.total_label || '0'"></p>
                            <p class="bp-adv-donut__label" x-text="chartThreat.center_label || 'Invalid Clicks'"></p>
                        </div>
                    </div>
                    <ul class="bp-adv-legend">
                        <template x-for="item in (chartThreat.items || [])" :key="'threat-' + item.label">
                            <li>
                                <span class="bp-adv-legend__swatch" :style="`background:${item.color}`"></span>
                                <span class="bp-adv-legend__name" x-text="item.label"></span>
                                <span class="bp-adv-legend__meta">
                                    <span x-text="item.pct + '%'"></span>
                                    <span class="opacity-55" x-text="'(' + item.count_label + ')'"></span>
                                </span>
                            </li>
                        </template>
                        <li x-show="!(chartThreat.items || []).length" class="!text-white/40">No invalid threat data in range.</li>
                    </ul>
                </div>
                <p class="bp-adv-chart-card__updated" x-text="chartsUpdatedLabel"></p>
            </article>

            <article class="bp-adv-chart-card">
                <h3 class="bp-adv-chart-card__title">Risk Level Distribution</h3>
                <div class="bp-adv-chart-card__body">
                    <div class="bp-adv-donut" :style="`--bp-donut: ${chartRisk.gradient || 'conic-gradient(rgba(100,0,178,0.25) 0 100%)'}`">
                        <div class="bp-adv-donut__inner">
                            <p class="bp-adv-donut__value" x-text="chartRisk.total_label || '0'"></p>
                            <p class="bp-adv-donut__label" x-text="chartRisk.center_label || 'Unique IPs'"></p>
                        </div>
                    </div>
                    <ul class="bp-adv-legend">
                        <template x-for="item in (chartRisk.items || [])" :key="'risk-' + item.label">
                            <li>
                                <span class="bp-adv-legend__swatch" :style="`background:${item.color}`"></span>
                                <span class="bp-adv-legend__name" x-text="item.label"></span>
                                <span class="bp-adv-legend__meta">
                                    <span x-text="item.pct + '%'"></span>
                                    <span class="opacity-55" x-text="'(' + item.count_label + ')'"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <p class="bp-adv-chart-card__updated" x-text="chartsUpdatedLabel"></p>
            </article>

            <article class="bp-adv-chart-card">
                <h3 class="bp-adv-chart-card__title">Top Countries by Invalid Clicks</h3>
                <div class="bp-adv-countries">
                    <template x-for="row in chartCountries" :key="'country-' + row.name">
                        <div class="bp-adv-country-row">
                            <span class="bp-adv-country-row__flag" x-text="row.flag || '🌐'"></span>
                            <span class="bp-adv-country-row__name" x-text="row.name"></span>
                            <div class="bp-adv-country-row__track">
                                <span class="bp-adv-country-row__bar" :style="`width:${row.bar || 0}%`"></span>
                            </div>
                            <span class="bp-adv-country-row__meta">
                                <span x-text="row.count_label"></span>
                                <span class="opacity-55" x-text="'(' + row.pct + '%)'"></span>
                            </span>
                        </div>
                    </template>
                    <p x-show="chartCountries.length === 0" class="py-[18px] text-center text-[12px] text-white/40">No country invalid-click data in range.</p>
                </div>
                <p class="bp-adv-chart-card__updated" x-text="chartsUpdatedLabel"></p>
            </article>
        </section>

        <section class="bp-adv-hip">
            <div class="bp-adv-hip__head">
                <h2 class="bp-adv-hip__title">Recent High Risk IPs</h2>
                <div class="bp-adv-hip__nav" x-show="highRiskIps.length > 1">
                    <button type="button" class="bp-adv-hip__btn" @click="scrollHighRisk(-1)" aria-label="Previous high risk IPs">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="bp-adv-hip__btn" @click="scrollHighRisk(1)" aria-label="Next high risk IPs">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
            <div class="bp-adv-hip__track" x-ref="highRiskTrack" x-show="highRiskIps.length">
                <template x-for="card in highRiskIps" :key="'hip-' + (card.id || card.ip)">
                    <button type="button" class="bp-adv-hip-card" @click="filterByIp(card.ip)">
                        <p class="bp-adv-hip-card__ip" x-text="card.ip"></p>
                        <p class="bp-adv-hip-card__risk" :class="card.risk_tone === 'high' ? 'bp-adv-hip-card__risk--high' : 'bp-adv-hip-card__risk--medium'">
                            Risk: <span x-text="card.risk"></span>/100
                        </p>
                        <span class="bp-adv-hip-card__badge" :style="card.risk_tone === 'high' ? 'color:#F43F5E' : 'color:#F59E0B'">
                            <span class="bp-adv-hip-card__dot" :style="`background:${card.dot || '#F43F5E'}`"></span>
                            <span x-text="card.category"></span>
                        </span>
                        <p class="bp-adv-hip-card__meta" x-text="card.invalid_label"></p>
                        <p class="bp-adv-hip-card__ago" x-text="card.ago"></p>
                    </button>
                </template>
            </div>
            <p class="bp-adv-hip__empty" x-show="highRiskIps.length === 0">No high-risk IPs in this range.</p>
        </section>

        <p class="mt-[12px] text-right">
            <a href="{{ route('bot-protection.dashboard') }}" class="text-[11px] text-[#a9a9a9] hover:text-white hover:underline">&larr; Back to Dashboard</a>
        </p>
    </section>

@include('partials.session-recording-player')

<script>
function botProtectionAdvancedFigma() {
    const columnCatalog = [
        { key: 'ip', label: 'IP Address', primary: true, min: 120 },
        { key: 'visits', label: 'Visits', primary: true, min: 44 },
        { key: 'domain', label: 'Domain', primary: true, min: 100 },
        { key: 'path', label: 'Path', primary: true, min: 100 },
        { key: 'last_seen_label', label: 'Last Seen', primary: true, min: 76 },
        { key: 'threat_group', label: 'Threat Group', primary: true, min: 84 },
        { key: 'threat_type', label: 'Threat Type', primary: true, min: 76 },
        { key: 'action_taken', label: 'Action Taken', primary: true, min: 76 },
        { key: 'country', label: 'Country', primary: true, min: 72 },
        { key: 'invalid_visits', label: 'Invalid', primary: true, min: 52 },
        { key: 'valid_visits', label: 'Valid', primary: true, min: 52 },
        { key: 'session_recording', label: 'Recording', primary: false, min: 44 },
        { key: 'status', label: 'Status', primary: false, min: 72 },
        { key: 'browser', label: 'Browser', primary: false, min: 80 },
        { key: 'os', label: 'OS', primary: false, min: 72 },
        { key: 'referrer', label: 'Referrer', primary: false, min: 100 },
        { key: 'threat_score', label: 'Threat Score', primary: false, min: 72 },
        { key: 'utm_source', label: 'UTM Source', primary: false, min: 80 },
        { key: 'utm_medium', label: 'UTM Medium', primary: false, min: 80 },
        { key: 'utm_campaign', label: 'UTM Campaign', primary: false, min: 90 },
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
        { key: 'intel_confidence', label: 'Confidence', primary: false, min: 72 },
        { key: 'intel_evidence', label: 'Evidence', primary: false, min: 90 },
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
        savedOptional = JSON.parse(localStorage.getItem('bp-adv-optional-columns') || '[]');
    } catch (e) {}
    if (!savedOptional.includes('session_recording')) {
        savedOptional = [...savedOptional, 'session_recording'];
    }

    return {
        columnCatalog,
        optionalColumnKeys: Array.isArray(savedOptional) ? savedOptional : [],
        recordingModal: { open: false, ip: '', page_url: '', events: [] },
        recordingStop: null,
        filterMenuOpen: false,
        get visibleColumns() {
            return this.columnCatalog.filter(col => col.primary || this.optionalColumnKeys.includes(col.key));
        },
        get gridStyle() {
            const cols = this.visibleColumns.map(col => this.columnTrack(col)).join(' ');
            return `grid-template-columns: ${cols}`;
        },
        get syncStyle() {
            return `min-width: ${this.tableMinWidth}px`;
        },
        get tableMinWidth() {
            const gap = 8;
            const pad = 24;
            const cols = this.visibleColumns.length;
            const colWidths = this.visibleColumns.reduce((sum, col) => sum + this.columnMinPx(col), 0);
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
        filters: {
            domain_id: '',
            traffic_source: 'google_ads',
            google_ads_account_id: '',
            campaign: '',
            path: '',
            ip: '',
            country: '',
            action: '',
            threat_group: '',
            only_invalid: false,
            only_paid: false,
            from: '',
            to: '',
        },
        rows: [],
        meta: { total: 0, page: 1, per_page: 25 },
        moreFiltersOpen: false,
        stats: { blocked: 0, invalid_traffic: 0, paid_traffic: 0, bot_detection: 0, country: 0, overall: 0 },
        chartThreat: { items: [], gradient: '', total_label: '0', center_label: 'Invalid Clicks' },
        chartRisk: { items: [], gradient: '', total_label: '0', center_label: 'Unique IPs' },
        chartCountries: [],
        highRiskIps: [],
        chartsUpdatedAt: null,
        get chartsUpdatedLabel() {
            if (!this.chartsUpdatedAt) return 'Updated: —';
            try {
                const ts = new Date(this.chartsUpdatedAt).getTime();
                const sec = Math.max(0, Math.round((Date.now() - ts) / 1000));
                if (sec < 5) return 'Updated: 1s ago';
                if (sec < 60) return `Updated: ${sec}s ago`;
                const min = Math.round(sec / 60);
                return `Updated: ${min}m ago`;
            } catch (e) {
                return 'Updated: —';
            }
        },
        get statCards() {
            return [
                { key: 'blocked', label: 'Blocked', value: this.stats.blocked ?? 0, tone: 'rose', sub: 'Blocked actions in range' },
                { key: 'invalid_traffic', label: 'Invalid Traffic', value: this.stats.invalid_traffic ?? 0, tone: 'amber', sub: 'Flagged as invalid' },
                { key: 'paid_traffic', label: 'Paid Traffic', value: this.stats.paid_traffic ?? 0, tone: 'purple', sub: 'Attributed paid share' },
                { key: 'bot_detection', label: 'Bot Detection', value: this.stats.bot_detection ?? 0, tone: 'purple', sub: 'VPN / DC / rate threats' },
                { key: 'country', label: 'Country', value: this.stats.country ?? 0, tone: 'amber', sub: 'Visits with country data' },
                { key: 'overall', label: 'Overall', value: this.stats.overall ?? 0, tone: 'green', sub: 'Valid traffic share' },
            ];
        },
        qs(extra = {}) {
            const p = new URLSearchParams();
            Object.entries({ ...this.filters, ...extra }).forEach(([k, v]) => {
                if (v === false || v === '' || v === null || v === undefined) return;
                p.set(k, v === true ? '1' : v);
            });
            return p.toString();
        },
        csvHref() { return `/bot-protection/export.csv?${this.qs()}`; },
        reloadTimer: null,
        debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
        scheduleReload(resetPage = false) {
            clearTimeout(this.reloadTimer);
            this.reloadTimer = setTimeout(() => this.reload(resetPage), this.debounceMs);
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
            this.reload(true);
        },
        async init() {
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const today = new Date();
                const start = new Date(today.getTime() - 6 * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = today.toISOString().slice(0, 10);
            }
            window.addEventListener('promotix:date-range', () => {
                this.syncHeaderDates();
                this.scheduleReload(true);
            });
            await this.reload(true);
        },
        async reload(resetPage = false) {
            if (resetPage) this.meta.page = 1;
            try {
                const qs = this.qs({ page: this.meta.page, per_page: this.meta.per_page });
                const [visits, stats] = await Promise.all([
                    fetch(`/bot-protection/visits?${qs}`).then(r => r.json()),
                    fetch(`/bot-protection/bot-stats?${this.qs()}`).then(r => r.json()),
                ]);
                this.rows = visits.data || [];
                this.meta = { ...this.meta, ...(visits.meta || {}) };
                this.stats = {
                    blocked: stats.blocked ?? 0,
                    invalid_traffic: stats.invalid_traffic ?? 0,
                    paid_traffic: stats.paid_traffic ?? 0,
                    bot_detection: stats.bot_detection ?? 0,
                    country: stats.country ?? 0,
                    overall: stats.overall ?? 0,
                };
                const charts = stats.charts || {};
                this.chartThreat = charts.threat || { items: [], gradient: '', total_label: '0', center_label: 'Invalid Clicks' };
                this.chartRisk = charts.risk || { items: [], gradient: '', total_label: '0', center_label: 'Unique IPs' };
                this.chartCountries = charts.countries || [];
                this.highRiskIps = charts.high_risk_ips || [];
                this.chartsUpdatedAt = charts.updated_at || new Date().toISOString();
            } finally {
                window.promotixPageLoader?.hide();
            }
        },
        scrollHighRisk(dir) {
            const track = this.$refs.highRiskTrack;
            if (!track) return;
            track.scrollBy({ left: dir * 240, behavior: 'smooth' });
        },
        filterByIp(ip) {
            if (!ip) return;
            this.filters.ip = ip;
            this.reload(true);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        async changePage(p) {
            this.meta.page = Math.max(1, p);
            await this.reload(false);
        },
        paginationLabel() {
            const start = this.rows.length ? ((this.meta.page - 1) * this.meta.per_page + 1) : 0;
            const end = Math.min(this.meta.total, this.meta.page * this.meta.per_page);
            return `${start}-${end} of ${this.meta.total}`;
        },
        toggleOptionalColumn(key) {
            if (this.optionalColumnKeys.includes(key)) {
                this.optionalColumnKeys = this.optionalColumnKeys.filter(k => k !== key);
            } else {
                this.optionalColumnKeys = [...this.optionalColumnKeys, key];
            }
            try {
                localStorage.setItem('bp-adv-optional-columns', JSON.stringify(this.optionalColumnKeys));
            } catch (e) {}
        },
        cellValue(row, key) {
            if (key === 'ip') return this.ipLabel(row);
            if (key === 'threat_group') return row.threat_group_label || row.threat_group || '—';
            if (key === 'threat_type') return row.threat_type || row.threat_type_label || '—';
            if (key === 'country') return row.country_label || row.country || '—';
            if (key === 'action_taken') {
                const v = row.action_taken;
                return v ? String(v).charAt(0).toUpperCase() + String(v).slice(1) : '—';
            }
            const value = row[key];
            if (value === 0) return '0';
            if (value === null || value === undefined || value === '') return '—';
            return String(value);
        },
        ipLabel(row) {
            const raw = String(row?.ip || '');
            if (!raw) return '—';
            if (raw.length > 20) return raw.slice(0, 18) + '…';
            return raw;
        },
        countryFlagUrl(code) {
            const c = String(code || '').trim().toLowerCase();
            if (!/^[a-z]{2}$/.test(c)) return '';
            return `https://flagcdn.com/w20/${c}.png`;
        },
        async openRecording(row) {
            if (!row?.session_recording_id) return;
            try {
                const res = await fetch(`{{ route('paid-marketing.session-recording', ['recording' => '__ID__']) }}`.replace('__ID__', row.session_recording_id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('recording fetch failed');
                const data = await res.json();
                this.recordingModal = { open: true, ip: data.ip || row.ip, page_url: data.page_url || '', events: data.events || [] };
                this.$nextTick(() => this.renderRecording(data.events || []));
            } catch (e) { console.error(e); }
        },
        closeRecording() {
            if (this.recordingStop) {
                this.recordingStop();
                this.recordingStop = null;
            }
            this.recordingModal = { open: false, ip: '', page_url: '', events: [] };
        },
        renderRecording(events) {
            if (this.recordingStop) {
                this.recordingStop();
                this.recordingStop = null;
            }
            const canvas = this.$refs.recordingCanvas;
            if (!canvas || !window.PromotixSessionRecordingPlayer) return;
            this.recordingStop = window.PromotixSessionRecordingPlayer.play(canvas, events, () => {
                this.recordingStop = null;
            });
        },
    };
}
</script>
</div>
@endsection

