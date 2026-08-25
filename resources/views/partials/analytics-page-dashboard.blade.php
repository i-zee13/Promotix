{{-- Page Analytics Dashboard (included inside botProtectionFigma() — no nested x-data) --}}
@php
    $analyticsFocus = $analyticsFocus ?? 'dashboard';
    $showAll = $analyticsFocus === 'dashboard';
    $showKpis = $showAll || in_array($analyticsFocus, ['sources', 'sales'], true);
    $showSourcesBlock = $showAll || $analyticsFocus === 'sources';
    $showJourneyBlock = $showAll || $analyticsFocus === 'journeys';
    $showTopPagesBlock = $showAll || $analyticsFocus === 'journeys';
    $showFunnelBlock = $showAll || $analyticsFocus === 'sales';
    $showReferrersBlock = $showAll || $analyticsFocus === 'sources';
    $showKeywordsBlock = $showAll || $analyticsFocus === 'sources';
    $showGeoBlock = $showAll || $analyticsFocus === 'sources';
    $showDeviceBlock = $showAll || $analyticsFocus === 'sources';
    $showSalesBlock = $showAll || $analyticsFocus === 'sales';
    $showQualityBlock = $showAll || $analyticsFocus === 'sales';
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
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: var(--pa-gutter);
            margin-bottom: 14px;
        }
        @media (max-width: 1280px) {
            .pa-dash .pa-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
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
        }
        @media (max-width: 1100px) {
            .pa-dash .pa-row-3 { grid-template-columns: 1fr; }
            .pa-dash .pa-row-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .pa-dash .pa-row-4 { grid-template-columns: 1fr; }
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

        .pa-dash .pa-donut-wrap {
            display: grid;
            grid-template-columns: 140px minmax(0, 1fr);
            gap: 14px;
            align-items: center;
            flex: 1;
        }
        @media (max-width: 640px) {
            .pa-dash .pa-donut-wrap { grid-template-columns: 1fr; }
        }
        .pa-dash .pa-donut {
            width: 140px;
            height: 140px;
            margin: 0 auto;
            border-radius: 999px;
            position: relative;
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
        }
        .pa-dash .pa-donut__hole strong {
            font-size: 16px;
            color: #fff;
            line-height: 1.1;
        }
        .pa-dash .pa-donut__hole span {
            font-size: 9px;
            color: rgba(255, 255, 255, 0.45);
            margin-top: 2px;
        }
        .pa-dash .pa-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }
        .pa-dash .pa-legend__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
        }
        .pa-dash .pa-legend__left {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
        }
        .pa-dash .pa-legend__swatch {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            flex-shrink: 0;
        }
        .pa-dash .pa-legend__pct {
            font-variant-numeric: tabular-nums;
            color: rgba(255, 255, 255, 0.55);
            white-space: nowrap;
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
            background: linear-gradient(90deg, #FF6600, #FFB380);
        }
        .pa-dash .pa-bar-fill.is-soft {
            background: linear-gradient(90deg, rgba(255, 102, 0, 0.55), rgba(255, 179, 128, 0.75));
        }
        .pa-dash .pa-bar-fill.is-green { background: linear-gradient(90deg, #059669, #34d399); }
        .pa-dash .pa-bar-fill.is-amber { background: linear-gradient(90deg, #d97706, #fbbf24); }
        .pa-dash .pa-bar-fill.is-rose { background: linear-gradient(90deg, #e11d48, #fb7185); }

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
        }
        .pa-dash .pa-geo__map {
            position: relative;
            height: 110px;
            border-radius: 8px;
            border: 1px solid rgba(255, 102, 0, 0.22);
            background:
                radial-gradient(ellipse at 30% 40%, rgba(255, 102, 0, 0.18), transparent 50%),
                radial-gradient(ellipse at 70% 55%, rgba(59, 130, 246, 0.12), transparent 48%),
                linear-gradient(180deg, #1a1a1a, #101010);
            overflow: hidden;
            margin-bottom: 6px;
        }
        .pa-dash .pa-geo__map svg {
            width: 100%;
            height: 100%;
            display: block;
            opacity: 0.85;
        }
        .pa-dash .pa-geo__dot {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #FF6600;
            box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.25);
            transform: translate(-50%, -50%);
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
        }
        .pa-dash .pa-quality__badge {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            border: 1px solid rgba(255, 102, 0, 0.4);
            background: rgba(255, 102, 0, 0.12);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 4px;
        }
        .pa-dash .pa-quality__badge strong {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }
        .pa-dash .pa-quality__badge span {
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--pa-accent-soft);
            margin-top: 4px;
        }
        .pa-dash .pa-empty {
            margin: 0;
            padding: 16px 0;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.45);
            text-align: center;
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

    {{-- Row 2: Traffic / Journey / Top Pages --}}
    @if ($showSourcesBlock || $showJourneyBlock || $showTopPagesBlock)
    <div class="pa-row-3">
        @if ($showSourcesBlock)
        <section class="pa-card" id="sources">
            <h2 class="pa-card__title">Traffic Source Overview</h2>
            <div class="pa-donut-wrap">
                <div
                    class="pa-donut"
                    role="img"
                    aria-label="Traffic sources"
                    :style="(rows => {
                        const list = rows || [];
                        const total = list.reduce((a, r) => a + Number(r.value || 0), 0);
                        if (!total) return { background: 'conic-gradient(rgba(255,255,255,0.15) 0 100%)' };
                        let deg = 0;
                        const stops = list.map(r => {
                            const span = (Number(r.value || 0) / total) * 360;
                            const start = deg;
                            deg += span;
                            return `${r.color || '#FF6600'} ${start}deg ${deg}deg`;
                        });
                        return { background: `conic-gradient(${stops.join(', ')})` };
                    })(pageTrafficSources())"
                >
                    <div class="pa-donut__hole">
                        <strong x-text="fmt((pageTrafficSources() || []).reduce((a, r) => a + Number(r.value || 0), 0))"></strong>
                        <span>Total Visitors</span>
                    </div>
                </div>
                <div class="pa-legend">
                    <template x-for="row in pageTrafficSources()" :key="row.key || row.label">
                        <div class="pa-legend__row">
                            <span class="pa-legend__left">
                                <i class="pa-legend__swatch" :style="`background:${row.color || '#FF6600'}`"></i>
                                <span x-text="row.label"></span>
                            </span>
                            <span class="pa-legend__pct" x-text="(row.pct != null ? row.pct : 0) + '%'"></span>
                        </div>
                    </template>
                    <p x-show="!(pageTrafficSources() || []).length" class="pa-empty">No traffic sources in this window.</p>
                </div>
            </div>
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
                <p
                    x-show="(pageJourney() || []).length"
                    class="mt-[10px] text-[11px] text-white/55"
                >
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
                            <th>Page path</th>
                            <th class="num">Visits</th>
                            <th class="num">Avg time</th>
                            <th class="num">Bounce</th>
                            <th class="num">Conv.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in pageTopPages()" :key="row.key || row.path || row.page">
                            <tr>
                                <td x-text="row.path || row.page || row.label"></td>
                                <td class="num" x-text="fmt(row.views ?? row.visitors ?? row.value)"></td>
                                <td class="num muted" x-text="row.avg_time || '1:12'"></td>
                                <td class="num muted" x-text="(row.bounce != null ? row.bounce : Math.max(12, 55 - Number(row.pct || 0))) + '%'"></td>
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

    {{-- Row 3: Funnel / Referrers / Keywords --}}
    @if ($showFunnelBlock || $showReferrersBlock || $showKeywordsBlock)
    <div class="pa-row-3">
        @if ($showFunnelBlock)
        <section class="pa-card">
            <h2 class="pa-card__title">Conversion Funnel</h2>
            <div class="pa-bars">
                <template x-for="row in pageFunnel()" :key="row.key || row.label">
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label" x-text="row.label"></span>
                        <span class="pa-bar-row__meta" x-text="`${fmt(row.value)} · ${row.pct != null ? row.pct : 0}%`"></span>
                        <div class="pa-bar-track">
                            <div
                                class="pa-bar-fill"
                                :style="`width:${row.bar != null ? row.bar : Math.max(4, Number(row.pct || 0))}%`"
                            ></div>
                        </div>
                    </div>
                </template>
                <p x-show="!(pageFunnel() || []).length" class="pa-empty">No funnel steps in this window.</p>
            </div>
            <div class="pa-inset" x-show="pageConversionSummary()">
                <div>
                    <span class="pa-inset__label">Conversion Rate</span>
                    <span class="pa-inset__value" x-text="pageConversionSummary()?.rate ?? pageConversionSummary()?.conversion_rate ?? '—'"></span>
                </div>
                <div>
                    <span class="pa-inset__label">Revenue</span>
                    <span class="pa-inset__value" x-text="pageConversionSummary()?.revenue ?? '—'"></span>
                </div>
                <div>
                    <span class="pa-inset__label">Transactions</span>
                    <span class="pa-inset__value" x-text="pageConversionSummary()?.transactions ?? '—'"></span>
                </div>
                <div>
                    <span class="pa-inset__label">AOV</span>
                    <span class="pa-inset__value" x-text="pageConversionSummary()?.aov ?? '—'"></span>
                </div>
            </div>
        </section>
        @endif

        @if ($showReferrersBlock)
        <section class="pa-card">
            <h2 class="pa-card__title">Referrer / Platform Breakdown</h2>
            <div class="pa-bars">
                <template x-for="row in pageReferrers()" :key="row.key || row.label">
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label" x-text="row.label"></span>
                        <span class="pa-bar-row__meta" x-text="`${fmt(row.value)} · ${row.pct != null ? row.pct : 0}%`"></span>
                        <div class="pa-bar-track">
                            <div
                                class="pa-bar-fill is-soft"
                                :style="`width:${row.bar != null ? row.bar : Math.max(4, Number(row.pct || 0))}%`"
                            ></div>
                        </div>
                    </div>
                </template>
                <p x-show="!(pageReferrers() || []).length" class="pa-empty">No referrer data in this window.</p>
            </div>
        </section>
        @endif

        @if ($showKeywordsBlock)
        <section class="pa-card">
            <h2 class="pa-card__title">Keyword &amp; Headline</h2>
            <div class="pa-split-2">
                <div>
                    <h3>Keywords</h3>
                    <template x-for="row in pageKeywords()" :key="row.key || row.keyword || row.label">
                        <div class="pa-list-item">
                            <span x-text="row.keyword || row.label"></span>
                            <span x-text="row.pct != null ? (row.pct + '%') : fmt(row.value)"></span>
                        </div>
                    </template>
                    <p x-show="!(pageKeywords() || []).length" class="pa-empty">No keywords.</p>
                </div>
                <div>
                    <h3>Headlines</h3>
                    <template x-for="row in pageHeadlines()" :key="row.key || row.headline || row.label">
                        <div class="pa-list-item">
                            <span x-text="row.headline || row.label"></span>
                            <span x-text="row.pct != null ? (row.pct + '%') : fmt(row.value)"></span>
                        </div>
                    </template>
                    <p x-show="!(pageHeadlines() || []).length" class="pa-empty">No headlines.</p>
                </div>
            </div>
            <div class="mt-[14px]" x-show="(pageKeywordHeadlines() || []).length">
                <h3 style="margin:0 0 8px;font-size:11px;font-weight:600;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:.06em">Keyword × Headline</h3>
                <div class="overflow-x-auto">
                    <table class="pa-table">
                        <thead>
                            <tr>
                                <th>Keyword</th>
                                <th>Headline / Campaign</th>
                                <th class="num">Visits</th>
                                <th class="num">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in pageKeywordHeadlines()" :key="row.key || row.label">
                                <tr>
                                    <td x-text="row.keyword"></td>
                                    <td class="muted" x-text="row.headline"></td>
                                    <td class="num" x-text="fmt(row.value)"></td>
                                    <td class="num muted" x-text="(row.pct != null ? row.pct : 0) + '%'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        @endif
    </div>
    @endif

    {{-- Row 4: Geo / Device / Revenue / Quality --}}
    @if ($showGeoBlock || $showDeviceBlock || $showSalesBlock || $showQualityBlock)
    <div class="pa-row-4">
        @if ($showGeoBlock)
        <section class="pa-card pa-card--compact">
            <h2 class="pa-card__title">Geography</h2>
            <div class="pa-geo">
                <div class="pa-geo__map" aria-hidden="true">
                    <svg viewBox="0 0 360 160" preserveAspectRatio="xMidYMid meet">
                        <ellipse cx="90" cy="70" rx="58" ry="42" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.12)"/>
                        <ellipse cx="190" cy="68" rx="46" ry="36" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.12)"/>
                        <ellipse cx="270" cy="95" rx="38" ry="30" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.12)"/>
                        <ellipse cx="145" cy="115" rx="28" ry="18" fill="rgba(255,255,255,0.03)" stroke="rgba(255,255,255,0.1)"/>
                    </svg>
                    <template x-for="(dot, idx) in pageGeoDots()" :key="'geo-dot-' + (dot.code || idx)">
                        <span class="pa-geo__dot" :style="`left:${dot.x}%;top:${dot.y}%;opacity:${dot.opacity}`" :title="dot.label"></span>
                    </template>
                </div>
                <template x-for="row in pageGeo()" :key="row.key || row.code || row.country || row.name">
                    <div class="pa-geo__row">
                        <img
                            class="pa-geo__flag"
                            x-show="typeof countryFlagUrl === 'function' && countryFlagUrl(row.code || row.country || row.name)"
                            :src="typeof countryFlagUrl === 'function' ? countryFlagUrl(row.code || row.country || row.name) : ''"
                            :alt="row.name || row.country || ''"
                        >
                        <span
                            class="pa-geo__flag-fallback"
                            x-show="!(typeof countryFlagUrl === 'function' && countryFlagUrl(row.code || row.country || row.name))"
                        ></span>
                        <span class="pa-geo__name" x-text="row.name || row.country || row.label || row.code"></span>
                        <span class="pa-geo__meta" x-text="`${fmt(row.value)} · ${row.pct != null ? row.pct : 0}%`"></span>
                        <div class="pa-bar-track">
                            <div
                                class="pa-bar-fill is-soft"
                                :style="`width:${row.bar != null ? row.bar : Math.max(4, Number(row.pct || 0))}%`"
                            ></div>
                        </div>
                    </div>
                </template>
                <p x-show="!(pageGeo() || []).length" class="pa-empty">No geography data.</p>
            </div>
        </section>
        @endif

        @if ($showDeviceBlock)
        <section class="pa-card pa-card--compact">
            <h2 class="pa-card__title">Device Breakdown</h2>
            <div class="pa-donut-wrap" style="grid-template-columns: 112px minmax(0, 1fr);">
                <div
                    class="pa-donut pa-donut--sm"
                    role="img"
                    aria-label="Device breakdown"
                    :style="(rows => {
                        const list = rows || [];
                        const total = list.reduce((a, r) => a + Number(r.value || 0), 0);
                        if (!total) return { background: 'conic-gradient(rgba(255,255,255,0.15) 0 100%)' };
                        let deg = 0;
                        const stops = list.map(r => {
                            const span = (Number(r.value || 0) / total) * 360;
                            const start = deg;
                            deg += span;
                            return `${r.color || '#FF6600'} ${start}deg ${deg}deg`;
                        });
                        return { background: `conic-gradient(${stops.join(', ')})` };
                    })(pageDevices())"
                >
                    <div class="pa-donut__hole">
                        <strong style="font-size:14px" x-text="fmt((pageDevices() || []).reduce((a, r) => a + Number(r.value || 0), 0))"></strong>
                        <span>Devices</span>
                    </div>
                </div>
                <div class="pa-legend">
                    <template x-for="row in pageDevices()" :key="row.key || row.label">
                        <div class="pa-legend__row">
                            <span class="pa-legend__left">
                                <i class="pa-legend__swatch" :style="`background:${row.color || '#FF6600'}`"></i>
                                <span x-text="row.label"></span>
                            </span>
                            <span class="pa-legend__pct" x-text="(row.pct != null ? row.pct : 0) + '%'"></span>
                        </div>
                    </template>
                    <p x-show="!(pageDevices() || []).length" class="pa-empty">No device data.</p>
                </div>
            </div>
        </section>
        @endif

        @if ($showSalesBlock)
        <section class="pa-card pa-card--compact" id="sales">
            <h2 class="pa-card__title">Sales &amp; Revenue</h2>
            <div class="pa-revenue-stats" x-show="pageConversionSummary()">
                <div>
                    <span>Revenue</span>
                    <strong x-text="pageConversionSummary()?.revenue || '$0.00'"></strong>
                </div>
                <div>
                    <span>Transactions</span>
                    <strong x-text="pageConversionSummary()?.transactions || '0'"></strong>
                </div>
                <div>
                    <span>AOV</span>
                    <strong x-text="pageConversionSummary()?.aov || '$0.00'"></strong>
                </div>
            </div>
            <div class="pa-revenue-spark" aria-hidden="true" x-html="sparkSvg(pageRevenueSpark(), '#FF6600')"></div>
        </section>
        @endif

        @if ($showQualityBlock)
        <section class="pa-card pa-card--compact">
            <h2 class="pa-card__title">Quality Signals</h2>
            <div class="pa-quality" x-show="pageQuality()">
                <div class="pa-quality__badge">
                    <strong x-text="(pageQuality()?.score ?? '—') + '/100'"></strong>
                    <span x-text="pageQuality()?.label || 'Score'"></span>
                </div>
                <div class="pa-bars">
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label">Human Visitors</span>
                        <span class="pa-bar-row__meta" x-text="`${fmt(pageQuality()?.human_count || 0)} · ${pageQuality()?.human ?? 0}%`"></span>
                        <div class="pa-bar-track">
                            <div class="pa-bar-fill is-green" :style="`width:${Math.max(4, Number(pageQuality()?.human || 0))}%`"></div>
                        </div>
                    </div>
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label">Crawlers</span>
                        <span class="pa-bar-row__meta" x-text="`${fmt(pageQuality()?.crawlers_count || 0)} · ${pageQuality()?.crawlers ?? 0}%`"></span>
                        <div class="pa-bar-track">
                            <div class="pa-bar-fill is-soft" :style="`width:${Math.max(4, Number(pageQuality()?.crawlers || 0))}%`"></div>
                        </div>
                    </div>
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label">Automation</span>
                        <span class="pa-bar-row__meta" x-text="`${fmt(pageQuality()?.automation_count || 0)} · ${pageQuality()?.automation ?? 0}%`"></span>
                        <div class="pa-bar-track">
                            <div class="pa-bar-fill is-amber" :style="`width:${Math.max(4, Number(pageQuality()?.automation || 0))}%`"></div>
                        </div>
                    </div>
                    <div class="pa-bar-row">
                        <span class="pa-bar-row__label">Malicious Activity</span>
                        <span class="pa-bar-row__meta" x-text="`${fmt(pageQuality()?.malicious_count || 0)} · ${pageQuality()?.malicious ?? 0}%`"></span>
                        <div class="pa-bar-track">
                            <div class="pa-bar-fill is-rose" :style="`width:${Math.max(4, Number(pageQuality()?.malicious || 0))}%`"></div>
                        </div>
                    </div>
                </div>
            </div>
            <p x-show="!pageQuality()" class="pa-empty">No quality signals.</p>
        </section>
        @endif
    </div>
    @endif
</div>
