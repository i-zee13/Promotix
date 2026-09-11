@extends('layouts.admin')

@section('title', 'Analytics | Traffic Control')

@section('rightbar')
<div class="figma-rightbar-default paid-rightbar">
    @include('partials.figma-rightbar-header-actions')
    @include('partials.figma-rightbar-analytics')
</div>
@endsection

@section('content')
<div
    class="brand-page-bg analytics-skin min-h-[calc(100vh-49px)]"
    x-data="trafficControlIntel()"
    x-init="init()"
    @promotix:date-range.window="onDateRange($event)"
>
    <section class="mx-auto w-full min-w-0 px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <style>
            .tc-page { color: rgba(255,255,255,.88); }
            .tc-head {
                display: flex; flex-direction: column; gap: 14px;
                margin-bottom: 18px; min-width: 0;
            }
            @media (min-width: 1100px) {
                .tc-head { flex-direction: row; align-items: flex-start; justify-content: space-between; gap: 16px; }
            }
            .tc-title-row {
                display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
            }
            .tc-title {
                font-size: 28px; font-weight: 650; letter-spacing: -.02em; color: #fff; line-height: 1.1;
            }
            @media (min-width: 640px) { .tc-title { font-size: 32px; } }
            .tc-title__pipe { color: rgba(255,255,255,.35); font-weight: 500; margin: 0 2px; }
            .tc-title__muted { color: #a9a9a9; }
            .tc-subtitle {
                margin-top: 10px; max-width: 560px;
                font-size: 13px; line-height: 1.45; color: rgba(255,255,255,.45);
            }
            .tc-filters {
                display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px;
            }
            .tc-filter {
                position: relative; min-width: 132px;
            }
            .tc-filter__select,
            .tc-filter__date {
                appearance: none; -webkit-appearance: none;
                height: 38px; width: 100%;
                border-radius: 8px;
                border: 1px solid rgba(255,255,255,.16);
                background: #101010;
                color: rgba(255,255,255,.82);
                font-size: 12px; font-weight: 500;
                padding: 0 32px 0 12px;
                outline: none;
            }
            .tc-filter__select:focus,
            .tc-filter__date:focus { border-color: rgba(255,102,0,.55); }
            .tc-filter__chev {
                pointer-events: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
                width: 12px; height: 12px; color: rgba(255,255,255,.45);
            }
            .tc-filter__date-wrap { position: relative; min-width: 168px; }
            .tc-filter__date {
                display: inline-flex; align-items: center; gap: 8px; cursor: pointer; padding-right: 12px;
                white-space: nowrap;
            }
            .tc-filter__date svg { width: 14px; height: 14px; color: rgba(255,255,255,.5); margin-left: auto; }
            .tc-export {
                display: inline-flex; align-items: center; gap: 8px;
                height: 38px; padding: 0 14px; border-radius: 8px;
                border: 1.5px solid #FF6600; background: transparent;
                color: #FF6600; font-size: 12px; font-weight: 650; white-space: nowrap;
            }
            .tc-export:hover { background: rgba(255,102,0,.12); }

            .tc-kpi-grid {
                display: grid; grid-template-columns: repeat(2, minmax(0,1fr));
                gap: 12px; margin-bottom: 16px;
            }
            @media (min-width: 900px) { .tc-kpi-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
            @media (min-width: 1280px) { .tc-kpi-grid { grid-template-columns: repeat(6, minmax(0,1fr)); } }
            .tc-kpi {
                border-radius: 10px; background: #121212;
                border: 1px solid rgba(255,255,255,.08);
                padding: 12px 12px 10px; min-height: 138px;
                display: flex; flex-direction: column;
            }
            .tc-kpi.is-green { border-color: rgba(34,197,94,.35); }
            .tc-kpi.is-orange { border-color: rgba(255,102,0,.4); }
            .tc-kpi.is-pink { border-color: rgba(244,63,94,.4); }
            .tc-kpi.is-yellow { border-color: rgba(234,179,8,.4); }
            .tc-kpi.is-red { border-color: rgba(239,68,68,.4); }
            .tc-kpi.is-purple { border-color: rgba(168,85,247,.4); }
            .tc-kpi__icon {
                width: 28px; height: 28px; border-radius: 7px;
                display: inline-flex; align-items: center; justify-content: center;
                margin-bottom: 8px; border: 1px solid currentColor;
            }
            .tc-kpi.is-green .tc-kpi__icon { color: #4ade80; background: rgba(34,197,94,.12); }
            .tc-kpi.is-orange .tc-kpi__icon { color: #ff944d; background: rgba(255,102,0,.12); }
            .tc-kpi.is-pink .tc-kpi__icon { color: #fb7185; background: rgba(244,63,94,.12); }
            .tc-kpi.is-yellow .tc-kpi__icon { color: #facc15; background: rgba(234,179,8,.12); }
            .tc-kpi.is-red .tc-kpi__icon { color: #f87171; background: rgba(239,68,68,.12); }
            .tc-kpi.is-purple .tc-kpi__icon { color: #c084fc; background: rgba(168,85,247,.12); }
            .tc-kpi__label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.5); margin-bottom: 6px; }
            .tc-kpi__value { font-size: 26px; font-weight: 700; color: #fff; letter-spacing: -.02em; line-height: 1.05; }
            .tc-kpi__delta { margin-top: 6px; font-size: 10px; display: flex; gap: 5px; align-items: baseline; flex-wrap: wrap; }
            .tc-kpi__delta-num { font-weight: 700; }
            .tc-kpi.is-green .tc-kpi__delta-num { color: #4ade80; }
            .tc-kpi.is-orange .tc-kpi__delta-num { color: #FF6600; }
            .tc-kpi.is-pink .tc-kpi__delta-num,
            .tc-kpi.is-red .tc-kpi__delta-num { color: #f87171; }
            .tc-kpi.is-yellow .tc-kpi__delta-num { color: #facc15; }
            .tc-kpi.is-purple .tc-kpi__delta-num { color: #c084fc; }
            .tc-kpi__delta-vs { color: rgba(255,255,255,.35); }
            .tc-kpi__spark { margin-top: auto; padding-top: 8px; height: 34px; }

            .tc-main {
                display: grid; grid-template-columns: minmax(0,1fr); gap: 14px; margin-bottom: 16px;
            }
            @media (min-width: 1180px) {
                .tc-main { grid-template-columns: minmax(0,1fr) 330px; align-items: start; }
                .tc-main.is-panel-closed { grid-template-columns: minmax(0,1fr); }
            }
            .tc-card {
                border-radius: 12px; border: 1px solid rgba(255,102,0,.22);
                background: #121212; overflow: hidden; min-width: 0;
            }
            .tc-card__pad { padding: 14px 14px 0; }
            .tc-card__title {
                font-size: 15px; font-weight: 650; color: #fff;
                display: inline-flex; align-items: center; gap: 7px;
            }
            .tc-tabs {
                display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
                padding: 12px 14px;
            }
            .tc-tab {
                border-radius: 8px; border: 1px solid rgba(255,255,255,.14);
                background: transparent; color: rgba(255,255,255,.55);
                font-size: 12px; font-weight: 600; padding: 7px 12px; white-space: nowrap;
            }
            .tc-tab.is-active { background: #FF6600; border-color: #FF6600; color: #fff; }
            .tc-search { margin-left: auto; min-width: 210px; max-width: 280px; flex: 1 1 210px; position: relative; }
            .tc-search input {
                width: 100%; height: 34px; border-radius: 8px;
                border: 1px solid rgba(255,255,255,.14); background: #0b0b0b;
                color: #ddd; font-size: 12px; padding: 0 12px 0 34px;
            }
            .tc-search svg {
                position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
                width: 14px; height: 14px; color: rgba(255,255,255,.4);
            }
            .tc-table-wrap { overflow-x: auto; padding: 0 10px 8px; }
            .tc-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; min-width: 1020px; }
            .tc-table th {
                text-align: left; font-size: 10px; font-weight: 650; letter-spacing: .04em;
                text-transform: uppercase; color: rgba(255,255,255,.42); padding: 8px 10px; white-space: nowrap;
            }
            .tc-table td {
                background: #181818; padding: 12px 10px; font-size: 12px; color: rgba(255,255,255,.88);
                vertical-align: middle; border-top: 1px solid rgba(255,255,255,.04); border-bottom: 1px solid rgba(255,255,255,.04);
            }
            .tc-table tr.is-selected td { background: #221a14; border-color: rgba(255,102,0,.28); }
            .tc-table tr td:first-child { border-left: 1px solid rgba(255,255,255,.04); border-radius: 10px 0 0 10px; }
            .tc-table tr td:last-child { border-right: 1px solid rgba(255,255,255,.04); border-radius: 0 10px 10px 0; }
            .tc-table tr.is-selected td:first-child { border-left-color: rgba(255,102,0,.45); }
            .tc-table tr.is-selected td:last-child { border-right-color: rgba(255,102,0,.45); }
            .tc-ip-count { color: rgba(255,255,255,.55); font-size: 11px; margin-right: 6px; white-space: nowrap; }
            .tc-ip-pill {
                display: inline-flex; align-items: center; border-radius: 999px;
                border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.04);
                color: rgba(255,255,255,.72); font-size: 10px; padding: 2px 8px; margin: 0 4px 4px 0;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            }
            .tc-risk-ring {
                width: 34px; height: 34px; border-radius: 999px; display: grid; place-items: center;
                position: relative;
            }
            .tc-risk-ring__inner {
                width: 26px; height: 26px; border-radius: 999px; background: #181818;
                display: grid; place-items: center; font-size: 11px; font-weight: 750; z-index: 1;
            }
            .tc-status {
                display: inline-flex; align-items: center; border-radius: 999px;
                border: 1px solid currentColor; font-size: 11px; font-weight: 650;
                padding: 3px 10px; white-space: nowrap;
            }
            .tc-status.is-high { color: #f87171; background: rgba(239,68,68,.12); }
            .tc-status.is-suspicious { color: #fb923c; background: rgba(255,102,0,.12); }
            .tc-status.is-watch { color: rgba(255,255,255,.55); background: rgba(255,255,255,.05); }
            .tc-investigate {
                display: inline-flex; align-items: center; height: 30px; padding: 0 12px;
                border-radius: 8px; border: 1px solid #FF6600; color: #FF6600;
                background: transparent; font-size: 11px; font-weight: 650;
            }
            .tc-investigate:hover { background: rgba(255,102,0,.12); }
            .tc-foot {
                padding: 4px 14px 14px; font-size: 12px; color: rgba(255,255,255,.42);
            }

            .tc-detail {
                border-radius: 12px; border: 1px solid rgba(255,102,0,.22);
                background: #121212; padding: 14px; position: sticky; top: 72px;
            }
            .tc-detail__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
            .tc-detail__title { font-size: 14px; font-weight: 650; color: #fff; }
            .tc-detail__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
            .tc-gauge {
                width: 112px; height: 112px; border-radius: 999px; display: grid; place-items: center; flex-shrink: 0;
            }
            .tc-gauge__inner {
                width: 82px; height: 82px; border-radius: 999px; background: #121212;
                display: grid; place-items: center; text-align: center; z-index: 1;
            }
            .tc-gauge__score { font-size: 26px; font-weight: 750; line-height: 1; }
            .tc-gauge__max { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 2px; }
            .tc-sec-title { font-size: 12px; font-weight: 650; color: #fff; margin: 12px 0 6px; }
            .tc-history-item {
                display: grid; grid-template-columns: 10px 1fr auto; gap: 10px; align-items: start;
                padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.06);
            }
            .tc-dot { width: 8px; height: 8px; border-radius: 999px; margin-top: 5px; }
            .tc-dot.is-high { background: #ef4444; }
            .tc-dot.is-suspicious { background: #FF6600; }
            .tc-dot.is-watch, .tc-dot.is-medium { background: #eab308; }
            .tc-dot.is-clean { background: #22c55e; }
            .tc-reason {
                display: flex; align-items: center; justify-content: space-between; gap: 8px;
                padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.06);
                font-size: 12px; color: rgba(255,255,255,.82);
            }
            .tc-reason__left { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
            .tc-reason__icon {
                width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0;
                display: inline-flex; align-items: center; justify-content: center;
                background: rgba(255,102,0,.12); color: #FF6600;
            }
            .tc-level { font-size: 10px; font-weight: 700; border-radius: 999px; padding: 2px 8px; border: 1px solid currentColor; }
            .tc-level.is-High { color: #f87171; background: rgba(239,68,68,.12); }
            .tc-level.is-Medium { color: #fb923c; background: rgba(255,102,0,.12); }
            .tc-level.is-Low { color: rgba(255,255,255,.55); background: rgba(255,255,255,.06); }

            .tc-charts { display: grid; grid-template-columns: 1fr; gap: 12px; }
            @media (min-width: 900px) { .tc-charts { grid-template-columns: repeat(2, minmax(0,1fr)); } }
            @media (min-width: 1280px) { .tc-charts { grid-template-columns: repeat(4, minmax(0,1fr)); } }
            .tc-chart {
                border-radius: 12px; border: 1px solid rgba(255,102,0,.28);
                background: #121212; padding: 14px; min-height: 250px;
            }
            .tc-chart__title { font-size: 13px; font-weight: 650; color: #fff; margin-bottom: 14px; }
            .tc-hbar {
                display: grid; grid-template-columns: 92px 1fr 28px; gap: 8px; align-items: center;
                margin-bottom: 10px; font-size: 11px; color: rgba(255,255,255,.65);
            }
            .tc-hbar__track { height: 9px; border-radius: 999px; background: rgba(255,255,255,.06); overflow: hidden; position: relative; }
            .tc-hbar__fill { height: 100%; border-radius: 999px; }
            .tc-axis {
                display: flex; justify-content: space-between; margin: 4px 0 0 100px; padding-right: 28px;
                font-size: 9px; color: rgba(255,255,255,.3);
            }
            .tc-donut-wrap { display: flex; align-items: center; gap: 14px; }
            .tc-donut {
                width: 132px; height: 132px; border-radius: 999px; display: grid; place-items: center; flex-shrink: 0;
            }
            .tc-donut__hole {
                width: 78px; height: 78px; border-radius: 999px; background: #121212;
                display: grid; place-items: center; text-align: center; z-index: 1;
            }
            .tc-legend { display: flex; flex-direction: column; gap: 7px; font-size: 11px; color: rgba(255,255,255,.7); min-width: 0; }
            .tc-legend-row { display: flex; align-items: center; gap: 8px; }
            .tc-legend-swatch { width: 8px; height: 8px; border-radius: 999px; flex-shrink: 0; }
            .tc-legend-meta { margin-left: auto; color: rgba(255,255,255,.4); white-space: nowrap; }
            .tc-empty { padding: 28px 16px; text-align: center; color: rgba(255,255,255,.4); font-size: 13px; }
        </style>

        <div class="tc-page">
            {{-- Header --}}
            <div class="tc-head">
                <div class="min-w-0">
                    <div class="tc-title-row">
                        <h1 class="tc-title">
                            <span class="tc-title__muted">Analytics</span>
                            <span class="tc-title__pipe">|</span>
                            <span>Traffic Control</span>
                        </h1>
                    </div>
                    <p class="tc-subtitle">Detect repeated devices, IP rotation and suspicious network activity from Google Ads.</p>
                </div>

                <div class="tc-filters">
                    <div class="tc-filter" style="min-width:140px">
                        <select x-model="filters.domain_id" @change="reload()" class="tc-filter__select">
                            <option value="">All Domains</option>
                            @foreach ($domains as $d)
                                <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                            @endforeach
                        </select>
                        <svg class="tc-filter__chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="tc-filter" style="min-width:148px">
                        <select x-model="filters.campaign" @change="reload()" class="tc-filter__select">
                            <option value="">All Campaigns</option>
                            <template x-for="c in campaignOptions" :key="'camp-' + c">
                                <option :value="c" x-text="c"></option>
                            </template>
                        </select>
                        <svg class="tc-filter__chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="tc-filter" style="min-width:132px">
                        <select x-model="filters.path" @change="reload()" class="tc-filter__select">
                            <option value="">All Landing Pages</option>
                            <template x-for="p in pathOptions" :key="'path-' + p">
                                <option :value="p" x-text="p"></option>
                            </template>
                        </select>
                        <svg class="tc-filter__chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div
                        class="tc-filter__date-wrap"
                        x-data="figmaDateRangePicker"
                        x-init="init()"
                        @click.outside="if (calendarOpen && !isMobile()) cancelCalendar()"
                    >
                        <button type="button" class="tc-filter__date" @click="toggleCalendar()" :aria-expanded="calendarOpen">
                            <span x-text="$root.prettyRange()"></span>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2" stroke-width="1.6"/><path stroke-width="1.6" stroke-linecap="round" d="M8 3v3M16 3v3M4 10h16"/></svg>
                        </button>
                        @include('partials.figma-date-range-popover')
                    </div>
                    <button type="button" class="tc-export" @click="exportReport()">
                        <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                        Export Report
                    </button>
                </div>
            </div>

            {{-- KPIs --}}
            <div class="tc-kpi-grid">
                <template x-for="kpi in kpis" :key="kpi.key">
                    <div class="tc-kpi" :class="'is-' + kpi.tone">
                        <div class="tc-kpi__icon" x-html="kpiIcon(kpi.key)"></div>
                        <div class="tc-kpi__label" x-text="kpi.label"></div>
                        <div class="tc-kpi__value" x-text="fmtNum(kpi.value)"></div>
                        <div class="tc-kpi__delta">
                            <span class="tc-kpi__delta-num" x-text="(Number(kpi.delta) >= 0 ? '↑ ' : '↓ ') + Math.abs(Number(kpi.delta || 0)).toFixed(1) + '%'"></span>
                            <span class="tc-kpi__delta-vs" x-text="kpi.vs_label || 'vs previous period'"></span>
                        </div>
                        <div class="tc-kpi__spark" x-html="sparkSvg(kpi.spark || [], kpi.tone)"></div>
                    </div>
                </template>
            </div>

            {{-- Table + detail --}}
            <div class="tc-main" :class="{ 'is-panel-closed': !selected }">
                <div class="tc-card">
                    <div class="tc-card__pad">
                        <div class="tc-card__title">
                            Device &amp; IP Intelligence
                            <svg class="h-[14px] w-[14px] text-white/35" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><path stroke-linecap="round" stroke-width="1.6" d="M12 11v5M12 8h.01"/></svg>
                        </div>
                    </div>
                    <div class="tc-tabs">
                        <template x-for="tab in tabs" :key="tab.key">
                            <button type="button" class="tc-tab" :class="{ 'is-active': activeTab === tab.key }" @click="activeTab = tab.key" x-text="tab.label"></button>
                        </template>
                        <div class="tc-search">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="search" x-model="filters.q" @input="scheduleSearch()" placeholder="Search Device ID or IP...">
                        </div>
                    </div>

                    <div class="tc-table-wrap" x-show="activeTab !== 'ranges'">
                        <table class="tc-table">
                            <thead>
                                <tr>
                                    <th x-text="activeTab === 'reputation' ? 'IP Address' : 'Device ID'"></th>
                                    <th>IPs Used</th>
                                    <th>IP Changes</th>
                                    <th>Google Ads Clicks</th>
                                    <th>Risk Score</th>
                                    <th>Last Seen</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in tableRows" :key="(row.device_key || row.device_id) + '-' + (row.ips?.[0] || '')">
                                    <tr :class="{ 'is-selected': isRowSelected(row) }">
                                        <td class="font-mono text-[11px]" x-text="activeTab === 'reputation' ? (row.ips?.[0] || '—') : row.device_id"></td>
                                        <td>
                                            <div class="flex flex-wrap items-center">
                                                <span class="tc-ip-count" x-text="(row.ip_count || 0) + ' IPs'"></span>
                                                <template x-for="(ip, idx) in (row.ips || []).slice(0, 2)" :key="ip + idx">
                                                    <span class="tc-ip-pill" x-text="ip"></span>
                                                </template>
                                                <span class="tc-ip-pill" x-show="(row.ip_count || 0) > 2" x-text="'+' + ((row.ip_count || 0) - 2)"></span>
                                            </div>
                                        </td>
                                        <td x-text="row.ip_changes ?? 0"></td>
                                        <td x-text="fmtNum(row.clicks || 0)"></td>
                                        <td>
                                            <div class="tc-risk-ring" :style="miniGauge(row.risk_score || 0)">
                                                <div class="tc-risk-ring__inner" :style="'color:' + riskColor(row.risk_score || 0)" x-text="row.risk_score"></div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap text-white/55" x-text="fmtWhen(row.last_seen)"></td>
                                        <td>
                                            <span class="tc-status" :class="'is-' + (row.status_tone || 'watch')" x-text="row.status"></span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <button type="button" class="tc-investigate" @click="selectRow(row)">Investigate</button>
                                                <button type="button" class="px-1 text-white/35 hover:text-white/70" aria-label="More">⋮</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div class="tc-empty" x-show="!loading && tableRows.length === 0">No matching devices for this filter range.</div>
                        <div class="tc-empty" x-show="loading">Loading intelligence…</div>
                    </div>

                    <div class="p-[14px]" x-show="activeTab === 'ranges'">
                        <template x-for="item in (charts.suspicious_ranges || [])" :key="'tab-' + item.label">
                            <div class="tc-hbar">
                                <div class="truncate font-mono text-[10px]" x-text="item.label"></div>
                                <div class="tc-hbar__track"><div class="tc-hbar__fill bg-[#ef4444]" :style="'width:' + barPct(item.value, maxRange) + '%'"></div></div>
                                <div class="text-right text-white/55" x-text="item.value"></div>
                            </div>
                        </template>
                        <div class="tc-empty" x-show="!(charts.suspicious_ranges || []).length">No suspicious ranges detected.</div>
                    </div>

                    <div class="tc-foot" x-show="activeTab !== 'ranges' && tableRows.length" x-text="footerLabel()"></div>
                </div>

                <aside class="tc-detail" x-show="selected" x-cloak>
                    <div class="tc-detail__head">
                        <div class="tc-detail__title">Selected Device Intelligence</div>
                        <button type="button" class="text-white/40 hover:text-white" @click="selected = null" aria-label="Close">✕</button>
                    </div>

                    <div class="tc-detail__top">
                        <div class="min-w-0">
                            <div class="mb-[4px] text-[10px] uppercase tracking-wide text-white/40">Device ID</div>
                            <div class="flex items-center gap-2">
                                <div class="truncate font-mono text-[12px] text-white" x-text="selected?.device_id"></div>
                                <button type="button" class="text-white/35 hover:text-white" @click="copyId(selected?.device_id)" title="Copy">
                                    <svg class="h-[13px] w-[13px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2" stroke-width="1.6"/><path stroke-width="1.6" d="M5 15V5a2 2 0 012-2h10"/></svg>
                                </button>
                            </div>
                            <div class="mt-[10px]">
                                <span class="tc-status" :class="'is-' + (selected?.status_tone || 'watch')" x-text="selected?.status"></span>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="tc-gauge" :style="gaugeStyle(selected?.risk_score || 0)">
                                <div class="tc-gauge__inner">
                                    <div class="tc-gauge__score" :style="'color:' + riskColor(selected?.risk_score || 0)" x-text="selected?.risk_score || 0"></div>
                                    <div class="tc-gauge__max">/ 100</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tc-sec-title" x-text="'IP Change History (' + ((selected?.ip_history || []).length || selected?.ip_count || 0) + ' IPs)'"></div>
                    <div class="mb-[8px]">
                        <template x-for="item in (selected?.ip_history || [])" :key="item.ip + item.at">
                            <div class="tc-history-item">
                                <span class="tc-dot" :class="'is-' + (item.tone || 'watch')"></span>
                                <div class="font-mono text-[12px] text-white" x-text="item.ip"></div>
                                <div class="text-[10px] text-white/40 whitespace-nowrap" x-text="fmtWhenShort(item.at)"></div>
                            </div>
                        </template>
                        <div class="text-[12px] text-white/35" x-show="!(selected?.ip_history || []).length">No IP history.</div>
                    </div>

                    <div class="tc-sec-title">Detection Reasons</div>
                    <div>
                        <template x-for="reason in (selected?.reasons || [])" :key="reason.label">
                            <div class="tc-reason">
                                <div class="tc-reason__left">
                                    <span class="tc-reason__icon" x-html="reasonIcon(reason.label)"></span>
                                    <span class="truncate" x-text="reason.label"></span>
                                </div>
                                <span class="tc-level" :class="'is-' + (reason.level || 'Low')" x-text="reason.level"></span>
                            </div>
                        </template>
                    </div>
                </aside>
            </div>

            {{-- Charts — no “What Traffic Control Detects” --}}
            <div class="tc-charts">
                <div class="tc-chart">
                    <div class="tc-chart__title">IP Changes per Device</div>
                    <template x-for="item in (charts.ip_changes_per_device || [])" :key="item.label">
                        <div class="tc-hbar">
                            <div class="truncate" x-text="item.label"></div>
                            <div class="tc-hbar__track"><div class="tc-hbar__fill bg-[#FF6600]" :style="'width:' + barPct(item.value, maxIpChanges) + '%'"></div></div>
                            <div class="text-right text-white/55" x-text="item.value"></div>
                        </div>
                    </template>
                    <div class="tc-axis" x-show="(charts.ip_changes_per_device || []).length">
                        <span>0</span><span x-text="Math.round(maxIpChanges/2)"></span><span x-text="maxIpChanges"></span>
                    </div>
                    <div class="tc-empty" x-show="!(charts.ip_changes_per_device || []).length">No IP change data.</div>
                </div>

                <div class="tc-chart">
                    <div class="tc-chart__title">IP Reputation Breakdown</div>
                    <div class="tc-donut-wrap">
                        <div class="tc-donut" :style="donutStyle">
                            <div class="tc-donut__hole">
                                <div class="text-[18px] font-bold text-white" x-text="fmtNum(charts.reputation?.total || 0)"></div>
                                <div class="text-[10px] text-white/45">IPs</div>
                            </div>
                        </div>
                        <div class="tc-legend">
                            <template x-for="slice in (charts.reputation?.slices || [])" :key="slice.key">
                                <div class="tc-legend-row">
                                    <span class="tc-legend-swatch" :style="'background:' + slice.color"></span>
                                    <span class="truncate" x-text="slice.label"></span>
                                    <span class="tc-legend-meta" x-text="slice.value + ' (' + slicePct(slice) + '%)'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="tc-chart">
                    <div class="tc-chart__title">Suspicious IP Ranges</div>
                    <template x-for="item in (charts.suspicious_ranges || []).slice(0, 6)" :key="'c-' + item.label">
                        <div class="tc-hbar">
                            <div class="truncate font-mono text-[10px]" x-text="item.label"></div>
                            <div class="tc-hbar__track"><div class="tc-hbar__fill bg-[#ef4444]" :style="'width:' + barPct(item.value, maxRange) + '%'"></div></div>
                            <div class="text-right text-white/55" x-text="item.value"></div>
                        </div>
                    </template>
                    <div class="tc-axis" x-show="(charts.suspicious_ranges || []).length">
                        <span>0</span><span x-text="Math.round(maxRange/2)"></span><span x-text="maxRange"></span>
                    </div>
                    <div class="tc-empty" x-show="!(charts.suspicious_ranges || []).length">No ranges.</div>
                </div>

                <div class="tc-chart">
                    <div class="tc-chart__title">Repeat Device Activity (Last 7 Days)</div>
                    <div x-html="areaSvg(charts.repeat_activity || [])"></div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
function trafficControlIntel() {
    const toneStroke = {
        green: '#22C55E',
        orange: '#FF6600',
        pink: '#F43F5E',
        yellow: '#EAB308',
        red: '#EF4444',
        purple: '#A855F7',
    };

    return {
        loading: false,
        searchTimer: null,
        filters: { domain_id: '', campaign: '', path: '', q: '', from: '', to: '' },
        tabs: [
            { key: 'devices', label: 'Repeated Devices' },
            { key: 'ip_changes', label: 'IP Changes' },
            { key: 'reputation', label: 'IP Reputation' },
            { key: 'ranges', label: 'Suspicious Ranges' },
        ],
        activeTab: 'devices',
        kpis: [],
        devices: [],
        ipChanges: [],
        reputationRows: [],
        charts: {
            ip_changes_per_device: [],
            reputation: { total: 0, slices: [] },
            suspicious_ranges: [],
            repeat_activity: [],
        },
        campaignOptions: [],
        pathOptions: [],
        selected: null,
        metaTotal: 0,

        get tableRows() {
            if (this.activeTab === 'ip_changes') return this.ipChanges;
            if (this.activeTab === 'reputation') return this.reputationRows;
            return this.devices;
        },
        get maxIpChanges() {
            return Math.max(1, ...(this.charts.ip_changes_per_device || []).map((i) => Number(i.value || 0)), 1);
        },
        get maxRange() {
            return Math.max(1, ...(this.charts.suspicious_ranges || []).map((i) => Number(i.value || 0)), 1);
        },
        get donutStyle() {
            const slices = this.charts.reputation?.slices || [];
            const total = Math.max(1, Number(this.charts.reputation?.total || 0));
            let cursor = 0;
            const stops = [];
            slices.forEach((s) => {
                const pct = (Number(s.value || 0) / total) * 100;
                if (pct <= 0) return;
                const next = cursor + pct;
                stops.push(`${s.color} ${cursor}% ${next}%`);
                cursor = next;
            });
            if (!stops.length) stops.push('rgba(255,255,255,.08) 0% 100%');
            return `background: conic-gradient(${stops.join(', ')})`;
        },

        init() {
            this.hydrateDates();
            this.reload();
        },
        hydrateDates() {
            try {
                const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
                if (r.from) this.filters.from = r.from;
                if (r.to) this.filters.to = r.to;
            } catch (e) {}
            if (!this.filters.from || !this.filters.to) {
                const to = new Date();
                const from = new Date();
                from.setDate(to.getDate() - 29);
                const fmt = (d) => d.toISOString().slice(0, 10);
                this.filters.from = fmt(from);
                this.filters.to = fmt(to);
            }
        },
        onDateRange(event) {
            const from = event?.detail?.from;
            const to = event?.detail?.to;
            if (!from || !to) return;
            if (this.filters.from === from && this.filters.to === to) return;
            this.filters.from = from;
            this.filters.to = to;
            this.reload();
        },
        queryParams() {
            const p = new URLSearchParams();
            if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
            if (this.filters.campaign) p.set('campaign', this.filters.campaign);
            if (this.filters.path) p.set('path', this.filters.path);
            if (this.filters.q) p.set('q', this.filters.q);
            if (this.filters.from) p.set('from', this.filters.from);
            if (this.filters.to) p.set('to', this.filters.to);
            return p;
        },
        async reload() {
            this.loading = true;
            try {
                const res = await fetch('/bot-protection/traffic-control/intelligence?' + this.queryParams().toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                this.kpis = data.kpis || [];
                this.devices = data.devices || [];
                this.ipChanges = data.ip_changes || [];
                this.reputationRows = data.reputation_rows || [];
                this.charts = data.charts || this.charts;
                this.campaignOptions = data.meta?.campaigns || [];
                this.pathOptions = data.meta?.paths || [];
                this.metaTotal = Number(data.meta?.device_count || this.devices.length || 0);
                if (this.selected) {
                    const match = this.devices.find((d) => d.device_key === this.selected.device_key)
                        || this.ipChanges.find((d) => d.device_key === this.selected.device_key);
                    this.selected = match || (this.devices[0] || null);
                } else if (this.devices[0]) {
                    this.selected = this.devices[0];
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        scheduleSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.reload(), 350);
        },
        selectRow(row) { this.selected = row; },
        isRowSelected(row) {
            if (!this.selected) return false;
            if (this.activeTab === 'reputation') {
                return (this.selected.ips?.[0] || this.selected.ip) === (row.ips?.[0] || row.ip);
            }
            return this.selected.device_key === row.device_key;
        },
        footerLabel() {
            const n = this.tableRows.length;
            const total = this.activeTab === 'devices' ? Math.max(this.metaTotal, n) : n;
            const noun = this.activeTab === 'reputation' ? 'IPs' : 'suspicious devices';
            return `Showing ${n} of ${total} ${noun}`;
        },
        exportReport() {
            window.location.href = '/bot-protection/traffic-control/export.csv?' + this.queryParams().toString();
        },
        copyId(id) {
            if (!id || !navigator.clipboard) return;
            navigator.clipboard.writeText(String(id)).catch(() => {});
        },
        fmtNum(n) { return Number(n || 0).toLocaleString(); },
        prettyRange() {
            const fmt = (iso) => {
                if (!iso) return '';
                const d = new Date(`${iso}T12:00:00`);
                if (Number.isNaN(d.getTime())) return iso;
                return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            };
            if (!this.filters.from || !this.filters.to) return 'Date range';
            if (this.filters.from === this.filters.to) return fmt(this.filters.from);
            return `${fmt(this.filters.from)} – ${fmt(this.filters.to)}`;
        },
        fmtWhen(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return String(iso).replace('T', ' ').slice(0, 16);
            return d.toLocaleString(undefined, {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        },
        fmtWhenShort(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return String(iso).slice(0, 16);
            return d.toLocaleString(undefined, {
                month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        },
        barPct(value, max) {
            return Math.max(4, Math.round((Number(value || 0) / Math.max(1, Number(max || 1))) * 100));
        },
        slicePct(slice) {
            const total = Math.max(1, Number(this.charts.reputation?.total || 0));
            return ((Number(slice.value || 0) / total) * 100).toFixed(1);
        },
        riskColor(score) {
            const s = Number(score || 0);
            if (s >= 75) return '#EF4444';
            if (s >= 45) return '#FF6600';
            return '#EAB308';
        },
        gaugeStyle(score) {
            const s = Math.max(0, Math.min(100, Number(score || 0)));
            const color = this.riskColor(s);
            return `background: conic-gradient(${color} 0% ${s}%, rgba(255,255,255,.08) ${s}% 100%)`;
        },
        miniGauge(score) {
            const s = Math.max(0, Math.min(100, Number(score || 0)));
            const color = this.riskColor(s);
            return `background: conic-gradient(${color} 0% ${s}%, rgba(255,255,255,.1) ${s}% 100%)`;
        },
        kpiIcon(key) {
            const common = 'width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"';
            const paths = {
                clicks: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>',
                suspicious_devices: '<rect x="3" y="5" width="18" height="12" rx="2" stroke-width="1.8"/><path stroke-width="1.8" d="M8 21h8M12 17v4"/>',
                repeated_devices: '<rect x="4" y="4" width="10" height="14" rx="1.5" stroke-width="1.8"/><path stroke-width="1.8" d="M10 8h8v12H10"/>',
                ip_change_devices: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/>',
                high_risk_ips: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v5c0 5-3.4 8.8-8 10-4.6-1.2-8-5-8-10V6l8-3z"/><path stroke-linecap="round" stroke-width="1.8" d="M12 8v4M12 16h.01"/>',
                suspicious_ranges: '<circle cx="6" cy="12" r="2" stroke-width="1.8"/><circle cx="18" cy="6" r="2" stroke-width="1.8"/><circle cx="18" cy="18" r="2" stroke-width="1.8"/><path stroke-width="1.8" d="M8 12h8M16.5 7.5l-8 3M16.5 16.5l-8-3"/>',
            };
            return `<svg ${common}>${paths[key] || paths.clicks}</svg>`;
        },
        reasonIcon(label) {
            const l = String(label || '').toLowerCase();
            const common = 'width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"';
            if (l.includes('velocity') || l.includes('rotation') || l.includes('change')) {
                return `<svg ${common}><path stroke-linecap="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
            }
            if (l.includes('proxy') || l.includes('vpn') || l.includes('reputation') || l.includes('network')) {
                return `<svg ${common}><circle cx="12" cy="12" r="9" stroke-width="1.8"/><path stroke-width="1.8" d="M3 12h18M12 3c3 3.5 5 4 5 9s-2 8.5-5 9c-3-.5-5-4-5-9s2-8.5 5-9z"/></svg>`;
            }
            return `<svg ${common}><rect x="5" y="3" width="10" height="16" rx="1.5" stroke-width="1.8"/><path stroke-width="1.8" d="M9 7h2M9 11h2"/></svg>`;
        },
        sparkSvg(values, tone) {
            const vals = (values || []).map((v) => Number(v || 0));
            if (!vals.length) return '';
            const w = 140, h = 32, pad = 2;
            const max = Math.max(...vals, 1);
            const min = Math.min(...vals, 0);
            const span = Math.max(1, max - min);
            const step = (w - pad * 2) / Math.max(1, vals.length - 1);
            const coords = vals.map((v, i) => {
                const x = pad + i * step;
                const y = h - pad - ((v - min) / span) * (h - pad * 2);
                return [x, y];
            });
            const line = coords.map(([x, y]) => `${x},${y}`).join(' ');
            const area = `${pad},${h} ` + line + ` ${coords[coords.length - 1][0]},${h}`;
            const color = toneStroke[tone] || '#FF6600';
            const dots = coords.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="1.6" fill="${color}" />`).join('');
            const gid = `sp-${tone}-${vals.join('').slice(0, 8)}`;
            return `<svg width="100%" height="100%" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
                <defs><linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="${color}" stop-opacity=".35"/><stop offset="100%" stop-color="${color}" stop-opacity="0"/></linearGradient></defs>
                <polygon fill="url(#${gid})" points="${area}" />
                <polyline fill="none" stroke="${color}" stroke-width="1.7" points="${line}" />
                ${dots}
            </svg>`;
        },
        areaSvg(series) {
            const vals = (series || []).map((p) => Number(p.value || 0));
            const labels = (series || []).map((p) => p.label || '');
            if (!vals.length) return '<div class="tc-empty">No activity yet.</div>';
            const w = 300, h = 160, padL = 28, padR = 8, padT = 10, padB = 26;
            const max = Math.max(...vals, 1);
            const step = (w - padL - padR) / Math.max(1, vals.length - 1);
            const coords = vals.map((v, i) => {
                const x = padL + i * step;
                const y = padT + (1 - v / max) * (h - padT - padB);
                return [x, y];
            });
            const line = coords.map(([x, y]) => `${x},${y}`).join(' ');
            const area = `${padL},${h - padB} ` + line + ` ${coords[coords.length - 1][0]},${h - padB}`;
            const dots = coords.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="2.6" fill="#FF6600" />`).join('');
            const yTicks = [0, 0.25, 0.5, 0.75, 1].map((t) => {
                const y = padT + (1 - t) * (h - padT - padB);
                const val = Math.round(max * t);
                return `<line x1="${padL}" y1="${y}" x2="${w - padR}" y2="${y}" stroke="rgba(255,255,255,.08)" stroke-dasharray="3 3"/>
                    <text x="${padL - 6}" y="${y + 3}" fill="rgba(255,255,255,.3)" font-size="9" text-anchor="end">${val}</text>`;
            }).join('');
            const labelStep = Math.max(1, Math.ceil(labels.length / 4));
            const text = labels.map((lab, i) => {
                if (i % labelStep !== 0 && i !== labels.length - 1) return '';
                const x = padL + i * step;
                return `<text x="${x}" y="${h - 6}" fill="rgba(255,255,255,.35)" font-size="9" text-anchor="middle">${lab}</text>`;
            }).join('');
            return `<svg width="100%" height="160" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
                <defs><linearGradient id="tcAreaFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FF6600" stop-opacity=".35"/><stop offset="100%" stop-color="#FF6600" stop-opacity="0"/></linearGradient></defs>
                ${yTicks}
                <polygon fill="url(#tcAreaFill)" points="${area}" />
                <polyline fill="none" stroke="#FF6600" stroke-width="2" points="${line}" />
                ${dots}${text}
            </svg>`;
        },
    };
}
</script>
@endsection
