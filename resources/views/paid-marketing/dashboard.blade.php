@extends('layouts.admin')

@section('title', 'Paid Advertising | Dashboard')
@section('subtitle', 'Live campaign performance and detection results')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="paidAdvertisingFigma(@js(['countryGetStarted' => $countryGetStarted, 'userTimezone' => \App\Support\UserTimezone::forUser(auth()->user())]))" x-init="init()">
    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[22px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[25px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Advertising</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Dashboard</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)]">
                <label class="flex min-w-0 flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domains</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="filters.domain_id" @change="onDomainChange()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All domains</option>
                            @foreach ($domains as $domain)
                                <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[178px] shrink-0 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleReload()" placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        <div class="paid-dashboard-cards">
            <article class="paid-dashboard-card">
                <div class="flex items-start justify-between">
                    <h2 class="paid-dashboard-card__title">Paid Traffic</h2>
                    <div class="flex items-center gap-[6px]">
                        <button type="button" class="paid-dashboard-card__icon-btn" aria-label="Refresh" @click="reload(true, true)" title="Refresh Google Ads sync">
                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h5M20 20v-5h-5M20 9A8 8 0 006.34 6.34M4 15a8 8 0 0013.66 2.66"/></svg>
                        </button>
                        <span class="paid-dashboard-card__icon-btn text-[11px]" title="Invalid rate for selected period">i</span>
                    </div>
                </div>
                <div class="mt-[8px] grid grid-cols-[minmax(0,1fr)_88px] items-center gap-[10px]">
                    <div class="grid min-w-0 grid-cols-2 gap-x-[14px] gap-y-[6px]">
                        <div class="min-w-0 text-left">
                            <p class="text-[9px] leading-[1.25] text-white/75">Paid traffic</p>
                            <p class="mt-[6px] text-[24px] font-semibold leading-none text-white" x-text="fmt(summary.paid_visits)"></p>
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="text-[9px] leading-[1.25] text-white/75">Invalid clicks</p>
                            <p class="mt-[6px] text-[24px] font-semibold leading-none text-white" x-text="fmt(summary.invalid_paid_visits)"></p>
                        </div>
                    </div>
                    <div class="relative h-[72px] w-[88px]">
                        <canvas id="paid-invalid-donut" class="h-full w-full" aria-label="Invalid traffic rate"></canvas>
                    </div>
                </div>
            </article>

            <article class="paid-dashboard-card">
                <h2 class="paid-dashboard-card__title">Bot Protection</h2>
                <div class="grid grid-cols-[70px_1fr] items-end gap-[10px]">
                    <div class="pt-[15px]">
                        <p class="text-[30px] font-normal leading-none text-white"><span x-text="botRate"></span>%</p>
                        <p class="text-[18px] leading-none text-white">Bots</p>
                    </div>
                    <div class="relative h-[80px] w-full min-w-0">
                        <canvas id="bot-bars" class="h-full w-full" aria-label="Invalid traffic trend"></canvas>
                    </div>
                </div>
            </article>

            <article class="paid-dashboard-card">
                <div class="flex items-start justify-between">
                    <h2 class="paid-dashboard-card__title">Blocking Activity</h2>
                    <span class="paid-dashboard-card__icon-btn text-[12px]" title="Blocking breakdown">i</span>
                </div>
                <div class="mt-[6px] grid grid-cols-2 gap-[8px]">
                    <div class="text-center">
                        <p class="text-[9px] text-white/70">Invalid Total Traffic</p>
                        <p class="text-[22px] font-semibold leading-none text-white" x-text="fmt(summary.invalid_paid_visits)"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-[9px] text-white/70">Total blocked</p>
                        <p class="text-[22px] font-semibold leading-none text-white" x-text="fmt(summary.blocked_paid_visits)"></p>
                    </div>
                </div>
                <div class="mt-[8px] space-y-0">
                    <div class="paid-blocking-row"><span>IP</span><span x-text="fmt(summary.unique_ips)"></span></div>
                    <div class="paid-blocking-row"><span>IP Range</span><span x-text="fmt(summary.flagged_paid_visits)"></span></div>
                    <div class="paid-blocking-row"><span>Events</span><span x-text="fmt(summary.blocked_paid_visits)"></span></div>
                    <div class="paid-blocking-row">
                        <span>Audiences</span>
                        <a href="{{ route('paid-marketing.detection-settings') }}" class="paid-campaign-link !py-[1px] !px-[8px] !text-[8px]">Set up</a>
                    </div>
                </div>
            </article>

            <article class="paid-dashboard-card">
                <h2 class="paid-dashboard-card__title">Campaigns Breakdown</h2>
                <div class="paid-campaign-breakdown">
                    <div class="paid-campaign-diamond">
                        <svg class="h-[28px] w-[28px] text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3l2.2 6.8H21l-5.5 4 2.1 6.8L12 16.6 6.4 20.6l2.1-6.8L3 9.8h6.8L12 3z" stroke="currentColor" stroke-width="1.4" fill="rgba(255,255,255,0.12)"/>
                        </svg>
                    </div>
                    <template x-if="topCampaign">
                        <p class="max-w-full truncate px-[6px] text-[10px] text-white/85" x-text="topCampaign.campaign"></p>
                    </template>
                    <template x-if="!topCampaign && untaggedDomains.length === 0">
                        <p class="text-[10px] text-white/55">No campaign data yet</p>
                    </template>
                    <template x-if="untaggedDomains.length > 0">
                        <div class="w-full space-y-[4px] px-[6px] text-left">
                            <p class="text-[9px] font-semibold uppercase text-white/60">Untagged domains</p>
                            <template x-for="d in untaggedDomains.slice(0, 3)" :key="d.id">
                                <p class="truncate text-[10px] text-white/85" x-text="d.hostname"></p>
                            </template>
                        </div>
                    </template>
                    <a href="{{ route('domains.index') }}" class="paid-campaign-link" x-text="untaggedDomains.length ? 'Tag Management' : 'Set Tracking Parameter'"></a>
                </div>
            </article>
        </div>

        <div class="mt-[15px] grid grid-cols-1 gap-[17px] xl:grid-cols-[minmax(0,589px)_minmax(260px,1fr)]">
            <section class="min-h-[341px] rounded-[12px] border border-[#6400B2] bg-[#6400B2] p-[20px] shadow-[0_0_24px_rgba(100,0,179,.45)]">
                <div class="mb-[8px] flex flex-wrap items-center justify-between gap-[8px]">
                    <div class="flex flex-wrap items-center gap-[10px]">
                        <h2 class="text-[20px] font-normal text-[#a9a9a9]">Paid Traffic Trends</h2>
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
                    <select x-model="filters.window" @change="setWindow()" class="h-[41px] rounded-full border border-white/30 bg-[#101010] px-[20px] text-[14px] text-white focus:border-[#9a1aff] focus:ring-1 focus:ring-[#9a1aff]/40">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="paid-trends-wrap">
                    <div id="paid-trends-tooltip" class="paid-trends-tooltip" hidden></div>
                    <canvas id="paid-trends" class="h-[270px] w-full"></canvas>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-[12px] sm:grid-cols-2 xl:grid-cols-2">
                <section class="paid-sidebar-card">
                    <div class="paid-sidebar-card__head">
                        <svg class="h-[16px] w-[16px] text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3l7 4v5c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V7l7-4z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 9v4m0 3h.01"/></svg>
                        <h2>Heatmap</h2>
                    </div>
                    <div id="heatmap-grid" class="mt-[10px] grid grid-cols-8 gap-[3px]"></div>
                    <div class="paid-heatmap-bar"><span :style="'width:' + heatmapIntensity + '%'"></span></div>
                </section>

                <section class="paid-sidebar-card">
                    <div class="paid-sidebar-card__head">
                        <svg class="h-[16px] w-[16px] text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                        <h2>Keyword</h2>
                    </div>
                    <div id="keyword-list" class="mt-[10px] space-y-[6px]"></div>
                </section>

                <section class="paid-invalid-card sm:col-span-2 xl:col-span-2">
                    <h2 class="text-[16px] font-normal text-[#a9a9a9]">Invalid Traffic Protection</h2>
                    <canvas id="invalid-protection" class="mt-[8px] h-[105px] w-full"></canvas>
                </section>
            </div>
        </div>

        <div class="mt-[15px] grid grid-cols-1 gap-[17px] xl:grid-cols-[minmax(0,585px)_minmax(260px,1fr)]">
            <section class="min-h-[451px] rounded-[10px] border border-[#5a2a99] bg-[#111111] p-[18px]">
                <div class="mb-[10px] flex flex-wrap items-center justify-between gap-[10px]">
                    <div class="flex items-center gap-[10px]">
                        <h2 class="text-[24px] font-semibold leading-none text-[#a9a9a9]">IP Address</h2>
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
                    <table class="w-full min-w-[720px] table-fixed text-left text-[11px] text-[#a9a9a9]">
                        <thead class="sticky top-0 z-[1] bg-[#6400B2]">
                            <tr>
                                <th class="w-[20%] px-[8px] py-[7px] font-normal">Address</th>
                                <th class="w-[14%] px-[8px] py-[7px] font-normal">Campaign</th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal">Country</th>
                                <th class="w-[9%] px-[8px] py-[7px] font-normal">Invalid</th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal">Valid</th>
                                <th class="w-[11%] px-[8px] py-[7px] font-normal">Bot detect</th>
                                <th class="w-[8%] px-[8px] py-[7px] font-normal">VPN</th>
                                <th class="w-[10%] px-[8px] py-[7px] font-normal">Data center</th>
                                <th class="w-[12%] px-[8px] py-[7px] font-normal">Last click</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/15">
                            <template x-for="row in ips" :key="row.ip">
                                <tr class="cursor-pointer align-middle transition hover:bg-white/5" @click="openIpModal(row)">
                                    <td class="max-w-0 px-[8px] py-[6px]">
                                        <span class="flex items-center gap-[4px]">
                                            <span class="block truncate font-mono text-[9px] text-white" :title="row.ip" x-text="ipLabel(row.ip)"></span>
                                            <span x-show="row.is_allowlisted" class="shrink-0 rounded-[3px] bg-emerald-500/20 px-[4px] py-[1px] text-[8px] font-semibold uppercase text-emerald-300">Allow list</span>
                                        </span>
                                    </td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px] text-[10px] text-white/85" :title="row.campaign || ''" x-text="row.campaign || '—'"></td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px]" x-text="row.country || '—'"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap" x-text="fmt(row.invalid)"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap" x-text="fmt(row.valid ?? Math.max(0, Number(row.total || 0) - Number(row.invalid || 0)))"></td>
                                    <td class="max-w-0 truncate px-[8px] py-[6px] capitalize" x-text="threatLabel(row.top_threat)"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap" x-text="row.vpn_hits > 0 ? fmt(row.vpn_hits) : '—'"></td>
                                    <td class="px-[8px] py-[6px] whitespace-nowrap" x-text="row.data_center_hits > 0 ? fmt(row.data_center_hits) : '—'"></td>
                                    <td class="whitespace-nowrap px-[8px] py-[6px] text-[10px]" x-text="dateLabel(row.last_seen)"></td>
                                </tr>
                            </template>
                            <tr x-show="ips.length === 0"><td colspan="9" class="px-[10px] py-[12px] text-center text-white/60" x-text="filters.campaign ? 'No paid IPs for this campaign in the selected date range.' : 'No paid IP data yet for the selected domain(s) and date range.'"></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="relative min-h-[329px] overflow-hidden rounded-[10px] border border-[#6400B2] bg-[linear-gradient(180deg,rgba(217,217,217,.72)_18%,#6625F8_58%,rgba(0,0,0,.92)_100%)] p-[12px]">
                <div class="flex items-center justify-between">
                    <h2 class="text-[14px] font-normal text-[#343434]">Country Breakdown</h2>
                    <span class="flex h-[16px] w-[16px] items-center justify-center rounded-full border border-[#343434]/50 text-[9px] font-semibold text-[#343434]" title="Geographic invalid visit breakdown">i</span>
                </div>

                <div class="relative mt-[14px] min-h-[248px]">
                    <div class="overflow-hidden rounded-[6px] border border-white/20 bg-black/10">
                        <table class="w-full text-left text-[10px] text-white/90">
                            <thead class="border-b border-white/15 bg-black/20 text-[#343434]">
                                <tr>
                                    <th class="px-[10px] py-[8px] font-normal">Country</th>
                                    <th class="px-[10px] py-[8px] font-normal text-center">Invalid Visit</th>
                                    <th class="px-[10px] py-[8px] font-normal text-right">Invalid Rate</th>
                                </tr>
                            </thead>
                            <tbody id="country-list" class="divide-y divide-white/10">
                                @if ($countryGetStarted)
                                    @for ($i = 0; $i < 5; $i++)
                                        <tr class="text-white/80">
                                            <td class="px-[10px] py-[9px]">
                                                <span class="inline-flex items-center gap-[8px]">
                                                    <span class="inline-block h-[10px] w-[14px] rounded-[2px] bg-white/40"></span>
                                                    Country Stats
                                                </span>
                                            </td>
                                            <td class="px-[10px] py-[9px] text-center">0</td>
                                            <td class="px-[10px] py-[9px] text-right">0%</td>
                                        </tr>
                                    @endfor
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if ($countryGetStarted)
                        <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-b from-transparent via-[#6625F8]/35 to-black/55 pt-[8px]">
                            <div class="mb-[52px] flex h-[118px] w-[118px] items-center justify-center rounded-[16px] border-2 border-white/90 bg-white/10 shadow-[0_14px_40px_rgba(0,0,0,.45)]">
                                <svg class="h-[78px] w-[78px] text-white drop-shadow-[0_0_12px_rgba(255,255,255,.45)]" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                                    <path d="M32 6L10 14v14c0 13.2 9.4 25.5 22 28 12.6-2.5 22-14.8 22-28V14L32 6z" stroke="currentColor" stroke-width="2.2" fill="rgba(100,0,178,.25)"/>
                                    <path d="M24 33l6 6 12-14" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <a href="{{ route('domains.index', ['add' => 1]) }}" class="absolute bottom-[28px] left-1/2 -translate-x-1/2 rounded-[7px] bg-white px-[42px] py-[9px] text-[15px] font-semibold text-[#6400B2] shadow-[0_8px_24px_rgba(0,0,0,.35)] hover:bg-white/95">Get Started</a>
                        </div>
                    @endif
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
                                    <p class="figma-modal-value" x-text="activeIpClick.browser_name || '—'"></p>
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
                                        <p class="figma-modal-label">Paid ID</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(activeIpClick.paid_id)" x-show="activeIpClick.paid_id">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--long" x-text="activeIpClick.paid_id || '—'"></p>
                                </div>
                                <div class="figma-modal-field figma-modal-field--full">
                                    <div class="figma-modal-field__head">
                                        <p class="figma-modal-label">Path</p>
                                        <button type="button" class="figma-modal-copy-btn" @click="copyText(activeIpClick.path)" x-show="activeIpClick.path">Copy</button>
                                    </div>
                                    <p class="figma-modal-value figma-modal-value--long" x-text="activeIpClick.path || '—'"></p>
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
        filters: { domain_id: '', campaign: '', campaign_id: '', path: '', window: 'weekly', from: '', to: '' },
        summary: { paid_visits: 0, invalid_paid_visits: 0, blocked_paid_visits: 0, flagged_paid_visits: 0, valid_paid_visits: 0, unique_ips: 0 },
        trends: { labels: [], datasets: [], invalid_daily: [] },
        blocking: { labels: [], datasets: [] },
        campaigns: [],
        untaggedDomains: [],
        keywords: [],
        countries: [],
        ips: [],
        ipModal: { open: false, row: null, clicks: [], activeIndex: 0, loading: false },
        countryModal: { open: false, country: '', rows: [], loading: false },
        get activeIpClick() { return this.ipModal.clicks[this.ipModal.activeIndex] || null; },
        heatmap: { days: [], hours: [], matrix: [] },
        trendsHoverIndex: null,
        hiddenTrendSeries: { lastWeek: false, thisWeek: false },
        cardCharts: {},
        get botRate() {
            const paid = Number(this.summary.paid_visits || 0);
            const invalid = Number(this.summary.invalid_paid_visits || 0);
            return paid ? Math.round((invalid / paid) * 100) : 0;
        },
        get topCampaign() {
            return (this.campaigns || []).find(r => r.campaign) || null;
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
            this.reload();
        },
        qs(forceGoogle = false) {
            const p = new URLSearchParams();
            if (this.filters.domain_id) p.set('domain_id', this.filters.domain_id);
            if (this.filters.path) p.set('path', this.filters.path);
            if (this.filters.from) p.set('from', this.filters.from);
            if (this.filters.to) p.set('to', this.filters.to);
            if (forceGoogle) p.set('force_google_sync', '1');
            return p.toString();
        },
        reloadTimer: null,
        livePollTimer: null,
        livePollOn: true,
        livePollMs: 30000,
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
            const id = new URLSearchParams(window.location.search).get('domain_id');
            if (id) this.filters.domain_id = id;
        },
        onDomainChange() {
            this.filters.campaign = '';
            this.filters.campaign_id = '';
            window.promotixPageLoader?.show('Loading data…');
            this.reload();
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
                this.reload();
            }, this.livePollMs);
        },
        async init() {
            window.__paidAdvertisingDash = this;
            this.applyDomainFromUrl();
            this.syncHeaderDates();
            if (!this.filters.from || !this.filters.to) {
                const today = new Date();
                const days = this.filters.window === 'monthly' ? 29 : 6;
                const start = new Date(today.getTime() - days * 86400000);
                this.filters.from = start.toISOString().slice(0, 10);
                this.filters.to = today.toISOString().slice(0, 10);
            }
            this.startLivePoll();
            window.addEventListener('promotix:date-range', () => {
                this.syncHeaderDates();
                this.scheduleReload();
            });
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.reload();
            });
            window.addEventListener('promotix:export-ips-csv', () => this.exportIpsCsv());
            await this.reload();
            window.addEventListener('resize', () => {
                clearTimeout(window.__paidFigmaResize);
                window.__paidFigmaResize = setTimeout(() => this.render(), 180);
            });
        },
        async reload(forceGoogle = false, withLoader = false) {
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
                this.trends = trends;
                this.blocking = blocking;
                this.campaigns = Array.isArray(campaigns) ? campaigns : (campaigns.campaigns || []);
                this.untaggedDomains = Array.isArray(campaigns) ? [] : (campaigns.untagged_domains || []);
                this.syncCampaignFilter();
                this.keywords = keywords;
                this.countries = countries;
                this.ips = ips;
                this.heatmap = heatmap;
                this.$nextTick(() => this.render());
            } finally {
                window.promotixPageLoader?.hide();
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
        render() {
            requestAnimationFrame(() => {
                this.renderCardCharts();
                const labels = this.trends.labels || [];
                const datasets = this.visibleTrendDatasets();
                this.drawPaidTrendLine('paid-trends', labels, datasets, this.trendsHoverIndex);
                this.bindPaidTrendHover('paid-trends', labels, this.trends.datasets || []);
                this.drawProtectionLine('invalid-protection', this.blocking.labels || [], this.blocking.datasets || []);
                this.renderHeatmap();
                this.renderKeywords();
                this.renderCountries();
            });
        },
        trendsLegendItems() {
            const datasets = this.trends.datasets || [];
            if (datasets.length) {
                return datasets.map(ds => ({
                    key: ds.dashed ? 'lastWeek' : 'thisWeek',
                    name: ds.name || (ds.dashed ? 'Last Week' : 'This Week'),
                    color: ds.color || (ds.dashed ? '#FF4BC1' : '#6625F8'),
                }));
            }
            return [
                { key: 'lastWeek', name: 'Last Week', color: '#FFFFFF' },
                { key: 'thisWeek', name: 'This Week', color: '#6625F8' },
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
        visibleTrendDatasets() {
            return (this.trends.datasets || []).filter(ds => {
                const key = ds.dashed ? 'lastWeek' : 'thisWeek';
                return !this.hiddenTrendSeries[key];
            });
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
                animation: { duration: 350 },
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                ...extra,
            };
        },
        renderCardCharts() {
            if (!window.Chart) return;
            this.renderInvalidDonut();
            this.renderBotBars();
        },
        renderInvalidDonut() {
            const el = document.getElementById('paid-invalid-donut');
            if (!el) return;
            this.destroyCardChart('invalidDonut');
            const valid = Number(this.summary.valid_paid_visits || 0);
            const invalid = Number(this.summary.invalid_paid_visits || 0);
            const paid = Number(this.summary.paid_visits || 0);
            const rate = paid ? Math.round((invalid / paid) * 100) : 0;
            const hasData = valid + invalid > 0;
            this.cardCharts.invalidDonut = new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: ['Invalid', 'Valid'],
                    datasets: [{
                        data: hasData ? [invalid, valid] : [0, 1],
                        backgroundColor: ['#FF4BC1', 'rgba(255,255,255,0.18)'],
                        borderWidth: 0,
                    }],
                },
                options: this.miniChartOptions({
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.label}: ${this.fmt(ctx.raw)}`,
                            },
                        },
                    },
                }),
                plugins: [{
                    id: 'invalidRateCenter',
                    afterDraw: (chart) => {
                        const { ctx, chartArea } = chart;
                        if (!chartArea) return;
                        const cx = (chartArea.left + chartArea.right) / 2;
                        const cy = (chartArea.top + chartArea.bottom) / 2;
                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillStyle = '#ffffff';
                        ctx.font = '600 12px Inter, sans-serif';
                        ctx.fillText(`${rate}%`, cx, cy - 5);
                        ctx.fillStyle = 'rgba(255,255,255,0.65)';
                        ctx.font = '8px Inter, sans-serif';
                        ctx.fillText('Invalid Rate', cx, cy + 9);
                        ctx.restore();
                    },
                }],
            });
        },
        renderBotBars() {
            const el = document.getElementById('bot-bars');
            if (!el) return;
            this.destroyCardChart('botBars');
            const values = (this.trends.invalid_daily || []).slice(-7);
            const labels = (this.trends.labels || []).slice(-7);
            const barValues = values.length ? values : [0, 0, 0, 0, 0, 0, 0];
            this.cardCharts.botBars = new Chart(el, {
                type: 'bar',
                data: {
                    labels: labels.length ? labels : barValues.map((_, i) => i + 1),
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
            canvas.width = Math.max(1, w * dpr);
            canvas.height = Math.max(1, h * dpr);
            const ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);
            return { ctx, w, h };
        },
        renderHeatmap() {
            const el = document.getElementById('heatmap-grid');
            if (!el) return;
            const flat = (this.heatmap.matrix || []).flat();
            const max = Math.max(...flat, 1);
            const cells = flat.slice(0, 56);
            el.innerHTML = cells.map(v => {
                const t = max ? Number(v || 0) / max : 0;
                const bg = t > 0.65 ? '#6625F8' : t > 0.35 ? '#8B4FD4' : 'rgba(255,255,255,0.22)';
                return `<span class="h-[13px] rounded-[2px]" style="background:${bg}"></span>`;
            }).join('');
        },
        renderKeywords() {
            const el = document.getElementById('keyword-list');
            if (!el) return;
            const rows = (this.keywords || []).slice(0, 4);
            el.innerHTML = rows.length ? rows.map(row => `
                <div class="paid-keyword-pill">
                    <span class="truncate">${row.keyword}</span><span>${row.invalid}</span>
                </div>
            `).join('') : '<p class="text-[10px] text-white/70">No keyword data.</p>';
        },
        renderCountries() {
            if (this.countryGetStarted) return;
            const el = document.getElementById('country-list');
            if (!el) return;
            const rows = (this.countries || []).slice(0, 8);
            el.innerHTML = rows.length ? rows.map(row => {
                const invalid = Number(row.invalid || 0);
                const total = Number(row.total || 0);
                const rate = total ? Math.round((invalid / total) * 100) : 0;
                const label = this.countryLabel(row.country);
                const flag = this.countryFlagUrl(row.country);
                const flagHtml = flag
                    ? `<img src="${flag}" alt="${label}" class="inline-block h-[10px] w-[14px] shrink-0 rounded-[2px] object-cover" loading="lazy">`
                    : `<span class="inline-block h-[10px] w-[14px] shrink-0 rounded-[2px] bg-white/25"></span>`;
                return `<tr class="cursor-pointer transition hover:bg-white/5" onclick="window.__paidAdvertisingDash?.openCountryIps('${row.country}')">
                    <td class="px-[10px] py-[9px]"><span class="inline-flex items-center gap-[8px]">${flagHtml}<span>${label}</span></span></td>
                    <td class="px-[10px] py-[9px] text-center">${this.fmt(invalid)}</td>
                    <td class="px-[10px] py-[9px] text-right">${rate}%</td>
                </tr>`;
            }).join('') : '<tr><td colspan="3" class="px-[10px] py-[14px] text-center text-white/65">No country data for this period.</td></tr>';
        },
    };
}
</script>
@endsection
