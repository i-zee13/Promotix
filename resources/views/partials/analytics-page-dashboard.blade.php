{{-- Page Analytics Dashboard (included inside botProtectionFigma() — no nested x-data) --}}
@php
    $analyticsFocus = $analyticsFocus ?? 'dashboard';
    $showAll = $analyticsFocus === 'dashboard';
    $showKpis = $showAll || in_array($analyticsFocus, ['sources', 'sales'], true);
    $showPerformanceBlock = $showAll;
    $showSourcesBlock = $analyticsFocus === 'sources';
    $showJourneyBlock = $showAll || $analyticsFocus === 'journeys';
    $showTopPagesBlock = $showAll || $analyticsFocus === 'journeys';
    $showFunnelBlock = $showAll || $analyticsFocus === 'sales';
    $showReferrersBlock = $analyticsFocus === 'sources';
    $showKeywordsBlock = $showAll || $analyticsFocus === 'sources';
    $showGeoBlock = $showAll || $analyticsFocus === 'sources';
    $showDeviceBlock = $showAll || $analyticsFocus === 'sources';
    $showCostBlock = $showAll || $analyticsFocus === 'sales';
    $showSalesBlock = $analyticsFocus === 'sales';
    $showQualityBlock = $analyticsFocus === 'sales';
@endphp
<div class="pa-dash" data-analytics-focus="{{ $analyticsFocus }}">
    <style>
        .pa-dash {
            --pa-accent: #FF6600;
            --pa-accent-soft: #FFB380;
            --pa-bg: #0F0F10;
            --pa-card: #141414;
            --pa-border: rgba(255, 255, 255, 0.1);
            --pa-gutter: 12px;
            color: rgba(255, 255, 255, 0.88);
        }
        .pa-dash .pa-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: var(--pa-gutter);
            margin-bottom: 14px;
        }
        @media (max-width: 1280px) {
            .pa-dash .pa-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .pa-dash .pa-kpi-grid { grid-template-columns: 1fr; }
        }
        .pa-dash .pa-kpi {
            border-radius: 10px;
            border: 1px solid var(--pa-border);
            background:
                linear-gradient(180deg, rgba(255, 102, 0, 0.22) 0%, rgba(255, 102, 0, 0.06) 42%, #141414 78%);
            padding: 14px 14px 10px;
            min-height: 148px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28);
        }
        .pa-dash .pa-kpi__top {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .pa-dash .pa-kpi__icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: rgba(255, 102, 0, 0.22);
            color: var(--pa-accent-soft);
        }
        .pa-dash .pa-kpi__icon svg { width: 14px; height: 14px; }
        .pa-dash .pa-kpi__title {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.72);
        }
        .pa-dash .pa-kpi__value {
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .pa-dash .pa-kpi__delta {
            margin-top: 8px;
            font-size: 11px;
            font-weight: 600;
        }
        .pa-dash .pa-kpi__delta.is-up { color: #34d399; }
        .pa-dash .pa-kpi__delta.is-down { color: #f87171; }
        .pa-dash .pa-kpi__spark {
            margin-top: auto;
            padding-top: 10px;
            height: 34px;
        }
        .pa-dash .pa-kpi__spark svg {
            width: 100%;
            height: 34px;
            display: block;
        }

        .pa-dash .pa-perf { margin-bottom: 14px; }
        .pa-dash .pa-perf__sub {
            margin: -6px 0 12px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.45);
        }
        .pa-dash .pa-perf__controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .pa-dash .pa-perf__metrics { display: flex; flex-wrap: wrap; gap: 8px; }
        .pa-dash .pa-perf__metric {
            min-width: 118px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-left: 3px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.03);
            padding: 10px 12px;
            text-align: left;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.55);
            transition: border-color .15s ease, background .15s ease, color .15s ease;
        }
        .pa-dash .pa-perf__metric.is-active {
            color: rgba(255, 255, 255, 0.92);
            background: rgba(255, 255, 255, 0.06);
            border-left-color: currentColor;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04);
        }
        .pa-dash .pa-perf__metric-label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            opacity: 0.7;
        }
        .pa-dash .pa-perf__metric-value {
            display: block;
            margin-top: 4px;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .pa-dash .pa-perf__right { display: inline-flex; align-items: center; gap: 8px; }
        .pa-dash .pa-perf__select {
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: #1a1a1a;
            color: #fff;
            font-size: 11px;
            padding: 6px 10px;
        }
        .pa-dash .pa-perf__chart {
            height: 280px;
            width: 100%;
            border-radius: 10px;
            background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent 40%);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 8px 4px 0;
        }
        .pa-dash .pa-perf__chart svg { width: 100%; height: 100%; display: block; }
        .pa-dash .pa-cost__top {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        .pa-dash .pa-cost__stat span {
            display: block;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.45);
        }
        .pa-dash .pa-cost__stat strong {
            display: block;
            margin-top: 4px;
            font-size: 16px;
            color: #fff;
        }
        .pa-dash .pa-cost__main { margin-top: auto; }
        .pa-dash .pa-cost__label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.55);
        }
        .pa-dash .pa-cost__value {
            margin: 6px 0 0;
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.02em;
        }

        .pa-dash .pa-row-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--pa-gutter);
            margin-bottom: 14px;
        }
        .pa-dash .pa-row-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: var(--pa-gutter);
            margin-bottom: 14px;
            align-items: stretch;
        }
        .pa-dash .pa-row-4 > .pa-card {
            min-height: 320px;
            max-height: 320px;
            overflow: hidden;
        }
        @media (max-width: 1100px) {
            .pa-dash .pa-row-3 { grid-template-columns: 1fr; }
            .pa-dash .pa-row-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .pa-dash .pa-row-4 > .pa-card {
                max-height: 360px;
            }
        }
        @media (max-width: 720px) {
            .pa-dash .pa-row-4 { grid-template-columns: 1fr; }
            .pa-dash .pa-row-4 > .pa-card {
                max-height: none;
                min-height: 280px;
            }
        }

        .pa-dash .pa-card {
            border-radius: 10px;
            border: 1px solid var(--pa-border);
            background: var(--pa-card);
            padding: 14px 16px 16px;
            min-height: 280px;
            display: flex;
            flex-direction: column;
        }
        .pa-dash .pa-card--compact { min-height: 240px; }
        .pa-dash .pa-card__title {
            margin: 0 0 12px;
            font-size: 15px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.88);
        }
        .pa-dash .pa-card__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
        }
        .pa-dash .pa-card__head .pa-card__title { margin: 0; }

        .pa-dash .pa-kh-toggle {
            display: inline-flex;
            align-items: stretch;
            border-radius: 8px;
            border: 1px solid rgba(255, 102, 0, 0.35);
            background: rgba(255, 102, 0, 0.06);
            overflow: hidden;
        }
        .pa-dash .pa-kh-toggle__btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 0;
            background: transparent;
            color: rgba(255, 255, 255, 0.55);
            font-size: 10px;
            font-weight: 600;
            padding: 5px 10px;
            cursor: pointer;
            line-height: 1;
        }
        .pa-dash .pa-kh-toggle__btn svg {
            width: 13px;
            height: 13px;
        }
        .pa-dash .pa-kh-toggle__btn.is-active {
            background: #FF6600;
            color: #fff;
        }
        .pa-dash .pa-kh-toggle__hint {
            margin: -4px 0 10px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.45);
        }
        .pa-dash .pa-kh-combo-title {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.45);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .pa-dash .pa-donut-wrap {
            display: grid;
            grid-template-columns: 120px minmax(0, 1fr);
            gap: 18px;
            align-items: center;
            flex: 1;
            min-height: 0;
        }
        @media (max-width: 640px) {
            .pa-dash .pa-donut-wrap { grid-template-columns: 1fr; justify-items: center; }
        }
        .pa-dash .pa-donut {
            width: 120px;
            height: 120px;
            margin: 0;
            border-radius: 999px;
            position: relative;
            flex-shrink: 0;
            box-sizing: border-box;
        }
        .pa-dash .pa-donut--sm {
            width: 112px;
            height: 112px;
        }
        .pa-dash .pa-donut__hole {
            position: absolute;
            inset: 22%;
            border-radius: 999px;
            background: #141414;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            pointer-events: none;
        }
        .pa-dash .pa-donut__hole strong {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .pa-dash .pa-donut__hole span {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.45);
            margin-top: 3px;
        }
        .pa-dash .pa-legend {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
            min-width: 0;
            width: 100%;
            padding-left: 2px;
        }
        .pa-dash .pa-legend__row {
            display: grid;
            grid-template-columns: 10px minmax(0, 1fr) auto;
            align-items: center;
            column-gap: 10px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.2;
        }
        .pa-dash .pa-legend__left {
            display: contents;
        }
        .pa-dash .pa-legend__swatch {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            flex-shrink: 0;
            display: block;
            box-sizing: border-box;
        }
        .pa-dash .pa-legend__label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pa-dash .pa-legend__pct {
            font-variant-numeric: tabular-nums;
            color: rgba(255, 255, 255, 0.55);
            white-space: nowrap;
            text-align: right;
            min-width: 3.5rem;
            font-size: 12px;
        }

        .pa-dash .pa-card--device {
            display: flex;
            flex-direction: column;
        }
        .pa-dash .pa-card--device .pa-donut-wrap {
            margin-top: 4px;
        }

        .pa-dash .pa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .pa-dash .pa-table th {
            text-align: left;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.4);
            padding: 0 0 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .pa-dash .pa-table td {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.78);
        }
        .pa-dash .pa-table tr:last-child td { border-bottom: 0; }
        .pa-dash .pa-table .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .pa-dash .pa-table .muted { color: rgba(255, 255, 255, 0.45); }

        .pa-dash .pa-bars {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }
        .pa-dash .pa-bar-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
        }
        .pa-dash .pa-bar-row__label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pa-dash .pa-bar-row__meta {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.55);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            display: inline-grid;
            grid-template-columns: minmax(3.2ch, auto) auto minmax(5.2ch, auto);
            column-gap: 4px;
            align-items: baseline;
            justify-items: end;
            text-align: right;
        }
        .pa-dash .pa-bar-row__meta-count {
            justify-self: end;
            min-width: 3.2ch;
            text-align: right;
        }
        .pa-dash .pa-bar-row__meta-sep {
            opacity: 0.55;
            justify-self: center;
        }
        .pa-dash .pa-bar-row__meta-pct {
            justify-self: end;
            min-width: 5.2ch;
            text-align: right;
        }
        .pa-dash .pa-bar-track {
            grid-column: 1 / -1;
            height: 8px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            overflow: hidden;
        }
        .pa-dash .pa-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: #FF6600;
        }
        .pa-dash .pa-bar-fill.is-soft {
            background: #FF8533;
        }
        .pa-dash .pa-bar-fill.is-green { background: #22C55E; }
        .pa-dash .pa-bar-fill.is-amber { background: #F59E0B; }
        .pa-dash .pa-bar-fill.is-rose { background: #F43F5E; }

        .pa-dash .pa-funnel {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(180px, 0.9fr);
            gap: 14px;
            flex: 1;
            min-height: 0;
            align-items: stretch;
        }
        @media (max-width: 900px) {
            .pa-dash .pa-funnel { grid-template-columns: 1fr; }
        }
        .pa-dash .pa-funnel__steps {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .pa-dash .pa-funnel__row {
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr) auto auto;
            gap: 10px;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .pa-dash .pa-funnel__row:last-child { border-bottom: 0; }
        .pa-dash .pa-funnel__icon {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background: #FF6600;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .pa-dash .pa-funnel__icon svg {
            width: 14px;
            height: 14px;
        }
        .pa-dash .pa-funnel__label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.82);
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pa-dash .pa-funnel__count,
        .pa-dash .pa-funnel__pct {
            font-size: 12px;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.88);
            text-align: right;
        }
        .pa-dash .pa-funnel__count { min-width: 5.5ch; }
        .pa-dash .pa-funnel__pct {
            min-width: 5.2ch;
            color: rgba(255, 255, 255, 0.55);
            font-weight: 500;
        }
        .pa-dash .pa-funnel__side {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
        }
        .pa-dash .pa-funnel__box {
            border: 1px solid rgba(255, 102, 0, 0.35);
            border-radius: 10px;
            background: rgba(255, 102, 0, 0.06);
            padding: 12px;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
        .pa-dash .pa-funnel__box-label {
            display: block;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.45);
            margin-bottom: 6px;
        }
        .pa-dash .pa-funnel__box-value {
            display: block;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            line-height: 1.15;
            font-variant-numeric: tabular-nums;
        }
        .pa-dash .pa-funnel__box-delta {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .pa-dash .pa-funnel__box-delta.is-up { color: #34d399; }
        .pa-dash .pa-funnel__box-delta.is-down { color: #f87171; }
        .pa-dash .pa-funnel__box-spark {
            margin-top: auto;
            padding-top: 10px;
            min-height: 42px;
        }
        .pa-dash .pa-funnel__box-spark svg {
            width: 100%;
            height: 42px;
            display: block;
        }
        .pa-dash .pa-funnel__metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .pa-dash .pa-funnel__metric-label {
            display: block;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.45);
            margin-bottom: 4px;
        }
        .pa-dash .pa-funnel__metric-value {
            display: block;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }
        html.light-mode .pa-dash .pa-funnel__row {
            border-bottom-color: rgba(0, 0, 0, 0.08);
        }
        html.light-mode .pa-dash .pa-funnel__label,
        html.light-mode .pa-dash .pa-funnel__count {
            color: #1a1a1a;
        }
        html.light-mode .pa-dash .pa-funnel__pct,
        html.light-mode .pa-dash .pa-funnel__box-label,
        html.light-mode .pa-dash .pa-funnel__metric-label {
            color: #6b6280 !important;
        }
        html.light-mode .pa-dash .pa-funnel__box {
            background: rgba(255, 102, 0, 0.08);
            border-color: rgba(255, 102, 0, 0.3);
        }
        html.light-mode .pa-dash .pa-funnel__box-value,
        html.light-mode .pa-dash .pa-funnel__metric-value {
            color: #1a1a1a !important;
        }

        .pa-dash .pa-inset {
            margin-top: 14px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 102, 0, 0.28);
            background: rgba(255, 102, 0, 0.08);
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        @media (max-width: 720px) {
            .pa-dash .pa-inset { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .pa-dash .pa-inset { grid-template-columns: 1fr; }
        }
        .pa-dash .pa-inset__label {
            display: block;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.45);
            margin-bottom: 4px;
        }
        .pa-dash .pa-inset__value {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }

        .pa-dash .pa-split-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            flex: 1;
            min-height: 0;
        }
        @media (max-width: 640px) {
            .pa-dash .pa-split-2 { grid-template-columns: 1fr; }
        }
        .pa-dash .pa-split-2 h3 {
            margin: 0 0 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.5);
        }
        .pa-dash .pa-list-item {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 8px;
        }
        .pa-dash .pa-list-item span:last-child {
            color: rgba(255, 255, 255, 0.5);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .pa-dash .pa-geo {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
            min-height: 0;
        }
        .pa-dash .pa-geo__list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 102, 0, 0.45) rgba(255, 255, 255, 0.06);
        }
        .pa-dash .pa-geo__list::-webkit-scrollbar {
            width: 6px;
        }
        .pa-dash .pa-geo__list::-webkit-scrollbar-thumb {
            background: rgba(255, 102, 0, 0.45);
            border-radius: 999px;
        }
        .pa-dash .pa-geo__list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.06);
            border-radius: 999px;
        }
        .pa-dash .pa-geo__map {
            position: relative;
            height: 140px;
            flex: 0 0 auto;
            border-radius: 8px;
            border: none;
            background:
                radial-gradient(ellipse at 22% 38%, rgba(255, 102, 0, 0.1), transparent 42%),
                linear-gradient(180deg, #171717 0%, #101010 100%);
            overflow: hidden;
            margin-bottom: 4px;
        }
        .pa-dash .pa-geo__map svg.pa-geo__world {
            width: 100%;
            height: 100%;
            display: block;
        }
        .pa-dash .pa-geo__world .pa-geo__land,
        .pa-dash .pa-geo__world path {
            fill: rgba(255, 255, 255, 0.08);
            stroke: rgba(255, 255, 255, 0.16);
            stroke-width: 0.35;
            transition: fill 0.2s ease;
        }
        .pa-dash .pa-geo__world .is-cool .pa-geo__land,
        .pa-dash .pa-geo__world .is-cool.pa-geo__land,
        .pa-dash .pa-geo__world path.is-cool {
            fill: rgba(255, 102, 0, 0.28);
            stroke: rgba(255, 102, 0, 0.4);
        }
        .pa-dash .pa-geo__world .is-warm .pa-geo__land,
        .pa-dash .pa-geo__world .is-warm.pa-geo__land,
        .pa-dash .pa-geo__world path.is-warm {
            fill: rgba(255, 102, 0, 0.5);
            stroke: rgba(255, 179, 128, 0.55);
        }
        .pa-dash .pa-geo__world .is-hot .pa-geo__land,
        .pa-dash .pa-geo__world .is-hot.pa-geo__land,
        .pa-dash .pa-geo__world path.is-hot {
            fill: rgba(255, 102, 0, 0.82);
            stroke: rgba(255, 179, 128, 0.75);
        }
        html.light-mode .pa-dash .pa-geo__map {
            border: none !important;
            background:
                radial-gradient(ellipse at 22% 38%, rgba(255, 102, 0, 0.1), transparent 42%),
                linear-gradient(180deg, #f4f4f5 0%, #e8e8ea 100%) !important;
        }
        html.light-mode .pa-dash .pa-geo__world .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path {
            fill: rgba(0, 0, 0, 0.1);
            stroke: rgba(0, 0, 0, 0.16);
        }
        /* Must beat base light-mode path fill (selected countries) */
        html.light-mode .pa-dash .pa-geo__world .is-cool .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world .is-cool.pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path.is-cool {
            fill: rgba(255, 102, 0, 0.35) !important;
            stroke: rgba(255, 102, 0, 0.5) !important;
        }
        html.light-mode .pa-dash .pa-geo__world .is-warm .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world .is-warm.pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path.is-warm {
            fill: rgba(255, 102, 0, 0.58) !important;
            stroke: rgba(194, 65, 12, 0.55) !important;
        }
        html.light-mode .pa-dash .pa-geo__world .is-hot .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world .is-hot.pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path.is-hot {
            fill: rgba(255, 102, 0, 0.88) !important;
            stroke: rgba(194, 65, 12, 0.7) !important;
        }
        .pa-dash .pa-geo__legend {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 9px;
            color: rgba(255, 255, 255, 0.45);
            margin-top: 2px;
        }
        .pa-dash .pa-geo__legend i {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 2px;
            background: #FF6600;
        }
        .pa-dash .pa-geo__row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 8px;
            align-items: center;
        }
        .pa-dash .pa-geo__flag {
            width: 16px;
            height: 11px;
            border-radius: 2px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .pa-dash .pa-geo__flag-fallback {
            width: 16px;
            height: 11px;
            border-radius: 2px;
            background: rgba(255, 255, 255, 0.12);
            flex-shrink: 0;
        }
        .pa-dash .pa-geo__name {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.75);
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pa-dash .pa-geo__meta {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
            white-space: nowrap;
        }
        .pa-dash .pa-geo .pa-bar-track {
            grid-column: 1 / -1;
            height: 6px;
        }
        .pa-dash .pa-revenue-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        .pa-dash .pa-revenue-stats div {
            background: rgba(255, 102, 0, 0.08);
            border: 1px solid rgba(255, 102, 0, 0.2);
            border-radius: 8px;
            padding: 8px;
        }
        .pa-dash .pa-revenue-stats span {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.45);
        }
        .pa-dash .pa-revenue-stats strong {
            display: block;
            margin-top: 4px;
            font-size: 13px;
            color: #fff;
            font-variant-numeric: tabular-nums;
        }
        .pa-dash .pa-revenue-spark {
            flex: 1;
            min-height: 88px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .pa-dash .pa-revenue-spark svg {
            width: 100%;
            height: 120px;
            display: block;
        }

        .pa-dash .pa-quality {
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
            min-height: 0;
        }
        .pa-dash .pa-quality__badge {
            flex: 0 0 auto;
            min-width: 92px;
            max-width: 110px;
            padding: 8px 10px;
            height: auto;
            border-radius: 10px;
            border: 1px solid rgba(255, 102, 0, 0.4);
            background: rgba(255, 102, 0, 0.12);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            margin: 0;
            box-sizing: border-box;
        }
        .pa-dash .pa-quality__badge strong {
            display: block;
            width: 100%;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            line-height: 1.15;
            text-align: center;
        }
        .pa-dash .pa-quality__badge span {
            display: block;
            width: 100%;
            font-size: 7px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--pa-accent-soft);
            margin-top: 4px;
            line-height: 1.25;
            text-align: center;
            white-space: normal;
        }
        .pa-dash .pa-empty {
            margin: 0;
            padding: 16px 0;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.45);
            text-align: center;
        }

        /* Light mode: dark text on peach/orange panels (readable contrast) */
        html.light-mode .pa-dash .pa-inset__label,
        html.light-mode .pa-dash .pa-revenue-stats span {
            color: #9a3412 !important;
        }
        html.light-mode .pa-dash .pa-inset__value,
        html.light-mode .pa-dash .pa-revenue-stats strong,
        html.light-mode .pa-dash .pa-quality__badge strong {
            color: #1a1a1a !important;
        }
        html.light-mode .pa-dash .pa-quality__badge span {
            color: #c2410c !important;
        }
        html.light-mode .pa-dash .pa-legend__pct,
        html.light-mode .pa-dash .pa-bar-row__label,
        html.light-mode .pa-dash .pa-bar-row__meta,
        html.light-mode .pa-dash .pa-list-item,
        html.light-mode .pa-dash .pa-list-item span:last-child,
        html.light-mode .pa-dash .pa-split-2 h3,
        html.light-mode .pa-dash .pa-geo__name,
        html.light-mode .pa-dash .pa-geo__meta,
        html.light-mode .pa-dash .pa-geo__legend,
        html.light-mode .pa-dash .pa-donut__hole span {
            color: #5c5470 !important;
        }
        html.light-mode .pa-dash .pa-geo__map {
            border: none !important;
            background:
                radial-gradient(ellipse at 22% 38%, rgba(255, 102, 0, 0.12), transparent 42%),
                linear-gradient(180deg, #f4f4f5 0%, #e8e8ea 100%) !important;
        }
        html.light-mode .pa-dash .pa-geo__world .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path {
            fill: rgba(0, 0, 0, 0.1);
            stroke: rgba(0, 0, 0, 0.16);
        }
        html.light-mode .pa-dash .pa-geo__world .is-cool .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world .is-cool.pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path.is-cool {
            fill: rgba(255, 102, 0, 0.35) !important;
            stroke: rgba(255, 102, 0, 0.5) !important;
        }
        html.light-mode .pa-dash .pa-geo__world .is-warm .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world .is-warm.pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path.is-warm {
            fill: rgba(255, 102, 0, 0.58) !important;
            stroke: rgba(194, 65, 12, 0.55) !important;
        }
        html.light-mode .pa-dash .pa-geo__world .is-hot .pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world .is-hot.pa-geo__land,
        html.light-mode .pa-dash .pa-geo__world path.is-hot {
            fill: rgba(255, 102, 0, 0.88) !important;
            stroke: rgba(194, 65, 12, 0.7) !important;
        }
    </style>

    {{-- Row 1: KPI cards --}}
    @if ($showKpis)
    <div class="pa-kpi-grid">
        <template x-for="card in pageAnalyticsKpis()" :key="card.key || card.title">
            <article class="pa-kpi">
                <div class="pa-kpi__top">
                    <span class="pa-kpi__icon" x-html="card.icon"></span>
                    <p class="pa-kpi__title" x-text="card.title"></p>
                </div>
                <p class="pa-kpi__value" x-text="card.value"></p>
                <p
                    class="pa-kpi__delta"
                    :class="Number(card.delta || 0) >= 0 ? 'is-up' : 'is-down'"
                    x-text="card.deltaLabel || formatDelta(card.delta)"
                ></p>
                <div class="pa-kpi__spark" aria-hidden="true" x-html="sparkSvg(card.spark, '#FF6600')"></div>
            </article>
        </template>
    </div>
    @endif

    {{-- Row 2: Performance Over Time --}}
    @if ($showPerformanceBlock)
    <section class="pa-card pa-perf">
        <div class="pa-card__head">
            <h2 class="pa-card__title">Performance Over Time</h2>
            <div class="pa-perf__right">
                <span class="text-[11px] text-white/45" x-text="(pagePerformance()?.granularity === 'daily') ? 'Daily' : 'Hourly'"></span>
                <button type="button" class="pa-kh-toggle__btn" :class="perfMode === 'line' ? 'is-active' : ''" @click="perfMode = 'line'">Line</button>
                <button type="button" class="pa-kh-toggle__btn" :class="perfMode === 'bar' ? 'is-active' : ''" @click="perfMode = 'bar'">Bar</button>
            </div>
        </div>
        <p class="pa-perf__sub">Real-time insights and performance overview.</p>
        <div class="pa-perf__controls">
            <div class="pa-perf__metrics">
                <template x-for="series in pagePerformanceSeries()" :key="series.key">
                    <button
                        type="button"
                        class="pa-perf__metric"
                        :class="isPerfSeriesActive(series.key) ? 'is-active' : ''"
                        :style="isPerfSeriesActive(series.key) ? `color:${series.color}` : ''"
                        @click="togglePerfSeries(series.key)"
                    >
                        <span class="pa-perf__metric-label" x-text="series.label"></span>
                        <span class="pa-perf__metric-value" x-text="fmt(series.total || 0)"></span>
                    </button>
                </template>
            </div>
        </div>
        <div class="pa-perf__chart" aria-hidden="true" x-html="performanceChartSvg(perfMode, (perfActiveSeries || []).join(','))"></div>
        <p x-show="!(pagePerformanceSeries() || []).length" class="pa-empty">No performance data in this window.</p>
    </section>
    @endif

    {{-- Row 3: Funnel / Journey / Top Pages --}}
    @if ($showFunnelBlock || $showJourneyBlock || $showTopPagesBlock)
    <div class="pa-row-3">
        @if ($showFunnelBlock)
        <section class="pa-card">
            <div class="pa-card__head">
                <h2 class="pa-card__title">Conversion Funnel</h2>
            </div>
            <div class="pa-funnel" x-show="(pageFunnel() || []).length">
                <div class="pa-funnel__steps">
                    <template x-for="row in pageFunnel()" :key="row.key || row.label">
                        <div class="pa-funnel__row">
                            <span class="pa-funnel__icon" x-html="funnelStepIcon(row.key || row.label)"></span>
                            <span class="pa-funnel__label" x-text="row.label"></span>
                            <span class="pa-funnel__count" x-text="fmt(row.value)"></span>
                            <span class="pa-funnel__pct" x-text="Number(row.pct != null ? row.pct : 0).toFixed(1) + '%'"></span>
                        </div>
                    </template>
                </div>
                <div class="pa-funnel__side" x-show="pageConversionSummary()">
                    <div class="pa-funnel__box">
                        <span class="pa-funnel__box-label">Overall Conversion Rate</span>
                        <span class="pa-funnel__box-value" x-text="pageConversionSummary()?.rate || '0.00%'"></span>
                        <span
                            class="pa-funnel__box-delta"
                            :class="Number(pageAnalytics?.kpis?.deltas?.conversion_rate || 0) >= 0 ? 'is-up' : 'is-down'"
                            x-text="formatDelta(pageAnalytics?.kpis?.deltas?.conversion_rate || 0)"
                        ></span>
                        <div class="pa-funnel__box-spark" aria-hidden="true" x-html="sparkSvg(pageRevenueSpark(), '#FF6600')"></div>
                    </div>
                    <div class="pa-funnel__box">
                        <div class="pa-funnel__metrics">
                            <div>
                                <span class="pa-funnel__metric-label">Revenue</span>
                                <span class="pa-funnel__metric-value" x-text="pageConversionSummary()?.revenue || ((pageAnalytics?.currency_symbol || '$') + '0.00')"></span>
                            </div>
                            <div>
                                <span class="pa-funnel__metric-label">Transactions</span>
                                <span class="pa-funnel__metric-value" x-text="pageConversionSummary()?.transactions || '0'"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p x-show="!(pageFunnel() || []).length" class="pa-empty">No funnel steps in this window.</p>
        </section>
        @endif

        @if ($showJourneyBlock)
        <section class="pa-card">
            <h2 class="pa-card__title" id="journey">Visitor Journey Summary</h2>
            <div class="overflow-x-auto">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Step</th>
                            <th class="num">Visitors</th>
                            <th class="num">Drop-off</th>
                            <th class="num">Avg time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in pageJourney()" :key="row.key || row.label || row.step">
                            <tr>
                                <td x-text="row.label || row.step"></td>
                                <td class="num" x-text="fmt(row.visitors ?? row.value)"></td>
                                <td class="num muted" x-text="(row.dropoff != null ? row.dropoff : Math.max(0, 100 - Number(row.pct || 0)).toFixed(0)) + '%'"></td>
                                <td class="num muted" x-text="row.avg_time || '0:45'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!(pageJourney() || []).length" class="pa-empty">No journey data in this window.</p>
                <p x-show="(pageJourney() || []).length" class="mt-[10px] text-[11px] text-white/55">
                    Avg. Session Duration:
                    <strong class="text-[#FFB380]" x-text="pageJourneySummary()?.avg_session_duration || '00:00:00'"></strong>
                </p>
            </div>
        </section>
        @endif

        @if ($showTopPagesBlock)
        <section class="pa-card">
            <h2 class="pa-card__title">Top Pages</h2>
            <div class="overflow-x-auto">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th class="num">Visitors</th>
                            <th class="num">Views</th>
                            <th class="num">Conv.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in pageTopPages()" :key="row.key || row.path || row.page">
                            <tr>
                                <td x-text="row.path || row.page || row.label"></td>
                                <td class="num" x-text="fmt(row.visitors ?? row.value ?? 0)"></td>
                                <td class="num" x-text="fmt(row.views ?? row.visitors ?? row.value)"></td>
                                <td class="num muted" x-text="row.conversions != null ? fmt(row.conversions) : Math.round(Number(row.views || 0) * 0.03)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!(pageTopPages() || []).length" class="pa-empty">No page data in this window.</p>
            </div>
        </section>
        @endif
    </div>
    @endif

    @if ($showSourcesBlock)
    <div class="pa-row-3">
        <section class="pa-card" id="sources">
            <h2 class="pa-card__title">Traffic Source Overview</h2>
            <div class="pa-donut-wrap">
                <div class="pa-donut" role="img" aria-label="Traffic sources"
                    :style="(rows => {
                        const list = rows || [];
                        const total = list.reduce((a, r) => a + Number(r.value || 0), 0);
                        if (!total) return { background: 'conic-gradient(rgba(255,255,255,0.15) 0 100%)' };
                        let deg = 0;
                        const stops = list.map(r => {
                            const span = (Number(r.value || 0) / total) * 360;
                            const start = deg; deg += span;
                            return `${r.color || '#FF6600'} ${start}deg ${deg}deg`;
                        });
                        return { background: `conic-gradient(${stops.join(', ')})` };
                    })(pageTrafficSources())">
                    <div class="pa-donut__hole">
                        <strong x-text="fmt((pageTrafficSources() || []).reduce((a, r) => a + Number(r.value || 0), 0))"></strong>
                        <span>Total Visitors</span>
                    </div>
                </div>
                <div class="pa-legend">
                    <template x-for="row in pageTrafficSources()" :key="row.key || row.label">
                        <div class="pa-legend__row">
                            <span class="pa-legend__swatch" :style="`background:${row.color || '#FF6600'}`" aria-hidden="true"></span>
                            <span class="pa-legend__label" x-text="row.label"></span>
                            <span class="pa-legend__pct" x-text="Number(row.pct != null ? row.pct : 0).toFixed(1) + '%'"></span>
                        </div>
                    </template>
                    <p x-show="!(pageTrafficSources() || []).length" class="pa-empty">No traffic sources in this window.</p>
                </div>
            </div>
        </section>
        @if ($showReferrersBlock)
        <section class="pa-card">
            <h2 class="pa-card__title">Referrer / Platform Breakdown</h2>
            <div class="pa-bars">
                <template x-for="row in pageReferrers()" :key="row.key || row.label">
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label" x-text="row.label"></span>
                        <span class="pa-bar-row__meta" x-text="`${fmt(row.value)} · ${row.pct != null ? row.pct : 0}%`"></span>
                        <div class="pa-bar-track">
                            <div class="pa-bar-fill is-soft" :style="`width:${row.bar != null ? row.bar : Math.max(4, Number(row.pct || 0))}%`"></div>
                        </div>
                    </div>
                </template>
                <p x-show="!(pageReferrers() || []).length" class="pa-empty">No referrer data in this window.</p>
            </div>
        </section>
        @endif
    </div>
    @endif

    @if ($showKeywordsBlock || $showGeoBlock || $showDeviceBlock || $showCostBlock || $showSalesBlock || $showQualityBlock)
    <div class="pa-row-4">
        @if ($showKeywordsBlock)
        <section class="pa-card pa-card--compact">
            <div class="pa-card__head">
                <h2 class="pa-card__title">Keyword Performance</h2>
                <div class="pa-kh-toggle" role="group" aria-label="Keyword source">
                    <button type="button" class="pa-kh-toggle__btn" :class="keywordHeadlineSource === 'ads' ? 'is-active' : ''" @click="keywordHeadlineSource = 'ads'">Ads</button>
                    <button type="button" class="pa-kh-toggle__btn" :class="keywordHeadlineSource === 'site' ? 'is-active' : ''" @click="keywordHeadlineSource = 'site'">Website</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Keyword</th>
                            <th class="num">Clicks</th>
                            <th class="num">Conversions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in pageKeywords()" :key="row.key || row.keyword || row.label">
                            <tr>
                                <td x-text="row.keyword || row.label"></td>
                                <td class="num" x-text="fmt(row.clicks ?? row.value ?? 0)"></td>
                                <td class="num" x-text="fmt(row.conversions ?? 0)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!(pageKeywords() || []).length" class="pa-empty" x-text="keywordHeadlineSource === 'ads' ? 'No ad keywords.' : 'No on-site keywords.'"></p>
            </div>
        </section>
        @endif

        @if ($showGeoBlock)
        <section class="pa-card pa-card--compact">
            <h2 class="pa-card__title">Geography</h2>
            <p class="pa-kh-toggle__hint">Ads campaign visitors only</p>
            <div class="pa-geo">
                <div class="pa-geo__map" role="img" aria-label="World map traffic chart">
                    @include('partials.analytics-world-map')
                </div>
                <div class="pa-geo__legend" x-show="(pageGeo() || []).length">
                    <i></i>
                    <span>Ads visit intensity by country</span>
                </div>
                <div class="pa-geo__list" x-show="(pageGeo() || []).length">
                    <template x-for="row in pageGeo()" :key="row.key || row.code || row.country || row.name">
                        <div class="pa-geo__row">
                            <img class="pa-geo__flag" x-show="typeof countryFlagUrl === 'function' && countryFlagUrl(row.code || row.country || row.name)" :src="typeof countryFlagUrl === 'function' ? countryFlagUrl(row.code || row.country || row.name) : ''" :alt="row.name || row.country || ''">
                            <span class="pa-geo__flag-fallback" x-show="!(typeof countryFlagUrl === 'function' && countryFlagUrl(row.code || row.country || row.name))"></span>
                            <span class="pa-geo__name" x-text="row.name || row.country || row.label || row.code"></span>
                            <span class="pa-geo__meta" x-text="`${row.pct != null ? row.pct : 0}%`"></span>
                        </div>
                    </template>
                </div>
                <p x-show="!(pageGeo() || []).length" class="pa-empty">No ads geography data in this window.</p>
            </div>
        </section>
        @endif

        @if ($showDeviceBlock)
        <section class="pa-card pa-card--compact pa-card--device">
            <h2 class="pa-card__title">Device Breakdown</h2>
            <div class="pa-donut-wrap">
                <div class="pa-donut" role="img" aria-label="Devices"
                    :style="(rows => {
                        const list = rows || [];
                        const total = list.reduce((a, r) => a + Number(r.value || 0), 0);
                        if (!total) return { background: 'conic-gradient(rgba(255,255,255,0.15) 0 100%)' };
                        let deg = 0;
                        const stops = list.map(r => {
                            const span = (Number(r.value || 0) / total) * 360;
                            const start = deg; deg += span;
                            return `${r.color || '#FF6600'} ${start}deg ${deg}deg`;
                        });
                        return { background: `conic-gradient(${stops.join(', ')})` };
                    })(pageDevices())">
                    <div class="pa-donut__hole">
                        <strong x-text="fmt((pageDevices() || []).reduce((a, r) => a + Number(r.value || 0), 0))"></strong>
                        <span>Visitors</span>
                    </div>
                </div>
                <div class="pa-legend">
                    <template x-for="row in pageDevices()" :key="row.key || row.label">
                        <div class="pa-legend__row">
                            <span class="pa-legend__swatch" :style="`background:${row.color || '#FF6600'}`" aria-hidden="true"></span>
                            <span class="pa-legend__label" x-text="row.label"></span>
                            <span class="pa-legend__pct" x-text="Number(row.pct != null ? row.pct : 0).toFixed(1) + '%'"></span>
                        </div>
                    </template>
                </div>
            </div>
        </section>
        @endif

        @if ($showCostBlock)
        <section class="pa-card pa-card--compact">
            <h2 class="pa-card__title">Cost per Conversion</h2>
            <div class="pa-cost__top">
                <div class="pa-cost__stat">
                    <span>Avg. CPC</span>
                    <strong x-text="pageCost()?.avg_cpc_label || ((pageAnalytics?.currency_symbol || '$') + '0.00')"></strong>
                </div>
                <div class="pa-cost__stat">
                    <span>Total Cost</span>
                    <strong x-text="pageCost()?.total_cost_label || ((pageAnalytics?.currency_symbol || '$') + '0.00')"></strong>
                </div>
            </div>
            <div class="pa-cost__main">
                <span class="pa-cost__label">Cost per Conversion</span>
                <p class="pa-cost__value" x-text="pageCost()?.cost_per_conversion_label || ((pageAnalytics?.currency_symbol || '$') + '0.00')"></p>
                <p class="pa-kpi__delta" :class="Number(pageCost()?.delta || 0) >= 0 ? 'is-up' : 'is-down'" x-text="formatDelta(pageCost()?.delta || 0)"></p>
                <div class="pa-kpi__spark" aria-hidden="true" x-html="sparkSvg(pageRevenueSpark(), '#FF6600')"></div>
            </div>
        </section>
        @endif

        @if ($showSalesBlock)
        <section class="pa-card pa-card--compact" id="sales">
            <h2 class="pa-card__title">Sales &amp; Revenue</h2>
            <div class="pa-revenue-stats" x-show="pageConversionSummary()">
                <div><span>Revenue</span><strong x-text="pageConversionSummary()?.revenue || ((pageAnalytics?.currency_symbol || '$') + '0.00')"></strong></div>
                <div><span>Transactions</span><strong x-text="pageConversionSummary()?.transactions || '0'"></strong></div>
                <div><span>AOV</span><strong x-text="pageConversionSummary()?.aov || ((pageAnalytics?.currency_symbol || '$') + '0.00')"></strong></div>
            </div>
            <div class="pa-revenue-spark" aria-hidden="true" x-html="sparkSvg(pageRevenueSpark(), '#FF6600')"></div>
        </section>
        @endif

        @if ($showQualityBlock)
        <section class="pa-card pa-card--compact">
            <div class="pa-card__head">
                <h2 class="pa-card__title">Quality Signals</h2>
                <div class="pa-quality__badge" x-show="pageQuality()">
                    <strong x-text="(pageQuality()?.score ?? '—') + '/100'"></strong>
                    <span x-text="pageQuality()?.label || 'Score'"></span>
                </div>
            </div>
            <div class="pa-quality" x-show="pageQuality()">
                <div class="pa-bars">
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label">Human Visitors</span>
                        <span class="pa-bar-row__meta">
                            <span class="pa-bar-row__meta-count" x-text="fmt(pageQuality()?.human_count || 0)"></span>
                            <span class="pa-bar-row__meta-sep">·</span>
                            <span class="pa-bar-row__meta-pct" x-text="Number(pageQuality()?.human || 0).toFixed(1) + '%'"></span>
                        </span>
                        <div class="pa-bar-track"><div class="pa-bar-fill is-green" :style="`width:${Math.max(4, Number(pageQuality()?.human || 0))}%`"></div></div>
                    </div>
                </div>
            </div>
            <p x-show="!pageQuality()" class="pa-empty">No quality signals.</p>
        </section>
        @endif
    </div>
    @endif
</div>
