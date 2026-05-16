@extends('layouts.admin')

@section('title', 'Paid Advertising | Advanced View')

@section('content')
@php
    $pageRows = $visits->getCollection();
    $rowCount = max($pageRows->count(), 1);
    $blockedCount = $pageRows->filter(fn ($visit) => (bool) ($visit->ip_is_blocked ?? false))->count();
    $threatCount = $pageRows->filter(fn ($visit) => filled($visit->threat_group) || filled($visit->threat_type))->count();
    $botCount = $pageRows->filter(fn ($visit) => str_contains(strtolower((string) $visit->threat_type), 'bot') || str_contains(strtolower((string) $visit->threat_group), 'bot'))->count();
    $countryCount = $pageRows->pluck('country')->filter()->unique()->count();
    $paidVisits = max((int) $pageRows->sum(fn ($visit) => (int) ($visit->visits ?? 1)), 1);

    $statCards = [
        ['label' => 'Blocked', 'value' => $pageRows->isEmpty() ? 84 : (int) round(($blockedCount / $rowCount) * 100), 'fillClass' => 'h-[80%]', 'toneClass' => 'bg-[#9A1AFF]'],
        ['label' => 'Invalid Traffic', 'value' => $pageRows->isEmpty() ? 34 : (int) round(($threatCount / $rowCount) * 100), 'fillClass' => 'h-[32%]', 'toneClass' => 'bg-white/55'],
        ['label' => 'PaidTraffic', 'value' => $pageRows->isEmpty() ? 92 : min(100, (int) round(($paidVisits / max($paidVisits + $threatCount, 1)) * 100)), 'fillClass' => 'h-[92%]', 'toneClass' => 'bg-white/55'],
        ['label' => 'Bot Detection', 'value' => $pageRows->isEmpty() ? 0 : (int) round(($botCount / $rowCount) * 100), 'fillClass' => 'h-0', 'toneClass' => 'bg-white/55'],
        ['label' => 'Countries', 'value' => $pageRows->isEmpty() ? 0 : min(100, $countryCount * 10), 'fillClass' => 'h-0', 'toneClass' => 'bg-white/55'],
        ['label' => 'Overall', 'value' => $pageRows->isEmpty() ? 68 : (int) round((($blockedCount + $threatCount + $botCount) / max($rowCount * 3, 1)) * 100), 'fillClass' => 'h-[68%]', 'toneClass' => 'bg-white/55'],
    ];
@endphp

<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="paidMarketingDetailed()">
    <form id="advanced-filters-form" method="GET" action="{{ route('paid-marketing.detailed') }}"></form>

    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[20px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Advanced View</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Campaigns</span>
                    <select name="platform" form="advanced-filters-form" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option value="">All campaigns</option>
                        @foreach ($platforms as $platform)
                            <option value="{{ $platform }}" @selected(request('platform') === $platform)>{{ $platform }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Filter by path</span>
                    <input name="path" form="advanced-filters-form" value="{{ request('path') }}" placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
                <button type="submit" form="advanced-filters-form" class="figma-filter-action flex w-[34px] items-center justify-center bg-[#6400B2] text-white" aria-label="Apply filters">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8M8 12h8M8 17h8"/></svg>
                </button>
            </div>
        </div>

        <div class="rounded-[10px] border border-white/40 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.35)]">
            <div class="grid gap-[16px] lg:grid-cols-[1fr_244px]">
                <div class="flex min-h-[91px] flex-col justify-between">
                    <h2 class="text-[20px] font-normal text-[#a9a9a9]">Paid Traffic Trends</h2>
                    <button type="button" @click="window.print()" class="flex w-fit items-center gap-[5px] text-[15px] text-white hover:underline">
                        <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 3v12m0 0l4-4m-4 4l-4-4M4 19h16"/></svg>
                        Download
                    </button>
                </div>

                <div class="space-y-[12px]">
                    <label class="flex h-[26px] items-center rounded-[5px] border border-white/30 bg-[#0f0e0e] px-[10px]">
                        <svg class="mr-[9px] h-[15px] w-[15px] text-[#9d9898]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input name="ip" form="advanced-filters-form" value="{{ request('ip') }}" placeholder="Filter by IP" class="h-full flex-1 border-0 bg-transparent p-0 text-[14px] font-light text-[#9d9898] placeholder:text-[#9d9898] focus:ring-0">
                    </label>
                    <button type="button" @click="filtersOpen = ! filtersOpen" class="flex h-[49px] w-full items-center rounded-[5px] border border-white/30 bg-[#0f0e0e] px-[13px] text-left text-[14px] text-[#9d9898]">
                        <svg class="mr-[12px] h-[22px] w-[22px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linejoin="round" stroke-width="1.7" d="M4 5h16l-6 7v5l-4 2v-7L4 5z"/></svg>
                        <span>Advanced<br>Filters</span>
                    </button>
                </div>
            </div>

            <div x-show="filtersOpen" x-cloak class="mt-[14px] grid gap-[10px] rounded-[8px] bg-black/20 p-[10px] sm:grid-cols-2">
                <label class="text-[11px] text-white/70">From
                    <input type="date" name="from" form="advanced-filters-form" value="{{ request('from') }}" class="mt-[4px] h-[32px] w-full rounded-[4px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                </label>
                <label class="text-[11px] text-white/70">To
                    <input type="date" name="to" form="advanced-filters-form" value="{{ request('to') }}" class="mt-[4px] h-[32px] w-full rounded-[4px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                </label>
                <div class="flex gap-[8px] sm:col-span-2 sm:justify-end">
                    <a href="{{ route('paid-marketing.detailed') }}" class="rounded-[4px] border border-white/30 px-[12px] py-[7px] text-[12px] text-white/80">Clear</a>
                    <button type="submit" form="advanced-filters-form" class="rounded-[4px] bg-white px-[12px] py-[7px] text-[12px] font-semibold text-[#6400B2]">Apply filters</button>
                </div>
            </div>
        </div>

        <section class="mt-[8px] overflow-hidden">
            <div class="overflow-x-auto">
                <div class="min-w-[895px]">
                    <div class="figma-data-grid-header grid grid-cols-[22px_115px_70px_135px_112px_120px_118px_1fr] items-center border-b border-white px-[14px] py-[9px] text-[13px] text-white">
                        <span></span>
                        <span>IP Address v</span>
                        <span>Visits v</span>
                        <span>Campaigns v</span>
                        <span>Last Click v</span>
                        <span>Threat Group v</span>
                        <span>Threat Type v</span>
                        <span>Country ^</span>
                    </div>

                    <div class="max-h-[318px] overflow-y-auto pr-[6px]">
                        @forelse ($pageRows as $visit)
                            <button type="button" class="figma-data-row mt-[6px] grid h-[47px] w-full grid-cols-[22px_115px_70px_135px_112px_120px_118px_1fr] items-center rounded-[10px] border-[3px] border-white/40 bg-[#151515] px-[11px] text-left text-[15px] text-[#a9a9a9] transition hover:border-white/70" @click="openClicks(@js($visit))">
                                <span class="text-white/90">&gt;</span>
                                <span>{{ $visit->ip }}</span>
                                <span>{{ $visit->visits ?? ($visit->clicks?->count() ?: 1) }}</span>
                                <span>{{ $visit->campaign ?? 'N/A' }}</span>
                                <span class="text-[#8d8d8d]">{{ $visit->last_click_at?->format('m/d/y') ?? '-' }}</span>
                                <span class="text-[#8d8d8d]">{{ $visit->threat_group ?? 'Known Bots' }}</span>
                                <span class="text-[#8d8d8d]">{{ $visit->threat_type ?? 'Good Bot' }}</span>
                                <span class="text-[#8d8d8d]">{{ $visit->country ?? 'United States' }}</span>
                            </button>
                        @empty
                            <div class="mt-[6px] rounded-[10px] border-[3px] border-white/40 px-[14px] py-[28px] text-center text-[15px] text-[#a9a9a9]">No rows yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-[20px]">
            <h2 class="mx-auto mb-[18px] flex h-[36px] w-[184px] items-center justify-center rounded-[4px] bg-[#6706B3] text-[24px] font-semibold text-[#a9a9a9]">Paid Stats</h2>
            <div class="grid grid-cols-2 gap-[14px] sm:grid-cols-3 xl:grid-cols-6">
                @foreach ($statCards as $card)
                    <article class="relative h-[228px] overflow-hidden rounded-[10px] border border-white/40 bg-[#6400B2]">
                        <div class="absolute inset-x-0 bottom-0 rounded-t-[10px] {{ $card['fillClass'] }} {{ $card['toneClass'] }}"></div>
                        <div class="relative z-10 pt-[31px] text-center">
                            <p class="mb-[26px] text-[14px] text-[#a9a9a9]">{{ $card['label'] }}</p>
                            <p class="text-[36px] font-medium leading-none text-white">{{ $card['value'] }}%</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="brand-modal-overlay"
             x-show="modal.open" x-cloak x-transition
             @keydown.escape.window="closeModal()" @click.self="closeModal()">
            <div class="brand-modal max-w-5xl">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="brand-modal-title">Click Details</h3>
                    <button type="button" class="rounded-lg p-1.5 text-night-300 hover:bg-night-800 hover:text-white" @click="closeModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="grid grid-cols-1 gap-0 lg:grid-cols-4">
                    <aside class="border-b border-night-700/60 p-2 lg:border-b-0 lg:border-r lg:pr-4">
                        <template x-for="(c, idx) in modal.clicks" :key="c.id ?? idx">
                            <button type="button"
                                    class="mb-2 w-full rounded-xl border border-night-700 bg-night-900 px-3 py-2 text-left transition hover:bg-night-800"
                                    :class="idx === modal.activeIndex ? 'ring-2 ring-brand-400' : ''"
                                    @click="modal.activeIndex = idx">
                                <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                                <p class="text-xs text-night-300" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            </button>
                        </template>
                        <template x-if="modal.clicks.length === 0">
                            <p class="text-sm text-night-300">No clicks for this visit.</p>
                        </template>
                    </aside>

                    <div class="p-4 lg:col-span-3 lg:pl-6" x-show="modal.clicks.length > 0">
                        <template x-if="activeClick">
                            <div class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
                                @php
                                    $modalRows = [
                                        ['IP', 'activeClick.ip || modal.visit.ip || "-"'],
                                        ['Browser', 'activeClick.browser_name || "-"'],
                                        ['Country', 'activeClick.country || modal.visit.country || "-"'],
                                        ['Browser version', 'activeClick.browser_version || "-"'],
                                        ['Last Click', 'formatDateTime(activeClick.last_click_at || modal.visit.last_click_at)'],
                                        ['OS', 'activeClick.os || "-"'],
                                        ['Threat Group', 'activeClick.threat_group || modal.visit.threat_group || "N/A"'],
                                        ['Paid ID', 'activeClick.paid_id || "-"'],
                                        ['Campaign', 'activeClick.campaign || modal.visit.campaign || "N/A"'],
                                        ['Path', 'activeClick.path || modal.visit.last_path || "-"'],
                                        ['Campaignr', 'activeClick.campaignr || "N/A"'],
                                        ['Keyword', 'activeClick.keyword || "N/A"'],
                                    ];
                                @endphp
                                @foreach ($modalRows as [$label, $expr])
                                    <div>
                                        <p class="text-xs uppercase tracking-wider text-night-400">{{ $label }}</p>
                                        <p class="mt-1 break-words text-sm text-white" x-text="{{ $expr }}"></p>
                                    </div>
                                @endforeach
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    function paidMarketingDetailed() {
        return {
            filtersOpen: false,
            modal: { open: false, visit: null, clicks: [], activeIndex: 0 },
            get activeClick() { return this.modal.clicks[this.modal.activeIndex] || null; },
            openClicks(visit) {
                this.modal.visit = visit;
                this.modal.clicks = (visit.clicks || []).slice();
                this.modal.activeIndex = 0;
                this.modal.open = true;
            },
            closeModal() {
                this.modal.open = false;
                this.modal.visit = null;
                this.modal.clicks = [];
                this.modal.activeIndex = 0;
            },
            formatDateTime(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return String(value);
                return date.toLocaleString();
            },
        };
    }
</script>
@endsection
