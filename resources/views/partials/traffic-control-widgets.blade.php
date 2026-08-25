{{-- Traffic Control bottom analytics panels (live page-analytics payload) --}}
<section x-show="analyticsMode" x-cloak class="tc-widgets">
    <div class="tc-widgets__row tc-widgets__row--5">
        <article class="tc-widget">
            <h3 class="tc-widget__title">Visitor Source Distribution</h3>
            <div class="tc-widget__body tc-widget__body--donut">
                <div class="bp-adv-donut" :style="`--bp-donut: ${sourceDonut.gradient}`">
                    <div class="bp-adv-donut__inner">
                        <p class="bp-adv-donut__value" x-text="sourceDonut.total_label"></p>
                        <p class="bp-adv-donut__label">Visitors</p>
                    </div>
                </div>
                <ul class="bp-adv-legend">
                    <template x-for="item in (pageAnalytics?.traffic_sources || [])" :key="'src-' + (item.key || item.label)">
                        <li>
                            <span class="bp-adv-legend__swatch" :style="`background:${item.color || '#FF6600'}`"></span>
                            <span class="bp-adv-legend__name" x-text="item.label"></span>
                            <span class="bp-adv-legend__meta">
                                <span x-text="(item.pct != null ? item.pct : 0) + '%'"></span>
                                <span class="opacity-55" x-text="'(' + fmt(item.value) + ')'"></span>
                            </span>
                        </li>
                    </template>
                    <li x-show="!(pageAnalytics?.traffic_sources || []).length" class="!text-white/40">No source data in range.</li>
                </ul>
            </div>
        </article>

        <article class="tc-widget">
            <h3 class="tc-widget__title">Engagement / Behavior</h3>
            <div class="tc-widget__body tc-widget__body--donut">
                <div class="bp-adv-donut" :style="`--bp-donut: ${engagementDonut.gradient}`">
                    <div class="bp-adv-donut__inner">
                        <p class="bp-adv-donut__value" x-text="engagementDonut.total_label"></p>
                        <p class="bp-adv-donut__label">Sessions</p>
                    </div>
                </div>
                <ul class="bp-adv-legend">
                    <template x-for="item in (pageAnalytics?.engagement || [])" :key="'eng-' + (item.key || item.label)">
                        <li>
                            <span class="bp-adv-legend__swatch" :style="`background:${item.color || '#FF6600'}`"></span>
                            <span class="bp-adv-legend__name" x-text="item.label"></span>
                            <span class="bp-adv-legend__meta">
                                <span x-text="(item.pct != null ? item.pct : 0) + '%'"></span>
                                <span class="opacity-55" x-text="'(' + fmt(item.value) + ')'"></span>
                            </span>
                        </li>
                    </template>
                    <li x-show="!(pageAnalytics?.engagement || []).length" class="!text-white/40">No engagement data in range.</li>
                </ul>
            </div>
        </article>

        <article class="tc-widget">
            <h3 class="tc-widget__title">Top Landing Pages</h3>
            <div class="tc-widget__list">
                <template x-for="row in (pageAnalytics?.top_landing_pages || [])" :key="'land-' + row.path">
                    <div class="tc-widget__row">
                        <span class="tc-widget__path" x-text="row.path" :title="row.path"></span>
                        <span class="tc-widget__meta" x-text="fmt(row.value) + ' · ' + (row.pct != null ? row.pct : 0) + '%'"></span>
                    </div>
                </template>
                <p x-show="!(pageAnalytics?.top_landing_pages || []).length" class="tc-widget__empty">No landing pages in range.</p>
            </div>
        </article>

        <article class="tc-widget">
            <h3 class="tc-widget__title">Best Journey Paths</h3>
            <div class="tc-widget__list">
                <template x-for="row in (pageAnalytics?.journey_paths || [])" :key="'jp-' + row.key">
                    <div class="tc-widget__row">
                        <span class="tc-widget__path" x-text="row.path" :title="row.path"></span>
                        <span class="tc-widget__meta" x-text="fmt(row.value)"></span>
                    </div>
                </template>
                <p x-show="!(pageAnalytics?.journey_paths || []).length" class="tc-widget__empty">No multi-page journeys yet.</p>
            </div>
        </article>

        <article class="tc-widget">
            <h3 class="tc-widget__title">Top Exit Pages</h3>
            <div class="tc-widget__list">
                <template x-for="row in (pageAnalytics?.top_exit_pages || [])" :key="'exit-' + row.path">
                    <div class="tc-widget__row">
                        <span class="tc-widget__path" x-text="row.path" :title="row.path"></span>
                        <span class="tc-widget__meta" x-text="fmt(row.value) + ' · ' + (row.pct != null ? row.pct : 0) + '%'"></span>
                    </div>
                </template>
                <p x-show="!(pageAnalytics?.top_exit_pages || []).length" class="tc-widget__empty">No exit pages in range.</p>
            </div>
        </article>
    </div>

    <div class="tc-widgets__row tc-widgets__row--3">
        <article class="tc-widget">
            <h3 class="tc-widget__title">Conversion by Source / Platform</h3>
            <div class="tc-widget__table-wrap">
                <table class="tc-widget__table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Visits</th>
                            <th>Conv %</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in (pageAnalytics?.conversion_by_source || [])" :key="'cbs-' + row.key">
                            <tr>
                                <td x-text="row.source || row.label"></td>
                                <td x-text="fmt(row.visits)"></td>
                                <td x-text="(row.conversion_rate != null ? row.conversion_rate : 0) + '%'"></td>
                                <td x-text="row.revenue_label || ('$' + fmt(row.revenue))"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!(pageAnalytics?.conversion_by_source || []).length" class="tc-widget__empty">No conversion-by-source data.</p>
            </div>
        </article>

        <article class="tc-widget">
            <h3 class="tc-widget__title">Recent High-Value Sessions</h3>
            <div class="tc-widget__cards">
                <template x-for="(card, idx) in (pageAnalytics?.high_value_sessions || [])" :key="'hvs-' + idx">
                    <div class="tc-hv-card">
                        <p class="tc-hv-card__rev" x-text="card.revenue_label || ('$' + Number(card.revenue || 0).toFixed(2))"></p>
                        <p class="tc-hv-card__product" x-text="card.product || 'Purchase'"></p>
                        <p class="tc-hv-card__meta" x-text="(card.device || '—') + (card.ip ? (' · ' + card.ip) : '')"></p>
                        <p class="tc-hv-card__ago" x-text="card.at || '—'"></p>
                    </div>
                </template>
                <p x-show="!(pageAnalytics?.high_value_sessions || []).length" class="tc-widget__empty">No purchase sessions with revenue in range.</p>
            </div>
        </article>

        <article class="tc-widget">
            <h3 class="tc-widget__title">Quality Signals</h3>
            <div class="tc-quality">
                <div class="tc-quality__gauge">
                    <div class="tc-quality__ring" :style="qualityRingStyle('crawler')"></div>
                    <div class="tc-quality__center">
                        <strong x-text="qualityScore('crawler')"></strong>
                        <span>Crawler</span>
                    </div>
                </div>
                <div class="tc-quality__gauge">
                    <div class="tc-quality__ring" :style="qualityRingStyle('automation')"></div>
                    <div class="tc-quality__center">
                        <strong x-text="qualityScore('automation')"></strong>
                        <span>Automation</span>
                    </div>
                </div>
                <div class="tc-quality__gauge">
                    <div class="tc-quality__ring" :style="qualityRingStyle('malicious')"></div>
                    <div class="tc-quality__center">
                        <strong x-text="qualityScore('malicious')"></strong>
                        <span>Malicious</span>
                    </div>
                </div>
            </div>
            <p class="tc-widget__hint" x-text="pageAnalytics?.quality?.label || 'Quality overview for selected range'"></p>
        </article>
    </div>
</section>
