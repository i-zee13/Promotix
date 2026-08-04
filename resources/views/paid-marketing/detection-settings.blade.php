@extends('layouts.admin')

@section('title', 'Paid Marketing | Detection')

@section('content')
@php
    $matrix = $settings?->suspicious_matrix ?? [];
    $matrixRows = [
        ['vpn', 'VPN', $matrix['vpn'] ?? 'allow'],
        ['proxy', 'Proxy', $matrix['proxy'] ?? 'block'],
        ['data_center', 'Data Center', $matrix['data_center'] ?? 'block'],
        ['abnormal_rate_limit', 'Abnormal Rate Limit', $matrix['abnormal_rate_limit'] ?? 'block'],
    ];
@endphp

<div class="brand-page-bg min-h-[calc(100vh-49px)]"
     x-data="detectionPageFilters(@js([
         'domainId' => (string) ($domain?->id ?? ''),
         'path' => (string) request('path', ''),
         'googleAdsAccountId' => (string) request('google_ads_account_id', ''),
         'campaign' => (string) request('campaign', ''),
         'trafficSource' => (string) request('traffic_source', 'google_ads'),
     ]))"
>
    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[19px] xl:pt-[68px]">
        <style>
            .figma-filter-bar--detection {
                width: fit-content !important;
                max-width: 100% !important;
                margin-left: auto;
                display: inline-flex !important;
                flex-wrap: nowrap;
                align-items: stretch;
                gap: 0 !important;
                overflow: visible;
            }
            .figma-filter-bar--detection > label {
                flex: 0 0 auto !important;
                padding-left: 8px !important;
                padding-right: 8px !important;
            }
            .figma-filter-bar--detection .figma-filter-calendar-host {
                display: flex;
                flex: 0 0 auto;
                align-items: center;
                justify-content: center;
                align-self: stretch;
                border-left: 1px solid rgba(0, 0, 0, 0.2);
                padding: 6px 10px;
                margin: 0;
            }
            @media (max-width: 900px) {
                .figma-filter-bar--detection {
                    width: 100% !important;
                    display: flex !important;
                    flex-wrap: wrap;
                    margin-left: 0;
                }
                .figma-filter-bar--detection > label { flex: 1 1 140px !important; }
                .figma-filter-bar--detection .figma-filter-calendar-host {
                    flex: 1 1 100%;
                    border-left: 0;
                    border-top: 1px solid rgba(0, 0, 0, 0.12);
                    justify-content: flex-start;
                }
            }

            /* Primary Access Control summary cards */
            .figma-pac { margin-bottom: 18px; }
            .figma-pac-head {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 14px;
            }
            .figma-pac-title {
                margin: 0 0 4px;
                font-size: 18px;
                font-weight: 600;
                color: #fff;
            }
            .figma-pac-lead {
                margin: 0;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.55);
            }
            .figma-pac-manage-all {
                flex-shrink: 0;
                border-radius: 8px;
                border: 1px solid rgba(255, 255, 255, 0.35);
                background: transparent;
                padding: 8px 14px;
                font-size: 11px;
                font-weight: 500;
                color: #fff;
                cursor: pointer;
            }
            .figma-pac-manage-all:hover { background: rgba(255, 255, 255, 0.06); }
            .figma-pac-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }
            @media (min-width: 720px) {
                .figma-pac-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (min-width: 1100px) {
                .figma-pac-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            }
            .figma-pac-card {
                display: flex;
                flex-direction: column;
                min-width: 0;
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.12);
                background: rgba(18, 18, 18, 0.92);
                padding: 14px;
            }
            .figma-pac-card--geo { border-color: rgba(52, 199, 89, 0.35); }
            .figma-pac-card--block-geo { border-color: rgba(255, 120, 70, 0.4); }
            .figma-pac-card--allow-ip { border-color: rgba(64, 156, 255, 0.4); }
            .figma-pac-card--block-ip { border-color: rgba(255, 80, 80, 0.4); }
            .figma-pac-card-top { display: flex; gap: 10px; margin-bottom: 12px; }
            .figma-pac-card-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                flex-shrink: 0;
                border-radius: 9px;
            }
            .figma-pac-card-icon svg { width: 18px; height: 18px; }
            .figma-pac-card--geo .figma-pac-card-icon { background: rgba(52, 199, 89, 0.15); color: #34c759; }
            .figma-pac-card--block-geo .figma-pac-card-icon { background: rgba(255, 120, 70, 0.15); color: #ff7846; }
            .figma-pac-card--allow-ip .figma-pac-card-icon { background: rgba(64, 156, 255, 0.15); color: #409cff; }
            .figma-pac-card--block-ip .figma-pac-card-icon { background: rgba(255, 80, 80, 0.15); color: #ff5050; }
            .figma-pac-card-heading {
                flex: 1;
                min-width: 0;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 8px;
            }
            .figma-pac-card-title {
                margin: 0;
                font-size: 13px;
                font-weight: 600;
                color: #fff;
                line-height: 1.3;
            }
            .figma-pac-badge {
                flex-shrink: 0;
                border-radius: 999px;
                padding: 3px 8px;
                font-size: 9px;
                font-weight: 600;
                letter-spacing: 0.02em;
            }
            .figma-pac-badge.is-active {
                background: rgba(52, 199, 89, 0.18);
                color: #34c759;
            }
            .figma-pac-badge.is-off {
                background: rgba(255, 255, 255, 0.08);
                color: rgba(255, 255, 255, 0.45);
            }
            .figma-pac-card-body { flex: 1; min-height: 0; }
            .figma-pac-list-label {
                margin: 0 0 8px;
                font-size: 11px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.75);
            }
            .figma-pac-list {
                margin: 0;
                padding: 0;
                list-style: none;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .figma-pac-list li {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.88);
            }
            .figma-pac-list code {
                font-size: 11px;
                color: rgba(255, 255, 255, 0.88);
            }
            .figma-pac-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                flex-shrink: 0;
            }
            .figma-pac-dot--geo { background: #34c759; }
            .figma-pac-dot--block-geo { background: #ff7846; }
            .figma-pac-dot--allow-ip { background: #409cff; }
            .figma-pac-dot--block-ip { background: #ff5050; }
            .figma-pac-more {
                margin: 6px 0 0;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.45);
            }
            .figma-pac-empty {
                margin: 0;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.4);
            }
            .figma-pac-purpose {
                margin: 12px 0 0;
                font-size: 11px;
                line-height: 1.45;
                color: rgba(255, 255, 255, 0.5);
            }
            .figma-pac-purpose span {
                display: block;
                margin-bottom: 2px;
                font-size: 10px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.65);
            }
            .figma-pac-card-btn {
                margin-top: 14px;
                width: 100%;
                border-radius: 8px;
                border: 1px solid;
                background: transparent;
                padding: 8px 10px;
                font-size: 11px;
                font-weight: 600;
                cursor: pointer;
            }
            .figma-pac-card--geo .figma-pac-card-btn { border-color: rgba(52, 199, 89, 0.55); color: #34c759; }
            .figma-pac-card--block-geo .figma-pac-card-btn { border-color: rgba(255, 120, 70, 0.55); color: #ff7846; }
            .figma-pac-card--allow-ip .figma-pac-card-btn { border-color: rgba(64, 156, 255, 0.55); color: #409cff; }
            .figma-pac-card--block-ip .figma-pac-card-btn { border-color: rgba(255, 80, 80, 0.55); color: #ff5050; }
            .figma-pac-card-btn:hover { background: rgba(255, 255, 255, 0.04); }

            html.light-mode .figma-pac-title { color: #2d2d3a; }
            html.light-mode .figma-pac-lead { color: #5c5470; }
            html.light-mode .figma-pac-manage-all {
                border-color: #c9bdd9;
                color: #2d2d3a;
            }
            html.light-mode .figma-pac-card {
                background: #fff;
                border-color: #e4dceb;
            }
            html.light-mode .figma-pac-card--geo { border-color: rgba(46, 160, 67, 0.45); }
            html.light-mode .figma-pac-card--block-geo { border-color: rgba(220, 100, 50, 0.45); }
            html.light-mode .figma-pac-card--allow-ip { border-color: rgba(40, 120, 220, 0.45); }
            html.light-mode .figma-pac-card--block-ip { border-color: rgba(200, 60, 60, 0.45); }
            html.light-mode .figma-pac-card-title,
            html.light-mode .figma-pac-list li,
            html.light-mode .figma-pac-list code { color: #2d2d3a; }
            html.light-mode .figma-pac-list-label { color: #4a4458; }
            html.light-mode .figma-pac-more,
            html.light-mode .figma-pac-empty,
            html.light-mode .figma-pac-purpose { color: #6b6578; }
            html.light-mode .figma-pac-purpose span { color: #4a4458; }
            html.light-mode .figma-pac-badge.is-off {
                background: #f0ecf5;
                color: #7a7388;
            }

            /* Detection Engine Modules */
            .figma-dem { margin-bottom: 22px; }
            .figma-dem-head {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 14px;
            }
            .figma-dem-title { margin: 0 0 4px; font-size: 18px; font-weight: 600; color: #fff; }
            .figma-dem-lead { margin: 0; font-size: 12px; color: rgba(255,255,255,.55); }
            .figma-dem-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }
            @media (min-width: 720px) { .figma-dem-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (min-width: 1100px) { .figma-dem-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            @media (min-width: 1400px) { .figma-dem-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
            .figma-dem-card {
                min-width: 0;
                border-radius: 12px;
                border: 1px solid rgba(255,255,255,.12);
                background: rgba(18,18,18,.92);
                padding: 14px;
            }
            .figma-dem-card-top { display: flex; gap: 10px; margin-bottom: 12px; }
            .figma-dem-card-icon {
                width: 36px; height: 36px; flex-shrink: 0;
                display: flex; align-items: center; justify-content: center;
                border-radius: 9px; background: #6400B2; color: #fff;
            }
            .figma-dem-card-icon svg { width: 18px; height: 18px; }
            .figma-dem-card-heading { flex: 1; min-width: 0; }
            .figma-dem-card-title { margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #fff; line-height: 1.25; }
            .figma-dem-enabled {
                display: inline-flex; align-items: center; gap: 5px;
                border-radius: 999px; padding: 3px 8px; font-size: 9px; font-weight: 600;
            }
            .figma-dem-enabled.is-on { background: rgba(52,199,89,.18); color: #34c759; }
            .figma-dem-enabled.is-on::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #34c759; }
            .figma-dem-enabled.is-off { background: rgba(255,255,255,.08); color: rgba(255,255,255,.45); }
            .figma-dem-card-meta { display: flex; flex-direction: column; gap: 8px; }
            .figma-dem-meta-row {
                display: flex; align-items: center; justify-content: space-between; gap: 8px;
                font-size: 11px; color: rgba(255,255,255,.5);
            }
            .figma-dem-meta-row strong { color: #fff; font-weight: 600; font-size: 12px; }
            .figma-dem-action-select {
                max-width: 110px; height: 26px; border-radius: 6px;
                border: 1px solid rgba(255,255,255,.2); background: #101010;
                color: #fff; font-size: 11px; padding: 0 6px;
            }
            .figma-dem-risk--high { color: #ff5050 !important; }
            .figma-dem-risk--medium { color: #ffb020 !important; }
            .figma-dem-risk--low { color: #34c759 !important; }

            /* Block IP + Google Exclusion row */
            .figma-bip-gaem {
                display: grid;
                grid-template-columns: 1fr;
                gap: 14px;
                margin-bottom: 18px;
            }
            @media (min-width: 1100px) {
                .figma-bip-gaem { grid-template-columns: 1.15fr 0.85fr; }
            }
            .figma-bip, .figma-gaem {
                min-width: 0;
                border-radius: 12px;
                border: 1px solid rgba(255,255,255,.12);
                background: rgba(18,18,18,.92);
                padding: 16px;
            }
            .figma-bip-head, .figma-gaem-head {
                display: flex; flex-wrap: wrap; align-items: flex-start;
                justify-content: space-between; gap: 10px; margin-bottom: 14px;
            }
            .figma-bip-title, .figma-gaem-title { margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #fff; }
            .figma-bip-lead, .figma-gaem-lead { margin: 0; font-size: 11px; color: rgba(255,255,255,.5); }
            .figma-bip-head-actions, .figma-gaem-head-actions { display: flex; align-items: center; gap: 8px; }
            .figma-bip-upload, .figma-gaem-bulk {
                border-radius: 8px; border: 1px solid rgba(255,255,255,.3);
                background: transparent; padding: 7px 12px; font-size: 11px; color: #fff; cursor: pointer;
            }
            .figma-bip-upload:hover, .figma-gaem-bulk:hover { background: rgba(255,255,255,.06); }
            .figma-bip-add {
                display: grid;
                grid-template-columns: 1fr;
                gap: 8px;
                margin-bottom: 12px;
            }
            @media (min-width: 900px) {
                .figma-bip-add { grid-template-columns: 1.4fr 0.7fr 1fr auto; align-items: end; }
            }
            .figma-bip-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
            .figma-bip-field span { font-size: 10px; color: rgba(255,255,255,.5); }
            .figma-bip-field input, .figma-bip-field select {
                height: 34px; border-radius: 8px; border: 1px solid rgba(255,255,255,.18);
                background: #101010; color: #fff; padding: 0 10px; font-size: 11px;
            }
            .figma-bip-add-btn, .figma-gaem-push-btn {
                height: 34px; border-radius: 8px; border: 0; background: #6400B2;
                color: #fff; font-size: 11px; font-weight: 600; padding: 0 14px; cursor: pointer;
            }
            .figma-gaem-ghost-btn {
                height: 34px; border-radius: 8px; border: 1px solid rgba(255,255,255,.3);
                background: transparent; color: #fff; font-size: 11px; padding: 0 12px; cursor: pointer;
            }
            .figma-bip-table-wrap, .figma-gaem-table-wrap { overflow-x: auto; border-radius: 8px; border: 1px solid rgba(255,255,255,.1); }
            .figma-bip-table, .figma-gaem-table { width: 100%; border-collapse: collapse; font-size: 11px; color: rgba(255,255,255,.85); }
            .figma-bip-table th, .figma-gaem-table th {
                text-align: left; font-weight: 500; color: rgba(255,255,255,.5);
                padding: 8px 10px; background: #101010; white-space: nowrap;
            }
            .figma-bip-table td, .figma-gaem-table td {
                padding: 9px 10px; border-top: 1px solid rgba(255,255,255,.08); vertical-align: middle;
            }
            .figma-bip-empty { color: rgba(255,255,255,.4) !important; }
            .figma-bip-risk {
                display: inline-block; border-radius: 999px; padding: 2px 7px;
                background: rgba(255,176,32,.15); color: #ffb020; font-size: 10px; font-weight: 600;
            }
            .figma-bip-view-all {
                margin-top: 10px; background: none; border: 0; color: #c084fc;
                font-size: 12px; font-weight: 600; cursor: pointer; padding: 0;
            }
            .figma-gaem-quick { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
            .figma-gaem-ip-input {
                flex: 1 1 160px; height: 34px; border-radius: 8px;
                border: 1px solid rgba(255,255,255,.18); background: #101010;
                color: #fff; padding: 0 10px; font-size: 11px;
            }
            .figma-gaem-bulk-box { margin-bottom: 12px; }
            .figma-gaem-bulk-hint { margin: 0 0 6px; font-size: 10px; color: rgba(255,255,255,.45); }
            .figma-gaem-status {
                display: inline-block; border-radius: 999px; padding: 3px 8px;
                font-size: 10px; font-weight: 600;
            }
            .figma-gaem-status.is-pending { background: rgba(255,176,32,.18); color: #ffb020; }
            .figma-gaem-status.is-sent, .figma-gaem-status.is-applied { background: rgba(52,199,89,.18); color: #34c759; }
            .figma-gaem-status.is-failed { background: rgba(255,80,80,.18); color: #ff7070; }
            .figma-gaem-status.is-off { background: rgba(255,255,255,.08); color: rgba(255,255,255,.45); }
            .figma-gaem-actions-cell { display: flex; align-items: center; gap: 8px; }
            .figma-gaem-row-btn {
                border-radius: 6px; border: 1px solid rgba(255,255,255,.25);
                background: transparent; color: #fff; font-size: 10px; padding: 5px 10px; cursor: pointer;
            }
            .figma-gaem-row-btn--primary { background: #6400B2; border-color: #6400B2; }

            html.light-mode .figma-dem-title,
            html.light-mode .figma-bip-title,
            html.light-mode .figma-gaem-title,
            html.light-mode .figma-dem-card-title,
            html.light-mode .figma-dem-meta-row strong { color: #2d2d3a; }
            html.light-mode .figma-dem-lead,
            html.light-mode .figma-bip-lead,
            html.light-mode .figma-gaem-lead,
            html.light-mode .figma-dem-meta-row { color: #6b6578; }
            html.light-mode .figma-dem-card,
            html.light-mode .figma-bip,
            html.light-mode .figma-gaem { background: #fff; border-color: #e4dceb; }
            html.light-mode .figma-bip-field input,
            html.light-mode .figma-bip-field select,
            html.light-mode .figma-gaem-ip-input,
            html.light-mode .figma-dem-action-select {
                background: #f7f5fa; color: #2d2d3a; border-color: #d4c4e8;
            }
            html.light-mode .figma-bip-table th,
            html.light-mode .figma-gaem-table th { background: #f7f5fa; color: #6b6578; }
            html.light-mode .figma-bip-table td,
            html.light-mode .figma-gaem-table td { color: #2d2d3a; border-color: #ece7f2; }
            html.light-mode .figma-bip-upload,
            html.light-mode .figma-gaem-bulk,
            html.light-mode .figma-gaem-ghost-btn,
            html.light-mode .figma-gaem-row-btn { color: #2d2d3a; border-color: #c9bdd9; }

            /* Advanced Detection Settings */
            .figma-ads { margin-top: 18px; margin-bottom: 18px; }
            .figma-ads-head { margin-bottom: 14px; }
            .figma-ads-title { margin: 0 0 4px; font-size: 18px; font-weight: 600; color: #fff; }
            .figma-ads-lead { margin: 0; font-size: 12px; color: rgba(255,255,255,.55); }
            .figma-ads-card {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0;
                border-radius: 12px;
                border: 1px solid rgba(255,255,255,.12);
                background: rgba(18,18,18,.92);
                margin-bottom: 18px;
                overflow: hidden;
            }
            @media (min-width: 1100px) {
                .figma-ads-card { grid-template-columns: 1.1fr 1.2fr 1fr; }
                .figma-ads-col + .figma-ads-col { border-left: 1px solid rgba(255,255,255,.1); }
            }
            .figma-ads-col { padding: 16px; min-width: 0; }
            .figma-ads-col-title {
                margin: 0 0 12px;
                font-size: 12px;
                font-weight: 600;
                color: rgba(255,255,255,.88);
            }
            .figma-ads-pills, .figma-ads-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .figma-ads-pill {
                border-radius: 8px;
                border: 1px solid rgba(255,255,255,.22);
                background: transparent;
                color: #fff;
                font-size: 11px;
                font-weight: 500;
                padding: 7px 12px;
                cursor: pointer;
            }
            .figma-ads-pill.is-active {
                background: #6400B2;
                border-color: #6400B2;
            }
            .figma-ads-limits {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }
            @media (min-width: 700px) {
                .figma-ads-limits { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            }
            .figma-ads-limits label { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
            .figma-ads-limits span { font-size: 10px; color: rgba(255,255,255,.5); }
            .figma-ads-limits select,
            .figma-ads-custom input {
                height: 32px;
                border-radius: 8px;
                border: 1px solid rgba(255,255,255,.18);
                background: #101010;
                color: #fff;
                font-size: 11px;
                padding: 0 8px;
            }
            .figma-ads-custom { display: flex; flex-direction: column; gap: 4px; margin-top: 10px; }
            .figma-ads-custom span { font-size: 10px; color: rgba(255,255,255,.5); }
            .figma-ads-action {
                border-radius: 8px;
                border: 1px solid;
                background: transparent;
                font-size: 11px;
                font-weight: 600;
                padding: 7px 12px;
                cursor: pointer;
            }
            .figma-ads-action--allow { border-color: #34c759; color: #34c759; }
            .figma-ads-action--monitor { border-color: #ffb020; color: #ffb020; }
            .figma-ads-action--challenge { border-color: #e8c547; color: #e8c547; }
            .figma-ads-action--redirect { border-color: #409cff; color: #409cff; }
            .figma-ads-action--block { border-color: #ff5050; color: #ff5050; }
            .figma-ads-action.is-selected { background: rgba(255,255,255,.06); box-shadow: inset 0 0 0 1px currentColor; }
            .figma-ads-more { padding-top: 4px; }

            html.light-mode .figma-ads-title { color: #2d2d3a; }
            html.light-mode .figma-ads-lead,
            html.light-mode .figma-ads-limits span,
            html.light-mode .figma-ads-custom span { color: #6b6578; }
            html.light-mode .figma-ads-col-title { color: #2d2d3a; }
            html.light-mode .figma-ads-card { background: #fff; border-color: #e4dceb; }
            html.light-mode .figma-ads-col + .figma-ads-col { border-left-color: #ece7f2; }
            html.light-mode .figma-ads-pill { color: #2d2d3a; border-color: #c9bdd9; }
            html.light-mode .figma-ads-pill.is-active { color: #fff; }
            html.light-mode .figma-ads-limits select,
            html.light-mode .figma-ads-custom input {
                background: #f7f5fa; color: #2d2d3a; border-color: #d4c4e8;
            }

            .figma-rule-editor {
                border-radius: 12px;
                border: 1px solid rgba(255,255,255,.12);
                background: rgba(18,18,18,.92);
                padding: 14px;
            }
            .figma-rule-editor-title {
                margin: 0 0 10px;
                font-size: 13px;
                font-weight: 600;
                color: #fff;
            }
            html.light-mode .figma-rule-editor {
                background: #fff;
                border-color: #e4dceb;
            }
            html.light-mode .figma-rule-editor-title { color: #2d2d3a; }
        </style>

        <div class="mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Detection</span>
            </div>

            @if ($domains->isNotEmpty())
                <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--detection ov-filter-bar ml-auto flex min-h-[54px] w-fit max-w-full flex-nowrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_2px_10px_rgba(0,0,0,.35)]">
                    <label class="flex w-[140px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                        <div class="figma-filter-select-wrap">
                            <select x-model="filters.domainId" @change="applyFilters()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                                @foreach ($domains as $d)
                                    <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                                @endforeach
                            </select>
                        </div>
                    </label>
                    <label class="flex w-[118px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                        <div class="figma-filter-select-wrap">
                            <select x-model="filters.trafficSource" @change="applyFilters()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                                <option value="google_ads">Google Ads</option>
                                <option value="meta_ads" disabled>Meta Ads</option>
                                <option value="microsoft_ads" disabled>Microsoft Ads</option>
                            </select>
                        </div>
                    </label>
                    <label class="flex w-[150px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Google Ads Account</span>
                        <div class="figma-filter-select-wrap">
                            <select x-model="filters.googleAdsAccountId" @change="applyFilters()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                                <option value="">All Accounts</option>
                                @foreach (($googleAdsAccounts ?? []) as $account)
                                    <option value="{{ $account->id }}">{{ $account->displayLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </label>
                    <label class="flex w-[130px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                        <div class="figma-filter-select-wrap">
                            <select x-model="filters.campaign" @change="applyFilters()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                                <option value="">All Campaigns</option>
                            </select>
                        </div>
                    </label>
                    <label class="flex w-[128px] shrink-0 flex-col justify-center border-r border-black/20 px-[8px] py-[6px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                        <div class="figma-filter-path-wrap">
                            <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input x-model="filters.path" @keydown.enter.prevent="applyFilters()" @change="applyFilters()" placeholder="All Pages" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                        </div>
                    </label>
                    @include('partials.figma-filter-date-fields')
                </div>
            @endif
        </div>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[#6400B2]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        @if ($domains->isEmpty())
            <div class="rounded-[10px] border border-[#6400B2] p-[28px] text-center text-[#a9a9a9]">No domain found. Add a domain first.</div>
        @else
            @if ($domain)
                <p class="mb-[14px] text-[12px] text-white/45">Editing settings for <span class="font-semibold text-white/80">#{{ $domain->id }} · {{ $domain->hostname }}</span></p>
            @endif

            @if ($domain && $settings)
            @php
                $geoAudienceRules = $settings->out_of_geo_audience['rules'] ?? null;
                $googleGeoBlockRules = $settings->google_geo_block_audience['rules'] ?? [];
                $exclusionRules = array_merge(
                    (new \App\Services\GoogleAudienceExclusionService())->defaultRules(),
                    is_array($settings->google_exclusion_rules) ? $settings->google_exclusion_rules : []
                );
                if (! is_array($geoAudienceRules) || $geoAudienceRules === []) {
                    $geoAudienceRules = collect($settings->out_of_geo_countries ?? [])
                        ->map(fn ($c) => ['country' => $c, 'state' => null, 'city' => null])
                        ->values()
                        ->all();
                }
                if (! is_array($googleGeoBlockRules)) {
                    $googleGeoBlockRules = [];
                }

                $parseIpPreviewLines = static function (?string $raw): array {
                    return collect(preg_split('/\r\n|\r|\n/', (string) $raw) ?: [])
                        ->map(function ($line) {
                            $line = trim((string) $line);
                            if ($line === '' || str_starts_with($line, '#')) {
                                return null;
                            }
                            $ip = trim(explode('|', $line, 2)[0] ?? '');

                            return $ip !== '' ? $ip : null;
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                };

                $allowedCountriesPreview = collect($geoAudienceRules ?? [])
                    ->pluck('country')
                    ->map(fn ($c) => trim((string) $c))
                    ->filter()
                    ->unique()
                    ->values();
                $blockedCountriesPreview = collect($googleGeoBlockRules ?? [])
                    ->pluck('country')
                    ->map(fn ($c) => trim((string) $c))
                    ->filter()
                    ->unique()
                    ->values();
                $allowIpsPreview = collect($parseIpPreviewLines($settings->allow_list_ips ?? ''));
                $blockIpsPreview = collect($parseIpPreviewLines($settings->block_list_ips ?? ''));

                $parseBlockIpRows = static function (?string $raw): array {
                    $rows = [];
                    foreach (preg_split('/\r\n|\r|\n/', (string) $raw) ?: [] as $line) {
                        $line = trim((string) $line);
                        if ($line === '') {
                            continue;
                        }
                        $active = true;
                        if (str_starts_with($line, '#')) {
                            $active = false;
                            $line = trim(ltrim($line, '#'));
                        }
                        if ($line === '' || str_starts_with($line, '#')) {
                            continue;
                        }
                        $parts = array_map('trim', explode('|', $line));
                        $ip = $parts[0] ?? '';
                        if ($ip === '') {
                            continue;
                        }
                        $duration = $parts[1] ?? 'permanent';
                        $reason = $parts[2] ?? '';
                        $rows[] = [
                            'ip' => $ip,
                            'duration' => $duration !== '' ? $duration : 'permanent',
                            'reason' => $reason,
                            'source' => 'Manual',
                            'risk' => null,
                            'added_by' => 'Admin',
                            'added_on' => null,
                            'active' => $active,
                            'raw' => $line,
                        ];
                    }

                    return $rows;
                };
                $blockIpRows = $parseBlockIpRows($settings->block_list_ips ?? '');

                $actionLabel = static fn (string $action): string => match ($action) {
                    'block' => 'Challenge',
                    'flag' => 'Monitor',
                    default => 'Allow',
                };
                $riskLabel = static fn (string $action): string => match ($action) {
                    'block' => 'High',
                    'flag' => 'Medium',
                    default => 'Low',
                };
                $detectionModules = [
                    [
                        'key' => 'vpn',
                        'title' => 'VPN Detection',
                        'field' => 'suspicious_vpn',
                        'action' => $matrix['vpn'] ?? 'allow',
                        'found' => 0,
                        'enabled' => (bool) $settings->suspicious_enabled && (($matrix['vpn'] ?? 'allow') !== 'allow'),
                        'icon' => 'vpn',
                    ],
                    [
                        'key' => 'proxy',
                        'title' => 'Proxy Detection',
                        'field' => 'suspicious_proxy',
                        'action' => $matrix['proxy'] ?? 'block',
                        'found' => 0,
                        'enabled' => (bool) $settings->suspicious_enabled && (($matrix['proxy'] ?? 'block') !== 'allow'),
                        'icon' => 'proxy',
                    ],
                    [
                        'key' => 'data_center',
                        'title' => 'Datacenter Detection',
                        'field' => 'suspicious_data_center',
                        'action' => $matrix['data_center'] ?? 'block',
                        'found' => 0,
                        'enabled' => (bool) $settings->suspicious_enabled && (($matrix['data_center'] ?? 'block') !== 'allow'),
                        'icon' => 'dc',
                    ],
                    [
                        'key' => 'abnormal_rate_limit',
                        'title' => 'Abnormal Rate Detection',
                        'field' => 'suspicious_abnormal_rate_limit',
                        'action' => $matrix['abnormal_rate_limit'] ?? 'block',
                        'found' => 0,
                        'enabled' => (bool) $settings->suspicious_enabled && (($matrix['abnormal_rate_limit'] ?? 'block') !== 'allow'),
                        'icon' => 'rate',
                    ],
                    [
                        'key' => 'repeated_click',
                        'title' => 'Repeated Click Detection',
                        'field' => null,
                        'action' => $settings->frequency_capping ? 'block' : 'allow',
                        'found' => 0,
                        'enabled' => (bool) $settings->frequency_capping,
                        'icon' => 'repeat',
                        'toggle' => 'frequency_capping',
                    ],
                    [
                        'key' => 'suspicious_behavior',
                        'title' => 'Suspicious Behavior Detection',
                        'field' => 'invalid_malicious_action',
                        'action' => $settings->invalid_malicious_action ?? 'allow',
                        'found' => 0,
                        'enabled' => ($settings->invalid_malicious_action ?? 'allow') !== 'allow',
                        'icon' => 'behavior',
                    ],
                ];
            @endphp
                <form method="POST" action="{{ route('paid-marketing.detection-settings.update', $domain) }}">
                    @csrf
                    <input type="hidden" name="control_mode" value="{{ old('control_mode', $settings->control_mode ?? 'mixed') }}">

                    <section class="figma-pac" aria-labelledby="figma-pac-heading">
                        <div class="figma-pac-head">
                            <div class="figma-pac-head-text">
                                <h2 id="figma-pac-heading" class="figma-pac-title">Primary Access Control</h2>
                                <p class="figma-pac-lead">Control who can access your website and from where.</p>
                            </div>
                            <button type="button" class="figma-pac-manage-all" onclick="document.getElementById('detection-panel-geo-allow')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                                Manage All Rules
                            </button>
                        </div>

                        <div class="figma-pac-grid">
                            <article class="figma-pac-card figma-pac-card--geo">
                                <div class="figma-pac-card-top">
                                    <div class="figma-pac-card-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.8 3.8 5.8 3.8 9s-1.3 6.2-3.8 9c-2.5-2.8-3.8-5.8-3.8-9s1.3-6.2 3.8-9z"/></svg>
                                    </div>
                                    <div class="figma-pac-card-heading">
                                        <h3 class="figma-pac-card-title">Geo Targeting Rules</h3>
                                        <x-figma-toggle name="out_of_geo_enabled" value="1" :checked="$settings->out_of_geo_enabled" size="sm" label-on="On" label-off="Off" />
                                    </div>
                                </div>
                                <div class="figma-pac-card-body">
                                    <p class="figma-pac-list-label">Allowed Countries ({{ $allowedCountriesPreview->count() }})</p>
                                    @if ($allowedCountriesPreview->isEmpty())
                                        <p class="figma-pac-empty">No countries configured yet.</p>
                                    @else
                                        <ul class="figma-pac-list">
                                            @foreach ($allowedCountriesPreview->take(3) as $country)
                                                <li><span class="figma-pac-dot figma-pac-dot--geo"></span>{{ $country }}</li>
                                            @endforeach
                                        </ul>
                                        @if ($allowedCountriesPreview->count() > 3)
                                            <p class="figma-pac-more">+{{ $allowedCountriesPreview->count() - 3 }} more</p>
                                        @endif
                                    @endif
                                    <p class="figma-pac-purpose"><span>Purpose</span> Allow legitimate advertising audience from approved countries.</p>
                                </div>
                                <button type="button" class="figma-pac-card-btn" onclick="document.getElementById('detection-panel-geo-allow')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">Manage Countries</button>
                            </article>

                            <article class="figma-pac-card figma-pac-card--block-geo">
                                <div class="figma-pac-card-top">
                                    <div class="figma-pac-card-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l7 3v5c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V6l7-3z"/><path d="M9.5 12.5l1.8 1.8 3.7-3.8"/></svg>
                                    </div>
                                    <div class="figma-pac-card-heading">
                                        <h3 class="figma-pac-card-title">Blocked Countries</h3>
                                        <x-figma-toggle name="google_geo_block_enabled" value="1" :checked="$settings->google_geo_block_enabled" size="sm" label-on="On" label-off="Off" />
                                    </div>
                                </div>
                                <div class="figma-pac-card-body">
                                    <p class="figma-pac-list-label">Blocked Countries ({{ $blockedCountriesPreview->count() }})</p>
                                    @if ($blockedCountriesPreview->isEmpty())
                                        <p class="figma-pac-empty">No blocked countries yet.</p>
                                    @else
                                        <ul class="figma-pac-list">
                                            @foreach ($blockedCountriesPreview->take(3) as $country)
                                                <li><span class="figma-pac-dot figma-pac-dot--block-geo"></span>{{ $country }}</li>
                                            @endforeach
                                        </ul>
                                        @if ($blockedCountriesPreview->count() > 3)
                                            <p class="figma-pac-more">+{{ $blockedCountriesPreview->count() - 3 }} more</p>
                                        @endif
                                    @endif
                                    <p class="figma-pac-purpose"><span>Reason</span> Traffic quality protection and high invalid activity.</p>
                                </div>
                                <button type="button" class="figma-pac-card-btn" onclick="document.getElementById('detection-panel-geo-block')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">Manage Blocked Countries</button>
                            </article>

                            <article class="figma-pac-card figma-pac-card--allow-ip">
                                <div class="figma-pac-card-top">
                                    <div class="figma-pac-card-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l7 3v5c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V6l7-3z"/><path d="M9.5 12.5l1.8 1.8 3.7-3.8"/></svg>
                                    </div>
                                    <div class="figma-pac-card-heading">
                                        <h3 class="figma-pac-card-title">Whitelist IP Addresses</h3>
                                        <x-figma-toggle name="allow_list_enabled" value="1" :checked="$settings->allow_list_enabled" size="sm" label-on="On" label-off="Off" />
                                    </div>
                                </div>
                                <div class="figma-pac-card-body">
                                    <p class="figma-pac-list-label">Whitelisted IPs ({{ $allowIpsPreview->count() }})</p>
                                    @if ($allowIpsPreview->isEmpty())
                                        <p class="figma-pac-empty">No whitelist IPs yet.</p>
                                    @else
                                        <ul class="figma-pac-list">
                                            @foreach ($allowIpsPreview->take(3) as $ip)
                                                <li><span class="figma-pac-dot figma-pac-dot--allow-ip"></span><code>{{ $ip }}</code></li>
                                            @endforeach
                                        </ul>
                                        @if ($allowIpsPreview->count() > 3)
                                            <p class="figma-pac-more">+{{ $allowIpsPreview->count() - 3 }} more</p>
                                        @endif
                                    @endif
                                    <p class="figma-pac-purpose"><span>Purpose</span> Protect internal, testing, and trusted user traffic.</p>
                                </div>
                                <button type="button" class="figma-pac-card-btn" onclick="document.getElementById('detection-panel-ip-allow')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">Manage Whitelist IPs</button>
                            </article>

                            <article class="figma-pac-card figma-pac-card--block-ip">
                                <div class="figma-pac-card-top">
                                    <div class="figma-pac-card-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c1.6-2.6 3.8-4 6.5-4s4.9 1.4 6.5 4"/><path d="M16.5 6.5l4 4M20.5 6.5l-4 4"/></svg>
                                    </div>
                                    <div class="figma-pac-card-heading">
                                        <h3 class="figma-pac-card-title">Blacklist IP Addresses</h3>
                                        <x-figma-toggle name="block_list_enabled" value="1" :checked="$settings->block_list_enabled" size="sm" label-on="On" label-off="Off" />
                                    </div>
                                </div>
                                <div class="figma-pac-card-body">
                                    <p class="figma-pac-list-label">Blocked IPs ({{ $blockIpsPreview->count() }})</p>
                                    @if ($blockIpsPreview->isEmpty())
                                        <p class="figma-pac-empty">No blacklist IPs yet.</p>
                                    @else
                                        <ul class="figma-pac-list">
                                            @foreach ($blockIpsPreview->take(3) as $ip)
                                                <li><span class="figma-pac-dot figma-pac-dot--block-ip"></span><code>{{ $ip }}</code></li>
                                            @endforeach
                                        </ul>
                                        @if ($blockIpsPreview->count() > 3)
                                            <p class="figma-pac-more">+{{ $blockIpsPreview->count() - 3 }} more</p>
                                        @endif
                                    @endif
                                    <p class="figma-pac-purpose"><span>Reason</span> Repeated invalid activity, suspicious behavior detected.</p>
                                </div>
                                <button type="button" class="figma-pac-card-btn" onclick="document.getElementById('detection-panel-ip-block')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">Manage Blacklist IPs</button>
                            </article>
                        </div>
                    </section>

                    {{-- Detection Engine Modules (old Suspicious Activity matrix) --}}
                    <section class="figma-dem" aria-labelledby="figma-dem-heading">
                        <div class="figma-dem-head">
                            <div>
                                <h2 id="figma-dem-heading" class="figma-dem-title">Detection Engine Modules</h2>
                                <p class="figma-dem-lead">All modules are active and running in real-time.</p>
                            </div>
                            <x-figma-toggle
                                name="suspicious_enabled"
                                value="1"
                                :checked="$settings->suspicious_enabled"
                                size="sm"
                                label-on="On"
                                label-off="Off"
                            />
                        </div>
                        <div class="figma-dem-grid">
                            @foreach ($detectionModules as $mod)
                                @php
                                    $modAction = old($mod['field'] ?? '', $mod['action']);
                                    if (($mod['key'] ?? '') === 'repeated_click') {
                                        $modAction = old('frequency_capping', $settings->frequency_capping) ? 'block' : 'allow';
                                    }
                                    $modRisk = $riskLabel($modAction);
                                @endphp
                                <article class="figma-dem-card">
                                    <div class="figma-dem-card-top">
                                        <div class="figma-dem-card-icon" aria-hidden="true">
                                            @if (($mod['icon'] ?? '') === 'vpn')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.2 2.5 3.4 5.4 3.4 9S14.2 18.5 12 21c-2.2-2.5-3.4-5.4-3.4-9S9.8 5.5 12 3z"/></svg>
                                            @elseif (($mod['icon'] ?? '') === 'proxy')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-4.5 7-11a7 7 0 10-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                            @elseif (($mod['icon'] ?? '') === 'dc')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="16" height="7" rx="1.5"/><rect x="4" y="14" width="16" height="7" rx="1.5"/><path d="M8 6.5h.01M8 17.5h.01M12 6.5h4M12 17.5h4"/></svg>
                                            @elseif (($mod['icon'] ?? '') === 'rate')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h16"/><path d="M8 15l3-4 3 2 4-6"/></svg>
                                            @elseif (($mod['icon'] ?? '') === 'repeat')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L2.6 17.2A2 2 0 004.3 20h15.4a2 2 0 001.7-2.8L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
                                            @endif
                                        </div>
                                        <div class="figma-dem-card-heading">
                                            <h3 class="figma-dem-card-title">{{ $mod['title'] }}</h3>
                                            @if (!empty($mod['toggle']))
                                                <x-figma-toggle :name="$mod['toggle']" value="1" :checked="$settings->{$mod['toggle']}" size="sm" label-on="On" label-off="Off" />
                                            @elseif (($mod['key'] ?? '') === 'suspicious_behavior')
                                                <span class="figma-dem-enabled {{ $modAction !== 'allow' ? 'is-on' : 'is-off' }}">{{ $modAction !== 'allow' ? 'Enabled' : 'Off' }}</span>
                                            @else
                                                <span class="figma-dem-enabled {{ ($settings->suspicious_enabled && $modAction !== 'allow') ? 'is-on' : 'is-off' }}">{{ ($settings->suspicious_enabled && $modAction !== 'allow') ? 'Enabled' : 'Off' }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="figma-dem-card-meta">
                                        <div class="figma-dem-meta-row">
                                            <span>Found</span>
                                            <strong>{{ number_format((int) $mod['found']) }} Sessions</strong>
                                        </div>
                                        <div class="figma-dem-meta-row">
                                            <span>Action:</span>
                                            @if (!empty($mod['field']))
                                                <select name="{{ $mod['field'] }}" class="figma-dem-action-select" aria-label="{{ $mod['title'] }} action">
                                                    <option value="allow" @selected($modAction === 'allow')>Allow</option>
                                                    <option value="flag" @selected($modAction === 'flag')>Monitor</option>
                                                    <option value="block" @selected($modAction === 'block')>Challenge</option>
                                                </select>
                                            @else
                                                <strong>{{ $actionLabel($modAction) }}</strong>
                                            @endif
                                        </div>
                                        <div class="figma-dem-meta-row">
                                            <span>Risk:</span>
                                            <strong class="figma-dem-risk figma-dem-risk--{{ strtolower($modRisk) }}">{{ $modRisk }}</strong>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    {{-- Block IP + Google Ads Exclusion Manager --}}
                    <div class="figma-bip-gaem">
                        <section
                            id="detection-panel-ip-block"
                            class="figma-bip"
                            x-data="blockIpPanel(@js([
                                'initial' => $settings->block_list_ips ?? '',
                                'rows' => $blockIpRows,
                            ]))"
                            x-init="init()"
                        >
                            <div class="figma-bip-head">
                                <div>
                                    <h2 class="figma-bip-title">Block IP Addresses</h2>
                                    <p class="figma-bip-lead">Upload or add IPs to block from accessing your website.</p>
                                </div>
                                <div class="figma-bip-head-actions">
                                    <label class="figma-bip-upload">
                                        <input type="file" class="sr-only" accept=".txt,.csv,text/plain,text/csv" @change="onFile($event)">
                                        Upload File
                                    </label>
                                </div>
                            </div>

                            <div class="figma-bip-add">
                                <label class="figma-bip-field figma-bip-field--grow">
                                    <span>Add IP Address</span>
                                    <input type="text" x-model="draftIp" placeholder="Enter IP or CIDR (e.g., 192.168.1.1 or 192.168.0.0/24)" @keydown.enter.prevent="addRow()">
                                </label>
                                <label class="figma-bip-field">
                                    <span>Duration</span>
                                    <select x-model="draftDuration">
                                        <option value="2m">2 Minutes</option>
                                        <option value="1h">1 Hour</option>
                                        <option value="24h">24 Hours</option>
                                        <option value="7d">7 Days</option>
                                        <option value="permanent">Permanent</option>
                                    </select>
                                </label>
                                <label class="figma-bip-field figma-bip-field--grow">
                                    <span>Reason (Optional)</span>
                                    <input type="text" x-model="draftReason" placeholder="e.g., Repeated clicks" @keydown.enter.prevent="addRow()">
                                </label>
                                <button type="button" class="figma-bip-add-btn" @click="addRow()">Add IP</button>
                            </div>

                            <textarea id="block_list_ips" name="block_list_ips" class="sr-only" x-model="rawText"></textarea>

                            <div class="figma-bip-table-wrap">
                                <table class="figma-bip-table">
                                    <thead>
                                        <tr>
                                            <th>IP Address/Range</th>
                                            <th>Source</th>
                                            <th>Risk Score</th>
                                            <th>Duration</th>
                                            <th>Reason</th>
                                            <th>Added By</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-if="!rows.length">
                                            <tr><td colspan="7" class="figma-bip-empty">No blocked IPs yet. Add an IP above or upload a file.</td></tr>
                                        </template>
                                        <template x-for="(row, idx) in visibleRows" :key="row.ip + '-' + idx">
                                            <tr>
                                                <td class="font-mono" x-text="maskIp(row.ip)"></td>
                                                <td x-text="row.source || 'Manual'"></td>
                                                <td><span class="figma-bip-risk" x-text="row.risk ? (row.risk + '/100') : '—'"></span></td>
                                                <td x-text="formatDuration(row.duration)"></td>
                                                <td x-text="row.reason || '—'"></td>
                                                <td x-text="row.added_by || 'Admin'"></td>
                                                <td>
                                                    <label class="figma-toggle figma-toggle--sm" :title="row.active ? 'Active' : 'Off'">
                                                        <input type="checkbox" class="figma-toggle-input" :checked="row.active !== false" @change="toggleRow(idx, $event.target.checked)">
                                                        <span class="figma-toggle-track pointer-events-none" aria-hidden="true"><span class="figma-toggle-thumb"></span></span>
                                                        <span class="figma-toggle-label figma-toggle-label--on">On</span>
                                                        <span class="figma-toggle-label figma-toggle-label--off">Off</span>
                                                    </label>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="figma-bip-view-all" x-show="rows.length > 5" @click="showAll = !showAll" x-text="showAll ? 'Show less' : 'View All Blocked IPs →'"></button>
                        </section>

                        <section
                            class="figma-gaem"
                            x-data="googleExclusionPanel(@js([
                                'pushUrl' => route('paid-marketing.detection-settings.google-exclusion.push', $domain),
                                'pushRowUrl' => route('paid-marketing.detection-settings.google-exclusion.push-row', $domain),
                                'toggleRowUrl' => route('paid-marketing.detection-settings.google-exclusion.toggle-row', $domain),
                                'bulkUrl' => route('paid-marketing.detection-settings.google-exclusion.push-bulk', $domain),
                                'syncUrl' => route('paid-marketing.detection-settings.google-exclusion.sync', $domain),
                                'csrf' => csrf_token(),
                                'rows' => $ipExclusions,
                            ]))"
                        >
                            <div class="figma-gaem-head">
                                <div>
                                    <h2 class="figma-gaem-title">Google Ads Exclusion Manager</h2>
                                    <p class="figma-gaem-lead">Recommendations to protect your campaigns.</p>
                                </div>
                                <div class="figma-gaem-head-actions">
                                    <button type="button" class="figma-gaem-bulk" @click="showBulk = !showBulk" x-text="showBulk ? 'Hide Bulk' : 'Bulk Exclusion'"></button>
                                    <x-figma-toggle name="google_exclusion_enabled" value="1" :checked="$exclusionRules['enabled'] ?? true" size="sm" label-on="On" label-off="Off" />
                                </div>
                            </div>

                            <div class="figma-gaem-quick" x-show="!showBulk">
                                <input type="text" x-model="ip" placeholder="Add IP / CIDR / wildcard" class="figma-gaem-ip-input" @keydown.enter.prevent="pushIp()">
                                <button type="button" class="figma-gaem-push-btn" :disabled="loading || !ip.trim()" @click="pushIp()">Add</button>
                                <button type="button" class="figma-gaem-ghost-btn" :disabled="loading" @click="syncPending()">Push all pending</button>
                            </div>

                            <div class="figma-gaem-bulk-box" x-show="showBulk" x-cloak>
                                <p class="figma-gaem-bulk-hint">One per line. Supports IP, CIDR, wildcards. Max 200.</p>
                                <textarea x-model="bulkIps" rows="4" placeholder="216.67.176.*&#10;54.202.0.0/15" class="figma-textarea w-full text-[11px]"></textarea>
                                <div class="flex flex-wrap items-center gap-[8px] mt-[8px]">
                                    <label class="figma-bip-upload">
                                        <input type="file" class="sr-only" accept=".txt,.csv,text/plain,text/csv" @change="onBulkFile($event)">
                                        Choose file
                                    </label>
                                    <span class="text-[10px] text-white/50" x-text="bulkFileName || 'No file chosen'"></span>
                                    <button type="button" class="figma-gaem-push-btn" :disabled="loading || (!bulkIps.trim() && !bulkFileName)" @click="pushBulk()">Upload &amp; add</button>
                                </div>
                            </div>

                            <p x-show="message" x-text="message" class="text-[11px] mt-[8px]" :class="ok ? 'text-emerald-300' : 'text-rose-300'"></p>

                            <div class="figma-gaem-table-wrap">
                                <table class="figma-gaem-table">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-if="!rows.length">
                                            <tr><td colspan="4" class="figma-bip-empty">No exclusions queued yet.</td></tr>
                                        </template>
                                        <template x-for="row in rows.slice(0, showAllExclusions ? rows.length : 5)" :key="row.ip + row.updated_at">
                                            <tr>
                                                <td class="font-mono" x-text="row.ip"></td>
                                                <td class="capitalize" x-text="row.threat_group || 'Manual'"></td>
                                                <td>
                                                    <span
                                                        class="figma-gaem-status"
                                                        :class="{
                                                            'is-pending': row.sync_status === 'pending',
                                                            'is-sent': row.sync_status === 'synced' && row.is_active !== false,
                                                            'is-applied': row.sync_status === 'synced' && row.is_active !== false,
                                                            'is-failed': row.sync_status === 'failed',
                                                            'is-off': row.sync_status === 'disabled' || row.is_active === false,
                                                        }"
                                                        x-text="statusLabel(row)"
                                                    ></span>
                                                </td>
                                                <td class="figma-gaem-actions-cell">
                                                    <template x-if="row.sync_status === 'pending' || row.sync_status === 'failed'">
                                                        <button type="button" class="figma-gaem-row-btn figma-gaem-row-btn--primary" :disabled="loading" @click="pushRow(row.ip)" x-text="row.sync_status === 'failed' ? 'Retry' : 'Push'"></button>
                                                    </template>
                                                    <template x-if="row.sync_status === 'synced' && row.is_active !== false">
                                                        <button type="button" class="figma-gaem-row-btn" disabled>Applied</button>
                                                    </template>
                                                    <label class="figma-toggle figma-toggle--sm figma-toggle--no-labels" :title="row.is_active === false ? 'Enable' : 'Disable'">
                                                        <input type="checkbox" class="figma-toggle-input" :checked="row.is_active !== false" :disabled="loading || togglingIp === row.ip" @change="toggleRow(row, $event.target.checked)">
                                                        <span class="figma-toggle-track pointer-events-none" aria-hidden="true"><span class="figma-toggle-thumb"></span></span>
                                                    </label>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="figma-bip-view-all" @click="showAllExclusions = !showAllExclusions" x-show="rows.length > 5" x-text="showAllExclusions ? 'Show less' : 'View All Exclusions →'"></button>
                        </section>
                    </div>

                    {{-- Persist fields from removed old panels + compact editors for Manage buttons --}}
                    <div class="sr-only" aria-hidden="true">
                        <input type="hidden" name="invalid_bot_action" value="{{ old('invalid_bot_action', $settings->invalid_bot_action ?? 'block') }}">
                        <input type="hidden" name="session_recordings" value="0">
                        @if ($settings->session_recordings)
                            <input type="hidden" name="session_recordings" value="1">
                        @endif
                        <input type="hidden" name="audience_exclusion_event" value="{{ old('audience_exclusion_event', $settings->audience_exclusion_event ?? 'exclude_all_threat_groups_auto') }}">
                        @foreach ([
                            'google_exclude_invalid' => $exclusionRules['exclude_invalid'] ?? true,
                            'google_exclude_malicious' => $exclusionRules['exclude_malicious'] ?? true,
                            'google_exclude_vpn' => $exclusionRules['exclude_vpn'] ?? true,
                            'google_exclude_data_center' => $exclusionRules['exclude_data_center'] ?? true,
                            'google_exclude_proxy' => $exclusionRules['exclude_proxy'] ?? true,
                            'google_exclude_rate_limit' => $exclusionRules['exclude_rate_limit'] ?? true,
                            'google_exclude_out_of_geo' => $exclusionRules['exclude_out_of_geo'] ?? true,
                        ] as $exName => $exOn)
                            <input type="hidden" name="{{ $exName }}" value="0">
                            @if ($exOn)
                                <input type="hidden" name="{{ $exName }}" value="1">
                            @endif
                        @endforeach
                    </div>

                    <div class="figma-rule-editors mb-[18px] space-y-[12px]">
                        <div id="detection-panel-geo-allow" class="figma-rule-editor" x-data="geoAudiencePicker({{ json_encode(['rules' => $geoAudienceRules, 'countries' => $geoCountries, 'endpoints' => $geoEndpoints]) }})" x-init="init()">
                            <h3 class="figma-rule-editor-title">Geo Targeting — Allowed Countries</h3>
                            <input type="hidden" name="out_of_geo_audience" :value="jsonValue">
                            <div class="figma-detection-card-inset space-y-[8px]">
                                <div class="flex flex-wrap items-end gap-[8px]">
                                    @include('paid-marketing.partials.geo-audience-comboboxes')
                                    <button type="button" @click="addRule()" class="figma-detection-geo-add-btn">Add</button>
                                </div>
                                <template x-if="rules.length">
                                    <div class="space-y-[4px]">
                                        <template x-for="(rule, idx) in rules" :key="idx">
                                            <div class="figma-detection-geo-rule-row">
                                                <span x-text="ruleLabel(rule)"></span>
                                                <button type="button" class="text-white/60 hover:text-white" @click="removeRule(idx)" aria-label="Remove">×</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <p x-show="!rules.length" class="figma-detection-geo-empty">No audience locations added yet.</p>
                            </div>
                        </div>

                        <div id="detection-panel-geo-block" class="figma-rule-editor" x-data="geoAudiencePicker({{ json_encode(['rules' => $googleGeoBlockRules, 'countries' => $geoCountries, 'endpoints' => $geoEndpoints]) }})" x-init="init()">
                            <h3 class="figma-rule-editor-title">Blocked Countries (Google Ads)</h3>
                            <input type="hidden" name="google_geo_block_audience" :value="jsonValue">
                            <div class="figma-detection-card-inset space-y-[8px]">
                                <div class="flex flex-wrap items-end gap-[8px]">
                                    @include('paid-marketing.partials.geo-audience-comboboxes')
                                    <button type="button" @click="addRule()" class="figma-detection-geo-add-btn">Add</button>
                                </div>
                                <template x-if="rules.length">
                                    <div class="space-y-[4px]">
                                        <template x-for="(rule, idx) in rules" :key="idx">
                                            <div class="figma-detection-geo-rule-row">
                                                <span x-text="ruleLabel(rule)"></span>
                                                <button type="button" class="text-white/60 hover:text-white" @click="removeRule(idx)" aria-label="Remove">×</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <p x-show="!rules.length" class="figma-detection-geo-empty">No blocked locations added yet.</p>
                            </div>
                        </div>

                        <div id="detection-panel-ip-allow" class="figma-rule-editor" x-data="ipListFileUpload('allow_list_ips')">
                            <div class="flex flex-wrap items-center justify-between gap-[8px] mb-[8px]">
                                <h3 class="figma-rule-editor-title !mb-0">Whitelist IP Addresses</h3>
                                <label class="cursor-pointer rounded-[6px] border border-white/30 px-[10px] py-[6px] text-[10px] text-white hover:bg-white/10">
                                    <input type="file" class="sr-only" accept=".txt,.csv,text/plain,text/csv" @change="onFile($event)">
                                    Choose file
                                </label>
                            </div>
                            <textarea id="allow_list_ips" name="allow_list_ips" rows="3" placeholder="Add IPs or ranges (e.g. 103.207.87.2 or 216.67.176.*)" class="figma-textarea text-[11px]">{{ $settings->allow_list_ips }}</textarea>
                        </div>
                    </div>

                    @if (!empty($countryAudits) && $countryAudits->isNotEmpty())
                        <div class="figma-detection-section mb-[18px]">
                            <h2 class="figma-detection-section-title">Country rule audit log</h2>
                            <div class="figma-detection-card overflow-x-auto">
                                <table class="w-full text-left text-[11px] text-white/80">
                                    <thead>
                                        <tr class="border-b border-white/15 text-white/50">
                                            <th class="py-[6px] pr-[8px] font-medium">When</th>
                                            <th class="py-[6px] pr-[8px] font-medium">Admin</th>
                                            <th class="py-[6px] pr-[8px] font-medium">Action</th>
                                            <th class="py-[6px] pr-[8px] font-medium">Field</th>
                                            <th class="py-[6px] pr-[8px] font-medium">Scope</th>
                                            <th class="py-[6px] font-medium">Change</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($countryAudits as $audit)
                                            <tr class="border-b border-white/10 align-top">
                                                <td class="py-[6px] pr-[8px] whitespace-nowrap">{{ optional($audit->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                                <td class="py-[6px] pr-[8px]">{{ $audit->user?->email ?? $audit->user?->name ?? ('#'.$audit->user_id) }}</td>
                                                <td class="py-[6px] pr-[8px]">{{ $audit->action }}</td>
                                                <td class="py-[6px] pr-[8px]">{{ $audit->field }}</td>
                                                <td class="py-[6px] pr-[8px]">{{ $audit->scope }}</td>
                                                <td class="py-[6px] max-w-[280px] break-all text-white/60">
                                                    <span class="text-white/40">from</span>
                                                    {{ \Illuminate\Support\Str::limit(json_encode($audit->previous_value['value'] ?? null), 80) }}
                                                    <span class="text-white/40">→</span>
                                                    {{ \Illuminate\Support\Str::limit(json_encode($audit->new_value['value'] ?? null), 80) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @php
                        $profileKey = $settings->detection_profile ?? 'standard';
                        $thr = $settings->detection_thresholds ?? [];
                        $profiles = $detectionProfiles ?? \App\Support\DetectionProfiles::catalog();
                        $rapidWindow = (int) ($thr['rapid_window_seconds'] ?? 60);
                        $rapidPreset = match ($rapidWindow) {
                            10 => '10',
                            30 => '30',
                            60 => '60',
                            300 => '300',
                            default => 'custom',
                        };
                        $blockResponse = (string) ($settings->block_response ?? 'hide');
                        $blockResponseUi = match ($blockResponse) {
                            'blank' => 'monitor',
                            'challenge' => 'challenge',
                            'redirect' => 'redirect',
                            'forbid' => 'block',
                            default => 'allow', // hide → Allow (softest)
                        };
                    @endphp
                    <section
                        id="detection-advanced"
                        class="figma-ads"
                        x-data="{
                            rapidPreset: @js($rapidPreset),
                            rapidCustom: @js($rapidWindow),
                            blockAction: @js($blockResponseUi),
                            responseMap: { allow: 'hide', monitor: 'blank', challenge: 'challenge', redirect: 'redirect', block: 'forbid' },
                            setRapid(preset) {
                                this.rapidPreset = preset;
                                if (preset !== 'custom') this.rapidCustom = Number(preset);
                            },
                            get rapidValue() {
                                return this.rapidPreset === 'custom' ? Number(this.rapidCustom || 60) : Number(this.rapidPreset);
                            },
                            get blockResponseValue() {
                                return this.responseMap[this.blockAction] || 'hide';
                            }
                        }"
                    >
                        <div class="figma-ads-head">
                            <h2 class="figma-ads-title">Advanced Detection Settings</h2>
                            <p class="figma-ads-lead">Configure how PromoTix detects and protects your traffic.</p>
                        </div>

                        <div class="figma-ads-card">
                            <div class="figma-ads-col">
                                <h3 class="figma-ads-col-title">Rapid Click Rules (Same IP)</h3>
                                <input type="hidden" name="rapid_window_seconds" :value="rapidValue">
                                <div class="figma-ads-pills">
                                    <button type="button" class="figma-ads-pill" :class="{ 'is-active': rapidPreset === '10' }" @click="setRapid('10')">10 sec</button>
                                    <button type="button" class="figma-ads-pill" :class="{ 'is-active': rapidPreset === '30' }" @click="setRapid('30')">30 sec</button>
                                    <button type="button" class="figma-ads-pill" :class="{ 'is-active': rapidPreset === '60' }" @click="setRapid('60')">60 sec</button>
                                    <button type="button" class="figma-ads-pill" :class="{ 'is-active': rapidPreset === '300' }" @click="setRapid('300')">5 min</button>
                                    <button type="button" class="figma-ads-pill" :class="{ 'is-active': rapidPreset === 'custom' }" @click="setRapid('custom')">Custom</button>
                                </div>
                                <label class="figma-ads-custom" x-show="rapidPreset === 'custom'" x-cloak>
                                    <span>Custom window (sec)</span>
                                    <input type="number" min="10" max="600" x-model.number="rapidCustom">
                                </label>
                            </div>

                            <div class="figma-ads-col">
                                <h3 class="figma-ads-col-title">Click Frequency Limits (Same IP)</h3>
                                <div class="figma-ads-limits">
                                    <label>
                                        <span>Hourly Limit</span>
                                        <select name="hourly_valid_click_limit">
                                            @foreach ([1,2,3,5,10,20] as $n)
                                                <option value="{{ $n }}" @selected((int) ($thr['hourly_valid_click_limit'] ?? 3) === $n)>{{ $n }} Clicks</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span>Daily Limit</span>
                                        <select name="daily_valid_click_limit">
                                            @foreach ([1,2,3,5,10,20,50,100] as $n)
                                                <option value="{{ $n }}" @selected((int) ($thr['daily_valid_click_limit'] ?? 20) === $n)>{{ $n }} Clicks</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span>Weekly Limit</span>
                                        <select name="weekly_valid_click_limit">
                                            @foreach ([10,20,50,100,200,500] as $n)
                                                <option value="{{ $n }}" @selected((int) ($thr['weekly_valid_click_limit'] ?? 100) === $n)>{{ $n }} Clicks</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span>Monthly Limit</span>
                                        <select name="monthly_valid_click_limit">
                                            @foreach ([50,100,200,300,500,1000] as $n)
                                                <option value="{{ $n }}" @selected((int) ($thr['monthly_valid_click_limit'] ?? 300) === $n)>{{ $n }} Clicks</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                            </div>

                            <div class="figma-ads-col">
                                <h3 class="figma-ads-col-title">Block Response Action</h3>
                                <input type="hidden" name="block_response" :value="blockResponseValue">
                                <div class="figma-ads-actions">
                                    <button type="button" class="figma-ads-action figma-ads-action--allow" :class="{ 'is-selected': blockAction === 'allow' }" @click="blockAction = 'allow'">Allow</button>
                                    <button type="button" class="figma-ads-action figma-ads-action--monitor" :class="{ 'is-selected': blockAction === 'monitor' }" @click="blockAction = 'monitor'">Monitor</button>
                                    <button type="button" class="figma-ads-action figma-ads-action--challenge" :class="{ 'is-selected': blockAction === 'challenge' }" @click="blockAction = 'challenge'">Challenge</button>
                                    <button type="button" class="figma-ads-action figma-ads-action--redirect" :class="{ 'is-selected': blockAction === 'redirect' }" @click="blockAction = 'redirect'">Redirect</button>
                                    <button type="button" class="figma-ads-action figma-ads-action--block" :class="{ 'is-selected': blockAction === 'block' }" @click="blockAction = 'block'">Block</button>
                                </div>
                                <label class="figma-ads-custom" x-show="blockAction === 'redirect'" x-cloak>
                                    <span>Redirect URL</span>
                                    <input type="url" name="block_redirect_url" value="{{ $settings->block_redirect_url }}" placeholder="https://example.com/safe">
                                </label>
                            </div>
                        </div>

                        <div class="figma-ads-more space-y-[12px]">
                            <p class="text-[11px] leading-relaxed text-[#a9a9a9]">
                                These controls tune how aggressively PromoTix flags or blocks paid clicks. Optional fine-tuning on top of the main threat rules above.
                            </p>
                            <h3 class="figma-detection-section-title !mb-[6px]">Detection profile</h3>
                            <div class="figma-detection-profile-grid">
                                @foreach ($profiles as $pkey => $pinfo)
                                    <label class="figma-detection-profile-card {{ $profileKey === $pkey ? 'is-selected' : '' }}">
                                        <div class="flex items-start gap-[8px]">
                                            <input type="radio" name="detection_profile" value="{{ $pkey }}" class="mt-[3px]" @checked($profileKey === $pkey)>
                                            <div>
                                                <p class="figma-detection-profile-card-title">{{ $pinfo['label'] }}</p>
                                                <p class="figma-detection-profile-card-summary">{{ $pinfo['summary'] }}</p>
                                                <p class="figma-detection-profile-card-meta">FP risk: {{ $pinfo['false_positive_risk'] }} · {{ $pinfo['recommended'] }}</p>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            <div class="figma-detection-advanced-panel grid gap-[10px] sm:grid-cols-2">
                                <label class="figma-detection-advanced-field">
                                    Flag at prior clicks
                                    <input type="number" name="rapid_flag_at" min="1" max="10" value="{{ $thr['rapid_flag_at'] ?? 1 }}" class="figma-detection-advanced-input mt-[4px] w-full">
                                </label>
                                <label class="figma-detection-advanced-field">
                                    Block at prior clicks
                                    <input type="number" name="rapid_block_at" min="1" max="20" value="{{ $thr['rapid_block_at'] ?? 2 }}" class="figma-detection-advanced-input mt-[4px] w-full">
                                </label>
                            </div>

                            <label class="figma-detection-advanced-inline">
                                Fail-safe when detection is unavailable
                                <select name="fail_mode" class="figma-detection-advanced-input">
                                    <option value="open" @selected(($settings->fail_mode ?? 'open') === 'open')>Fail open (allow)</option>
                                    <option value="closed" @selected(($settings->fail_mode ?? 'open') === 'closed')>Fail closed (block)</option>
                                </select>
                            </label>

                            <div class="figma-detection-advanced-panel grid gap-[10px] sm:grid-cols-2">
                                <label class="figma-detection-advanced-field sm:col-span-2">
                                    Session recording retention (days)
                                    <input type="number" name="recording_retention_days" min="1" max="3650" value="{{ $settings->recording_retention_days ?? 30 }}" class="figma-detection-advanced-input mt-[4px] w-full max-w-[160px]">
                                </label>
                                <label class="figma-detection-advanced-field">
                                    Geo rule scope
                                    <select name="geo_rule_scope" class="figma-detection-advanced-input mt-[4px] w-full">
                                        <option value="domain" @selected(($settings->geo_rule_scope ?? 'domain') === 'domain')>This domain only</option>
                                        <option value="workspace" @selected(($settings->geo_rule_scope ?? '') === 'workspace')>Workspace defaults</option>
                                    </select>
                                </label>
                                <label class="figma-detection-advanced-field flex items-end gap-[8px]">
                                    <input type="checkbox" name="save_workspace_geo" value="1" class="rounded border-white/30">
                                    Save current geo rules as workspace defaults
                                </label>
                                <label class="figma-detection-advanced-field flex items-center gap-[8px] sm:col-span-2">
                                    <input type="hidden" name="consent_required" value="0">
                                    <input type="checkbox" name="consent_required" value="1" @checked($settings->consent_required ?? false) class="rounded border-white/30">
                                    Require consent banner before tracking (GDPR/CCPA)
                                </label>
                                <label class="figma-detection-advanced-field sm:col-span-2">
                                    Consent regions (ISO country codes, comma-separated; empty = all)
                                    <input type="text" name="consent_regions" value="{{ implode(',', (array) ($settings->consent_regions ?? [])) }}" placeholder="DE,FR,GB" class="figma-detection-advanced-input mt-[4px] w-full">
                                </label>
                                <label class="figma-detection-advanced-field flex items-center gap-[8px] sm:col-span-2">
                                    <input type="hidden" name="recording_mask_passwords" value="0">
                                    <input type="checkbox" name="recording_mask_passwords" value="1" @checked($settings->recording_mask_passwords ?? true) class="rounded border-white/30">
                                    Mask password and sensitive inputs in session recordings
                                </label>
                            </div>
                        </div>

                        <div class="figma-detection-save-row flex justify-end pt-[16px]">
                            <button type="submit" class="rounded-[6px] bg-white px-[22px] py-[9px] text-[13px] font-semibold text-[#6400B2] shadow-[0_8px_20px_rgba(0,0,0,.25)]">Save changes</button>
                        </div>
                    </section>
                </form>
            @endif
        @endif
    </section>
</div>

<script>
function ipListFileUpload(textareaId) {
    return {
        fileName: '',
        async onFile(event) {
            const file = event.target.files?.[0];
            if (!file) {
                this.fileName = '';
                return;
            }
            this.fileName = file.name;
            try {
                const text = await file.text();
                const textarea = document.getElementById(textareaId);
                if (!textarea) return;
                const existing = textarea.value.trim();
                const incoming = text.trim();
                if (!incoming) return;
                textarea.value = existing ? existing + '\n' + incoming : incoming;
            } catch (e) {
                this.fileName = 'Read failed';
            }
            event.target.value = '';
        },
    };
}

function blockIpPanel(config) {
    const parseRows = (raw) => {
        const rows = [];
        String(raw || '').split(/\r?\n/).forEach((line) => {
            const trimmed = line.trim();
            if (!trimmed) return;
            const disabled = trimmed.startsWith('#');
            const clean = disabled ? trimmed.replace(/^#\s?/, '') : trimmed;
            if (!clean || clean.startsWith('#')) return;
            const parts = clean.split('|').map((p) => p.trim());
            const ip = parts[0] || '';
            if (!ip) return;
            rows.push({
                ip,
                duration: parts[1] || 'permanent',
                reason: parts[2] || '',
                source: 'Manual',
                risk: null,
                added_by: 'Admin',
                active: !disabled,
                raw: trimmed,
            });
        });
        return rows;
    };

    return {
        rows: Array.isArray(config.rows) && config.rows.length ? config.rows.map((r) => ({ ...r, active: r.active !== false })) : parseRows(config.initial || ''),
        rawText: config.initial || '',
        draftIp: '',
        draftDuration: '24h',
        draftReason: '',
        showAll: false,
        get visibleRows() {
            return this.showAll ? this.rows : this.rows.slice(0, 5);
        },
        syncRaw() {
            this.rawText = this.rows.map((r) => {
                const base = [r.ip, r.duration || 'permanent', r.reason || ''].filter((p, i) => i < 2 || p).join(' | ');
                return r.active === false ? ('# ' + base) : base;
            }).join('\n');
        },
        addRow() {
            const ip = (this.draftIp || '').trim();
            if (!ip) return;
            this.rows.unshift({
                ip,
                duration: this.draftDuration || 'permanent',
                reason: (this.draftReason || '').trim(),
                source: 'Manual',
                risk: null,
                added_by: 'Admin',
                active: true,
            });
            this.draftIp = '';
            this.draftReason = '';
            this.syncRaw();
        },
        toggleRow(idx, active) {
            const list = this.showAll ? this.rows : this.rows.slice(0, 5);
            const row = list[idx];
            if (!row) return;
            const realIdx = this.rows.indexOf(row);
            if (realIdx < 0) return;
            this.rows[realIdx].active = active;
            this.syncRaw();
        },
        maskIp(ip) {
            const s = String(ip || '');
            if (s.includes(':') || s.includes('*') || s.includes('/')) return s;
            const parts = s.split('.');
            if (parts.length !== 4) return s;
            return parts[0] + '.XXX.XXX.' + parts[3];
        },
        formatDuration(d) {
            const map = { '2m': '2 Minutes', '1h': '1 Hour', '24h': '24 Hours', '7d': '7 Days', permanent: 'Permanent' };
            return map[d] || d || 'Permanent';
        },
        async onFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            try {
                const text = await file.text();
                const incoming = parseRows(text);
                if (!incoming.length) return;
                this.rows = [...incoming, ...this.rows];
                this.syncRaw();
            } catch (e) {}
            event.target.value = '';
        },
        init() {
            this.syncRaw();
        },
    };
}

function googleExclusionPanel(config) {
    return {
        ip: '',
        bulkIps: '',
        bulkFile: null,
        bulkFileName: '',
        rows: config.rows || [],
        pushUrl: config.pushUrl,
        pushRowUrl: config.pushRowUrl,
        toggleRowUrl: config.toggleRowUrl,
        bulkUrl: config.bulkUrl,
        syncUrl: config.syncUrl,
        csrf: config.csrf,
        loading: false,
        pushingIp: '',
        togglingIp: '',
        message: '',
        ok: true,
        showBulk: false,
        showAllExclusions: false,
        statusLabel(row) {
            if (row.sync_status === 'disabled' || row.is_active === false) return 'Off';
            if (row.sync_status === 'pending') return 'Pending';
            if (row.sync_status === 'failed') return 'Failed';
            if (row.sync_status === 'synced') return 'Applied';
            return row.sync_status || '—';
        },
        onBulkFile(event) {
            const file = event.target.files?.[0];
            this.bulkFile = file || null;
            this.bulkFileName = file ? file.name : '';
        },
        async pushBulk() {
            if (this.loading || (!this.bulkIps.trim() && !this.bulkFile)) return;
            this.loading = true;
            this.message = '';
            try {
                const form = new FormData();
                if (this.bulkIps.trim()) {
                    form.append('ips', this.bulkIps.trim());
                }
                if (this.bulkFile) {
                    form.append('file', this.bulkFile);
                }
                const res = await fetch(this.bulkUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || (this.ok ? 'Bulk upload finished.' : 'Bulk upload failed.');
                if (Array.isArray(data.rows)) this.rows = data.rows;
                if (this.ok) {
                    this.bulkIps = '';
                    this.bulkFile = null;
                    this.bulkFileName = '';
                }
            } catch (e) {
                this.ok = false;
                this.message = 'Bulk upload request failed.';
            } finally {
                this.loading = false;
            }
        },
        async pushIp() {
            const ip = this.ip.trim();
            if (!ip || this.loading) return;
            this.loading = true;
            this.message = '';
            try {
                const res = await fetch(this.pushUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ip }),
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || (this.ok ? 'IP pushed.' : 'Push failed.');
                if (Array.isArray(data.rows)) this.rows = data.rows;
                if (this.ok) this.ip = '';
            } catch (e) {
                this.ok = false;
                this.message = 'Request failed. Check console or try again.';
            } finally {
                this.loading = false;
            }
        },
        async pushRow(ip) {
            if (!ip || this.loading) return;
            this.loading = true;
            this.pushingIp = ip;
            this.message = '';
            try {
                const res = await fetch(this.pushRowUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ip }),
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || (this.ok ? `IP ${ip} pushed to Google Ads.` : 'Push failed.');
                if (Array.isArray(data.rows)) this.rows = data.rows;
            } catch (e) {
                this.ok = false;
                this.message = 'Push request failed.';
            } finally {
                this.loading = false;
                this.pushingIp = '';
            }
        },
        async toggleRow(row, active) {
            const ip = row?.ip;
            if (!ip || this.loading) return;
            this.loading = true;
            this.togglingIp = ip;
            this.message = '';
            const previous = row.is_active !== false;
            row.is_active = active;
            try {
                const res = await fetch(this.toggleRowUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ip, active }),
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || (this.ok
                    ? (active ? `IP ${ip} block enabled in Google Ads.` : `IP ${ip} block removed from Google Ads.`)
                    : 'Toggle failed.');
                if (Array.isArray(data.rows)) {
                    this.rows = data.rows;
                } else if (data.row) {
                    const idx = this.rows.findIndex(r => r.ip === ip);
                    if (idx >= 0) this.rows[idx] = data.row;
                }
                if (!this.ok) row.is_active = previous;
            } catch (e) {
                this.ok = false;
                this.message = 'Toggle request failed.';
                row.is_active = previous;
            } finally {
                this.loading = false;
                this.togglingIp = '';
            }
        },
        async syncPending() {
            if (this.loading) return;
            this.loading = true;
            this.message = '';
            try {
                const res = await fetch(this.syncUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ limit: 100 }),
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || 'Sync finished.';
                if (Array.isArray(data.rows)) this.rows = data.rows;
            } catch (e) {
                this.ok = false;
                this.message = 'Sync request failed.';
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
<script>
window.detectionPageFilters = function detectionPageFilters(config) {
    return {
        filters: {
            domainId: config.domainId || '',
            path: config.path || '',
            googleAdsAccountId: config.googleAdsAccountId || '',
            campaign: config.campaign || '',
            trafficSource: config.trafficSource || 'google_ads',
        },
        applyFilters() {
            const params = new URLSearchParams();
            if (this.filters.domainId) params.set('domain_id', this.filters.domainId);
            if (this.filters.path) params.set('path', this.filters.path);
            if (this.filters.googleAdsAccountId) params.set('google_ads_account_id', this.filters.googleAdsAccountId);
            if (this.filters.campaign) params.set('campaign', this.filters.campaign);
            if (this.filters.trafficSource) params.set('traffic_source', this.filters.trafficSource);
            const qs = params.toString();
            window.location.href = `{{ route('paid-marketing.detection-settings') }}${qs ? '?' + qs : ''}`;
        },
    };
};
</script>
@endsection
