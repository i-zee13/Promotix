@extends('layouts.admin')

@section('title', 'Paid Advertising | Advanced View')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="paidMarketingDetailed()" x-init="init()">
    <section class="mx-auto w-full px-[12px] pb-[20px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[10px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[8px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Advanced View</span>
            </div>

            <div class="figma-filter-bar relative z-20 flex h-[54px] w-full overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black" :class="filters.domain_id ? 'max-w-[560px]' : 'max-w-[370px]'">
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
                <label x-show="filters.domain_id" x-cloak class="relative flex w-[150px] shrink-0 flex-col justify-center border-r border-black/20 px-[12px]" @click.outside="campaignMenuOpen = false">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaigns</span>
                    <button type="button" @click="openCampaignMenu()" class="figma-filter-select-wrap flex h-[23px] w-full items-center rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[22px] text-left text-[11px] text-[#8c8787]">
                        <span class="truncate" x-text="filters.campaign || 'All campaigns'"></span>
                    </button>
                    <div x-show="campaignMenuOpen" x-cloak class="paid-advanced-campaign-menu promotix-slim-scroll !left-[12px] !right-auto !min-w-[180px]">
                        <button type="button" @click="selectCampaign('')" class="paid-advanced-campaign-option" :class="!filters.campaign && 'is-active'">All campaigns</button>
                        <template x-for="name in campaignOptions" :key="name">
                            <button type="button" @click="selectCampaign(name)" class="paid-advanced-campaign-option" :class="filters.campaign === name && 'is-active'" x-text="name"></button>
                        </template>
                    </div>
                </label>
                <label class="flex w-[178px] shrink-0 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <div class="figma-filter-path-wrap">
                        <svg class="figma-filter-path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="filters.path" @input="scheduleFetch()" placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[22px] pr-[8px] text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        <section class="overflow-visible rounded-[12px] border border-[#6706b3]">
            <div class="flex flex-wrap items-center justify-between gap-[10px] overflow-visible rounded-t-[12px] bg-[#6400B2] px-[16px] py-[12px]">
                <h2 class="text-[18px] font-normal text-white sm:text-[20px]">Advanced View</h2>
                <div class="flex flex-1 flex-wrap items-center justify-end gap-[10px]">
                    <label class="relative flex h-[28px] min-w-[200px] max-w-[280px] flex-1 items-center rounded-[6px] bg-white px-[10px]">
                        <svg class="mr-[6px] h-[14px] w-[14px] shrink-0 text-[#8c8787]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" placeholder="Search for IP Address" x-model="filters.ip" @input="scheduleFetch(true)" class="w-full border-0 bg-transparent text-[11px] text-[#121212] placeholder:text-[#8c8787] focus:ring-0">
                    </label>
                    <div class="relative" @click.outside="filterMenuOpen = false">
                        <button type="button" @click="filterMenuOpen = !filterMenuOpen" class="inline-flex h-[28px] items-center gap-[6px] rounded-[6px] border border-white/30 bg-[#0f0e0e] px-[10px] text-[11px] text-white">
                            Advanced Filter
                            <span class="rounded-[3px] bg-white/15 px-[5px] text-[10px]" x-text="visibleColumns.length"></span>
                        </button>
                        <div x-show="filterMenuOpen" x-cloak class="paid-advanced-columns-menu promotix-slim-scroll">
                            <p class="mb-[8px] text-[10px] font-semibold uppercase text-white/55">Primary columns</p>
                            <template x-for="col in columnCatalog.filter(c => c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option is-locked">
                                    <input type="checkbox" checked disabled>
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                            <p class="mb-[8px] mt-[10px] text-[10px] font-semibold uppercase text-white/55">Optional columns</p>
                            <template x-for="col in columnCatalog.filter(c => !c.primary)" :key="col.key">
                                <label class="paid-advanced-column-option">
                                    <input type="checkbox" :value="col.key" :checked="optionalColumnKeys.includes(col.key)" @change="toggleOptionalColumn(col.key)">
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <a :href="csvHref()" class="inline-flex items-center gap-[6px] text-[12px] font-medium text-white hover:underline">
                        <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v-1a4 4 0 014-4h0a4 4 0 014 4v1"/></svg>
                        Download CSV
                    </a>
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <div class="w-full min-w-0">
                    <div class="pm-adv-table-grid grid w-full gap-[8px] bg-[#1a1a1a] px-[12px] py-[10px] text-[10px] font-medium uppercase tracking-wide text-[#a9a9a9] sm:text-[11px]" :style="gridStyle">
                        <template x-for="col in visibleColumns" :key="'head-' + col.key">
                            <span class="truncate" x-text="col.label"></span>
                        </template>
                    </div>

                    <div class="max-h-[420px] overflow-y-auto pm-adv-scroll px-[12px] py-[8px]">
                        <template x-for="visit in rows" :key="visit.id">
                            <div class="pm-adv-table-grid mb-[8px] grid w-full cursor-pointer gap-[8px] rounded-[10px] bg-[#d9d9d9] px-[12px] py-[10px] text-[10px] text-[#121212] transition hover:bg-[#ececec] sm:text-[11px]" :style="gridStyle" @click="openClicks(visit)">
                                <template x-for="col in visibleColumns" :key="visit.id + '-' + col.key">
                                    <template x-if="col.key !== 'session_recording'">
                                        <span class="truncate" :class="col.key === 'ip' && 'font-medium'" :title="cellValue(visit, col.key)" x-text="cellValue(visit, col.key)"></span>
                                    </template>
                                    <template x-if="col.key === 'session_recording'">
                                        <span class="flex items-center justify-center">
                                            <button type="button" x-show="visit.has_session_recording" @click.stop="openRecording(visit)" class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-full bg-[#6400B2] text-white hover:bg-[#7B13C8]" title="Watch session recording">
                                                <svg class="h-[11px] w-[11px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </button>
                                            <span x-show="!visit.has_session_recording" class="text-[#8c8787]">—</span>
                                        </span>
                                    </template>
                                </template>
                            </div>
                        </template>
                        <p x-show="!loading && rows.length === 0" class="py-[24px] text-center text-[12px] text-[#a9a9a9]">No rows match your filters.</p>
                    </div>
                </div>
            </div>
        </section>

<style>
.pm-adv-scroll { scrollbar-width: thin; scrollbar-color: #6400B2 transparent; }
.pm-adv-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.pm-adv-scroll::-webkit-scrollbar-thumb { background: #6400B2; border-radius: 4px; }
.pm-adv-table-grid > * { min-width: 0; }
</style>

        <section class="mt-[20px]">
            <h2 class="mb-[20px] text-center text-[24px] font-semibold leading-none text-[#a9a9a9]">Paid Stats</h2>
            <div class="grid grid-cols-2 gap-[14px] sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-7">
                <template x-for="card in statCards" :key="card.label">
                    <article class="relative h-[228px] overflow-hidden rounded-[10px] border border-white/40 bg-[#6400B2]">
                        <div class="absolute inset-x-0 bottom-0 rounded-t-[10px] opacity-40" :class="card.fillClass + ' ' + card.toneClass"></div>
                        <div class="relative z-10 pt-[31px] text-center">
                            <p class="mb-[26px] text-[14px] text-[#a9a9a9]" x-text="card.label"></p>
                            <p class="text-[36px] font-medium leading-none text-white" x-text="card.value + '%'"></p>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <div class="figma-modal-overlay"
             x-show="modal.open" x-cloak x-transition
             @keydown.escape.window="closeModal()" @click.self="closeModal()">
            <div class="figma-modal figma-modal--click-details">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="figma-modal-title">Click Details</h3>
                    <button type="button" class="rounded-lg p-1.5 text-white/50 hover:bg-white/10 hover:text-white" @click="closeModal()" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="figma-click-modal-layout">
                    <aside class="figma-click-modal-sidebar">
                        <template x-for="(c, idx) in modal.clicks" :key="c.id ?? idx">
                            <button type="button"
                                    class="figma-click-modal-tab"
                                    :class="idx === modal.activeIndex ? 'is-active' : ''"
                                    @click="modal.activeIndex = idx">
                                <p class="text-sm font-semibold text-white" x-text="`Click ${idx + 1}`"></p>
                                <p class="text-xs text-white/50" x-text="formatDateTime(c.clicked_at || c.last_click_at)"></p>
                            </button>
                        </template>
                        <template x-if="modal.clicks.length === 0">
                            <p class="text-sm text-white/50">No clicks for this visit.</p>
                        </template>
                    </aside>

                    <div class="figma-click-modal-body" x-show="modal.clicks.length > 0">
                        <template x-if="activeClick">
                            <div class="figma-click-modal-fields">
                                <div class="figma-click-modal-compact">
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">IP</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(modal.visit?.ip || activeClick.ip)">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--mono" x-text="modal.visit?.ip || activeClick.ip || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">VPN Hits</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.vpn_hits > 0 ? modal.visit.vpn_hits : '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Data Center</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.data_center_hits > 0 ? modal.visit.data_center_hits : '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Invalid Clicks</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.invalid_clicks ?? 0"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Valid Clicks</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.valid_clicks ?? 0"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Browser</p>
                                        <p class="figma-modal-value" x-text="activeClick.browser_name || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Country</p>
                                        <p class="figma-modal-value inline-flex items-center gap-2">
                                            <img x-show="countryFlagUrl(activeClick.country || modal.visit?.country)"
                                                 :src="countryFlagUrl(activeClick.country || modal.visit?.country)"
                                                 :alt="countryLabel(activeClick.country || modal.visit?.country)"
                                                 class="h-[10px] w-[14px] shrink-0 rounded-[2px] object-cover"
                                                 loading="lazy">
                                            <span x-text="countryLabel(activeClick.country || modal.visit?.country)"></span>
                                        </p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Browser version</p>
                                        <p class="figma-modal-value" x-text="activeClick.browser_version || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Last Click</p>
                                        <p class="figma-modal-value" x-text="formatDateTime(activeClick.last_click_at || modal.visit?.last_click_at)"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">OS</p>
                                        <p class="figma-modal-value" x-text="activeClick.os || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Threat Group</p>
                                        <p class="figma-modal-value" x-text="activeClick.threat_group || modal.visit?.threat_group || 'N/A'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Domain</p>
                                        <p class="figma-modal-value" x-text="modal.visit?.domain || activeClick.domain || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Campaign</p>
                                        <p class="figma-modal-value" x-text="activeClick.campaign || modal.visit?.campaign || 'N/A'"></p>
                                    </div>
                                    <div class="figma-modal-field">
                                        <p class="figma-modal-label">Keyword</p>
                                        <p class="figma-modal-value" x-text="activeClick.keyword || 'N/A'"></p>
                                    </div>
                                </div>

                                <div class="figma-click-modal-wide">
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">Paid ID</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.paid_id)" x-show="activeClick.paid_id">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--long" x-text="activeClick.paid_id || '—'"></p>
                                    </div>
                                    <div class="figma-modal-field figma-modal-field--full">
                                        <div class="figma-modal-field__head">
                                            <p class="figma-modal-label">Path</p>
                                            <button type="button" class="figma-modal-copy-btn" @click="copyText(activeClick.path || modal.visit?.last_path)" x-show="activeClick.path || modal.visit?.last_path">Copy</button>
                                        </div>
                                        <p class="figma-modal-value figma-modal-value--long" x-text="activeClick.path || modal.visit?.last_path || '—'"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
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
                <p class="mt-3 text-[11px] text-white/50" x-text="recordingModal.page_url || ''"></p>
            </div>
        </div>
    </section>
</div>

@include('partials.session-recording-player')

<script>
    function paidMarketingDetailed() {
        const columnCatalog = [
            { key: 'ip', label: 'IP Address', primary: true, min: 120 },
            { key: 'visits', label: 'Visits', primary: true, min: 44 },
            { key: 'domain', label: 'Domain', primary: true, min: 100 },
            { key: 'campaign', label: 'Campaigns', primary: true, min: 100 },
            { key: 'last_click_label', label: 'Last Click', primary: true, min: 76 },
            { key: 'threat_group', label: 'Threat Group', primary: true, min: 84 },
            { key: 'threat_type', label: 'Threat Type', primary: true, min: 76 },
            { key: 'country', label: 'Country', primary: true, min: 72 },
            { key: 'invalid_clicks', label: 'Invalid', primary: true, min: 52 },
            { key: 'valid_clicks', label: 'Valid', primary: true, min: 52 },
            { key: 'session_recording', label: 'Recording', primary: false, min: 44 },
            { key: 'status', label: 'Status', primary: false, min: 72 },
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
            { key: 'intel_risk_score', label: 'Risk Score', primary: false, min: 72 },
            { key: 'intel_risk_level', label: 'Risk Level', primary: false, min: 72 },
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

        let savedOptional = [];
        try {
            savedOptional = JSON.parse(localStorage.getItem('pm-adv-optional-columns') || '[]');
        } catch (e) {}
        if (!savedOptional.includes('session_recording')) {
            savedOptional = [...savedOptional, 'session_recording'];
        }

        return {
            debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 1500,
            fetchTimer: null,
            loading: false,
            filterMenuOpen: false,
            campaignMenuOpen: false,
            columnCatalog,
            optionalColumnKeys: Array.isArray(savedOptional) ? savedOptional : [],
            filters: { ip: '', path: '', domain_id: '', campaign: '', from: '', to: '' },
            campaignOptions: [],
            rows: [],
            statCards: [],
            modal: { open: false, visit: null, clicks: [], activeIndex: 0 },
            recordingModal: { open: false, ip: '', page_url: '', events: [] },
            recordingStop: null,
            get activeClick() { return this.modal.clicks[this.modal.activeIndex] || null; },
            get visibleColumns() {
                return this.columnCatalog.filter(col => col.primary || this.optionalColumnKeys.includes(col.key));
            },
            get gridStyle() {
                const cols = this.visibleColumns.map(col => this.columnTrack(col)).join(' ');
                return `grid-template-columns: ${cols}`;
            },
            columnTrack(col) {
                const min = col.min || 72;
                const key = col.key;
                if (key === 'session_recording') return '40px';
                if (['visits', 'invalid_clicks', 'valid_clicks', 'invalid_visits', 'valid_visits'].includes(key)) {
                    return `minmax(40px, 0.45fr)`;
                }
                if (key === 'ip') return `minmax(${min}px, 1.5fr)`;
                if (key === 'domain' || key === 'campaign' || key === 'path') return `minmax(${min}px, 1.15fr)`;
                if (key === 'country' || key === 'last_click_label' || key === 'last_seen_label') {
                    return `minmax(${min}px, 0.95fr)`;
                }
                if (key === 'threat_group' || key === 'threat_type' || key === 'action_taken' || key === 'status') {
                    return `minmax(${min}px, 0.85fr)`;
                }
                return `minmax(${min}px, 1fr)`;
            },
            init() {
                const id = new URLSearchParams(window.location.search).get('domain_id');
                if (id) this.filters.domain_id = id;
                this.syncHeaderDates();
                if (!this.filters.from || !this.filters.to) {
                    const today = new Date();
                    const start = new Date(today.getTime() - 6 * 86400000);
                    this.filters.from = start.toISOString().slice(0, 10);
                    this.filters.to = today.toISOString().slice(0, 10);
                }
                if (this.filters.domain_id) {
                    this.loadCampaignsForDomain();
                }
                this.fetchNow();
                window.addEventListener('promotix:date-range', () => {
                    this.syncHeaderDates();
                    this.scheduleFetch();
                });
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
                this.scheduleFetch();
            },
            scheduleFetch(fast = false) {
                clearTimeout(this.fetchTimer);
                this.fetchTimer = setTimeout(() => this.fetchNow(), fast ? 350 : this.debounceMs);
            },
            async onDomainChange() {
                this.filters.campaign = '';
                this.campaignOptions = [];
                this.campaignMenuOpen = false;
                if (this.filters.domain_id) {
                    await this.loadCampaignsForDomain();
                }
                this.scheduleFetch(true);
            },
            async openCampaignMenu() {
                if (this.filters.domain_id && this.campaignOptions.length === 0) {
                    await this.loadCampaignsForDomain();
                }
                this.campaignMenuOpen = !this.campaignMenuOpen;
            },
            toggleOptionalColumn(key) {
                if (this.optionalColumnKeys.includes(key)) {
                    this.optionalColumnKeys = this.optionalColumnKeys.filter(k => k !== key);
                } else {
                    this.optionalColumnKeys = [...this.optionalColumnKeys, key];
                }
                try {
                    localStorage.setItem('pm-adv-optional-columns', JSON.stringify(this.optionalColumnKeys));
                } catch (e) {}
            },
            cellValue(visit, key) {
                if (key === 'ip') return this.ipLabel(visit);
                if (key === 'campaign') return visit.campaign || 'N/A';
                const value = visit[key];
                if (value === 0) return '0';
                if (value === null || value === undefined || value === '') return '—';
                return String(value);
            },
            selectCampaign(name) {
                this.filters.campaign = name;
                this.campaignMenuOpen = false;
                this.scheduleFetch(true);
            },
            async loadCampaignsForDomain() {
                if (!this.filters.domain_id) {
                    this.campaignOptions = [];
                    return;
                }
                const params = new URLSearchParams();
                params.set('domain_id', this.filters.domain_id);
                if (this.filters.from) params.set('from', this.filters.from);
                if (this.filters.to) params.set('to', this.filters.to);
                try {
                    const rows = await fetch(`/paid-marketing/campaigns?${params}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json());
                    const list = Array.isArray(rows) ? rows : (rows.campaigns || []);
                    this.campaignOptions = [...new Set(list.map(r => r.campaign).filter(Boolean))].sort();
                } catch (e) {
                    this.campaignOptions = [];
                }
            },
            queryString() {
                const p = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (v !== '' && v != null) p.set(k, v);
                });
                return p.toString();
            },
            csvHref() {
                const qs = this.queryString();
                return `{{ route('paid-marketing.detailed-export') }}${qs ? '?' + qs : ''}`;
            },
            async fetchNow() {
                this.loading = true;
                try {
                    const qs = this.queryString();
                    const res = await fetch(`{{ route('paid-marketing.detailed-visits') }}${qs ? '?' + qs : ''}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('fetch failed');
                    const data = await res.json();
                    this.rows = data.rows || [];
                    this.statCards = data.stats?.cards || [];
                } catch (e) {
                    console.error(e);
                } finally {
                    this.loading = false;
                    window.promotixPageLoader?.hide();
                }
            },
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
            async openRecording(visit) {
                if (!visit?.session_recording_id) return;
                try {
                    const res = await fetch(`{{ route('paid-marketing.session-recording', ['recording' => '__ID__']) }}`.replace('__ID__', visit.session_recording_id), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('recording fetch failed');
                    const data = await res.json();
                    this.recordingModal = {
                        open: true,
                        ip: data.ip || visit.ip,
                        page_url: data.page_url || '',
                        events: data.events || [],
                    };
                    this.$nextTick(() => this.renderRecording(data.events || []));
                } catch (e) {
                    console.error(e);
                }
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
            formatDateTime(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return String(value);
                const tz = document.querySelector('meta[name="user-timezone"]')?.content || undefined;
                return date.toLocaleString(undefined, tz ? { timeZone: tz } : undefined);
            },
            ipLabel(visit) {
                const raw = String(visit?.ip || '');
                const parts = Array.isArray(visit?.ip_parts) && visit.ip_parts.length
                    ? visit.ip_parts
                    : raw.split(',').map((p) => p.trim()).filter(Boolean);
                const first = parts[0] || raw || '—';
                if (first.length > 20) return first.slice(0, 18) + '…';
                return first;
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
                const raw = String(value || '').trim();
                return raw || '—';
            },
            countryFlagUrl(value) {
                const code = this.countryCode(value).toLowerCase();
                if (!/^[a-z]{2}$/.test(code)) return '';
                return `https://flagcdn.com/w20/${code}.png`;
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
        };
    }
</script>
@endsection
