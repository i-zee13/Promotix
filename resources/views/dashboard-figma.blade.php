@extends('layouts.admin')

@section('title', 'Overview')

@section('rightbar')
<div class="figma-rightbar-default ov-rightbar">
    <div class="mb-[16px]">
        <p class="text-[18px] font-bold leading-none text-[#a9a9a9]">Digital Promotix</p>
        <p class="mt-[4px] text-[9px] text-white/45">Account panel</p>
    </div>

    <div class="mb-[6px]">
        <h2 class="mb-[8px] text-[14px] font-bold text-[#a9a9a9]">Recent Activity</h2>
        <div id="right-notifications" class="figma-rightbar-notify space-y-[10px] border-b-2 border-[#5a2a99] pb-[12px] text-[9px] text-[#a9a9a9]"></div>
    </div>

    <div class="mt-[16px]">
        <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">Add Account</h2>
        <a href="{{ route('integrations') }}" class="figma-rightbar-add-card block rounded-[3px] bg-[#6400B2] p-[6px]">
            <div class="flex h-[96px] items-center justify-center bg-[#6400B2]">
                <svg class="h-[44px] w-[44px]" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.6-6 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.5 16.1 18.9 13 24 13c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/>
                    <path fill="#4CAF50" d="M24 44c5.4 0 10.3-2.1 14-5.5l-6.5-5.3C29.3 35.1 26.8 36 24 36c-5.2 0-9.6-3.4-11.2-8.1l-6.6 5.1C9.5 39.6 16.2 44 24 44z"/>
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.1 4.3-3.9 5.9l.1.1 6.5 5.3C39.8 35.8 44 30.5 44 24c0-1.3-.1-2.7-.4-3.5z"/>
                </svg>
            </div>
            <div class="mt-[2px] flex h-[24px] items-center justify-between border border-white px-[8px] text-[11px] text-white">
                <span>Connect Google Ads</span>
                <span class="flex h-[11px] w-[11px] items-center justify-center rounded-full border border-white text-[8px]">+</span>
            </div>
        </a>
    </div>

    <div class="mt-[18px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">Tools</h2>
        <div class="grid w-full max-w-[168px] grid-cols-3 gap-x-[14px] gap-y-[14px]">
            <a href="{{ route('paid-marketing.detailed') }}" title="IP Lookup" class="ov-tool-btn">
                @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[18px] w-[18px]'])
            </a>
            <a href="{{ route('domains.index') }}" title="Tag Generator" class="ov-tool-btn">
                @include('partials.sidebar-icon', ['name' => 'tag', 'class' => 'h-[18px] w-[18px]'])
            </a>
            <a href="{{ route('integrations') }}" title="Integrations" class="ov-tool-btn">
                @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[18px] w-[18px]'])
            </a>
            <a href="{{ route('paid-marketing.detection-settings') }}" title="Detection" class="ov-tool-btn">
                @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[18px] w-[18px]'])
            </a>
            <a href="{{ route('reports.index') }}" title="Reports" class="ov-tool-btn">
                @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[18px] w-[18px]'])
            </a>
            <a href="{{ route('profile.edit') }}" title="Settings" class="ov-tool-btn">
                @include('partials.sidebar-icon', ['name' => 'settings', 'class' => 'h-[18px] w-[18px]'])
            </a>
        </div>
    </div>

    <div class="mt-[18px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">System Overview</h2>
        <div class="space-y-[8px] text-[10px] text-white/75">
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Server status</span><span id="sys-server-status" class="text-emerald-200">Online</span>
            </div>
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Events today</span><span id="sys-events-today" class="text-white/90">—</span>
            </div>
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Tracking version</span><span id="sys-tracking-version" class="text-white/90">—</span>
            </div>
            <div class="flex items-center justify-between rounded-[6px] bg-[#0B0B0B]/70 px-[10px] py-[8px]">
                <span>Google Ads API</span><span id="sys-google-api" class="text-white/90">—</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<script>
    document.documentElement.classList.add('ov-boot-lock');
    if (window.promotixPageLoader) {
        window.promotixPageLoader.show('Loading Overview…');
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            window.promotixPageLoader?.show('Loading Overview…');
        });
    }
</script>
<div class="brand-page-bg min-h-[calc(100vh-49px)]">
    <section id="ov-page" class="ov-page ov-page--booting mx-auto w-full max-w-[1120px] px-[12px] pb-[22px] pt-[18px] sm:px-[18px] xl:max-w-none xl:px-[22px] xl:pt-[20px]">
        <div class="mb-[12px] flex flex-col gap-[10px] xl:flex-row xl:items-start xl:justify-between">
            <h1 class="text-[28px] font-normal leading-none text-white sm:text-[31px]">Overview</h1>
            <div class="figma-filter-bar figma-filter-bar--overview ov-filter-bar flex min-h-[54px] w-full max-w-[720px] flex-wrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_2px_10px_rgba(0,0,0,.35)] xl:max-w-[720px]">
                <label class="flex min-w-[130px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                    <div class="figma-filter-select-wrap">
                        <select id="domain-filter" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Domains</option>
                            @foreach ($domains as $domain)
                                <option value="{{ $domain->id }}" @selected((string) request('domain_id') === (string) $domain->id)>{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex min-w-[120px] flex-1 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Traffic Source</span>
                    <div class="figma-filter-select-wrap">
                        <select id="traffic-source-filter" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="google_ads" selected>Google Ads</option>
                            <option value="meta_ads" disabled>Meta Ads</option>
                            <option value="microsoft_ads" disabled>Microsoft Ads</option>
                        </select>
                    </div>
                </label>
                <label class="flex min-w-[140px] flex-[1.1] flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input id="path-filter" value="{{ request('path', '') }}" placeholder="All Pages" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                <input type="hidden" id="campaign-filter" value="">
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        {{-- Top: Suite protection cards (mockup) + Live Security Feed --}}
        <div class="ov-top-grid">
            <div class="ov-suite-pair">
            <article class="ov-suite-card">
                <div class="ov-suite-card__head">
                    <div class="ov-suite-card__title-row">
                        <span class="ov-suite-card__brand" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#FBBC04" d="M12 2.5l9.5 16.5H2.5L12 2.5z"/><path fill="#4285F4" d="M12 2.5l9.5 16.5h-6.2L12 2.5z"/><path fill="#34A853" d="M8.7 19H2.5L12 2.5 8.7 19z"/></svg>
                        </span>
                        <h2 class="ov-suite-card__title">Google Ads Protection</h2>
                    </div>
                    <span class="ov-suite-card__badge" aria-hidden="true" title="Ads protection">
                        <img src="{{ asset('images/overview-icons/shield-dollar.png') }}" alt="" class="ov-suite-icon-img ov-suite-icon-img--lg">
                    </span>
                </div>
                <div class="ov-suite-card__metrics">
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--clicks" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/cursor-click.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Total Clicks</p>
                        <p id="suite-paid-clicks" class="ov-suite-stat__value">--</p>
                        <p id="suite-paid-clicks-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--valid" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/shield-check.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Valid Clicks</p>
                        <p id="suite-paid-valid" class="ov-suite-stat__value">--</p>
                        <p id="suite-paid-valid-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--invalid" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/ban.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Invalid Clicks</p>
                        <p id="suite-paid-visits" class="ov-suite-stat__value">--</p>
                        <p id="suite-paid-visits-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--rate" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/gauge.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Protection Rate</p>
                        <p id="suite-paid-rate" class="ov-suite-stat__value">0.00%</p>
                        <p id="suite-paid-rate-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                </div>
                <a href="{{ route('paid-marketing.dashboard') }}" class="ov-suite-card__cta">View Detailed Report →</a>
            </article>

            <article class="ov-suite-card">
                <div class="ov-suite-card__head">
                    <div class="ov-suite-card__title-row">
                        <span class="ov-suite-card__brand ov-suite-card__brand--bot" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/cpu-bolt.png') }}" alt="" class="ov-suite-icon-img ov-suite-icon-img--md">
                        </span>
                        <h2 class="ov-suite-card__title">Bot Protection</h2>
                    </div>
                    <span class="ov-suite-card__badge" aria-hidden="true" title="Bot protection">
                        <img src="{{ asset('images/overview-icons/bug-scan.png') }}" alt="" class="ov-suite-icon-img ov-suite-icon-img--lg">
                    </span>
                </div>
                <div class="ov-suite-card__metrics">
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--clicks" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/users.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Total Visitors</p>
                        <p id="suite-bot-visitors" class="ov-suite-stat__value">--</p>
                        <p id="suite-bot-visitors-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--invalid" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/bug-scan.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Bots Detected</p>
                        <p id="suite-bot-detected" class="ov-suite-stat__value">--</p>
                        <p id="suite-bot-detected-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--valid" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/lock.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Blocked Bots</p>
                        <p id="suite-bot-blocked" class="ov-suite-stat__value">--</p>
                        <p id="suite-bot-blocked-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                    <div class="ov-suite-stat">
                        <span class="ov-suite-stat__icon ov-suite-stat__icon--rate" aria-hidden="true">
                            <img src="{{ asset('images/overview-icons/gauge.png') }}" alt="" class="ov-suite-icon-img">
                        </span>
                        <p class="ov-suite-stat__label">Detection Rate</p>
                        <p id="suite-bot-rate" class="ov-suite-stat__value">0.00%</p>
                        <p id="suite-bot-rate-delta" class="ov-suite-stat__delta is-flat">—</p>
                        <p class="ov-suite-stat__vs">vs previous period</p>
                    </div>
                </div>
                <a href="{{ route('bot-protection.dashboard') }}" class="ov-suite-card__cta">View Bot Report →</a>
            </article>
            </div>

            <article class="ov-card ov-card--feed">
                <div class="ov-card__head">
                    <h2 class="ov-card__title">Live Security Feed</h2>
                    <a href="{{ route('paid-marketing.detailed') }}" class="ov-card__link">View All</a>
                </div>
                <div id="insight-list" class="ov-feed-list promotix-slim-scroll">
                    <div class="text-white/60">Loading insights…</div>
                </div>
            </article>
        </div>

        {{-- Charts: Traffic Quality + Threat Breakdown --}}
        <div class="ov-charts-grid">
            <section class="ov-card ov-card--chart">
                <div class="ov-card__head">
                    <div>
                        <h2 class="ov-card__title">Traffic Quality Overview</h2>
                        <div class="ov-chart-legend" id="traffic-quality-legend"></div>
                    </div>
                </div>
                <div class="ov-chart-area">
                    <canvas id="trends-chart" class="ov-chart-canvas"></canvas>
                </div>
            </section>

            <section class="ov-card ov-card--donut">
                <div class="ov-card__head">
                    <h2 class="ov-card__title">Threat Breakdown</h2>
                </div>
                <div class="ov-donut-wrap">
                    <canvas id="threats-chart" class="ov-donut-canvas"></canvas>
                    <div id="threats-donut-center" class="ov-donut-center is-empty" aria-hidden="true">
                        <span id="threats-donut-pct" class="ov-donut-center__pct">—</span>
                        <span id="threats-donut-label" class="ov-donut-center__label">Invalid</span>
                    </div>
                </div>
                <div id="chart-legend" class="ov-threat-legend"></div>
            </section>
        </div>

        {{-- Domain Performance --}}
        <section class="ov-card ov-card--table">
            <div class="mb-[10px] flex flex-col gap-[8px] sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="ov-card__title">Overall Domain Performance</h2>
                    <div class="mt-[6px] flex gap-[14px] border-b border-white/40 pb-[4px] text-[10px] text-white/85">
                        <button type="button" id="domain-tab-all" class="border-b-2 border-white pb-[2px] text-white">All</button>
                        <button type="button" id="domain-tab-invalid" class="pb-[2px] text-white/60 hover:text-white">Invalid</button>
                        <button type="button" id="domain-tab-pending" class="pb-[2px] text-white/60 hover:text-white">Pending</button>
                    </div>
                </div>
                <div class="relative w-full sm:w-[150px]">
                    <span class="absolute left-[7px] top-1/2 -translate-y-1/2 text-white/70">
                        <svg class="h-[9px] w-[9px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input id="domain-search" type="search" placeholder="Search domain" autocomplete="off" class="h-[26px] w-full rounded-[3px] border border-black/40 bg-[#0B0B0B] pl-[22px] pr-[6px] text-[10px] text-white focus:border-white/60 focus:ring-0">
                </div>
            </div>
            <div class="overflow-x-auto rounded-[4px] border border-white/15">
                <table class="ov-table min-w-[860px] w-full text-left text-[11px] text-white">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Clicks</th>
                            <th>Visitors</th>
                            <th>Invalid %</th>
                            <th>Invalid Clicks</th>
                            <th>Threats</th>
                            <th>Risk Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="domain-performance-body">
                        <tr><td colspan="8" class="px-[8px] py-[10px] text-center text-white/75">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Bottom: Campaigns + Quick Stats + Connection --}}
        <div class="ov-bottom-grid">
            <section class="ov-card ov-card--table">
                <div class="ov-card__head">
                    <h2 class="ov-card__title">Campaign Performance</h2>
                    <span class="rounded-[4px] bg-black/40 px-[8px] py-[3px] text-[9px] text-white/70">Top 5</span>
                </div>
                <div class="overflow-x-auto rounded-[4px] border border-white/15">
                    <table class="ov-table min-w-[560px] w-full text-left text-[11px] text-white">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Clicks</th>
                                <th>Valid</th>
                                <th>Invalid</th>
                                <th>Cost Saved</th>
                            </tr>
                        </thead>
                        <tbody id="campaign-performance-body">
                            <tr><td colspan="5" class="px-[8px] py-[10px] text-center text-white/75">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ov-card">
                <h2 class="ov-card__title mb-[10px]">Quick Stats</h2>
                <div class="ov-quick-stats">
                    <div class="ov-quick-stat">
                        <p class="ov-metric-label">Total Clicks</p>
                        <p id="quick-total-clicks" class="ov-metric-value">--</p>
                    </div>
                    <div class="ov-quick-stat">
                        <p class="ov-metric-label">Invalid Clicks</p>
                        <p id="quick-invalid-clicks" class="ov-metric-value">--</p>
                    </div>
                    <div class="ov-quick-stat">
                        <p class="ov-metric-label">Save Amount</p>
                        <p id="quick-cost-saved" class="ov-metric-value">--</p>
                    </div>
                    <div class="ov-quick-stat">
                        <p class="ov-metric-label">Blocked Today</p>
                        <p id="quick-blocked-today" class="ov-metric-value">--</p>
                    </div>
                </div>
            </section>

            <section class="ov-card">
                <h2 class="ov-card__title mb-[10px]">Connection Status</h2>
                <div class="space-y-[7px] text-[11px] text-white/85">
                    <div class="ov-conn-row"><span>Tracking Script</span><span id="conn-tracking">—</span></div>
                    <div class="ov-conn-row"><span>Data Ingestion</span><span id="conn-ingestion">—</span></div>
                    <div class="ov-conn-row"><span>Google Ads API</span><span id="conn-google-api">—</span></div>
                    <div class="ov-conn-row"><span>Detection Engine</span><span id="conn-detection">—</span></div>
                    <div class="ov-conn-row"><span>Last sync</span><span id="conn-last-sync">—</span></div>
                    <div class="ov-conn-row"><span>Last event</span><span id="conn-last-event">—</span></div>
                </div>
            </section>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));
    const money = (n) => `$${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
    const FILTER_DEBOUNCE_MS = window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500;

    let threatsChart = null;
    let hiddenThreatSlices = {};
    let lastThreatLegend = { labels: [], values: [] };
    let domainRows = [];
    let domainFilter = 'all';
    let domainSearch = '';
    const donutColors = ['#D9D9D9', '#FFFFFF', '#B893D8', '#8C8C8C', '#F0ABFC', '#67E8F9'];

    function retina(canvas) {
        const dpr = window.devicePixelRatio || 1;
        const w = Math.max(canvas.clientWidth || 0, 1);
        const h = Math.max(canvas.clientHeight || 0, 1);
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx, w, h };
    }

    async function json(url) {
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(`${url} (${res.status})`);
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error(`${url} (non-json)`);
        return res.json();
    }

    function dateParams() {
        try {
            const r = JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
            const c = JSON.parse(localStorage.getItem('promotix-date-compare') || '{}');
            const p = new URLSearchParams();
            if (r.from) p.set('from', r.from);
            if (r.to) p.set('to', r.to);
            if (c.enabled) p.set('compare', '1');
            return p;
        } catch (e) {
            return new URLSearchParams();
        }
    }

    function filterParams() {
        const params = dateParams();
        const domainId = document.getElementById('domain-filter')?.value || '';
        const path = document.getElementById('path-filter')?.value || '';
        const trafficSource = document.getElementById('traffic-source-filter')?.value || '';
        if (domainId) params.set('domain_id', domainId);
        if (path) params.set('path', path);
        if (trafficSource) params.set('traffic_source', trafficSource);
        return params;
    }

    function apiUrl(path) {
        const qs = filterParams().toString();
        return qs ? `${path}?${qs}` : path;
    }

    function deltaLabel(delta) {
        if (delta == null || Number.isNaN(Number(delta))) return '';
        const n = Number(delta);
        const sign = n > 0 ? '+' : '';
        const tone = n > 0 ? 'text-rose-200' : (n < 0 ? 'text-emerald-200' : 'text-white/45');
        return `<span class="ml-1 text-[9px] ${tone}">${sign}${n}</span>`;
    }

    function pctFromAbsDelta(current, absDelta) {
        if (absDelta == null || Number.isNaN(Number(absDelta))) return null;
        const cur = Number(current || 0);
        const d = Number(absDelta);
        const prev = cur - d;
        if (prev === 0) return d === 0 ? 0 : 100;
        return Math.round((d / Math.abs(prev)) * 1000) / 10;
    }

    function setSuiteStat(id, value, absDelta, { isRate = false } = {}) {
        const el = document.getElementById(id);
        const deltaEl = document.getElementById(`${id}-delta`);
        if (el) {
            el.textContent = isRate
                ? `${Number(value ?? 0).toFixed(2)}%`
                : fmt(value);
        }
        if (!deltaEl) return;

        let shown = null;
        if (absDelta != null && !Number.isNaN(Number(absDelta))) {
            shown = isRate ? Number(absDelta) : pctFromAbsDelta(value, absDelta);
        }

        if (shown == null || Number.isNaN(shown)) {
            deltaEl.textContent = '—';
            deltaEl.className = 'ov-suite-stat__delta is-flat';
            return;
        }

        const n = Number(shown);
        const arrow = n > 0 ? '↑' : (n < 0 ? '↓' : '·');
        deltaEl.textContent = `${arrow} ${Math.abs(n).toFixed(n % 1 ? 1 : 0)}%`;
        deltaEl.className = 'ov-suite-stat__delta ' + (n > 0 ? 'is-up' : (n < 0 ? 'is-down' : 'is-flat'));
    }

    function setMetric(id, value, delta) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.classList.contains('ov-suite-stat__value')) {
            setSuiteStat(id, value, delta, { isRate: id.endsWith('-rate') });
            return;
        }
        el.innerHTML = `${fmt(value)}${deltaLabel(delta)}`;
    }

    function setConn(id, text, healthyRe = /healthy|online|active|connected|ready/i) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = text || '—';
        el.className = healthyRe.test(text || '') ? 'text-emerald-200' : (/error|failed|pending|idle|waiting|not connected/i.test(text || '') ? 'text-amber-100' : 'text-white/90');
    }

    function riskTone(level) {
        const l = String(level || '').toLowerCase();
        if (l === 'high') return 'text-rose-200';
        if (l === 'medium') return 'text-amber-200';
        return 'text-emerald-200';
    }

    function drawTrendDual(labels, datasets) {
        const canvas = document.getElementById('trends-chart');
        if (!canvas) return;
        const { ctx, w, h } = retina(canvas);
        ctx.clearRect(0, 0, w, h);
        const series = (datasets || []).map((d) => ({ ...d, values: d.values || [] }));
        const max = Math.max(...series.flatMap((d) => d.values), 1);
        const left = 34, right = 12, top = 16, bottom = 28;

        ctx.strokeStyle = 'rgba(255,255,255,.14)';
        ctx.lineWidth = 1;
        for (let i = 0; i < 5; i++) {
            const y = top + i * ((h - top - bottom) / 4);
            ctx.beginPath();
            ctx.moveTo(left, y);
            ctx.lineTo(w - right, y);
            ctx.stroke();
        }

        series.forEach((ds) => {
            const values = ds.values;
            const points = values.map((value, i) => ({
                x: left + i * ((w - left - right) / Math.max(values.length - 1, 1)),
                y: h - bottom - (Number(value || 0) / max) * (h - top - bottom),
            }));
            ctx.strokeStyle = ds.color || '#B893D8';
            ctx.lineWidth = 2;
            ctx.beginPath();
            points.forEach((p, i) => (i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y)));
            ctx.stroke();
            ctx.fillStyle = ds.color || '#B893D8';
            points.forEach((p) => {
                ctx.beginPath();
                ctx.arc(p.x, p.y, 2.2, 0, Math.PI * 2);
                ctx.fill();
            });
        });

        ctx.fillStyle = 'rgba(255,255,255,.55)';
        ctx.font = '10px sans-serif';
        const step = Math.max(1, Math.ceil((labels || []).length / 7));
        (labels || []).forEach((label, i) => {
            if (i % step !== 0 && i !== labels.length - 1) return;
            const x = left + i * ((w - left - right) / Math.max(labels.length - 1, 1));
            ctx.fillText(String(label), x - 12, h - 8);
        });
    }

    function renderTrafficLegend(datasets) {
        const el = document.getElementById('traffic-quality-legend');
        if (!el) return;
        el.innerHTML = (datasets || []).map((ds) => (
            `<span class="ov-legend-item"><i style="background:${ds.color}"></i>${ds.name}</span>`
        )).join('');
    }

    function updateDonutCenter(labels, values, absoluteTotal = null) {
        const pctEl = document.getElementById('threats-donut-pct');
        const labelEl = document.getElementById('threats-donut-label');
        const wrapEl = document.getElementById('threats-donut-center');
        if (!pctEl || !labelEl || !wrapEl) return;

        const visible = (values || []).map((value, index) => (hiddenThreatSlices[index] ? 0 : Number(value || 0)));
        const total = absoluteTotal != null ? Number(absoluteTotal) : visible.reduce((sum, value) => sum + value, 0);

        if (total <= 0 || !labels?.length) {
            pctEl.textContent = '—';
            labelEl.textContent = 'Invalid';
            wrapEl.classList.add('is-empty');
            return;
        }

        pctEl.textContent = fmt(total);
        labelEl.textContent = 'Invalid';
        wrapEl.classList.remove('is-empty');
    }

    function renderDonut(labels, values, absoluteTotal = null) {
        const canvas = document.getElementById('threats-chart');
        if (!canvas || !window.Chart) return;
        if (threatsChart) {
            threatsChart.destroy();
            threatsChart = null;
        }

        const safeLabels = labels || [];
        const safeValues = (values || []).map((value) => Number(value || 0));
        const total = safeValues.reduce((sum, value) => sum + value, 0);
        const chartLabels = total > 0 ? safeLabels : ['No data'];
        const chartValues = total > 0 ? safeValues : [1];
        const chartColors = total > 0
            ? chartLabels.map((_, index) => donutColors[index % donutColors.length])
            : ['rgba(255,255,255,0.15)'];

        threatsChart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartValues,
                    backgroundColor: chartColors,
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    hoverBorderColor: '#ffffff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            label: (ctx) => total > 0 ? `${ctx.label}: ${fmt(ctx.raw)}` : 'No threat groups',
                        },
                    },
                },
            },
        });

        updateDonutCenter(safeLabels, safeValues, absoluteTotal ?? total);
    }

    function renderThreatLegend(labels, values) {
        lastThreatLegend = { labels: labels || [], values: values || [] };
        const legend = document.getElementById('chart-legend');
        if (!legend) return;
        if (!labels?.length) {
            legend.innerHTML = '<span class="text-white/60">No threat groups in range yet.</span>';
            return;
        }
        const sum = (values || []).reduce((a, b) => a + Number(b || 0), 0) || 1;
        legend.innerHTML = labels.map((label, i) => {
            const hidden = hiddenThreatSlices[i];
            const pct = Math.round((Number((values || [])[i] || 0) / sum) * 1000) / 10;
            return `<button type="button" class="chart-legend-item${hidden ? ' is-hidden' : ''}" data-slice="${i}"><i class="mr-[5px] inline-block h-[7px] w-[7px] rounded-[2px]" style="background:${donutColors[i % donutColors.length]}"></i>${label} (${pct}%)</button>`;
        }).join('');
        legend.querySelectorAll('[data-slice]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const i = Number(btn.dataset.slice);
                hiddenThreatSlices[i] = !hiddenThreatSlices[i];
                if (threatsChart) {
                    threatsChart.toggleDataVisibility(i);
                    threatsChart.update();
                }
                renderThreatLegend(lastThreatLegend.labels, lastThreatLegend.values);
                updateDonutCenter(lastThreatLegend.labels, lastThreatLegend.values);
            });
        });
    }

    function renderDomainTable() {
        const body = document.getElementById('domain-performance-body');
        if (!body) return;
        const q = domainSearch.trim().toLowerCase();
        const filtered = domainRows.filter((row) => {
            if (q && !(row.domain || '').toLowerCase().includes(q)) return false;
            if (domainFilter === 'invalid') return (row.threats || 0) > 0 || (row.invalidPct || 0) > 0;
            if (domainFilter === 'pending') return row.pending;
            return true;
        });
        body.innerHTML = filtered.length ? filtered.map((row) => `
            <tr>
                <td>${row.domain}${row.pending ? ' <span class="text-[9px] text-amber-200">(pending)</span>' : ''}</td>
                <td>${fmt(row.clicks ?? row.visits)}</td>
                <td>${fmt(row.visitors ?? row.visits)}</td>
                <td>${Number(row.invalidPct || 0).toFixed(1)}%</td>
                <td>${fmt(row.invalidClicks ?? row.threats ?? 0)}</td>
                <td>${fmt(row.threats || 0)}</td>
                <td class="${riskTone(row.riskLevel)}">${row.riskLevel || 'Low'}</td>
                <td>${row.status || (row.pending ? 'Pending' : 'Active')}</td>
            </tr>
        `).join('') : '<tr><td colspan="8" class="px-[8px] py-[10px] text-center text-white/75">No domains match this filter.</td></tr>';
    }

    function setDomainTab(tab) {
        domainFilter = tab;
        ['all', 'invalid', 'pending'].forEach((name) => {
            const btn = document.getElementById(`domain-tab-${name}`);
            if (!btn) return;
            const active = name === tab;
            btn.classList.toggle('border-b-2', active);
            btn.classList.toggle('border-white', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('text-white/60', !active);
        });
        renderDomainTable();
    }

    async function loadSummary() {
        const params = filterParams();
        params.set('compare', '1');
        const qs = params.toString();
        const data = await json(qs ? `/overview/summary?${qs}` : '/overview/summary?compare=1');
        const paid = data.paidAdvertising || {};
        const bot = data.botProtection || {};
        const compare = data.compare || {};
        const paidDelta = compare.paidAdvertising || {};
        const botDelta = compare.botProtection || {};
        const quick = data.quickStats || {};
        const conn = data.connectionStatus || {};

        setSuiteStat('suite-paid-clicks', paid.googleAdsClicks ?? paid.visits, paidDelta.googleAdsClicks ?? paidDelta.visits);
        setSuiteStat('suite-paid-valid', paid.validClicks ?? Math.max(0, Number(paid.visits || 0) - Number(paid.invalidVisits || 0)), paidDelta.validClicks);
        setSuiteStat('suite-paid-visits', paid.invalidClicks ?? paid.invalidVisits, paidDelta.invalidClicks ?? paidDelta.invalidVisits);
        setSuiteStat('suite-paid-rate', paid.protectionRate ?? paid.invalidRate ?? 0, paidDelta.protectionRate ?? paidDelta.invalidRate, { isRate: true });
        setSuiteStat('suite-bot-visitors', bot.totalVisitors, botDelta.totalVisitors);
        setSuiteStat('suite-bot-detected', bot.botsDetected ?? bot.blockedHits, botDelta.botsDetected ?? botDelta.blockedHits);
        setSuiteStat('suite-bot-blocked', bot.blockedHits, botDelta.blockedHits);
        setSuiteStat('suite-bot-rate', bot.detectionRate ?? bot.invalidRate ?? 0, botDelta.detectionRate ?? botDelta.invalidRate, { isRate: true });

        document.getElementById('quick-total-clicks').textContent = fmt(quick.totalClicks ?? paid.visits ?? 0);
        document.getElementById('quick-invalid-clicks').textContent = fmt(quick.invalidClicks ?? paid.invalidVisits ?? 0);
        document.getElementById('quick-cost-saved').textContent = money(quick.costSaved ?? 0);
        document.getElementById('quick-blocked-today').textContent = fmt(quick.blockedToday ?? bot.blockedHits ?? 0);

        setConn('conn-tracking', conn.tracking);
        setConn('conn-ingestion', conn.ingestion || 'Waiting for traffic');
        setConn('conn-google-api', conn.googleAdsApi || 'Not connected');
        setConn('conn-detection', conn.detectionEngine || conn.protection || 'Idle');

        const lastSyncEl = document.getElementById('conn-last-sync');
        if (lastSyncEl) {
            lastSyncEl.textContent = conn.lastSyncAt
                ? new Date(conn.lastSyncAt).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                : '—';
            lastSyncEl.className = 'text-white/80';
        }
        const lastEventEl = document.getElementById('conn-last-event');
        if (lastEventEl) {
            lastEventEl.textContent = conn.lastEventAt
                ? new Date(conn.lastEventAt).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                : '—';
            lastEventEl.className = 'text-white/80';
        }

        document.getElementById('sys-events-today').textContent = fmt(conn.eventsToday ?? 0);
        document.getElementById('sys-tracking-version').textContent = conn.trackingVersion || '—';
        setConn('sys-google-api', conn.googleAdsApi || 'Not connected');
    }

    async function loadInsights() {
        const list = document.getElementById('insight-list');
        try {
            const d = await json(apiUrl('/insights'));
            const feed = Array.isArray(d.feed) ? d.feed : [];
            if (!feed.length) {
                list.innerHTML = `<article class="ov-feed-item"><p class="text-white/65">No high-risk detections in this range yet.</p></article>`;
                return;
            }
            list.innerHTML = feed.map((item) => {
                const severity = item.severity || 'medium';
                const bar = severity === 'high' ? '#ef4444' : (severity === 'medium' ? '#f59e0b' : '#60a5fa');
                const reasons = (item.reasons || []).slice(0, 2).map((r) => String(r).replace(/_/g, ' ')).join(' · ') || 'Review signals';
                const time = item.at ? new Date(item.at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
                const ip = String(item.ip || '').replace(/"/g, '&quot;');
                const advancedHref = ip
                    ? `{{ route('paid-marketing.detailed') }}?ip=${encodeURIComponent(ip)}`
                    : `{{ route('paid-marketing.detailed') }}`;
                return `
                <article class="ov-feed-item" style="border-left-color:${bar}" data-ip="${ip}"
                         onclick="window.dispatchEvent(new CustomEvent('promotix-open-ip-modal', { detail: { ip: this.dataset.ip } }))">
                    <div class="mb-[2px] flex items-center justify-between gap-2">
                        <span class="truncate font-semibold text-white">${item.title || 'High Risk Click Detected'}</span>
                        <span class="ov-feed-sev shrink-0" style="background:${bar}33;color:${bar}">${severity}</span>
                    </div>
                    <div class="truncate text-white/70">
                        <span class="text-white/40">IP</span> ${item.ip || '—'}
                        <span class="text-white/35"> · </span>
                        <span class="text-white/40">Risk</span> ${Number(item.risk || 0)}%
                        <span class="text-white/35"> · </span>
                        ${item.action || '—'}
                    </div>
                    <div class="mt-[3px] flex items-center justify-between gap-2">
                        <span class="truncate text-[9px] text-white/45">${reasons}</span>
                        <span class="flex shrink-0 items-center gap-2">
                            ${time ? `<span class="text-[9px] text-white/40">${time}</span>` : ''}
                            <a href="${advancedHref}" class="text-[9px] text-[#B893D8] hover:text-white" onclick="event.stopPropagation()">Investigate →</a>
                        </span>
                    </div>
                </article>`;
            }).join('');
        } catch (error) {
            console.error(error);
            if (list) list.innerHTML = `<article class="ov-feed-item"><p class="text-amber-100">Couldn’t load live feed.</p></article>`;
        }
    }

    async function loadDomainTable() {
        const params = dateParams();
        const q = domainSearch.trim();
        if (q) params.set('search', q);
        const qs = params.toString();
        domainRows = await json(qs ? `/domains/performance?${qs}` : '/domains/performance');
        renderDomainTable();
    }

    async function loadCampaignPerformance() {
        const body = document.getElementById('campaign-performance-body');
        if (!body) return;
        try {
            const rows = await json(apiUrl('/campaigns/performance'));
            body.innerHTML = (rows || []).length ? rows.map((row) => `
                <tr>
                    <td>${row.campaign || '—'}</td>
                    <td>${fmt(row.clicks)}</td>
                    <td>${fmt(row.valid)}</td>
                    <td>${fmt(row.invalid)}</td>
                    <td>${money(row.costSaved)}</td>
                </tr>
            `).join('') : '<tr><td colspan="5" class="px-[8px] py-[10px] text-center text-white/75">No campaigns in this range.</td></tr>';
        } catch (error) {
            console.error(error);
            body.innerHTML = '<tr><td colspan="5" class="px-[8px] py-[10px] text-center text-amber-100">Couldn’t load campaigns.</td></tr>';
        }
    }

    async function loadCharts() {
        hiddenThreatSlices = {};
        const qs = filterParams().toString();
        const trends = await json(qs ? `/analytics/trends?${qs}` : '/analytics/trends');
        const threats = await json(qs ? `/analytics/threats?${qs}` : '/analytics/threats');
        const datasets = trends.datasets || [{
            key: 'invalid',
            name: 'Invalid Clicks',
            color: '#FB7185',
            values: trends.values || [],
        }];
        drawTrendDual(trends.labels || [], datasets);
        renderTrafficLegend(datasets);
        renderDonut(threats.labels || [], threats.values || [], threats.total);
        renderThreatLegend(threats.labels || [], threats.values || []);
    }

    function syncFeedToSuiteHeight() {
        const pair = document.querySelector('.ov-suite-pair');
        const feed = document.querySelector('.ov-card--feed');
        if (!pair || !feed) return;

        // Desktop: CSS stretch aligns bottoms — clear any leftover inline caps
        if (window.matchMedia('(min-width: 1280px)').matches) {
            feed.style.removeProperty('height');
            feed.style.removeProperty('max-height');
            feed.style.removeProperty('--ov-suite-h');
            // Force feed to suite-pair height (grid stretch can fail if feed content mins tall)
            const h = pair.getBoundingClientRect().height;
            if (h > 0) {
                feed.style.height = `${Math.round(h)}px`;
                feed.style.maxHeight = `${Math.round(h)}px`;
            }
            return;
        }

        const h = Math.max(Math.round(pair.getBoundingClientRect().height || 0), 200);
        feed.style.height = `${h}px`;
        feed.style.maxHeight = `${h}px`;
    }

    function revealOverview() {
        syncFeedToSuiteHeight();
        const page = document.getElementById('ov-page');
        page?.classList.remove('ov-page--booting');
        page?.classList.add('ov-page--ready');
        document.documentElement.classList.remove('ov-boot-lock');
        window.promotixPageLoader?.hide();
    }

    async function loadAll() {
        window.promotixPageLoader?.show('Loading Overview…');
        try {
            const results = await Promise.allSettled([
                loadSummary(),
                loadInsights(),
                loadDomainTable(),
                loadCampaignPerformance(),
            ]);
            results.forEach((result, index) => {
                if (result.status === 'rejected') {
                    console.error(['summary', 'insights', 'domains', 'campaigns'][index], result.reason);
                }
            });
            if (results[0].status === 'rejected') {
                ['suite-paid-clicks', 'suite-paid-valid', 'suite-paid-visits', 'suite-bot-visitors', 'suite-bot-detected', 'suite-bot-blocked']
                    .forEach((id) => setMetric(id, 0));
            }
            try {
                await loadCharts();
            } catch (error) {
                console.error('charts', error);
            }
        } finally {
            // Wait for feed/layout paint so scrollbar doesn’t shove cards mid-reveal
            syncFeedToSuiteHeight();
            requestAnimationFrame(() => {
                syncFeedToSuiteHeight();
                requestAnimationFrame(revealOverview);
            });
        }
    }

    document.getElementById('domain-tab-all')?.addEventListener('click', () => setDomainTab('all'));
    document.getElementById('domain-tab-invalid')?.addEventListener('click', () => setDomainTab('invalid'));
    document.getElementById('domain-tab-pending')?.addEventListener('click', () => setDomainTab('pending'));
    document.getElementById('domain-search')?.addEventListener('input', (e) => {
        domainSearch = e.target.value || '';
        renderDomainTable();
    });
    document.getElementById('domain-filter')?.addEventListener('change', loadAll);
    document.getElementById('traffic-source-filter')?.addEventListener('change', loadAll);
    document.getElementById('path-filter')?.addEventListener('input', () => {
        clearTimeout(window.__ovPathTimer);
        window.__ovPathTimer = setTimeout(loadAll, FILTER_DEBOUNCE_MS);
    });
    window.addEventListener('promotix:date-range', loadAll);
    window.addEventListener('resize', () => {
        window.clearTimeout(window.__ovFeedSyncTimer);
        window.__ovFeedSyncTimer = setTimeout(syncFeedToSuiteHeight, 100);
    });

    const bootstrapParams = new URLSearchParams(location.search);
    if (bootstrapParams.get('domain_id')) {
        const domainEl = document.getElementById('domain-filter');
        if (domainEl) domainEl.value = bootstrapParams.get('domain_id');
    }
    if (bootstrapParams.get('path')) {
        const pathEl = document.getElementById('path-filter');
        if (pathEl) pathEl.value = bootstrapParams.get('path');
    }

    loadAll();
});
</script>
@endsection
