@extends('layouts.admin')

@section('title', 'Bot Protection | Dashboard')

@section('rightbar')
<div class="figma-rightbar-default paid-rightbar">
    @include('partials.figma-rightbar-header-actions')
    @include('partials.figma-rightbar-bot-protection')
</div>
@endsection

@section('content')
<div class="brand-page-bg min-h-[calc(100vh-49px)]" x-data="botProtectionFigma(@js(['useDemo' => $useDemo]))" x-init="init()">
    <section class="mx-auto w-full px-[12px] pb-[24px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        {{-- Header --}}
        <div class="mb-[18px] flex flex-col gap-[14px] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-[12px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Bot Protection</h1>
                <span class="hidden h-[34px] w-[2px] bg-[#a9a9a9] sm:block sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Dashboard</span>
                <span x-show="useDemo" x-cloak class="figma-bp-demo-badge">Sample data</span>
            </div>

            <style>
                .figma-filter-bar--bp-dash.ov-filter-bar,
                .figma-filter-bar--bp-dash {
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
                .figma-filter-bar--bp-dash > label {
                    flex: 0 0 auto !important;
                    margin: 0 !important;
                    padding-left: 6px !important;
                    padding-right: 6px !important;
                }
                .figma-filter-bar--bp-dash > label.bp-dash-f-domain { width: 128px !important; }
                .figma-filter-bar--bp-dash > label.bp-dash-f-traffic { width: 108px !important; }
                .figma-filter-bar--bp-dash > label.bp-dash-f-account { width: 128px !important; }
                .figma-filter-bar--bp-dash > label.bp-dash-f-campaign { width: 118px !important; }
                .figma-filter-bar--bp-dash > label.bp-dash-f-path { width: 112px !important; }
                .figma-filter-bar--bp-dash .figma-filter-calendar-host {
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
                    .figma-filter-bar--bp-dash {
                        width: 100% !important;
                        align-self: stretch;
                        margin-left: 0 !important;
                        flex-wrap: wrap !important;
                        display: flex !important;
                    }
                    .figma-filter-bar--bp-dash > label {
                        flex: 1 1 130px !important;
                        width: auto !important;
                    }
                    .figma-filter-bar--bp-dash .figma-filter-calendar-host {
                        flex: 1 1 100% !important;
                        justify-content: flex-start;
                        border-left: 0;
                        border-top: 1px solid rgba(0, 0, 0, 0.12);
                    }
                }
            </style>

            <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--bp-dash ov-filter-bar ml-auto flex min-h-[54px] w-fit max-w-full flex-nowrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_2px_10px_rgba(0,0,0,.35)]">
                <label class="bp-dash-f-domain flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.domain_id" @change="reload()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Domains</option>
                            @foreach ($domains as $d)
                                <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="bp-dash-f-traffic flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.traffic_source" @change="reload()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="google_ads">Google Ads</option>
                            <option value="meta_ads" disabled>Meta Ads</option>
                            <option value="microsoft_ads" disabled>Microsoft Ads</option>
                        </select>
                    </div>
                </label>
                <label class="bp-dash-f-account flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Google Ads Account</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.google_ads_account_id" @change="reload()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Accounts</option>
                            @foreach (($googleAdsAccounts ?? []) as $account)
                                <option value="{{ $account->id }}">{{ $account->displayLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="bp-dash-f-campaign flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.campaign" @change="reload()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Campaigns</option>
                        </select>
                    </div>
                </label>
                <label class="bp-dash-f-path flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleReload()" placeholder="All Pages" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        <div class="figma-bp-dashboard">
            <style>
                .bpv2-kpi-row {
                    display: grid;
                    grid-template-columns: repeat(5, minmax(0, 1fr));
                    gap: 12px;
                    margin-bottom: 14px;
                }
                @media (max-width: 1200px) {
                    .bpv2-kpi-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                }
                @media (max-width: 720px) {
                    .bpv2-kpi-row { grid-template-columns: repeat(1, minmax(0, 1fr)); }
                }
                .bpv2-kpi {
                    border-radius: 12px;
                    border: 1px solid rgba(100, 0, 178, 0.5);
                    background:
                        linear-gradient(180deg, rgba(100, 0, 178, 0.38) 0%, rgba(77, 0, 142, 0.14) 42%, #151515 78%);
                    padding: 14px 14px 10px;
                    min-height: 148px;
                    display: flex;
                    flex-direction: column;
                    box-shadow: 0 8px 22px rgba(100, 0, 178, 0.16);
                }
                .bpv2-kpi__top { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
                .bpv2-kpi__icon {
                    width: 28px; height: 28px; border-radius: 999px;
                    display: inline-flex; align-items: center; justify-content: center;
                    flex-shrink: 0;
                }
                .bpv2-kpi__title { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.72); }
                .bpv2-kpi__value { font-size: 28px; font-weight: 700; line-height: 1; color: #fff; letter-spacing: -0.02em; }
                .bpv2-kpi__sub { margin-top: 6px; font-size: 11px; color: rgba(255,255,255,0.45); }
                .bpv2-kpi__trend { margin-top: 8px; font-size: 11px; font-weight: 600; }
                .bpv2-kpi__trend.is-up { color: #34d399; }
                .bpv2-kpi__trend.is-down { color: #f87171; }
                .bpv2-kpi__spark { margin-top: auto; padding-top: 10px; height: 34px; }
                .bpv2-kpi__spark svg { width: 100%; height: 34px; display: block; }
                .bpv2-kpi.is-human .bpv2-kpi__icon,
                .bpv2-kpi.is-auto .bpv2-kpi__icon,
                .bpv2-kpi.is-crawl .bpv2-kpi__icon,
                .bpv2-kpi.is-invalid .bpv2-kpi__icon,
                .bpv2-kpi.is-impact .bpv2-kpi__icon {
                    background: rgba(100, 0, 178, 0.32);
                    color: #C4A0E8;
                }

                .bpv2-grid {
                    display: grid;
                    grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr) minmax(0, 1fr);
                    gap: 12px;
                    margin-bottom: 14px;
                }
                @media (max-width: 1100px) {
                    .bpv2-grid { grid-template-columns: 1fr; }
                }
                .bpv2-card {
                    border-radius: 12px;
                    border: 1px solid rgba(255,255,255,0.08);
                    background: #151515;
                    padding: 14px 16px 16px;
                    min-height: 280px;
                    display: flex;
                    flex-direction: column;
                }
                .bpv2-card__title {
                    margin: 0 0 12px;
                    font-size: 15px;
                    font-weight: 700;
                    color: rgba(255,255,255,0.88);
                }
                .bpv2-card__head {
                    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px;
                    margin-bottom: 12px;
                }
                .bpv2-card__head .bpv2-card__title { margin: 0; }
                .bpv2-legend { display: flex; flex-wrap: wrap; gap: 10px; }
                .bpv2-legend span {
                    display: inline-flex; align-items: center; gap: 5px;
                    font-size: 10px; color: rgba(255,255,255,0.55);
                }
                .bpv2-legend i {
                    width: 8px; height: 8px; border-radius: 2px; display: inline-block;
                }
                .bpv2-class-body {
                    display: grid;
                    grid-template-columns: 150px minmax(0, 1fr);
                    gap: 16px;
                    align-items: center;
                    flex: 1;
                }
                @media (max-width: 640px) {
                    .bpv2-class-body { grid-template-columns: 1fr; }
                }
                .bpv2-donut {
                    width: 140px; height: 140px; margin: 0 auto;
                    border-radius: 999px; position: relative;
                }
                .bpv2-donut__hole {
                    position: absolute; inset: 22%;
                    border-radius: 999px; background: #151515;
                    display: flex; flex-direction: column; align-items: center; justify-content: center;
                    text-align: center;
                }
                .bpv2-donut__hole strong { font-size: 16px; color: #fff; line-height: 1.1; }
                .bpv2-donut__hole span { font-size: 9px; color: rgba(255,255,255,0.45); margin-top: 2px; }
                .bpv2-table { width: 100%; border-collapse: collapse; font-size: 11px; }
                .bpv2-table th {
                    text-align: left; font-weight: 600; color: rgba(255,255,255,0.4);
                    padding: 0 0 8px; border-bottom: 1px solid rgba(255,255,255,0.06);
                }
                .bpv2-table td {
                    padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
                    color: rgba(255,255,255,0.78);
                }
                .bpv2-table tr:last-child td { border-bottom: 0; font-weight: 700; color: #fff; }
                .bpv2-table .num { text-align: right; font-variant-numeric: tabular-nums; }
                .bpv2-swatch {
                    display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; vertical-align: middle;
                }
                .bpv2-bars { display: flex; flex-direction: column; gap: 10px; flex: 1; }
                .bpv2-bar-row { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; }
                .bpv2-bar-row__label { font-size: 11px; color: rgba(255,255,255,0.7); }
                .bpv2-bar-row__meta { font-size: 11px; color: rgba(255,255,255,0.55); white-space: nowrap; }
                .bpv2-bar-track {
                    grid-column: 1 / -1; height: 8px; border-radius: 999px;
                    background: rgba(255,255,255,0.06); overflow: hidden;
                }
                .bpv2-bar-fill { height: 100%; border-radius: 999px; }
                .bpv2-bar-fill.is-red { background: linear-gradient(90deg, #e11d48, #fb7185); }
                .bpv2-bar-fill.is-purple { background: linear-gradient(90deg, #6400B2, #B893D8); }
                .bpv2-card__foot {
                    margin-top: auto; padding-top: 12px;
                    text-align: right; font-size: 12px; color: rgba(255,255,255,0.55);
                }
                .bpv2-detect {
                    display: grid;
                    grid-template-columns: 120px minmax(0, 1fr);
                    gap: 8px;
                    align-items: start;
                    justify-content: start;
                    flex: 1;
                }
                @media (max-width: 640px) {
                    .bpv2-detect { grid-template-columns: 1fr; }
                }
                .bpv2-detect .bpv2-donut {
                    width: 112px;
                    height: 112px;
                    margin: 0;
                }
                .bpv2-detect .bpv2-donut__hole strong { font-size: 14px; }
                .bpv2-detect .bpv2-donut__hole span { font-size: 8px; }
                .bpv2-detect__copy {
                    min-width: 0;
                    padding-left: 0;
                }
                .bpv2-detect__signals { margin-bottom: 14px; }
                .bpv2-detect__signals h4,
                .bpv2-detect__actions h4 {
                    margin: 0 0 8px; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.55);
                    text-transform: uppercase; letter-spacing: 0.04em;
                }
                .bpv2-signal {
                    display: flex; align-items: center; gap: 7px;
                    font-size: 11px; color: rgba(255,255,255,0.55); margin-bottom: 6px;
                }
                .bpv2-signal.is-on { color: rgba(255,255,255,0.85); }
                .bpv2-signal svg { width: 14px; height: 14px; color: #B893D8; flex-shrink: 0; opacity: 0.35; }
                .bpv2-signal.is-on svg { opacity: 1; }
                .bpv2-action {
                    display: flex; align-items: center; justify-content: space-between;
                    font-size: 11px; color: rgba(255,255,255,0.75); margin-bottom: 6px;
                }
                .bpv2-action__left { display: inline-flex; align-items: center; gap: 7px; }
                .bpv2-dot { width: 7px; height: 7px; border-radius: 999px; display: inline-block; }
                /* Classic purple vertical pills for Google Ads Sessions */
                .bpv2-ads {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 12px;
                    flex: 1;
                    align-content: stretch;
                    min-height: 0;
                }
                @media (max-width: 900px) {
                    .bpv2-ads { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                }
                .bpv2-ads__col {
                    display: flex;
                    flex-direction: column;
                    align-items: stretch;
                    min-width: 0;
                    min-height: 220px;
                }
                .bpv2-ads__label {
                    margin: 0 0 8px;
                    text-align: center;
                    font-size: 12px;
                    font-weight: 500;
                    color: rgba(255,255,255,0.78);
                    line-height: 1.25;
                }
                .bpv2-ads__pill {
                    display: flex;
                    flex: 1;
                    flex-direction: column;
                    justify-content: flex-end;
                    overflow: hidden;
                    border-radius: 12px;
                    border: 1px solid rgba(255,255,255,0.35);
                    background: #6400B2;
                    padding: 6px;
                    min-height: 180px;
                }
                .bpv2-ads__fill {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    width: 100%;
                    min-height: 36px;
                    padding: 12px 4px 10px;
                    border-radius: 10px;
                    border: 1px solid rgba(255,255,255,0.35);
                    background: rgba(255, 255, 255, 0.56);
                    transition: height 0.35s ease;
                    box-sizing: border-box;
                }
                .bpv2-ads__fill.is-strong { background: #9a1aff; }
                .bpv2-ads__fill.is-light { background: #C9B0E8; }
                .bpv2-ads__fill.is-empty {
                    background: transparent !important;
                    border-color: transparent !important;
                    justify-content: center;
                }
                .bpv2-ads__value {
                    margin: 0;
                    text-align: center;
                    font-size: 22px;
                    font-weight: 700;
                    color: #fff;
                    line-height: 1.1;
                    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
                }
                .bpv2-ads__count {
                    display: block;
                    margin-top: 4px;
                    font-size: 10px;
                    font-weight: 500;
                    color: rgba(255,255,255,0.9);
                }
                html.light-mode .bpv2-ads__label { color: #4a4458; }
                .bpv2-mal {
                    display: grid; grid-template-columns: 1fr 1fr; gap: 14px; flex: 1; align-items: start;
                }
                @media (max-width: 640px) {
                    .bpv2-mal { grid-template-columns: 1fr; }
                }
                .bpv2-mal__hero {
                    display: flex; flex-direction: column; align-items: flex-start; gap: 10px; padding-top: 8px;
                }
                .bpv2-mal__badge {
                    width: 56px; height: 56px; border-radius: 14px;
                    background: rgba(244,63,94,0.15); color: #fb7185;
                    display: inline-flex; align-items: center; justify-content: center;
                }
                .bpv2-mal__count { font-size: 28px; font-weight: 700; color: #fff; line-height: 1; }
                .bpv2-mal__sub { font-size: 12px; color: rgba(255,255,255,0.5); }
                .bpv2-mal__list h4 {
                    margin: 0 0 10px; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.55);
                    text-transform: uppercase; letter-spacing: 0.04em;
                }
                .bpv2-mal__item {
                    display: flex; justify-content: space-between; gap: 8px;
                    font-size: 11px; color: rgba(255,255,255,0.75); margin-bottom: 8px;
                }
                .bpv2-mal__item-left { display: inline-flex; align-items: center; gap: 7px; min-width: 0; }
                html.light-mode .bpv2-kpi {
                    background: linear-gradient(180deg, rgba(100, 0, 178, 0.12) 0%, rgba(100, 0, 178, 0.03) 40%, #fff 72%);
                    border-color: rgba(100, 0, 178, 0.28);
                    box-shadow: 0 6px 18px rgba(100, 0, 178, 0.1);
                }
                html.light-mode .bpv2-card { background: #fff; border-color: #e7e1ef; }
                html.light-mode .bpv2-kpi__value,
                html.light-mode .bpv2-mal__count,
                html.light-mode .bpv2-card__title { color: #1a1524; }
                html.light-mode .bpv2-kpi__title,
                html.light-mode .bpv2-kpi__sub,
                html.light-mode .bpv2-ads__label,
                html.light-mode .bpv2-bar-row__label,
                html.light-mode .bpv2-table td { color: #5b5568; }
                html.light-mode .bpv2-ads__value { color: #fff; }
                html.light-mode .bpv2-donut__hole { background: #fff; }
                html.light-mode .bpv2-donut__hole strong { color: #1a1524; }
                html.light-mode .bpv2-bar-track { background: #efeaf6; }

                /* Classic Domain + Country tables (old BP look) */
                .bpv2-tables-row {
                    display: grid !important;
                    grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.95fr) !important;
                    gap: 12px !important;
                    align-items: stretch;
                    width: 100%;
                    margin-top: 2px;
                }
                @media (max-width: 1100px) {
                    .bpv2-tables-row { grid-template-columns: 1fr !important; }
                }
                .bpv2-card--table {
                    min-height: 300px;
                    padding-bottom: 12px;
                    width: 100%;
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                }
                .bpv2-tables-row .figma-bp-domain-panel,
                .bpv2-tables-row .figma-bp-country-panel {
                    display: flex !important;
                    flex: 1;
                    min-height: 0;
                    flex-direction: column;
                    width: 100%;
                }
                .bpv2-tables-row .figma-bp-table {
                    display: flex !important;
                    flex-direction: column;
                    flex: 1;
                    min-height: 0;
                    width: 100%;
                }
                .bpv2-tables-row .figma-bp-table-body {
                    max-height: 340px;
                    overflow-y: auto;
                }
                /* Readable columns at full panel width (old sidebar grids were too narrow) */
                .bpv2-tables-row .figma-bp-table-head--domain,
                .bpv2-tables-row .figma-bp-table-row--domain {
                    grid-template-columns: minmax(0, 1.5fr) repeat(3, minmax(0, 0.85fr)) 100px;
                }
                .bpv2-tables-row .figma-bp-table-head--country,
                .bpv2-tables-row .figma-bp-table-row--country {
                    grid-template-columns: minmax(0, 1.5fr) minmax(52px, 0.7fr) minmax(52px, 0.7fr) minmax(40px, 0.55fr);
                    padding: 10px 12px;
                    font-size: 11px;
                    gap: 8px;
                }
                .bpv2-tables-row .figma-bp-table-head--country {
                    color: #ffffff;
                    font-size: 11px;
                    font-weight: 500;
                }
                .bpv2-tables-row .figma-bp-table-row--country {
                    cursor: pointer;
                    width: 100%;
                    border: 0;
                    text-align: left;
                    font: inherit;
                }
                .bpv2-tables-row .figma-bp-table-row--country:hover {
                    background-color: #1f1f1f;
                }
                .bpv2-tables-row .figma-bp-num {
                    text-align: right;
                    font-variant-numeric: tabular-nums;
                    white-space: nowrap;
                }
                .bpv2-tables-row .figma-bp-country-cell,
                .bpv2-tables-row .figma-bp-domain-cell {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    min-width: 0;
                    max-width: 100%;
                    overflow: hidden;
                }
                .bpv2-tables-row .figma-bp-flag {
                    width: 16px;
                    height: 11px;
                    border-radius: 2px;
                    object-fit: cover;
                    flex-shrink: 0;
                }
                .bpv2-tables-row .figma-bp-domain-icon {
                    width: 14px;
                    height: 14px;
                    color: #6400b2;
                    flex-shrink: 0;
                }
                .bpv2-tables-row .figma-bp-empty {
                    margin: 0;
                    padding: 28px 12px;
                    text-align: center;
                    font-size: 12px;
                    color: rgba(255,255,255,0.45);
                }
                html.light-mode .bpv2-tables-row .figma-bp-table-row--country {
                    background-color: #f7f5fa;
                    color: #2d2d3a;
                    border-bottom-color: #e7e1ef;
                }
                html.light-mode .bpv2-tables-row .figma-bp-table-row--country:hover {
                    background-color: #efeaf6;
                }
                html.light-mode .bpv2-tables-row .figma-bp-empty { color: #8a8498; }
            </style>

            {{-- Row 1: KPI metric cards --}}
            <div class="bpv2-kpi-row">
                <template x-for="card in kpiCards()" :key="card.key">
                    <article class="bpv2-kpi" :class="'is-' + card.tone">
                        <div class="bpv2-kpi__top">
                            <span class="bpv2-kpi__icon" x-html="card.icon"></span>
                            <p class="bpv2-kpi__title" x-text="card.title"></p>
                        </div>
                        <p class="bpv2-kpi__value" x-text="card.value"></p>
                        <p class="bpv2-kpi__sub" x-text="card.sub"></p>
                        <p class="bpv2-kpi__trend" :class="card.delta >= 0 ? 'is-up' : 'is-down'" x-text="card.deltaLabel"></p>
                        <div class="bpv2-kpi__spark" aria-hidden="true" x-html="sparkSvg(card.spark, card.color)"></div>
                    </article>
                </template>
            </div>

            {{-- Row 2: Classification / Threats / Detection --}}
            <div class="bpv2-grid">
                <section class="bpv2-card">
                    <div class="bpv2-card__head">
                        <h2 class="bpv2-card__title">Traffic Classification Overview</h2>
                        <div class="bpv2-legend">
                            <span><i style="background:#6400B2"></i>Valid Users</span>
                            <span><i style="background:#F43F5E"></i>Automated Traffic</span>
                            <span><i style="background:#3B82F6"></i>Known Crawlers</span>
                            <span><i style="background:#F59E0B"></i>Invalid Traffic</span>
                        </div>
                    </div>
                    <div class="bpv2-class-body">
                        <div class="bpv2-donut" :style="classificationDonutStyle()" role="img" aria-label="Traffic classification">
                            <div class="bpv2-donut__hole">
                                <strong x-text="fmt(summary.total_visits || 0)"></strong>
                                <span>Total Visits</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="bpv2-table">
                                <thead>
                                    <tr>
                                        <th>Traffic Type</th>
                                        <th class="num">Visits</th>
                                        <th class="num">%</th>
                                        <th class="num">Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in classificationRows()" :key="row.key">
                                        <tr>
                                            <td><span class="bpv2-swatch" :style="`background:${row.color}`"></span><span x-text="row.label"></span></td>
                                            <td class="num" x-text="fmt(row.value)"></td>
                                            <td class="num" x-text="row.pct + '%'"></td>
                                            <td class="num" :style="`color:${row.delta >= 0 ? '#34d399' : '#f87171'}`" x-text="formatDelta(row.delta)"></td>
                                        </tr>
                                    </template>
                                    <tr>
                                        <td>Total</td>
                                        <td class="num" x-text="fmt(summary.total_visits || 0)"></td>
                                        <td class="num">100%</td>
                                        <td class="num">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="bpv2-card">
                    <h2 class="bpv2-card__title">Threat Groups</h2>
                    <div class="bpv2-bars">
                        <template x-for="row in threatBarRows()" :key="row.label">
                            <div class="bpv2-bar-row">
                                <span class="bpv2-bar-row__label" x-text="row.label"></span>
                                <span class="bpv2-bar-row__meta" x-text="`${fmt(row.value)} · ${row.pct}%`"></span>
                                <div class="bpv2-bar-track"><div class="bpv2-bar-fill is-red" :style="`width:${row.bar}%`"></div></div>
                            </div>
                        </template>
                        <p x-show="threatBarRows().length === 0" class="text-[11px] text-white/45">No threat groups in this window.</p>
                    </div>
                    <p class="bpv2-card__foot">Total <span x-text="fmt(threatBarTotal())"></span></p>
                </section>

                <section class="bpv2-card">
                    <h2 class="bpv2-card__title">Bot Detection Summary</h2>
                    <div class="bpv2-detect">
                        <div class="bpv2-donut" :style="botsDonutStyle()" role="img" aria-label="Bots detected">
                            <div class="bpv2-donut__hole">
                                <strong x-text="fmt(summary.bots_detected || 0)"></strong>
                                <span>Bots Detected</span>
                            </div>
                        </div>
                        <div class="bpv2-detect__copy">
                            <div class="bpv2-detect__signals">
                                <h4>Detection Signals</h4>
                                <template x-for="sig in (summary.signals || [])" :key="sig.key">
                                    <div class="bpv2-signal" :class="{ 'is-on': sig.active }">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 13l4 4L19 7"/></svg>
                                        <span x-text="sig.label"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="bpv2-detect__actions">
                                <h4>Actions Taken</h4>
                                <template x-for="row in actionRows()" :key="row.key">
                                    <div class="bpv2-action">
                                        <span class="bpv2-action__left">
                                            <i class="bpv2-dot" :style="`background:${row.color}`"></i>
                                            <span x-text="row.label"></span>
                                        </span>
                                        <span x-text="`${fmt(row.value)} · ${row.pct}%`"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Row 3: Ads / Reasons / Malicious --}}
            <div class="bpv2-grid">
                <section class="bpv2-card">
                    <h2 class="bpv2-card__title">Google Ads Sessions Summary</h2>
                    <div class="bpv2-ads">
                        <div class="bpv2-ads__col">
                            <p class="bpv2-ads__label">Total Ad Sessions</p>
                            <div class="bpv2-ads__pill">
                                <div
                                    class="bpv2-ads__fill is-strong"
                                    :class="{ 'is-empty': paidOfAllPct() <= 0 }"
                                    :style="'height:' + (paidOfAllPct() <= 0 ? '100%' : Math.min(100, paidOfAllPct()) + '%')"
                                >
                                    <p class="bpv2-ads__value">
                                        <span x-text="paidOfAllPct() + '%'"></span>
                                        <span class="bpv2-ads__count" x-text="fmt(summary.paid?.total || 0) + ' sessions'"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bpv2-ads__col">
                            <p class="bpv2-ads__label">Valid Sessions</p>
                            <div class="bpv2-ads__pill">
                                <div
                                    class="bpv2-ads__fill is-light"
                                    :class="{ 'is-empty': paidValidPct() <= 0 }"
                                    :style="'height:' + (paidValidPct() <= 0 ? '100%' : Math.min(100, paidValidPct()) + '%')"
                                >
                                    <p class="bpv2-ads__value">
                                        <span x-text="paidValidPct() + '%'"></span>
                                        <span class="bpv2-ads__count" x-text="fmt(summary.paid?.valid || 0) + ' valid'"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bpv2-ads__col">
                            <p class="bpv2-ads__label">Invalid Sessions</p>
                            <div class="bpv2-ads__pill">
                                <div
                                    class="bpv2-ads__fill is-light"
                                    :class="{ 'is-empty': paidInvalidPct() <= 0 }"
                                    :style="'height:' + (paidInvalidPct() <= 0 ? '100%' : Math.min(100, paidInvalidPct()) + '%')"
                                >
                                    <p class="bpv2-ads__value">
                                        <span x-text="paidInvalidPct() + '%'"></span>
                                        <span class="bpv2-ads__count" x-text="fmt(summary.paid?.invalid || 0) + ' invalid'"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bpv2-ads__col">
                            <p class="bpv2-ads__label">Bot Impact</p>
                            <div class="bpv2-ads__pill">
                                <div
                                    class="bpv2-ads__fill is-light"
                                    :class="{ 'is-empty': Number(summary.paid?.bot_impact || 0) <= 0 }"
                                    :style="'height:' + (Number(summary.paid?.bot_impact || 0) <= 0 ? '100%' : Math.min(100, Number(summary.paid?.bot_impact || 0)) + '%')"
                                >
                                    <p class="bpv2-ads__value">
                                        <span x-text="(summary.paid?.bot_impact ?? 0) + '%'"></span>
                                        <span class="bpv2-ads__count" x-text="formatDelta(summary.deltas?.bot_impact || 0, true)"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bpv2-card">
                    <h2 class="bpv2-card__title">Invalid Traffic Reasons</h2>
                    <div class="bpv2-bars">
                        <template x-for="row in reasonBarRows()" :key="row.label">
                            <div class="bpv2-bar-row">
                                <span class="bpv2-bar-row__label" x-text="row.label"></span>
                                <span class="bpv2-bar-row__meta" x-text="`${fmt(row.value)} · ${row.pct}%`"></span>
                                <div class="bpv2-bar-track"><div class="bpv2-bar-fill is-purple" :style="`width:${row.bar}%`"></div></div>
                            </div>
                        </template>
                        <p x-show="reasonBarRows().length === 0" class="text-[11px] text-white/45">No invalid reasons yet.</p>
                    </div>
                    <p class="bpv2-card__foot">Total <span x-text="fmt(reasonBarTotal())"></span></p>
                </section>

                <section class="bpv2-card">
                    <h2 class="bpv2-card__title">Malicious Behavior</h2>
                    <div class="bpv2-mal">
                        <div class="bpv2-mal__hero">
                            <span class="bpv2-mal__badge" aria-hidden="true">
                                <svg class="h-[28px] w-[28px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v5c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V6l8-3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4"/></svg>
                            </span>
                            <p class="bpv2-mal__count" x-text="fmt(summary.invalid_malicious_visits || 0)"></p>
                            <p class="bpv2-mal__sub">Malicious Activities</p>
                        </div>
                        <div class="bpv2-mal__list">
                            <h4>Top Reasons</h4>
                            <template x-for="row in maliciousReasonRows()" :key="row.label">
                                <div class="bpv2-mal__item">
                                    <span class="bpv2-mal__item-left">
                                        <i class="bpv2-dot" style="background:#F43F5E"></i>
                                        <span class="truncate" x-text="row.label"></span>
                                    </span>
                                    <span x-text="`${fmt(row.value)} · ${row.pct}%`"></span>
                                </div>
                            </template>
                            <p x-show="maliciousReasonRows().length === 0" class="text-[11px] text-white/45">No malicious reasons.</p>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Domain (left) + Country (right) — classic figma-bp table design --}}
            <div class="bpv2-tables-row" id="bp-domain-country-row">
                <section class="bpv2-card bpv2-card--table">
                    <h2 class="bpv2-card__title">Domain Performance</h2>
                    <div class="figma-bp-domain-panel">
                        <div class="figma-bp-table">
                            <div class="figma-bp-table-head figma-bp-table-head--domain">
                                <span>Domain</span>
                                <span class="figma-bp-num">Valid</span>
                                <span class="figma-bp-num">Invalid</span>
                                <span class="figma-bp-num">Crawlers</span>
                                <span class="figma-bp-num">Action</span>
                            </div>
                            <div class="figma-bp-table-body promotix-slim-scroll">
                                <template x-for="row in domainsList" :key="'d-' + row.id">
                                    <div class="figma-bp-table-row--domain">
                                        <span class="figma-bp-domain-cell">
                                            <svg class="figma-bp-domain-icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 4v5c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V7l7-4z"/></svg>
                                            <span class="truncate" x-text="row.hostname"></span>
                                        </span>
                                        <span class="figma-bp-num" x-text="fmt(row.valid_visits)"></span>
                                        <span class="figma-bp-num" x-text="fmt(row.invalid_visits)"></span>
                                        <span class="figma-bp-num" x-text="fmt(row.known_crawlers)"></span>
                                        <a href="{{ route('paid-marketing.detection-settings') }}" class="figma-bp-protect-btn">Get Protected</a>
                                    </div>
                                </template>
                                <p x-show="!domainsList.length" class="figma-bp-empty">No domains in this window.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bpv2-card bpv2-card--table">
                    <h2 class="bpv2-card__title">Country Breakdown</h2>
                    <div class="figma-bp-country-panel">
                        <div class="figma-bp-table">
                            <div class="figma-bp-table-head figma-bp-table-head--country">
                                <span>Country</span>
                                <span class="figma-bp-num">Visits</span>
                                <span class="figma-bp-num">Invalid</span>
                                <span class="figma-bp-num">%</span>
                            </div>
                            <div class="figma-bp-table-body figma-bp-table-body--country promotix-slim-scroll">
                                <template x-for="row in countries" :key="'c-' + row.country">
                                    <button type="button" class="figma-bp-table-row--country" @click="openCountryIps(row.country)">
                                        <span class="figma-bp-country-cell">
                                            <img
                                                x-show="countryFlagUrl(row.country)"
                                                :src="countryFlagUrl(row.country)"
                                                :alt="countryLabel(row.country)"
                                                class="figma-bp-flag"
                                                loading="lazy"
                                            >
                                            <span class="truncate" x-text="countryLabel(row.country)"></span>
                                        </span>
                                        <span class="figma-bp-num" x-text="fmt(row.total ?? 0)"></span>
                                        <span class="figma-bp-num" x-text="fmt(row.invalid)"></span>
                                        <span class="figma-bp-num" x-text="(row.percent ?? 0) + '%'"></span>
                                    </button>
                                </template>
                                <p x-show="!countries.length" class="figma-bp-empty">No country data.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <p class="mt-[12px] text-right">
            <a href="{{ route('bot-protection.advanced') }}" class="text-[11px] text-[#a9a9a9] hover:text-white hover:underline">Open Advanced View →</a>
        </p>

        <div class="figma-modal-overlay"
             x-show="countryModal.open" x-cloak x-transition
             @keydown.escape.window="closeCountryModal()" @click.self="closeCountryModal()">
            <div class="figma-modal max-w-[520px]">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="figma-modal-title" x-text="`IPs from ${countryLabel(countryModal.country)}`"></h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeCountryModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>
                <p x-show="countryModal.loading" class="text-[12px] text-white/60">Loading IPs…</p>
                <div class="max-h-[320px] overflow-y-auto promotix-slim-scroll" x-show="!countryModal.loading">
                    <template x-for="row in countryModal.rows" :key="row.ip">
                        <div class="mb-[6px] flex items-center justify-between rounded-[6px] bg-white/5 px-[10px] py-[8px] text-[11px] text-white">
                            <span class="font-mono" x-text="row.ip"></span>
                            <span class="text-white/60" x-text="`${fmt(row.invalid)} invalid / ${fmt(row.total)} total`"></span>
                        </div>
                    </template>
                    <p x-show="!countryModal.rows.length" class="text-[12px] text-white/50">No IPs for this country.</p>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
function buildBpDemoPayload() {
    const spark = (base, amp) => Array.from({ length: 7 }, (_, i) => Math.max(0, Math.round(base + Math.sin(i * 0.9) * amp)));
    return {
        summary: {
            total_visits: 25430,
            valid_visits: 23800,
            invalid_bot_visits: 900,
            invalid_malicious_visits: 50,
            invalid_traffic: 230,
            known_crawlers: 500,
            bots_detected: 342,
            deltas: {
                valid_visits: 12.5,
                invalid_bot_visits: -8.7,
                known_crawlers: 3.4,
                invalid_traffic: -15.3,
                bot_impact: -0.8,
            },
            paid: { total: 10532, valid: 8945, invalid: 1587, bot_impact: 3.2 },
            actions: { block: 280, challenge: 42, allow: 20 },
            signals: [
                { key: 'headless', label: 'Headless Browser', active: true },
                { key: 'automation', label: 'Automation Tool', active: true },
                { key: 'missing_events', label: 'Missing Browser Events', active: true },
                { key: 'abnormal_rate', label: 'Abnormal Request Rate', active: true },
            ],
            sparklines: {
                valid: spark(3400, 400),
                automated: spark(50, 18),
                crawlers: spark(70, 16),
                invalid: spark(220, 40),
                bot_impact: spark(3.2, 0.6),
            },
        },
        domainsList: [
            { id: 1, hostname: 'www.example.com', valid_visits: 23800, invalid_visits: 230, known_crawlers: 500 },
            { id: 2, hostname: 'www.infinitdigi.com', valid_visits: 8200, invalid_visits: 2100, known_crawlers: 340 },
        ],
        countries: [
            { country: 'US', total: 12400, invalid: 4200, percent: 34 },
            { country: 'GB', total: 6800, invalid: 2100, percent: 17 },
            { country: 'DE', total: 5200, invalid: 1800, percent: 15 },
            { country: 'PK', total: 4100, invalid: 1200, percent: 10 },
            { country: 'AE', total: 2900, invalid: 980, percent: 8 },
        ],
        invalidTrends: { labels: [], datasets: [], stats: { pageloads: 0, interactions: 0 } },
        cache: {
            traffic: { labels: [], datasets: [] },
            th: { labels: ['automation', 'vpn', 'proxy', 'data_center', 'suspicious', 'other'], values: [48, 32, 21, 18, 15, 16] },
            ib: { labels: [], values: [] },
            mal: { labels: [], values: [] },
            reasons: { labels: ['Headless Browser', 'Rapid Requests', 'Datacenter Traffic', 'Unknown Automation'], values: [120, 90, 80, 50] },
            malicious_reasons: { labels: ['Repeated Click Pattern', 'Same Device Multi Sessions', 'Suspicious Navigation', 'Abnormal Interaction'], values: [18, 12, 11, 9] },
        },
    };
}

function botProtectionFigma(config = {}) {
    const countryNames = { US: 'United states', GB: 'United Kingdom', DE: 'Germany', PK: 'Pakistan', AE: 'UAE', CA: 'Canada', FR: 'France', IN: 'India' };

    return {
        useDemo: Boolean(config.useDemo),
        filters: {
            domain_id: '',
            traffic_source: 'google_ads',
            google_ads_account_id: '',
            campaign: '',
            path: '',
            from: '',
            to: '',
        },
        summary: {
            total_visits: 0, valid_visits: 0, invalid_bot_visits: 0, invalid_malicious_visits: 0,
            invalid_traffic: 0, known_crawlers: 0, bots_detected: 0,
            deltas: { valid_visits: 0, invalid_bot_visits: 0, known_crawlers: 0, invalid_traffic: 0, bot_impact: 0 },
            paid: { total: 0, valid: 0, invalid: 0, bot_impact: 0 },
            actions: { block: 0, challenge: 0, allow: 0 },
            signals: [],
            sparklines: { valid: [], automated: [], crawlers: [], invalid: [], bot_impact: [] },
        },
        countries: [],
        countryModal: { open: false, country: '', rows: [], loading: false },
        domainsList: [],
        invalidTrends: { labels: [], datasets: [], stats: { pageloads: 0, interactions: 0 } },
        cache: {},
        donutPalette: ['#FFFFFF', '#B893D8', '#6625F8', '#FF4BC1'],
        charts: {},
        hiddenSeries: { area: {}, invalid: {} },
        hiddenDonutSegments: { ib: {}, mal: {} },
        invalidHoverIndex: null,
        fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
        fmtCompact(n) {
            const v = Number(n || 0);
            if (v >= 1000) return Math.round(v / 1000) + 'k';
            return this.fmt(v);
        },
        countryLabel(code) { return countryNames[code] || code || 'Unknown'; },
        countryFlagUrl(code) {
            const c = String(code || '').trim().toLowerCase();
            if (!/^[a-z]{2}$/.test(c)) return '';
            return `https://flagcdn.com/w20/${c}.png`;
        },
        async openCountryIps(country) {
            if (!country) return;
            this.countryModal = { open: true, country, rows: [], loading: true };
            try {
                const p = new URLSearchParams(this.qs());
                p.set('country', country);
                this.countryModal.rows = await fetch(`/bot-protection/country-ips?${p}`).then(r => r.json());
            } catch (e) {
                this.countryModal.rows = [];
            } finally {
                this.countryModal.loading = false;
            }
        },
        closeCountryModal() {
            this.countryModal = { open: false, country: '', rows: [], loading: false };
        },
        threatLabel(key) {
            const map = {
                vpn: 'VPN', proxy: 'Proxy', data_center: 'Datacenter', abnormal_rate_limit: 'Suspicious Behavior',
                malicious: 'Malicious', automation: 'Automation', suspicious: 'Suspicious Behavior', other: 'Other',
            };
            const k = String(key || '').toLowerCase();
            return map[k] || k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
        },

        formatDelta(delta, pts = false) {
            const n = Number(delta || 0);
            const arrow = n >= 0 ? '↑' : '↓';
            const abs = Math.abs(n).toFixed(1).replace(/\.0$/, '');
            return pts ? `${n >= 0 ? '+' : ''}${abs}%` : `${arrow} ${abs}% vs previous period`;
        },
        sharePct(part, total) {
            const t = Number(total || 0);
            if (!t) return 0;
            return Math.round((Number(part || 0) / t) * 10000) / 100;
        },
        paidOfAllPct() {
            return this.sharePct(this.summary?.paid?.total, this.summary?.total_visits);
        },
        paidValidPct() {
            return this.sharePct(this.summary?.paid?.valid, this.summary?.paid?.total);
        },
        paidInvalidPct() {
            return this.sharePct(this.summary?.paid?.invalid, this.summary?.paid?.total);
        },
        sparkSvg(values, color) {
            const brand = '#6400B2';
            const stroke = color || brand;
            const vals = (values || []).map(v => Number(v || 0));
            const gid = 'bpSpark' + Math.random().toString(36).slice(2, 8);
            if (!vals.length) {
                return `<svg viewBox="0 0 120 34" preserveAspectRatio="none"><path d="M0 28 H120" fill="none" stroke="${brand}" stroke-opacity="0.35" stroke-width="2"/></svg>`;
            }
            const min = Math.min(...vals);
            const max = Math.max(...vals);
            const span = Math.max(max - min, 0.0001);
            const w = 120, h = 34, pad = 3;
            const coords = vals.map((v, i) => {
                const x = vals.length === 1 ? w / 2 : (i / (vals.length - 1)) * w;
                const y = pad + (1 - ((v - min) / span)) * (h - pad * 2);
                return [x, y];
            });
            const line = coords.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
            const area = `M0,${h} ` + coords.map(([x, y]) => `L${x.toFixed(1)},${y.toFixed(1)}`).join(' ') + ` L${w},${h} Z`;
            return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="${brand}" stop-opacity="0.45"/>
                        <stop offset="55%" stop-color="${brand}" stop-opacity="0.16"/>
                        <stop offset="100%" stop-color="${brand}" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <path d="${area}" fill="url(#${gid})"/>
                <polyline fill="none" stroke="${stroke}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="${line}"/>
            </svg>`;
        },
        kpiCards() {
            const s = this.summary || {};
            const total = Number(s.total_visits || 0);
            const paid = s.paid || {};
            const deltas = s.deltas || {};
            const sparks = s.sparklines || {};
            const brandSpark = '#6400B2';
            const icon = (path) => `<svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">${path}</svg>`;
                    return [
                {
                    key: 'human', tone: 'human', title: 'Human Visitors', color: brandSpark,
                    value: this.fmt(s.valid_visits || 0),
                    sub: `${this.sharePct(s.valid_visits, total)}% of total traffic`,
                    delta: Number(deltas.valid_visits || 0),
                    deltaLabel: this.formatDelta(deltas.valid_visits),
                    spark: sparks.valid || [],
                    icon: icon('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'),
                },
                {
                    key: 'auto', tone: 'auto', title: 'Automated Threats', color: brandSpark,
                    value: this.fmt(s.invalid_bot_visits || 0),
                    sub: `${this.sharePct(s.invalid_bot_visits, total)}% of total traffic`,
                    delta: Number(deltas.invalid_bot_visits || 0),
                    deltaLabel: this.formatDelta(deltas.invalid_bot_visits),
                    spark: sparks.automated || [],
                    icon: icon('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3h6v2h2a2 2 0 012 2v3h-2v2h2v3a2 2 0 01-2 2h-2v2H9v-2H7a2 2 0 01-2-2v-3h2v-2H5V7a2 2 0 012-2h2V3z"/>'),
                },
                {
                    key: 'crawl', tone: 'crawl', title: 'Verified Crawlers', color: brandSpark,
                    value: this.fmt(s.known_crawlers || 0),
                    sub: `${this.sharePct(s.known_crawlers, total)}% of total traffic`,
                    delta: Number(deltas.known_crawlers || 0),
                    deltaLabel: this.formatDelta(deltas.known_crawlers),
                    spark: sparks.crawlers || [],
                    icon: icon('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'),
                },
                {
                    key: 'invalid', tone: 'invalid', title: 'Invalid Traffic', color: brandSpark,
                    value: this.fmt(s.invalid_traffic || 0),
                    sub: `${this.sharePct(s.invalid_traffic, total)}% of total traffic`,
                    delta: Number(deltas.invalid_traffic || 0),
                    deltaLabel: this.formatDelta(deltas.invalid_traffic),
                    spark: sparks.invalid || [],
                    icon: icon('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>'),
                },
                {
                    key: 'impact', tone: 'impact', title: 'Bot Impact (Ads Traffic)', color: brandSpark,
                    value: `${paid.bot_impact ?? 0}%`,
                    sub: `${this.fmt(paid.invalid || 0)} invalid of ${this.fmt(paid.total || 0)} sessions`,
                    delta: Number(deltas.bot_impact || 0),
                    deltaLabel: this.formatDelta(deltas.bot_impact),
                    spark: sparks.bot_impact || [],
                    icon: icon('<circle cx="12" cy="12" r="8" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/>'),
                },
            ];
        },
        classificationRows() {
            const s = this.summary || {};
            const total = Number(s.total_visits || 0) || 1;
            const deltas = s.deltas || {};
            const rows = [
                { key: 'valid', label: 'Valid Users', value: s.valid_visits || 0, color: '#6400B2', delta: deltas.valid_visits || 0 },
                { key: 'auto', label: 'Automated Traffic', value: s.invalid_bot_visits || 0, color: '#F43F5E', delta: deltas.invalid_bot_visits || 0 },
                { key: 'crawl', label: 'Known Crawlers', value: s.known_crawlers || 0, color: '#3B82F6', delta: deltas.known_crawlers || 0 },
                { key: 'invalid', label: 'Invalid Traffic', value: s.invalid_malicious_visits || Math.max(0, Number(s.invalid_traffic || 0) - Number(s.invalid_bot_visits || 0)), color: '#F59E0B', delta: (deltas.invalid_malicious_visits ?? deltas.invalid_traffic) || 0 },
            ];
            return rows.map(r => ({ ...r, pct: this.sharePct(r.value, total) }));
        },
        classificationDonutStyle() {
            const rows = this.classificationRows();
            const total = rows.reduce((a, r) => a + Number(r.value || 0), 0);
            if (!total) return { background: 'conic-gradient(rgba(255,255,255,0.2) 0 100%)' };
            let deg = 0;
            const stops = rows.map(r => {
                const span = (Number(r.value || 0) / total) * 360;
                const start = deg;
                deg += span;
                return `${r.color} ${start}deg ${deg}deg`;
            });
            return { background: `conic-gradient(${stops.join(', ')})` };
        },
        barRowsFrom(block) {
            const labels = block?.labels || [];
            const values = (block?.values || []).map(v => Number(v || 0));
            const total = values.reduce((a, b) => a + b, 0) || 1;
            const max = Math.max(...values, 1);
            return labels.map((label, i) => {
                const value = values[i] || 0;
                return {
                    label: this.threatLabel(label),
                    value,
                    pct: Math.round((value / total) * 1000) / 10,
                    bar: Math.max(4, Math.round((value / max) * 100)),
                };
            });
        },
        threatBarRows() { return this.barRowsFrom(this.cache?.th); },
        threatBarTotal() { return (this.cache?.th?.values || []).reduce((a, b) => a + Number(b || 0), 0); },
        reasonBarRows() { return this.barRowsFrom(this.cache?.reasons); },
        reasonBarTotal() { return (this.cache?.reasons?.values || []).reduce((a, b) => a + Number(b || 0), 0); },
        maliciousReasonRows() { return this.barRowsFrom(this.cache?.malicious_reasons); },
        actionRows() {
            const a = this.summary?.actions || {};
            const rows = [
                { key: 'block', label: 'Blocked', value: a.block || 0, color: '#F43F5E' },
                { key: 'challenge', label: 'Challenge', value: a.challenge || 0, color: '#F59E0B' },
                { key: 'allow', label: 'Allowed', value: a.allow || 0, color: '#22C55E' },
            ];
            const total = rows.reduce((s, r) => s + r.value, 0) || 1;
            return rows.map(r => ({ ...r, pct: Math.round((r.value / total) * 1000) / 10 }));
        },
        botsDonutStyle() {
            const rows = this.actionRows();
            const total = rows.reduce((a, r) => a + r.value, 0);
            if (!total) return { background: 'conic-gradient(rgba(100,0,178,0.35) 0 100%)' };
            let deg = 0;
            const stops = rows.map(r => {
                const span = (r.value / total) * 360;
                const start = deg;
                deg += span;
                return `${r.color} ${start}deg ${deg}deg`;
            });
            return { background: `conic-gradient(${stops.join(', ')})` };
        },

        get thisWeekInvalid() {
            const ds = (this.invalidTrends.datasets || []).find(d => !d.dashed);
            return (ds?.values || []).reduce((a, b) => a + Number(b || 0), 0);
        },
        get lastWeekInvalid() {
            const ds = (this.invalidTrends.datasets || []).find(d => d.dashed);
            return (ds?.values || []).reduce((a, b) => a + Number(b || 0), 0);
        },
        get botActivityTotal() {
            const vals = this.cache.ib?.values || [];
            return vals.reduce((a, b) => a + Number(b || 0), 0);
        },
        barPct(kind) {
            // Figma export (v266_674 230px, v266_676 270px, v290_369 127px in 287px cards)
            const figmaPct = { valid: 80, invalid: 94, crawler: 44 };
            const total = Math.max(Number(this.summary.total_visits || 0), 1);
            const map = {
                valid: Number(this.summary.valid_visits || 0),
                invalid: Number(this.summary.invalid_bot_visits || 0),
                crawler: Number(this.summary.known_crawlers || 0),
            };
            const live = Math.round((map[kind] / total) * 100);
            return Math.max(12, Math.min(94, Math.round((figmaPct[kind] * 0.65) + (live * 0.35))));
        },
        qs() {
            const p = new URLSearchParams();
            if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
            if (this.filters.traffic_source) p.set('traffic_source', this.filters.traffic_source);
            if (this.filters.google_ads_account_id) p.set('google_ads_account_id', this.filters.google_ads_account_id);
            if (this.filters.campaign) p.set('campaign', this.filters.campaign);
            if (this.filters.path) p.set('path', this.filters.path);
            if (this.filters.from) p.set('from', this.filters.from);
            if (this.filters.to) p.set('to', this.filters.to);
            return p.toString();
        },
        reloadTimer: null,
        debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
        scheduleReload() {
            clearTimeout(this.reloadTimer);
            this.reloadTimer = setTimeout(() => this.reload(), this.debounceMs);
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
            this.reload();
        },
        async init() {
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const today = new Date();
                const start = new Date(today.getTime() - 6 * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = today.toISOString().slice(0, 10);
            }
            await this.reload();
            window.addEventListener('promotix:date-range', () => {
                this.syncHeaderDates();
                this.scheduleReload();
            });
            window.addEventListener('resize', () => {
                clearTimeout(window.__bpFigmaResize);
                window.__bpFigmaResize = setTimeout(() => this.renderCharts(), 180);
            });
        },
        applyDemoPayload() {
            const demo = buildBpDemoPayload();
            this.summary = demo.summary;
            this.invalidTrends = demo.invalidTrends;
            this.countries = demo.countries;
            this.domainsList = demo.domainsList || [];
            this.cache = demo.cache;
        },
        donutTotal(values) {
            return (values || []).reduce((a, b) => a + Number(b || 0), 0);
        },
        areaLegendItems() {
            const defaults = [
                { name: 'Valid Visits', color: '#FFFFFF' },
                { name: 'Bad Bots', color: '#0D0D0D' },
                { name: 'Crawler', color: '#6625F8' },
                { name: 'Invalid', color: '#FF4BC1' },
                { name: 'Total Visits', color: '#B893D8', line: true },
            ];
            const datasets = this.cache?.traffic?.datasets || [];
            return datasets.length ? datasets : defaults;
        },
        invalidLegendItems() {
            const defaults = [
                { name: 'Invalid Pageloads', color: '#6625F8' },
                { name: 'Invalid Site Interaction', color: '#FF4BC1', dashed: true },
            ];
            const datasets = this.invalidTrends?.datasets || [];
            return datasets.length ? datasets : defaults;
        },
        legendSwatchStyle(ds) {
            const color = ds?.color || '#FFFFFF';
            if (String(color).toLowerCase() === '#0d0d0d') {
                return 'background:#0D0D0D;border:1px solid rgba(255,255,255,.25)';
            }
            return `background:${color}`;
        },
        isSeriesHidden(chartKey, name) {
            return Boolean(this.hiddenSeries?.[chartKey]?.[name]);
        },
        toggleChartSeries(chartKey, name) {
            const chart = this.charts?.[chartKey];
            if (!chart || !name) return;
            if (!this.hiddenSeries[chartKey]) this.hiddenSeries[chartKey] = {};
            this.hiddenSeries[chartKey][name] = !this.hiddenSeries[chartKey][name];
            chart.toggleSeries(name);
        },
        applyHiddenSeries(chartKey) {
            const chart = this.charts?.[chartKey];
            const hidden = this.hiddenSeries?.[chartKey] || {};
            if (!chart) return;
            Object.entries(hidden).forEach(([name, isHidden]) => {
                if (!isHidden) return;
                const idx = chart.w?.globals?.seriesNames?.indexOf(name);
                const collapsed = chart.w?.globals?.collapsedSeriesIndices || [];
                if (idx >= 0 && !collapsed.includes(idx)) {
                    chart.toggleSeries(name);
                }
            });
        },
        isDonutSegmentHidden(group, index) {
            return Boolean(this.hiddenDonutSegments?.[group]?.[index]);
        },
        toggleDonutSegment(group, index) {
            if (!this.hiddenDonutSegments[group]) this.hiddenDonutSegments[group] = {};
            this.hiddenDonutSegments[group][index] = !this.hiddenDonutSegments[group][index];
        },
        visibleDonutValues(values, group) {
            const hidden = this.hiddenDonutSegments?.[group] || {};
            return (values || []).map((v, i) => (hidden[i] ? 0 : Number(v || 0)));
        },
        donutRingStyle(values, group = null) {
            const data = group
                ? this.visibleDonutValues(values, group)
                : (values || []).map(v => Number(v || 0));
            const total = data.reduce((a, b) => a + b, 0);
            if (!total) {
                return { background: 'conic-gradient(rgba(255,255,255,0.35) 0deg 360deg)' };
            }
            let deg = 0;
            const stops = data.map((v, i) => {
                const span = (v / total) * 360;
                const start = deg;
                deg += span;
                const color = this.donutPalette[i % this.donutPalette.length];
                return `${color} ${start}deg ${deg}deg`;
            });
            return { background: `conic-gradient(${stops.join(', ')})` };
        },
        donutFooter(block) {
            const total = this.donutTotal(block?.values);
            if (!total) return 'No data';
            const labels = block?.labels || [];
            if (labels.length === 1) return `${this.threatLabel(labels[0])}: ${total}`;
            return `Total ${this.fmt(total)}`;
        },
        donutLegendLine(label, value) {
            const name = this.threatLabel(label);
            return `${name}: ${Number(value || 0)}`;
        },
        dataIsEmpty() {
            return Number(this.summary?.total_visits || 0) === 0;
        },
        async reload() {
            window.promotixPageLoader?.show('Loading Bot Protection…');
            try {
                if (this.useDemo) {
                    this.applyDemoPayload();
                    this.$nextTick(() => this.renderCharts());
                    return;
                }

                const qs = this.qs();

                // 1) Top cards first — hide loader as soon as the viewport KPIs can paint.
                const summary = await fetch(`/bot-protection/summary?${qs}`).then(r => r.json());
                this.summary = summary;
                await this.$nextTick();
                window.promotixPageLoader?.hide();

                // 2) Remaining panels load in the background.
                const [traffic, trends, th, ib, c, ds] = await Promise.all([
                    fetch(`/bot-protection/traffic-breakdown?${qs}`).then(r => r.json()),
                    fetch(`/bot-protection/invalid-traffic-trends?${qs}`).then(r => r.json()),
                    fetch(`/bot-protection/threat-groups?${qs}`).then(r => r.json()),
                    fetch(`/bot-protection/invalid-breakdown?${qs}`).then(r => r.json()),
                    fetch(`/bot-protection/countries?${qs}`).then(r => r.json()),
                    fetch(`/bot-protection/domains-summary?${qs}`).then(r => r.json()),
                ]);
                this.invalidTrends = trends;
                this.countries = c;
                this.domainsList = Array.isArray(ds) ? ds : [];
                this.cache = {
                    traffic,
                    th,
                    ib: ib?.invalid_bot ?? { labels: [], values: [] },
                    mal: ib?.invalid_malicious ?? { labels: [], values: [] },
                    reasons: ib?.reasons ?? { labels: [], values: [] },
                    malicious_reasons: ib?.malicious_reasons ?? { labels: [], values: [] },
                };
                if (this.useDemo && this.dataIsEmpty()) {
                    this.applyDemoPayload();
                }
                this.$nextTick(() => this.renderCharts());
            } catch (e) {
                console.error(e);
            } finally {
                window.promotixPageLoader?.hide();
            }
        },
        destroyChart(key) {
            if (this.charts[key]) {
                this.charts[key].destroy();
                delete this.charts[key];
            }
        },
        bpApexGrid() {
            return {
                borderColor: 'rgba(255,255,255,0.08)',
                strokeDashArray: 4,
                xaxis: { lines: { show: true } },
                yaxis: { lines: { show: true } },
                padding: { top: 0, right: 8, bottom: 0, left: 8 },
            };
        },
        bpYAxisCap(peak) {
            const value = Math.max(Number(peak) || 0, 1);
            if (value <= 10) return 10;
            if (value <= 50) return Math.ceil(value / 5) * 5;
            if (value <= 200) return Math.ceil(value / 20) * 20;
            if (value <= 1000) return Math.ceil(value / 100) * 100;
            if (value <= 5000) return Math.ceil(value / 500) * 500;
            return Math.ceil(value / 1000) * 1000;
        },
        bpYAxisStep(cap) {
            if (cap <= 10) return 2;
            if (cap <= 50) return 10;
            if (cap <= 200) return 40;
            if (cap <= 1000) return 200;
            if (cap <= 5000) return 1000;
            return Math.max(1000, cap / 5);
        },
        bpApexYAxis(maxY) {
            const cap = this.bpYAxisCap(maxY);
            const step = this.bpYAxisStep(cap);
            return {
                min: 0,
                max: cap,
                tickAmount: Math.max(2, Math.round(cap / step)),
                labels: {
                    style: { colors: 'rgba(255,255,255,0.45)', fontSize: '8px' },
                    formatter: (v) => (v >= 1000 ? `${Math.round(v / 1000)}k` : Math.round(v)),
                },
            };
        },
        alignSeriesValues(values, length) {
            const out = (values || []).map(v => Number(v || 0));
            while (out.length < length) out.push(0);
            return out.slice(0, length);
        },
        renderAreaChart() {
            const el = document.getElementById('bp-area-chart');
            if (!el || !window.ApexCharts) return;
            this.destroyChart('area');

            const traffic = this.cache?.traffic ?? { labels: [], datasets: [] };
            const labels = traffic.labels || [];
            const datasets = traffic.datasets || [];
            if (!labels.length) return;

            const areas = datasets.filter(d => !d.line);
            const lineDs = datasets.find(d => d.line);
            const allValues = datasets.flatMap(d => this.alignSeriesValues(d.values, labels.length));
            const maxY = Math.max(...allValues, 0);
            const sparse = labels.length <= 4;
            const fillOpacity = {
                '#FFFFFF': 0.42,
                '#0D0D0D': 0.58,
                '#6625F8': 0.52,
                '#FF4BC1': 0.46,
            };

            const series = areas.map(ds => ({
                name: ds.name,
                data: this.alignSeriesValues(ds.values, labels.length),
            }));

            const colors = areas.map(ds => ds.color);
            const opacities = areas.map(ds => fillOpacity[ds.color] ?? 0.45);

            if (lineDs) {
                series.push({ name: lineDs.name, data: this.alignSeriesValues(lineDs.values, labels.length) });
                colors.push(lineDs.color || '#B893D8');
                opacities.push(0);
            }

            const strokeWidths = [...areas.map(() => 1.5), ...(lineDs ? [2.5] : [])];

            this.charts.area = new ApexCharts(el, {
                chart: {
                    type: 'area',
                    height: 177,
                    fontFamily: 'inherit',
                    background: 'transparent',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: true, speed: 450 },
                },
                series,
                colors,
                dataLabels: { enabled: false },
                stroke: {
                    curve: sparse ? 'straight' : 'smooth',
                    width: strokeWidths,
                    lineCap: 'round',
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        type: 'vertical',
                        shadeIntensity: 0.35,
                        opacityFrom: 0.65,
                        opacityTo: 0.04,
                        stops: [0, 88, 100],
                    },
                    opacity: opacities,
                },
                grid: this.bpApexGrid(),
                xaxis: {
                    categories: labels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: 'rgba(255,255,255,0.55)', fontSize: '9px' },
                    },
                    crosshairs: {
                        show: true,
                        stroke: { color: 'rgba(255,255,255,0.25)', width: 1, dashArray: 4 },
                    },
                },
                yaxis: this.bpApexYAxis(maxY),
                legend: { show: false },
                tooltip: {
                    theme: 'dark',
                    shared: true,
                    intersect: false,
                    style: { fontSize: '11px' },
                    y: { formatter: (v) => this.fmt(Math.round(v || 0)) },
                },
                markers: {
                    size: 0,
                    strokeWidth: 0,
                    hover: { size: 4, sizeOffset: 1 },
                },
            });
            this.charts.area.render();
            this.applyHiddenSeries('area');
        },
        renderInvalidChart() {
            const el = document.getElementById('bp-invalid-line');
            if (!el || !window.ApexCharts) return;
            this.destroyChart('invalid');

            const labels = this.invalidTrends.labels ?? [];
            const datasets = (this.invalidTrends.datasets ?? []).map(d => ({
                ...d,
                values: this.alignSeriesValues(d.values, labels.length),
            }));
            if (!labels.length) return;

            const primary = datasets.find(d => !d.dashed) || datasets[0];
            const compare = datasets.find(d => d.dashed) || datasets[1];
            const maxY = Math.max(...datasets.flatMap(d => d.values), 0);
            const sparse = labels.length <= 6;
            const showMarkers = labels.length <= 8;

            const series = [];
            const colors = [];
            const strokeWidths = [];
            const dashArrays = [];
            const fillOpacities = [];

            if (primary) {
                series.push({ name: primary.name || 'Invalid Pageloads', data: primary.values });
                colors.push(primary.color || '#6625F8');
                strokeWidths.push(2);
                dashArrays.push(0);
                fillOpacities.push(0.48);
            }
            if (compare) {
                series.push({ name: compare.name || 'Invalid Site Interaction', data: compare.values });
                colors.push(compare.color || '#FF4BC1');
                strokeWidths.push(2);
                dashArrays.push(6);
                fillOpacities.push(0);
            }

            const fmtCompact = this.fmtCompact.bind(this);

            this.charts.invalid = new ApexCharts(el, {
                chart: {
                    type: 'area',
                    height: 210,
                    fontFamily: 'inherit',
                    background: 'transparent',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: true, speed: 450 },
                },
                series,
                colors,
                dataLabels: { enabled: false },
                plotOptions: {
                    area: { fillTo: 'origin' },
                },
                stroke: {
                    curve: sparse ? 'straight' : 'smooth',
                    width: strokeWidths,
                    dashArray: dashArrays,
                    lineCap: 'round',
                    show: true,
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        type: 'vertical',
                        shadeIntensity: 0.4,
                        opacityFrom: 0.55,
                        opacityTo: 0.03,
                        stops: [0, 92, 100],
                    },
                    opacity: fillOpacities,
                },
                grid: this.bpApexGrid(),
                xaxis: {
                    categories: labels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: '#9D9D9D', fontSize: '9px' },
                    },
                    crosshairs: {
                        show: true,
                        stroke: { color: 'rgba(255,255,255,0.45)', width: 1, dashArray: 4 },
                    },
                },
                yaxis: this.bpApexYAxis(maxY),
                legend: { show: false },
                tooltip: {
                    theme: 'dark',
                    shared: true,
                    intersect: false,
                    custom: ({ series, dataPointIndex, w }) => {
                        const label = w.globals.labels[dataPointIndex] || '';
                        const rows = (w.globals.seriesNames || []).map((name, idx) => {
                            const color = colors[idx] || '#6625F8';
                            const val = Number(series[idx]?.[dataPointIndex] || 0);
                            return `<span><i style="background:${color}"></i>${name}: ${fmtCompact(val)}</span>`;
                        }).join('');
                        return `<div class="figma-bp-apex-tooltip"><strong>${label}</strong>${rows}</div>`;
                    },
                },
                markers: {
                    size: showMarkers ? 4 : 0,
                    strokeWidth: 2,
                    strokeColors: colors,
                    fillOpacity: 1,
                    hover: { size: 7, sizeOffset: 2 },
                },
            });
            this.charts.invalid.render();
            this.applyHiddenSeries('invalid');
        },
        renderCharts() {
            // Apex charts from older layout removed; KPI/panel UI is Alpine-driven.
        },
    };
}
</script>
@endsection
