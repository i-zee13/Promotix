@extends('layouts.admin')

@section('title', 'Analytics | Visitor Journey')

@section('rightbar')
<div class="figma-rightbar-default paid-rightbar">
    @include('partials.figma-rightbar-header-actions')
    @include('partials.figma-rightbar-analytics')
</div>
@endsection

@section('content')
<div
    class="brand-page-bg analytics-skin min-h-[calc(100vh-49px)]"
    x-data="visitorJourneyPage()"
    x-init="init()"
    @promotix:date-range.window="onDateRange($event)"
>
    <section class="mx-auto w-full min-w-0 px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <style>
            .vj-page { color: rgba(255,255,255,.88); }
            .vj-head { display:flex; flex-direction:column; gap:14px; margin-bottom:18px; }
            @media (min-width:1100px){ .vj-head{ flex-direction:row; align-items:flex-start; justify-content:space-between; } }
            .vj-title { font-size:28px; font-weight:650; color:#fff; letter-spacing:-.02em; line-height:1.1; }
            @media (min-width:640px){ .vj-title{ font-size:32px; } }
            .vj-title__muted { color:#a9a9a9; }
            .vj-title__pipe { color:rgba(255,255,255,.35); margin:0 4px; }
            .vj-filters { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:8px; }
            .vj-filter { position:relative; min-width:132px; }
            .vj-filter__select, .vj-filter__date {
                appearance:none; -webkit-appearance:none; height:38px; width:100%;
                border-radius:8px; border:1px solid rgba(255,255,255,.16); background:#101010;
                color:rgba(255,255,255,.82); font-size:12px; font-weight:500; padding:0 32px 0 12px; outline:none;
            }
            .vj-filter__chev { pointer-events:none; position:absolute; right:10px; top:50%; transform:translateY(-50%); width:12px; height:12px; color:rgba(255,255,255,.45); }
            .vj-filter__date-wrap { position:relative; min-width:168px; }
            .vj-filter__date { display:inline-flex; align-items:center; gap:8px; cursor:pointer; padding-right:12px; white-space:nowrap; }
            .vj-filter__date svg { width:14px; height:14px; color:rgba(255,255,255,.5); margin-left:auto; }
            .vj-sample {
                display:inline-flex; align-items:center; gap:8px; height:38px; padding:0 10px;
                border-radius:8px; border:1px solid rgba(255,255,255,.14); background:#101010;
                font-size:11px; color:rgba(255,255,255,.65);
            }
            .vj-toggle {
                width:34px; height:18px; border-radius:999px; background:rgba(255,255,255,.15);
                position:relative; transition:background .15s ease; flex-shrink:0;
            }
            .vj-toggle.is-on { background:#FF6600; }
            .vj-toggle::after {
                content:''; position:absolute; top:2px; left:2px; width:14px; height:14px;
                border-radius:999px; background:#fff; transition:transform .15s ease;
            }
            .vj-toggle.is-on::after { transform:translateX(16px); }

            .vj-kpi-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
            @media (min-width:900px){ .vj-kpi-grid{ grid-template-columns:repeat(3,minmax(0,1fr)); } }
            @media (min-width:1280px){ .vj-kpi-grid{ grid-template-columns:repeat(6,minmax(0,1fr)); } }
            .vj-kpi {
                border-radius:10px; background:#121212; border:1px solid rgba(255,102,0,.28);
                padding:12px; min-height:132px; display:flex; flex-direction:column;
            }
            .vj-kpi__label { font-size:11px; font-weight:600; color:rgba(255,255,255,.5); margin-bottom:8px; }
            .vj-kpi__value { font-size:26px; font-weight:700; color:#fff; letter-spacing:-.02em; line-height:1.05; }
            .vj-kpi__delta { margin-top:6px; font-size:10px; display:flex; gap:5px; flex-wrap:wrap; }
            .vj-kpi__delta-num { font-weight:700; color:#4ade80; }
            .vj-kpi__delta-num.is-down { color:#f87171; }
            .vj-kpi__delta-vs { color:rgba(255,255,255,.35); }
            .vj-kpi__spark { margin-top:auto; padding-top:8px; height:34px; }

            .vj-main { display:grid; grid-template-columns:minmax(0,1fr); gap:14px; margin-bottom:16px; }
            @media (min-width:1180px){ .vj-main{ grid-template-columns:minmax(0,1fr) 320px; align-items:start; } }
            .vj-card {
                border-radius:12px; border:1px solid rgba(255,102,0,.22); background:#121212; overflow:hidden; min-width:0;
            }
            .vj-card__pad { padding:14px; }
            .vj-card__title { font-size:15px; font-weight:650; color:#fff; margin-bottom:12px; }
            .vj-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
            .vj-tab {
                border-radius:8px; border:1px solid rgba(255,255,255,.14); background:transparent;
                color:rgba(255,255,255,.55); font-size:12px; font-weight:600; padding:7px 12px;
            }
            .vj-tab.is-active { background:#FF6600; border-color:#FF6600; color:#fff; }

            .vj-flow { position:relative; min-height:320px; overflow-x:auto; }
            .vj-flow__cols {
                display:grid; grid-template-columns:repeat(4, minmax(140px, 1fr)); gap:18px;
                position:relative; z-index:1; min-width:640px;
            }
            .vj-flow__col-label {
                font-size:10px; font-weight:650; letter-spacing:.04em; text-transform:uppercase;
                color:rgba(255,255,255,.4); margin-bottom:10px;
            }
            .vj-node {
                border-radius:10px; border:1px solid rgba(255,102,0,.45); background:#0f0f0f;
                padding:10px 12px; margin-bottom:10px; min-height:52px;
            }
            .vj-node.is-exit { border-color:rgba(239,68,68,.55); }
            .vj-node.is-lead { border-color:rgba(34,197,94,.55); }
            .vj-node.is-pending { border-color:rgba(234,179,8,.55); }
            .vj-node.is-action { border-color:rgba(255,102,0,.7); }
            .vj-node.is-form { border-color:rgba(34,197,94,.45); }
            .vj-node__label { font-size:12px; font-weight:650; color:#fff; margin-bottom:4px; word-break:break-word; }
            .vj-node__meta { font-size:11px; color:rgba(255,255,255,.45); }
            .vj-flow__svg {
                position:absolute; inset:28px 0 0 0; width:100%; height:calc(100% - 28px);
                pointer-events:none; z-index:0; min-width:640px;
            }

            .vj-detail { border-radius:12px; border:1px solid rgba(255,102,0,.22); background:#121212; padding:14px; position:sticky; top:72px; }
            .vj-detail__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
            .vj-detail__title { font-size:14px; font-weight:650; color:#fff; }
            .vj-meta-grid { display:grid; gap:8px; margin-bottom:14px; font-size:12px; }
            .vj-meta-row { display:flex; justify-content:space-between; gap:8px; color:rgba(255,255,255,.55); }
            .vj-meta-row strong { color:#fff; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
            .vj-status-dot { width:7px; height:7px; border-radius:999px; background:#ef4444; display:inline-block; }
            .vj-timeline { position:relative; padding-left:18px; }
            .vj-timeline::before {
                content:''; position:absolute; left:4px; top:4px; bottom:4px; width:1px; background:rgba(255,102,0,.35);
            }
            .vj-tl-item { position:relative; padding:0 0 14px; }
            .vj-tl-item::before {
                content:''; position:absolute; left:-16px; top:5px; width:9px; height:9px; border-radius:999px;
                background:#FF6600; box-shadow:0 0 0 3px rgba(255,102,0,.15);
            }
            .vj-tl-time { font-size:10px; color:rgba(255,255,255,.4); margin-bottom:2px; }
            .vj-tl-label { font-size:12px; color:#fff; font-weight:600; }
            .vj-tl-kind { font-size:10px; color:rgba(255,255,255,.45); margin-left:6px; font-weight:500; }
            .vj-tl-note { font-size:11px; color:rgba(255,255,255,.4); margin-top:4px; }

            .vj-widgets { display:grid; grid-template-columns:1fr; gap:12px; margin-bottom:16px; }
            @media (min-width:900px){ .vj-widgets{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
            @media (min-width:1280px){ .vj-widgets{ grid-template-columns:repeat(4,minmax(0,1fr)); } }
            .vj-widget { border-radius:12px; border:1px solid rgba(255,102,0,.22); background:#121212; padding:14px; min-height:220px; }
            .vj-widget__title { font-size:13px; font-weight:650; color:#fff; margin-bottom:12px; }
            .vj-path-row { display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; font-size:12px; }
            .vj-path-rank {
                width:18px; height:18px; border-radius:999px; background:rgba(255,102,0,.18); color:#FF6600;
                display:grid; place-items:center; font-size:10px; font-weight:700; flex-shrink:0; margin-top:1px;
            }
            .vj-path-body { min-width:0; flex:1; color:rgba(255,255,255,.75); word-break:break-word; }
            .vj-path-meta { color:rgba(255,255,255,.4); white-space:nowrap; font-size:11px; }
            .vj-hbar { display:grid; grid-template-columns:78px 1fr 34px; gap:8px; align-items:center; margin-bottom:10px; font-size:11px; color:rgba(255,255,255,.65); }
            .vj-hbar__track { height:8px; border-radius:999px; background:rgba(255,255,255,.06); overflow:hidden; }
            .vj-hbar__fill { height:100%; border-radius:999px; background:#FF6600; }
            .vj-donut-wrap { display:flex; align-items:center; gap:12px; }
            .vj-donut { width:120px; height:120px; border-radius:999px; display:grid; place-items:center; flex-shrink:0; }
            .vj-donut__hole { width:72px; height:72px; border-radius:999px; background:#121212; display:grid; place-items:center; text-align:center; z-index:1; }
            .vj-legend { display:flex; flex-direction:column; gap:7px; font-size:11px; color:rgba(255,255,255,.7); min-width:0; }
            .vj-legend-row { display:flex; align-items:center; gap:8px; }
            .vj-legend-swatch { width:8px; height:8px; border-radius:999px; flex-shrink:0; }

            .vj-table-card { border-radius:12px; border:1px solid rgba(255,102,0,.22); background:#121212; overflow:hidden; }
            .vj-table-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 14px 8px; }
            .vj-table-wrap { overflow-x:auto; padding:0 10px 14px; }
            .vj-table { width:100%; border-collapse:separate; border-spacing:0 6px; min-width:860px; }
            .vj-table th {
                text-align:left; font-size:10px; font-weight:650; letter-spacing:.04em; text-transform:uppercase;
                color:rgba(255,255,255,.42); padding:8px 10px; white-space:nowrap;
            }
            .vj-table td {
                background:#181818; padding:12px 10px; font-size:12px; color:rgba(255,255,255,.88);
                border-top:1px solid rgba(255,255,255,.04); border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle;
            }
            .vj-table tr.is-selected td { background:#221a14; border-color:rgba(255,102,0,.28); }
            .vj-table tr td:first-child { border-left:1px solid rgba(255,255,255,.04); border-radius:10px 0 0 10px; }
            .vj-table tr td:last-child { border-right:1px solid rgba(255,255,255,.04); border-radius:0 10px 10px 0; }
            .vj-chip {
                display:inline-flex; align-items:center; border-radius:999px; font-size:10px; font-weight:600;
                padding:3px 8px; margin:0 2px; white-space:nowrap;
            }
            .vj-chip.is-page { background:rgba(255,255,255,.08); color:rgba(255,255,255,.75); border:1px solid rgba(255,255,255,.1); }
            .vj-chip.is-action { background:rgba(255,102,0,.15); color:#FF6600; border:1px solid rgba(255,102,0,.45); }
            .vj-chip.is-form { background:rgba(34,197,94,.12); color:#4ade80; border:1px solid rgba(34,197,94,.4); }
            .vj-chip.is-exit { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.4); }
            .vj-chip.is-lead { background:rgba(34,197,94,.12); color:#4ade80; border:1px solid rgba(34,197,94,.45); }
            .vj-chip.is-pending { background:rgba(234,179,8,.12); color:#facc15; border:1px solid rgba(234,179,8,.45); }
            .vj-chip.is-none { background:rgba(255,255,255,.06); color:rgba(255,255,255,.55); border:1px solid rgba(255,255,255,.12); }
            .vj-arrow { color:rgba(255,255,255,.3); margin:0 2px; font-size:11px; }
            .vj-empty { padding:28px 16px; text-align:center; color:rgba(255,255,255,.4); font-size:13px; }
            .vj-link { font-size:12px; color:#FF6600; font-weight:600; }
            .vj-card__top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
            .vj-card__title-row { display:inline-flex; align-items:center; gap:8px; }
            .vj-card__icon { color:#FF6600; display:inline-flex; }
            .vj-mini-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
            .vj-mini-filters select {
                height:32px; border-radius:8px; border:1px solid rgba(255,255,255,.14); background:#0f0f0f;
                color:rgba(255,255,255,.75); font-size:11px; padding:0 28px 0 10px; appearance:none;
            }
            .vj-ev-legend {
                display:flex; flex-wrap:wrap; gap:14px; align-items:center;
                margin:0 0 12px; font-size:11px; color:rgba(255,255,255,.55);
            }
            .vj-ev-legend__item { display:inline-flex; align-items:center; gap:6px; }
            .vj-axis-label { font-size:10px; color:rgba(255,255,255,.35); margin-bottom:6px; }
            .vj-et { overflow-x:auto; min-height:280px; }
            .vj-et__axis {
                display:grid; grid-template-columns:118px 1fr; gap:0; margin-bottom:4px; min-width:720px;
            }
            .vj-et__ticks {
                display:flex; justify-content:space-between; padding:0 8px 6px;
                font-size:10px; color:rgba(255,255,255,.35); border-bottom:1px solid rgba(255,255,255,.08);
            }
            .vj-et__row {
                display:grid; grid-template-columns:118px 1fr; gap:0; min-width:720px;
                border-radius:8px; margin-bottom:2px; cursor:pointer;
            }
            .vj-et__row.is-active { background:rgba(255,102,0,.10); }
            .vj-et__row:hover { background:rgba(255,255,255,.03); }
            .vj-et__row.is-active:hover { background:rgba(255,102,0,.14); }
            .vj-et__sid {
                padding:14px 10px; font-size:12px; color:#fff; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            }
            .vj-et__sid small { display:block; margin-top:3px; font-size:10px; color:rgba(255,255,255,.4); font-family:inherit; }
            .vj-et__track {
                position:relative; height:64px; margin:6px 8px;
                border-bottom:1px dotted rgba(255,255,255,.18);
            }
            .vj-et__marker {
                position:absolute; top:8px; transform:translateX(-50%); text-align:center; z-index:2;
            }
            .vj-et__marker.is-selected .vj-ev-icon { outline:2px solid #FF6600; outline-offset:2px; }
            .vj-et__m-label {
                font-size:9px; color:rgba(255,255,255,.7); white-space:nowrap; margin-bottom:4px;
                max-width:72px; overflow:hidden; text-overflow:ellipsis; margin-left:auto; margin-right:auto;
            }
            .vj-et__m-time { font-size:9px; color:rgba(255,255,255,.4); margin-top:4px; }
            .vj-ev-icon {
                width:14px; height:14px; display:inline-block; vertical-align:middle;
            }
            .vj-ev-icon.is-page { width:10px; height:10px; border-radius:999px; background:#FF6600; }
            .vj-ev-icon.is-scroll {
                width:10px; height:10px; background:#A855F7; transform:rotate(45deg); border-radius:1px;
            }
            .vj-ev-icon.is-cta {
                width:10px; height:10px; background:#EAB308; transform:rotate(45deg); border-radius:1px;
            }
            .vj-ev-icon.is-form {
                width:11px; height:11px; border-radius:999px; border:2px solid #22C55E; background:transparent;
            }
            .vj-ev-icon.is-exit {
                width:10px; height:10px; border-radius:2px; background:rgba(255,255,255,.55);
            }
            .vj-tooltip {
                position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
                white-space:nowrap; background:#0f0f0f; border:1px solid rgba(255,102,0,.55);
                color:rgba(255,255,255,.85); font-size:10px; padding:5px 8px; border-radius:6px;
                pointer-events:none; z-index:5; box-shadow:0 8px 20px rgba(0,0,0,.45);
            }
            .vj-info-box {
                margin:12px 0; padding:10px 12px; border-radius:8px;
                background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
                font-size:12px; color:rgba(255,255,255,.65); display:flex; gap:8px; align-items:flex-start;
            }
            .vj-info-box svg { flex-shrink:0; color:#60a5fa; margin-top:1px; }
            .vj-seq-title { font-size:12px; font-weight:650; color:#fff; margin:14px 0 8px; }
            .vj-seq-item {
                display:grid; grid-template-columns:42px 16px 1fr; gap:8px; align-items:start;
                padding:7px 0; border-bottom:1px solid rgba(255,255,255,.06); font-size:12px;
            }
            .vj-status-ok { width:7px; height:7px; border-radius:999px; background:#22c55e; display:inline-block; }

            /* Individual Sessions 3-pane */
            .vj-main.is-sessions { grid-template-columns: minmax(0,1fr) !important; }
            .vj-is {
                display:grid; grid-template-columns:minmax(0,1fr); gap:0;
                border-top:1px solid rgba(255,255,255,.06); min-height:520px;
            }
            @media (min-width:1100px) {
                .vj-is { grid-template-columns: 280px minmax(0,1fr) 280px; }
            }
            .vj-is__pane { min-width:0; padding:12px; }
            .vj-is__pane + .vj-is__pane { border-left:1px solid rgba(255,255,255,.08); }
            .vj-is__head {
                display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px;
            }
            .vj-is__title { font-size:13px; font-weight:650; color:#fff; }
            .vj-is__count { font-size:11px; color:rgba(255,255,255,.4); }
            .vj-is__tools { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
            .vj-is__search { position:relative; flex:1 1 100%; }
            .vj-is__search input {
                width:100%; height:32px; border-radius:8px; border:1px solid rgba(255,255,255,.12);
                background:#0b0b0b; color:#ddd; font-size:11px; padding:0 10px 0 30px;
            }
            .vj-is__search svg {
                position:absolute; left:9px; top:50%; transform:translateY(-50%);
                width:13px; height:13px; color:rgba(255,255,255,.35);
            }
            .vj-is__tools select {
                height:30px; border-radius:7px; border:1px solid rgba(255,255,255,.12); background:#0b0b0b;
                color:rgba(255,255,255,.7); font-size:10px; padding:0 22px 0 8px; appearance:none;
            }
            .vj-scard {
                width:100%; text-align:left; border-radius:10px; border:1px solid rgba(255,255,255,.1);
                background:#0f0f0f; padding:10px; margin-bottom:8px; cursor:pointer; color:inherit;
            }
            .vj-scard.is-active { border-color:#FF6600; background:rgba(255,102,0,.06); }
            .vj-scard__top { display:flex; gap:8px; align-items:flex-start; }
            .vj-scard__icon {
                width:28px; height:28px; border-radius:8px; background:rgba(255,255,255,.06);
                display:grid; place-items:center; color:rgba(255,255,255,.7); flex-shrink:0;
            }
            .vj-scard__ids { min-width:0; flex:1; }
            .vj-scard__sid { font-family:ui-monospace,Menlo,monospace; font-size:12px; color:#fff; font-weight:650; }
            .vj-scard__did { font-family:ui-monospace,Menlo,monospace; font-size:10px; color:rgba(255,255,255,.4); margin-top:2px; }
            .vj-scard__src { display:flex; align-items:center; gap:6px; margin-top:8px; font-size:11px; color:rgba(255,255,255,.6); }
            .vj-scard__meta { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; font-size:10px; color:rgba(255,255,255,.45); }
            .vj-scard__meta span { display:inline-flex; align-items:center; gap:4px; }
            .vj-pill {
                display:inline-flex; align-items:center; gap:5px; border-radius:999px; border:1px solid currentColor;
                font-size:10px; font-weight:650; padding:2px 8px; white-space:nowrap;
            }
            .vj-pill.is-pending { color:#fb923c; background:rgba(255,102,0,.1); }
            .vj-pill.is-lead { color:#4ade80; background:rgba(34,197,94,.1); }
            .vj-pill.is-none { color:rgba(255,255,255,.5); background:rgba(255,255,255,.04); }
            .vj-pill.is-active { color:#60a5fa; background:rgba(59,130,246,.12); }
            .vj-pill__dot { width:6px; height:6px; border-radius:999px; background:currentColor; }
            .vj-pager {
                display:flex; align-items:center; justify-content:space-between; gap:8px;
                margin-top:10px; font-size:11px; color:rgba(255,255,255,.4);
            }
            .vj-pager__btns { display:flex; gap:4px; align-items:center; }
            .vj-pager button {
                min-width:26px; height:26px; border-radius:6px; border:1px solid rgba(255,255,255,.12);
                background:transparent; color:rgba(255,255,255,.65); font-size:11px;
            }
            .vj-pager button.is-on { background:#FF6600; border-color:#FF6600; color:#fff; }

            .vj-sj__head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; }
            .vj-sj__title { font-size:14px; font-weight:650; color:#fff; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
            .vj-sj__stats { display:flex; flex-wrap:wrap; gap:12px; font-size:11px; color:rgba(255,255,255,.55); }
            .vj-sj__stats span { display:inline-flex; align-items:center; gap:5px; }
            .vj-sj-tl { position:relative; padding-left:0; }
            .vj-sj-item { display:grid; grid-template-columns:72px 18px 1fr; gap:10px; min-height:56px; }
            .vj-sj-time { text-align:right; font-size:10px; color:rgba(255,255,255,.45); padding-top:2px; }
            .vj-sj-time small { display:block; color:rgba(255,255,255,.3); margin-top:2px; }
            .vj-sj-rail { position:relative; display:flex; justify-content:center; }
            .vj-sj-rail::before {
                content:''; position:absolute; top:8px; bottom:-8px; width:2px; background:rgba(255,102,0,.45);
            }
            .vj-sj-item:last-child .vj-sj-rail::before { display:none; }
            .vj-sj-node {
                width:14px; height:14px; border-radius:999px; background:#FF6600; border:2px solid #121212;
                z-index:1; margin-top:3px; box-shadow:0 0 0 2px rgba(255,102,0,.25);
            }
            .vj-sj-node.is-exit { background:rgba(255,255,255,.55); box-shadow:none; border-radius:3px; }
            .vj-sj-gap {
                grid-column:1 / -1; text-align:center; font-size:10px; color:rgba(255,255,255,.3);
                padding:2px 0 6px 90px;
            }
            .vj-sj-body__title { font-size:13px; font-weight:650; color:#fff; }
            .vj-sj-body__page { font-size:11px; color:rgba(255,255,255,.45); margin-top:2px; }
            .vj-sj-tag {
                display:inline-flex; margin-top:6px; border-radius:6px; border:1px solid rgba(255,255,255,.12);
                background:rgba(255,255,255,.04); color:rgba(255,255,255,.55); font-size:10px;
                font-family:ui-monospace,Menlo,monospace; padding:2px 7px;
            }
            .vj-sj-footer {
                margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,.08);
            }
            .vj-sj-path {
                display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-bottom:10px;
            }
            .vj-sj-alert {
                border:1px solid rgba(255,102,0,.35); background:rgba(255,102,0,.06);
                border-radius:8px; padding:9px 11px; font-size:12px; color:rgba(255,255,255,.7);
                display:flex; gap:8px; align-items:flex-start;
            }
            .vj-sd-sec { margin-bottom:16px; }
            .vj-sd-sec__title {
                font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
                color:rgba(255,255,255,.4); margin-bottom:8px;
            }
            .vj-export {
                display:flex; align-items:center; justify-content:center; gap:8px; width:100%;
                height:40px; border-radius:9px; border:1.5px solid #FF6600; background:transparent;
                color:#FF6600; font-size:12px; font-weight:650;
            }
            .vj-export:hover { background:rgba(255,102,0,.12); }
        </style>

        <div class="vj-page">
            <div class="vj-head">
                <div>
                    <h1 class="vj-title">
                        <span class="vj-title__muted">Analytics</span>
                        <span class="vj-title__pipe">|</span>
                        <span>Visitor Journey</span>
                    </h1>
                </div>
                <div class="vj-filters">
                    <div class="vj-filter" style="min-width:160px">
                        <select class="vj-filter__select" x-model="filters.google_ads_account_id" @change="reload()">
                            <option value="">Google Ads · All accounts</option>
                            @foreach (($googleAdsAccounts ?? []) as $account)
                                <option value="{{ $account->id }}">{{ method_exists($account, 'displayLabel') ? $account->displayLabel() : ($account->account_name ?: 'Account') }}</option>
                            @endforeach
                        </select>
                        <svg class="vj-filter__chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <button type="button" class="vj-sample" @click="filters.sample = !filters.sample; reload()">
                        <span>Sample data</span>
                        <span class="vj-toggle" :class="{ 'is-on': filters.sample }"></span>
                    </button>
                    <div class="vj-filter">
                        <select class="vj-filter__select" x-model="filters.domain_id" @change="reload()">
                            <option value="">All Domains</option>
                            @foreach ($domains as $d)
                                <option value="{{ $d->id }}">{{ $d->hostname }}</option>
                            @endforeach
                        </select>
                        <svg class="vj-filter__chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="vj-filter">
                        <select class="vj-filter__select" x-model="filters.campaign" @change="reload()">
                            <option value="">All Campaigns</option>
                            <template x-for="c in campaignOptions" :key="'c-'+c">
                                <option :value="c" x-text="c"></option>
                            </template>
                        </select>
                        <svg class="vj-filter__chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="vj-filter" style="min-width:120px">
                        <select class="vj-filter__select" x-model="filters.device" @change="reload()">
                            <option value="">All Devices</option>
                            <option value="mobile">Mobile</option>
                            <option value="desktop">Desktop</option>
                            <option value="tablet">Tablet</option>
                        </select>
                        <svg class="vj-filter__chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div
                        class="vj-filter__date-wrap"
                        x-data="figmaDateRangePicker"
                        x-init="init()"
                        @click.outside="if (calendarOpen && !isMobile()) cancelCalendar()"
                    >
                        <button type="button" class="vj-filter__date" @click="toggleCalendar()">
                            <span x-text="$root.prettyRange()"></span>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2" stroke-width="1.6"/><path stroke-width="1.6" stroke-linecap="round" d="M8 3v3M16 3v3M4 10h16"/></svg>
                        </button>
                        @include('partials.figma-date-range-popover')
                    </div>
                </div>
            </div>

            {{-- KPIs --}}
            <div class="vj-kpi-grid">
                <template x-for="kpi in kpis" :key="kpi.key">
                    <div class="vj-kpi">
                        <div class="vj-kpi__label" x-text="kpi.label"></div>
                        <div class="vj-kpi__value" x-text="kpi.display"></div>
                        <div class="vj-kpi__delta">
                            <span class="vj-kpi__delta-num" :class="deltaClass(kpi)" x-text="deltaText(kpi)"></span>
                            <span class="vj-kpi__delta-vs" x-text="kpi.vs_label || 'vs previous period'"></span>
                        </div>
                        <div class="vj-kpi__spark" x-html="sparkSvg(kpi.spark || [])"></div>
                    </div>
                </template>
            </div>

            {{-- Flow / Timeline / Sessions + detail panel --}}
            <div class="vj-main" :class="{ 'is-sessions': flowTab === 'sessions' }">
                <div class="vj-card">
                    <div class="vj-card__pad">
                        <div class="vj-card__top">
                            <div class="vj-card__title-row">
                                <span class="vj-card__icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="6" cy="12" r="2.2" stroke-width="1.8"/><circle cx="18" cy="6" r="2.2" stroke-width="1.8"/><circle cx="18" cy="18" r="2.2" stroke-width="1.8"/><path stroke-width="1.8" d="M8 12h8M16.4 7.4l-8 3.2M16.4 16.6l-8-3.2"/></svg>
                                </span>
                                <div class="vj-card__title" style="margin:0">Visitor Journey</div>
                            </div>
                            <div class="vj-mini-filters" x-show="flowTab === 'timeline'">
                                <select x-model="eventFilter" @change="selectedEvent = null">
                                    <option value="all">All events</option>
                                    <option value="page">Page view</option>
                                    <option value="scroll">Scroll</option>
                                    <option value="cta">CTA click</option>
                                    <option value="form">Form submit</option>
                                    <option value="exit">Exit</option>
                                </select>
                                <select x-model="timeScale">
                                    <option value="30">30s scale</option>
                                    <option value="15">15s scale</option>
                                    <option value="60">60s scale</option>
                                </select>
                            </div>
                        </div>

                        <div class="vj-tabs">
                            <button type="button" class="vj-tab" :class="{ 'is-active': flowTab === 'paths' }" @click="setFlowTab('paths')">Page Paths</button>
                            <button type="button" class="vj-tab" :class="{ 'is-active': flowTab === 'timeline' }" @click="setFlowTab('timeline')">Event Timeline</button>
                            <button type="button" class="vj-tab" :class="{ 'is-active': flowTab === 'sessions' }" @click="setFlowTab('sessions')">Individual Sessions</button>
                        </div>

                        <div class="vj-flow" x-show="flowTab === 'paths'" x-ref="flowBox">
                            <svg class="vj-flow__svg" x-html="flowSvg()"></svg>
                            <div class="vj-flow__cols">
                                <template x-for="col in (flow.columns || [])" :key="col.key">
                                    <div>
                                        <div class="vj-flow__col-label" x-text="col.label"></div>
                                        <template x-for="node in (col.nodes || [])" :key="node.id">
                                            <div class="vj-node" :class="nodeToneClass(node.tone)" :data-node-id="node.id">
                                                <div class="vj-node__label" x-text="node.label"></div>
                                                <div class="vj-node__meta" x-text="node.value + ' (' + Number(node.pct||0).toFixed(1) + '%)'"></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <div class="vj-empty" x-show="!(flow.columns || []).length && !loading">No journey flow for this range.</div>
                        </div>

                        {{-- Event Timeline (multi-session lanes) --}}
                        <div x-show="flowTab === 'timeline'">
                            <div class="vj-ev-legend">
                                <span class="vj-ev-legend__item"><span class="vj-ev-icon is-page"></span> Page view</span>
                                <span class="vj-ev-legend__item"><span class="vj-ev-icon is-scroll"></span> Scroll</span>
                                <span class="vj-ev-legend__item"><span class="vj-ev-icon is-cta"></span> CTA click</span>
                                <span class="vj-ev-legend__item"><span class="vj-ev-icon is-form"></span> Form submit</span>
                                <span class="vj-ev-legend__item"><span class="vj-ev-icon is-exit"></span> Exit</span>
                            </div>
                            <div class="vj-axis-label">Elapsed time from session start</div>
                            <div class="vj-et">
                                <div class="vj-et__axis">
                                    <div></div>
                                    <div class="vj-et__ticks">
                                        <template x-for="tick in timeTicks" :key="'tick-'+tick">
                                            <span x-text="tick"></span>
                                        </template>
                                    </div>
                                </div>
                                <template x-for="row in timelineSessions" :key="'lane-'+row.session_key">
                                    <div class="vj-et__row" :class="{ 'is-active': selected?.session_key === row.session_key }" @click="selectSession(row, false)">
                                        <div class="vj-et__sid">
                                            <span x-text="row.session_id"></span>
                                            <small x-text="row.duration"></small>
                                        </div>
                                        <div class="vj-et__track">
                                            <template x-for="ev in filteredEvents(row)" :key="ev.id || (ev.event + ev.elapsed_sec)">
                                                <div
                                                    class="vj-et__marker"
                                                    :class="{ 'is-selected': isEventSelected(row, ev) }"
                                                    :style="'left:' + eventLeftPct(ev) + '%'"
                                                    @click.stop="selectEvent(row, ev)"
                                                    @mouseenter="hoverEvent = { session: row.session_key, id: ev.id }"
                                                    @mouseleave="hoverEvent = null"
                                                >
                                                    <div class="vj-tooltip" x-show="hoverEvent && hoverEvent.session === row.session_key && hoverEvent.id === ev.id" x-cloak
                                                         x-text="(ev.event || ev.label) + ' • ' + (ev.elapsed || ev.elapsed_short) + ' • ' + (ev.page || '')"></div>
                                                    <div class="vj-et__m-label" x-text="ev.label"></div>
                                                    <span class="vj-ev-icon" :class="'is-' + (ev.type || 'page')"></span>
                                                    <div class="vj-et__m-time" x-text="ev.elapsed_short || ev.elapsed"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <div class="vj-empty" x-show="!timelineSessions.length">No session timelines for this range.</div>
                            </div>
                        </div>

                        <div x-show="flowTab === 'sessions'">
                            <div class="vj-is">
                                {{-- Left: sessions list --}}
                                <div class="vj-is__pane">
                                    <div class="vj-is__head">
                                        <div class="vj-is__title">Sessions</div>
                                        <div class="vj-is__count" x-text="filteredSessionList.length + ' sessions'"></div>
                                    </div>
                                    <div class="vj-is__tools">
                                        <div class="vj-is__search">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            <input type="search" x-model="sessionSearch" @input="sessionPage = 1" placeholder="Search session or device ID...">
                                        </div>
                                        <select x-model="sessionStatusFilter" @change="sessionPage = 1">
                                            <option value="all">All sessions</option>
                                            <option value="pending">Pending</option>
                                            <option value="lead">Lead confirmed</option>
                                            <option value="none">No conversion</option>
                                        </select>
                                        <select x-model="sessionSort" @change="sessionPage = 1">
                                            <option value="newest">Newest first</option>
                                            <option value="oldest">Oldest first</option>
                                            <option value="longest">Longest first</option>
                                        </select>
                                    </div>

                                    <template x-for="row in pagedSessions" :key="'sc-'+row.session_key">
                                        <button type="button" class="vj-scard" :class="{ 'is-active': selected?.session_key === row.session_key }" @click="selectSession(row, false)">
                                            <div class="vj-scard__top">
                                                <div class="vj-scard__icon" x-html="deviceIcon(row.device)"></div>
                                                <div class="vj-scard__ids">
                                                    <div class="vj-scard__sid" x-text="row.session_id"></div>
                                                    <div class="vj-scard__did" x-text="row.device_id"></div>
                                                </div>
                                                <span class="vj-pill" :class="'is-' + (row.outcome?.tone || 'none')">
                                                    <span class="vj-pill__dot"></span>
                                                    <span x-text="row.outcome?.label || '—'"></span>
                                                </span>
                                            </div>
                                            <div class="vj-scard__src">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF6600"><path d="M12 2l2.4 7.2H22l-6 4.8 2.3 7L12 16.8 5.7 21l2.3-7L2 9.2h7.6z"/></svg>
                                                <span x-text="row.campaign || row.source || 'Google Ads'"></span>
                                            </div>
                                            <div class="vj-scard__meta">
                                                <span>
                                                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" stroke-width="1.6"/><path stroke-width="1.6" d="M12 8v4l2.5 1.5"/></svg>
                                                    <span x-text="row.start_label || '—'"></span>
                                                </span>
                                                <span>
                                                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.6" d="M12 6v6l3 2"/><circle cx="12" cy="12" r="8" stroke-width="1.6"/></svg>
                                                    <span x-text="row.duration"></span>
                                                </span>
                                                <span class="ml-auto">
                                                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.6" d="M7 4h7l3 3v13H7z"/></svg>
                                                    <span x-text="(row.page_views || 0) + ' pages'"></span>
                                                </span>
                                            </div>
                                        </button>
                                    </template>
                                    <div class="vj-empty" x-show="!filteredSessionList.length">No sessions match.</div>

                                    <div class="vj-pager" x-show="filteredSessionList.length">
                                        <div x-text="sessionPageLabel"></div>
                                        <div class="vj-pager__btns">
                                            <button type="button" @click="sessionPage = Math.max(1, sessionPage - 1)">‹</button>
                                            <template x-for="p in sessionPageNumbers" :key="'pg'+p">
                                                <button type="button" :class="{ 'is-on': sessionPage === p }" @click="sessionPage = p" x-text="p"></button>
                                            </template>
                                            <button type="button" @click="sessionPage = Math.min(sessionPageCount, sessionPage + 1)">›</button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Center: session journey --}}
                                <div class="vj-is__pane">
                                    <template x-if="selected">
                                        <div>
                                            <div class="vj-sj__head">
                                                <div class="vj-sj__title">
                                                    <span>Session Journey — <span class="font-mono" x-text="selected.session_id"></span></span>
                                                    <span class="vj-chip is-none" x-text="selected.status || 'Ended'"></span>
                                                </div>
                                                <div class="vj-sj__stats">
                                                    <span>
                                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.6" d="M7 4h7l3 3v13H7z"/></svg>
                                                        <span x-text="selected.page_views || 0"></span> page views
                                                    </span>
                                                    <span>
                                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.6" d="M5 9l7 4 7-4M5 15l7 4 7-4M5 5l7 4 7-4"/></svg>
                                                        <span x-text="selected.cta_clicks || 0"></span> CTA click
                                                    </span>
                                                    <span>
                                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" stroke-width="1.6"/><path stroke-width="1.6" d="M12 8v4l2.5 1.5"/></svg>
                                                        <span x-text="selected.duration"></span>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="vj-sj-tl">
                                                <template x-for="(ev, idx) in (selected.timeline || [])" :key="'sj-'+ev.id">
                                                    <div>
                                                        <div class="vj-sj-gap" x-show="idx > 0" x-text="gapLabel(selected.timeline[idx-1], ev)"></div>
                                                        <div class="vj-sj-item">
                                                            <div class="vj-sj-time">
                                                                <div x-text="ev.time || '—'"></div>
                                                                <small x-text="'+' + (ev.elapsed || '00:00')"></small>
                                                            </div>
                                                            <div class="vj-sj-rail">
                                                                <div class="vj-sj-node" :class="{ 'is-exit': ev.type === 'exit' }"></div>
                                                            </div>
                                                            <div>
                                                                <div class="vj-sj-body__title" x-text="ev.title || ev.label"></div>
                                                                <div class="vj-sj-body__page" x-show="ev.type === 'exit'" x-text="'Last page: ' + (ev.page || selected.exit_page || '—')"></div>
                                                                <div class="vj-sj-body__page" x-show="ev.type !== 'exit'" x-text="ev.page || ''"></div>
                                                                <span class="vj-sj-tag" x-text="ev.tag || ev.event || ev.type"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>

                                            <div class="vj-sj-footer">
                                                <div class="vj-sj-path">
                                                    <template x-for="(chip, idx) in (selected.path_footer || [])" :key="'pf'+idx+chip.label">
                                                        <span>
                                                            <span class="vj-arrow" x-show="idx > 0">→</span>
                                                            <span class="vj-chip" :class="'is-' + (chip.tone || 'page')" x-text="chip.label"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                                <div class="vj-sj-alert" x-show="selected.alert">
                                                    <span>⚠️</span>
                                                    <span x-text="selected.alert"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <div class="vj-empty" x-show="!selected">Select a session from the list.</div>
                                </div>

                                {{-- Right: session details --}}
                                <div class="vj-is__pane">
                                    <template x-if="selected">
                                        <div>
                                            <div class="vj-sd-sec">
                                                <div class="vj-sd-sec__title">Session Details</div>
                                                <div class="vj-meta-grid">
                                                    <div class="vj-meta-row">
                                                        <span>Device ID</span>
                                                        <strong><span class="font-mono text-[11px]" x-text="selected.device_id"></span>
                                                            <button type="button" class="text-white/35" @click="copyText(selected.device_id)">⧉</button></strong>
                                                    </div>
                                                    <div class="vj-meta-row">
                                                        <span>Session ID</span>
                                                        <strong><span class="font-mono text-[11px]" x-text="selected.session_id"></span>
                                                            <button type="button" class="text-white/35" @click="copyText(selected.session_id)">⧉</button></strong>
                                                    </div>
                                                    <div class="vj-meta-row"><span>Device</span><strong x-text="selected.device"></strong></div>
                                                    <div class="vj-meta-row"><span>Browser / OS</span><strong x-text="(selected.browser || '—') + ' / ' + (selected.os || '—')"></strong></div>
                                                    <div class="vj-meta-row"><span>Campaign</span><strong x-text="selected.campaign || '—'"></strong></div>
                                                    <div class="vj-meta-row"><span>Landing page</span><strong x-text="selected.landing_page || '—'"></strong></div>
                                                    <div class="vj-meta-row"><span>Exit page</span><strong x-text="selected.exit_page || '—'"></strong></div>
                                                    <div class="vj-meta-row"><span>Duration</span><strong x-text="selected.duration"></strong></div>
                                                </div>
                                            </div>

                                            <div class="vj-sd-sec">
                                                <div class="vj-sd-sec__title">Engagement</div>
                                                <div class="vj-meta-grid">
                                                    <div class="vj-meta-row"><span>Page views</span><strong x-text="selected.page_views || 0"></strong></div>
                                                    <div class="vj-meta-row"><span>CTA clicks</span><strong x-text="selected.cta_clicks || 0"></strong></div>
                                                    <div class="vj-meta-row"><span>Form submissions</span><strong x-text="selected.form_submits || 0"></strong></div>
                                                </div>
                                            </div>

                                            <div class="vj-sd-sec">
                                                <div class="vj-sd-sec__title">Tracking</div>
                                                <div class="vj-meta-grid">
                                                    <div class="vj-meta-row">
                                                        <span>Google Ads click ID</span>
                                                        <strong>
                                                            <span x-show="selected.gclid_captured" style="color:#4ade80">✓ Captured</span>
                                                            <span x-show="!selected.gclid_captured" class="text-white/40">Not captured</span>
                                                        </strong>
                                                    </div>
                                                    <div class="vj-meta-row"><span>Last event</span><strong x-text="selected.last_event_time || '—'"></strong></div>
                                                </div>
                                            </div>

                                            <button type="button" class="vj-export" @click="exportSession(selected)">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                                Export Session
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Selected Session (Page Paths tab only) --}}
                <aside class="vj-detail" x-show="flowTab === 'paths' && selected" x-cloak>
                    <div class="vj-detail__head">
                        <div class="vj-detail__title">Selected Session</div>
                        <div class="text-[12px] text-white/55">Duration <span class="font-semibold text-white" x-text="selected?.duration"></span></div>
                    </div>
                    <div class="vj-meta-grid">
                        <div class="vj-meta-row">
                            <span>Device ID</span>
                            <strong>
                                <span class="font-mono text-[11px]" x-text="selected?.device_id"></span>
                                <button type="button" class="text-white/35 hover:text-white" @click="copyText(selected?.device_id)">⧉</button>
                            </strong>
                        </div>
                        <div class="vj-meta-row">
                            <span>Session ID</span>
                            <strong>
                                <span class="font-mono text-[11px]" x-text="selected?.session_id"></span>
                                <button type="button" class="text-white/35 hover:text-white" @click="copyText(selected?.session_id)">⧉</button>
                            </strong>
                        </div>
                        <div class="vj-meta-row"><span>Device</span><strong x-text="selected?.device"></strong></div>
                        <div class="vj-meta-row">
                            <span>Status</span>
                            <strong><span class="vj-status-dot"></span> <span x-text="selected?.status || 'Ended'"></span></strong>
                        </div>
                        <div class="vj-meta-row"><span>Campaign</span><strong x-text="selected?.campaign || '—'"></strong></div>
                    </div>

                    <div class="mb-2 text-[12px] font-semibold text-white">Event Timeline</div>
                    <div class="vj-timeline">
                        <template x-for="item in (selected?.timeline || [])" :key="'d-'+item.time+item.label">
                            <div class="vj-tl-item">
                                <div class="vj-tl-time" x-text="item.time || item.elapsed"></div>
                                <div>
                                    <span class="vj-tl-label" x-text="item.label"></span>
                                    <span class="vj-tl-kind" x-text="item.kind"></span>
                                </div>
                                <div class="vj-tl-note" x-show="item.note" x-text="item.note"></div>
                            </div>
                        </template>
                    </div>
                </aside>

                {{-- Selected Event (Event Timeline tab) --}}
                <aside class="vj-detail" x-show="flowTab === 'timeline'" x-cloak>
                    <div class="vj-detail__head">
                        <div class="vj-detail__title" style="display:inline-flex;align-items:center;gap:8px">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FF6600"><circle cx="12" cy="12" r="8" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke-width="1.7"/></svg>
                            Selected Event
                        </div>
                    </div>

                    <template x-if="selectedEvent">
                        <div>
                            <div class="vj-meta-grid">
                                <div class="vj-meta-row">
                                    <span>Session ID</span>
                                    <strong>
                                        <span class="font-mono text-[11px]" x-text="selected?.session_id"></span>
                                        <button type="button" class="text-white/35 hover:text-white" @click="copyText(selected?.session_id)">⧉</button>
                                    </strong>
                                </div>
                                <div class="vj-meta-row">
                                    <span>Device ID</span>
                                    <strong>
                                        <span class="font-mono text-[11px]" x-text="selected?.device_id"></span>
                                        <button type="button" class="text-white/35 hover:text-white" @click="copyText(selected?.device_id)">⧉</button>
                                    </strong>
                                </div>
                                <div class="vj-meta-row"><span>Event</span><strong class="font-mono text-[11px]" x-text="selectedEvent.event || selectedEvent.label"></strong></div>
                                <div class="vj-meta-row"><span>Time</span><strong x-text="selectedEvent.time || '—'"></strong></div>
                                <div class="vj-meta-row"><span>Elapsed Time</span><strong x-text="selectedEvent.elapsed || selectedEvent.elapsed_short"></strong></div>
                                <div class="vj-meta-row"><span>Page</span><strong x-text="selectedEvent.page || '—'"></strong></div>
                                <div class="vj-meta-row"><span>Campaign</span><strong x-text="selected?.campaign || '—'"></strong></div>
                                <div class="vj-meta-row">
                                    <span>Status</span>
                                    <strong><span class="vj-status-ok"></span> <span x-text="selectedEvent.status || selectedEvent.kind"></span></strong>
                                </div>
                            </div>

                            <div class="vj-info-box" x-show="selectedEvent.note">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><path stroke-linecap="round" stroke-width="1.6" d="M12 11v5M12 8h.01"/></svg>
                                <span x-text="selectedEvent.note"></span>
                            </div>

                            <div class="vj-seq-title">Event Sequence</div>
                            <div>
                                <template x-for="item in eventSequence" :key="'seq-'+item.id">
                                    <div class="vj-seq-item">
                                        <div class="text-[11px] text-white/40" x-text="item.elapsed || item.elapsed_short"></div>
                                        <span class="vj-ev-icon" :class="'is-' + (item.type || 'page')" style="margin-top:3px"></span>
                                        <div>
                                            <div class="text-white font-semibold" x-text="item.label"></div>
                                            <div class="text-[11px] text-white/40" x-text="item.kind || item.status"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div class="vj-empty" x-show="!selectedEvent">Click an event marker on the timeline.</div>
                </aside>
            </div>

            {{-- Widgets --}}
            <div class="vj-widgets">
                <div class="vj-widget">
                    <div class="vj-widget__title">Common Journey Paths</div>
                    <template x-for="row in commonPaths" :key="row.rank + row.path">
                        <div class="vj-path-row">
                            <div class="vj-path-rank" x-text="row.rank"></div>
                            <div class="vj-path-body" x-text="row.path"></div>
                            <div class="vj-path-meta" x-text="row.value + ' (' + Number(row.pct||0).toFixed(1) + '%)'"></div>
                        </div>
                    </template>
                    <div class="vj-empty" x-show="!commonPaths.length">No common paths yet.</div>
                </div>
                <div class="vj-widget">
                    <div class="vj-widget__title">Top Landing Pages</div>
                    <template x-for="row in landingPages" :key="'l'+row.label">
                        <div class="vj-hbar">
                            <div class="truncate" x-text="row.label"></div>
                            <div class="vj-hbar__track"><div class="vj-hbar__fill" :style="'width:' + barPct(row.value, maxLanding) + '%'"></div></div>
                            <div class="text-right text-white/45" x-text="row.value"></div>
                        </div>
                    </template>
                    <div class="vj-empty" x-show="!landingPages.length">No landing pages.</div>
                </div>
                <div class="vj-widget">
                    <div class="vj-widget__title">Top Exit Pages</div>
                    <template x-for="row in exitPages" :key="'e'+row.label">
                        <div class="vj-hbar">
                            <div class="truncate" x-text="row.label"></div>
                            <div class="vj-hbar__track"><div class="vj-hbar__fill" style="background:#ef4444" :style="'width:' + barPct(row.value, maxExit) + '%;background:#ef4444'"></div></div>
                            <div class="text-right text-white/45" x-text="row.value"></div>
                        </div>
                    </template>
                    <div class="vj-empty" x-show="!exitPages.length">No exit pages.</div>
                </div>
                <div class="vj-widget">
                    <div class="vj-widget__title">Journey Outcomes</div>
                    <div class="vj-donut-wrap">
                        <div class="vj-donut" :style="donutStyle">
                            <div class="vj-donut__hole">
                                <div class="text-[16px] font-bold text-white" x-text="fmtNum(outcomes.total || 0)"></div>
                                <div class="text-[10px] text-white/45">sessions</div>
                            </div>
                        </div>
                        <div class="vj-legend">
                            <template x-for="slice in (outcomes.slices || [])" :key="slice.key">
                                <div class="vj-legend-row">
                                    <span class="vj-legend-swatch" :style="'background:' + slice.color"></span>
                                    <span class="truncate" x-text="slice.label"></span>
                                    <span class="ml-auto text-white/40" x-text="slice.value"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent table --}}
            <div class="vj-table-card">
                <div class="vj-table-head">
                    <div class="vj-card__title" style="margin:0">Recent Visitor Journeys <span class="text-[12px] font-normal text-white/40" x-text="'(latest ' + Math.min(3, sessions.length) + ' sessions)'"></span></div>
                    <button type="button" class="vj-link" @click="setFlowTab('sessions')">View all journeys →</button>
                </div>
                <div class="vj-table-wrap">
                    <table class="vj-table">
                        <thead>
                            <tr>
                                <th>Session ID</th>
                                <th>Device ID</th>
                                <th>Journey Path</th>
                                <th>Duration</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in sessions.slice(0, 3)" :key="'t'+row.session_key">
                                <tr :class="{ 'is-selected': selected?.session_key === row.session_key }" @click="selectSession(row, false); setFlowTab('sessions')" style="cursor:pointer">
                                    <td class="font-mono text-[11px]" x-text="row.session_id"></td>
                                    <td class="font-mono text-[11px]" x-text="row.device_id"></td>
                                    <td>
                                        <div class="flex flex-wrap items-center">
                                            <template x-for="(chip, idx) in (row.path_chips || [])" :key="chip.label+idx">
                                                <span>
                                                    <span class="vj-arrow" x-show="idx > 0">→</span>
                                                    <span class="vj-chip" :class="'is-' + (chip.tone || 'page')" x-text="chip.label"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </td>
                                    <td x-text="row.duration"></td>
                                    <td><span class="vj-chip" :class="'is-' + (row.outcome?.tone || 'none')" x-text="row.outcome?.label"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="vj-empty" x-show="!loading && !sessions.length">No recent journeys.</div>
                    <div class="vj-empty" x-show="loading">Loading journeys…</div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
function visitorJourneyPage() {
    return {
        loading: false,
        filters: {
            domain_id: '',
            google_ads_account_id: '',
            campaign: '',
            device: '',
            sample: false,
            from: '',
            to: '',
        },
        flowTab: 'paths',
        eventFilter: 'all',
        timeScale: '30',
        selectedEvent: null,
        hoverEvent: null,
        sessionSearch: '',
        sessionStatusFilter: 'all',
        sessionSort: 'newest',
        sessionPage: 1,
        sessionPerPage: 5,
        kpis: [],
        flow: { columns: [], links: [] },
        commonPaths: [],
        landingPages: [],
        exitPages: [],
        outcomes: { total: 0, slices: [] },
        sessions: [],
        selected: null,
        timeline: [],
        campaignOptions: [],

        get timelineSessions() {
            return (this.sessions || []).slice(0, 8);
        },
        get timeTicks() {
            const step = Number(this.timeScale || 30);
            const maxSec = Math.max(step * 5, ...this.timelineSessions.flatMap((s) => (s.timeline || []).map((e) => Number(e.elapsed_sec || 0))), step * 5);
            const ticks = [];
            for (let s = 0; s <= maxSec + 0.1; s += step) {
                ticks.push(`${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`);
                if (ticks.length >= 8) break;
            }
            return ticks;
        },
        get timelineMaxSec() {
            const step = Number(this.timeScale || 30);
            const fromData = Math.max(0, ...this.timelineSessions.flatMap((s) => (s.timeline || []).map((e) => Number(e.elapsed_sec || 0))));
            return Math.max(step * 5, fromData, 150);
        },
        get eventSequence() {
            if (!this.selected?.timeline?.length) return [];
            const list = this.selected.timeline;
            if (!this.selectedEvent) return list.slice(0, 4);
            const idx = list.findIndex((e) => e.id === this.selectedEvent.id);
            if (idx < 0) return list.slice(0, 4);
            // nearby sequence: previous page + exit (or selected neighborhood)
            const around = [];
            if (idx > 0) around.push(list[idx - 1]);
            around.push(list[idx]);
            const exit = list.find((e) => e.type === 'exit');
            if (exit && exit.id !== this.selectedEvent.id) around.push(exit);
            return around;
        },
        get filteredSessionList() {
            let list = [...(this.sessions || [])];
            const q = String(this.sessionSearch || '').trim().toLowerCase();
            if (q) {
                list = list.filter((s) =>
                    String(s.session_id || '').toLowerCase().includes(q)
                    || String(s.device_id || '').toLowerCase().includes(q)
                    || String(s.campaign || '').toLowerCase().includes(q)
                );
            }
            if (this.sessionStatusFilter !== 'all') {
                list = list.filter((s) => (s.outcome?.tone || 'none') === this.sessionStatusFilter);
            }
            if (this.sessionSort === 'oldest') {
                list = list.slice().reverse();
            } else if (this.sessionSort === 'longest') {
                list = list.slice().sort((a, b) => this.durationSec(b.duration_raw || b.duration) - this.durationSec(a.duration_raw || a.duration));
            }
            return list;
        },
        get sessionPageCount() {
            return Math.max(1, Math.ceil(this.filteredSessionList.length / this.sessionPerPage));
        },
        get pagedSessions() {
            const start = (this.sessionPage - 1) * this.sessionPerPage;
            return this.filteredSessionList.slice(start, start + this.sessionPerPage);
        },
        get sessionPageNumbers() {
            const n = this.sessionPageCount;
            return Array.from({ length: Math.min(n, 5) }, (_, i) => i + 1);
        },
        get sessionPageLabel() {
            const total = this.filteredSessionList.length;
            if (!total) return '0 of 0 sessions';
            const start = (this.sessionPage - 1) * this.sessionPerPage + 1;
            const end = Math.min(total, this.sessionPage * this.sessionPerPage);
            return `${start}-${end} of ${total} sessions`;
        },
        get maxLanding() {
            return Math.max(1, ...(this.landingPages || []).map((r) => Number(r.value || 0)));
        },
        get maxExit() {
            return Math.max(1, ...(this.exitPages || []).map((r) => Number(r.value || 0)));
        },
        get donutStyle() {
            const slices = this.outcomes.slices || [];
            const total = Math.max(1, Number(this.outcomes.total || 0));
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
            // Default sample ON when no live preference — matches design demo until data exists.
            try {
                const pref = localStorage.getItem('promotix-vj-sample');
                if (pref === '1') this.filters.sample = true;
                if (pref === '0') this.filters.sample = false;
                if (pref === null) this.filters.sample = false;
            } catch (e) {
                this.filters.sample = false;
            }
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
        queryParams() {
            const p = new URLSearchParams();
            if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
            if (this.filters.google_ads_account_id) p.set('google_ads_account_id', this.filters.google_ads_account_id);
            if (this.filters.campaign) p.set('campaign', this.filters.campaign);
            if (this.filters.device) p.set('device', this.filters.device);
            if (this.filters.from) p.set('from', this.filters.from);
            if (this.filters.to) p.set('to', this.filters.to);
            if (this.filters.sample) p.set('sample', '1');
            return p;
        },
        async reload() {
            this.loading = true;
            try {
                localStorage.setItem('promotix-vj-sample', this.filters.sample ? '1' : '0');
            } catch (e) {}
            try {
                const res = await fetch('/bot-protection/visitor-journey/intelligence?' + this.queryParams().toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                this.kpis = data.kpis || [];
                this.flow = data.flow || { columns: [], links: [] };
                this.commonPaths = data.common_paths || [];
                this.landingPages = data.landing_pages || [];
                this.exitPages = data.exit_pages || [];
                this.outcomes = data.outcomes || { total: 0, slices: [] };
                this.sessions = data.sessions || [];
                this.campaignOptions = data.meta?.campaigns || [];
                this.selected = data.selected || this.sessions[0] || null;
                this.timeline = data.timeline || this.selected?.timeline || [];
                this.ensureSelectedEvent();
                this.$nextTick(() => { /* allow flow svg recompute */ });
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        setFlowTab(tab) {
            this.flowTab = tab;
            if (tab === 'timeline') {
                this.ensureSelectedEvent();
            }
            if (tab === 'sessions') {
                this.sessionPage = 1;
                if (!this.selected && this.sessions[0]) this.selected = this.sessions[0];
            }
        },
        ensureSelectedEvent() {
            if (!this.selected && this.sessions[0]) this.selected = this.sessions[0];
            if (!this.selected?.timeline?.length) {
                this.selectedEvent = null;
                return;
            }
            const preferred = this.selected.timeline.find((e) => e.type === 'cta')
                || this.selected.timeline.find((e) => e.type === 'form')
                || this.selected.timeline[1]
                || this.selected.timeline[0];
            this.selectedEvent = preferred || null;
        },
        selectSession(row, jumpTimeline = true) {
            this.selected = row;
            this.timeline = row.timeline || [];
            this.ensureSelectedEvent();
            if (jumpTimeline && this.flowTab !== 'sessions') this.flowTab = 'timeline';
        },
        selectEvent(row, ev) {
            this.selected = row;
            this.selectedEvent = ev;
        },
        isEventSelected(row, ev) {
            return this.selected?.session_key === row.session_key && this.selectedEvent?.id === ev.id;
        },
        filteredEvents(row) {
            const list = row.timeline || [];
            if (this.eventFilter === 'all') return list;
            return list.filter((e) => e.type === this.eventFilter);
        },
        eventLeftPct(ev) {
            const sec = Number(ev.elapsed_sec || 0);
            const max = Math.max(1, this.timelineMaxSec);
            return Math.min(98, Math.max(1, (sec / max) * 100));
        },
        durationSec(raw) {
            if (!raw) return 0;
            if (typeof raw === 'number') return raw;
            const s = String(raw);
            if (s.includes(':')) {
                const p = s.split(':').map(Number);
                if (p.length === 3) return p[0] * 3600 + p[1] * 60 + p[2];
                if (p.length === 2) return p[0] * 60 + p[1];
            }
            const m = s.match(/(\d+)\s*m/);
            const sec = s.match(/(\d+)\s*s/);
            return (m ? Number(m[1]) * 60 : 0) + (sec ? Number(sec[1]) : 0);
        },
        gapLabel(prev, next) {
            const a = Number(prev?.elapsed_sec || 0);
            const b = Number(next?.elapsed_sec || 0);
            const d = Math.max(0, b - a);
            if (d <= 0) return '';
            if (d < 60) return d + 's';
            return Math.floor(d / 60) + 'm ' + String(d % 60).padStart(2, '0') + 's';
        },
        deviceIcon(device) {
            const d = String(device || '').toLowerCase();
            if (d.includes('mobile') || d.includes('phone')) {
                return '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="7" y="3" width="10" height="18" rx="2" stroke-width="1.6"/><path stroke-width="1.6" d="M11 18h2"/></svg>';
            }
            return '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="12" rx="2" stroke-width="1.6"/><path stroke-width="1.6" d="M8 21h8M12 17v4"/></svg>';
        },
        exportSession(row) {
            if (!row) return;
            const lines = [
                ['Field', 'Value'],
                ['Session ID', row.session_id],
                ['Device ID', row.device_id],
                ['Device', row.device],
                ['Browser', row.browser],
                ['OS', row.os],
                ['Campaign', row.campaign],
                ['Landing page', row.landing_page],
                ['Exit page', row.exit_page],
                ['Duration', row.duration],
                ['Page views', row.page_views],
                ['CTA clicks', row.cta_clicks],
                ['Form submissions', row.form_submits],
                ['Outcome', row.outcome?.label || ''],
                ['GCLID captured', row.gclid_captured ? 'yes' : 'no'],
            ];
            (row.timeline || []).forEach((ev, i) => {
                lines.push([`Event ${i + 1}`, `${ev.time || ''} | ${ev.elapsed || ''} | ${ev.title || ev.label} | ${ev.page || ''} | ${ev.tag || ''}`]);
            });
            const csv = lines.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `session-${row.session_id || 'export'}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        },
        copyText(v) {
            if (!v || !navigator.clipboard) return;
            navigator.clipboard.writeText(String(v)).catch(() => {});
        },
        fmtNum(n) { return Number(n || 0).toLocaleString(); },
        barPct(value, max) {
            return Math.max(4, Math.round((Number(value || 0) / Math.max(1, Number(max || 1))) * 100));
        },
        deltaText(kpi) {
            const d = Number(kpi.delta || 0);
            return (d >= 0 ? '↑ ' : '↓ ') + Math.abs(d).toFixed(1) + '%';
        },
        deltaClass(kpi) {
            const d = Number(kpi.delta || 0);
            const badUp = !!kpi.delta_bad_when_up;
            if (badUp) return d > 0 ? 'is-down' : '';
            return d < 0 ? 'is-down' : '';
        },
        nodeToneClass(tone) {
            if (tone === 'exit') return 'is-exit';
            if (tone === 'lead') return 'is-lead';
            if (tone === 'pending') return 'is-pending';
            if (tone === 'action') return 'is-action';
            if (tone === 'form') return 'is-form';
            return '';
        },
        sparkSvg(values) {
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
            const dots = coords.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="1.6" fill="#FF6600" />`).join('');
            return `<svg width="100%" height="100%" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
                <defs><linearGradient id="vjSpark" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FF6600" stop-opacity=".35"/><stop offset="100%" stop-color="#FF6600" stop-opacity="0"/></linearGradient></defs>
                <polygon fill="url(#vjSpark)" points="${area}" />
                <polyline fill="none" stroke="#FF6600" stroke-width="1.7" points="${line}" />
                ${dots}
            </svg>`;
        },
        flowSvg() {
            const cols = this.flow.columns || [];
            const links = this.flow.links || [];
            if (!cols.length || !links.length) return '';
            // Approximate node centers in a 1000x420 viewBox (4 columns).
            const colX = [90, 340, 590, 840];
            const positions = {};
            cols.forEach((col, ci) => {
                const nodes = col.nodes || [];
                nodes.forEach((node, ni) => {
                    const y = 40 + ni * 90 + 26;
                    positions[node.id] = { x: colX[ci] ?? 90, y };
                });
            });
            const maxLink = Math.max(1, ...links.map((l) => Number(l.value || 0)));
            return links.map((link) => {
                const a = positions[link.source];
                const b = positions[link.target];
                if (!a || !b) return '';
                const mid = (a.x + b.x) / 2;
                const w = Math.max(2, (Number(link.value || 0) / maxLink) * 18);
                const d = `M ${a.x + 55} ${a.y} C ${mid} ${a.y}, ${mid} ${b.y}, ${b.x - 55} ${b.y}`;
                return `<path d="${d}" fill="none" stroke="rgba(255,102,0,0.28)" stroke-width="${w}" />`;
            }).join('');
        },
    };
}
</script>
@endsection
