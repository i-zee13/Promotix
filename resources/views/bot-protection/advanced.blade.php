@extends('layouts.admin')

@section('title', 'Analytics | Traffic Control')

@section('rightbar')
<div class="figma-rightbar-default paid-rightbar">
    @include('partials.figma-rightbar-header-actions')
    @include('partials.figma-rightbar-analytics')
</div>
@endsection

@section('content')
<div class="brand-page-bg analytics-skin min-h-[calc(100vh-49px)]" x-data="botProtectionAdvancedFigma({ analyticsMode: @json($analyticsMode ?? false) })" x-init="init()">
    <section class="mx-auto w-full min-w-0 px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        @include('partials.advanced-view-pager-styles')
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
            .analytics-skin .bp-adv-kpi-card {
                border-color: rgba(255, 102, 0, 0.55);
            }
            .analytics-skin .bp-adv-kpi-card__icon.is-purple {
                background: rgba(255, 102, 0, 0.22);
                color: #FFB380;
            }
            .analytics-skin .bp-adv-country-row__bar {
                background: #FF6600;
            }
            /* Orange scrollbars on Traffic Control table */
            .analytics-skin .pm-adv-table-x-scroll,
            .analytics-skin .pm-adv-table-body-scroll,
            .analytics-skin .promotix-slim-scroll {
                scrollbar-width: thin;
                scrollbar-color: #FF6600 transparent;
            }
            .analytics-skin .pm-adv-table-x-scroll::-webkit-scrollbar,
            .analytics-skin .pm-adv-table-body-scroll::-webkit-scrollbar,
            .analytics-skin .promotix-slim-scroll::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            .analytics-skin .pm-adv-table-x-scroll::-webkit-scrollbar-thumb,
            .analytics-skin .pm-adv-table-body-scroll::-webkit-scrollbar-thumb,
            .analytics-skin .promotix-slim-scroll::-webkit-scrollbar-thumb {
                background: #FF6600 !important;
                border-radius: 4px;
            }
            .analytics-skin .pm-adv-table-x-scroll::-webkit-scrollbar-track,
            .analytics-skin .pm-adv-table-body-scroll::-webkit-scrollbar-track,
            .analytics-skin .promotix-slim-scroll::-webkit-scrollbar-track {
                background: transparent;
            }
            .tc-journey-drawer {
                position: fixed;
                inset: 0;
                z-index: 80;
                display: flex;
                justify-content: flex-end;
                background: rgba(0,0,0,0.55);
            }
            .tc-journey-drawer__panel {
                width: min(440px, 100%);
                height: 100%;
                background: #141414;
                border-left: 1px solid rgba(255,102,0,0.45);
                padding: 18px 16px;
                overflow-y: auto;
            }
            .tc-journey-drawer__meta {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin: 12px 0 16px;
            }
            .tc-journey-drawer__meta div {
                background: #1a1a1a;
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 8px;
                padding: 8px 10px;
            }
            .tc-journey-drawer__meta span {
                display: block;
                font-size: 10px;
                color: rgba(255,255,255,0.45);
                margin-bottom: 2px;
            }
            .tc-journey-drawer__meta strong {
                font-size: 12px;
                color: #fff;
                word-break: break-all;
            }
            .tc-journey-step {
                display: flex;
                gap: 10px;
                margin-bottom: 10px;
            }
            .tc-journey-step__dot {
                width: 10px;
                height: 10px;
                margin-top: 4px;
                border-radius: 999px;
                background: #FF6600;
                flex-shrink: 0;
            }
            .tc-journey-step__body {
                flex: 1;
                border-bottom: 1px solid rgba(255,255,255,0.06);
                padding-bottom: 8px;
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
            html.light-mode .analytics-skin .bp-adv-kpi-card {
                border-color: rgba(255, 102, 0, 0.35);
                box-shadow: 0 1px 0 rgba(255, 102, 0, 0.08);
            }
            html.light-mode .bp-adv-kpi-card__label,
            html.light-mode .bp-adv-kpi-card__sub { color: #6b6280; }
            html.light-mode .bp-adv-kpi-card__value { color: #1a1a1a; }
            html.light-mode .bp-adv-kpi-card__icon.is-purple { background: rgba(100, 0, 178, 0.12); color: #6400B2; }
            html.light-mode .analytics-skin .bp-adv-kpi-card__icon.is-purple,
            html.light-mode .analytics-skin .bp-adv-kpi-card__icon.is-green,
            html.light-mode .analytics-skin .bp-adv-kpi-card__icon.is-amber {
                background: rgba(255, 102, 0, 0.14);
                color: #ea580c;
            }
            html.light-mode .bp-adv-kpi-card__icon.is-green { background: rgba(34, 197, 94, 0.14); color: #15803d; }
            html.light-mode .bp-adv-kpi-card__icon.is-rose { background: rgba(244, 63, 94, 0.12); color: #be123c; }
            html.light-mode .bp-adv-kpi-card__icon.is-amber { background: rgba(245, 158, 11, 0.14); color: #b45309; }
            html.light-mode .bp-adv-filters-menu {
                background: #fff;
                border-color: #d4c4e8;
            }
            html.light-mode .analytics-skin .bp-adv-filters-menu {
                border-color: rgba(255, 102, 0, 0.35);
            }
            /* Light mode: Advanced Filter + table chrome stay orange (beat global purple remaps) */
            html.light-mode .analytics-skin .analytics-adv-filter-btn,
            html.light-mode .analytics-skin .analytics-adv-filter-btn[class*='bg-[#0f0e0e]'] {
                background: #FF6600 !important;
                background-color: #FF6600 !important;
                border-color: #ea580c !important;
                color: #fff !important;
            }
            html.light-mode .analytics-skin .figma-filter-bar--bp-adv {
                background: #fff4eb !important;
                border-color: rgba(255, 102, 0, 0.4) !important;
            }
            html.light-mode .analytics-skin .pm-adv-table-grid--head {
                background: #141414 !important;
                color: rgba(255, 255, 255, 0.72) !important;
            }
            html.light-mode .analytics-skin .pm-adv-table-grid--row {
                background: transparent !important;
                color: rgba(255, 255, 255, 0.92) !important;
                border: 0 !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
                border-radius: 0 !important;
                margin: 0 !important;
            }
            html.light-mode .analytics-skin .pm-adv-table-grid--row:hover {
                background: rgba(255, 102, 0, 0.08) !important;
            }
            html.light-mode .analytics-skin .pm-adv-table-body-scroll {
                background: #0b0b0b !important;
            }
            /* Traffic Control table body — reference: dark flat rows, not grey pills */
            .analytics-skin .pm-adv-table-shell {
                background: #0b0b0b;
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 0 0 10px 10px;
                overflow: hidden;
            }
            .analytics-skin .pm-adv-table-body-scroll {
                background: #0b0b0b;
            }
            .analytics-skin .pm-adv-table-grid--head {
                background: #141414;
                color: rgba(255, 255, 255, 0.72);
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                margin: 0;
                border-radius: 0;
            }
            .analytics-skin .pm-adv-table-grid--row {
                align-items: start;
                margin: 0 !important;
                padding: 12px;
                border-radius: 0 !important;
                background: transparent !important;
                color: rgba(255, 255, 255, 0.92) !important;
                border: 0 !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
                box-shadow: none !important;
            }
            .analytics-skin .pm-adv-table-grid--row:hover {
                background: rgba(255, 102, 0, 0.08) !important;
            }
            .analytics-skin .pm-adv-table-grid--row > * {
                white-space: normal;
                overflow: visible;
                text-overflow: unset;
                color: inherit;
            }
            .analytics-skin .pm-adv-table-grid--head > * {
                white-space: nowrap;
            }
            .analytics-skin .pm-adv-cell {
                color: rgba(255, 255, 255, 0.9);
            }
            .analytics-skin .pm-adv-cell .text-\[\#8c8787\] {
                color: rgba(255, 255, 255, 0.45) !important;
            }
            .analytics-skin .tc-flow-cell,
            .analytics-skin .tc-events-cell,
            .analytics-skin .tc-source-cell__label,
            .analytics-skin .tc-datetime-cell__date {
                color: rgba(255, 255, 255, 0.9);
            }
            .analytics-skin .tc-flow-cell__arrow,
            .analytics-skin .tc-datetime-cell__time {
                color: rgba(255, 255, 255, 0.45);
            }
            .tc-source-cell {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-width: 0;
                max-width: 100%;
            }
            .tc-source-cell__icon {
                flex-shrink: 0;
                width: 18px;
                height: 18px;
                border-radius: 4px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: rgba(255, 255, 255, 0.75);
                background: rgba(255, 255, 255, 0.06);
            }
            .tc-source-cell__icon.is-direct,
            .tc-source-cell__icon.is-link,
            .tc-source-cell__icon.is-organic,
            .tc-source-cell__icon.is-paid,
            .tc-source-cell__icon.is-social { color: #FF6600; }
            .tc-source-cell__label {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .tc-flow-cell {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 2px 0;
                line-height: 1.35;
                color: rgba(255, 255, 255, 0.88);
            }
            .tc-flow-cell__seg {
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .tc-flow-cell__path { word-break: break-all; }
            .tc-flow-cell__arrow {
                color: rgba(255, 255, 255, 0.35);
                margin: 0 4px;
                flex-shrink: 0;
            }
            .tc-events-cell {
                display: flex;
                flex-direction: column;
                gap: 2px;
                line-height: 1.35;
                color: rgba(255, 255, 255, 0.82);
                font-variant-numeric: tabular-nums;
            }
            .tc-events-cell__row { white-space: nowrap; }
            .tc-datetime-cell {
                display: flex;
                flex-direction: column;
                gap: 2px;
                line-height: 1.3;
            }
            .tc-datetime-cell__date { color: rgba(255, 255, 255, 0.92); }
            .tc-datetime-cell__time { color: rgba(255, 255, 255, 0.45); font-size: 10px; }
            html.light-mode .analytics-skin .tc-flow-cell,
            html.light-mode .analytics-skin .tc-events-cell,
            html.light-mode .analytics-skin .tc-source-cell__label,
            html.light-mode .analytics-skin .tc-datetime-cell__date,
            html.light-mode .analytics-skin .pm-adv-cell {
                color: rgba(255, 255, 255, 0.9) !important;
            }
            html.light-mode .analytics-skin .tc-flow-cell__arrow,
            html.light-mode .analytics-skin .tc-datetime-cell__time {
                color: rgba(255, 255, 255, 0.45) !important;
            }
            html.light-mode .analytics-skin .tc-source-cell__icon {
                background: rgba(255, 255, 255, 0.08);
                color: rgba(255, 255, 255, 0.8);
            }
            html.light-mode .tc-flow-cell,
            html.light-mode .tc-events-cell,
            html.light-mode .tc-source-cell__label,
            html.light-mode .tc-datetime-cell__date { color: #1a1a1a; }
            html.light-mode .tc-flow-cell__arrow,
            html.light-mode .tc-datetime-cell__time { color: #8a8178; }
            html.light-mode .tc-source-cell__icon {
                background: rgba(255, 102, 0, 0.1);
            }
            html.light-mode .analytics-skin .paid-advanced-columns-menu {
                background: #fff !important;
                border-color: rgba(255, 102, 0, 0.4) !important;
            }
            html.light-mode .analytics-skin .paid-advanced-column-option input {
                accent-color: #FF6600 !important;
            }
            html.light-mode .analytics-skin section.border-\[\#FF6600\]\/60,
            html.light-mode .analytics-skin section[class*='border-[#FF6600]'] {
                border-color: rgba(255, 102, 0, 0.55) !important;
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

            .tc-widgets { margin-top: 18px; display: flex; flex-direction: column; gap: 14px; }
            .tc-widgets__row {
                display: grid;
                gap: 14px;
                grid-template-columns: 1fr;
            }
            @media (min-width: 900px) {
                .tc-widgets__row--5 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .tc-widgets__row--3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (min-width: 1280px) {
                .tc-widgets__row--5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
                .tc-widgets__row--3 { grid-template-columns: 1.2fr 1fr 1fr; }
            }
            .tc-widget {
                border-radius: 10px;
                border: 1px solid rgba(255, 102, 0, 0.35);
                background: #111111;
                padding: 14px 14px 12px;
                min-height: 240px;
                display: flex;
                flex-direction: column;
                min-width: 0;
            }
            .tc-widget__title {
                margin: 0 0 12px;
                font-size: 13px;
                font-weight: 600;
                color: #fff;
            }
            .tc-widget__body--donut {
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 1;
                min-width: 0;
            }
            .tc-widget__body--donut .bp-adv-donut {
                width: 96px;
                height: 96px;
            }
            .tc-widget__body--donut .bp-adv-donut__inner {
                width: 64px;
                height: 64px;
            }
            .tc-widget__body--donut .bp-adv-donut__value { font-size: 14px; }
            .tc-widget__body--donut .bp-adv-legend__name {
                font-size: 10px;
            }
            .tc-widget__list {
                display: flex;
                flex-direction: column;
                gap: 8px;
                flex: 1;
                min-height: 0;
                overflow-y: auto;
            }
            .tc-widget__row {
                display: flex;
                justify-content: space-between;
                gap: 8px;
                font-size: 11px;
                border-bottom: 1px solid rgba(255,255,255,0.06);
                padding-bottom: 6px;
            }
            .tc-widget__path {
                color: rgba(255,255,255,0.8);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                min-width: 0;
            }
            .tc-widget__meta {
                color: rgba(255,255,255,0.5);
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }
            .tc-widget__empty {
                margin: auto 0;
                text-align: center;
                font-size: 11px;
                color: rgba(255,255,255,0.4);
                padding: 18px 0;
            }
            .tc-widget__hint {
                margin: 10px 0 0;
                font-size: 10px;
                color: rgba(255,255,255,0.45);
                text-align: center;
            }
            .tc-widget__table-wrap { overflow-x: auto; flex: 1; }
            .tc-widget__table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }
            .tc-widget__table th {
                text-align: left;
                color: rgba(255,255,255,0.45);
                font-weight: 600;
                padding: 4px 6px 8px 0;
                border-bottom: 1px solid rgba(255,255,255,0.08);
            }
            .tc-widget__table td {
                padding: 7px 6px 7px 0;
                color: rgba(255,255,255,0.85);
                border-bottom: 1px solid rgba(255,255,255,0.05);
                font-variant-numeric: tabular-nums;
            }
            .tc-widget__cards {
                display: flex;
                flex-direction: column;
                gap: 8px;
                flex: 1;
                overflow-y: auto;
            }
            .tc-hv-card {
                border: 1px solid rgba(255, 102, 0, 0.28);
                border-radius: 8px;
                padding: 8px 10px;
                background: rgba(255, 102, 0, 0.06);
            }
            .tc-hv-card__rev { margin: 0; font-size: 14px; font-weight: 700; color: #fff; }
            .tc-hv-card__product { margin: 2px 0 0; font-size: 11px; color: #FFB380; }
            .tc-hv-card__meta, .tc-hv-card__ago { margin: 2px 0 0; font-size: 10px; color: rgba(255,255,255,0.45); }
            .tc-quality {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                flex: 1;
                align-items: center;
            }
            .tc-quality__gauge {
                position: relative;
                width: 100%;
                aspect-ratio: 1;
                max-width: 96px;
                margin: 0 auto;
            }
            .tc-quality__ring {
                width: 100%;
                height: 100%;
                border-radius: 999px;
                background: conic-gradient(var(--qc, #FF6600) calc(var(--qp, 0) * 1%), rgba(255,255,255,0.08) 0);
            }
            .tc-quality__center {
                position: absolute;
                inset: 18%;
                border-radius: 999px;
                background: #111;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            .tc-quality__center strong { font-size: 12px; color: #fff; line-height: 1.1; }
            .tc-quality__center span { font-size: 8px; color: rgba(255,255,255,0.45); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.03em; }
            html.light-mode .tc-widget {
                background: #fff;
                border-color: rgba(255, 102, 0, 0.35);
            }
            html.light-mode .tc-widget__title { color: #1a1a1a; }
            html.light-mode .tc-widget__path,
            html.light-mode .tc-widget__table td { color: #2d2d3a; }
            html.light-mode .tc-widget__meta,
            html.light-mode .tc-widget__empty,
            html.light-mode .tc-widget__hint,
            html.light-mode .tc-widget__table th,
            html.light-mode .tc-hv-card__meta,
            html.light-mode .tc-hv-card__ago { color: #6b6578; }
            html.light-mode .tc-hv-card { background: rgba(255, 102, 0, 0.08); }
            html.light-mode .tc-hv-card__rev { color: #1a1a1a; }
            html.light-mode .tc-quality__center { background: #fff; }
            html.light-mode .tc-quality__center strong { color: #1a1a1a; }

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
            /* Grid only on the button — nesting grid on both li + btn clipped legend names to 0 width */
            .bp-adv-legend > li {
                display: block;
                margin: 0;
                padding: 0;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.82);
            }
            .bp-adv-legend__btn {
                display: grid;
                grid-template-columns: 10px minmax(52px, 1fr) auto;
                align-items: center;
                gap: 8px;
                width: 100%;
                margin: 0;
                padding: 2px 0;
                border: 0;
                background: transparent;
                text-align: left;
                cursor: pointer;
                border-radius: 4px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.82);
                transition: opacity 0.15s ease;
            }
            .bp-adv-legend__btn:hover { background: rgba(255, 255, 255, 0.04); }
            .bp-adv-legend__btn.is-hidden {
                opacity: 0.38;
            }
            .bp-adv-legend__btn.is-hidden .bp-adv-legend__swatch {
                background: rgba(255, 255, 255, 0.22) !important;
            }
            .bp-adv-legend__btn.is-hidden .bp-adv-legend__name {
                text-decoration: line-through;
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
            .bp-adv-country-row__flag {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 18px;
                height: 14px;
            }
            .bp-adv-country-row__flag img {
                display: block;
                width: 16px;
                height: 12px;
                object-fit: cover;
                border-radius: 2px;
            }
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
                font-size: 12px;
                font-weight: 600;
                line-height: 1.3;
                color: #fff;
                font-variant-numeric: tabular-nums;
                word-break: break-all;
                overflow-wrap: anywhere;
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
            html.light-mode .bp-adv-legend__btn,
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
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Analytics</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Traffic Control</span>
                <span class="rounded-full border border-[#FF6600]/40 bg-[#FF6600]/10 px-[10px] py-[4px] text-[10px] font-medium text-[#FFB380]">Visitor intelligence · no IP blocking</span>
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
                            <template x-for="opt in trafficSourceOptions()" :key="'ts-' + opt.value">
                                <option :value="opt.value" :disabled="opt.disabled || false" x-text="opt.label"></option>
                            </template>
                        </select>
                    </div>
                </label>
                <label x-show="!analyticsMode" class="bp-adv-f-account flex shrink-0 flex-col justify-center border-r border-black/20 px-[6px] py-[6px]">
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
                        <template x-if="card.key === 'visitors'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        </template>
                        <template x-if="card.key === 'duration'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        <template x-if="card.key === 'pps'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17H7A5 5 0 017 7h2m6 10h2a5 5 0 000-10h-2M8 12h8"/></svg>
                        </template>
                        <template x-if="card.key === 'cta_rate'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                        </template>
                        <template x-if="card.key === 'conv'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        <template x-if="card.key === 'revenue'">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        </template>
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
                    <p class="bp-adv-kpi-card__value" x-text="card.asPercent ? (card.value + '%') : card.value"></p>
                    <p class="bp-adv-kpi-card__sub" x-text="card.sub"></p>
                </article>
            </template>
        </div>

        <section class="overflow-visible rounded-[12px] border border-[#FF6600]/60">
            <div class="flex flex-wrap items-center justify-between gap-[10px] overflow-visible rounded-t-[12px] bg-[#FF6600] px-[16px] py-[12px]">
                <h2 class="text-[18px] font-normal text-white sm:text-[20px]">Traffic Control</h2>
                <div class="flex flex-1 flex-wrap items-center justify-end gap-[10px]">
                    <div class="relative" @click.outside="filterMenuOpen = false">
                        <button type="button" @click="filterMenuOpen = !filterMenuOpen" class="analytics-adv-filter-btn inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-[#ea580c] bg-[#FF6600] px-[10px] text-[11px] text-white">
                            Advanced Filter
                            <span class="rounded-[3px] bg-white/20 px-[5px] text-[10px]" x-text="visibleColumns.length"></span>
                        </button>
                        <div x-show="filterMenuOpen" x-cloak class="paid-advanced-columns-menu promotix-slim-scroll">
                            <p class="mb-[8px] text-[10px] font-semibold uppercase text-white/55">Required columns</p>
                            <template x-for="col in columnCatalog.filter(c => c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option is-locked">
                                    <input type="checkbox" checked disabled>
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                            <p class="mb-[8px] mt-[10px] text-[10px] font-semibold uppercase text-white/55">Additional columns</p>
                            <template x-for="col in columnCatalog.filter(c => !c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option">
                                    <input type="checkbox" :value="col.key" :checked="optionalColumnKeys.includes(col.key)" @change="toggleOptionalColumn(col.key)">
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="relative" x-show="!analyticsMode" @click.outside="moreFiltersOpen = false">
                        <button type="button" @click="moreFiltersOpen = !moreFiltersOpen" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            More filters
                            <svg class="h-[12px] w-[12px] transition-transform" :class="moreFiltersOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="moreFiltersOpen && !analyticsMode" x-cloak x-transition class="bp-adv-filters-menu promotix-slim-scroll">
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
                        <input type="search" :placeholder="analyticsMode ? 'Search session / IP' : 'Search for IP Address'" x-model="filters.ip" @input="scheduleReload(true)" class="w-full border-0 bg-transparent text-[11px] text-[#121212] placeholder:text-[#8c8787] focus:ring-0">
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
                            <label class="flex items-center justify-center">
                                <input type="checkbox" class="rounded border-white/30" disabled>
                            </label>
                            <template x-for="col in visibleColumns" :key="'head-' + col.key">
                                <span class="truncate" x-text="col.label"></span>
                            </template>
                        </div>

                        <div class="pm-adv-table-body-scroll">
                            <template x-for="row in rows" :key="(row.domain_id || '') + '|' + (row.session_key || row.ip || row.id)">
                                <div class="pm-adv-table-grid pm-adv-table-grid--row text-[10px] sm:text-[11px] cursor-pointer" :style="gridStyle" @click="analyticsMode && openJourneyDrawer(row)">
                                    <label class="flex items-center justify-center">
                                        <input type="checkbox" class="rounded border-white/30">
                                    </label>
                                    <template x-for="col in visibleColumns" :key="(row.domain_id || '') + '|' + row.ip + '-' + col.key">
                                        <div class="pm-adv-cell">
                                            @include('partials.advanced-view-rich-cell', ['item' => 'row'])
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <p x-show="rows.length === 0" class="py-[24px] text-center text-[12px] text-[#a9a9a9]" x-text="emptyMessage()"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="adv-pager">
                <span class="adv-pager__label" x-text="paginationLabel()"></span>
                <div class="adv-pager__controls">
                    <div class="adv-pager__pages">
                        <button type="button" class="adv-pager__btn" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
                        <template x-for="item in pageItems" :key="'p-'+item">
                            <button type="button" class="adv-pager__btn" :class="item === meta.page && 'is-active'" :disabled="item === '…'" @click="item !== '…' && changePage(item)" x-text="item"></button>
                        </template>
                        <button type="button" class="adv-pager__btn" :disabled="meta.page * meta.per_page >= meta.total" @click="changePage(meta.page + 1)">›</button>
                    </div>
                    <select class="adv-pager__select" x-model.number="meta.per_page" @change="changePage(1)" aria-label="Rows per page" style="width:108px;height:28px;max-width:108px">
                        <option :value="10">10 / page</option>
                        <option :value="20">20 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                    </select>
                </div>
            </div>
        </section>

        @include('partials.traffic-control-widgets')

        <div class="figma-modal-overlay"
             x-show="eventModal.open" x-cloak x-transition
             @keydown.escape.window="closeEventModal()" @click.self="closeEventModal()">
            <div class="figma-modal max-w-[520px]">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="figma-modal-title" x-text="eventModal.title"></h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeEventModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>
                <p class="mb-3 text-[12px] text-white/60" x-text="eventModal.subtitle"></p>
                <div class="max-h-[320px] overflow-y-auto rounded-[8px] border border-white/15 bg-[#101010] p-3 promotix-slim-scroll">
                    <template x-for="(ev, idx) in eventModal.events" :key="'ev-' + idx">
                        <div class="mb-2 flex items-start justify-between gap-3 border-b border-white/5 pb-2 text-[11px] last:mb-0 last:border-0 last:pb-0">
                            <span class="text-white/85" x-text="ev.label || ev.type || 'Event'"></span>
                            <span class="shrink-0 text-white/45" x-text="ev.time || ev.at || '—'"></span>
                        </div>
                    </template>
                    <p x-show="!(eventModal.events || []).length" class="py-6 text-center text-[12px] text-white/40">No event timeline for this session.</p>
                </div>
            </div>
        </div>

        <div class="tc-journey-drawer" x-show="journeyDrawer.open" x-cloak x-transition
             @keydown.escape.window="closeJourneyDrawer()" @click.self="closeJourneyDrawer()">
            <div class="tc-journey-drawer__panel" @click.stop>
                <header class="mb-2 flex items-center justify-between gap-3">
                    <h3 class="text-[16px] font-semibold text-white">Visitor Journey</h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeJourneyDrawer()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>
                <p class="text-[11px] text-white/50" x-text="journeyDrawer.row?.timezone ? ('Timezone: ' + journeyDrawer.row.timezone) : ''"></p>
                <div class="tc-journey-drawer__meta">
                    <div><span>Visitor IP</span><strong x-text="journeyDrawer.row?.ip || '—'"></strong></div>
                    <div><span>Session</span><strong x-text="journeyDrawer.row?.session_id || '—'"></strong></div>
                    <div><span>Device ID</span><strong x-text="journeyDrawer.row?.fingerprint_id || '—'"></strong></div>
                    <div><span>Source</span><strong x-text="journeyDrawer.row?.source_platform || '—'"></strong></div>
                    <div><span>Landing</span><strong x-text="journeyDrawer.row?.landing_page || '—'"></strong></div>
                    <div><span>Exit</span><strong x-text="journeyDrawer.row?.exit_page || '—'"></strong></div>
                    <div><span>Entry / Exit</span><strong x-text="(journeyDrawer.row?.entry_time || '—') + ' → ' + (journeyDrawer.row?.exit_time || '—')"></strong></div>
                    <div><span>Time on site</span><strong x-text="journeyDrawer.row?.time_on_site || '—'"></strong></div>
                    <div><span>Device</span><strong x-text="(journeyDrawer.row?.device || '—') + ' · ' + (journeyDrawer.row?.country || '—')"></strong></div>
                    <div><span>Region</span><strong x-text="journeyDrawer.row?.region || '—'"></strong></div>
                    <div><span>CTA / Tel</span><strong x-text="(journeyDrawer.row?.cta_clicks || 0) + ' / ' + (journeyDrawer.row?.tel_clicks || 0)"></strong></div>
                    <div><span>Forms / Purchase</span><strong x-text="((journeyDrawer.row?.form_fills ?? journeyDrawer.row?.form_submits) ?? 0) + ' / ' + (journeyDrawer.row?.purchase || 'No')"></strong></div>
                </div>
                <h4 class="mb-2 text-[12px] font-semibold uppercase tracking-wide text-[#FFB380]">Page flow</h4>
                <template x-for="(step, idx) in journeySteps()" :key="'js-' + idx">
                    <div class="tc-journey-step">
                        <span class="tc-journey-step__dot"></span>
                        <div class="tc-journey-step__body">
                            <p class="text-[12px] text-white" x-text="step"></p>
                        </div>
                    </div>
                </template>
                <p x-show="!journeySteps().length" class="py-4 text-center text-[12px] text-white/40">No page flow recorded.</p>
                <h4 class="mb-2 mt-4 text-[12px] font-semibold uppercase tracking-wide text-[#FFB380]">Behaviour timeline</h4>
                <div class="max-h-[240px] overflow-y-auto promotix-slim-scroll">
                    <template x-for="(ev, idx) in journeyTimeline()" :key="'jt-' + idx">
                        <div class="mb-2 flex justify-between gap-3 border-b border-white/5 pb-2 text-[11px]">
                            <span class="text-white/85" x-text="ev.label"></span>
                            <span class="shrink-0 text-white/45" x-text="ev.time"></span>
                        </div>
                    </template>
                    <p x-show="!journeyTimeline().length" class="py-4 text-center text-[12px] text-white/40">No timeline events.</p>
                </div>
                <div class="mt-4 flex gap-2" x-show="journeyDrawer.row?.has_session_recording">
                    <button type="button" class="rounded-[6px] bg-[#FF6600] px-3 py-2 text-[12px] font-medium text-white" @click="openRecording(journeyDrawer.row)">Watch recording</button>
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
                <p class="mb-3 text-[12px] text-white/70" x-text="recordingModal.ip ? `IP: ${recordingModal.ip}` : ''"></p>
                <div class="overflow-hidden rounded-[8px] border border-white/20 bg-[#101010]">
                    <canvas x-ref="recordingCanvas" width="600" height="320" class="h-auto w-full"></canvas>
                </div>
            </div>
        </div>

        <section x-show="!analyticsMode" class="bp-adv-charts">
            <article class="bp-adv-chart-card">
                <h3 class="bp-adv-chart-card__title">Threat Distribution</h3>
                <p class="mb-2 -mt-1 text-[11px] text-white/45">Valid Paid · Suspicious · Repeat · Automated · VPN/Proxy · Datacenter · Out-of-Geo</p>
                <div class="bp-adv-chart-card__body">
                    <div class="bp-adv-donut" :style="`--bp-donut: ${threatDonut.gradient || 'conic-gradient(rgba(100,0,178,0.25) 0 100%)'}`">
                        <div class="bp-adv-donut__inner">
                            <p class="bp-adv-donut__value" x-text="threatDonut.total_label || '0'"></p>
                            <p class="bp-adv-donut__label" x-text="chartThreat.center_label || 'Invalid Clicks'"></p>
                        </div>
                    </div>
                    <ul class="bp-adv-legend">
                        <template x-for="item in (chartThreat.items || [])" :key="'threat-' + legendKey(item)">
                            <li>
                                <button
                                    type="button"
                                    class="bp-adv-legend__btn"
                                    :class="{ 'is-hidden': isThreatHidden(legendKey(item)) }"
                                    :title="isThreatHidden(legendKey(item)) ? 'Click to show' : 'Click to hide'"
                                    @click="toggleThreatLegend(legendKey(item))"
                                >
                                    <span class="bp-adv-legend__swatch" :style="`background:${item.color}`"></span>
                                    <span class="bp-adv-legend__name" x-text="item.label"></span>
                                    <span class="bp-adv-legend__meta">
                                        <span x-text="legendItemPct(item, threatDonut.visible_total, hiddenThreatKeys) + '%'"></span>
                                        <span class="opacity-55" x-text="'(' + item.count_label + ')'"></span>
                                    </span>
                                </button>
                            </li>
                        </template>
                        <li x-show="!(chartThreat.items || []).length" class="!text-white/40">No invalid threat data in range.</li>
                    </ul>
                </div>
                <p class="bp-adv-chart-card__updated" x-text="chartsUpdatedLabel"></p>
            </article>

            <article class="bp-adv-chart-card">
                <h3 class="bp-adv-chart-card__title">Risk Level Distribution</h3>
                <p class="mb-2 -mt-1 text-[11px] text-white/45">Low = clean · Medium = some signals · High = strong suspicion · Critical = likely fraud</p>
                <div class="bp-adv-chart-card__body">
                    <div class="bp-adv-donut" :style="`--bp-donut: ${riskDonut.gradient || 'conic-gradient(rgba(100,0,178,0.25) 0 100%)'}`">
                        <div class="bp-adv-donut__inner">
                            <p class="bp-adv-donut__value" x-text="riskDonut.total_label || '0'"></p>
                            <p class="bp-adv-donut__label" x-text="chartRisk.center_label || 'Unique IPs'"></p>
                        </div>
                    </div>
                    <ul class="bp-adv-legend">
                        <template x-for="item in (chartRisk.items || [])" :key="'risk-' + legendKey(item)">
                            <li>
                                <button
                                    type="button"
                                    class="bp-adv-legend__btn"
                                    :class="{ 'is-hidden': isRiskHidden(legendKey(item)) }"
                                    :title="isRiskHidden(legendKey(item)) ? 'Click to show' : 'Click to hide'"
                                    @click="toggleRiskLegend(legendKey(item))"
                                >
                                    <span class="bp-adv-legend__swatch" :style="`background:${item.color}`"></span>
                                    <span class="bp-adv-legend__name" x-text="item.label"></span>
                                    <span class="bp-adv-legend__meta">
                                        <span x-text="legendItemPct(item, riskDonut.visible_total, hiddenRiskKeys) + '%'"></span>
                                        <span class="opacity-55" x-text="'(' + item.count_label + ')'"></span>
                                    </span>
                                </button>
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
                            <span class="bp-adv-country-row__flag">
                                <img x-show="countryFlagUrl(row.code || row.name)"
                                     :src="countryFlagUrl(row.code || row.name)"
                                     alt=""
                                     width="16"
                                     height="12"
                                     loading="lazy"
                                     decoding="async">
                            </span>
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

        <section x-show="!analyticsMode" class="bp-adv-hip">
            <div class="bp-adv-hip__head">
                <h2 class="bp-adv-hip__title">Recent High Risk IPs</h2>
                <div class="bp-adv-hip__nav">
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
            <a href="{{ route('analytics.dashboard') }}" class="text-[11px] text-[#a9a9a9] hover:text-white hover:underline">&larr; Back to Dashboard</a>
        </p>
    </section>

@include('partials.session-recording-player')
@include('partials.advanced-view-table-helpers')

<script>
function botProtectionAdvancedFigma(config = {}) {
    const fraudColumnCatalog = [
        { key: 'ip', label: 'IP Address', primary: true, min: 120 },
        { key: 'click_id', label: 'Click ID', primary: true, min: 88 },
        { key: 'gclid', label: 'GCLID', primary: true, min: 110 },
        { key: 'campaign', label: 'Campaign', primary: true, min: 100 },
        { key: 'visits', label: 'Visits', primary: true, min: 44 },
        { key: 'last_seen_label', label: 'Last Click', primary: true, min: 96 },
        { key: 'country', label: 'Country', primary: true, min: 72 },
        { key: 'device', label: 'Device', primary: true, min: 72 },
        { key: 'browser', label: 'Browser', primary: true, min: 80 },
        { key: 'os', label: 'OS', primary: true, min: 72 },
        { key: 'intel_risk_score', label: 'Risk Score', primary: true, min: 72 },
        { key: 'intel_risk_level', label: 'Risk Level', primary: true, min: 80 },
        { key: 'invalid_visits', label: 'Invalid', primary: true, min: 52 },
        { key: 'valid_visits', label: 'Valid', primary: true, min: 52 },
        { key: 'action_taken', label: 'Action', primary: true, min: 84 },
        { key: 'status', label: 'Status', primary: true, min: 56 },
        { key: 'domain', label: 'Domain', primary: false, min: 100 },
        { key: 'path', label: 'Path', primary: false, min: 100 },
        { key: 'threat_group', label: 'Threat Group', primary: false, min: 84 },
        { key: 'threat_type', label: 'Threat Type', primary: false, min: 76 },
        { key: 'cta_clicks', label: 'CTA Clicks', primary: false, min: 64 },
        { key: 'tel_clicks', label: 'Tel Clicks', primary: false, min: 64 },
        { key: 'page_changes', label: 'Page Changes', primary: false, min: 72 },
        { key: 'session_recording', label: 'Recording', primary: false, min: 44 },
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

    const analyticsColumnCatalog = [
        // Required — always visible by default (mock Traffic Control body)
        { key: 'ip', label: 'Visitor IP', primary: true, min: 118 },
        { key: 'session_id', label: 'Session ID', primary: true, min: 118 },
        { key: 'source_platform', label: 'Source / Platform', primary: true, min: 140 },
        { key: 'keyword', label: 'Keyword / Headline', primary: true, min: 130 },
        { key: 'landing_page', label: 'Landing Page', primary: true, min: 140 },
        { key: 'page_flow', label: 'Page Flow / Pages Visited', primary: true, min: 220 },
        { key: 'entry_time', label: 'Entry Time', primary: true, min: 88 },
        { key: 'exit_time', label: 'Exit Time', primary: true, min: 88 },
        { key: 'time_on_site', label: 'Time on Site', primary: true, min: 88 },
        { key: 'event_actions', label: 'Events / Actions', primary: true, min: 120 },
        { key: 'cta_clicks', label: 'CTA Clicks', primary: true, min: 72 },
        { key: 'add_to_cart', label: 'Add to Cart', primary: true, min: 80 },
        { key: 'checkout', label: 'Checkout', primary: true, min: 72 },
        { key: 'purchase', label: 'Purchase / Sale', primary: true, min: 88 },
        { key: 'revenue', label: 'Revenue', primary: true, min: 80 },
        { key: 'device', label: 'Device', primary: true, min: 72 },
        { key: 'browser', label: 'Browser', primary: true, min: 80 },
        { key: 'os', label: 'OS', primary: true, min: 72 },
        { key: 'crawler_score', label: 'Crawler Score', primary: true, min: 88 },
        { key: 'automation_score', label: 'Automation Score', primary: true, min: 100 },
        { key: 'malicious_score', label: 'Malicious Activity Score', primary: true, min: 120 },
        // Optional — Advanced Filter only
        { key: 'page_views', label: 'Page Views', primary: false, min: 72 },
        { key: 'fingerprint_id', label: 'Device ID', primary: false, min: 100 },
        { key: 'campaign', label: 'Campaign', primary: false, min: 100 },
        { key: 'headline', label: 'Headline', primary: false, min: 100 },
        { key: 'first_seen', label: 'First Seen', primary: false, min: 96 },
        { key: 'last_seen', label: 'Last Seen', primary: false, min: 96 },
        { key: 'timezone', label: 'Timezone', primary: false, min: 88 },
        { key: 'scroll_events', label: 'Scroll Events', primary: false, min: 72 },
        { key: 'tel_clicks', label: 'Tel Clicks', primary: false, min: 64 },
        { key: 'form_starts', label: 'Form Starts', primary: false, min: 72 },
        { key: 'form_fills', label: 'Form Fills', primary: false, min: 72 },
        { key: 'country', label: 'Country', primary: false, min: 72 },
        { key: 'region', label: 'Region', primary: false, min: 80 },
        { key: 'referrer', label: 'Referrer URL', primary: false, min: 100 },
        { key: 'exit_page', label: 'Exit Page', primary: false, min: 100 },
        { key: 'session_recording', label: 'Recording', primary: false, min: 44 },
    ];

    const analyticsMode = Boolean(config.analyticsMode);
    const columnCatalog = analyticsMode ? analyticsColumnCatalog : fraudColumnCatalog;
    const storageKey = analyticsMode ? 'bp-adv-analytics-columns-v4' : 'bp-adv-optional-columns-v2';

    let savedOptional = [];
    try {
        savedOptional = JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch (e) {}

    return {
        ...window.promotixAdvTableHelpers || {},
        analyticsMode,
        hasDomains: @json($domains->isNotEmpty()),
        loadError: '',
        columnCatalog,
        optionalColumnKeys: Array.isArray(savedOptional) ? savedOptional : [],
        recordingModal: { open: false, ip: '', page_url: '', events: [] },
        eventModal: { open: false, title: '', subtitle: '', events: [] },
        journeyDrawer: { open: false, row: null },
        sessionKpis: {},
        pageAnalytics: null,
        recordingStop: null,
        filterMenuOpen: false,
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
            if (['visits', 'invalid_clicks', 'valid_clicks', 'invalid_visits', 'valid_visits', 'cta_clicks', 'tel_clicks', 'page_changes'].includes(key)) {
                return 52;
            }
            return col.min || 72;
        },
        columnTrack(col) {
            const min = this.columnMinPx(col);
            const key = col.key;
            if (key === 'session_recording') return `${min}px`;
            if (['visits', 'invalid_clicks', 'valid_clicks', 'invalid_visits', 'valid_visits', 'cta_clicks', 'tel_clicks', 'page_changes', 'add_to_cart', 'checkout', 'crawler_score', 'automation_score', 'malicious_score'].includes(key)) {
                return `${min}px`;
            }
            if (key === 'ip') return `minmax(${min}px, 1.15fr)`;
            if (key === 'page_flow' || key === 'event_actions') return `minmax(${min}px, 1.8fr)`;
            if (key === 'source_platform' || key === 'landing_page' || key === 'keyword') return `minmax(${min}px, 1.35fr)`;
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
            traffic_source: analyticsMode ? '' : 'google_ads',
            google_ads_account_id: '',
            campaign: '',
            path: '',
            ip: '',
            country: '',
            device: '',
            action: '',
            threat_group: '',
            only_invalid: false,
            only_paid: false,
            from: '',
            to: '',
        },
        rows: [],
        meta: { total: 0, page: 1, per_page: 20, domain_count: 0, paid_hidden: 0 },
        moreFiltersOpen: false,
        stats: { blocked: 0, invalid_traffic: 0, paid_traffic: 0, bot_detection: 0, country: 0, overall: 0 },
        chartThreat: { items: [], gradient: '', total_label: '0', center_label: 'Invalid Clicks' },
        chartRisk: { items: [], gradient: '', total_label: '0', center_label: 'Unique IPs' },
        chartCountries: [],
        highRiskIps: [],
        chartsUpdatedAt: null,
        hiddenThreatKeys: {},
        hiddenRiskKeys: {},
        hiddenSourceKeys: {},
        hiddenEngagementKeys: {},
        legendKey(item) {
            return String(item?.key || item?.label || item?.name || '').trim();
        },
        isThreatHidden(key) {
            return Boolean(this.hiddenThreatKeys[key]);
        },
        isRiskHidden(key) {
            return Boolean(this.hiddenRiskKeys[key]);
        },
        isSourceHidden(key) {
            return Boolean(this.hiddenSourceKeys[key]);
        },
        isEngagementHidden(key) {
            return Boolean(this.hiddenEngagementKeys[key]);
        },
        toggleThreatLegend(key) {
            if (!key) return;
            this.hiddenThreatKeys = { ...this.hiddenThreatKeys, [key]: !this.hiddenThreatKeys[key] };
        },
        toggleRiskLegend(key) {
            if (!key) return;
            this.hiddenRiskKeys = { ...this.hiddenRiskKeys, [key]: !this.hiddenRiskKeys[key] };
        },
        toggleSourceLegend(key) {
            if (!key) return;
            const visible = (this.pageAnalytics?.traffic_sources || []).filter((i) => !this.isSourceHidden(this.legendKey(i)));
            if (!this.isSourceHidden(key) && visible.length <= 1 && this.legendKey(visible[0]) === key) return;
            this.hiddenSourceKeys = { ...this.hiddenSourceKeys, [key]: !this.hiddenSourceKeys[key] };
        },
        toggleEngagementLegend(key) {
            if (!key) return;
            const visible = (this.pageAnalytics?.engagement || []).filter((i) => !this.isEngagementHidden(this.legendKey(i)));
            if (!this.isEngagementHidden(key) && visible.length <= 1 && this.legendKey(visible[0]) === key) return;
            this.hiddenEngagementKeys = { ...this.hiddenEngagementKeys, [key]: !this.hiddenEngagementKeys[key] };
        },
        buildDonutFromItems(items, hiddenMap) {
            const all = Array.isArray(items) ? items : [];
            const visible = all.filter((item) => !hiddenMap[this.legendKey(item)]);
            const total = visible.reduce((sum, item) => sum + Number(item.count || 0), 0);
            const base = Math.max(total, 1);
            const stops = [];
            let cursor = 0;
            visible.forEach((item) => {
                const count = Number(item.count || 0);
                if (count <= 0) return;
                const pct = (count / base) * 100;
                const next = cursor + pct;
                stops.push(`${item.color} ${cursor}% ${next}%`);
                cursor = next;
            });
            if (!stops.length) {
                stops.push('rgba(100,0,178,0.25) 0% 100%');
            }
            return {
                visible_total: total,
                total_label: Number(total).toLocaleString(),
                gradient: `conic-gradient(${stops.join(', ')})`,
            };
        },
        legendItemPct(item, visibleTotal, hiddenMap = {}) {
            const key = this.legendKey(item);
            if (hiddenMap[key]) return 0;
            const count = Number(item?.count ?? item?.value ?? 0);
            const base = Math.max(Number(visibleTotal || 0), 1);
            if (!count) return 0;
            return Math.round((count / base) * 1000) / 10;
        },
        get threatDonut() {
            return this.buildDonutFromItems(this.chartThreat.items, this.hiddenThreatKeys);
        },
        get riskDonut() {
            return this.buildDonutFromItems(this.chartRisk.items, this.hiddenRiskKeys);
        },
        get hasThreatData() {
            return (this.chartThreat.items || []).some((item) => Number(item?.count || 0) > 0);
        },
        get hasRiskData() {
            return (this.chartRisk.items || []).some((item) => Number(item?.count || 0) > 0);
        },
        get hasCountryFlags() {
            return (this.chartCountries || []).length > 0;
        },
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
            if (this.analyticsMode) {
                const k = this.sessionKpis || {};
                const pa = this.pageAnalytics || {};
                const cta = Number(k.cta_clicks ?? pa?.kpis?.cta_clicks ?? 0);
                const sessions = Math.max(1, Number(k.total_sessions ?? this.meta.total ?? 0));
                const ctaRate = ((cta / sessions) * 100).toFixed(2);
                return [
                    { key: 'visitors', label: 'Unique Visitors', value: this.fmt(pa?.kpis?.total_visitors ?? k.total_sessions ?? this.meta.total ?? 0), tone: 'green', sub: 'Visits in selected range', asPercent: false },
                    { key: 'duration', label: 'Avg. Session Duration', value: pa?.journey_summary?.avg_session_duration || '00:00:00', tone: 'green', sub: 'Entry → exit average', asPercent: false },
                    { key: 'pps', label: 'Pages per Session', value: Number(pa?.pages_per_session ?? 0).toFixed(2), tone: 'amber', sub: 'Average depth', asPercent: false },
                    { key: 'cta_rate', label: 'CTA Click Rate', value: ctaRate, tone: 'amber', sub: 'CTA clicks / sessions', asPercent: true },
                    { key: 'conv', label: 'Conversion Rate', value: Number(k.conversion_rate ?? pa?.kpis?.conversion_rate ?? 0).toFixed(2), tone: 'green', sub: 'Purchase / session rate', asPercent: true },
                    { key: 'revenue', label: 'Sales / Revenue', value: pa?.conversion_summary?.revenue || '$0.00', tone: 'green', sub: 'Attributed purchase revenue', asPercent: false },
                ];
            }
            return [
                { key: 'blocked', label: 'Blocked', value: this.stats.blocked ?? 0, tone: 'rose', sub: 'Blocked actions in range', asPercent: true },
                { key: 'invalid_traffic', label: 'Invalid Traffic', value: this.stats.invalid_traffic ?? 0, tone: 'amber', sub: 'Flagged as invalid', asPercent: true },
                { key: 'paid_traffic', label: 'Paid Traffic', value: this.stats.paid_traffic ?? 0, tone: 'purple', sub: 'Attributed paid share', asPercent: true },
                { key: 'bot_detection', label: 'Bot Detection', value: this.stats.bot_detection ?? 0, tone: 'purple', sub: 'VPN / DC / rate threats', asPercent: true },
                { key: 'country', label: 'Country', value: this.stats.country ?? 0, tone: 'amber', sub: 'Visits with country data', asPercent: true },
                { key: 'overall', label: 'Overall', value: this.stats.overall ?? 0, tone: 'green', sub: 'Valid traffic share', asPercent: true },
            ];
        },
        get sourceDonut() {
            return this.buildDonut(this.pageAnalytics?.traffic_sources || [], 'Visitors', this.hiddenSourceKeys);
        },
        get engagementDonut() {
            return this.buildDonut(this.pageAnalytics?.engagement || [], 'Sessions', this.hiddenEngagementKeys);
        },
        buildDonut(items, centerLabel = '', hiddenMap = {}) {
            const rows = (items || []).filter((i) => {
                if (hiddenMap[this.legendKey(i)]) return false;
                return Number(i.value || 0) > 0;
            });
            const total = rows.reduce((a, r) => a + Number(r.value || 0), 0);
            if (!total) {
                return {
                    gradient: 'conic-gradient(rgba(255,102,0,0.2) 0 100%)',
                    total_label: '0',
                    center_label: centerLabel,
                    visible_total: 0,
                };
            }
            let deg = 0;
            const stops = rows.map((r) => {
                const span = (Number(r.value || 0) / total) * 360;
                const start = deg;
                deg += span;
                return `${r.color || '#FF6600'} ${start}deg ${deg}deg`;
            });
            return {
                gradient: `conic-gradient(${stops.join(', ')})`,
                total_label: this.fmt(total),
                center_label: centerLabel,
                visible_total: total,
            };
        },
        qualityScore(kind) {
            const q = this.pageAnalytics?.quality || {};
            const map = {
                crawler: q.crawler_score ?? q.crawlers ?? 0,
                automation: q.automation_score ?? q.automation ?? 0,
                malicious: q.malicious_score ?? q.malicious ?? 0,
            };
            return Number(map[kind] || 0).toFixed(1);
        },
        qualityRingStyle(kind) {
            const score = Math.max(0, Math.min(100, Number(this.qualityScore(kind))));
            const colors = { crawler: '#22C55E', automation: '#FF6600', malicious: '#F59E0B' };
            return `--qp:${score};--qc:${colors[kind] || '#FF6600'}`;
        },
        trafficSourceOptions() {
            if (this.analyticsMode) {
                return [
                    { value: '', label: 'All Sources' },
                    { value: 'organic', label: 'Organic' },
                    { value: 'direct', label: 'Direct' },
                    { value: 'social', label: 'Social' },
                    { value: 'referral', label: 'Referral' },
                    { value: 'paid', label: 'Paid' },
                ];
            }
            return [
                { value: 'google_ads', label: 'Google Ads' },
                { value: 'meta_ads', label: 'Meta Ads', disabled: true },
                { value: 'microsoft_ads', label: 'Microsoft Ads', disabled: true },
            ];
        },
        fmt(n) { return new Intl.NumberFormat().format(Number(n || 0)); },
        qs(extra = {}) {
            const p = new URLSearchParams();
            Object.entries({ ...this.filters, ...extra }).forEach(([k, v]) => {
                if (v === false || v === '' || v === null || v === undefined) return;
                p.set(k, v === true ? '1' : v);
            });
            return p.toString();
        },
        csvHref() {
            const extra = this.analyticsMode ? { mode: 'sessions', source: 'traffic-control' } : {};
            return `/bot-protection/export.csv?${this.qs(extra)}`;
        },
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
        emptyMessage() {
            if (this.loadError) return this.loadError;
            if (!this.hasDomains) {
                return this.analyticsMode
                    ? 'Add a domain and install the tracking tag to see session intelligence.'
                    : 'Add a domain and install the tracking tag to see IPs here.';
            }
            if (this.analyticsMode) {
                return 'No matching sessions in this window.';
            }
            const hidden = Number(this.meta.paid_hidden || 0);
            if (hidden > 0) {
                return `No organic IPs in this window. ${hidden.toLocaleString()} paid Google Ads IP${hidden === 1 ? '' : 's'} ${hidden === 1 ? 'is' : 'are'} listed under Paid Advertising.`;
            }
            return 'No matching IPs in this window.';
        },
        async parseJson(res) {
            try {
                return await res.json();
            } catch (e) {
                return {};
            }
        },
        async reload(resetPage = false) {
            if (resetPage) this.meta.page = 1;
            this.loadError = '';
            window.promotixPageLoader?.show(this.analyticsMode ? 'Loading Traffic Control…' : 'Loading Traffic Control…');
            try {
                if (this.analyticsMode) {
                    const qs = this.qs({ page: this.meta.page, per_page: this.meta.per_page });
                    const [sessionsRes, analyticsRes] = await Promise.all([
                        fetch(`/bot-protection/traffic-control/sessions?${qs}`),
                        fetch(`/bot-protection/page-analytics?${this.qs()}`),
                    ]);
                    const sessions = await this.parseJson(sessionsRes);
                    const analytics = await this.parseJson(analyticsRes);
                    this.rows = sessions.data || [];
                    this.meta = { ...this.meta, ...(sessions.meta || {}) };
                    if (!sessionsRes.ok) {
                        this.loadError = sessions.error || sessions.message || `Could not load sessions (${sessionsRes.status}).`;
                        this.rows = [];
                    }
                    const k = analytics?.kpis || {};
                    this.pageAnalytics = analytics?.kpis ? analytics : null;
                    this.sessionKpis = {
                        total_sessions: this.meta.total || 0,
                        cta_clicks: k.cta_clicks || 0,
                        tel_clicks: k.tel_clicks || 0,
                        form_submits: k.form_submits || 0,
                        purchases: k.purchases || 0,
                        conversion_rate: k.conversion_rate || 0,
                    };
                    return;
                }

                const statsRes = await fetch(`/bot-protection/bot-stats?${this.qs()}`);
                const stats = await this.parseJson(statsRes);
                if (!statsRes.ok) {
                    this.loadError = stats.error || `Could not load stats (${statsRes.status}).`;
                }
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
                await this.$nextTick();
                window.promotixPageLoader?.hide();

                const qs = this.qs({ page: this.meta.page, per_page: this.meta.per_page });
                const visitsRes = await fetch(`/bot-protection/visits?${qs}`);
                const visits = await this.parseJson(visitsRes);
                this.rows = visits.data || [];
                this.meta = { ...this.meta, ...(visits.meta || {}) };
                if (!visitsRes.ok) {
                    this.loadError = visits.error || visits.message || `Could not load IPs (${visitsRes.status}).`;
                    this.rows = [];
                }
            } catch (e) {
                this.loadError = 'Could not load Traffic Control. Check the network tab and retry.';
                this.rows = [];
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
            return `Showing ${start} to ${end} of ${Number(this.meta.total || 0).toLocaleString()} results`;
        },
        get pageItems() {
            const totalPages = Math.max(1, Math.ceil((this.meta.total || 0) / Math.max(this.meta.per_page, 1)));
            return this.pagerPages(this.meta.page, totalPages);
        },
        toggleOptionalColumn(key) {
            if (this.optionalColumnKeys.includes(key)) {
                this.optionalColumnKeys = this.optionalColumnKeys.filter(k => k !== key);
            } else {
                this.optionalColumnKeys = [...this.optionalColumnKeys, key];
            }
            try {
                localStorage.setItem(storageKey, JSON.stringify(this.optionalColumnKeys));
            } catch (e) {}
        },
        openEventDrilldown(row, kind) {
            const key = String(kind || '');
            const countRaw = row?.[key];
            const count = key === 'purchase'
                ? (String(countRaw).toLowerCase() === 'yes' ? 1 : Number(countRaw || 0))
                : Number(countRaw || 0);
            if (!count) return;
            const timeline = row?.event_detail?.timeline || row?.event_detail?.cta || [];
            const matchers = {
                cta_clicks: (t) => t.includes('cta') || t.includes('click'),
                tel_clicks: (t) => t.includes('tel') || t.includes('phone'),
                form_starts: (t) => t.includes('form') && t.includes('start'),
                form_submits: (t) => t.includes('form') && (t.includes('submit') || t.includes('fill')),
                add_to_cart: (t) => t.includes('cart'),
                checkout: (t) => t.includes('checkout'),
                purchase: (t) => t.includes('purchase') || t.includes('sale'),
            };
            const match = matchers[key] || (() => true);
            const titles = {
                cta_clicks: 'CTA Click Timeline',
                tel_clicks: 'Tel Click Timeline',
                form_starts: 'Form Start Timeline',
                form_submits: 'Form Submit Timeline',
                add_to_cart: 'Add to Cart Timeline',
                checkout: 'Checkout Timeline',
                purchase: 'Purchase Timeline',
            };
            const events = (Array.isArray(timeline) ? timeline : [])
                .filter(ev => match(String(ev?.type || ev?.label || ev?.kind || '').toLowerCase()))
                .map(ev => ({
                    label: ev.label || ev.detail || ev.kind || ev.type || 'Event',
                    time: ev.t != null ? `${Math.max(0, Math.round(Number(ev.t) / 1000))}s` : (ev.time || ev.at || '—'),
                }));
            this.eventModal = {
                open: true,
                title: titles[key] || 'Event Timeline',
                subtitle: `${count} event(s) · Session ${row.session_id || row.session_key || row.ip || ''}`,
                events: events.length ? events : [{ label: `${count} ${key.replace(/_/g, ' ')} event(s) recorded`, time: row.last_seen || '—' }],
            };
        },
        closeEventModal() {
            this.eventModal = { open: false, title: '', subtitle: '', events: [] };
        },
        openJourneyDrawer(row) {
            if (!row) return;
            this.journeyDrawer = { open: true, row };
        },
        closeJourneyDrawer() {
            this.journeyDrawer = { open: false, row: null };
        },
        journeySteps() {
            const row = this.journeyDrawer?.row;
            if (!row) return [];
            if (Array.isArray(row.pages) && row.pages.length) return row.pages;
            const flow = String(row.page_flow || '');
            if (!flow || flow === '—') {
                return [row.landing_page, row.exit_page].filter((p, i, a) => p && p !== '—' && a.indexOf(p) === i);
            }
            return flow.split(/\s*(?:->|→)\s*/).map(s => s.trim()).filter(Boolean);
        },
        journeyTimeline() {
            const timeline = this.journeyDrawer?.row?.event_detail?.timeline || [];
            return (Array.isArray(timeline) ? timeline : []).map(ev => ({
                label: ev.label || ev.detail || ev.kind || ev.type || 'Event',
                time: ev.t != null ? `${Math.max(0, Math.round(Number(ev.t) / 1000))}s` : (ev.time || '—'),
            }));
        },
        cellValue(row, key) {
            if (key === 'ip') return this.ipLabel(row);
            if (key === 'session_id') return row.session_id || row.session_key || '—';
            if (key === 'form_fills') return row.form_fills ?? row.form_submits ?? 0;
            if (key === 'keyword') {
                const kw = String(row.keyword || '').trim();
                const hl = String(row.headline || '').trim();
                if (kw && hl && kw !== hl) return `${kw} · ${hl}`;
                return kw || hl || '—';
            }
            if (key === 'event_actions') {
                const rows = this.eventActionRows(row);
                return rows.length ? rows.map((ev) => `${ev.key} (${ev.count})`).join(', ') : '—';
            }
            if (key === 'page_flow') {
                const parts = this.pageFlowParts(row);
                return parts.length ? parts.join(' -> ') : '—';
            }
            if (key === 'entry_clock' || key === 'exit_clock') {
                const value = row[key];
                return value ? String(value) : '';
            }
            if (key === 'fingerprint_id') {
                const v = String(row.fingerprint_id || '');
                if (!v) return '—';
                return v.length > 18 ? v.slice(0, 16) + '…' : v;
            }
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

