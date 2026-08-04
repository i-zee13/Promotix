@extends('layouts.admin')

@section('title', 'Paid Advertising | Dashboard')
@section('subtitle', 'Live campaign performance and detection results')

@section('header-toolbar')
    @include('partials.paid-marketing-header-timezone')
@endsection

@section('rightbar')
<div class="figma-rightbar-default paid-rightbar">
    <div class="mb-[16px]">
        <p class="text-[18px] font-bold leading-none text-[#a9a9a9]">Digital Promotix</p>
        <p class="mt-[4px] text-[9px] text-white/45">Paid Advertising</p>
    </div>

    <div class="mb-[6px]">
        <h2 class="mb-[8px] text-[14px] font-bold text-[#a9a9a9]">Activity Feed</h2>
        <div id="paid-activity-feed" class="space-y-[8px] border-b-2 border-[#5a2a99] pb-[12px] text-[9px] text-[#a9a9a9]">
            <p class="text-white/45">Loading…</p>
        </div>
    </div>

    <div class="mt-[16px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">Quick Actions</h2>
        <div class="grid w-full max-w-[168px] grid-cols-2 gap-[10px]">
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
            <a href="{{ route('reports.index') }}" class="paid-quick-action" title="View Reports">
                @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[16px] w-[16px]'])
                <span>View Reports</span>
            </a>
        </div>
    </div>

    <div class="mt-[18px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">System Overview</h2>
        <div id="paid-system-overview" class="space-y-[8px] text-[10px] text-white/75">
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Total Clicks</span><span data-sys="clicks" class="text-white/90">—</span>
            </div>
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Invalid Clicks</span><span data-sys="invalid" class="text-rose-300">—</span>
            </div>
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Blocked</span><span data-sys="blocked" class="text-white/90">—</span>
            </div>
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Protection Rate</span><span data-sys="rate" class="text-emerald-200">—</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="paidAdvertisingFigma(@js([
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
        <div class="mb-[23px] flex flex-col gap-[14px] 2xl:flex-row 2xl:items-start 2xl:justify-between">
            <div class="flex flex-wrap items-center gap-[12px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Advertising</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Dashboard</span>
            </div>

            <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--paid flex min-h-[54px] w-full max-w-full flex-wrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)] 2xl:max-w-[min(100%,980px)]">
                <label class="flex min-w-[120px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
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
                <label class="flex min-w-[120px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.traffic_source" @change="reload()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="google_ads">Google Ads</option>
                            <option value="meta_ads" disabled>Meta Ads</option>
                            <option value="microsoft_ads" disabled>Microsoft Ads</option>
                        </select>
                    </div>
                </label>
                <label class="flex min-w-[130px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
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
                <label class="flex min-w-[130px] flex-[1.1] flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaign</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.campaign" @change="onCampaignChange(); reload()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Campaigns</option>
                            <template x-for="row in campaignOptions" :key="row.campaign + '-' + (row.campaign_id || '')">
                                <option :value="row.campaign" x-text="row.campaign"></option>
                            </template>
                        </select>
                    </div>
                </label>
                <label class="flex min-w-[120px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleReload()" placeholder="Landing page" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
                <label class="flex min-w-[88px] shrink-0 flex-col items-center justify-center gap-[4px] px-[10px] py-[6px]">
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

        <div class="paid-dashboard-cards-wrap">
        <div class="paid-dashboard-cards">
            <article class="paid-dashboard-card paid-dashboard-card--traffic">
                <div class="flex items-start justify-between gap-[8px]">
                    <h2 class="paid-dashboard-card__title">Google Ads Click Summary</h2>
                    <div class="flex shrink-0 items-center gap-[6px]">
                        <button type="button" class="paid-dashboard-card__icon-btn" aria-label="Refresh" @click="reload(true, true)" title="Refresh Google Ads sync">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h5M20 20v-5h-5M20 9A8 8 0 006.34 6.34M4 15a8 8 0 0013.66 2.66"/></svg>
                        </button>
                        <span class="paid-dashboard-card__icon-btn text-[11px]" title="1 Google click ID (gclid/gbraid/wbraid) = 1 click. Tracking accuracy = tracked ÷ Google Ads clicks.">i</span>
                    </div>
                </div>
                <div class="mt-[8px] grid grid-cols-2 gap-x-[10px] gap-y-[6px]">
                    <div>
                        <p class="paid-traffic-metrics__label">Total Clicks</p>
                        <p class="paid-traffic-metrics__value" x-text="fmt(summary.total_click_count || summary.google_clicks)"></p>
                    </div>
                    <div>
                        <p class="paid-traffic-metrics__label">Tracked Clicks</p>
                        <p class="paid-traffic-metrics__value" x-text="fmt(summary.tracked_clicks ?? summary.unique_paid_clicks)"></p>
                    </div>
                </div>
                <div class="mt-[10px] space-y-[8px]">
                    <div>
                        <div class="mb-[3px] flex items-center justify-between text-[9px]">
                            <span class="text-emerald-300">Valid Clicks</span>
                            <span class="text-white/85"><span x-text="fmt(summary.unique_valid_paid_clicks ?? summary.valid_paid_visits)"></span> · <span x-text="validClickPct"></span>%</span>
                        </div>
                        <div class="paid-metric-bar"><span class="paid-metric-bar__fill is-valid" :style="'width:' + validClickPct + '%'"></span></div>
                    </div>
                    <div>
                        <div class="mb-[3px] flex items-center justify-between text-[9px]">
                            <span class="text-rose-300">Invalid Clicks</span>
                            <span class="text-white/85"><span x-text="fmt(summary.unique_invalid_paid_clicks ?? summary.invalid_paid_visits)"></span> · <span x-text="invalidClickPct"></span>%</span>
                        </div>
                        <div class="paid-metric-bar"><span class="paid-metric-bar__fill is-invalid" :style="'width:' + invalidClickPct + '%'"></span></div>
                    </div>
                    <div>
                        <div class="mb-[3px] flex items-center justify-between text-[9px]">
                            <span class="text-white/70">Tracking Accuracy</span>
                            <span class="text-white/85"><span x-text="fmt(summary.tracking_accuracy_pct ?? summary.tag_capture_pct)"></span>%</span>
                        </div>
                        <div class="paid-metric-bar"><span class="paid-metric-bar__fill is-accuracy" :style="'width:' + Number(summary.tracking_accuracy_pct ?? summary.tag_capture_pct ?? 0) + '%'"></span></div>
                        <p class="paid-traffic-metrics__hint" x-show="summary.tag_gap_warning">Tracking gap vs Google Ads — check GCLID capture</p>
                    </div>
                </div>
            </article>

            <article class="paid-dashboard-card">
                <h2 class="paid-dashboard-card__title">Bot Protection</h2>
                <div class="grid grid-cols-[minmax(0,1fr)_1fr] items-end gap-[10px]">
                    <div class="grid grid-cols-2 gap-x-[8px] gap-y-[6px] pt-[6px] text-left">
                        <div>
                            <p class="text-[8px] text-white/65">Visitors</p>
                            <p class="text-[16px] font-semibold text-white" x-text="fmt(summary.tag_paid_visits)"></p>
                        </div>
                        <div>
                            <p class="text-[8px] text-white/65">Bots Detected</p>
                            <p class="text-[16px] font-semibold text-emerald-300" x-text="fmt(summary.invalid_paid_visits)"></p>
                        </div>
                        <div>
                            <p class="text-[8px] text-white/65">Blocked Bots</p>
                            <p class="text-[16px] font-semibold text-rose-300" x-text="fmt(summary.block_enforced || summary.block_attempts || 0)"></p>
                        </div>
                        <div>
                            <p class="text-[8px] text-white/65">Detection Rate</p>
                            <p class="text-[16px] font-semibold text-white"><span x-text="botRate"></span>%</p>
                        </div>
                    </div>
                    <div class="relative h-[80px] w-full min-w-0">
                        <canvas id="bot-bars" class="h-full w-full" aria-label="Invalid traffic trend"></canvas>
                    </div>
                </div>
            </article>

            <article class="paid-dashboard-card">
                <div class="flex items-start justify-between">
                    <h2 class="paid-dashboard-card__title">Invalid Traffic Actions</h2>
                    <span class="paid-dashboard-card__icon-btn text-[12px]" title="What happened after invalid traffic detection">i</span>
                </div>
                <div class="mt-[6px] grid grid-cols-2 gap-[8px]">
                    <div class="text-center">
                        <p class="text-[9px] text-white/70">Invalid Clicks</p>
                        <p class="text-[22px] font-semibold leading-none text-white" x-text="fmt(summary.unique_invalid_paid_clicks ?? summary.invalid_paid_visits)"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-[9px] text-white/70">Detection Events</p>
                        <p class="text-[22px] font-semibold leading-none text-white" x-text="fmt(summary.invalid_paid_events || summary.invalid_paid_visits)"></p>
                    </div>
                </div>
                <div class="mt-[8px] space-y-0">
                    <div class="paid-blocking-row"><span class="text-rose-300">Blocked</span><span x-text="fmt(summary.block_enforced || 0)"></span></div>
                    <div class="paid-blocking-row"><span class="text-amber-200">Monitored</span><span x-text="fmt(summary.flagged_paid_visits)"></span></div>
                    <div class="paid-blocking-row"><span class="text-emerald-300">Whitelisted</span><span x-text="fmt(whitelistedIpCount)"></span></div>
                </div>
            </article>

            <article class="paid-dashboard-card">
                <h2 class="paid-dashboard-card__title">Campaign Performance</h2>
                <div class="paid-campaign-breakdown !items-stretch">
                    <template x-if="untaggedDomains.length > 0">
                        <div class="w-full space-y-[4px] px-[2px] text-left">
                            <template x-for="d in untaggedDomains.slice(0, 3)" :key="d.id">
                                <p class="truncate text-[10px] text-white/85" x-text="d.hostname"></p>
                            </template>
                        </div>
                    </template>
                    <template x-if="untaggedDomains.length === 0">
                        <div class="paid-campaign-table-wrap w-full">
                            <table class="paid-campaign-table">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Clicks</th>
                                        <th>Inv %</th>
                                        <th>Saved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in campaignOptions.slice(0, 4)" :key="row.campaign">
                                        <tr>
                                            <td class="truncate" :title="row.campaign" x-text="row.campaign"></td>
                                            <td x-text="fmt(row.total)"></td>
                                            <td x-text="(row.invalid_pct != null ? row.invalid_pct : 0) + '%'"></td>
                                            <td>$<span x-text="Number(row.cost_saved || 0).toFixed(0)"></span></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <p x-show="campaignOptions.length === 0" class="px-[2px] text-[10px] text-white/55">No campaign data yet</p>
                        </div>
                    </template>
                    <a
                        :href="campaignBreakdownLink()"
                        class="paid-campaign-link"
                        x-text="untaggedDomains.length ? 'Add Tag Management' : (topCampaign ? 'Detection Settings' : 'Add Tag Management')"
                    ></a>
                </div>
            </article>
        </div>
        </div>

        <div class="mt-[15px] grid grid-cols-1 gap-[17px] xl:grid-cols-[minmax(0,589px)_minmax(260px,1fr)]">
            <section class="paid-trends-card self-start rounded-[12px] border border-[#6400B2] bg-[#6400B2] p-[16px] shadow-[0_0_24px_rgba(100,0,179,.45)] sm:p-[20px]">
                <div class="mb-[8px] flex flex-wrap items-center justify-between gap-[8px]">
                    <div class="flex flex-wrap items-center gap-[10px]">
                        <h2 class="text-[20px] font-normal text-[#a9a9a9]">Paid Traffic Trend</h2>
                        <template x-for="item in trendsLegendItems()" :key="item.key">
                            <button
                                type="button"
                                class="chart-legend-item text-[12px] text-white"
                                :class="{ 'is-hidden': isTrendSeriesHidden(item.key) }"
                                @click="toggleTrendSeries(item.key)"
                            >
                                <i class="mr-[4px] inline-block h-[12px] w-[12px] rounded-[1px]" :style="`background:${item.color}`"></i>
                                <span x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                    <select x-model="filters.window" @change="setWindow()" class="h-[36px] rounded-full border border-white/30 bg-[#101010] px-[16px] text-[13px] text-white focus:border-[#9a1aff] focus:ring-1 focus:ring-[#9a1aff]/40 sm:h-[41px] sm:px-[20px] sm:text-[14px]">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="paid-trends-wrap">
                    <div id="paid-trends-tooltip" class="paid-trends-tooltip" hidden></div>
                    <canvas id="paid-trends" class="paid-trends-canvas h-[200px] w-full sm:h-[240px]"></canvas>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-2 xl:grid-cols-2">
                <section class="paid-sidebar-card">
                    <div class="paid-sidebar-card__head">
                        <svg class="h-[16px] w-[16px] text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3l7 4v5c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V7l7-4z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 9v4m0 3h.01"/></svg>
                        <h2>Click Activity Heatmap</h2>
                    </div>
                    <div id="heatmap-grid" class="paid-heatmap-grid mt-[10px]"></div>
                    <div class="paid-heatmap-bar mt-[8px]"><span :style="'width:' + heatmapIntensity + '%'"></span></div>
                </section>

                <section class="paid-sidebar-card">
                    <div class="paid-sidebar-card__head">
                        <svg class="h-[16px] w-[16px] text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                        <h2>Keyword Performance</h2>
                    </div>
                    <div id="keyword-list" class="mt-[10px] overflow-x-auto"></div>
                </section>

                <section class="paid-invalid-card sm:col-span-2 xl:col-span-2">
                    <div class="flex flex-wrap items-start justify-between gap-[8px]">
                        <div>
                            <h2 class="text-[16px] font-normal text-[#a9a9a9]">Invalid Traffic Protection Engine</h2>
                            <p class="mt-[2px] text-[10px] text-white/55">Detection rules and protection actions</p>
                        </div>
                        <a href="{{ route('paid-marketing.detection-settings') }}" class="rounded-[5px] border border-white/20 px-[10px] py-[4px] text-[10px] text-white/80 hover:bg-white/10">Configure</a>
                    </div>
                    <div class="mt-[10px] grid grid-cols-1 gap-[8px] sm:grid-cols-2" x-show="(blocking.rules || []).length">
                        <template x-for="rule in (blocking.rules || [])" :key="rule.label">
                            <div class="flex items-center justify-between gap-[10px] rounded-[6px] border border-white/15 bg-black/25 px-[10px] py-[8px]">
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] text-white/90" x-text="rule.label"></p>
                                    <p class="mt-[2px] text-[9px] text-white/50" x-text="rule.action"></p>
                                </div>
                                <span
                                    class="paid-rule-toggle"
                                    :class="{
                                        'is-on': rule.tone !== 'off',
                                        'is-block': rule.tone === 'block',
                                        'is-challenge': rule.tone === 'challenge',
                                        'is-monitor': rule.tone === 'monitor'
                                    }"
                                    :title="rule.action"
                                >
                                    <span class="paid-rule-toggle__knob"></span>
                                </span>
                            </div>
                        </template>
                    </div>
                    <canvas id="invalid-protection" class="mt-[8px] h-[105px] w-full"></canvas>
                </section>
            </div>
        </div>

        <div class="mt-[15px] grid grid-cols-1 gap-[17px] xl:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]">
            <section class="min-h-[451px] rounded-[10px] border border-[#5a2a99] bg-[#111111] p-[18px]">
                <div class="mb-[10px] flex flex-wrap items-center justify-between gap-[10px]">
                    <div class="flex flex-wrap items-center gap-[10px]">
                        <h2 class="text-[24px] font-semibold leading-none text-[#a9a9a9]">Recent IP Activity</h2>
                        <div class="flex rounded-[6px] border border-white/15 bg-black/30 p-[2px] text-[10px]">
                            <button type="button" class="rounded-[4px] px-[10px] py-[4px]" :class="ipViewMode === 'basic' ? 'bg-[#6400B2] text-white' : 'text-white/60'" @click="ipViewMode = 'basic'">Basic View</button>
                            <button type="button" class="rounded-[4px] px-[10px] py-[4px]" :class="ipViewMode === 'expert' ? 'bg-[#6400B2] text-white' : 'text-white/60'" @click="ipViewMode = 'expert'">Expert View</button>
                        </div>
                        <button type="button" @click="exportIpsCsv()" title="Download CSV" class="flex h-[26px] w-[26px] items-center justify-center rounded-[4px] border border-[#6400B2]/60 bg-[#1a1a1a] text-[#B893D8] transition hover:border-[#6400B2] hover:bg-[#6400B2]/20 hover:text-white">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l4-4m-4 4l-4-4M4 19h16"/></svg>
                        </button>
                    </div>
                    <div class="flex h-[28px] max-w-[min(100%,280px)] items-center gap-[8px] rounded-[3px] bg-[#6400B2] px-[9px] text-[10px] text-white">
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
                <div class="promotix-slim-scroll max-h-[365px] overflow-x-auto overflow-y-auto rounded-[4px] border border-white/15">
                    <table class="w-full min-w-[980px] table-fixed text-left text-[11px] text-[#a9a9a9]" :class="ipViewMode === 'expert' ? 'min-w-[1280px]' : 'min-w-[860px]'">
                        <thead class="sticky top-0 z-[1] bg-[#6400B2]">
                            <tr>
                                <th class="w-[14%] px-[8px] py-[7px] font-normal">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('ip')" @click="setIpSort('ip')"><span>IP Address</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                                <th class="w-[12%] px-[8px] py-[7px] font-normal">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('campaign')" @click="setIpSort('campaign')"><span>Campaign</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                                <th class="w-[7%] px-[8px] py-[7px] font-normal">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('invalid')" @click="setIpSort('invalid')"><span>Invalid</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                                <th class="w-[7%] px-[8px] py-[7px] font-normal">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('valid')" @click="setIpSort('valid')"><span>Valid</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('risk_score')" @click="setIpSort('risk_score')"><span>Risk</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('country')" @click="setIpSort('country')"><span>Country</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                                <th class="w-[10%] px-[8px] py-[7px] font-normal">ISP</th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal">Action</th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('last_seen')" @click="setIpSort('last_seen')"><span>Timestamp</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal" x-show="ipViewMode === 'expert'">Device</th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal" x-show="ipViewMode === 'expert'">Browser</th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal" x-show="ipViewMode === 'expert'">
                                    <button type="button" class="promotix-sortable" :class="ipSortClass('top_threat')" @click="setIpSort('top_threat')"><span>Threat</span><span class="promotix-sortable-arrows" aria-hidden="true"><span class="promotix-sortable-up">▲</span><span class="promotix-sortable-down">▼</span></span></button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/15">
                            <template x-for="row in sortedIps" :key="row.ip">
                                <tr class="cursor-pointer align-middle transition hover:bg-white/5" @click="openIpModal(row)">
                                    <td class="max-w-0 px-[8px] py-[6px]">
                                        <span class="flex items-center gap-[4px]">
                                            <span class="block truncate font-mono text-[9px] text-white" :title="row.ip" x-text="ipLabel(row.ip)"></span>
                                            <span x-show="row.is_allowlisted" class="shrink-0 rounded-[3px] bg-emerald-500/20 px-[4px] py-[1px] text-[8px] font-semibold uppercase text-emerald-300">WL</span>
                                        </span>
                                    </td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px] text-[10px] text-white/85" :title="row.campaign || ''" x-text="row.campaign || '—'"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap text-rose-300" x-text="fmt(row.invalid)"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap text-emerald-300" x-text="fmt(row.valid ?? Math.max(0, Number(row.total || 0) - Number(row.invalid || 0)))"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap">
                                        <span class="paid-risk-badge" :class="riskBadgeClass(row.risk_level)" x-text="(row.risk_level || '—') + (row.risk_score != null ? ' ' + row.risk_score : '')"></span>
                                    </td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px]" x-text="row.country || '—'"></td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px] text-[10px]" :title="row.isp || ''" x-text="row.isp || '—'"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap" x-text="row.action || '—'"></td>
                                    <td class="whitespace-nowrap px-[8px] py-[6px] text-[10px]" x-text="dateLabel(row.last_seen)"></td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px] capitalize" x-show="ipViewMode === 'expert'" x-text="row.device || '—'"></td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px]" x-show="ipViewMode === 'expert'" x-text="row.browser || '—'"></td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px] capitalize" x-show="ipViewMode === 'expert'" x-text="threatLabel(row.top_threat)"></td>
                                </tr>
                            </template>
                            <tr x-show="sortedIps.length === 0"><td colspan="12" class="px-[10px] py-[12px] text-center text-white/60" x-text="filters.campaign ? 'No paid IPs for this campaign in the selected date range.' : 'No paid IP data yet for the selected domain(s) and date range.'"></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="min-h-[329px] rounded-[10px] border border-[#5a2a99] bg-[#111111] p-[14px]">
                <div class="mb-[10px] flex items-center justify-between">
                    <h2 class="text-[16px] font-semibold text-[#a9a9a9]">Top High Risk IPs</h2>
                    <span class="text-[9px] text-white/45">Risk ≥ Medium</span>
                </div>
                <div class="promotix-slim-scroll max-h-[380px] overflow-y-auto rounded-[4px] border border-white/10">
                    <table class="w-full text-left text-[10px] text-[#a9a9a9]">
                        <thead class="sticky top-0 bg-[#6400B2] text-white">
                            <tr>
                                <th class="px-[8px] py-[7px] font-normal">IP</th>
                                <th class="px-[8px] py-[7px] font-normal">Score</th>
                                <th class="px-[8px] py-[7px] font-normal">Level</th>
                                <th class="px-[8px] py-[7px] font-normal">Threat</th>
                                <th class="px-[8px] py-[7px] font-normal">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <template x-for="row in highRiskIps" :key="'hr-' + row.ip">
                                <tr class="cursor-pointer hover:bg-white/5" @click="openIpModal(row)">
                                    <td class="max-w-[90px] truncate px-[8px] py-[7px] font-mono text-[9px] text-white" :title="row.ip" x-text="ipLabel(row.ip)"></td>
                                    <td class="px-[8px] py-[7px]" x-text="row.risk_score != null ? row.risk_score : '—'"></td>
                                    <td class="px-[8px] py-[7px]"><span class="paid-risk-badge" :class="riskBadgeClass(row.risk_level)" x-text="row.risk_level || '—'"></span></td>
                                    <td class="max-w-[70px] truncate px-[8px] py-[7px] capitalize" x-text="threatLabel(row.top_threat)"></td>
                                    <td class="px-[8px] py-[7px]" x-text="row.action || '—'"></td>
                                </tr>
                            </template>
                            <tr x-show="highRiskIps.length === 0">
                                <td colspan="5" class="px-[10px] py-[16px] text-center text-white/55">No high-risk IPs in this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
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
                                        <p class="figma-modal-label">IP</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(ipModal.row?.ip || activeIpClick.ip)">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--mono figma-modal-value--mono-sm"
                                       :title="ipModal.row?.ip || activeIpClick.ip"
                                       x-text="ipLabel(ipModal.row?.ip || activeIpClick.ip)"></p>
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
                                    <p class="figma-modal-label">Device</p>
                                    <p class="figma-modal-value capitalize" x-text="ipModal.row?.device || activeIpClick.device || '—'"></p>
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
                                    <p class="figma-modal-value" x-text="ipModal.row?.asn || '—'"></p>
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
        trackingTemplate: '{lpurl}?gclid={gclid}&gbraid={gbraid}&wbraid={wbraid}&utm_source=google&utm_medium=cpc&utm_campaign={campaignid}',
        summary: { paid_visits: 0, verified_paid_visits: 0, verified_valid_paid_visits: 0, unverified_paid_visits: 0, tag_paid_visits: 0, tracked_clicks: 0, google_clicks: 0, total_click_count: 0, tag_capture_pct: 0, tracking_accuracy_pct: 0, tag_gap_warning: false, invalid_paid_visits: 0, invalid_paid_events: 0, unique_invalid_paid_clicks: 0, blocked_paid_visits: 0, block_attempts: 0, block_enforced: 0, flagged_paid_visits: 0, valid_paid_visits: 0, unique_paid_clicks: 0, unique_valid_paid_clicks: 0, unique_ips: 0, invalid_reconciliation: { platform_only: 0, google_only: 0, overlap: 0 } },
        trends: { labels: [], datasets: [], invalid_daily: [] },
        blocking: { labels: [], datasets: [], rules: [] },
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
        hiddenTrendSeries: { lastWeek: false, thisWeek: false, clicks: false, valid: false, invalid: false },
        compareEnabled: false,
        ipViewMode: 'basic',
        cardCharts: {},
        get botRate() {
            const tracked = Number(this.summary.tracked_clicks || this.summary.unique_paid_clicks || this.summary.tag_paid_visits || 0);
            const invalid = Number(this.summary.unique_invalid_paid_clicks || this.summary.invalid_paid_visits || 0);
            return tracked ? Math.round((invalid / tracked) * 100) : 0;
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
        get whitelistedIpCount() {
            return (this.ips || []).filter((row) => row.is_allowlisted).length;
        },
        get highRiskIps() {
            const rank = { High: 3, Medium: 2, Low: 1 };
            return (this.sortedIps || [])
                .filter((row) => {
                    const level = String(row.risk_level || '');
                    return level === 'High' || level === 'Medium' || Number(row.risk_score || 0) >= 20;
                })
                .sort((a, b) => {
                    const lr = (rank[b.risk_level] || 0) - (rank[a.risk_level] || 0);
                    if (lr !== 0) return lr;
                    return Number(b.risk_score || 0) - Number(a.risk_score || 0);
                })
                .slice(0, 12);
        },
        toggleCompare() {
            this.compareEnabled = !this.compareEnabled;
            this.hiddenTrendSeries = this.compareEnabled
                ? { lastWeek: false, thisWeek: false }
                : { clicks: false, valid: false, invalid: false };
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
            return `https://flagcdn.com/w20/${code}.png`;
        },
        ipLabel(value) {
            const raw = String(value || '').trim();
            if (!raw) return '—';
            if (raw.length > 22) return raw.slice(0, 20) + '…';
            return raw;
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
        setWindow() {
            const today = new Date();
            const days = this.filters.window === 'monthly' ? 29 : 6;
            const start = new Date(today.getTime() - days * 86400000);
            this.filters.from = start.toISOString().slice(0, 10);
            this.filters.to = today.toISOString().slice(0, 10);
            window.promotixPageLoader?.show('Loading data…');
            this.reload(false, true);
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
        watermarkMs: 45000,
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
            window.promotixPageLoader?.show('Loading data…');
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
            try {
                const qs = this.ipsQueryString();
                this.ips = await fetch(`/paid-marketing/ips?${qs}`).then(r => r.json());
            } catch (e) {
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
            if (!this.livePollOn || document.hidden || this.reloadInFlight || this.summaryRefreshInFlight) return;
            if (Date.now() - this.lastReloadAt < 15000) return;
            try {
                const data = await fetch(`/paid-marketing/watermark?${this.qs()}`).then(r => r.json());
                if (this.lastWatermarkId !== null && data.last_id > this.lastWatermarkId) {
                    this.refreshSummaryOnly(false);
                }
                this.lastWatermarkId = data.last_id;
            } catch (e) { /* silent — next tick retries */ }
        },
        startWatermarkPoll() {
            clearInterval(this.watermarkTimer);
            this.watermarkTimer = setInterval(() => this.checkWatermark(), this.watermarkMs);
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
            this.applyDomainFromUrl();
            this.applyDomainTimezoneFromCatalog();
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const today = new Date();
                const days = this.filters.window === 'monthly' ? 29 : 6;
                const start = new Date(today.getTime() - days * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = today.toISOString().slice(0, 10);
            }
            this.startLivePoll();
            this.startGoogleSyncPoll();
            this.startWatermarkPoll();
            window.addEventListener('promotix:date-range', () => {
                this.syncHeaderDates();
                this.scheduleReload();
            });
            document.addEventListener('visibilitychange', () => {
                // Auto polls are off by default; no tab-focus refresh either.
                if (!document.hidden && this.livePollOn) this.refreshSummaryOnly(false);
            });
            window.addEventListener('promotix:export-ips-csv', () => this.exportIpsCsv());
            await this.reload(false, true);
            window.addEventListener('resize', () => {
                clearTimeout(window.__paidFigmaResize);
                window.__paidFigmaResize = setTimeout(() => this.render(true), 180);
            });
        },
        async reload(forceGoogle = false, withLoader = false) {
            if (this.reloadInFlight) {
                this.reloadQueued = true;
                this.reloadQueuedForceGoogle = this.reloadQueuedForceGoogle || forceGoogle;
                return;
            }
            this.reloadInFlight = true;
            if (withLoader) window.promotixPageLoader?.show('Refreshing dashboard…');
            try {
                const qs = this.qs(forceGoogle);
                const [summary, trends, blocking, campaigns, keywords, countries, ips, heatmap] = await Promise.all([
                    fetch(`/paid-marketing/summary?${qs}`).then(r => r.json()),
                    fetch(`/paid-marketing/trends?${qs}`).then(r => r.json()),
                    fetch(`/paid-marketing/blocking-activity?${qs}`).then(r => r.json()),
                    fetch(`/paid-marketing/campaigns?${qs}`).then(r => r.json()),
                    fetch(`/paid-marketing/keywords?${qs}`).then(r => r.json()),
                    fetch(`/paid-marketing/countries?${qs}`).then(r => r.json()),
                    fetch(`/paid-marketing/ips?${this.ipsQueryString()}`).then(r => r.json()),
                    fetch(`/paid-marketing/heatmap?${qs}`).then(r => r.json()),
                ]);
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
                this.trends = trends;
                this.blocking = blocking;
                this.campaigns = Array.isArray(campaigns) ? campaigns : (campaigns.campaigns || []);
                this.untaggedDomains = Array.isArray(campaigns) ? [] : (campaigns.untagged_domains || []);
                this.syncCampaignFilter();
                this.keywords = keywords;
                this.countries = countries;
                this.ips = ips;
                this.heatmap = heatmap;
                this.lastReloadAt = Date.now();
                this.$nextTick(() => this.render(false));
            } finally {
                this.reloadInFlight = false;
                if (withLoader) window.promotixPageLoader?.hide();
                if (this.reloadQueued) {
                    const queuedForce = this.reloadQueuedForceGoogle;
                    this.reloadQueued = false;
                    this.reloadQueuedForceGoogle = false;
                    this.reload(queuedForce, false);
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
            return date.toLocaleString('en-GB', {
                timeZone: this.userTimezone,
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
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
                this.drawProtectionLine('invalid-protection', this.blocking.labels || [], this.blocking.datasets || []);
                this.renderHeatmap();
                this.renderKeywords();
                this.renderCountries();
                this.updatePaidRightbar();
            });
        },
        trendsLegendItems() {
            if (this.compareEnabled) {
                const datasets = this.trends.datasets || [];
                if (datasets.length) {
                    return datasets.map(ds => ({
                        key: ds.dashed ? 'lastWeek' : 'thisWeek',
                        name: ds.name || (ds.dashed ? 'Last Week' : 'This Week'),
                        color: ds.color || (ds.dashed ? '#FF4BC1' : '#FFFFFF'),
                    }));
                }
                return [
                    { key: 'thisWeek', name: 'This Week', color: '#FFFFFF' },
                    { key: 'lastWeek', name: 'Last Week', color: '#FF4BC1' },
                ];
            }
            return [
                { key: 'clicks', name: 'Clicks', color: '#FFFFFF' },
                { key: 'valid', name: 'Valid', color: '#4ade80' },
                { key: 'invalid', name: 'Invalid', color: '#f87171' },
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
            return [
                { key: 'clicks', name: 'Clicks', values: paid, color: '#FFFFFF' },
                {
                    key: 'valid',
                    name: 'Valid',
                    values: paid.map((v, i) => Math.max(0, Number(v || 0) - Number(invalid[i] || 0))),
                    color: '#4ade80',
                },
                { key: 'invalid', name: 'Invalid', values: invalid, color: '#f87171' },
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
            if (!window.Chart) {
                if (retry < 20) {
                    requestAnimationFrame(() => this.renderCardCharts(retry + 1));
                }
                return;
            }
            this.destroyCardChart('invalidDonut');
            this.renderBotBars();
        },
        renderInvalidDonut() {
            // Donut removed from summary card layout; keep no-op for safety.
        },
        renderBotBars() {
            const el = document.getElementById('bot-bars');
            if (!el) return;
            const values = (this.trends.invalid_daily || []).slice(-7);
            const labels = (this.trends.labels || []).slice(-7);
            const barValues = values.length ? values : [0, 0, 0, 0, 0, 0, 0];
            const barLabels = labels.length ? labels : barValues.map((_, i) => i + 1);

            if (this.cardCharts.botBars) {
                const chart = this.cardCharts.botBars;
                chart.data.labels = barLabels;
                chart.data.datasets[0].data = barValues;
                chart.update('none');
                return;
            }

            this.cardCharts.botBars = new Chart(el, {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [{
                        data: barValues,
                        backgroundColor: (ctx) => {
                            const chart = ctx.chart;
                            const { chartArea } = chart;
                            if (!chartArea) return '#6625F8';
                            const g = chart.ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                            g.addColorStop(0, 'rgba(102,37,248,0.05)');
                            g.addColorStop(1, '#6625F8');
                            return g;
                        },
                        borderRadius: 3,
                        borderSkipped: false,
                    }],
                },
                options: this.miniChartOptions({
                    scales: {
                        x: { display: false, grid: { display: false } },
                        y: { display: false, grid: { display: false }, beginAtZero: true },
                    },
                }),
            });
        },
        bindPaidTrendHover(id, labels, datasets) {
            const canvas = document.getElementById(id);
            const tip = document.getElementById('paid-trends-tooltip');
            if (!canvas || !tip) return;
            if (canvas._paidHoverBound) return;
            canvas._paidHoverBound = true;

            canvas.addEventListener('mousemove', (e) => {
                const rect = canvas.getBoundingClientRect();
                const left = 36, right = 14;
                const x = e.clientX - rect.left;
                const innerW = rect.width - left - right;
                if (innerW <= 0 || labels.length === 0) return;
                const idx = Math.max(0, Math.min(labels.length - 1, Math.round(((x - left) / innerW) * (labels.length - 1))));
                this.trendsHoverIndex = idx;
                const visible = this.visibleTrendDatasets();
                const thisDs = visible.find(d => !d.dashed) || visible[0];
                const lastDs = visible.find(d => d.dashed) || visible[1];
                const thisVal = Number(thisDs?.values?.[idx] || 0);
                const lastVal = Number(lastDs?.values?.[idx] || 0);
                tip.hidden = false;
                const rows = [];
                if (thisDs && !this.isTrendSeriesHidden('thisWeek')) {
                    rows.push(`<span><i style="background:#6625F8"></i>This Week ${this.fmtCompact(thisVal)}</span>`);
                }
                if (lastDs && !this.isTrendSeriesHidden('lastWeek')) {
                    rows.push(`<span><i style="background:#FF4BC1"></i>Last Week ${this.fmtCompact(lastVal)}</span>`);
                }
                tip.innerHTML = `<strong>${labels[idx] || ''}</strong>${rows.join('')}`;
                tip.style.left = `${Math.min(Math.max(x, 60), rect.width - 60)}px`;
                tip.style.top = '12px';
                this.drawPaidTrendLine(id, labels, visible, idx);
            });
            canvas.addEventListener('mouseleave', () => {
                this.trendsHoverIndex = null;
                tip.hidden = true;
                this.drawPaidTrendLine(id, labels, this.visibleTrendDatasets(), null);
            });
        },
        drawPaidTrendLine(id, labels, datasets, hoverIndex = null) {
            const c = this.canvas(id);
            if (!c) return;
            const { ctx, w, h } = c;
            const series = (datasets || []).map(d => ({ ...d, values: d.values || [] }));
            const max = Math.max(...series.flatMap(d => d.values), 1);
            const left = 36, right = 14, top = 16, bottom = 28;
            const xStep = (w - left - right) / Math.max(labels.length - 1, 1);
            const yAt = v => h - bottom - (Number(v) / max) * (h - top - bottom);

            ctx.strokeStyle = 'rgba(255,255,255,.14)';
            ctx.lineWidth = 1;
            for (let i = 0; i < 6; i++) {
                const y = top + i * ((h - top - bottom) / 5);
                ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(w - right, y); ctx.stroke();
            }

            ctx.fillStyle = 'rgba(255,255,255,0.45)';
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
                grad.addColorStop(0, 'rgba(102,37,248,0.38)');
                grad.addColorStop(1, 'rgba(102,37,248,0.02)');
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
                ctx.strokeStyle = ds.color || '#fff';
                ctx.lineWidth = ds.dashed ? 1 : 1.5;
                ctx.setLineDash(ds.dashed ? [5, 4] : []);
                ctx.beginPath();
                pts.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
                ctx.stroke();
                ctx.setLineDash([]);
            });

            if (hoverIndex != null && labels[hoverIndex] != null) {
                const x = left + hoverIndex * xStep;
                ctx.strokeStyle = 'rgba(255,255,255,0.55)';
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(x, top);
                ctx.lineTo(x, h - bottom);
                ctx.stroke();
                ctx.setLineDash([]);
                series.forEach(ds => {
                    const v = Number(ds.values[hoverIndex] || 0);
                    ctx.beginPath();
                    ctx.fillStyle = ds.dashed ? '#FF4BC1' : '#6625F8';
                    ctx.arc(x, yAt(v), 4, 0, Math.PI * 2);
                    ctx.fill();
                });
            }

            ctx.fillStyle = '#D9D9D9';
            ctx.font = '10px Inter, sans-serif';
            labels.forEach((l, i) => ctx.fillText(String(l).slice(0, 3), left + i * xStep - 8, h - 8));
        },
        drawProtectionLine(id, labels, datasets) {
            const c = this.canvas(id);
            if (!c) return;
            const { ctx, w, h } = c;
            const series = datasets.map(d => d.values || []);
            const max = Math.max(...series.flat(), 1);
            const left = 28, right = 10, top = 8, bottom = 22;
            const colors = ['#6625F8', '#FFFFFF'];
            ctx.strokeStyle = 'rgba(255,255,255,.16)';
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
            ctx.fillStyle = '#9D9D9D';
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
            const days = this.heatmap.days?.length ? this.heatmap.days : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const hours = this.heatmap.hours?.length ? this.heatmap.hours : Array.from({ length: 24 }, (_, i) => i);
            const matrix = this.heatmap.matrix || [];
            const flat = matrix.flat();
            const max = Math.max(...flat, 1);
            // Show every 3rd hour label to keep mobile readable
            const hourTicks = [0, 3, 6, 9, 12, 15, 18, 21];
            const head = ['<div class="paid-heatmap-corner"></div>']
                .concat(hourTicks.map((h) => {
                    const label = h === 0 ? '12a' : h < 12 ? `${h}a` : h === 12 ? '12p' : `${h - 12}p`;
                    return `<div class="paid-heatmap-hour">${label}</div>`;
                }))
                .join('');
            const body = days.map((day, d) => {
                const cells = hourTicks.map((h) => {
                    const v = Number(matrix?.[d]?.[h] || 0);
                    const t = max ? v / max : 0;
                    const bg = t > 0.65 ? '#6625F8' : t > 0.35 ? '#8B4FD4' : t > 0.1 ? 'rgba(139,79,212,0.45)' : 'rgba(255,255,255,0.14)';
                    return `<span class="paid-heatmap-cell" title="${day} ${h}:00 — ${v}" style="background:${bg}"></span>`;
                }).join('');
                return `<div class="paid-heatmap-day">${day}</div>${cells}`;
            }).join('');
            el.innerHTML = `${head}${body}`;
        },
        renderKeywords() {
            const el = document.getElementById('keyword-list');
            if (!el) return;
            const rows = (this.keywords || []).slice(0, 6);
            if (!rows.length) {
                el.innerHTML = '<p class="text-[10px] text-white/70">No keyword data.</p>';
                return;
            }
            el.innerHTML = `
                <table class="paid-keyword-table">
                    <thead>
                        <tr>
                            <th>Keyword</th>
                            <th>Clicks</th>
                            <th>Inv %</th>
                            <th>Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => {
                            const pct = row.invalid_pct != null ? row.invalid_pct : (row.risk != null ? row.risk : 0);
                            const level = pct >= 40 ? 'High' : pct >= 20 ? 'Medium' : 'Low';
                            const cls = level === 'High' ? 'is-high' : level === 'Medium' ? 'is-medium' : 'is-low';
                            return `<tr>
                                <td class="truncate" title="${String(row.keyword || '').replace(/"/g, '&quot;')}">${row.keyword || '—'}</td>
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
