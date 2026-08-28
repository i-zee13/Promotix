@extends('layouts.admin')

@section('title', 'Paid Advertising | Dashboard')
@section('subtitle', 'Live campaign performance and detection results')

@section('header-toolbar')
    @include('partials.paid-marketing-header-timezone')
@endsection

@section('rightbar')
<div class="figma-rightbar-default paid-rightbar">
    @include('partials.figma-rightbar-header-actions')

    <div class="figma-rightbar-center mb-[6px]">
        <h2 class="mb-[8px] w-full max-w-[168px] text-[14px] font-bold text-[#a9a9a9]">Activity Feed</h2>
        <div id="paid-activity-feed" class="w-full max-w-[168px] space-y-[8px] border-b-2 border-[#5a2a99] pb-[12px] text-[9px] text-[#a9a9a9]">
            <p class="text-white/45">Loading…</p>
        </div>
    </div>

    <div class="figma-rightbar-center mt-[16px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] w-full max-w-[168px] text-[16px] font-bold text-[#a9a9a9]">Quick Actions</h2>
        <div class="mx-auto grid w-full max-w-[168px] grid-cols-2 gap-[10px]">
            <a href="{{ route('domains.index') }}" class="paid-quick-action" title="Test Tracking">
                @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[16px] w-[16px]'])
                <span>Test Tracking</span>
            </a>
            <a href="{{ route('integrations') }}" class="paid-quick-action" title="Sync Ads">
                @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[16px] w-[16px]'])
                <span>Sync Ads</span>
            </a>
            <a href="{{ route('domains.index') }}" class="paid-quick-action" title="Generate Tag">
                @include('partials.sidebar-icon', ['name' => 'tag', 'class' => 'h-[16px] w-[16px]'])
                <span>Generate Tag</span>
            </a>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-promotix-settings',{detail:{tab:'reports'}}))" class="paid-quick-action" title="View Reports">
                @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[16px] w-[16px]'])
                <span>View Reports</span>
            </button>
        </div>
    </div>

    <div class="figma-rightbar-center mt-[18px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] w-full max-w-[168px] text-[16px] font-bold text-[#a9a9a9]">System Overview</h2>
        <div id="paid-system-overview" class="w-full max-w-[168px] space-y-[8px] text-[10px] text-white/75">
            <div class="paid-sys-row">
                <span>Total Clicks</span><span data-sys="clicks" class="text-white/90">—</span>
            </div>
            <div class="paid-sys-row">
                <span>Invalid Clicks</span><span data-sys="invalid" class="text-rose-300">—</span>
            </div>
            <div class="paid-sys-row">
                <span>Blocked</span><span data-sys="blocked" class="text-white/90">—</span>
            </div>
            <div class="paid-sys-row">
                <span>Protection Rate</span><span data-sys="rate" class="text-emerald-200">—</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<style>
    /* Layout guards — work even if Vite assets are stale */
    .google-reconnect-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 20px;
        padding: 0 7px;
        border-radius: 4px;
        border: 1px solid rgba(251, 191, 36, 0.45);
        background: rgba(251, 191, 36, 0.14);
        color: #fcd34d;
        font-size: 10px;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
    }
    .google-reconnect-chip:hover {
        background: rgba(251, 191, 36, 0.24);
        color: #fde68a;
    }
    .google-reconnect-chip__icon {
        width: 10px;
        height: 10px;
        flex-shrink: 0;
    }
    html.light-mode .google-reconnect-chip {
        border-color: rgba(180, 83, 9, 0.35);
        background: rgba(251, 191, 36, 0.2);
        color: #b45309;
    }
    .paid-kpi-card {
        display: flex;
        flex-direction: column;
        min-height: 168px;
    }
    .paid-kpi-card__head {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .paid-kpi-card__icon {
        display: inline-flex;
        height: 22px;
        width: 22px;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        background: rgba(0, 0, 0, 0.25);
        color: #fff;
    }
    .paid-kpi-card__big {
        margin: 4px 0 0;
        font-size: 22px;
        font-weight: 700;
        line-height: 1;
        color: #fff;
    }
    .paid-kpi-card__mid {
        margin: 4px 0 0;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.1;
        color: #fff;
    }
    .paid-kpi-card__link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 12px;
        font-size: 10px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.88);
    }
    .paid-kpi-card__link:hover { color: #fff; }
    .paid-metric-bar--lg { height: 8px; }
    .paid-panel-card {
        border-radius: 12px;
        border: 1px solid color-mix(in srgb, var(--brand-primary) 55%, #141414);
        background: #111111;
        box-shadow: 0 0 18px var(--brand-shadow);
    }
    .paid-heatmap-legend {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 9px;
        color: rgba(255, 255, 255, 0.5);
    }
    .paid-heatmap-legend__bar {
        height: 6px;
        flex: 1;
        border-radius: 999px;
        background: linear-gradient(90deg, color-mix(in srgb, var(--brand-primary) 25%, #141414) 0%, var(--brand-primary) 45%, #f59e0b 78%, #ef4444 100%);
    }
    .paid-window-select {
        -webkit-appearance: none;
        appearance: none;
        display: inline-block;
        height: 32px;
        min-height: 32px;
        max-height: 32px;
        box-sizing: border-box;
        margin: 0;
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        background-color: #101010;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23ffffff'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 12px;
        padding: 0 28px 0 12px;
        font-size: 11px;
        font-weight: 500;
        line-height: 30px;
        color: #fff;
        vertical-align: middle;
    }
    .paid-window-select:focus {
        outline: none;
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 1px color-mix(in srgb, var(--brand-primary) 40%, transparent);
    }
    .paid-keyword-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 10px;
        color: rgba(255, 255, 255, 0.88);
    }
    .paid-keyword-table th {
        padding: 4px 6px 8px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.55);
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        white-space: nowrap;
    }
    .paid-keyword-table td {
        padding: 7px 6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        vertical-align: middle;
    }
    .paid-keyword-table th:nth-child(2),
    .paid-keyword-table td:nth-child(2),
    .paid-keyword-table th:nth-child(3),
    .paid-keyword-table td:nth-child(3),
    .paid-keyword-table th:nth-child(4),
    .paid-keyword-table td:nth-child(4) {
        text-align: right;
        white-space: nowrap;
    }
    .paid-mid-row { align-items: start; }
    .paid-trends-card { align-self: start; height: fit-content; min-height: 0; }
    .paid-trends-wrap { line-height: 0; }
    .paid-trends-canvas { display: block; width: 100%; }
    .paid-dashboard-page {
        container-type: inline-size;
        container-name: paid-page;
    }
    .paid-row2 {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
        align-items: stretch;
        width: 100%;
    }
    /* Standard laptop / sidebar-narrow: Trend full width, Heatmap | Keywords below */
    @container paid-page (min-width: 640px) {
        .paid-row2 {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }
        .paid-row2 > .paid-panel-card:first-child {
            grid-column: 1 / -1;
        }
    }
    /* Wide canvas only: Trend | Heatmap | Keywords in one row */
    @container paid-page (min-width: 1100px) {
        .paid-row2 {
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr);
        }
        .paid-row2 > .paid-panel-card:first-child {
            grid-column: auto;
        }
    }
    /* Short / standard laptop height — less empty top chrome */
    @media (max-height: 900px) {
        .paid-dashboard-page {
            padding-top: 18px !important;
            padding-bottom: 16px !important;
        }
    }
    /* Filter bar under title: compact fit-width, calendar + actions grouped */
    .figma-filter-bar--paid {
        width: fit-content !important;
        max-width: 100% !important;
        margin-left: auto !important;
        flex-wrap: nowrap !important;
        overflow: visible;
    }
    .figma-filter-bar--paid > label {
        flex: 0 0 auto;
        min-width: 0;
        max-width: none;
    }
    .figma-filter-bar--paid .figma-filter-select-wrap,
    .figma-filter-bar--paid .figma-filter-path-wrap {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }
    .figma-filter-bar--paid .figma-filter-select-wrap .figma-filter-control,
    .figma-filter-bar--paid .figma-filter-path-wrap .figma-filter-control {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box;
    }
    .figma-filter-bar--paid .paid-filter-secondary {
        display: flex;
        flex: 0 0 auto;
        flex-wrap: nowrap;
        align-items: stretch;
        min-width: 0;
        border-top: 0;
    }
    .figma-filter-bar--paid .paid-filter-secondary > label.paid-filter-landing {
        flex: 0 0 150px;
        width: 150px;
        min-width: 150px;
        max-width: 150px;
    }
    .figma-filter-bar--paid .paid-filter-secondary > label:last-child {
        flex: 0 0 auto;
        min-width: 88px;
        max-width: none;
    }
    .figma-filter-bar--paid .paid-filter-secondary > label,
    .figma-filter-bar--paid .paid-filter-secondary > .figma-filter-calendar-host {
        border-top: 0 !important;
    }
    .figma-filter-bar--paid .figma-filter-calendar-host {
        flex: 0 0 auto !important;
    }
    @container paid-page (max-width: 900px) {
        .figma-filter-bar--paid .paid-filter-secondary {
            flex: 1 1 100%;
            flex-wrap: wrap;
            border-top: 1px solid rgba(0, 0, 0, 0.12);
        }
    }
    @container paid-page (min-width: 1100px) {
        .figma-filter-bar--paid > label {
            flex: 1 1 0;
            min-width: 0;
            max-width: none;
        }
    }
    @media (max-width: 1023px) {
        .paid-kpi-card__big { font-size: 18px; }
        .paid-kpi-card { min-height: 0; padding: 10px 12px; }
        .paid-dashboard-card__title { font-size: 12px; }
    }
    .paid-engine-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
        align-items: stretch;
        width: 100%;
    }
    @container paid-page (min-width: 900px) {
        .paid-engine-row {
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        }
    }
    .paid-engine-card {
        border-radius: 10px;
        border: 1px solid color-mix(in srgb, var(--brand-primary) 55%, #141414);
        background: #111111;
        padding: 16px 18px;
        min-width: 0;
        height: 100%;
    }
    .paid-engine-active {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border-radius: 999px;
        background: rgba(34, 197, 94, 0.16);
        color: #86efac;
        font-size: 10px;
        font-weight: 600;
        padding: 3px 9px;
        line-height: 1;
    }
    .paid-engine-active__dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25);
    }
    .paid-engine-active.is-off {
        background: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.55);
    }
    .paid-engine-active.is-off .paid-engine-active__dot {
        background: rgba(255, 255, 255, 0.35);
        box-shadow: none;
    }
    .paid-engine-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
        margin-top: 14px;
    }
    @media (min-width: 640px) {
        .paid-engine-grid { grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr); }
    }
    .paid-engine-col__title {
        font-size: 12px;
        font-weight: 600;
        color: #93a4b8;
        margin-bottom: 10px;
    }
    .paid-engine-rule {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 28px;
        margin-bottom: 6px;
    }
    .paid-engine-rule__icon {
        width: 18px;
        height: 18px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--brand-primary) 28%, transparent);
        color: color-mix(in srgb, var(--brand-primary) 65%, white);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .paid-engine-rule__label {
        flex: 1;
        min-width: 0;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.88);
    }
    .paid-engine-on {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 18px;
        border-radius: 4px;
        background: #16a34a;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.02em;
        padding: 0 6px;
    }
    .paid-engine-on.is-off {
        background: rgba(255, 255, 255, 0.12);
        color: rgba(255, 255, 255, 0.5);
    }
    .paid-engine-action {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px;
        min-height: 34px;
        margin-bottom: 8px;
    }
    .paid-engine-action__name {
        font-size: 12px;
        font-weight: 600;
        color: #fff;
    }
    .paid-engine-action__desc { font-size: 11px; }
    .paid-engine-action__desc.is-low { color: #60a5fa; }
    .paid-engine-action__desc.is-medium { color: #fb923c; }
    .paid-engine-action__desc.is-high { color: #f87171; }
    .paid-engine-action-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 52px;
        height: 20px;
        border-radius: 4px;
        border: 1px solid rgba(34, 197, 94, 0.65);
        color: #86efac;
        font-size: 9px;
        font-weight: 600;
        padding: 0 7px;
    }
    .paid-engine-action-badge.is-off {
        border-color: rgba(255, 255, 255, 0.2);
        color: rgba(255, 255, 255, 0.45);
    }
    .paid-engine-link {
        display: inline-flex;
        margin-top: 10px;
        font-size: 11px;
        font-weight: 600;
        color: color-mix(in srgb, var(--brand-primary) 70%, white);
    }
    .paid-engine-link:hover { color: color-mix(in srgb, var(--brand-primary) 55%, white); }
    .paid-hrisk-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
        color: #a9a9a9;
    }
    .paid-hrisk-table th {
        text-align: left;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.55);
        padding: 7px 6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        white-space: nowrap;
    }
    .paid-hrisk-table td {
        padding: 9px 6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        vertical-align: middle;
    }
    .paid-hrisk-table tr:last-child td { border-bottom: 0; }
    .paid-hrisk-table tr { cursor: pointer; }
    .paid-hrisk-table tr:hover td { background: rgba(255, 255, 255, 0.03); }

    /* Recent Paid Traffic table */
    .paid-traffic-wrap {
        margin-top: 6px;
        max-height: 365px;
        overflow: auto;
        border-radius: 4px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .paid-traffic-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: auto;
        text-align: left;
        font-size: 11px;
        color: #a9a9a9;
    }
    .paid-traffic-table th,
    .paid-traffic-table td {
        padding: 8px 10px;
        vertical-align: middle;
        white-space: nowrap;
        box-sizing: border-box;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    }
    .paid-traffic-table thead th {
        position: sticky;
        top: 0;
        z-index: 4;
        background: var(--brand-primary);
        color: #fff;
        font-weight: 500;
        line-height: 1.2;
    }
    .paid-traffic-table th .promotix-sortable {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        max-width: none;
        color: inherit;
    }
    /* Proper column hide for Basic View (avoids blank header gaps) */
    .paid-traffic-table[data-ip-view="basic"] col.pt-expert,
    .paid-traffic-table[data-ip-view="basic"] .pt-expert {
        visibility: collapse;
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        font-size: 0 !important;
        line-height: 0 !important;
        overflow: hidden !important;
    }
    .paid-traffic-table .pt-col-ip { min-width: 124px; width: 124px; }
    .paid-traffic-table .pt-col-device { min-width: 118px; }
    .paid-traffic-table .pt-col-conf { min-width: 88px; }
    .paid-traffic-table .pt-col-num { min-width: 70px; }
    .paid-traffic-table .pt-col-detect { min-width: 150px; max-width: 190px; }
    .paid-traffic-table .pt-col-risk { min-width: 84px; }
    .paid-traffic-table .pt-col-action { min-width: 72px; }
    .paid-traffic-table .pt-col-excl { min-width: 92px; }
    .paid-traffic-table .pt-col-campaign { min-width: 140px; }
    .paid-traffic-table .pt-col-pid { min-width: 110px; }
    .paid-traffic-table .pt-col-fp { min-width: 100px; }
    .paid-traffic-table .pt-col-time { min-width: 110px; }
    .paid-traffic-table th.pt-sticky-ip,
    .paid-traffic-table td.pt-sticky-ip {
        position: sticky;
        left: 0;
    }
    .paid-traffic-table th.pt-sticky-ip {
        z-index: 6;
        background: var(--brand-primary) !important;
        box-shadow: 2px 0 0 var(--brand-secondary);
    }
    .paid-traffic-table td.pt-sticky-ip {
        z-index: 3;
        background: #111111 !important;
        box-shadow: 2px 0 6px rgba(0, 0, 0, 0.35);
    }
    .paid-traffic-table tbody tr:hover td.pt-sticky-ip {
        background: #1a1a1a !important;
    }
    html.light-mode .paid-traffic-table td.pt-sticky-ip {
        background: #fff !important;
        box-shadow: 2px 0 6px color-mix(in srgb, var(--brand-primary) 8%, transparent);
    }
    html.light-mode .paid-traffic-table tbody tr:hover td.pt-sticky-ip { background: var(--brand-tint-hover) !important; }

    /* Light mode: dark panel cards → white surfaces + readable text */
    html.light-mode .paid-panel-card,
    html.light-mode .paid-engine-card {
        background: #ffffff;
        border-color: var(--brand-tint-border);
        box-shadow: 0 1px 10px color-mix(in srgb, var(--brand-primary) 8%, transparent);
    }
    html.light-mode .paid-window-select {
        background-color: #ffffff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b6578'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        border-color: var(--brand-tint-border);
        color: #2d2d3a;
    }
    html.light-mode .paid-heatmap-legend,
    html.light-mode .paid-heatmap-hour,
    html.light-mode .paid-heatmap-day {
        color: #6b6578;
    }
    html.light-mode .paid-keyword-table { color: #2d2d3a; }
    html.light-mode .paid-keyword-table th {
        color: #6b6578;
        border-bottom-color: var(--brand-tint-border);
    }
    html.light-mode .paid-keyword-table td { border-bottom-color: var(--brand-tint-soft); }
    html.light-mode .paid-engine-col__title { color: #6b6578; }
    html.light-mode .paid-engine-rule__label,
    html.light-mode .paid-engine-action__name { color: #2d2d3a; }
    html.light-mode .paid-engine-active.is-off {
        background: var(--brand-tint-soft);
        color: #6b6578;
    }
    html.light-mode .paid-engine-active.is-off .paid-engine-active__dot { background: #9a93a8; }
    html.light-mode .paid-engine-on.is-off {
        background: var(--brand-tint-soft);
        color: #6b6578;
    }
    html.light-mode .paid-engine-action-badge.is-off {
        border-color: var(--brand-tint-border);
        color: #6b6578;
    }
    html.light-mode .paid-engine-link { color: var(--brand-primary); }
    html.light-mode .paid-engine-link:hover { color: var(--figma-chrome-accent-hover); }
    html.light-mode .paid-hrisk-table { color: #5c5470; }
    html.light-mode .paid-hrisk-table th {
        color: #6b6578;
        border-bottom-color: var(--brand-tint-border);
    }
    html.light-mode .paid-hrisk-table td { border-bottom-color: var(--brand-tint-soft); }
    html.light-mode .paid-hrisk-table tr:hover td { background: var(--brand-tint-hover); }
    html.light-mode .paid-traffic-wrap { border-color: var(--brand-tint-border); }
    html.light-mode .paid-traffic-table { color: #5c5470; }
    html.light-mode .paid-traffic-table th,
    html.light-mode .paid-traffic-table td { border-bottom-color: var(--brand-tint-soft); }
    html.light-mode .paid-score-low { color: #15803d; }
    html.light-mode .paid-outline-badge.is-high,
    html.light-mode .paid-outline-badge.is-block { color: #be123c; border-color: rgba(190, 18, 60, 0.45); }
    html.light-mode .paid-outline-badge.is-medium,
    html.light-mode .paid-outline-badge.is-monitor { color: #c2410c; border-color: rgba(194, 65, 12, 0.4); }
    html.light-mode .paid-outline-badge.is-low,
    html.light-mode .paid-outline-badge.is-allow { color: #15803d; border-color: rgba(21, 128, 61, 0.4); }
    html.light-mode .paid-panel-card [class*="text-white"],
    html.light-mode .paid-engine-card [class*="text-white"]:not(.paid-export-btn) {
        color: #2d2d3a !important;
    }
    html.light-mode .paid-panel-card [class*="text-white/"],
    html.light-mode .paid-engine-card [class*="text-white/"]:not(.paid-export-btn) {
        color: #6b6578 !important;
    }
    html.light-mode .paid-panel-card [class*="bg-white/5"] {
        background: var(--brand-tint-selected) !important;
        border-color: var(--brand-tint-border) !important;
    }
    html.light-mode .paid-panel-card a[class*="hover:text-white"]:hover {
        color: var(--brand-secondary) !important;
    }
    html.light-mode .paid-engine-card [class*="bg-[var(--brand-primary)]"],
    html.light-mode .paid-engine-card [class*="bg-[var(--brand-primary)]"] [class*="text-white"],
    html.light-mode .paid-view-tabs [class*="bg-[var(--brand-primary)]"],
    html.light-mode .paid-view-tabs [class*="bg-[var(--brand-primary)]"] [class*="text-white"],
    html.light-mode .paid-engine-card [class*="bg-[#6400B2]"],
    html.light-mode .paid-engine-card [class*="bg-[#6400B2]"] [class*="text-white"],
    html.light-mode .paid-view-tabs [class*="bg-[#6400B2]"],
    html.light-mode .paid-view-tabs [class*="bg-[#6400B2]"] [class*="text-white"] {
        color: #ffffff !important;
    }
    html.light-mode .figma-main .paid-view-tabs {
        background: var(--brand-tint-selected) !important;
        border-color: var(--brand-tint-border) !important;
        color: #6b6578 !important;
    }
    html.light-mode .paid-view-tabs button:not([class*="bg-[var(--brand-primary)"]):not([class*="bg-[#6400B2]"]) {
        color: #6b6578 !important;
    }
    html.light-mode .paid-export-btn {
        background: #ffffff !important;
        color: var(--brand-primary) !important;
        border-color: color-mix(in srgb, var(--brand-primary) 42%, #ffffff) !important;
    }
    html.light-mode .paid-export-btn svg {
        stroke: var(--brand-primary) !important;
        color: var(--brand-primary) !important;
    }
    html.light-mode .paid-export-btn:hover {
        background: color-mix(in srgb, var(--brand-primary) 10%, #ffffff) !important;
        color: var(--brand-secondary, var(--brand-primary)) !important;
        border-color: var(--brand-primary) !important;
    }
    html.light-mode .paid-export-btn:hover svg {
        stroke: var(--brand-secondary, var(--brand-primary)) !important;
        color: var(--brand-secondary, var(--brand-primary)) !important;
    }
    .paid-traffic-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 6px;
    }
    .paid-traffic-head__left {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .paid-traffic-head__title {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        line-height: 1;
        color: #a9a9a9;
    }
    @media (min-width: 640px) {
        .paid-traffic-head__title { font-size: 22px; }
    }
    html.light-mode .paid-traffic-head__title { color: #2d2d3a; }
    html.light-mode .paid-traffic-head__domain .figma-panel-select {
        background-color: #ffffff !important;
        color: #2d2d3a !important;
        border: 1px solid rgba(255, 255, 255, 0.65) !important;
        color-scheme: light;
    }
    .paid-view-tabs {
        display: inline-flex;
        align-items: center;
        height: 28px;
        flex-shrink: 0;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        background: rgba(0, 0, 0, 0.3);
        padding: 2px;
        font-size: 10px;
    }
    .paid-view-tabs button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 24px;
        border-radius: 4px;
        padding: 0 10px;
        line-height: 1;
    }
    .paid-export-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 4px;
        border: 1px solid color-mix(in srgb, var(--brand-primary) 60%, transparent);
        background: #1a1a1a;
        color: color-mix(in srgb, var(--brand-primary) 65%, white);
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .paid-export-btn:hover {
        border-color: var(--brand-primary);
        background: color-mix(in srgb, var(--brand-primary) 18%, #1a1a1a);
        color: #fff;
    }
    .paid-export-btn svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
    }
    .paid-traffic-head__domain {
        display: flex;
        align-items: center;
        gap: 8px;
        height: 28px;
        max-width: min(100%, 280px);
        flex-shrink: 0;
        border-radius: 3px;
        background: var(--brand-primary);
        padding: 0 9px;
        font-size: 10px;
        color: #fff;
    }
    @media (min-width: 900px) {
        .paid-traffic-head { flex-wrap: nowrap; }
        .paid-traffic-head__left { flex-wrap: nowrap; }
    }
    html.light-mode #keyword-list [class*="text-white"] { color: #5c5470 !important; }
    html.light-mode #keyword-list button {
        color: var(--brand-primary) !important;
        background: color-mix(in srgb, var(--brand-primary) 8%, transparent) !important;
    }
    .paid-score-high { color: #f87171; font-weight: 600; }
    .paid-score-medium { color: #fb923c; font-weight: 600; }
    .paid-score-low { color: #86efac; font-weight: 600; }
    .paid-outline-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        border: 1px solid;
        padding: 1px 7px;
        font-size: 9px;
        font-weight: 600;
        line-height: 1.3;
        white-space: nowrap;
    }
    .paid-outline-badge.is-high { border-color: rgba(248, 113, 113, 0.7); color: #fca5a5; }
    .paid-outline-badge.is-medium { border-color: rgba(251, 146, 60, 0.7); color: #fdba74; }
    .paid-outline-badge.is-low { border-color: rgba(134, 239, 172, 0.55); color: #86efac; }
    .paid-outline-badge.is-block { border-color: rgba(248, 113, 113, 0.7); color: #fca5a5; }
    .paid-outline-badge.is-monitor { border-color: rgba(251, 146, 60, 0.7); color: #fdba74; }
    .paid-outline-badge.is-allow { border-color: rgba(134, 239, 172, 0.55); color: #86efac; }
    .paid-status-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #f87171;
    }
    .paid-status-dot.is-monitor { background: #fb923c; }
    .paid-status-dot.is-allow { background: #22c55e; }
</style>
<div class="brand-page-bg min-h-[calc(100vh-49px)]" x-data="paidAdvertisingFigma(@js([
    'countryGetStarted' => $countryGetStarted,
    'userTimezone' => \App\Support\UserTimezone::reportingTimezoneForUser(
        auth()->user(),
        \App\Support\UserTimezone::resolveGoogleAccountTimezone(auth()->user(), (int) request('domain_id', 0) ?: null)
    ),
    'domainCatalog' => $domainCatalog ?? [],
    'reportingMode' => \App\Support\UserTimezone::reportingMode(auth()->user()),
    'profileTimezone' => \App\Support\UserTimezone::forUser(auth()->user()),
]))" x-init="init()">
    <section class="paid-dashboard-page mx-auto w-full max-w-[1120px] px-[12px] pb-[22px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[25px] xl:pt-[68px]">
        <div class="mb-[23px] bp-adv-page-head">
            <div class="flex flex-wrap items-center gap-[12px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Advertising</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Dashboard</span>
            </div>

            <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--paid ov-filter-bar ml-auto flex min-h-[54px] w-fit max-w-full flex-nowrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)]">
                <label class="flex flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
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
                <label class="flex flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.traffic_source" @change="reload(false, true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="google_ads">Google Ads</option>
                            <option value="meta_ads" disabled>Meta Ads</option>
                            <option value="microsoft_ads" disabled>Microsoft Ads</option>
                        </select>
                    </div>
                </label>
                <label class="flex flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Google Ads Account</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.google_ads_account_id" @change="reload(false, true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Accounts</option>
                            @foreach (($googleAdsAccounts ?? []) as $account)
                                <option value="{{ $account->id }}">{{ $account->displayLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.campaign" @change="onCampaignChange(); reload(false, true)" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Campaigns</option>
                            <template x-for="row in campaignOptions" :key="row.campaign + '-' + (row.campaign_id || '')">
                                <option :value="row.campaign" x-text="row.campaign"></option>
                            </template>
                        </select>
                    </div>
                </label>
                <div class="paid-filter-secondary">
                <label class="paid-filter-landing flex flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleReload()" placeholder="Landing page" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
                <label class="flex shrink-0 flex-col items-center justify-center gap-[4px] px-[10px] py-[6px]">
                    <span class="text-[8px] font-semibold uppercase text-black/55">Compare</span>
                    <button
                        type="button"
                        class="paid-compare-toggle"
                        :class="{ 'is-on': compareEnabled }"
                        :aria-pressed="compareEnabled ? 'true' : 'false'"
                        @click="toggleCompare()"
                        title="Compare with previous period"
                    >
                        <span class="paid-compare-toggle__knob"></span>
                    </button>
                </label>
                </div>
            </div>
        </div>

        <div class="paid-dashboard-cards-wrap">
        <div class="paid-dashboard-cards">
            {{-- Row 1 / Card 1 — Google Ads Click Summary --}}
            <article class="paid-dashboard-card paid-kpi-card">
                <div class="paid-kpi-card__head">
                    <span class="paid-kpi-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-[14px] w-[14px]"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    </span>
                    <h2 class="paid-dashboard-card__title">Google Ads Click Summary</h2>
                    <button type="button" class="paid-dashboard-card__icon-btn ml-auto" aria-label="Refresh" @click="reload(true, true)" title="Refresh Google Ads sync">
                        <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h5M20 20v-5h-5M20 9A8 8 0 006.34 6.34M4 15a8 8 0 0013.66 2.66"/></svg>
                    </button>
                </div>
                <div class="mt-[10px] grid grid-cols-2 gap-x-[12px] gap-y-[18px]">
                    <div>
                        <p class="paid-traffic-metrics__label">Total Google Ads Clicks</p>
                        <div class="flex flex-wrap items-center gap-[8px]">
                            <p class="paid-kpi-card__big" x-text="fmt(summary.total_click_count || summary.google_clicks)"></p>
                            <template x-if="showGoogleReconnect">
                                <a
                                    :href="summary.google_reconnect_url || '{{ route('integrations.google.redirect') }}'"
                                    class="google-reconnect-chip"
                                    title="Google Ads totals not syncing — reconnect your Google account"
                                >
                                    <svg class="google-reconnect-chip__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M20 9A8 8 0 006.34 6.34M4 15a8 8 0 0013.66 2.66"/>
                                    </svg>
                                    <span>Reconnect</span>
                                </a>
                            </template>
                        </div>
                        <p class="mt-[4px] text-[9px] leading-snug text-amber-200/90" x-show="showGoogleReconnect" x-cloak>
                            Google Ads total not syncing — reconnect Google, then refresh.
                        </p>
                    </div>
                    <div>
                        <p class="paid-traffic-metrics__label">Tracked Clicks</p>
                        <p class="paid-kpi-card__big" x-text="fmt(summary.tracked_clicks ?? summary.unique_paid_clicks)"></p>
                    </div>
                    <div>
                        <p class="paid-traffic-metrics__label">Valid Clicks</p>
                        <p class="text-[15px] font-semibold leading-none text-emerald-300">
                            <span x-text="fmt(summary.unique_valid_paid_clicks ?? summary.valid_paid_visits)"></span>
                            <span class="text-[11px] font-medium opacity-90">(<span x-text="validClickPct"></span>%)</span>
                        </p>
                    </div>
                    <div>
                        <p class="paid-traffic-metrics__label">Invalid Clicks</p>
                        <p class="text-[15px] font-semibold leading-none text-rose-300">
                            <span x-text="fmt(summary.unique_invalid_paid_clicks ?? summary.invalid_paid_visits)"></span>
                            <span class="text-[11px] font-medium opacity-90">(<span x-text="invalidClickPct"></span>%)</span>
                        </p>
                    </div>
                </div>
                <div class="mt-[14px] flex items-end justify-between gap-[10px]">
                    <div>
                        <p class="paid-traffic-metrics__label">Cost Saved</p>
                        <p class="text-[16px] font-semibold leading-none text-white">$<span x-text="Number(summary.cost_saved || 0).toFixed(2)"></span></p>
                    </div>
                    <p class="text-[9px] text-white/45" x-show="summary.avg_cpc">Avg CPC $<span x-text="Number(summary.avg_cpc || 0).toFixed(2)"></span></p>
                </div>
                <div class="mt-auto pt-[12px]">
                    <div class="mb-[4px] flex items-center justify-between text-[9px]">
                        <span class="text-white/70">Tracking Accuracy</span>
                        <span class="font-semibold text-white"><span x-text="fmt(summary.tracking_accuracy_pct ?? summary.tag_capture_pct)"></span>%</span>
                    </div>
                    <div class="paid-metric-bar paid-metric-bar--lg"><span class="paid-metric-bar__fill is-accuracy" :style="'width:' + Number(summary.tracking_accuracy_pct ?? summary.tag_capture_pct ?? 0) + '%'"></span></div>
                    <p class="paid-traffic-metrics__hint" x-show="summary.tag_gap_warning">Tracking gap vs Google Ads — check GCLID capture</p>
                </div>
            </article>

            {{-- Row 1 / Card 2 — Paid Traffic Protection --}}
            <article class="paid-dashboard-card paid-kpi-card">
                <div class="paid-kpi-card__head">
                    <span class="paid-kpi-card__icon" aria-hidden="true">
                        <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 2a4 4 0 014 4v1h1a3 3 0 013 3v2a3 3 0 01-1 2.236V15a5 5 0 01-10 0v-.764A3 3 0 018 10V8a3 3 0 013-3h1V6a4 4 0 014-4z"/><circle cx="10" cy="11" r="1" fill="currentColor"/><circle cx="14" cy="11" r="1" fill="currentColor"/></svg>
                    </span>
                    <h2 class="paid-dashboard-card__title">Paid Traffic Protection</h2>
                </div>
                <div class="mt-[10px] grid flex-1 grid-cols-2 gap-x-[12px] gap-y-[12px]">
                    <div>
                        <p class="paid-traffic-metrics__label">Tracked Clicks</p>
                        <p class="paid-kpi-card__mid" x-text="fmt(summary.tag_paid_visits)"></p>
                    </div>
                    <div>
                        <p class="paid-traffic-metrics__label">Fraud Signals</p>
                        <p class="paid-kpi-card__mid text-emerald-300">
                            <span x-text="fmt(summary.invalid_paid_visits)"></span>
                            <span class="text-[10px] opacity-90">(<span x-text="botRate"></span>%)</span>
                        </p>
                    </div>
                    <div>
                        <p class="paid-traffic-metrics__label">Blocked</p>
                        <p class="paid-kpi-card__mid text-rose-300">
                            <span x-text="fmt(summary.block_enforced || summary.block_attempts || 0)"></span>
                            <span class="text-[10px] opacity-90">(<span x-text="blockedBotPct"></span>%)</span>
                        </p>
                    </div>
                    <div>
                        <p class="paid-traffic-metrics__label">Invalid Rate</p>
                        <p class="paid-kpi-card__mid text-emerald-300"><span x-text="botRate"></span>%</p>
                    </div>
                </div>
                <a href="{{ route('paid-marketing.detailed') }}" class="paid-kpi-card__link mt-auto">View Advanced Investigation <span aria-hidden="true">→</span></a>
            </article>

            {{-- Row 1 / Card 3 — Invalid Traffic Actions --}}
            <article class="paid-dashboard-card paid-kpi-card">
                <div class="paid-kpi-card__head">
                    <span class="paid-kpi-card__icon" aria-hidden="true">
                        @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[14px] w-[14px]'])
                    </span>
                    <h2 class="paid-dashboard-card__title">Invalid Traffic Actions</h2>
                </div>
                <div class="mt-[8px] flex-1 space-y-0">
                    <div class="paid-blocking-row"><span>Invalid Clicks</span><span x-text="fmt(summary.unique_invalid_paid_clicks ?? summary.invalid_paid_visits)"></span></div>
                    <div class="paid-blocking-row"><span>Detection Events</span><span x-text="fmt(summary.invalid_paid_events || summary.invalid_paid_visits)"></span></div>
                    <div class="paid-blocking-row"><span class="text-rose-300">Blocked</span><span class="text-rose-300"><span x-text="fmt(summary.block_enforced || 0)"></span> (<span x-text="actionBlockedPct"></span>%)</span></div>
                    <div class="paid-blocking-row"><span class="text-amber-200">Monitored</span><span class="text-amber-200"><span x-text="fmt(summary.flagged_paid_visits)"></span> (<span x-text="actionMonitoredPct"></span>%)</span></div>
                    <div class="paid-blocking-row"><span class="text-emerald-300">Whitelisted</span><span class="text-emerald-300"><span x-text="fmt(whitelistedIpCount)"></span> (<span x-text="actionWhitelistedPct"></span>%)</span></div>
                </div>
                <a href="{{ route('paid-marketing.detailed') }}" class="paid-kpi-card__link mt-auto">View All Threats <span aria-hidden="true">→</span></a>
            </article>

            {{-- Row 1 / Card 4 — Campaign Performance --}}
            <article class="paid-dashboard-card paid-kpi-card">
                <div class="paid-kpi-card__head">
                    <span class="paid-kpi-card__icon" aria-hidden="true">
                        <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 15l4-4 4 3 6-7"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M14 7h6v6"/></svg>
                    </span>
                    <h2 class="paid-dashboard-card__title">Campaign Performance</h2>
                    <a href="{{ route('paid-marketing.detailed') }}" class="ml-auto text-[10px] font-semibold text-white/85 hover:text-white">View All</a>
                </div>
                <div class="paid-campaign-breakdown !items-stretch mt-[6px] flex-1">
                    <template x-if="untaggedDomains.length > 0">
                        <div class="w-full space-y-[4px] px-[2px] text-left">
                            <template x-for="d in untaggedDomains.slice(0, 3)" :key="d.id">
                                <p class="truncate text-[10px] text-white/85" x-text="d.hostname"></p>
                            </template>
                        </div>
                    </template>
                    <template x-if="untaggedDomains.length === 0">
                        <div class="paid-campaign-table-wrap w-full flex-1">
                            <table class="paid-campaign-table">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Clicks</th>
                                        <th>Invalid %</th>
                                        <th>Cost Saved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in campaignOptions.slice(0, 4)" :key="row.campaign">
                                        <tr>
                                            <td class="truncate" :title="row.campaign" x-text="row.campaign"></td>
                                            <td x-text="fmt(row.total)"></td>
                                            <td x-text="(row.invalid_pct != null ? row.invalid_pct : 0) + '%'"></td>
                                            <td>$<span x-text="Number(row.cost_saved || 0).toFixed(2)"></span></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <p x-show="campaignOptions.length === 0" class="px-[2px] text-[10px] text-white/55">No campaign data yet</p>
                        </div>
                    </template>
                </div>
                <a
                    :href="campaignBreakdownLink()"
                    class="paid-kpi-card__link mt-auto"
                    x-text="untaggedDomains.length ? 'Add Tag Management →' : 'Set Tracking Template →'"
                ></a>
            </article>
        </div>
        </div>

        {{-- Row 2 — Trend | Heatmap | Keywords --}}
        <div class="mt-[15px]">
            <div class="paid-row2">
            <section class="paid-panel-card flex min-h-0 flex-col p-[16px] sm:p-[18px]">
                <div class="mb-[10px] flex flex-wrap items-center justify-between gap-[8px]">
                    <div class="flex min-w-0 flex-wrap items-center gap-[8px]">
                        <h2 class="text-[15px] font-semibold text-[#a9a9a9] sm:text-[16px]">Paid Traffic Trend</h2>
                        <span class="rounded-[4px] border border-white/10 bg-white/5 px-[6px] py-[2px] text-[9px] text-white/55" x-text="trends.granularity_label || (filters.from && filters.from === filters.to ? 'Hourly · Today' : 'Daily')"></span>
                        <div class="flex flex-wrap items-center gap-[6px]">
                            <template x-for="item in trendsLegendItems()" :key="item.key">
                                <button
                                    type="button"
                                    class="chart-legend-item text-[10px] text-white/90 sm:text-[11px]"
                                    :class="{ 'is-hidden': isTrendSeriesHidden(item.key) }"
                                    @click="toggleTrendSeries(item.key)"
                                >
                                    <i class="mr-[3px] inline-block h-[8px] w-[8px] rounded-full" :style="`background:${item.color}`"></i>
                                    <span x-text="item.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <select x-model="filters.window" @change="setWindow()" class="paid-window-select">
                        <option value="today">Today (Hourly)</option>
                        <option value="weekly">This Week (Daily)</option>
                        <option value="monthly">This Month (Daily)</option>
                    </select>
                </div>
                <div class="paid-trends-wrap flex-1" style="line-height: 0;">
                    <div id="paid-trends-tooltip" class="paid-trends-tooltip" hidden></div>
                    <canvas id="paid-trends" class="paid-trends-canvas h-[210px] w-full xl:h-[230px]" style="display: block; width: 100%;"></canvas>
                </div>
            </section>

            <section class="paid-panel-card flex min-h-0 flex-col p-[16px] sm:p-[18px]">
                <div class="mb-[10px] flex items-center justify-between gap-[8px]">
                    <h2 class="text-[15px] font-semibold text-[#a9a9a9] sm:text-[16px]">Click Activity Heatmap</h2>
                    <select x-model="filters.window" @change="setWindow()" class="paid-window-select">
                        <option value="today">Today (Hourly)</option>
                        <option value="weekly">This Week (Daily)</option>
                        <option value="monthly">This Month (Daily)</option>
                    </select>
                </div>
                <div id="heatmap-grid" class="paid-heatmap-grid flex-1"></div>
                <div class="paid-heatmap-legend mt-[10px]">
                    <span>Low</span>
                    <div class="paid-heatmap-legend__bar"></div>
                    <span>High</span>
                </div>
            </section>

            <section class="paid-panel-card flex min-h-0 flex-col p-[16px] sm:p-[18px]">
                <div class="mb-[10px] flex items-center justify-between gap-[8px]">
                    <h2 class="text-[15px] font-semibold text-[#a9a9a9] sm:text-[16px]">Keyword Performance</h2>
                    <a href="{{ route('paid-marketing.detailed') }}" class="text-[10px] font-semibold text-[#B893D8] hover:text-white">View All</a>
                </div>
                <div id="keyword-list" class="min-h-0 flex-1 overflow-x-auto overflow-y-auto"></div>
            </section>
            </div>
        </div>

        {{-- Protection Engine | Top High Risk IPs --}}
        <div class="mt-[15px]">
            <div class="paid-engine-row">
            <section class="paid-engine-card">
                <div class="flex flex-wrap items-center gap-[10px]">
                    <h2 class="text-[15px] font-semibold text-white sm:text-[16px]">Invalid Traffic Protection Engine</h2>
                    <span class="paid-engine-active" :class="{ 'is-off': !engineIsActive }">
                        <span class="paid-engine-active__dot" aria-hidden="true"></span>
                        <span x-text="engineIsActive ? 'Active' : 'Inactive'"></span>
                    </span>
                </div>

                <div class="paid-engine-grid">
                    <div>
                        <h3 class="paid-engine-col__title">Detection Rules</h3>
                        <template x-for="rule in engineDetectionRules" :key="rule.key || rule.label">
                            <div class="paid-engine-rule">
                                <span class="paid-engine-rule__icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" class="h-[10px] w-[10px]" fill="currentColor"><path d="M8 1.3l5.2 2.2v3.4c0 3.2-2.2 5.9-5.2 6.8-3-.9-5.2-3.6-5.2-6.8V3.5L8 1.3z"/></svg>
                                </span>
                                <span class="paid-engine-rule__label" x-text="rule.label"></span>
                                <span class="paid-engine-on" :class="{ 'is-off': !rule.on }" x-text="rule.on ? 'ON' : 'OFF'"></span>
                            </div>
                        </template>
                    </div>

                    <div class="flex min-h-0 flex-col">
                        <h3 class="paid-engine-col__title">Protection Actions</h3>
                        <template x-for="action in engineProtectionActions" :key="action.key || action.label">
                            <div class="paid-engine-action">
                                <span class="paid-engine-action__name" x-text="action.label"></span>
                                <span class="paid-engine-action__desc" :class="'is-' + (action.tone || 'low')" x-text="action.desc"></span>
                                <span class="paid-engine-action-badge" :class="{ 'is-off': !action.active }" x-text="action.active ? 'Active' : 'Off'"></span>
                            </div>
                        </template>
                        <a href="{{ route('paid-marketing.detection-settings') }}" class="paid-engine-link mt-auto self-end">Manage Protection Settings <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </section>

            <section class="paid-engine-card">
                <div class="mb-[10px] flex items-center justify-between gap-[8px]">
                    <h2 class="text-[15px] font-semibold text-white sm:text-[16px]">Top High Risk Identities</h2>
                    <a href="{{ route('paid-marketing.detailed') }}" class="text-[11px] font-semibold text-[#a78bfa] hover:text-[#c4b5fd]">View All</a>
                </div>
                <div class="promotix-slim-scroll max-h-[320px] overflow-x-auto overflow-y-auto">
                    <table class="paid-hrisk-table min-w-[640px]">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Device / PID</th>
                                <th>Risk Score</th>
                                <th>Risk Level</th>
                                <th>Detection</th>
                                <th>Clicks</th>
                                <th>Action</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in highRiskIps" :key="'hr-' + row.ip">
                                <tr @click="openIpModal(row)">
                                    <td class="max-w-[110px] truncate font-mono text-[10px] text-white" :title="row.ip" x-text="ipLabel(row.ip)"></td>
                                    <td class="max-w-[140px] truncate font-mono text-[9px] text-white/85" :title="identityLabel(row)" x-text="identityShort(row)"></td>
                                    <td>
                                        <span :class="riskScoreClass(row)" x-text="riskScoreLabel(row)"></span>
                                    </td>
                                    <td>
                                        <span class="paid-outline-badge" :class="riskBadgeClass(row.risk_level)" x-text="row.risk_level || '—'"></span>
                                    </td>
                                    <td class="max-w-[140px] truncate text-white/80" :title="row.primary_detection || threatsLabel(row)" x-text="row.primary_detection || threatsLabel(row)"></td>
                                    <td class="whitespace-nowrap text-white" x-text="fmt(row.total)"></td>
                                    <td>
                                        <span class="paid-outline-badge" :class="actionToneClass(row)" x-text="actionLabel(row)"></span>
                                    </td>
                                    <td class="w-[16px] text-right">
                                        <span class="paid-status-dot" :class="actionToneClass(row)" aria-hidden="true"></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="highRiskIps.length === 0">
                                <td colspan="8" class="px-[10px] py-[18px] text-center text-white/55">No high-risk identities in this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            </div>
        </div>

        {{-- Recent Paid Traffic (full width) --}}
        <section class="paid-engine-card mt-[15px]">
            <div class="paid-traffic-head">
                <div class="paid-traffic-head__left">
                    <h2 class="paid-traffic-head__title">Recent Paid Traffic</h2>
                    <div class="paid-view-tabs">
                        <button type="button" :class="ipViewMode === 'basic' ? 'bg-[var(--brand-primary)] text-white' : 'text-white/60'" @click="ipViewMode = 'basic'">Basic View</button>
                        <button type="button" :class="ipViewMode === 'expert' ? 'bg-[var(--brand-primary)] text-white' : 'text-white/60'" @click="ipViewMode = 'expert'">Expert View</button>
                    </div>
                    <button type="button" @click="exportIpsCsv()" title="Download CSV" aria-label="Download CSV" class="paid-export-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l4-4m-4 4l-4-4M4 19h16"/></svg>
                    </button>
                </div>
                <div class="paid-traffic-head__domain">
                    <span class="shrink-0">Domain</span>
                    <select
                        x-model="filters.domain_id"
                        @change="onDomainChange()"
                        class="figma-panel-select min-h-[24px] min-w-0 flex-1 !rounded-[3px] !py-[4px] !text-[10px]"
                    >
                        <option value="">All domains</option>
                        @foreach ($domains as $domain)
                            <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="paid-traffic-wrap promotix-slim-scroll">
                <table class="paid-traffic-table" :data-ip-view="ipViewMode">
                    <colgroup>
                        <col class="pt-col-ip">
                        <col class="pt-col-device">
                        <col class="pt-col-conf">
                        <col class="pt-col-num">
                        <col class="pt-col-num">
                        <col class="pt-col-num">
                        <col class="pt-col-num">
                        <col class="pt-col-detect">
                        <col class="pt-col-risk">
                        <col class="pt-col-action">
                        <col class="pt-col-excl">
                        <col class="pt-expert pt-col-campaign">
                        <col class="pt-expert pt-col-pid">
                        <col class="pt-expert pt-col-fp">
                        <col class="pt-expert pt-col-time">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="pt-col-ip pt-sticky-ip">
                                <button type="button" class="promotix-sortable" :class="ipSortClass('ip')" @click="setIpSort('ip')"><span>IP Address</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                            </th>
                            <th class="pt-col-device">Device ID</th>
                            <th class="pt-col-conf">Identity Conf.</th>
                            <th class="pt-col-num">
                                <button type="button" class="promotix-sortable" :class="ipSortClass('total')" @click="setIpSort('total')"><span>Paid Clicks</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                            </th>
                            <th class="pt-col-num">Clicks 60m</th>
                            <th class="pt-col-num">
                                <button type="button" class="promotix-sortable" :class="ipSortClass('invalid')" @click="setIpSort('invalid')"><span>Invalid</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                            </th>
                            <th class="pt-col-num">
                                <button type="button" class="promotix-sortable" :class="ipSortClass('valid')" @click="setIpSort('valid')"><span>Valid</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                            </th>
                            <th class="pt-col-detect">Primary Detection</th>
                            <th class="pt-col-risk">
                                <button type="button" class="promotix-sortable" :class="ipSortClass('risk_score')" @click="setIpSort('risk_score')"><span>Risk</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                            </th>
                            <th class="pt-col-action">Block</th>
                            <th class="pt-col-excl">IP Exclusion</th>
                            <th class="pt-col-campaign pt-expert">
                                <button type="button" class="promotix-sortable" :class="ipSortClass('campaign')" @click="setIpSort('campaign')"><span>Campaign</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                            </th>
                            <th class="pt-col-pid pt-expert">PID</th>
                            <th class="pt-col-fp pt-expert" title="Same fingerprint as Advanced / Detailed View">Fingerprint</th>
                            <th class="pt-col-time pt-expert">
                                <button type="button" class="promotix-sortable" :class="ipSortClass('last_seen')" @click="setIpSort('last_seen')" title="When invalid/paid evidence was last recorded for this IP"><span>Last Click</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in sortedIps" :key="row.ip">
                            <tr class="cursor-pointer transition hover:bg-white/5" @click="openIpModal(row)">
                                <td class="pt-col-ip pt-sticky-ip">
                                    <span class="flex items-center gap-[4px]">
                                        <span class="block max-w-[110px] truncate font-mono text-[9px] text-white" :title="row.ip" x-text="ipLabel(row.ip)"></span>
                                        <span x-show="row.is_allowlisted" class="shrink-0 rounded-[3px] bg-emerald-500/20 px-[4px] py-[1px] text-[8px] font-semibold uppercase text-emerald-300">WL</span>
                                    </span>
                                </td>
                                <td class="pt-col-device font-mono text-[9px] text-white/90"
                                    :title="(row.device_id || '') + (row.multi_identity ? (' · ' + (row.distinct_device_count || '?') + ' distinct devices on this IP') : '')"
                                    x-text="row.device_id_label || row.device_id || '—'"></td>
                                <td class="pt-col-conf text-[10px]"
                                    :title="row.multi_identity ? ('IP row: ' + (row.distinct_visitor_count || 0) + ' visitors, ' + (row.distinct_browser_count || 0) + ' browsers, ' + (row.distinct_fingerprint_count || 0) + ' fingerprints') : ''"
                                    x-text="row.identity_confidence_label || 'Unknown'"></td>
                                <td class="pt-col-num text-white" x-text="fmt(row.total)"></td>
                                <td class="pt-col-num text-white/90" x-text="fmt(row.clicks_60m ?? row.total)"></td>
                                <td class="pt-col-num text-rose-300" x-text="fmt(row.invalid)"></td>
                                <td class="pt-col-num text-emerald-300" x-text="fmt(row.valid ?? Math.max(0, Number(row.total || 0) - Number(row.invalid || 0)))"></td>
                                <td class="pt-col-detect max-w-[190px] truncate text-[10px] text-white/85" :title="row.primary_detection || threatsLabel(row)" x-text="row.primary_detection || threatsLabel(row)"></td>
                                <td class="pt-col-risk">
                                    <span class="paid-risk-badge" :class="riskBadgeClass(row.risk_level)" x-text="(row.risk_level || '—') + (row.risk_score != null ? ' ' + row.risk_score : '')"></span>
                                </td>
                                <td class="pt-col-action" x-text="row.action || '—'"></td>
                                <td class="pt-col-excl text-[10px]" x-text="row.ip_exclusion || 'Not needed'"></td>
                                <td class="pt-col-campaign pt-expert text-[10px] text-white/85" :title="row.campaign || ''" x-text="row.campaign || '—'"></td>
                                <td class="pt-col-pid pt-expert font-mono text-[9px] text-white/85" :title="row.paid_identity_id || ''" x-text="row.paid_identity_id || '—'"></td>
                                <td class="pt-col-fp pt-expert font-mono text-[9px] text-white/85" :title="row.fingerprint_id || row.device_fingerprint || ''" x-text="fingerprintLabel(row.fingerprint_id || row.device_fingerprint)"></td>
                                <td class="pt-col-time pt-expert text-[10px] text-white/85" :title="evidenceTimeTitle(row)" x-text="evidenceTimeLabel(row)"></td>
                            </tr>
                        </template>
                        <template x-if="sortedIps.length === 0">
                            <tr>
                                <td colspan="15" class="px-[10px] py-[12px] text-center text-white/60" x-text="filters.campaign ? 'No paid traffic for this campaign in the selected date range.' : 'No paid traffic yet for the selected domain(s) and date range.'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <div class="figma-modal-overlay"
         x-show="ipModal.open" x-cloak x-transition
         @keydown.escape.window="closeIpModal()" @click.self="closeIpModal()">
        <div class="figma-modal figma-modal--click-details">
            <header class="mb-4 flex items-center justify-between gap-3">
                <h3 class="figma-modal-title">Click Details</h3>
                <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeIpModal()" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </header>

            <div class="figma-click-modal-layout">
                <aside class="figma-click-modal-sidebar">
                    <template x-if="ipModal.row && !ipModal.loading">
                        <div class="mb-3 space-y-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-[11px] text-white/70">
                            <p><span class="text-white/45">Paid Clicks (this IP):</span> <span class="text-white" x-text="fmt(ipModal.row.total)"></span></p>
                            <p><span class="text-white/45">Clicks 60m (device/PID):</span> <span class="text-white" x-text="fmt(ipModal.row.clicks_60m ?? ipModal.row.total)"></span></p>
                        </div>
                    </template>
                    <template x-if="ipModal.loading">
                        <p class="text-sm text-white/50">Loading clicks…</p>
                    </template>
                    <template x-for="(c, idx) in ipModal.clicks" :key="idx">
                        <button type="button"
                                class="figma-click-modal-tab"
                                :class="idx === ipModal.activeIndex ? 'is-active' : ''"
                                @click="ipModal.activeIndex = idx">
                            <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                            <p class="text-xs text-white/50" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            <p class="mt-0.5 text-[10px] text-violet-300" x-show="c.is_related" x-text="c.ip ? ('Related · ' + ipLabel(c.ip)) : 'Related · other IP'"></p>
                        </button>
                    </template>
                    <template x-if="!ipModal.loading && ipModal.clicks.length === 0">
                        <p class="text-sm text-white/50">No clicks for this IP.</p>
                    </template>
                </aside>

                <div class="figma-click-modal-body" x-show="ipModal.clicks.length > 0">
                    <template x-if="activeIpClick">
                        <div class="figma-click-modal-fields">
                            <div class="figma-click-modal-compact">
                                <div class="figma-modal-field figma-modal-field--full">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label" x-text="activeIpClick.is_related ? 'IP (related click)' : 'IP'"></p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(activeIpClick.ip || ipModal.row?.ip)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono figma-modal-value--mono-sm"
                                       :title="activeIpClick.ip || ipModal.row?.ip"
                                       x-text="ipLabel(activeIpClick.ip || ipModal.row?.ip)"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Status</p>
                                    <p class="figma-modal-value" :class="activeIpClick.is_invalid ? 'text-rose-400' : 'text-emerald-400'" x-text="activeIpClick.is_invalid ? 'Invalid' : 'Valid'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Invalid Clicks</p>
                                    <p class="figma-modal-value" x-text="fmt(ipModal.row?.invalid ?? 0)"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Valid Clicks</p>
                                    <p class="figma-modal-value" x-text="fmt(ipModal.row?.valid ?? Math.max(0, Number(ipModal.row?.total || 0) - Number(ipModal.row?.invalid || 0)))"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">VPN Hits</p>
                                    <p class="figma-modal-value" x-text="ipModal.row?.vpn_hits > 0 ? fmt(ipModal.row.vpn_hits) : '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Data Center</p>
                                    <p class="figma-modal-value" x-text="ipModal.row?.data_center_hits > 0 ? fmt(ipModal.row.data_center_hits) : '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Browser</p>
                                    <p class="figma-modal-value" x-text="activeIpClick.browser_name || ipModal.row?.browser || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Device Type</p>
                                    <p class="figma-modal-value capitalize" x-text="ipModal.row?.device || activeIpClick.device || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Device ID</p>
                                        <button type="button" class="figma-modal-copy-btn" x-show="activeIpClick.device_id || ipModal.row?.device_id" @click="copyText(activeIpClick.device_id || ipModal.row?.device_id)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono figma-modal-value--mono-sm" :title="activeIpClick.device_id || ipModal.row?.device_id || ''" x-text="activeIpClick.device_id || ipModal.row?.device_id || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Visitor ID</p>
                                        <button type="button" class="figma-modal-copy-btn" x-show="activeIpClick.visitor_id || ipModal.row?.visitor_id" @click="copyText(activeIpClick.visitor_id || ipModal.row?.visitor_id)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono figma-modal-value--mono-sm" :title="activeIpClick.visitor_id || ipModal.row?.visitor_id || ''" x-text="activeIpClick.visitor_id || ipModal.row?.visitor_id || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Browser ID</p>
                                        <button type="button" class="figma-modal-copy-btn" x-show="activeIpClick.browser_id || ipModal.row?.browser_id" @click="copyText(activeIpClick.browser_id || ipModal.row?.browser_id)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono figma-modal-value--mono-sm" :title="activeIpClick.browser_id || ipModal.row?.browser_id || ''" x-text="activeIpClick.browser_id || ipModal.row?.browser_id || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Fingerprint ID</p>
                                        <button type="button" class="figma-modal-copy-btn" x-show="activeIpClick.fingerprint_id || ipModal.row?.fingerprint_id || ipModal.row?.device_fingerprint" @click="copyText(activeIpClick.fingerprint_id || ipModal.row?.fingerprint_id || ipModal.row?.device_fingerprint)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono figma-modal-value--mono-sm" :title="activeIpClick.fingerprint_id || ipModal.row?.fingerprint_id || ipModal.row?.device_fingerprint || ''" x-text="activeIpClick.fingerprint_id || ipModal.row?.fingerprint_id || fingerprintLabel(ipModal.row?.device_fingerprint) || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Paid Identity</p>
                                        <button type="button" class="figma-modal-copy-btn" x-show="activeIpClick.paid_identity_id || ipModal.row?.paid_identity_id" @click="copyText(activeIpClick.paid_identity_id || ipModal.row?.paid_identity_id)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono figma-modal-value--mono-sm" :title="activeIpClick.paid_identity_id || ipModal.row?.paid_identity_id || ''" x-text="activeIpClick.paid_identity_id || ipModal.row?.paid_identity_id || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Identity Conf.</p>
                                    <p class="figma-modal-value" x-text="activeIpClick.identity_confidence_label || ipModal.row?.identity_confidence_label || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Action</p>
                                    <p class="figma-modal-value" x-text="ipModal.row?.action || activeIpClick.action_taken || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Risk</p>
                                    <p class="figma-modal-value" x-text="(ipModal.row?.risk_level || '—') + (ipModal.row?.risk_score != null ? (' · ' + ipModal.row.risk_score + '%') : '')"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">ISP</p>
                                    <p class="figma-modal-value" x-text="ipModal.row?.isp || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">ASN</p>
                                    <p class="figma-modal-value" x-text="ipModal.row?.asn || activeIpClick.asn || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Country</p>
                                    <p class="figma-modal-value inline-flex items-center gap-2">
                                        <img x-show="countryFlagUrl(activeIpClick.country || ipModal.row?.country)"
                                             :src="countryFlagUrl(activeIpClick.country || ipModal.row?.country)"
                                             :alt="countryLabel(activeIpClick.country || ipModal.row?.country)"
                                             class="h-[10px] w-[14px] shrink-0 rounded-[2px] object-cover"
                                             loading="lazy">
                                        <span x-text="countryLabel(activeIpClick.country || ipModal.row?.country)"></span>
                                    </p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Last Click</p>
                                    <p class="figma-modal-value" x-text="formatDateTime(activeIpClick.last_click_at)"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">OS</p>
                                    <p class="figma-modal-value" x-text="activeIpClick.os || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Threat Group</p>
                                    <p class="figma-modal-value" x-text="activeIpClick.threat_group || 'N/A'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Threat Type</p>
                                    <p class="figma-modal-value" x-text="activeIpClick.threat_type || activeIpClick.action_taken || 'N/A'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Campaign</p>
                                    <p class="figma-modal-value" x-text="activeIpClick.campaign || ipModal.row?.campaign || 'N/A'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">Keyword</p>
                                    <p class="figma-modal-value" x-text="activeIpClick.keyword || 'N/A'"></p>
                                </div>
                            </div>

                            <div class="figma-click-modal-wide">
                                <div class="figma-modal-field figma-modal-field--full">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Google Click ID (GCLID)</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(activeIpClick.gclid || activeIpClick.paid_id)" x-show="activeIpClick.gclid || activeIpClick.paid_id">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--long" x-text="activeIpClick.gclid || activeIpClick.paid_id || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">GBRAID</p>
                                    <p class="figma-modal-value figma-modal-value--mono-sm" x-text="activeIpClick.gbraid || '—'"></p>
                                </div>
                                <div class="figma-modal-field">
                                    <p class="figma-modal-label">WBRAID</p>
                                    <p class="figma-modal-value figma-modal-value--mono-sm" x-text="activeIpClick.wbraid || '—'"></p>
                                </div>
                                <div class="figma-modal-field figma-modal-field--full">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Path / Landing Page</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(activeIpClick.path)" x-show="activeIpClick.path">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--long" x-text="activeIpClick.path || '—'"></p>
                                </div>
                                <div class="figma-modal-field figma-modal-field--full" x-show="(activeIpClick.detection_reasons || []).length">
                                    <p class="figma-modal-label">Detection reasons</p>
                                    <p class="figma-modal-value" x-text="(activeIpClick.detection_reasons || []).join(' · ')"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

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
    </div>
</div>

<script>
function paidAdvertisingFigma(config = {}) {
    return {
        countryGetStarted: Boolean(config.countryGetStarted),
        userTimezone: config.userTimezone || 'UTC',
        domainCatalog: config.domainCatalog || {},
        reportingMode: config.reportingMode || 'profile',
        profileTimezone: config.profileTimezone || 'UTC',
        filters: { domain_id: '', google_ads_account_id: '', campaign: '', campaign_id: '', path: '', traffic_source: 'google_ads', window: 'weekly', from: '', to: '' },
        trackingTemplate: '{lpurl}?gclid={gclid}&gbraid={gbraid}&wbraid={wbraid}&utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&utm_term={keyword}&keyword={keyword}',
        summary: { paid_visits: 0, verified_paid_visits: 0, verified_valid_paid_visits: 0, unverified_paid_visits: 0, tag_paid_visits: 0, tracked_clicks: 0, google_clicks: 0, total_click_count: 0, tag_capture_pct: 0, tracking_accuracy_pct: 0, tag_gap_warning: false, google_sync_error: null, google_needs_reconnect: false, google_reconnect_url: '', invalid_paid_visits: 0, invalid_paid_events: 0, unique_invalid_paid_clicks: 0, blocked_paid_visits: 0, block_attempts: 0, block_enforced: 0, flagged_paid_visits: 0, valid_paid_visits: 0, unique_paid_clicks: 0, unique_valid_paid_clicks: 0, unique_ips: 0, invalid_reconciliation: { platform_only: 0, google_only: 0, overlap: 0 } },
        trends: { labels: [], datasets: [], invalid_daily: [] },
        blocking: { labels: [], datasets: [], rules: [], engine: null },
        campaigns: [],
        untaggedDomains: [],
        keywords: [],
        countries: [],
        ips: [],
        ipSortKey: 'invalid',
        ipSortDir: 'desc',
        countrySortKey: 'invalid',
        countrySortDir: 'desc',
        ipModal: { open: false, row: null, clicks: [], activeIndex: 0, loading: false },
        countryModal: { open: false, country: '', rows: [], loading: false },
        get activeIpClick() { return this.ipModal.clicks[this.ipModal.activeIndex] || null; },
        get sortedIps() {
            const rows = (this.ips || []).map((row) => ({
                ...row,
                valid: row.valid ?? Math.max(0, Number(row.total || 0) - Number(row.invalid || 0)),
            }));
            return window.promotixSortable?.sortRows
                ? window.promotixSortable.sortRows(rows, this.ipSortKey, this.ipSortDir, ['invalid', 'valid', 'vpn_hits', 'data_center_hits', 'total', 'risk_score'])
                : rows;
        },
        setIpSort(key) {
            const next = window.promotixSortable?.toggleSort(this.ipSortKey, key, this.ipSortDir)
                || { key, dir: this.ipSortKey === key && this.ipSortDir === 'asc' ? 'desc' : 'asc' };
            this.ipSortKey = next.key;
            this.ipSortDir = next.dir;
        },
        ipSortClass(key) {
            return window.promotixSortable?.sortStateClass(this.ipSortKey, key, this.ipSortDir) || 'is-sortable';
        },
        setCountrySort(key) {
            const next = window.promotixSortable?.toggleSort(this.countrySortKey, key, this.countrySortDir)
                || { key, dir: this.countrySortKey === key && this.countrySortDir === 'asc' ? 'desc' : 'asc' };
            this.countrySortKey = next.key;
            this.countrySortDir = next.dir;
            this.renderCountries();
        },
        countrySortClass(key) {
            return window.promotixSortable?.sortStateClass(this.countrySortKey, key, this.countrySortDir) || 'is-sortable';
        },
        heatmap: { days: [], hours: [], matrix: [] },
        trendsHoverIndex: null,
        hiddenTrendSeries: { lastWeek: false, thisWeek: false, clicks: false, valid: false, invalid: false, blocked: false },
        compareEnabled: false,
        ipViewMode: 'basic',
        cardCharts: {},
        get botRate() {
            const tracked = Number(this.summary.tracked_clicks || this.summary.unique_paid_clicks || this.summary.tag_paid_visits || 0);
            const invalid = Number(this.summary.unique_invalid_paid_clicks || this.summary.invalid_paid_visits || 0);
            return tracked ? Math.round((invalid / tracked) * 100) : 0;
        },
        get showGoogleReconnect() {
            // Only when a specific domain is selected (All Domains has no single OAuth target).
            if (!String(this.filters.domain_id || '').trim()) return false;
            return Boolean(this.summary.google_needs_reconnect);
        },
        get validClickPct() {
            const tracked = Number(this.summary.tracked_clicks || this.summary.unique_paid_clicks || 0);
            const valid = Number(this.summary.unique_valid_paid_clicks ?? this.summary.valid_paid_visits ?? 0);
            return tracked ? Math.min(100, Math.round((valid / tracked) * 1000) / 10) : 0;
        },
        get invalidClickPct() {
            const tracked = Number(this.summary.tracked_clicks || this.summary.unique_paid_clicks || 0);
            const invalid = Number(this.summary.unique_invalid_paid_clicks ?? this.summary.invalid_paid_visits ?? 0);
            return tracked ? Math.min(100, Math.round((invalid / tracked) * 1000) / 10) : 0;
        },
        get blockedBotPct() {
            const visitors = Number(this.summary.tag_paid_visits || 0);
            const blocked = Number(this.summary.block_enforced || this.summary.block_attempts || 0);
            return visitors ? Math.min(100, Math.round((blocked / visitors) * 1000) / 10) : 0;
        },
        get actionEventsBase() {
            return Math.max(1, Number(this.summary.invalid_paid_events || this.summary.invalid_paid_visits || 0));
        },
        get actionBlockedPct() {
            return Math.min(100, Math.round((Number(this.summary.block_enforced || 0) / this.actionEventsBase) * 1000) / 10);
        },
        get actionMonitoredPct() {
            return Math.min(100, Math.round((Number(this.summary.flagged_paid_visits || 0) / this.actionEventsBase) * 1000) / 10);
        },
        get actionWhitelistedPct() {
            return Math.min(100, Math.round((Number(this.whitelistedIpCount || 0) / this.actionEventsBase) * 1000) / 10);
        },
        get whitelistedIpCount() {
            return (this.ips || []).filter((row) => row.is_allowlisted).length;
        },
        get engineIsActive() {
            if (this.blocking?.engine && typeof this.blocking.engine.active === 'boolean') {
                return this.blocking.engine.active;
            }
            return (this.engineDetectionRules || []).some((rule) => rule.on);
        },
        get engineDetectionRules() {
            const fromApi = this.blocking?.engine?.detection_rules;
            if (Array.isArray(fromApi) && fromApi.length) return fromApi;
            return [
                { key: 'vpn', label: 'VPN Detection', on: true },
                { key: 'proxy', label: 'Proxy Detection', on: true },
                { key: 'datacenter', label: 'Datacenter Detection', on: true },
                { key: 'repeated', label: 'Repeated Click Detection', on: true },
                { key: 'bot', label: 'Bot Detection', on: true },
                { key: 'abnormal', label: 'Abnormal Behavior Detection', on: true },
            ];
        },
        get engineProtectionActions() {
            const fromApi = this.blocking?.engine?.protection_actions;
            if (Array.isArray(fromApi) && fromApi.length) return fromApi;
            return [
                { key: 'monitor', label: 'Monitor', desc: 'Low Risk Traffic', active: true, tone: 'low' },
                { key: 'challenge', label: 'Challenge', desc: 'Medium Risk Traffic', active: true, tone: 'medium' },
                { key: 'block', label: 'Block', desc: 'High Risk Traffic', active: true, tone: 'high' },
            ];
        },
        get highRiskIps() {
            const rank = { High: 3, Medium: 2, Low: 1 };
            return (this.sortedIps || [])
                .filter((row) => {
                    const level = String(row.risk_level || '');
                    const action = String(row.action || '').toLowerCase();
                    return level === 'High'
                        || level === 'Medium'
                        || Number(row.risk_score || 0) >= 20
                        || Number(row.invalid || 0) > 0
                        || action === 'blocked'
                        || action === 'block'
                        || action === 'monitored';
                })
                .sort((a, b) => {
                    const lr = (rank[b.risk_level] || 0) - (rank[a.risk_level] || 0);
                    if (lr !== 0) return lr;
                    const inv = Number(b.invalid || 0) - Number(a.invalid || 0);
                    if (inv !== 0) return inv;
                    return Number(b.risk_score || 0) - Number(a.risk_score || 0);
                })
                .slice(0, 6);
        },
        riskScoreLabel(row) {
            if (row?.risk_score == null || row.risk_score === '') return '—';
            return `${Number(row.risk_score)}/100`;
        },
        riskScoreClass(row) {
            const level = String(row?.risk_level || '').toLowerCase();
            if (level === 'high' || Number(row?.risk_score || 0) >= 80) return 'paid-score-high';
            if (level === 'medium' || Number(row?.risk_score || 0) >= 20) return 'paid-score-medium';
            return 'paid-score-low';
        },
        threatsLabel(row) {
            if (row?.threats_label && row.threats_label !== '—') return row.threats_label;
            if (Array.isArray(row?.threats) && row.threats.length) return row.threats.join(', ');
            const parts = [];
            if (Number(row?.vpn_hits || 0) > 0) parts.push('VPN');
            if (Number(row?.data_center_hits || 0) > 0) parts.push('Datacenter');
            if (Number(row?.malicious_hits || 0) > 0) parts.push('Malicious');
            const top = this.threatLabel(row?.top_threat);
            if (top && top !== '—') parts.push(top);
            return [...new Set(parts)].join(', ') || '—';
        },
        actionLabel(row) {
            const raw = String(row?.action || '').trim();
            if (!raw) return '—';
            const lower = raw.toLowerCase();
            if (lower === 'block' || lower === 'blocked') return 'Blocked';
            if (lower === 'flag' || lower === 'flagged' || lower === 'monitor' || lower === 'monitored' || lower === 'challenge') return 'Monitored';
            if (lower === 'allow' || lower === 'whitelisted') return row?.is_allowlisted ? 'Whitelisted' : 'Allow';
            return raw;
        },
        actionToneClass(row) {
            if (row?.action_tone) return `is-${row.action_tone}`;
            const label = this.actionLabel(row).toLowerCase();
            if (label === 'blocked') return 'is-block';
            if (label === 'monitored') return 'is-monitor';
            return 'is-allow';
        },
        toggleCompare() {
            this.compareEnabled = !this.compareEnabled;
            this.hiddenTrendSeries = this.compareEnabled
                ? { lastWeek: false, thisWeek: false }
                : { clicks: false, valid: false, invalid: false, blocked: false };
            this.render(true);
        },
        riskBadgeClass(level) {
            const l = String(level || '').toLowerCase();
            if (l === 'high') return 'is-high';
            if (l === 'medium') return 'is-medium';
            if (l === 'low') return 'is-low';
            return '';
        },
        updatePaidRightbar() {
            const fmt = (n) => this.fmt(n);
            const clicks = Number(this.summary.total_click_count || this.summary.google_clicks || 0);
            const invalid = Number(this.summary.unique_invalid_paid_clicks ?? this.summary.invalid_paid_visits ?? 0);
            const blocked = Number(this.summary.block_enforced || 0);
            const tracked = Number(this.summary.tracked_clicks || this.summary.unique_paid_clicks || 0);
            const rate = tracked ? Math.round((blocked / tracked) * 1000) / 10 : 0;

            const sys = document.getElementById('paid-system-overview');
            if (sys) {
                const set = (key, val) => {
                    const el = sys.querySelector(`[data-sys="${key}"]`);
                    if (el) el.textContent = val;
                };
                set('clicks', fmt(clicks));
                set('invalid', fmt(invalid));
                set('blocked', fmt(blocked));
                set('rate', `${rate}%`);
            }

            const feed = document.getElementById('paid-activity-feed');
            if (feed) {
                const items = [
                    { label: 'Paid traffic in range', value: fmt(tracked) },
                    { label: 'Invalid clicks detected', value: fmt(invalid) },
                    { label: 'IPs blocked', value: fmt(blocked) },
                    { label: 'Monitored / flagged', value: fmt(this.summary.flagged_paid_visits) },
                    { label: 'Cost saved', value: `$${Number(this.summary.cost_saved || 0).toFixed(2)}` },
                ];
                feed.innerHTML = items.map((item) => `
                    <div class="flex items-start justify-between gap-[8px] rounded-[6px] bg-[#0B0B0B]/55 px-[8px] py-[7px]">
                        <span>${item.label}</span>
                        <span class="shrink-0 text-white/85">${item.value}</span>
                    </div>
                `).join('');
            }
        },
        resolveReportingTimezone(googleTz) {
            if (this.reportingMode === 'google' && googleTz) return googleTz;
            if (this.reportingMode === 'utc') return 'UTC';
            return this.profileTimezone;
        },
        applyDomainTimezoneFromCatalog() {
            const id = String(this.filters.domain_id || '');
            const entry = id ? this.domainCatalog[id] : null;
            this.userTimezone = this.resolveReportingTimezone(entry?.google_timezone || null);
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
                const domainCtx = this.summary?.timezone_context?.domain;
                if (domainCtx && String(domainCtx.id) === id) {
                    return {
                        hostname: domainCtx.hostname,
                        timezone: domainCtx.google_timezone_label || domainCtx.google_timezone || 'Timezone not synced — run Sync Ads in Integrations',
                        account: domainCtx.google_account_name || null,
                        hasTimezone: !!domainCtx.google_timezone,
                    };
                }
            }
            const ctx = this.summary?.timezone_context;
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
            const ctx = this.summary?.timezone_context;
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
        get topCampaign() {
            return (this.campaigns || []).find(r => r.campaign) || null;
        },
        campaignBreakdownLink() {
            if ((this.untaggedDomains || []).length > 0) {
                const first = this.untaggedDomains[0];
                return first.setup_url || `/domains/${first.id}/setup`;
            }
            if (this.topCampaign) {
                return @js(route('paid-marketing.detection-settings'));
            }
            return @js(route('domains.index'));
        },
        get campaignOptions() {
            return (this.campaigns || []).filter(r => r.campaign);
        },
        campaignOptionLabel(row) {
            const name = row.campaign || 'Campaign';
            const traffic = Number(row.clicks ?? row.total ?? 0);
            const invalid = Number(row.invalid ?? 0);
            if (traffic > 0 && invalid > 0) {
                return `${name} (${this.fmt(traffic)} · ${this.fmt(invalid)} inv)`;
            }
            if (traffic > 0) {
                return `${name} (${this.fmt(traffic)} clicks)`;
            }
            return `${name} (${this.fmt(row.total ?? 0)})`;
        },
        get heatmapIntensity() {
            const flat = (this.heatmap.matrix || []).flat();
            const max = Math.max(...flat, 1);
            const avg = flat.length ? flat.reduce((a, b) => a + Number(b || 0), 0) / flat.length : 0;
            return Math.min(100, Math.round((avg / max) * 100));
        },
        fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
        fmtCompact(n) {
            const v = Number(n || 0);
            if (v >= 1000) return `${(v / 1000).toFixed(1).replace(/\.0$/, '')}k`;
            return this.fmt(v);
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
            return String(value || 'Unknown');
        },
        countryFlagUrl(value) {
            const code = this.countryCode(value).toLowerCase();
            if (!/^[a-z]{2}$/.test(code)) return '';
            return `/media/flags/${code}`;
        },
        ipLabel(value) {
            const raw = String(value || '').trim();
            if (!raw) return '—';
            if (raw.length > 22) return raw.slice(0, 20) + '…';
            return raw;
        },
        fingerprintLabel(value) {
            const raw = String(value || '').trim();
            if (!raw) return '—';
            return raw.length > 16 ? raw.slice(0, 16) : raw;
        },
        identityLabel(row) {
            const device = String(row?.device_id || '').trim();
            const pid = String(row?.paid_identity_id || '').trim();
            if (device && pid) return `${device} · ${pid}`;
            return device || pid || '—';
        },
        identityShort(row) {
            const device = String(row?.device_id || '').trim();
            const pid = String(row?.paid_identity_id || '').trim();
            if (device) return device.length > 16 ? device.slice(0, 16) + '…' : device;
            if (pid) return pid.length > 16 ? pid.slice(0, 16) + '…' : pid;
            return '—';
        },
        threatLabel(key) {
            const map = { vpn: 'VPN', data_center: 'Data center', malicious: 'Malicious', abnormal_rate_limit: 'Rate limit' };
            const k = String(key || '').toLowerCase();
            if (!k) return '—';
            return map[k] || k.replace(/_/g, ' ');
        },
        dateLabel(value) {
            if (!value) return 'N/A';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return 'N/A';
            return d.toLocaleDateString('en-GB', {
                timeZone: this.userTimezone,
                month: '2-digit',
                day: '2-digit',
                year: '2-digit',
            });
        },
        evidenceTimeValue(row) {
            return row?.last_seen || row?.first_seen || null;
        },
        evidenceTimeLabel(row) {
            return this.formatDateTime(this.evidenceTimeValue(row));
        },
        evidenceTimeTitle(row) {
            const last = row?.last_seen ? this.formatDateTime(row.last_seen) : null;
            const first = row?.first_seen ? this.formatDateTime(row.first_seen) : null;
            if (last && first && last !== first) return `Evidence: ${last} (first seen ${first})`;
            if (last) return `Evidence: ${last}`;
            if (first) return `Evidence: ${first}`;
            return 'No evidence time';
        },
        setWindow() {
            const today = new Date();
            const iso = today.toISOString().slice(0, 10);
            if (this.filters.window === 'today') {
                this.filters.from = iso;
                this.filters.to = iso;
            } else {
                const days = this.filters.window === 'monthly' ? 29 : 6;
                const start = new Date(today.getTime() - days * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = iso;
            }
            try {
                localStorage.setItem('promotix-date-range', JSON.stringify({
                    from: this.filters.from,
                    to: this.filters.to,
                }));
            } catch (e) {}
            window.dispatchEvent(new CustomEvent('promotix:date-range', {
                detail: { from: this.filters.from, to: this.filters.to },
            }));
            this.reload(false, true);
        },
        syncWindowFromDates() {
            if (!this.filters.from || !this.filters.to) return;
            if (this.filters.from === this.filters.to) {
                this.filters.window = 'today';
                return;
            }
            const from = new Date(`${this.filters.from}T00:00:00`);
            const to = new Date(`${this.filters.to}T00:00:00`);
            const days = Math.round((to - from) / 86400000) + 1;
            this.filters.window = days > 10 ? 'monthly' : 'weekly';
        },
        qs(forceGoogle = false) {
            const p = new URLSearchParams();
            if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
            if (this.filters.google_ads_account_id) p.set('google_ads_account_id', this.filters.google_ads_account_id);
            if (this.filters.path) p.set('path', this.filters.path);
            if (this.filters.campaign) p.set('campaign', this.filters.campaign);
            if (this.filters.campaign_id) p.set('campaign_id', this.filters.campaign_id);
            if (this.filters.traffic_source) p.set('traffic_source', this.filters.traffic_source);
            if (this.filters.from) p.set('from', this.filters.from);
            if (this.filters.to) p.set('to', this.filters.to);
            if (forceGoogle) p.set('force_google_sync', '1');
            return p.toString();
        },
        onCampaignChange() {
            this.syncCampaignFilter();
        },
        reloadTimer: null,
        livePollTimer: null,
        googleSyncTimer: null,
        watermarkTimer: null,
        lastWatermarkId: null,
        lastWatermarkVersion: null,
        lastWatermarkCount: null,
        reloadInFlight: false,
        reloadQueued: false,
        reloadQueuedForceGoogle: false,
        summaryRefreshInFlight: false,
        lastSummaryFingerprint: '',
        lastRenderFingerprint: '',
        lastReloadAt: 0,
        livePollOn: false,
        livePollMs: 60000,
        googleSyncMs: 300000,
        watermarkMs: 20000,
        debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
        staggerMs: 1000,
        reloadGeneration: 0,
        sleep(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        },
        /**
         * Fire jobs 1s apart (start stagger) — do not wait for one to finish before starting the next.
         * Each job applies its own slice so cards/tables populate progressively.
         */
        async runStaggered(jobs) {
            const generation = ++this.reloadGeneration;
            const pending = [];
            for (let i = 0; i < jobs.length; i++) {
                if (generation !== this.reloadGeneration) break;
                if (i > 0) await this.sleep(this.staggerMs);
                if (generation !== this.reloadGeneration) break;
                pending.push((async () => {
                    try {
                        await jobs[i]();
                    } catch (e) { /* keep previous slice */ }
                })());
            }
            await Promise.all(pending);
            return generation === this.reloadGeneration;
        },
        scheduleReload() {
            clearTimeout(this.reloadTimer);
            this.reloadTimer = setTimeout(() => this.reload(false, true), this.debounceMs);
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
            this.reload(false, true);
        },
        applyDomainFromUrl() {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('domain_id');
            if (id) this.filters.domain_id = id;
            const accountId = params.get('google_ads_account_id');
            if (accountId) this.filters.google_ads_account_id = accountId;
        },
        onDomainChange() {
            this.filters.campaign = '';
            this.filters.campaign_id = '';
            this.applyDomainTimezoneFromCatalog();
            this.reload(false, true);
        },
        async onCampaignFilterChange() {
            this.syncCampaignFilter();
            await this.reloadIps();
        },
        syncCampaignFilter() {
            if (!this.filters.campaign) {
                this.filters.campaign_id = '';
                return;
            }
            const row = this.campaignOptions.find(r => r.campaign === this.filters.campaign);
            if (!row) {
                this.filters.campaign = '';
                this.filters.campaign_id = '';
                return;
            }
            this.filters.campaign_id = row.campaign_id ? String(row.campaign_id) : '';
        },
        ipsQueryString() {
            const p = new URLSearchParams(this.qs());
            if (this.filters.campaign) {
                p.set('campaign', this.filters.campaign);
            }
            if (this.filters.campaign_id) {
                p.set('campaign_id', this.filters.campaign_id);
            }
            return p.toString();
        },
        async reloadIps() {
            const gen = this.reloadGeneration;
            try {
                const qs = this.ipsQueryString();
                const ips = await fetch(`/paid-marketing/ips?${qs}`).then(r => r.json());
                if (gen !== this.reloadGeneration) return;
                this.ips = Array.isArray(ips) ? ips : (ips?.data || []);
            } catch (e) {
                if (gen !== this.reloadGeneration) return;
                this.ips = [];
            }
        },
        startLivePoll() {
            clearInterval(this.livePollTimer);
            this.livePollTimer = setInterval(() => {
                if (!this.livePollOn || document.hidden) return;
                // Patch click/summary numbers only — never rebuild the whole dashboard.
                this.refreshSummaryOnly(false);
            }, this.livePollMs);
        },
        startGoogleSyncPoll() {
            clearInterval(this.googleSyncTimer);
            this.googleSyncTimer = setInterval(() => {
                if (!this.livePollOn || document.hidden) return;
                // Google click counts: update Paid Traffic card only.
                this.refreshSummaryOnly(true);
            }, this.googleSyncMs);
        },
        async checkWatermark() {
            // Always poll — do not wait for livePollOn. New visits/IPs must invalidate stale views.
            if (document.hidden || this.reloadInFlight || this.summaryRefreshInFlight) return;
            if (Date.now() - this.lastReloadAt < 8000) return;
            try {
                const data = await fetch(`/paid-marketing/watermark?${this.qs()}`).then(r => r.json());
                const version = data.version || `${data.last_id || 0}:${data.count || 0}:${data.domains_sig || ''}`;
                const changed = this.lastWatermarkVersion !== null && version !== this.lastWatermarkVersion;
                const grew = this.lastWatermarkId !== null && Number(data.last_id || 0) > Number(this.lastWatermarkId);
                const countGrew = this.lastWatermarkCount !== null && Number(data.count || 0) > Number(this.lastWatermarkCount);
                this.lastWatermarkId = data.last_id;
                this.lastWatermarkCount = data.count;
                this.lastWatermarkVersion = version;
                if (changed || grew || countGrew) {
                    // Full reload so IP table / charts also pick up new traffic (not summary-only).
                    this.reload(false, false);
                }
            } catch (e) { /* silent — next tick retries */ }
        },
        startWatermarkPoll() {
            clearInterval(this.watermarkTimer);
            this.watermarkTimer = setInterval(() => this.checkWatermark(), this.watermarkMs);
            // Seed baseline version without forcing a second reload.
            this.checkWatermark();
        },
        async refreshSummaryOnly(forceGoogle = false) {
            if (this.reloadInFlight || this.summaryRefreshInFlight) return;
            this.summaryRefreshInFlight = true;
            try {
                const summary = await fetch(`/paid-marketing/summary?${this.qs(forceGoogle)}`).then(r => r.json());
                const fingerprint = JSON.stringify({
                    paid_visits: summary?.paid_visits,
                    invalid_paid_visits: summary?.invalid_paid_visits,
                    total_click_count: summary?.total_click_count,
                    google_clicks: summary?.google_clicks,
                    tag_paid_visits: summary?.tag_paid_visits,
                    block_attempts: summary?.block_attempts,
                    block_enforced: summary?.block_enforced,
                    flagged_paid_visits: summary?.flagged_paid_visits,
                    invalid_reconciliation: summary?.invalid_reconciliation,
                });
                if (fingerprint === this.lastSummaryFingerprint) {
                    this.lastReloadAt = Date.now();
                    return;
                }
                this.lastSummaryFingerprint = fingerprint;
                this.summary = summary;
                if (summary?.timezone_context?.reporting_timezone) {
                    this.userTimezone = summary.timezone_context.reporting_timezone;
                }
                this.syncPaidTimezoneHeader();
                this.lastReloadAt = Date.now();
                // Do NOT call render() — charts/tables stay put; Alpine updates card numbers only.
            } catch (e) {
                /* keep previous summary */
            } finally {
                this.summaryRefreshInFlight = false;
            }
        },
        async init() {
            window.__paidAdvertisingDash = this;
            window.promotixPageLoader?.hide();
            this.applyDomainFromUrl();
            this.applyDomainTimezoneFromCatalog();
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const today = new Date();
                const days = this.filters.window === 'monthly' ? 29 : (this.filters.window === 'today' ? 0 : 6);
                const start = new Date(today.getTime() - days * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = today.toISOString().slice(0, 10);
            }
            this.syncWindowFromDates();
            this.startLivePoll();
            this.startGoogleSyncPoll();
            window.addEventListener('promotix:date-range', (event) => {
                const prevFrom = this.filters.from;
                const prevTo = this.filters.to;
                this.syncHeaderDates();
                if (event?.detail?.from) this.filters.from = event.detail.from;
                if (event?.detail?.to) this.filters.to = event.detail.to;
                this.syncWindowFromDates();
                if (this.filters.from === prevFrom && this.filters.to === prevTo) return;
                this.scheduleReload();
            });
            document.addEventListener('visibilitychange', () => {
                // Auto polls are off by default; no tab-focus refresh either.
                if (!document.hidden && this.livePollOn) this.refreshSummaryOnly(false);
            });
            window.addEventListener('promotix:export-ips-csv', () => this.exportIpsCsv());
            await this.reload(false, false);
            this.startWatermarkPoll();
            window.addEventListener('resize', () => {
                clearTimeout(window.__paidFigmaResize);
                window.__paidFigmaResize = setTimeout(() => this.render(true), 180);
            });
            if (!this._themeObserver) {
                this._themeObserver = new MutationObserver(() => this.render(true));
                this._themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            }
        },
        async reload(forceGoogle = false, withLoader = false) {
            if (this.reloadInFlight) {
                this.reloadQueued = true;
                this.reloadQueuedForceGoogle = this.reloadQueuedForceGoogle || forceGoogle;
                return;
            }
            this.reloadInFlight = true;
            try {
                const qs = this.qs(forceGoogle);
                const ipsQs = this.ipsQueryString();

                const ok = await this.runStaggered([
                    async () => {
                        const gen = this.reloadGeneration;
                        const ips = await fetch(`/paid-marketing/ips?${ipsQs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.ips = Array.isArray(ips) ? ips : (ips?.data || []);
                        this.lastReloadAt = Date.now();
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        const summary = await fetch(`/paid-marketing/summary?${qs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.summary = summary;
                        this.lastSummaryFingerprint = JSON.stringify({
                            paid_visits: summary?.paid_visits,
                            invalid_paid_visits: summary?.invalid_paid_visits,
                            total_click_count: summary?.total_click_count,
                            google_clicks: summary?.google_clicks,
                            tag_paid_visits: summary?.tag_paid_visits,
                            block_attempts: summary?.block_attempts,
                            block_enforced: summary?.block_enforced,
                            flagged_paid_visits: summary?.flagged_paid_visits,
                            invalid_reconciliation: summary?.invalid_reconciliation,
                        });
                        if (summary?.timezone_context?.reporting_timezone) {
                            this.userTimezone = summary.timezone_context.reporting_timezone;
                        }
                        this.syncPaidTimezoneHeader();
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        const campaignsRaw = await fetch(`/paid-marketing/campaigns?${qs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.campaigns = Array.isArray(campaignsRaw) ? campaignsRaw : (campaignsRaw.campaigns || []);
                        this.untaggedDomains = Array.isArray(campaignsRaw) ? [] : (campaignsRaw.untagged_domains || []);
                        this.syncCampaignFilter();
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        const trends = await fetch(`/paid-marketing/trends?${qs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.trends = trends;
                        await this.$nextTick();
                        this.render(false);
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        const keywords = await fetch(`/paid-marketing/keywords?${qs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.keywords = Array.isArray(keywords) ? keywords : [];
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        const countries = await fetch(`/paid-marketing/countries?${qs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.countries = Array.isArray(countries) ? countries : [];
                        await this.$nextTick();
                        this.render(false);
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        const heatmap = await fetch(`/paid-marketing/heatmap?${qs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.heatmap = heatmap && typeof heatmap === 'object' ? heatmap : { days: [], hours: [], matrix: [] };
                        await this.$nextTick();
                        this.render(false);
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        const blocking = await fetch(`/paid-marketing/blocking-activity?${qs}`).then(r => r.json());
                        if (gen !== this.reloadGeneration) return;
                        this.blocking = blocking;
                    },
                    async () => {
                        const gen = this.reloadGeneration;
                        try {
                            const wm = await fetch(`/paid-marketing/watermark?${qs}`).then(r => r.json());
                            if (gen !== this.reloadGeneration) return;
                            this.lastWatermarkId = wm.last_id;
                            this.lastWatermarkCount = wm.count;
                            this.lastWatermarkVersion = wm.version || `${wm.last_id || 0}:${wm.count || 0}:${wm.domains_sig || ''}`;
                        } catch (e) { /* next poll will seed */ }
                    },
                ]);

                if (ok) {
                    await this.$nextTick();
                    this.render(false);
                }
            } catch (e) {
                /* keep previous dashboard state */
            } finally {
                this.reloadInFlight = false;
                if (this.reloadQueued) {
                    const queuedForce = this.reloadQueuedForceGoogle;
                    this.reloadQueued = false;
                    this.reloadQueuedForceGoogle = false;
                    this.reload(queuedForce, withLoader);
                }
            }
        },
        openIpModal(row) {
            this.ipModal.row = row;
            this.ipModal.clicks = [];
            this.ipModal.activeIndex = 0;
            this.ipModal.open = true;
            this.loadIpClicks(row);
        },
        async loadIpClicks(row) {
            if (!row?.ip) return;
            this.ipModal.loading = true;
            try {
                const p = new URLSearchParams(this.qs());
                p.set('ip', row.ip);
                if (row.device_id) p.set('device_id', row.device_id);
                if (row.paid_identity_id) p.set('paid_identity_id', row.paid_identity_id);
                if (row.visitor_id) p.set('visitor_id', row.visitor_id);
                this.ipModal.clicks = await fetch(`/paid-marketing/ip-clicks?${p}`).then(r => r.json());
            } catch (e) {
                this.ipModal.clicks = [];
            } finally {
                this.ipModal.loading = false;
            }
        },
        closeIpModal() {
            this.ipModal.open = false;
            this.ipModal.row = null;
            this.ipModal.clicks = [];
            this.ipModal.activeIndex = 0;
            this.ipModal.loading = false;
        },
        async openCountryIps(country) {
            if (!country) return;
            this.countryModal = { open: true, country, rows: [], loading: true };
            try {
                const p = new URLSearchParams(this.qs());
                p.set('country', country);
                this.countryModal.rows = await fetch(`/paid-marketing/country-ips?${p}`).then(r => r.json());
            } catch (e) {
                this.countryModal.rows = [];
            } finally {
                this.countryModal.loading = false;
            }
        },
        closeCountryModal() {
            this.countryModal = { open: false, country: '', rows: [], loading: false };
        },
        formatDateTime(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return String(value);
            // Client format: Jul 31, 02:42 PM
            return date.toLocaleString('en-US', {
                timeZone: this.userTimezone,
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            });
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
        async copyTrackingTemplate() {
            await this.copyText(this.trackingTemplate);
            window.alert?.('Tracking template copied. Paste it in Google Ads → Campaign → Settings → Tracking template (or Final URL suffix).');
        },
        exportIpsCsv() {
            const qs = this.ipsQueryString();
            window.location.href = `{{ route('paid-marketing.ips.export') }}${qs ? '?' + qs : ''}`;
        },
        render(force = false) {
            const fingerprint = JSON.stringify({
                s: this.summary,
                t: this.trends,
                b: this.blocking,
                k: this.keywords,
                c: this.countries,
                h: this.heatmap,
                hidden: this.hiddenTrendSeries,
                compare: this.compareEnabled,
                ipView: this.ipViewMode,
                light: this.isLightMode(),
            });
            if (!force && fingerprint === this.lastRenderFingerprint) {
                return;
            }
            this.lastRenderFingerprint = fingerprint;
            requestAnimationFrame(() => {
                this.renderCardCharts();
                const labels = this.trends.labels || [];
                const datasets = this.visibleTrendDatasets();
                this.drawPaidTrendLine('paid-trends', labels, datasets, this.trendsHoverIndex);
                this.bindPaidTrendHover('paid-trends', labels, datasets);
                this.renderHeatmap();
                this.renderKeywords();
                this.renderCountries();
                this.updatePaidRightbar();
            });
        },
        isLightMode() {
            return document.documentElement.classList.contains('light-mode');
        },
        brandPrimary() {
            return getComputedStyle(document.documentElement).getPropertyValue('--brand-primary').trim() || '#FF6600';
        },
        brandSecondary() {
            return getComputedStyle(document.documentElement).getPropertyValue('--brand-secondary').trim() || '#CC5200';
        },
        brandPrimaryRgb() {
            return getComputedStyle(document.documentElement).getPropertyValue('--brand-primary-rgb').trim() || '255, 102, 0';
        },
        brandRgba(alpha) {
            return `rgba(${this.brandPrimaryRgb()}, ${alpha})`;
        },
        brandSoft(mixWhite = 0.55) {
            const parts = this.brandPrimaryRgb().split(',').map((s) => parseInt(s.trim(), 10));
            const mix = (c) => Math.round(c + (255 - c) * mixWhite);
            return `rgb(${mix(parts[0])}, ${mix(parts[1])}, ${mix(parts[2])})`;
        },
        brandDark(mixBlack = 0.45) {
            const parts = this.brandPrimaryRgb().split(',').map((s) => parseInt(s.trim(), 10));
            const mix = (c) => Math.round(c * (1 - mixBlack));
            return `rgb(${mix(parts[0])}, ${mix(parts[1])}, ${mix(parts[2])})`;
        },
        compareThisWeekColor() {
            return this.isLightMode() ? this.brandPrimary() : '#FFFFFF';
        },
        trendLineColor(ds) {
            const raw = String(ds?.color || '').toUpperCase();
            if (!raw || raw === '#FFF' || raw === '#FFFFFF') {
                return this.compareThisWeekColor();
            }
            return ds.color;
        },
        trendsLegendItems() {
            if (this.compareEnabled) {
                const datasets = this.trends.datasets || [];
                if (datasets.length) {
                    return datasets.map(ds => ({
                        key: ds.dashed ? 'lastWeek' : 'thisWeek',
                        name: ds.name || (ds.dashed ? 'Last Week' : 'This Week'),
                        color: ds.dashed ? (ds.color || '#FF4BC1') : this.trendLineColor(ds),
                    }));
                }
                return [
                    { key: 'thisWeek', name: 'This Week', color: this.compareThisWeekColor() },
                    { key: 'lastWeek', name: 'Last Week', color: '#FF4BC1' },
                ];
            }
            return [
                { key: 'clicks', name: 'Clicks', color: this.brandSoft(0.35) },
                { key: 'valid', name: 'Valid', color: '#4ade80' },
                { key: 'invalid', name: 'Invalid', color: '#f87171' },
                { key: 'blocked', name: 'Blocked', color: '#f59e0b' },
            ];
        },
        isTrendSeriesHidden(key) {
            return Boolean(this.hiddenTrendSeries?.[key]);
        },
        toggleTrendSeries(key) {
            if (!this.hiddenTrendSeries[key]) this.hiddenTrendSeries[key] = false;
            this.hiddenTrendSeries[key] = !this.hiddenTrendSeries[key];
            const labels = this.trends.labels || [];
            const datasets = this.visibleTrendDatasets();
            this.drawPaidTrendLine('paid-trends', labels, datasets, this.trendsHoverIndex);
        },
        qualityTrendDatasets() {
            const paid = (this.trends.datasets || []).find((ds) => !ds.dashed)?.values || [];
            const invalid = this.trends.invalid_daily || [];
            const blockedSrc = (this.blocking.datasets || []).find((ds) => String(ds.name || '').toLowerCase().includes('block'))?.values || [];
            const blocked = paid.map((_, i) => Number(blockedSrc[i] || 0));
            return [
                { key: 'clicks', name: 'Clicks', values: paid, color: this.brandSoft(0.35) },
                {
                    key: 'valid',
                    name: 'Valid',
                    values: paid.map((v, i) => Math.max(0, Number(v || 0) - Number(invalid[i] || 0))),
                    color: '#4ade80',
                },
                { key: 'invalid', name: 'Invalid', values: invalid, color: '#f87171' },
                { key: 'blocked', name: 'Blocked', values: blocked, color: '#f59e0b' },
            ];
        },
        visibleTrendDatasets() {
            if (this.compareEnabled) {
                return (this.trends.datasets || []).filter(ds => {
                    const key = ds.dashed ? 'lastWeek' : 'thisWeek';
                    return !this.hiddenTrendSeries[key];
                });
            }
            return this.qualityTrendDatasets().filter((ds) => !this.hiddenTrendSeries[ds.key]);
        },
        destroyCardChart(key) {
            if (this.cardCharts[key]) {
                this.cardCharts[key].destroy();
                delete this.cardCharts[key];
            }
        },
        miniChartOptions(extra = {}) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                ...extra,
            };
        },
        renderCardCharts(retry = 0) {
            // Mini bot bars removed from KPI card layout.
            this.destroyCardChart('invalidDonut');
            this.destroyCardChart('botBars');
        },
        renderInvalidDonut() {
            // Donut removed from summary card layout; keep no-op for safety.
        },
        renderBotBars() {
            // Bot bars removed from KPI card layout; keep no-op for safety.
        },
        bindPaidTrendHover(id, labels, datasets) {
            const canvas = document.getElementById(id);
            const tip = document.getElementById('paid-trends-tooltip');
            if (!canvas || !tip) return;

            // Always use live labels/datasets in handlers — never close over a stale render.
            // Rebinding when already attached would stack listeners, so install once.
            if (canvas._paidHoverBound) return;
            canvas._paidHoverBound = true;

            canvas.addEventListener('mousemove', (e) => {
                const liveLabels = this.trends.labels || [];
                const visible = this.visibleTrendDatasets();
                const rect = canvas.getBoundingClientRect();
                const left = 36, right = 14;
                const x = e.clientX - rect.left;
                const innerW = rect.width - left - right;
                if (innerW <= 0 || liveLabels.length === 0 || !visible.length) {
                    tip.hidden = true;
                    return;
                }
                const idx = Math.max(0, Math.min(liveLabels.length - 1, Math.round(((x - left) / innerW) * (liveLabels.length - 1))));
                this.trendsHoverIndex = idx;
                tip.hidden = false;
                tip.innerHTML = this.paidTrendTooltipHtml(liveLabels, visible, idx);
                tip.style.left = `${Math.min(Math.max(x, 60), rect.width - 60)}px`;
                tip.style.top = '12px';
                this.drawPaidTrendLine(id, liveLabels, visible, idx);
            });
            canvas.addEventListener('mouseleave', () => {
                this.trendsHoverIndex = null;
                tip.hidden = true;
                this.drawPaidTrendLine(id, this.trends.labels || [], this.visibleTrendDatasets(), null);
            });
        },
        paidTrendTooltipHtml(labels, datasets, idx) {
            const label = labels[idx] || '';
            const rows = [];
            if (this.compareEnabled) {
                const thisDs = datasets.find(d => !d.dashed) || datasets[0];
                const lastDs = datasets.find(d => d.dashed);
                if (thisDs && !this.isTrendSeriesHidden('thisWeek')) {
                    rows.push(`<span><i style="background:${this.trendLineColor(thisDs)}"></i>${thisDs.name || 'This Week'} ${this.fmtCompact(Number(thisDs.values?.[idx] || 0))}</span>`);
                }
                if (lastDs && !this.isTrendSeriesHidden('lastWeek')) {
                    rows.push(`<span><i style="background:${lastDs.color || '#FF4BC1'}"></i>${lastDs.name || 'Last Week'} ${this.fmtCompact(Number(lastDs.values?.[idx] || 0))}</span>`);
                }
            } else {
                datasets.forEach((ds) => {
                    if (!ds || this.isTrendSeriesHidden(ds.key)) return;
                    rows.push(`<span><i style="background:${this.trendLineColor(ds)}"></i>${ds.name || ds.key} ${this.fmtCompact(Number(ds.values?.[idx] || 0))}</span>`);
                });
            }
            return `<strong>${label}</strong>${rows.join('')}`;
        },
        drawPaidTrendLine(id, labels, datasets, hoverIndex = null) {
            const c = this.canvas(id);
            if (!c) return;
            const { ctx, w, h } = c;
            const series = (datasets || []).map(d => ({ ...d, values: d.values || [] })).filter(d => d.values.length);
            if (!series.length && !(labels || []).length) return;
            const pointCount = Math.max(
                (labels || []).length,
                ...series.map(d => d.values.length),
                1,
            );
            const max = Math.max(...series.flatMap(d => d.values), 1);
            const left = 36, right = 14, top = 16, bottom = 28;
            const xStep = (w - left - right) / Math.max(pointCount - 1, 1);
            const yAt = v => h - bottom - (Number(v) / max) * (h - top - bottom);

            const light = this.isLightMode();
            ctx.strokeStyle = light ? this.brandRgba(0.12) : 'rgba(255,255,255,.14)';
            ctx.lineWidth = 1;
            for (let i = 0; i < 6; i++) {
                const y = top + i * ((h - top - bottom) / 5);
                ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(w - right, y); ctx.stroke();
            }

            ctx.fillStyle = light ? '#6b6578' : 'rgba(255,255,255,0.45)';
            ctx.font = '9px Inter, sans-serif';
            for (let i = 0; i < 6; i++) {
                const val = Math.round(max - (i * max / 5));
                const y = top + i * ((h - top - bottom) / 5);
                ctx.fillText(val >= 1000 ? `${Math.round(val / 1000)}k` : String(val), 4, y + 3);
            }

            const primary = series.find(d => !d.dashed) || series[0];
            if (primary) {
                const pts = primary.values.map((v, i) => ({ x: left + i * xStep, y: yAt(v) }));
                const grad = ctx.createLinearGradient(0, top, 0, h - bottom);
                grad.addColorStop(0, this.brandRgba(0.38));
                grad.addColorStop(1, this.brandRgba(0.02));
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                ctx.lineTo(pts.at(-1)?.x || left, h - bottom);
                ctx.lineTo(left, h - bottom);
                ctx.closePath();
                ctx.fillStyle = grad;
                ctx.fill();
            }

            series.forEach(ds => {
                const pts = ds.values.map((v, i) => ({ x: left + i * xStep, y: yAt(v) }));
                ctx.strokeStyle = this.trendLineColor(ds);
                ctx.lineWidth = ds.dashed ? 1 : 1.5;
                ctx.setLineDash(ds.dashed ? [5, 4] : []);
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                ctx.stroke();
                ctx.setLineDash([]);
            });

            if (hoverIndex != null && labels[hoverIndex] != null) {
                const x = left + hoverIndex * xStep;
                ctx.strokeStyle = light ? this.brandRgba(0.4) : 'rgba(255,255,255,0.55)';
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(x, top);
                ctx.lineTo(x, h - bottom);
                ctx.stroke();
                ctx.setLineDash([]);
                series.forEach(ds => {
                    const v = Number(ds.values[hoverIndex] || 0);
                    ctx.beginPath();
                    ctx.fillStyle = this.trendLineColor(ds) || (ds.dashed ? '#FF4BC1' : this.brandPrimary());
                    ctx.arc(x, yAt(v), 4, 0, Math.PI * 2);
                    ctx.fill();
                });
            }

            ctx.fillStyle = light ? '#5c5470' : '#D9D9D9';
            ctx.font = '10px Inter, sans-serif';
            const labelCount = labels.length;
            const useSingleLetter = labelCount > 10;
            // When many days are selected, only show first letter (M, T, W…).
            // Also thin labels further if the range is very long.
            const drawEvery = labelCount > 21 ? Math.ceil(labelCount / 14) : 1;
            labels.forEach((l, i) => {
                const isEdge = i === 0 || i === labelCount - 1;
                if (!isEdge && i % drawEvery !== 0) return;
                const raw = String(l || '');
                const text = useSingleLetter ? raw.charAt(0).toUpperCase() : raw.slice(0, 3);
                const x = left + i * xStep;
                const tw = ctx.measureText(text).width;
                ctx.fillText(text, x - tw / 2, h - 8);
            });
        },
        drawProtectionLine(id, labels, datasets) {
            const c = this.canvas(id);
            if (!c) return;
            const { ctx, w, h } = c;
            const series = datasets.map(d => d.values || []);
            const max = Math.max(...series.flat(), 1);
            const left = 28, right = 10, top = 8, bottom = 22;
            const colors = [this.brandPrimary(), this.compareThisWeekColor()];
            ctx.strokeStyle = this.isLightMode() ? this.brandRgba(0.14) : 'rgba(255,255,255,.16)';
            ctx.lineWidth = 1;
            for (let i = 0; i < 5; i++) {
                const y = top + i * ((h - top - bottom) / 4);
                ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(w - right, y); ctx.stroke();
            }
            series.forEach((values, si) => {
                const pts = values.map((v, i) => ({
                    x: left + i * ((w - left - right) / Math.max(values.length - 1, 1)),
                    y: h - bottom - (Number(v || 0) / max) * (h - top - bottom),
                }));
                ctx.strokeStyle = colors[si % colors.length];
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                ctx.stroke();
            });
            ctx.fillStyle = this.isLightMode() ? '#6b6578' : '#9D9D9D';
            ctx.font = '9px Inter, sans-serif';
            labels.forEach((l, i) => {
                if (i % Math.ceil(labels.length / 7 || 1) === 0) {
                    const x = left + i * ((w - left - right) / Math.max(labels.length - 1, 1));
                    ctx.fillText(String(l).slice(0, 3), x - 8, h - 4);
                }
            });
        },
        canvas(id) {
            const canvas = document.getElementById(id);
            if (!canvas) return null;
            const dpr = window.devicePixelRatio || 1;
            const w = canvas.clientWidth;
            const h = canvas.clientHeight;
            const nextW = Math.max(1, Math.floor(w * dpr));
            const nextH = Math.max(1, Math.floor(h * dpr));
            // Only reset bitmap when size changes — resetting width/height clears the canvas and causes a visible flash.
            if (canvas.width !== nextW || canvas.height !== nextH) {
                canvas.width = nextW;
                canvas.height = nextH;
            }
            const ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);
            return { ctx, w, h };
        },
        renderHeatmap() {
            const el = document.getElementById('heatmap-grid');
            if (!el) return;
            // API returns Sun..Sat (DAYOFWEEK). Mockup shows Mon..Sun.
            const apiDays = this.heatmap.days?.length ? this.heatmap.days : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const matrix = this.heatmap.matrix || [];
            const order = [1, 2, 3, 4, 5, 6, 0]; // Mon..Sun indices into Sun-first matrix
            const days = order.map((i) => apiDays[i] || ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][i]);
            const flat = matrix.flat();
            const max = Math.max(...flat, 1);
            const hourTicks = [0, 3, 6, 9, 12, 15, 18, 21];
            const head = ['<div class="paid-heatmap-corner"></div>']
                .concat(hourTicks.map((h) => {
                    const label = h === 0 ? '12a' : h < 12 ? `${h}a` : h === 12 ? '12p' : `${h - 12}p`;
                    return `<div class="paid-heatmap-hour">${label}</div>`;
                }))
                .join('');
            const body = order.map((dIdx, rowIdx) => {
                const day = days[rowIdx];
                const cells = hourTicks.map((h) => {
                    const v = Number(matrix?.[dIdx]?.[h] || 0);
                    const t = max ? v / max : 0;
                    const light = this.isLightMode();
                    let bg = light ? this.brandRgba(0.08) : 'rgba(255,255,255,0.08)';
                    if (t > 0.85) bg = '#ef4444';
                    else if (t > 0.65) bg = '#f59e0b';
                    else if (t > 0.4) bg = this.brandPrimary();
                    else if (t > 0.15) bg = light ? this.brandSoft(0.25) : this.brandDark(0.25);
                    else if (t > 0) bg = light ? this.brandSoft(0.55) : this.brandDark(0.55);
                    return `<span class="paid-heatmap-cell" title="${day} ${h}:00 — ${v}" style="background:${bg}"></span>`;
                }).join('');
                return `<div class="paid-heatmap-day">${day}</div>${cells}`;
            }).join('');
            el.innerHTML = `${head}${body}`;
        },
        renderKeywords() {
            const el = document.getElementById('keyword-list');
            if (!el) return;
            const rows = (Array.isArray(this.keywords) ? this.keywords : []).slice(0, 6);
            if (!rows.length) {
                el.innerHTML = `
                    <p class="text-[10px] text-white/70">No keyword data yet.</p>
                    <p class="mt-[6px] text-[9px] leading-relaxed text-white/45">
                        Keywords only appear when Google Ads appends
                        <code class="text-white/70">keyword={keyword}</code> /
                        <code class="text-white/70">utm_term={keyword}</code>
                        on the Final URL (tracking template). New clicks after that will show here.
                    </p>
                    <button type="button" data-copy-tracking-template
                        class="mt-[10px] rounded-[4px] border border-[#6400B2]/60 bg-[#6400B2]/20 px-[8px] py-[5px] text-[10px] font-semibold text-[#c4b5fd] hover:bg-[#6400B2]/35">
                        Copy tracking template
                    </button>
                `;
                el.querySelector('[data-copy-tracking-template]')?.addEventListener('click', () => this.copyTrackingTemplate());
                return;
            }
            el.innerHTML = `
                <table class="paid-keyword-table">
                    <thead>
                        <tr>
                            <th>Keyword</th>
                            <th>Clicks</th>
                            <th>Invalid %</th>
                            <th>Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => {
                            const pct = row.invalid_pct != null ? row.invalid_pct : (row.risk != null ? row.risk : 0);
                            const level = pct >= 40 ? 'High' : pct >= 20 ? 'Medium' : 'Low';
                            const cls = level === 'High' ? 'is-high' : level === 'Medium' ? 'is-medium' : 'is-low';
                            const raw = String(row.keyword ?? '').trim();
                            const name = (!raw || ['null', 'undefined', '{keyword}'].includes(raw.toLowerCase()) ? '—' : raw)
                                .replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            return `<tr>
                                <td class="truncate" title="${name}">${name}</td>
                                <td>${this.fmt(row.total)}</td>
                                <td>${pct}%</td>
                                <td><span class="paid-risk-badge ${cls}">${level}</span></td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            `;
        },
        renderCountries() {
            if (this.countryGetStarted) return;
            const el = document.getElementById('country-list');
            if (!el) return;
            const prepared = (this.countries || []).map((row) => {
                const invalid = Number(row.invalid || 0);
                const total = Number(row.total || 0);
                return {
                    ...row,
                    invalid,
                    total,
                    invalid_rate: total ? Math.round((invalid / total) * 100) : 0,
                };
            });
            const rows = (window.promotixSortable?.sortRows
                ? window.promotixSortable.sortRows(prepared, this.countrySortKey, this.countrySortDir, ['invalid', 'total', 'invalid_rate'])
                : prepared
            ).slice(0, 8);
            el.innerHTML = rows.length ? rows.map(row => {
                const label = this.countryLabel(row.country);
                const flag = this.countryFlagUrl(row.country);
                const flagHtml = flag
                    ? `<img src="${flag}" alt="${label}" class="inline-block h-[10px] w-[14px] shrink-0 rounded-[2px] object-cover" loading="lazy">`
                    : `<span class="inline-block h-[10px] w-[14px] shrink-0 rounded-[2px] bg-white/25"></span>`;
                return `<tr class="cursor-pointer transition hover:bg-white/5" onclick="window.__paidAdvertisingDash?.openCountryIps('${row.country}')">
                    <td class="px-[10px] py-[9px]"><span class="inline-flex items-center gap-[8px]">${flagHtml}<span>${label}</span></span></td>
                    <td class="px-[10px] py-[9px] text-center">${this.fmt(row.invalid)}</td>
                    <td class="px-[10px] py-[9px] text-right">${row.invalid_rate}%</td>
                </tr>`;
            }).join('') : '<tr><td colspan="3" class="px-[10px] py-[14px] text-center text-white/65">No country data for this period.</td></tr>';
        },
    };
}
</script>
@endsection
