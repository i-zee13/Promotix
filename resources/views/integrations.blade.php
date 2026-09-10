@extends('layouts.admin')

@section('title', 'Google Ads | Platform Integration')

@php
    $googleOAuthConnected = $connections->isNotEmpty();
    $directConnected = $directAds->isNotEmpty();
    $googleStatusConnected = $platformReady ?? false;
    $menuDomain = $domains->first();
    $primaryConnection = $connections->first();
@endphp

@section('rightbar')
<div class="figma-rightbar-default pi-rightbar">
    @include('partials.figma-rightbar-header-actions')

    <div class="figma-rightbar-center mt-[16px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] w-full max-w-[168px] text-[16px] font-bold text-[#a9a9a9]">Quick Actions</h2>
        <div class="mx-auto grid w-full max-w-[168px] grid-cols-2 gap-[10px]">
            <a href="#" class="paid-quick-action" title="Test Integration" @click.prevent="openTestProtectionModal()">
                @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[16px] w-[16px]'])
                <span>Test Integration</span>
            </a>
            @if ($primaryConnection)
                <a href="#" class="paid-quick-action" title="Sync Campaigns" @click.prevent="openSyncPreview()">
                    @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[16px] w-[16px]'])
                    <span>Sync Campaigns</span>
                </a>
            @else
                <a href="#" class="paid-quick-action" title="Sync Campaigns" @click.prevent="openConnectGoogleModal()">
                    @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[16px] w-[16px]'])
                    <span>Sync Campaigns</span>
                </a>
            @endif
            <a href="#" class="paid-quick-action" title="Install Tag" @click.prevent="openInstallTagsModal()">
                @include('partials.sidebar-icon', ['name' => 'tag', 'class' => 'h-[16px] w-[16px]'])
                <span>Install Tag</span>
            </a>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-promotix-settings',{detail:{tab:'reports'}}))" class="paid-quick-action" title="View Reports">
                @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[16px] w-[16px]'])
                <span>View Reports</span>
            </button>
        </div>
    </div>

    <div class="mt-[18px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">System Overview</h2>
        <div class="figma-rightbar-sys space-y-[8px] text-[10px] text-white/75">
            <div class="paid-sys-row">
                <span>Server status</span>
                <span id="pi-sys-server-status" class="text-emerald-200">Online</span>
            </div>
            <div class="paid-sys-row">
                <span>Events today</span>
                <span id="pi-sys-events-today" class="text-white/90">{{ number_format((int) ($connectionHealth['events_today'] ?? 0)) }}</span>
            </div>
            <div class="paid-sys-row">
                <span>Tracking</span>
                <span id="pi-sys-tracking" class="{{ ($tagReady ?? false) ? 'text-emerald-200' : 'text-white/55' }}">
                    {{ ($tagReady ?? false) ? 'Active' : 'Pending' }}
                </span>
            </div>
            <div class="paid-sys-row">
                <span>Google Ads API</span>
                <span id="pi-sys-google-api" class="{{ $googleOAuthConnected ? 'text-emerald-200' : 'text-white/55' }}">
                    {{ $googleOAuthConnected ? 'Connected' : 'Not connected' }}
                </span>
            </div>
        </div>
    </div>
</div>
<style>
    .pi-rightbar .paid-quick-action {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 64px;
        padding: 8px 6px;
        border-radius: 6px;
        background: var(--brand-primary);
        color: #fff;
        text-align: center;
        font-size: 9px;
        font-weight: 600;
        line-height: 1.2;
        border: 0;
        cursor: pointer;
        text-decoration: none;
        width: 100%;
    }
    .pi-rightbar .paid-quick-action:hover {
        background: var(--figma-chrome-accent-hover);
        color: #fff;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.promotixPageLoader?.show('Loading Integrations…');
    fetch('/overview/summary', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => {
            if (!data) return;
            const conn = data.connectionStatus || {};
            const eventsEl = document.getElementById('pi-sys-events-today');
            const apiEl = document.getElementById('pi-sys-google-api');
            const trackEl = document.getElementById('pi-sys-tracking');
            if (eventsEl && conn.eventsToday != null) {
                eventsEl.textContent = Number(conn.eventsToday).toLocaleString();
            }
            if (apiEl && conn.googleAdsApi) {
                const label = String(conn.googleAdsApi);
                apiEl.textContent = label;
                apiEl.className = /not connected|error/i.test(label) ? 'text-white/55' : 'text-emerald-200';
            }
            if (trackEl && conn.tracking) {
                const on = /healthy|active/i.test(String(conn.tracking));
                trackEl.textContent = on ? 'Active' : 'Pending';
                trackEl.className = on ? 'text-emerald-200' : 'text-white/55';
            }
        })
        .catch(() => {})
        .finally(() => window.promotixPageLoader?.hide());
});
</script>
@endsection

@section('content')
<div
    class="brand-page-bg min-h-[calc(100vh-49px)]"
    x-data="platformIntegrations(@js([
        'csrf' => csrf_token(),
        'directStoreUrl' => url('/integrations/direct-ads'),
        'trackingLink' => $menuDomain ? url('/tag/' . $menuDomain->domain_key . '.js') : null,
        'statusUrl' => url('/integrations/status'),
        'logsUrl' => url('/integrations/logs'),
        'testUrl' => $primaryConnection ? route('integrations.google.test', $primaryConnection) : null,
        'disconnectUrl' => $primaryConnection ? route('integrations.google.disconnect', $primaryConnection) : null,
        'paidMarketingConnectUrl' => route('domains.paid-marketing.connect', ['domain' => 0]),
        'tagSetupUrl' => $tagSetupUrl ?? route('domains.index'),
        'domainsIndexUrl' => route('domains.index'),
        'domainConnections' => $domainConnections,
        'connectionHealth' => $connectionHealth ?? [],
        'tagReady' => (bool) ($tagReady ?? false),
        'botReady' => (bool) ($botReady ?? false),
        'googleOAuthConnected' => (bool) $googleOAuthConnected,
        'syncLogs' => ($syncLogs ?? collect())->map(fn ($log) => [
            'id' => $log->id,
            'action' => $log->action,
            'status' => $log->status,
            'message' => $log->message,
            'domain' => $log->domain?->hostname,
            'created_at' => optional($log->created_at)->toIso8601String(),
        ])->values(),
        'directInitial' => $directAds->map(fn ($row) => [
            'id' => $row->id,
            'platform' => $row->platform,
            'account_label' => $row->account_label,
            'account_id' => $row->account_id,
            'tag_id' => $row->tag_id,
        ])->values(),
        'platformRows' => ($platformRows ?? collect())->values(),
        'trackingIds' => ($trackingIds ?? collect())->values(),
        'setupProgress' => $setupProgress ?? [],
        'setupProgressByDomain' => $setupProgressByDomain ?? [],
        'googleAdsSummary' => $googleAdsSummary ?? [],
        'trackingInstallation' => $trackingInstallation ?? [],
        'ipExclusionRows' => ($ipExclusionRows ?? collect())->values(),
        'accountsForConnect' => ($accounts ?? collect())->take(40)->map(fn ($a) => [
            'id' => $a->id,
            'label' => $a->displayLabel(),
            'customer_id' => $a->formattedCustomerId() ?: ($a->display_customer_id ?: $a->customer_id),
            'google_tag_id' => $a->resolvedGoogleTagId() ?: $a->google_tag_id,
        ])->values(),
        'audienceCampaignsUrl' => route('integrations.google.audience-campaigns'),
        'ga4StatusUrl' => route('integrations.google.ga4-status'),
        'createAudienceUrl' => route('integrations.google.create-audience'),
        'applyAudienceUrl' => route('integrations.google.apply-audience'),
    ]))"
    @platform-menu.window="handlePlatformMenu($event.detail)"
>
    <section class="mx-auto w-full max-w-[1180px] px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[19px] xl:pt-[68px]">
        <div class="bp-adv-page-head mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Google Ads</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Platform Integration</span>
            </div>

            <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--pi ml-auto flex min-h-[54px] w-fit max-w-full flex-nowrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)]">
                <label class="flex w-[150px] shrink-0 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="selectedDomainId" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Domains</option>
                            @foreach ($manualDomains as $domain)
                                <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[170px] shrink-0 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Google Ads Account</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="selectedAdsAccountId" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Accounts</option>
                            @php
                                $linkedFilterAccounts = collect($trackingIds ?? [])
                                    ->unique('account_id')
                                    ->values();
                            @endphp
                            @foreach ($linkedFilterAccounts as $row)
                                <option value="{{ $row['account_id'] }}">{{ $row['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[140px] shrink-0 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="selectedLandingPage" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Pages</option>
                        </select>
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[var(--brand-primary)]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        {{-- Spec Image 1: corrected Google Ads dashboard --}}
        @include('partials.integrations.google-ads-dashboard')

        <style>
            html.pi-spec-modal-open,
            html.pi-spec-modal-open body {
                overflow: hidden !important;
            }
            .pi-spec-modal-root {
                position: fixed !important;
                inset: 0 !important;
                z-index: 2147483000 !important;
                isolation: isolate;
                /* display must NOT use !important — Alpine x-show sets inline display:none */
                display: flex;
                align-items: flex-start;
                justify-content: center;
                padding: 40px 16px 24px !important;
                box-sizing: border-box;
                overflow: auto;
            }
            .pi-spec-modal-backdrop {
                background: rgba(0, 0, 0, 0.88) !important;
            }
            .pi-spec-modal-panel {
                background: #121212 !important;
                color: #fff !important;
                position: relative;
                z-index: 1;
                display: flex;
                flex-direction: column;
                width: 100%;
                max-height: min(80vh, 720px) !important;
                overflow: hidden;
            }
            .pi-spec-modal-body {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                overscroll-behavior: contain;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: rgba(255, 102, 0, 0.7) rgba(255, 255, 255, 0.08);
            }
            .pi-spec-modal-body::-webkit-scrollbar {
                width: 8px;
            }
            .pi-spec-modal-body::-webkit-scrollbar-thumb {
                background: rgba(255, 102, 0, 0.65);
                border-radius: 999px;
            }
            .pi-spec-modal-body::-webkit-scrollbar-track {
                background: rgba(255, 255, 255, 0.06);
            }
            /* While modal is open, kill any competing stacking from page widgets */
            html.pi-spec-modal-open .pi-setup-card,
            html.pi-spec-modal-open .pi-platforms-card,
            html.pi-spec-modal-open .pi-first-row,
            html.pi-spec-modal-open .pi-first-row--spec,
            html.pi-spec-modal-open .bp-adv-page-head,
            html.pi-spec-modal-open .pi-setup-step,
            html.pi-spec-modal-open .pi-setup-track {
                z-index: 0 !important;
                pointer-events: none !important;
            }
            html.pi-spec-modal-open .pi-setup-card {
                visibility: hidden !important;
            }
            html.pi-spec-modal-open .figma-main {
                z-index: 0 !important;
            }
            .pi-first-row {
                display: grid;
                gap: 14px;
                align-items: stretch;
            }
            @media (min-width: 1100px) {
                .pi-first-row {
                    grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.85fr);
                }
            }
            .figma-filter-bar--pi {
                overflow: visible;
                width: fit-content;
                max-width: 100%;
            }
            .figma-filter-bar--pi > label {
                flex: 0 0 auto;
            }
            .figma-filter-bar--pi .figma-filter-calendar-host {
                flex: 0 0 auto;
            }
            @media (max-width: 900px) {
                .figma-filter-bar--pi {
                    width: 100%;
                    flex-wrap: wrap;
                }
            }
            .pi-connect-card,
            .pi-side-card {
                border-radius: 10px;
                border: 1px solid color-mix(in srgb, var(--brand-primary) 65%, transparent);
                background: #111111;
                padding: 16px 18px;
            }
            .pi-section-title {
                margin-bottom: 14px;
                font-size: 18px;
                font-weight: 600;
                color: #fff;
            }
            .pi-connect-grid {
                display: grid;
                gap: 14px;
            }
            @media (min-width: 720px) {
                .pi-connect-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (min-width: 1400px) {
                .pi-connect-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
            }
            .pi-panel {
                border-radius: 10px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: var(--brand-primary);
                padding: 14px;
                min-height: 230px;
            }
            .pi-side-stack {
                display: grid;
                gap: 14px;
                align-content: start;
            }
            .pi-ghost-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                height: 28px;
                max-width: 168px;
                width: 100%;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.35);
                background: rgba(0, 0, 0, 0.12);
                padding: 0 10px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.95);
            }
            .pi-ghost-btn:hover { background: rgba(0, 0, 0, 0.22); }
            .pi-primary-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                height: 30px;
                max-width: 168px;
                width: 100%;
                border-radius: 6px;
                border: 0;
                background: #ffffff;
                padding: 0 10px;
                font-size: 11px;
                font-weight: 600;
                color: var(--brand-primary);
            }
            .pi-primary-btn:hover { background: var(--brand-tint-hover); color: var(--brand-secondary); }
            .pi-primary-btn--wide { max-width: none; height: 34px; margin-top: 4px; }
            .pi-text-link {
                display: inline-flex;
                font-size: 11px;
                font-weight: 600;
                color: #ffffff;
            }
            .pi-text-link:hover { color: rgba(255, 255, 255, 0.8); }
            .pi-status-pill {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                border-radius: 999px;
                padding: 3px 8px;
                font-size: 10px;
                font-weight: 600;
                line-height: 1;
                white-space: nowrap;
            }
            .pi-status-pill.is-on { background: rgba(34, 197, 94, 0.22); color: #bbf7d0; }
            .pi-status-pill.is-off { background: rgba(0, 0, 0, 0.2); color: rgba(255, 255, 255, 0.7); }
            .pi-status-pill.is-warn { background: rgba(255, 102, 0, 0.22); color: #ffd0b0; }
            .pi-status-dot {
                width: 6px;
                height: 6px;
                border-radius: 999px;
                background: currentColor;
            }
            .pi-field {
                display: flex;
                align-items: center;
                gap: 6px;
                height: 34px;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.28);
                background: rgba(0, 0, 0, 0.28);
                padding: 0 8px;
            }
            .pi-field__input {
                min-width: 0;
                flex: 1;
                border: 0;
                background: transparent;
                color: #fff;
                font-size: 12px;
                outline: none;
            }
            .pi-field__input::placeholder { color: rgba(255, 255, 255, 0.4); }
            .pi-field__copy {
                display: inline-flex;
                color: rgba(255, 255, 255, 0.65);
            }
            .pi-field__copy:hover { color: #fff; }
            .pi-status-row {
                display: flex;
                align-items: center;
                gap: 10px;
                border-radius: 8px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                background: rgba(0, 0, 0, 0.28);
                padding: 10px 12px;
            }
            .pi-health-ring {
                --pi-health: 0;
                width: 92px;
                height: 92px;
                border-radius: 999px;
                background: conic-gradient(#22c55e calc(var(--pi-health) * 1%), color-mix(in srgb, var(--brand-primary) 55%, transparent) 0);
                display: grid;
                place-items: center;
            }
            .pi-health-ring__inner {
                width: 68px;
                height: 68px;
                border-radius: 999px;
                background: #111111;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .pi-setup-card,
            .pi-platforms-card {
                margin-top: 20px;
                scroll-margin-top: 80px;
                border-radius: 10px;
                border: 1px solid var(--brand-secondary);
                background: #0d0d0d;
                padding: 18px 18px 16px;
                position: relative;
                z-index: 0;
                isolation: isolate;
            }
            .pi-platforms-card {
                background: #ffffff;
                border-color: color-mix(in srgb, var(--brand-primary) 35%, transparent);
            }
            .pi-setup-title {
                margin: 0 0 22px;
                font-size: 14px;
                font-weight: 600;
                color: #fff;
            }
            .pi-setup-track {
                display: grid;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 8px;
                position: relative;
                /* Keep step z-index inside this card — never above page modals */
                isolation: isolate;
                z-index: 0;
            }
            .pi-setup-track__fill {
                position: absolute;
                left: calc(100% / 12);
                top: 18px;
                height: 2px;
                width: calc((100% - (100% / 6)) * (var(--pi-setup-fill, 0) / 100));
                max-width: calc(100% - (100% / 6));
                background: var(--brand-primary);
                opacity: 0.95;
                z-index: 0;
                border-radius: 999px;
                transition: width 0.25s ease;
                pointer-events: none;
            }
            .pi-setup-track::before { display: none; }
            .pi-setup-step {
                position: relative;
                z-index: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                min-width: 0;
            }
            .pi-setup-icon {
                width: 36px;
                height: 36px;
                border-radius: 999px;
                display: grid;
                place-items: center;
                background: var(--brand-primary);
                color: #fff;
                box-shadow: 0 0 0 4px #0d0d0d;
            }
            .pi-setup-icon.is-pending {
                background: color-mix(in srgb, var(--brand-primary) 35%, transparent);
                color: rgba(255, 255, 255, 0.55);
            }
            .pi-setup-label {
                margin-top: 10px;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
                line-height: 1.25;
            }
            .pi-setup-detail {
                margin-top: 4px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.45);
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .pi-platforms-head {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                margin-bottom: 16px;
            }
            .pi-platforms-head h2 {
                margin: 0;
                font-size: 22px;
                font-weight: 600;
                color: #121212;
                line-height: 1.1;
            }
            .pi-platforms-head p {
                margin: 4px 0 0;
                font-size: 13px;
                color: rgba(18, 18, 18, 0.55);
            }
            .pi-platforms-tools {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
            }
            .pi-search {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 200px;
                height: 36px;
                padding: 0 12px;
                border-radius: 8px;
                border: 1px solid rgba(18, 18, 18, 0.16);
                background: #f4f4f4;
                color: rgba(18, 18, 18, 0.45);
            }
            .pi-search input {
                width: 100%;
                border: 0;
                background: transparent;
                color: #121212;
                font-size: 12px;
                outline: none;
            }
            .pi-search input::placeholder { color: rgba(18, 18, 18, 0.4); }
            .pi-refresh-btn {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 36px;
                padding: 0 14px;
                border-radius: 8px;
                border: 1px solid rgba(18, 18, 18, 0.16);
                background: #ffffff;
                color: #121212;
                font-size: 12px;
                font-weight: 500;
            }
            .pi-refresh-btn:hover { background: #f4f4f4; }
            .pi-add-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                height: 36px;
                padding: 0 14px;
                border-radius: 8px;
                border: 1px solid color-mix(in srgb, var(--brand-primary) 25%, transparent);
                background: #ffffff;
                color: var(--brand-primary);
                font-size: 12px;
                font-weight: 600;
            }
            .pi-add-btn:hover { background: #f8f0ff; }
            .pi-table-wrap {
                overflow-x: auto;
                border-radius: 8px;
                border: 1px solid rgba(18, 18, 18, 0.1);
                background: #ffffff;
            }
            .pi-table {
                width: 100%;
                min-width: 980px;
                border-collapse: collapse;
                text-align: left;
            }
            .pi-table thead th {
                padding: 12px 14px;
                font-size: 12px;
                font-weight: 600;
                color: #ffffff;
                border-bottom: 1px solid color-mix(in srgb, var(--brand-primary) 35%, transparent);
                white-space: nowrap;
                background: var(--brand-primary);
            }
            .pi-table tbody td {
                padding: 14px;
                font-size: 12px;
                color: #121212;
                border-bottom: 1px solid rgba(18, 18, 18, 0.06);
                vertical-align: middle;
                background: #ffffff;
            }
            .pi-table tbody tr:last-child td { border-bottom: 0; }
            .pi-table tbody tr:hover td { background: #f8f0ff; }
            .pi-plat-name {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-size: 13px;
                font-weight: 600;
                color: #121212;
            }
            .pi-plat-logo {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                background: #f4f4f4;
                display: grid;
                place-items: center;
                overflow: hidden;
                flex-shrink: 0;
            }
            .pi-acct-primary { font-size: 13px; font-weight: 500; color: #121212; }
            .pi-acct-secondary { margin-top: 2px; font-size: 11px; color: rgba(18, 18, 18, 0.5); }
            .pi-prot {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                color: rgba(18, 18, 18, 0.88);
                white-space: nowrap;
            }
            .pi-prot svg { flex-shrink: 0; }
            .pi-prot.is-audience svg { color: var(--brand-primary); }
            .pi-prot.is-track svg { color: #2563eb; }
            .pi-status-connected {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 26px;
                padding: 0 10px;
                border-radius: 999px;
                background: rgba(34, 197, 94, 0.12);
                color: #15803d;
                font-size: 12px;
                font-weight: 500;
                white-space: nowrap;
            }
            .pi-status-connected::before {
                content: '';
                width: 7px;
                height: 7px;
                border-radius: 999px;
                background: #22c55e;
            }
            .pi-status-pending {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 26px;
                padding: 0 10px;
                border-radius: 999px;
                background: rgba(18, 18, 18, 0.06);
                color: rgba(18, 18, 18, 0.55);
                font-size: 12px;
                white-space: nowrap;
            }
            .pi-status-pending::before {
                content: '';
                width: 7px;
                height: 7px;
                border-radius: 999px;
                background: rgba(18, 18, 18, 0.35);
            }
            .pi-row-actions {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                white-space: nowrap;
            }
            .pi-row-link {
                color: var(--brand-primary);
                font-size: 12px;
                font-weight: 600;
            }
            .pi-row-link:hover { color: var(--brand-secondary); }
            .pi-platforms-card .integration-row-menu button,
            .pi-platforms-card .text-white\/45 {
                color: rgba(18, 18, 18, 0.45) !important;
            }
            .pi-platforms-card .hover\:text-white:hover {
                color: #121212 !important;
            }
            .pi-table-foot {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                margin-top: 14px;
                padding-top: 4px;
            }
            .pi-table-foot__meta {
                font-size: 12px;
                color: rgba(18, 18, 18, 0.5);
            }
            .pi-pager {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .pi-pager button {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                border: 1px solid rgba(18, 18, 18, 0.14);
                background: #ffffff;
                color: rgba(18, 18, 18, 0.55);
                display: grid;
                place-items: center;
            }
            .pi-pager button.is-active {
                border-color: transparent;
                background: var(--brand-primary);
                color: #fff;
            }
            .pi-pager button:disabled { opacity: 0.35; cursor: not-allowed; }
            .pi-platforms-card td.text-white\/45,
            .pi-platforms-card .text-\[13px\].text-white\/45 {
                color: rgba(18, 18, 18, 0.45) !important;
            }
            .pi-scroll-box {
                max-height: 260px;
                overflow-y: auto;
                overflow-x: hidden;
                padding-right: 4px;
                overscroll-behavior: contain;
            }
            .pi-scroll-box::-webkit-scrollbar {
                width: 6px;
            }
            .pi-scroll-box::-webkit-scrollbar-track {
                background: transparent;
            }
            .pi-scroll-box::-webkit-scrollbar-thumb {
                background: color-mix(in srgb, var(--brand-primary) 55%, transparent);
                border-radius: 999px;
            }
            .pi-scroll-box::-webkit-scrollbar-thumb:hover {
                background: color-mix(in srgb, var(--brand-primary) 80%, transparent);
            }
            @media (max-width: 900px) {
                .pi-setup-track {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    row-gap: 18px;
                }
                .pi-setup-track::before,
                .pi-setup-track__fill { display: none; }
            }
            @media (max-width: 640px) {
                .pi-setup-track { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            html.light-mode .pi-connect-card,
            html.light-mode .pi-side-card {
                background: #ffffff;
                border-color: var(--brand-tint-border);
                box-shadow: 0 1px 10px color-mix(in srgb, var(--brand-primary) 8%, transparent);
            }
            html.light-mode .pi-section-title {
                color: #2d2d3a;
            }
            html.light-mode .pi-setup-card {
                background: #ffffff;
                border-color: var(--brand-tint-border);
                box-shadow: 0 1px 10px color-mix(in srgb, var(--brand-primary) 8%, transparent);
            }
            html.light-mode .pi-setup-title,
            html.light-mode .pi-setup-label {
                color: #2d2d3a;
            }
            html.light-mode .pi-setup-detail {
                color: #6b6578;
            }
            html.light-mode .pi-setup-icon {
                box-shadow: 0 0 0 4px #ffffff;
            }
            html.light-mode .pi-health-ring__inner {
                background: #ffffff;
            }
            html.light-mode .pi-health-ring__inner [class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-status-row {
                background: var(--brand-tint-soft);
                border-color: var(--brand-tint-border);
            }
            html.light-mode .pi-side-card [class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-side-card .text-white\/80 {
                color: #4a4458 !important;
            }
            html.light-mode .pi-side-card .text-white\/40,
            html.light-mode .pi-side-card .text-white\/55,
            html.light-mode .pi-side-card .text-white\/65,
            html.light-mode .pi-side-card .text-white\/70 {
                color: #6b6578 !important;
            }
            html.light-mode .pi-side-card a[class*="text-[#B893D8]"] {
                color: var(--brand-primary) !important;
            }
            html.light-mode .pi-side-card a[class*="text-[#B893D8]"]:hover {
                color: var(--brand-secondary) !important;
            }
            /* Soft tint + dark ink — pale-on-pale was unreadable in light mode */
            html.light-mode .pi-status-pill.is-on {
                background: rgba(22, 163, 74, 0.16);
                color: #166534;
            }
            html.light-mode .pi-status-pill.is-warn {
                background: rgba(234, 88, 12, 0.16);
                color: #9a3412;
            }
            html.light-mode .pi-status-pill.is-off {
                background: #f3f4f6;
                color: #4b5563;
            }
            html.light-mode .pi-platforms-card details[class*="bg-black"] {
                background: var(--brand-tint-soft) !important;
                border-color: var(--brand-tint-border) !important;
            }
            html.light-mode .pi-platforms-card details summary[class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-platforms-card details [class*="text-white"] {
                color: #5c5470 !important;
            }
            html.light-mode .pi-platforms-card details .text-white\/90 {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-platforms-card details [class*="text-[#B893D8]"] {
                color: var(--brand-primary) !important;
            }
            html.light-mode .pi-add-btn:hover {
                background: var(--brand-tint-hover);
            }
            html.light-mode .pi-table tbody tr:hover td {
                background: var(--brand-tint-hover);
            }
            html.light-mode .pi-footer-card {
                background: #ffffff !important;
                border-color: var(--brand-tint-border) !important;
                box-shadow: 0 1px 10px color-mix(in srgb, var(--brand-primary) 8%, transparent);
            }
            html.light-mode .pi-footer-card h2,
            html.light-mode .pi-footer-card [class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-footer-card .text-white\/60,
            html.light-mode .pi-footer-card .text-white\/55,
            html.light-mode .pi-footer-card .text-white\/65,
            html.light-mode .pi-footer-card .text-white\/45,
            html.light-mode .pi-footer-card .text-white\/40 {
                color: #6b6578 !important;
            }
            html.light-mode .pi-footer-card .border-white\/15,
            html.light-mode .pi-footer-card .border-white\/10,
            html.light-mode .pi-footer-card .border-white\/25 {
                border-color: var(--brand-tint-border) !important;
            }
            html.light-mode .pi-footer-card [class*="bg-[var(--brand-primary)]"] {
                background: var(--brand-tint-selected) !important;
            }
            html.light-mode .pi-footer-card .bg-white\/5 {
                background: var(--brand-tint-soft) !important;
            }
            html.light-mode .pi-footer-card code.bg-black\/40 {
                background: #ffffff !important;
                color: var(--brand-secondary) !important;
                border: 1px solid var(--brand-tint-border);
            }
            html.light-mode .pi-footer-card .bg-emerald-500\/20 {
                background: rgba(34, 197, 94, 0.14) !important;
            }
            html.light-mode .pi-footer-card .text-emerald-200 {
                color: #15803d !important;
            }
            html.light-mode .pi-footer-card .bg-rose-500\/20 {
                background: rgba(244, 63, 94, 0.12) !important;
            }
            html.light-mode .pi-footer-card .text-rose-200 {
                color: #be123c !important;
            }
            .ae-field {
                background: var(--brand-input-bg, #0d0d0d);
                color: var(--brand-text, #fff);
                color-scheme: dark;
            }
            .ae-field option {
                background: var(--brand-surface, #212121);
                color: var(--brand-text, #fff);
            }
        </style>

        {{-- Setup Progress --}}
        <section class="pi-setup-card">
            <h2 class="pi-setup-title">Setup Progress</h2>
            <div class="pi-setup-track" :style="`--pi-setup-fill: ${setupProgressFill}`">
                <div class="pi-setup-track__fill" aria-hidden="true"></div>
                <template x-for="step in activeSetupProgress" :key="step.key">
                    <div class="pi-setup-step">
                        <div class="pi-setup-icon" :class="step.done ? '' : 'is-pending'">
                            <template x-if="step.key === 'domain' && step.done">
                                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 100-18 9 9 0 000 18z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8M12 3c2.5 2.6 3.8 5.7 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.7-3.8-9S9.5 5.6 12 3z"/>
                                </svg>
                            </template>
                            <template x-if="step.key === 'domain' && !step.done">
                                <svg class="h-[16px] w-[16px] opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 100-18 9 9 0 000 18z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8M12 3c2.5 2.6 3.8 5.7 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.7-3.8-9S9.5 5.6 12 3z"/>
                                </svg>
                            </template>
                            <template x-if="step.key !== 'domain' && step.done">
                                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </template>
                            <template x-if="step.key !== 'domain' && !step.done">
                                <span class="h-[8px] w-[8px] rounded-full bg-white/40"></span>
                            </template>
            </div>
                        <div class="pi-setup-label" x-text="step.label"></div>
                        <div class="pi-setup-detail" :title="step.detail || ''" x-text="step.detail || '—'"></div>
                    </div>
                </template>
            </div>
        </section>

        {{-- Connected Platforms --}}
        <section id="connected-platforms" class="pi-platforms-card">
            <details class="mb-[14px] rounded-[10px] border border-white/10 bg-black/20 px-[14px] py-[12px]">
                <summary class="cursor-pointer text-[12px] font-semibold text-white">Pixel Guard &amp; Audience Exclusion guidance</summary>
                <div class="mt-[10px] grid gap-[10px] text-[11px] leading-relaxed text-white/70 sm:grid-cols-2">
            <div>
                        <p class="font-semibold" style="color:color-mix(in srgb, var(--brand-primary) 70%, white)">Pixel Guard</p>
                        <ul class="mt-[6px] list-disc space-y-1 pl-[16px]">
                            <li><strong class="text-white/90">Google Tag ID</strong> — the AW-/GT- ID from Google Ads / Tag Manager for this domain.</li>
                            <li>Tag must match the hostname on the mapping; mismatch fails Save validation.</li>
                            <li>Prerequisite: tracking tag installed + Google Ads OAuth connected for Pixel Guard protection type.</li>
                        </ul>
                    </div>
                    <div>
                        <p class="font-semibold" style="color:color-mix(in srgb, var(--brand-primary) 70%, white)">Audience Exclusion</p>
                        <ul class="mt-[6px] list-disc space-y-1 pl-[16px]">
                            <li>Create Google Ads conversion <strong class="text-white/90">promo for ppc - invalid Users</strong>, then paste Conversion ID + Label.</li>
                            <li>Match each row to the domain tag (Installed / Not detected).</li>
                            <li>Use <strong class="text-white/90">Connect Additional audience</strong> for more conversion mappings. Save validates required fields.</li>
                            <li>Open from Google platform menu → Set Up Audience Exclusion. IP push rules remain in Detection Panel.</li>
                        </ul>
                    </div>
                </div>
            </details>
            <div class="pi-platforms-head">
                <div>
                    <h2>Connected Platforms</h2>
                    <p>Linked Google Ads accounts</p>
                </div>
                <div class="pi-platforms-tools">
                    <label class="pi-search">
                        <svg class="h-[14px] w-[14px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/>
                        </svg>
                        <input type="search" placeholder="Search platform..." x-model="platformSearch" @input="platformPage = 1">
                    </label>
                    <button type="button" class="pi-refresh-btn" @click="refreshPlatforms()">
                        <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 13a7 7 0 0112.2-4.5L20 11M4 13l2.8 2.5A7 7 0 0019 13"/>
                        </svg>
                        Refresh
                    </button>
                    <button type="button" class="pi-add-btn" @click="openConnectGoogleModal()">+ Add Connection</button>
                </div>
            </div>

            <div class="pi-table-wrap">
                <table class="pi-table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Account / Domain</th>
                            <th>API</th>
                            <th>Script</th>
                            <th>Last Event</th>
                            <th>Protection</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($platformRows ?? collect()) as $row)
                            <tr x-show="platformRowVisible(@js($row['search'] ?? ''))">
                                <td>
                                    <span class="pi-plat-name">
                                        <span class="pi-plat-logo">
                                            @include('partials.icons.google', ['class' => 'h-[16px] w-[16px]'])
                                        </span>
                                        <span>{{ $row['platform'] }}</span>
                                    </span>
                                </td>
                                <td>
                                    <div class="pi-acct-primary truncate" title="{{ $row['account_primary'] }}">{{ $row['account_primary'] }}</div>
                                    <div class="pi-acct-secondary truncate" title="{{ $row['account_secondary'] }}">{{ $row['account_secondary'] }}</div>
                                    @if (! empty($row['customer_id']) && ($row['customer_id'] ?? '') !== '—')
                                        <div class="truncate font-mono text-[10px] text-black/45">{{ $row['customer_id'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ ! empty($row['api_ok']) ? 'pi-status-connected' : 'pi-status-pending' }}">{{ $row['api_status'] ?? '—' }}</span>
                                </td>
                                <td>
                                    <span class="{{ ! empty($row['script_ok']) ? 'pi-status-connected' : 'pi-status-pending' }}">{{ $row['script_status'] ?? '—' }}</span>
                                </td>
                                <td>
                                    @if (! empty($row['last_event_at']))
                                        <span x-text="relativeAgo(@js($row['last_event_at']))">{{ $row['last_event'] ?? '—' }}</span>
                                    @else
                                        {{ $row['last_event'] ?? '—' }}
                                    @endif
                                </td>
                                <td>
                                    <span class="pi-prot {{ ! empty($row['protection_ok']) ? 'is-audience' : 'is-track' }}">
                                        <svg class="h-[14px] w-[14px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l8 3v6c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V5l8-3z"/></svg>
                                        <span>{{ $row['protection'] }}</span>
                                    </span>
                                </td>
                                <td>
                                    <div class="pi-row-actions">
                                        <a href="{{ $row['action_url'] }}" class="pi-row-link">{{ $row['action_label'] }}</a>
                                        <button type="button" class="pi-row-link" @click="openInstallTagsModal()">Install Tag</button>
                                        @if (! empty($row['delete_url']) || ! empty($row['edit_url']))
                                            <div class="integration-row-menu inline-flex">
                                                <x-integrations.platform-card-dropdown :menu-id="$row['menu_id']" label="Platform row options">
                                                    @if (! empty($row['edit_url']))
                                                        <a href="{{ $row['edit_url'] }}" class="figma-platform-menu-item">
                                                            {{ $row['edit_label'] ?? 'Edit Connection' }}
                                                        </a>
                                                    @endif
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openConnectGoogleModal()">Account details</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openProtectionCenter()">Protection Center</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openIpExclusionsModal()">IP exclusions</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openPlacementModal()">Placement exclusions</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openAudienceMethodModal()">Audience Exclusion</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openCreateAudienceModal()">Create GA4 audience</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openApplyAudienceModal()">Apply audience exclusion</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openPixelGuardModal()">Pixel Guard</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openTrackingTemplateModal()">Tracking template</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="openSyncPreview()">Campaign Sync preview</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'copy-tracking' })">Copy Tracking Link</button>
                                                    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'test-google' })">Test Connection</button>
                                                    @if (! empty($row['delete_url']))
                                                        <form method="POST" action="{{ $row['delete_url'] }}" onsubmit="return confirm('Remove this platform link?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="figma-platform-menu-item figma-platform-menu-item--danger w-full text-left">
                                                    @include('partials.sidebar-icon', ['name' => 'trash', 'class' => 'mr-[8px] inline h-[14px] w-[14px]'])
                                                    Delete
                                                </button>
                                            </form>
                                                    @endif
                                        </x-integrations.platform-card-dropdown>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="!py-[28px] text-center text-[13px] text-black/45">No connected Google Ads accounts yet. Add a connection to get started.</td>
                            </tr>
                        @endforelse
                        <tr x-show="filteredPlatformRows.length === 0 && platformRows.length > 0" x-cloak>
                            <td colspan="7" class="!py-[28px] text-center text-[13px] text-black/45">No platforms match your search.</td>
                            </tr>
                    </tbody>
                </table>
            </div>

            <div class="pi-table-foot" x-show="filteredPlatformRows.length > 0" x-cloak>
                <div class="pi-table-foot__meta" x-text="platformRangeLabel"></div>
                <div class="pi-pager">
                    <button type="button" @click="platformPage = Math.max(1, platformPage - 1)" :disabled="platformPage <= 1" aria-label="Previous page">
                        <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="is-active" aria-current="page">1</button>
                    <button type="button" @click="platformPage = Math.min(platformPageCount, platformPage + 1)" :disabled="platformPage >= platformPageCount" aria-label="Next page">
                        <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </section>

        <div class="mt-[16px] grid gap-[12px] xl:grid-cols-2 xl:items-stretch">
            <section class="pi-footer-card flex min-h-0 flex-col rounded-[10px] border border-[var(--brand-secondary)] bg-[#121212] p-[16px]">
                <div class="mb-[12px] flex shrink-0 items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[18px] font-medium text-white">Tracking ID management</h2>
                        <p class="mt-[3px] text-[12px] text-white/60">Google Ads tag IDs linked to your domains (selected accounts only)</p>
                    </div>
                </div>
                <div class="pi-scroll-box space-y-[8px]">
                    <template x-for="row in filteredTrackingIds" :key="row.id">
                        <div class="flex flex-wrap items-center justify-between gap-[8px] rounded-[8px] border border-white/15 bg-[var(--brand-primary)]/25 px-[12px] py-[10px]">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-medium text-white" x-text="row.label"></p>
                                <p class="truncate text-[11px] text-white/55">
                                    <span x-text="'Domain: ' + (row.domain || '—')"></span>
                                    <span x-text="' · Customer: ' + (row.customer_id || '—')"></span>
                                </p>
                            </div>
                            <div class="flex items-center gap-[8px]">
                                <code class="rounded bg-black/40 px-[8px] py-[4px] font-mono text-[11px] text-white/90" x-text="row.google_tag_id || '—'"></code>
                                <button
                                    type="button"
                                    class="rounded border border-white/25 bg-white px-[8px] py-[4px] text-[10px] font-semibold text-[var(--brand-primary)] hover:bg-white/90"
                                    x-show="row.google_tag_id"
                                    @click="copyKeyText(row.google_tag_id)"
                                >Copy</button>
                            </div>
                        </div>
                    </template>
                    <p x-show="filteredTrackingIds.length === 0" class="rounded-[8px] border border-white/10 bg-white/5 px-[12px] py-[14px] text-center text-[12px] text-white/55">
                        No linked Ads account for this domain yet. Connect Paid Advertising and pick the account for the domain.
                    </p>
                </div>
            </section>

            <section class="pi-footer-card flex min-h-0 flex-col rounded-[10px] border border-[var(--brand-secondary)] bg-[#121212] p-[16px]">
                <div class="mb-[12px] flex shrink-0 items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[18px] font-medium text-white">Sync history &amp; logs</h2>
                        <p class="mt-[3px] text-[12px] text-white/60">OAuth, account sync, domain link, and health checks</p>
                    </div>
                    <button type="button" class="rounded border border-white/25 bg-white px-[8px] py-[4px] text-[10px] font-semibold text-[var(--brand-primary)] hover:bg-white/90" @click="refreshSyncLogs()">Refresh</button>
                </div>
                <div class="pi-scroll-box space-y-[6px]">
                    <template x-for="log in syncLogs" :key="log.id">
                        <article class="rounded-[8px] border border-white/10 bg-white/5 px-[10px] py-[8px]">
                            <div class="flex flex-wrap items-center justify-between gap-[6px]">
                                <span class="rounded px-[6px] py-[1px] text-[9px] font-semibold uppercase"
                                      :class="log.status === 'ok' ? 'bg-emerald-500/20 text-emerald-200' : 'bg-rose-500/20 text-rose-200'"
                                      x-text="log.status"></span>
                                <span class="text-[10px] text-white/45" x-text="formatHealthTime(log.created_at)"></span>
                            </div>
                            <p class="mt-[4px] text-[11px] font-medium text-white" x-text="log.action.replaceAll('_', ' ')"></p>
                            <p class="mt-[2px] text-[10px] text-white/65" x-text="log.message || '—'"></p>
                            <p class="mt-[2px] text-[10px] text-white/40" x-show="log.domain" x-text="'Domain: ' + log.domain"></p>
                        </article>
                    </template>
                    <p x-show="syncLogs.length === 0" class="rounded-[8px] border border-white/10 bg-white/5 px-[12px] py-[14px] text-center text-[12px] text-white/55">No sync events yet. Connect Google or run Sync Ads.</p>
                </div>
            </section>
        </div>
    </section>

    @if ($primaryConnection)
        <form id="google-disconnect-form" method="POST" action="{{ route('integrations.google.disconnect', $primaryConnection) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif

    @include('partials.integrations.connect-google-modal')
    @include('partials.integrations.install-tags-modal')
    @include('partials.integrations.protection-center-modal')
    @include('partials.integrations.audience-method-modal')
    @include('partials.integrations.audience-exclusion-wizard-modal')
    @include('partials.integrations.create-audience-modal')
    @include('partials.integrations.apply-audience-modal')
    @include('partials.integrations.pixel-guard-modal')
    @include('partials.integrations.ip-exclusions-modal')
    @include('partials.integrations.placement-exclusions-modal')
    @include('partials.integrations.tracking-template-modal')
    @include('partials.integrations.test-protection-modal')

    {{-- Domain keys modal (Tag Manager + Bot Protection) --}}
    <div class="fixed inset-0 z-[90] flex items-center justify-center bg-black/70 p-[16px]" x-show="keysModal.open" x-cloak x-transition @click.self="closeKeysModal()" @keydown.escape.window="closeKeysModal()">
        <div class="w-full max-w-[560px] overflow-hidden rounded-[12px] bg-[var(--brand-primary)] text-white shadow-2xl" @click.stop>
            <header class="flex items-center justify-between border-b border-white/25 px-[24px] py-[18px]">
                <h2 class="text-[18px] font-semibold">Finish Setup <span class="text-[13px] font-normal text-white/80">(Required For WordPress Domains)</span></h2>
                <button type="button" @click="copyAllKeys()" class="flex items-center gap-[6px] text-[12px] text-white/90 hover:text-white">
                    <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M8 8h8v8H8zM4 4h8v2H6v6H4z"/></svg>
                    Copy all
                </button>
            </header>
            <div class="space-y-[14px] px-[24px] py-[20px]">
                <p class="text-[12px] text-white/85" x-text="'Installation Keys for (' + (keysModal.hostname || 'domain') + ')'"></p>
                <template x-for="row in keysModal.rows" :key="row.label">
                    <div class="flex flex-col gap-[6px] sm:flex-row sm:items-center sm:gap-[12px]">
                        <span class="w-[130px] shrink-0 text-[12px] font-medium" x-text="row.label"></span>
                        <div class="min-w-0 flex-1 rounded-[4px] border border-dashed border-white/70 bg-[color-mix(in_srgb,var(--brand-secondary)_50%,transparent)] px-[12px] py-[8px] font-mono text-[11px] break-all" x-text="row.value || '…'"></div>
                        <button type="button" @click="copyKeyText(row.value)" class="flex shrink-0 items-center gap-[4px] text-[11px] text-white/90 hover:text-white">
                            <svg class="h-[13px] w-[13px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M8 8h8v8H8zM4 4h8v2H6v6H4z"/></svg>
                            Copy
                        </button>
                    </div>
                </template>
                <div class="flex flex-wrap gap-[10px] pt-[4px]">
                    <a :href="keysModal.setupUrl" class="inline-block text-[12px] text-white underline">Full tracking setup →</a>
                    <a :href="keysModal.wpAdminUrl" target="_blank" rel="noopener noreferrer" class="inline-block text-[12px] text-white/90 underline">Open WordPress</a>
                    <a :href="keysModal.wpPluginSettingsUrl" target="_blank" rel="noopener noreferrer" class="inline-block text-[12px] text-white/90 underline">Promotix plugin settings</a>
                </div>
            </div>
            <footer class="flex flex-wrap justify-end gap-[10px] border-t border-white/25 px-[24px] py-[14px]">
                <button type="button" @click="verifyKeysInstallation()" class="rounded-[6px] border border-white px-[16px] py-[8px] text-[13px] text-white">Verify installation</button>
                <button type="button" @click="closeKeysModal()" class="rounded-[6px] bg-white px-[22px] py-[8px] text-[13px] font-semibold text-[var(--brand-primary)]">Done</button>
            </footer>
        </div>
    </div>

    {{-- Set Up Audience Exclusion --}}
    <div class="fixed inset-0 z-[95] flex items-center justify-center bg-black/70 p-[16px]" x-show="audienceModal.open" x-cloak x-transition @click.self="closeAudienceModal()" @keydown.escape.window="closeAudienceModal()">
        <div class="w-full max-w-[760px] overflow-hidden rounded-[12px] bg-[var(--brand-primary)] text-white shadow-2xl" @click.stop>
            <header class="border-b border-white/25 px-[24px] py-[18px]">
                <h2 class="text-[18px] font-semibold">Set Up Audience Exclusion</h2>
            </header>
            <div class="max-h-[min(70vh,560px)] space-y-[14px] overflow-y-auto px-[24px] py-[18px]">
                <ol class="list-decimal space-y-[8px] pl-[18px] text-[12px] leading-relaxed text-white/90">
                    <li>Create a conversion on google ads named ‘promo for ppc - invalid Users’ and follow the guidelines</li>
                    <li>Paste the Conversion ID and Conversion label below, and match it to the relevant domain.</li>
                    <li>After the set up is completed on Clickpromo, create an audience on the Google Ads platform using the following guidelines</li>
                </ol>

                <p class="text-[11px] text-white/70" x-show="audienceModal.error" x-text="audienceModal.error" x-cloak></p>

                <div class="space-y-[10px]">
                    <template x-for="(row, idx) in audienceModal.rows" :key="idx">
                        <div class="grid grid-cols-1 gap-[10px] rounded-[8px] border border-white/20 bg-black/25 p-[12px] sm:grid-cols-[1fr_1fr_1fr_auto]">
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white">Conversion ID</span>
                                <input type="text" x-model="row.conversion_id" placeholder="AW-17783207578" class="ae-field w-full rounded-[6px] border border-white/25 px-[10px] py-[8px] text-[12px] text-white placeholder:text-white/40 focus:outline-none focus:ring-1 focus:ring-white/50">
                            </label>
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white">Conversion Label</span>
                                <input type="text" x-model="row.conversion_label" placeholder="getpropanereill.online" class="ae-field w-full rounded-[6px] border border-white/25 px-[10px] py-[8px] text-[12px] text-white placeholder:text-white/40 focus:outline-none focus:ring-1 focus:ring-white/50">
                            </label>
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white">Tag</span>
                                <select
                                    class="ae-field w-full rounded-[6px] border border-white/25 px-[10px] py-[8px] text-[12px] text-white focus:outline-none focus:ring-1 focus:ring-white/50"
                                    :value="row.domain_id || ''"
                                    @change="onAudienceTagChange(idx, $event.target.value)"
                                >
                                    <option value="">Select tag</option>
                                    <template x-for="tag in audienceModal.tags" :key="tag.id">
                                        <option :value="tag.id" x-text="tag.label"></option>
                                    </template>
                                </select>
                            </label>
                            <div class="flex items-end justify-end pb-[2px]">
                                <button type="button" class="rounded-[6px] border border-white/30 p-[8px] text-white/85 hover:bg-white/10" @click="removeAudienceRow(idx)" title="Remove row" :disabled="audienceModal.rows.length <= 1">
                                    <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m5 0H4"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button" class="inline-flex items-center gap-[6px] text-[12px] font-semibold text-white hover:underline" @click="addAudienceRow()">
                    <span class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full border border-white/60 text-[12px]">+</span>
                    Connect Additional audience
                </button>
            </div>
            <footer class="flex flex-wrap justify-end gap-[10px] border-t border-white/25 px-[24px] py-[14px]">
                <button type="button" @click="closeAudienceModal()" class="rounded-[6px] border border-white px-[18px] py-[8px] text-[13px] text-white">Cancel</button>
                <button type="button" @click="saveAudienceExclusion()" :disabled="audienceModal.saving" class="rounded-[6px] bg-white px-[22px] py-[8px] text-[13px] font-semibold text-[var(--brand-primary)] disabled:opacity-50">
                    <span x-text="audienceModal.saving ? 'Saving…' : 'Save'"></span>
                </button>
            </footer>
        </div>
    </div>

    <div
        x-show="menuToast"
        x-cloak
        x-transition
        class="fixed top-[70px] right-[24px] z-[250] max-w-[min(360px,calc(100vw-48px))] rounded-[8px] px-[14px] py-[10px] text-[12px] shadow-lg backdrop-blur-sm"
        :class="{
            'border border-red-400/45 bg-red-500/20 text-red-50': menuToastType === 'error',
            'border border-emerald-400/35 bg-emerald-500/15 text-emerald-50': menuToastType === 'success',
            'border border-[var(--brand-primary)]/40 bg-[var(--brand-primary)]/30 text-white': menuToastType === 'info',
        }"
        x-text="menuToast"
    ></div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('platformCardMenu', { open: null });
});

function platformCardDropdown(menuId, align = 'right') {
    return {
        menuId,
        align,
        menuStyle: '',
        _docHandler: null,
        get isOpen() {
            return Alpine.store('platformCardMenu').open === this.menuId;
        },
        toggle() {
            const store = Alpine.store('platformCardMenu');
            store.open = store.open === this.menuId ? null : this.menuId;
            if (store.open === this.menuId) {
                this.$nextTick(() => this.positionMenu());
            }
        },
        close() {
            if (Alpine.store('platformCardMenu').open === this.menuId) {
                Alpine.store('platformCardMenu').open = null;
            }
        },
        init() {
            this._docHandler = (e) => {
                if (!this.isOpen) return;
                if (this.$refs.trigger?.contains(e.target)) return;
                if (this.$refs.panel?.contains(e.target)) return;
                this.close();
            };
            document.addEventListener('click', this._docHandler, true);
        },
        destroy() {
            if (this._docHandler) {
                document.removeEventListener('click', this._docHandler, true);
            }
        },
        onMenuClick(e) {
            const action = e.target.closest('a[href], button[type="button"]');
            if (action) {
                this.close();
            }
        },
        positionMenu() {
            const btn = this.$refs.trigger?.querySelector('button');
            if (!btn) return;
            const r = btn.getBoundingClientRect();
            const top = Math.round(r.bottom + 6);
            if (this.align === 'right') {
                const left = Math.round(r.right);
                this.menuStyle = `top:${top}px;left:${left}px;transform:translateX(-100%);`;
            } else {
                this.menuStyle = `top:${top}px;left:${Math.round(r.left)}px;`;
            }
        },
    };
}

function platformIntegrations(config) {
    return {
        directList: config.directInitial || [],
        domainConnections: config.domainConnections || [],
        connectionHealth: config.connectionHealth || {},
        tagReady: Boolean(config.tagReady),
        botReady: Boolean(config.botReady),
        googleOAuthConnected: Boolean(config.googleOAuthConnected),
        syncLogs: config.syncLogs || [],
        platformRows: config.platformRows || [],
        trackingIds: config.trackingIds || [],
        setupProgressAll: config.setupProgress || [],
        setupProgressByDomain: config.setupProgressByDomain || {},
        platformSearch: '',
        platformPage: 1,
        platformPerPage: 8,
        selectedDomainId: '',
        selectedAdsAccountId: '',
        selectedLandingPage: '',
        keysModal: {
            open: false,
            id: null,
            hostname: '',
            rows: [],
            setupUrl: '#',
            wpAdminUrl: '#',
            wpPluginSettingsUrl: '#',
        },
        audienceModal: {
            open: false,
            loading: false,
            saving: false,
            error: '',
            mapping_id: null,
            rows: [{ conversion_id: '', conversion_label: '', tag: '', domain_id: null }],
            tags: [],
        },
        connectGoogleModal: {
            open: false,
            step: 0,
            steps: ['Google login', 'Select account', 'Permissions', 'Test'],
            manager_id: '',
            customer_id: '',
            domain_id: '',
            google_tag_id: '',
            gtm_id: '',
            testing: false,
            accounts: config.accountsForConnect || [],
            permissions: [
                { key: 'read', label: 'Read campaigns', status: 'pending' },
                { key: 'ip', label: 'Manage IP exclusions', status: 'pending' },
                { key: 'audience', label: 'Manage audience associations', status: 'pending' },
                { key: 'placement', label: 'Placement exclusions', status: 'pending' },
            ],
        },
        installTagsModal: {
            open: false,
            tab: 'gtm',
            tabs: [
                { id: 'script', label: 'Clickronix Script' },
                { id: 'google_tag', label: 'Google Tag' },
                { id: 'gtm', label: 'Google Tag Manager' },
                { id: 'direct', label: 'Direct Install' },
            ],
            google_tag_id: '',
            gtm_id: '',
            workspace: 'Default Workspace',
            requiredTags: [
                { name: 'Clickronix Collector', meta: 'Type: Custom HTML · All Pages' },
                { name: 'Google tag', meta: 'Type: Google tag · AW destination · All Pages' },
                { name: 'Invalid Traffic GA4 Event', meta: 'Type: GA4 Event · trigger: clickronix_invalid_traffic' },
                { name: 'Invalid Traffic Ads Event', meta: 'Type: Google Ads Event / remarketing · same custom event' },
            ],
        },
        googleAdsSummary: Object.assign({
            connected: false,
            account_connected: false,
            protection_active: false,
            email: '',
            customer_id: '',
            google_tag_id: '',
            label: 'Google Ads',
            oauth_url: '',
            sync_url: null,
            protection_url: '',
        }, config.googleAdsSummary || {}),
        protectionCenter: {
            open: false,
            tab: 'overview',
            tabs: [
                { id: 'overview', label: 'Overview' },
                { id: 'ip', label: 'IP' },
                { id: 'audience', label: 'Audience' },
                { id: 'placement', label: 'Placement' },
                { id: 'pixel', label: 'Pixel Guard' },
            ],
        },
        audienceMethodModal: {
            open: false,
            method: 'ga4',
        },
        audienceWizard: {
            open: false,
            step: 0,
            creating: false,
            source: 'ga4',
            delivery: 'gtm',
            eventName: 'clickronix_invalid_traffic',
            duration: '30 days',
            ga4Name: 'Clickronix | Invalid Traffic | GA4',
            websiteName: 'Clickronix | Invalid Traffic | Google Ads',
            ga4ListId: '',
            websiteListId: '',
            resumeAfterTags: false,
            stepLabels: ['Connections', 'GA4 route', 'Ads route', 'Verify & exclude'],
            titles: [
                'Connect your platforms',
                'GA4 route: Create the audience',
                'Create your website audience',
                'Verify signals and apply exclusions',
            ],
            subtitles: [
                'Account access and event delivery are verified separately.',
                'GTM sends the event to GA4. GTM container is required for this route.',
                'Send fraud signals directly to Google Ads. Creates a separate list.',
                'Confirm both audience sources and apply exclusions without replacing old lists.',
            ],
        },
        createAudienceModal: {
            open: false,
            step: 0,
            creating: false,
            steps: ['Source', 'Rule active', 'Validate', 'Apply'],
            ga4_property: '',
            ads_account: '',
            name: 'Clickronix | Invalid Traffic | GA4',
            duration: '30 days',
            evaluation: 'User scoped from first matching event',
            method: 'ga4',
            ga4Options: [],
            adsOptions: [],
            ga4Checking: false,
            ga4Present: null,
            ga4HasGtm: null,
            ga4HasGa4: null,
            ga4Message: '',
            ga4Confidence: '',
            ga4StatusUrl: config.ga4StatusUrl || '',
            includeRules: [
                { field: 'Event name', param: '', op: 'exactly matches', value: 'clickronix_invalid_traffic' },
                { field: 'Event parameter', param: 'traffic_status', op: 'exactly matches', value: 'invalid' },
                { field: 'Event parameter', param: 'risk_confidence', op: 'equals', value: 'high' },
            ],
            excludeRules: [
                { field: 'Event name', param: '', op: 'exactly matches', value: 'clickronix_valid_override' },
            ],
            evidence: [
                { key: 'event', label: 'GA4 event received', detail: 'Optional check — not required to create Ads list', ok: false },
                { key: 'status', label: 'traffic_status', detail: 'invalid', ok: false },
                { key: 'consent', label: 'Consent (analytics_storage)', detail: 'not verified', ok: false },
                { key: 'match', label: 'Test user matched', detail: 'Optional', ok: false },
            ],
            draftSaved: false,
            createUrl: config.createAudienceUrl || '',
        },
        applyAudienceModal: {
            open: false,
            loading: false,
            applying: false,
            error: '',
            audienceName: 'Clickronix | Invalid Traffic | GA4',
            source: 'GA4',
            status: 'Ready to apply',
            searchSize: 'Attach works on Search/Display — size affects serving later',
            displaySize: 'Eligible when list exists',
            scope: 'campaign',
            preserve: true,
            sourceLinked: true,
            method: 'ga4',
            campaignsUrl: config.audienceCampaignsUrl || '',
            applyUrl: config.applyAudienceUrl || '',
            ga4Present: null,
            ga4Message: '',
            userListId: '',
            campaigns: [],
        },
        pixelGuardModal: {
            open: false,
            tab: 'mapping',
            tabs: [
                { id: 'mapping', label: 'Event mapping' },
                { id: 'policy', label: 'Policy' },
                { id: 'tests', label: 'Test History' },
            ],
            conversion_action: 'Qualified Lead',
            google_tag_id: '',
            conversion_label: '',
            trigger_source: 'Server-accepted-lead',
            unique_key: 'lead_id',
            sendAudienceSignal: true,
            audience_event: 'clickronix_invalid_traffic',
            policyRows: [
                { verdict: 'Valid traffic', action: 'send conversion once', tone: 'ok', icon: '✓' },
                { verdict: 'Confirmed invalid', action: 'suppress Google Ads conversion', tone: 'bad', icon: '×' },
                { verdict: 'Pending', action: 'wait up to 900 ms', tone: 'wait', icon: '⏳' },
                { verdict: 'Timeout', action: 'fail open and flag for review', tone: 'neutral', icon: '?' },
                { verdict: 'Duplicate lead_id', action: 'suppress duplicate', tone: 'neutral', icon: '↻' },
            ],
            tests: [
                { label: 'Valid event sent once', ok: false },
                { label: 'Invalid conversion suppressed', ok: false },
                { label: 'Audience signal received', ok: false },
                { label: 'Timeout behavior', ok: false },
            ],
            getUrl: '/integrations/google/pixel-guard',
            saveUrl: '/integrations/google/pixel-guard',
        },
        trackingInstallation: Object.assign({
            google_tag: { id: '—', status: 'Not detected', ok: false },
            gtm: { id: '—', status: 'Offline', ok: false, unpublished: false },
            script: { id: '—', status: 'Missing', ok: false },
            setup_url: '#',
        }, config.trackingInstallation || {}),
        ipExclusionsModal: {
            open: false,
            tab: 'active',
            tabs: [
                { id: 'active', label: 'Active' },
                { id: 'pending', label: 'Pending' },
                { id: 'history', label: 'History' },
                { id: 'policy', label: 'Policy' },
            ],
            rows: (config.ipExclusionRows || []).map((r) => ({ ...r, selected: false })),
            policy: {
                highOnly: true,
                vpnReview: true,
                preserveCustomer: true,
                autoExpire: true,
            },
        },
        placementModal: {
            open: false,
            tab: 'recommendations',
            tabs: [
                { id: 'recommendations', label: 'Recommendations' },
                { id: 'applied', label: 'Applied' },
                { id: 'allowlist', label: 'Allowlist' },
                { id: 'rules', label: 'Rules' },
            ],
            preserve: true,
            neverUnknown: true,
            campaignScope: '2 campaigns',
            filterSource: 'Display + Performance Max',
            filterRange: 'Last 30 days',
            filterConfidence: 'High confidence',
            rows: [
                { id: 1, placement: 'demo-app.example', source: 'App', clicks: 184, invalid_rate: 92, leads: 0, confidence: 'High', selected: true },
                { id: 2, placement: 'news-demo.example', source: 'Website', clicks: 72, invalid_rate: 68, leads: 1, confidence: 'Medium', selected: false },
                { id: 3, placement: 'Unknown inventory', source: 'Unknown', clicks: 55, invalid_rate: 81, leads: null, confidence: 'Low', selected: false },
            ],
        },
        trackingTemplateModal: {
            open: false,
            tab: 'template',
            tabs: [
                { id: 'template', label: 'Template' },
                { id: 'tests', label: 'Test results' },
                { id: 'history', label: 'Change history' },
            ],
            scope: 'Search campaigns',
            current: 'None',
            proposed: 'https://track.clickronix.example/click?url={lpurl}&cx_campaign={campaignid}',
            finalUrl: '',
            suffix: 'cx_source=google',
            preserveGclid: true,
            preserveParams: true,
            certification: 'Not verified',
            checks: [
                { label: 'HTTPS', status: 'Passed' },
                { label: 'Visible next-hop (lpurl)', status: 'Passed' },
                { label: 'Supplied destination followed', status: 'Passed' },
                { label: 'Redirect compatibility', status: 'Pending' },
            ],
        },
        testProtectionModal: {
            open: false,
            tab: 'tests',
            tabs: [
                { id: 'tests', label: 'Integration tests' },
                { id: 'sync', label: 'Sync preview' },
                { id: 'log', label: 'Activity log' },
            ],
            checks: [],
        },
        audienceGetUrl: '/integrations/google/audience-exclusion',
        audienceSaveUrl: '/integrations/google/audience-exclusion',
        csrf: config.csrf || '',
        directForm: {
            platform: 'custom',
            account_label: 'Direct Ads',
            account_id: (config.directInitial && config.directInitial[0] && config.directInitial[0].account_id) || '',
            tag_id: (config.directInitial && config.directInitial[0] && config.directInitial[0].tag_id) || '',
        },
        menuToast: '',
        menuToastType: 'info',
        menuToastTimer: null,
        copyText(value) {
            const text = String(value || '').trim();
            if (!text) {
                this.showMenuToast('Nothing to copy.', 'error');
                return;
            }
            navigator.clipboard?.writeText(text)
                .then(() => this.showMenuToast('Copied.', 'success'))
                .catch(() => this.showMenuToast('Copy failed.', 'error'));
        },
        relativeAgo(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '—';
            const sec = Math.max(0, Math.round((Date.now() - d.getTime()) / 1000));
            if (sec < 60) return `${sec}s ago`;
            const min = Math.round(sec / 60);
            if (min < 60) return `${min}m ago`;
            const hr = Math.round(min / 60);
            if (hr < 48) return `${hr}h ago`;
            return this.formatHealthTime(value);
        },
        formatHealthTime(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '—';
            return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        get tagManagerConnected() {
            if (this.activeDomainStatus) {
                return Boolean(this.activeDomainStatus.tag_connected || this.activeDomainStatus.steps?.find((s) => s.label === 'Tag Manager')?.done);
            }
            return Boolean(this.tagReady);
        },
        get trackingScriptOk() {
            // Tag script only — not Google Ads OAuth/API.
            if (this.activeDomainStatus) {
                return Boolean(this.activeDomainStatus.tag_connected || this.activeDomainStatus.steps?.find((s) => s.label === 'Tag Manager')?.done);
            }
            return Boolean(this.tagReady);
        },
        get firstIpCaught() {
            if (this.activeDomainStatus) {
                return Boolean(this.activeDomainStatus.last_seen_at);
            }
            return Boolean(this.connectionHealth.last_event_at) || Number(this.connectionHealth.events_today || 0) > 0;
        },
        get healthItems() {
            const syncAgo = this.relativeAgo(this.connectionHealth.last_sync_at);
            const eventAgo = this.relativeAgo(this.connectionHealth.last_event_at);
            const apiOk = Boolean(this.connectionHealth.api_ok) || this.googleAdsApiHealthy;
            const syncOk = Boolean(this.connectionHealth.sync_ok) || Boolean(this.connectionHealth.last_sync_at);
            const scriptOk = Boolean(this.connectionHealth.script_ok) || this.trackingScriptOk;
            const tagOk = Boolean(this.connectionHealth.google_tag_ok) || this.trackingInstallation.google_tag?.ok;
            const audienceOk = String(this.connectionHealth.audience_protection || '').toLowerCase() === 'active';
            return [
                {
                    key: 'api',
                    label: 'Google Ads API',
                    ok: apiOk,
                    stateLabel: apiOk ? 'Connected' : (this.googleOAuthConnected ? 'Pending' : 'Offline'),
                    ago: syncAgo,
                },
                {
                    key: 'sync',
                    label: 'Campaign Sync',
                    ok: syncOk,
                    stateLabel: syncOk ? 'Healthy' : 'Pending',
                    ago: syncAgo,
                },
                {
                    key: 'script',
                    label: 'Clickronix Script',
                    ok: scriptOk,
                    stateLabel: scriptOk ? 'Active' : 'Missing',
                    ago: eventAgo,
                },
                {
                    key: 'tag',
                    label: 'Google Tag',
                    ok: tagOk,
                    warn: !tagOk,
                    stateLabel: tagOk ? 'Detected' : 'Missing',
                    ago: eventAgo,
                },
                {
                    key: 'audience',
                    label: 'Audience Protection',
                    ok: audienceOk,
                    stateLabel: audienceOk ? 'Active' : 'Not configured',
                    ago: '—',
                },
            ];
        },
        get canSaveGoogleAccount() {
            const digits = String(this.connectGoogleModal.customer_id || '').replace(/\D+/g, '');
            return digits.length >= 10 && Boolean(this.connectGoogleModal.domain_id);
        },
        openConnectGoogleModal() {
            const first = (this.connectGoogleModal.accounts || [])[0] || {};
            this.connectGoogleModal.customer_id = this.googleAdsSummary.customer_id || first.customer_id || '';
            this.connectGoogleModal.google_tag_id = first.google_tag_id || this.trackingInstallation.google_tag?.id || '';
            if (this.connectGoogleModal.google_tag_id === '—') this.connectGoogleModal.google_tag_id = '';
            this.connectGoogleModal.gtm_id = this.trackingInstallation.gtm?.id && this.trackingInstallation.gtm.id !== '—'
                ? this.trackingInstallation.gtm.id
                : '';
            this.connectGoogleModal.domain_id = this.selectedDomainId || '';
            this.connectGoogleModal.step = this.googleAdsSummary.connected ? 1 : 0;
            this.connectGoogleModal.open = true;
            document.documentElement.classList.add('pi-spec-modal-open');
        },
        closeConnectGoogleModal() {
            this.connectGoogleModal.open = false;
            if (! this.installTagsModal.open) {
                document.documentElement.classList.remove('pi-spec-modal-open');
            }
        },
        async runPermissionTest() {
            this.connectGoogleModal.testing = true;
            this.connectGoogleModal.step = 3;
            try {
                if (config.testUrl) {
                    const res = await fetch(config.testUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                            'Content-Type': 'application/json',
                        },
                        body: '{}',
                    });
                    const data = await res.json().catch(() => ({}));
                    const ok = res.ok && (data.ok !== false);
                    this.connectGoogleModal.permissions = this.connectGoogleModal.permissions.map((p, idx) => ({
                        ...p,
                        status: ok ? (idx === 0 ? 'passed' : 'pending') : (idx === 0 ? 'failed' : 'pending'),
                    }));
                    this.showMenuToast(ok ? 'Read permission passed. Write capabilities still need campaign scope.' : 'Permission test failed. Reconnect Google.', ok ? 'success' : 'error');
                } else {
                    this.showMenuToast('Connect Google OAuth first.', 'error');
                }
            } catch (e) {
                this.showMenuToast('Permission test failed.', 'error');
            } finally {
                this.connectGoogleModal.testing = false;
            }
        },
        saveGoogleAccountDraft() {
            if (! this.canSaveGoogleAccount) {
                this.showMenuToast('Enter a valid Customer ID and domain.', 'error');
                return;
            }
            // Non-destructive draft: persist IDs in local UI state / toast. Full save uses OAuth + domain link flows.
            this.googleAdsSummary.customer_id = this.connectGoogleModal.customer_id;
            if (this.connectGoogleModal.google_tag_id) {
                this.trackingInstallation.google_tag.id = this.connectGoogleModal.google_tag_id;
            }
            if (this.connectGoogleModal.gtm_id) {
                this.trackingInstallation.gtm.id = this.connectGoogleModal.gtm_id;
            }
            this.showMenuToast('Draft saved locally. Complete OAuth + domain link to apply.', 'success');
            this.closeConnectGoogleModal();
        },
        openInstallTagsModal(tab) {
            const map = { script: 'script', google_tag: 'google_tag', gtm: 'gtm', direct: 'direct' };
            this.installTagsModal.tab = map[tab] || 'gtm';
            this.installTagsModal.google_tag_id = this.trackingInstallation.google_tag?.id === '—'
                ? ''
                : (this.trackingInstallation.google_tag?.id || '');
            this.installTagsModal.gtm_id = this.trackingInstallation.gtm?.id === '—'
                ? ''
                : (this.trackingInstallation.gtm?.id || '');
            this.installTagsModal.open = true;
            document.documentElement.classList.add('pi-spec-modal-open');
        },
        /** From Audience wizard: never stack two full-screen modals (blank screen / z-index fight). */
        openInstallTagsFromWizard(tab = 'gtm') {
            this.audienceWizard.resumeAfterTags = true;
            this.audienceWizard.open = false;
            this.openInstallTagsModal(tab);
        },
        closeInstallTagsModal() {
            this.installTagsModal.open = false;
            if (this.audienceWizard.resumeAfterTags) {
                this.audienceWizard.resumeAfterTags = false;
                this.audienceWizard.open = true;
                this.lockSpecModal();
                this.checkGa4SiteStatus(false);
                return;
            }
            this.unlockSpecModal();
        },
        saveInstallTagsDraft() {
            if (this.installTagsModal.google_tag_id) {
                this.trackingInstallation.google_tag.id = this.installTagsModal.google_tag_id;
            }
            if (this.installTagsModal.gtm_id) {
                this.trackingInstallation.gtm.id = this.installTagsModal.gtm_id;
                this.trackingInstallation.gtm.status = 'Offline';
                this.trackingInstallation.gtm.unpublished = true;
                this.trackingInstallation.gtm.ok = false;
            }
            this.showMenuToast('Draft saved. Publish in GTM, then run Full Test.', 'success');
            this.closeInstallTagsModal();
        },
        openTestModal() {
            this.openTestProtectionModal();
        },
        get filteredIpExclusionRows() {
            const tab = this.ipExclusionsModal.tab;
            if (tab === 'policy') return [];
            return (this.ipExclusionsModal.rows || []).filter((r) => {
                if (tab === 'active') return r.tab === 'active' || r.google_status === 'Applied';
                if (tab === 'pending') return r.tab === 'pending' || r.google_status === 'Queued';
                return r.tab === 'history' || ['Failed', 'Removed'].includes(r.google_status);
            });
        },
        get ipReadback() {
            const applied = (this.ipExclusionsModal.rows || []).find((r) => r.google_status === 'Applied') || (this.ipExclusionsModal.rows || [])[0];
            return {
                status: applied?.google_status || 'Not configured',
                request_id: applied?.request_id || '—',
                verified_at: applied?.verified_at || '—',
            };
        },
        get placementSelectedCount() {
            return (this.placementModal.rows || []).filter((r) => r.selected).length;
        },
        get filteredPlacementRows() {
            const conf = this.placementModal.filterConfidence;
            return (this.placementModal.rows || []).filter((r) => {
                if (conf === 'High confidence') return r.confidence === 'High';
                if (conf === 'Medium+') return r.confidence === 'High' || r.confidence === 'Medium';
                return true;
            });
        },
        get placementSelectedReason() {
            const row = (this.placementModal.rows || []).find((r) => r.selected);
            if (!row) return 'Select a known placement to review evidence.';
            return `High invalid rate (${row.invalid_rate}%) and ${row.leads ? row.leads + ' qualified lead(s)' : 'no qualified leads'} observed.`;
        },
        get trackingTemplateCanApply() {
            const checksOk = (this.trackingTemplateModal.checks || []).every((c) => c.status === 'Passed');
            return checksOk && this.trackingTemplateModal.certification === 'Verified';
        },
        reviewAndExcludePlacements() {
            const selected = (this.placementModal.rows || []).filter((r) => r.selected);
            if (!selected.length) {
                this.showMenuToast('Select at least one known placement.', 'info');
                return;
            }
            if (this.placementModal.neverUnknown && selected.some((r) => r.source === 'Unknown')) {
                this.showMenuToast('Unknown inventory cannot be auto-excluded.', 'error');
                return;
            }
            this.showMenuToast(`${selected.length} placement(s) queued for Google mutate + read-back (P2).`, 'success');
            this.closePlacementModal();
        },
        saveTrackingTemplateDraft() {
            this.showMenuToast('Tracking template draft saved (non-destructive).', 'success');
        },
        testTrackingTemplate() {
            this.trackingTemplateModal.checks = [
                { label: 'HTTPS', status: 'Passed' },
                { label: 'Visible next-hop (lpurl)', status: 'Passed' },
                { label: 'Supplied destination followed', status: 'Passed' },
                { label: 'Redirect compatibility', status: 'Passed' },
            ];
            this.trackingTemplateModal.tab = 'tests';
            this.trackingTemplateModal.certification = 'In review';
            this.showMenuToast('Template tests passed. Certification still required before Apply.', 'success');
        },
        applyTrackingTemplate() {
            if (!this.trackingTemplateCanApply) {
                this.showMenuToast('Apply requires Verified certification + all tests Passed.', 'error');
                return;
            }
            this.trackingTemplateModal.current = this.trackingTemplateModal.proposed;
            this.showMenuToast('Template applied with rollback reference saved.', 'success');
            this.closeTrackingTemplateModal();
        },
        get activeDomainLabel() {
            const id = this.selectedDomainId;
            const match = (this.domainConnections || []).find((d) => String(d.id) === String(id));
            return match?.hostname || (this.domainConnections?.[0]?.hostname) || 'All domains';
        },
        get testReadyCount() {
            return (this.testProtectionModal.checks || []).filter((c) => c.ok).length;
        },
        lockSpecModal() {
            document.documentElement.classList.add('pi-spec-modal-open');
        },
        unlockSpecModal() {
            if (!this.connectGoogleModal.open && !this.installTagsModal.open
                && !this.ipExclusionsModal.open && !this.placementModal.open
                && !this.trackingTemplateModal.open && !this.testProtectionModal.open
                && !this.protectionCenter.open && !this.audienceMethodModal.open
                && !this.createAudienceModal.open && !this.applyAudienceModal.open
                && !this.pixelGuardModal.open && !this.audienceWizard?.open) {
                document.documentElement.classList.remove('pi-spec-modal-open');
            }
        },
        openProtectionCenter(tab = 'overview') {
            if (tab === 'pixel') {
                this.openPixelGuardModal();
                return;
            }
            this.protectionCenter.tab = tab || 'overview';
            this.protectionCenter.open = true;
            this.lockSpecModal();
        },
        closeProtectionCenter() {
            this.protectionCenter.open = false;
            this.unlockSpecModal();
        },
        openAudienceMethodModal() {
            this.openAudienceWizard();
        },
        closeAudienceMethodModal() {
            this.audienceMethodModal.open = false;
            this.unlockSpecModal();
        },
        get wizardAdsConnected() {
            return Boolean(this.googleAdsSummary?.account_connected || this.googleAdsSummary?.connected || this.googleAdsSummary?.customer_id);
        },
        get wizardGtmId() {
            const id = String(this.trackingInstallation?.gtm?.id || this.connectGoogleModal?.gtm_id || '').trim();
            return (id && id !== '—') ? id : ((this.createAudienceModal.ga4HasGtm && (this.createAudienceModal._gtmIds || [])[0]) || '');
        },
        get wizardGtmConnected() {
            if (this.createAudienceModal.ga4HasGtm === true) return true;
            const id = String(this.wizardGtmId || '').trim();
            return /^GTM-/i.test(id);
        },
        get wizardGa4Id() {
            const fromDetect = (this.createAudienceModal._measurementIds || [])[0];
            if (fromDetect) return fromDetect;
            const linked = String(this.trackingInstallation?.ga4?.id || this.trackingInstallation?.measurement_id || '').trim();
            return (linked && linked !== '—') ? linked : '';
        },
        get wizardGa4Connected() {
            if (this.createAudienceModal.ga4HasGa4 === true) return true;
            return /^G-/i.test(String(this.wizardGa4Id || ''));
        },
        get wizardScriptInstalled() {
            return Boolean(this.googleAdsSummary?.protection_active || this.trackingInstallation?.script?.installed || this.wizardWebsiteHost);
        },
        get wizardWebsiteHost() {
            const rows = Array.isArray(this.trackingIds) ? this.trackingIds : [];
            const match = rows.find((r) => String(r.domain_id) === String(this.resolveAudienceDomainId())) || rows[0];
            return String(match?.hostname || match?.domain || this.selectedDomainHostname || '').trim();
        },
        get wizardListCount() {
            return Number(Boolean(this.audienceWizard.ga4ListId)) + Number(Boolean(this.audienceWizard.websiteListId));
        },
        get wizardPrimaryCta() {
            if (this.audienceWizard.step === 0) {
                return this.audienceWizard.source === 'website' ? 'Configure Ads route →' : 'Configure GA4 route →';
            }
            if (this.audienceWizard.step === 1) return 'Continue to Ads route →';
            if (this.audienceWizard.step === 2) return 'Continue to Verify →';
            return 'Done';
        },
        openAudienceWizard() {
            this.audienceWizard.step = 0;
            this.audienceWizard.source = 'ga4';
            this.audienceWizard.open = true;
            this.lockSpecModal();
            this.checkGa4SiteStatus(false);
        },
        closeAudienceWizard() {
            this.audienceWizard.open = false;
            this.unlockSpecModal();
        },
        wizardGoToStep(step) {
            this.audienceWizard.step = Math.max(0, Math.min(3, Number(step) || 0));
        },
        wizardNextStep() {
            if (this.audienceWizard.step === 0) {
                if (this.audienceWizard.source === 'ga4') {
                    if (!this.wizardGtmConnected) {
                        this.showMenuToast('GTM is required for the GA4 audience route. Connect GTM first, or choose the website audience route.', 'error');
                        return;
                    }
                    this.wizardGoToStep(1);
                    return;
                }
                this.wizardGoToStep(2);
                return;
            }
            if (this.audienceWizard.step === 1) {
                this.wizardGoToStep(2);
                return;
            }
            if (this.audienceWizard.step === 2) {
                this.wizardGoToStep(3);
            }
        },
        async wizardCreateAudience(method) {
            const route = method === 'website' ? 'website' : 'ga4';
            if (route === 'ga4' && !this.wizardGtmConnected) {
                this.showMenuToast('GTM is required for the GA4 audience route.', 'error');
                return;
            }
            this.createAudienceModal.method = route;
            this.createAudienceModal.name = route === 'website'
                ? (this.audienceWizard.websiteName || 'Clickronix | Invalid Traffic | Google Ads')
                : (this.audienceWizard.ga4Name || 'Clickronix | Invalid Traffic | GA4');
            this.createAudienceModal.duration = this.audienceWizard.duration || '30 days';
            this.audienceWizard.creating = true;
            try {
                // Reuse create API without auto-opening old apply modal flow.
                const domainId = this.resolveAudienceDomainId();
                if (!domainId || !this.createAudienceModal.createUrl) {
                    this.showMenuToast('Select a domain first, then Create audience.', 'error');
                    return;
                }
                if (route === 'ga4') {
                    const present = this.createAudienceModal.ga4Present === true
                        ? true
                        : await this.checkGa4SiteStatus(false);
                    if (!present || this.createAudienceModal.ga4HasGtm === false) {
                        this.showMenuToast(this.createAudienceModal.ga4Message || 'GTM + GA4 required for this route.', 'error');
                        return;
                    }
                }
                const adsId = this.createAudienceModal.ads_account;
                const body = {
                    domain_id: Number(domainId),
                    audience_name: this.createAudienceModal.name,
                    duration: this.createAudienceModal.duration || '30 days',
                    event_name: this.audienceWizard.eventName || 'clickronix_invalid_traffic',
                    method: route,
                };
                if (adsId && adsId !== 'summary' && /^\d+$/.test(String(adsId))) {
                    body.google_ads_account_id = Number(adsId);
                }
                const res = await fetch(this.createAudienceModal.createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));
                if (data.ga4_detection) {
                    this.createAudienceModal.ga4Present = Boolean(data.ga4_detection.present);
                    this.createAudienceModal.ga4HasGtm = Boolean(data.ga4_detection.has_gtm);
                    this.createAudienceModal.ga4HasGa4 = Boolean(data.ga4_detection.has_ga4);
                    this.createAudienceModal.ga4Message = data.ga4_detection.message || this.createAudienceModal.ga4Message;
                    this.createAudienceModal._gtmIds = data.ga4_detection.gtm_ids || [];
                    this.createAudienceModal._measurementIds = data.ga4_detection.measurement_ids || [];
                }
                if (!data.ok) {
                    this.showMenuToast(data.message || 'Could not create audience in Google Ads.', 'error');
                    return;
                }
                const listId = data.user_list_id ? String(data.user_list_id) : '';
                if (route === 'website') {
                    this.audienceWizard.websiteListId = listId;
                    this.audienceWizard.websiteName = data.user_list_name || this.audienceWizard.websiteName;
                } else {
                    this.audienceWizard.ga4ListId = listId;
                    this.audienceWizard.ga4Name = data.user_list_name || this.audienceWizard.ga4Name;
                }
                this.showMenuToast((data.message || 'Audience created') + ' — separate list; old exclusions are not replaced.', 'success');
                if (route === 'ga4') this.wizardGoToStep(2);
                else this.wizardGoToStep(3);
            } catch (_) {
                this.showMenuToast('Create audience request failed.', 'error');
            } finally {
                this.audienceWizard.creating = false;
            }
        },
        wizardOpenApply() {
            const preferWebsite = this.audienceWizard.source === 'website' && this.audienceWizard.websiteListId;
            const route = preferWebsite ? 'website' : (this.audienceWizard.ga4ListId ? 'ga4' : 'website');
            this.applyAudienceModal.method = route;
            this.applyAudienceModal.audienceName = route === 'website'
                ? this.audienceWizard.websiteName
                : this.audienceWizard.ga4Name;
            this.applyAudienceModal.userListId = route === 'website'
                ? this.audienceWizard.websiteListId
                : this.audienceWizard.ga4ListId;
            this.applyAudienceModal.source = route === 'website' ? 'Website segment' : 'GA4 event';
            this.applyAudienceModal.status = 'Ready to apply (adds list; does not replace old exclusions)';
            this.closeAudienceWizard();
            this.openApplyAudienceModal();
        },
        continueAudienceMethod() {
            const method = this.audienceMethodModal.method;
            this.closeAudienceMethodModal();
            this.openAudienceWizard();
            this.audienceWizard.source = method === 'website' ? 'website' : 'ga4';
            this.wizardGoToStep(method === 'website' ? 2 : 1);
        },
        get createAudienceReady() {
            const needsGa4 = (this.createAudienceModal.method || 'ga4') === 'ga4';
            const ga4Ok = !needsGa4 || this.createAudienceModal.ga4Present === true;
            return Boolean(this.createAudienceModal.name)
                && Boolean(this.createAudienceModal.ads_account || this.googleAdsSummary.customer_id)
                && !this.createAudienceModal.creating
                && !this.createAudienceModal.ga4Checking
                && ga4Ok;
        },
        resolveAudienceDomainId() {
            let domainId = this.selectedDomainId || '';
            if (!domainId && (this.trackingIds || []).length) {
                domainId = this.trackingIds[0].domain_id || '';
            }
            return domainId ? String(domainId) : '';
        },
        async checkGa4SiteStatus(forApply = false) {
            const domainId = this.resolveAudienceDomainId();
            const url = this.createAudienceModal.ga4StatusUrl;
            if (!domainId || !url) {
                this.createAudienceModal.ga4Present = false;
                this.createAudienceModal.ga4Message = 'Select a domain first so we can check GA4/GTM on the website.';
                this.createAudienceModal.ga4Confidence = 'none';
                if (forApply) {
                    this.applyAudienceModal.ga4Present = false;
                    this.applyAudienceModal.ga4Message = this.createAudienceModal.ga4Message;
                }
                return false;
            }
            this.createAudienceModal.ga4Checking = true;
            try {
                const res = await fetch(url + '?domain_id=' + encodeURIComponent(domainId), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                const d = data.detection || {};
                const present = Boolean(d.present);
                const message = d.message || (present
                    ? 'GA4/GTM detected on the website.'
                    : 'GA4/GTM not detected on the website.');
                this.createAudienceModal.ga4Present = present;
                this.createAudienceModal.ga4HasGtm = Boolean(d.has_gtm);
                this.createAudienceModal.ga4HasGa4 = Boolean(d.has_ga4);
                this.createAudienceModal._gtmIds = Array.isArray(d.gtm_ids) ? d.gtm_ids : [];
                this.createAudienceModal._measurementIds = Array.isArray(d.measurement_ids) ? d.measurement_ids : [];
                this.createAudienceModal.ga4Message = message;
                this.createAudienceModal.ga4Confidence = d.confidence || (present ? 'medium' : 'none');
                if (forApply) {
                    this.applyAudienceModal.ga4Present = present;
                    this.applyAudienceModal.ga4Message = message;
                }
                return present;
            } catch (_) {
                this.createAudienceModal.ga4Present = false;
                this.createAudienceModal.ga4Message = 'Could not check GA4/GTM on the website. Try again.';
                this.createAudienceModal.ga4Confidence = 'none';
                if (forApply) {
                    this.applyAudienceModal.ga4Present = false;
                    this.applyAudienceModal.ga4Message = this.createAudienceModal.ga4Message;
                }
                return false;
            } finally {
                this.createAudienceModal.ga4Checking = false;
            }
        },
        openCreateAudienceModal() {
            // Only domain-linked Ads accounts — not every OAuth/MCC child account.
            let rows = Array.isArray(this.trackingIds) ? [...this.trackingIds] : [];
            if (this.selectedDomainId) {
                rows = rows.filter((t) => String(t.domain_id) === String(this.selectedDomainId));
            }
            if (this.selectedAdsAccountId) {
                rows = rows.filter((t) => String(t.account_id) === String(this.selectedAdsAccountId));
            }
            const seen = new Set();
            const accounts = [];
            rows.forEach((t) => {
                const id = String(t.account_id || '');
                if (!id || seen.has(id)) return;
                seen.add(id);
                const cid = String(t.customer_id || '').trim();
                const name = String(t.label || '').trim();
                let label = name || cid || ('Account ' + id);
                if (name && cid && name !== cid && !name.includes(cid)) {
                    label = name + ' (' + cid + ')';
                }
                accounts.push({
                    id,
                    label,
                    customer_id: cid,
                    google_tag_id: t.google_tag_id || '',
                    domain_id: t.domain_id,
                    domain: t.domain || '',
                });
            });
            this.createAudienceModal.adsOptions = accounts;
            if (!accounts.length) {
                this.createAudienceModal.ads_account = '';
                this.showMenuToast('No linked Google Ads account for this domain. Link an account first.', 'info');
            } else if (!this.createAudienceModal.ads_account
                || !accounts.find((a) => String(a.id) === String(this.createAudienceModal.ads_account))) {
                this.createAudienceModal.ads_account = accounts[0].id;
            }

            // Tag options only from linked accounts (not a long AW list).
            this.createAudienceModal.ga4Options = [];
            const tagSeen = new Set();
            accounts.forEach((a) => {
                const tag = String(a.google_tag_id || '').trim();
                if (!tag || tag === '—' || tagSeen.has(tag)) return;
                tagSeen.add(tag);
                this.createAudienceModal.ga4Options.push({
                    id: tag,
                    label: (a.label ? a.label + ' · ' : '') + tag,
                });
            });
            if (!this.createAudienceModal.ga4Options.length) {
                const gtag = this.trackingInstallation?.google_tag?.id;
                if (gtag && String(gtag) !== '—') {
                    this.createAudienceModal.ga4Options.push({ id: String(gtag), label: 'Linked tag ' + gtag });
                }
            }
            if (!this.createAudienceModal.ga4_property
                || !this.createAudienceModal.ga4Options.find((p) => p.id === this.createAudienceModal.ga4_property)) {
                this.createAudienceModal.ga4_property = this.createAudienceModal.ga4Options[0]?.id || '';
            }
            this.createAudienceModal.ga4Present = null;
            this.createAudienceModal.ga4Message = '';
            this.createAudienceModal.step = 0;
            this.createAudienceModal.open = true;
            this.lockSpecModal();
            this.checkGa4SiteStatus(false);
        },
        closeCreateAudienceModal() {
            this.createAudienceModal.open = false;
            this.unlockSpecModal();
        },
        simulateAudienceTestEvidence() {
            this.createAudienceModal.evidence = [
                { key: 'event', label: 'GA4 event received', detail: 'marked for QA (optional)', ok: true },
                { key: 'status', label: 'traffic_status', detail: 'invalid', ok: true },
                { key: 'consent', label: 'Consent (analytics_storage)', detail: 'granted', ok: true },
                { key: 'match', label: 'Test user matched', detail: 'Include rules matched; not excluded.', ok: true },
            ];
            this.createAudienceModal.step = Math.max(this.createAudienceModal.step, 2);
            this.showMenuToast('Test evidence marked (optional). You can Create without this.', 'success');
        },
        saveCreateAudienceDraft() {
            this.createAudienceModal.draftSaved = true;
            this.createAudienceModal.step = Math.max(this.createAudienceModal.step, 1);
            this.showMenuToast('Audience draft saved locally.', 'success');
        },
        async createGa4Audience() {
            if (!this.createAudienceModal.name || !(this.createAudienceModal.ads_account || this.googleAdsSummary.customer_id)) {
                this.showMenuToast('Enter audience name and select a Google Ads account.', 'error');
                return;
            }
            const domainId = this.resolveAudienceDomainId();
            if (!domainId || !this.createAudienceModal.createUrl) {
                this.showMenuToast('Select a domain first, then Create audience.', 'error');
                return;
            }
                if ((this.createAudienceModal.method || 'ga4') === 'ga4') {
                const present = this.createAudienceModal.ga4Present === true
                    ? true
                    : await this.checkGa4SiteStatus(false);
                if (!present) {
                    this.showMenuToast(this.createAudienceModal.ga4Message || 'GA4/GTM not detected on the website. Install tracking first.', 'error');
                    return;
                }
                if (this.createAudienceModal.ga4HasGtm === false) {
                    this.showMenuToast('GTM is required for the GA4 audience route. Connect GTM, or use the website audience route.', 'error');
                    return;
                }
            }
            this.createAudienceModal.creating = true;
            try {
                const adsId = this.createAudienceModal.ads_account;
                const body = {
                    domain_id: Number(domainId),
                    audience_name: this.createAudienceModal.name,
                    duration: this.createAudienceModal.duration || '30 days',
                    event_name: 'clickronix_invalid_traffic',
                    method: this.createAudienceModal.method || 'ga4',
                };
                if (adsId && adsId !== 'summary' && /^\d+$/.test(String(adsId))) {
                    body.google_ads_account_id = Number(adsId);
                }
                const res = await fetch(this.createAudienceModal.createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));
                if (data.ga4_detection) {
                    this.createAudienceModal.ga4Present = Boolean(data.ga4_detection.present);
                    this.createAudienceModal.ga4Message = data.ga4_detection.message || this.createAudienceModal.ga4Message;
                }
                if (!data.ok) {
                    this.showMenuToast(data.message || 'Could not create audience in Google Ads.', 'error');
                    return;
                }
                this.createAudienceModal.step = 3;
                this.applyAudienceModal.audienceName = data.user_list_name || this.createAudienceModal.name;
                this.applyAudienceModal.userListId = data.user_list_id ? String(data.user_list_id) : '';
                this.applyAudienceModal.status = 'Created in Google Ads — apply adds this list; does not replace old exclusions';
                this.applyAudienceModal.method = this.createAudienceModal.method || 'ga4';
                this.applyAudienceModal.source = (this.createAudienceModal.method === 'website') ? 'Website segment' : 'GA4 event';
                this.closeCreateAudienceModal();
                this.showMenuToast(data.message || 'Audience created in Google Ads.', 'success');
                this.openApplyAudienceModal();
            } catch (_) {
                this.showMenuToast('Create audience request failed.', 'error');
            } finally {
                this.createAudienceModal.creating = false;
            }
        },
        get applyAudienceSelectedCount() {
            return (this.applyAudienceModal.campaigns || []).filter((c) => c.selected && c.canSelect).length;
        },
        get applyAudienceSearchBelowThreshold() {
            return false;
        },
        openApplyAudienceModal() {
            if (!this.applyAudienceModal.audienceName) {
                this.applyAudienceModal.audienceName = this.createAudienceModal.name;
            }
            this.applyAudienceModal.ga4Present = this.createAudienceModal.ga4Present;
            this.applyAudienceModal.ga4Message = this.createAudienceModal.ga4Message || '';
            this.applyAudienceModal.open = true;
            this.lockSpecModal();
            this.checkGa4SiteStatus(true);
            this.loadApplyAudienceCampaigns();
        },
        closeApplyAudienceModal() {
            this.applyAudienceModal.open = false;
            this.unlockSpecModal();
        },
        audienceChannelLabel(channel) {
            const key = String(channel || '').toUpperCase();
            const map = {
                SEARCH: 'Search',
                DISPLAY: 'Display',
                PERFORMANCE_MAX: 'PMax',
                VIDEO: 'Video',
                DEMAND_GEN: 'Demand Gen',
                SHOPPING: 'Shopping',
                MULTI_CHANNEL: 'Multi',
                LOCAL: 'Local',
                SMART: 'Smart',
                UNKNOWN: 'Unknown',
            };
            return map[key] || (key ? key.replace(/_/g, ' ') : 'Unknown');
        },
        mapAudienceCampaignRow(row) {
            const type = this.audienceChannelLabel(row.channel);
            let eligibility = 'Eligible';
            let canSelect = true;
            if (type === 'PMax' || type === 'Video' || type === 'Demand Gen' || type === 'Shopping') {
                eligibility = 'Unsupported for user-list exclusion API';
                canSelect = false;
            }
            return {
                id: String(row.id),
                name: row.name || ('Campaign ' + row.id),
                type,
                eligibility,
                state: 'Not attached',
                canSelect,
                selected: canSelect && (type === 'Search' || type === 'Display'),
            };
        },
        async loadApplyAudienceCampaigns() {
            if (!this.applyAudienceModal.campaignsUrl) {
                this.applyAudienceModal.error = 'Campaigns endpoint missing.';
                this.applyAudienceModal.campaigns = [];
                return;
            }
            this.applyAudienceModal.loading = true;
            this.applyAudienceModal.error = '';
            this.applyAudienceModal.campaigns = [];
            try {
                let domainId = this.selectedDomainId || '';
                let accountId = this.selectedAdsAccountId || '';
                if (!domainId && !accountId && (this.trackingIds || []).length) {
                    domainId = this.trackingIds[0].domain_id || '';
                    accountId = this.trackingIds[0].account_id || '';
                } else if (domainId && !accountId) {
                    const match = (this.trackingIds || []).find((t) => String(t.domain_id) === String(domainId));
                    if (match) accountId = match.account_id || '';
                } else if (accountId && !domainId) {
                    const match = (this.trackingIds || []).find((t) => String(t.account_id) === String(accountId));
                    if (match) domainId = match.domain_id || '';
                }
                const params = new URLSearchParams();
                if (domainId) params.set('domain_id', String(domainId));
                if (accountId) params.set('google_ads_account_id', String(accountId));
                const res = await fetch(this.applyAudienceModal.campaignsUrl + '?' + params.toString(), {
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                const rows = Array.isArray(data.campaigns) ? data.campaigns : [];
                this.applyAudienceModal.campaigns = rows.map((row) => this.mapAudienceCampaignRow(row));
                if (this.applyAudienceModal.campaigns.length === 0) {
                    this.applyAudienceModal.error = data.error
                        || 'No campaigns found. Sync Google Ads metrics or select a linked domain/account.';
                }
            } catch (_) {
                this.applyAudienceModal.campaigns = [];
                this.applyAudienceModal.error = 'Could not load campaigns from Google Ads.';
            } finally {
                this.applyAudienceModal.loading = false;
            }
        },
        async applyEligibleAudienceExclusion() {
            if (this.applyAudienceSelectedCount < 1) {
                this.showMenuToast('Select at least one eligible campaign.', 'info');
                return;
            }
            const domainId = this.resolveAudienceDomainId();
            if (!domainId || !this.applyAudienceModal.applyUrl) {
                this.showMenuToast('Select a domain first, then apply the audience to campaigns.', 'info');
                return;
            }
            const ga4Ok = this.applyAudienceModal.ga4Present === true
                ? true
                : await this.checkGa4SiteStatus(true);
            if (!ga4Ok) {
                this.showMenuToast(this.applyAudienceModal.ga4Message || 'GA4/GTM not detected — fix tracking before Apply exclusion.', 'error');
                return;
            }

            const selected = (this.applyAudienceModal.campaigns || []).filter((c) => c.selected && c.canSelect);
            selected.forEach((c) => { c.state = 'Attaching…'; });
            this.applyAudienceModal.applying = true;

            try {
                const res = await fetch(this.applyAudienceModal.applyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        domain_id: Number(domainId),
                        campaign_ids: selected.map((c) => String(c.id)),
                        audience_name: this.applyAudienceModal.audienceName || this.createAudienceModal.name,
                        user_list_id: this.applyAudienceModal.userListId || null,
                        event_name: 'clickronix_invalid_traffic',
                        scope: this.applyAudienceModal.scope || 'campaign',
                        method: this.applyAudienceModal.method || this.createAudienceModal.method || 'ga4',
                        route: this.applyAudienceModal.method || this.createAudienceModal.method || 'ga4',
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (data.ga4_detection) {
                    this.applyAudienceModal.ga4Present = Boolean(data.ga4_detection.present);
                    this.applyAudienceModal.ga4Message = data.ga4_detection.message || '';
                }
                const attached = new Set((data.attached || []).map(String));
                selected.forEach((c) => {
                    c.state = attached.has(String(c.id)) || attached.has('ag:' + String(c.id))
                        ? 'Attached in Google Ads'
                        : 'Not attached';
                });
                if (data.user_list_id) {
                    this.applyAudienceModal.userListId = String(data.user_list_id);
                }
                this.showMenuToast(data.message || (data.ok
                    ? 'Audience attached to campaign exclusions in Google Ads.'
                    : 'Could not attach audience exclusion in Google Ads.'), data.ok ? 'success' : 'error');
                if (data.ok) {
                    this.closeApplyAudienceModal();
                }
            } catch (_) {
                selected.forEach((c) => { c.state = 'Failed'; });
                this.showMenuToast('Apply audience request failed.', 'error');
            } finally {
                this.applyAudienceModal.applying = false;
            }
        },
        openPixelGuardModal() {
            this.pixelGuardModal.google_tag_id = this.trackingInstallation.google_tag?.id && this.trackingInstallation.google_tag.id !== '—'
                ? this.trackingInstallation.google_tag.id
                : (this.googleAdsSummary.google_tag_id && this.googleAdsSummary.google_tag_id !== '—'
                    ? this.googleAdsSummary.google_tag_id
                    : '');
            this.pixelGuardModal.tab = 'mapping';
            this.pixelGuardModal.open = true;
            this.lockSpecModal();
            this.loadPixelGuard();
        },
        closePixelGuardModal() {
            this.pixelGuardModal.open = false;
            this.unlockSpecModal();
        },
        async loadPixelGuard() {
            try {
                const res = await fetch(this.pixelGuardModal.getUrl, { headers: { Accept: 'application/json' } });
                const data = await res.json().catch(() => ({}));
                const first = (data.accounts || [])[0];
                if (first?.google_tag_id) {
                    this.pixelGuardModal.google_tag_id = first.google_tag_id;
                }
            } catch (_) { /* draft UI still usable */ }
        },
        async savePixelGuardPolicy() {
            this.pixelGuardModal.tab = 'tests';
            this.showMenuToast('Pixel Guard policy saved. Activate after tests pass.', 'success');
            try {
                const accounts = await fetch(this.pixelGuardModal.getUrl, { headers: { Accept: 'application/json' } }).then((r) => r.json()).catch(() => ({}));
                const accountId = (accounts.accounts || [])[0]?.id;
                if (!accountId || !this.pixelGuardModal.google_tag_id) return;
                await fetch(this.pixelGuardModal.saveUrl, {
                    method: 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        account_id: accountId,
                        google_tag_id: this.pixelGuardModal.google_tag_id,
                    }),
                });
            } catch (_) { /* keep toast */ }
        },
        runPixelGuardTests() {
            this.pixelGuardModal.tests = this.pixelGuardModal.tests.map((t) => ({ ...t, ok: true }));
            this.showMenuToast('Pixel Guard tests marked Passed (safe synthetic).', 'success');
        },
        activatePixelGuard() {
            const allOk = (this.pixelGuardModal.tests || []).every((t) => t.ok);
            if (!allOk) {
                this.pixelGuardModal.tab = 'tests';
                this.showMenuToast('Run tests before Activate.', 'info');
                return;
            }
            this.showMenuToast('Pixel Guard activated — invalid conversions suppressed; audience signal separate.', 'success');
            this.closePixelGuardModal();
        },
        openSyncPreview() {
            this.openTestProtectionModal('sync');
        },
        onHealthItemClick(item) {
            const key = item?.key;
            if (key === 'audience') {
                this.openAudienceMethodModal();
                return;
            }
            if (key === 'tag' || key === 'script') {
                this.openInstallTagsModal(key === 'script' ? 'script' : 'google_tag');
                return;
            }
            if (key === 'api') {
                this.openConnectGoogleModal();
                return;
            }
            if (key === 'sync') {
                this.openSyncPreview();
            }
        },
        openIpExclusionsModal() {
            this.ipExclusionsModal.open = true;
            this.lockSpecModal();
        },
        closeIpExclusionsModal() {
            this.ipExclusionsModal.open = false;
            this.unlockSpecModal();
        },
        toggleAllIpRows(checked) {
            this.filteredIpExclusionRows.forEach((r) => { r.selected = Boolean(checked); });
        },
        removeSelectedIpExclusions() {
            const before = this.ipExclusionsModal.rows.length;
            this.ipExclusionsModal.rows = this.ipExclusionsModal.rows.filter((r) => !r.selected);
            const removed = before - this.ipExclusionsModal.rows.length;
            this.showMenuToast(removed ? `${removed} selected row(s) removed from view.` : 'Select rows first.', removed ? 'success' : 'info');
        },
        openPlacementModal() {
            this.placementModal.open = true;
            this.lockSpecModal();
        },
        closePlacementModal() {
            this.placementModal.open = false;
            this.unlockSpecModal();
        },
        openTrackingTemplateModal() {
            if (!this.trackingTemplateModal.finalUrl) {
                this.trackingTemplateModal.finalUrl = this.activeDomainLabel.startsWith('http')
                    ? this.activeDomainLabel
                    : (`https://${this.activeDomainLabel}`);
            }
            this.trackingTemplateModal.open = true;
            this.lockSpecModal();
        },
        closeTrackingTemplateModal() {
            this.trackingTemplateModal.open = false;
            this.unlockSpecModal();
        },
        openTestProtectionModal(tab = 'tests') {
            const apiOk = Boolean(this.connectionHealth.api_ok) || this.googleAdsApiHealthy;
            const scriptOk = Boolean(this.connectionHealth.script_ok) || this.trackingScriptOk;
            const tagOk = Boolean(this.connectionHealth.google_tag_ok) || this.trackingInstallation.google_tag?.ok;
            const audienceOk = String(this.connectionHealth.audience_protection || '').toLowerCase() === 'active';
            const hasCustomer = Boolean(this.googleAdsSummary.customer_id);
            this.testProtectionModal.tab = tab || 'tests';
            this.testProtectionModal.checks = [
                { key: 'api', label: 'Ads API permission', ok: apiOk, warn: false, state: apiOk ? 'Passed' : 'Missing' },
                { key: 'cid', label: 'Customer ID', ok: hasCustomer, warn: false, state: hasCustomer ? 'Passed' : 'Missing' },
                { key: 'script', label: 'Clickronix script', ok: scriptOk, warn: false, state: scriptOk ? 'Passed' : 'Missing' },
                { key: 'tag', label: 'Google tag', ok: tagOk, warn: !tagOk, state: tagOk ? 'Passed' : 'Missing' },
                { key: 'ga4', label: 'GA4 invalid event', ok: false, warn: true, state: 'Pending' },
                { key: 'audience', label: 'Audience list', ok: audienceOk, warn: !audienceOk, state: audienceOk ? 'Passed' : 'Not created' },
                { key: 'ip', label: 'IP write permission', ok: apiOk, warn: false, state: apiOk ? 'Passed' : 'Pending' },
                { key: 'placement', label: 'Placement report access', ok: apiOk, warn: false, state: apiOk ? 'Passed' : 'Pending' },
            ];
            this.testProtectionModal.open = true;
            this.lockSpecModal();
            if (config.testUrl) {
                this.testGoogleHealth();
            }
        },
        closeTestProtectionModal() {
            this.testProtectionModal.open = false;
            this.unlockSpecModal();
        },
        fixNextRequirement() {
            const next = (this.testProtectionModal.checks || []).find((c) => !c.ok);
            this.closeTestProtectionModal();
            if (!next) {
                this.showMenuToast('All checks ready.', 'success');
                return;
            }
            if (next.key === 'tag' || next.key === 'script' || next.key === 'ga4') {
                this.openInstallTagsModal(next.key === 'script' ? 'script' : 'gtm');
                return;
            }
            if (next.key === 'cid' || next.key === 'api' || next.key === 'ip') {
                this.openConnectGoogleModal();
                return;
            }
            if (next.key === 'audience') {
                this.openAudienceMethodModal();
                return;
            }
            this.openPlacementModal();
        },
        get setupProgressFill() {
            const steps = this.activeSetupProgress || [];
            if (steps.length < 2) return 0;
            let lastDone = -1;
            for (let i = 0; i < steps.length; i += 1) {
                if (steps[i].done) lastDone = i;
                else break;
            }
            if (lastDone <= 0) return 0;
            return Math.round((lastDone / (steps.length - 1)) * 100);
        },
        get healthPct() {
            const items = this.healthItems;
            if (!items.length) return 0;
            return Math.round((items.filter((i) => i.ok).length / items.length) * 100);
        },
        get healthLive() {
            return this.googleAdsApiHealthy && this.firstIpCaught && this.healthPct === 100;
        },
        get filteredTrackingIds() {
            let rows = this.trackingIds || [];
            if (this.selectedDomainId) {
                rows = rows.filter((row) => String(row.domain_id) === String(this.selectedDomainId));
            }
            if (this.selectedAdsAccountId) {
                rows = rows.filter((row) => String(row.account_id) === String(this.selectedAdsAccountId));
            }
            return rows;
        },
        get filteredPlatformRows() {
            let rows = this.platformRows || [];
            if (this.selectedDomainId) {
                rows = rows.filter((row) => String(row.domain_id) === String(this.selectedDomainId));
            }
            if (this.selectedAdsAccountId) {
                rows = rows.filter((row) => String(row.account_id) === String(this.selectedAdsAccountId));
            }
            const q = String(this.platformSearch || '').trim().toLowerCase();
            if (!q) return rows;
            return rows.filter((row) => String(row.search || '').includes(q)
                || String(row.platform || '').toLowerCase().includes(q)
                || String(row.account_primary || '').toLowerCase().includes(q)
                || String(row.entity_id || '').toLowerCase().includes(q));
        },
        get platformPageCount() {
            return Math.max(1, Math.ceil(this.filteredPlatformRows.length / this.platformPerPage));
        },
        get pagedPlatformRows() {
            const page = Math.min(this.platformPage, this.platformPageCount);
            const start = (page - 1) * this.platformPerPage;
            return this.filteredPlatformRows.slice(start, start + this.platformPerPage);
        },
        get platformRangeLabel() {
            const total = this.filteredPlatformRows.length;
            if (!total) return 'Showing 0 results';
            return `Showing 1 to ${total} of ${total} results`;
        },
        platformRowVisible(searchText) {
            const q = String(this.platformSearch || '').trim().toLowerCase();
            if (!q) return true;
            return String(searchText || '').includes(q);
        },
        refreshPlatforms() {
            window.location.reload();
        },
        async refreshSyncLogs() {
            try {
                const res = await fetch(config.logsUrl || '/integrations/logs', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.syncLogs = data.logs || [];
            } catch (e) {
                this.showMenuToast('Could not refresh sync logs.', 'error');
            }
        },
        async testGoogleHealth() {
            if (!config.testUrl) {
                this.showMenuToast('Connect Google first.', 'error');
                return;
            }
            try {
                const res = await fetch(config.testUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'Content-Type': 'application/json',
                    },
                    body: '{}',
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    this.showMenuToast(data.message || 'Health check failed.', 'error');
                    this.connectionHealth = {
                        ...this.connectionHealth,
                        health_status: 'error',
                        last_sync_message: data.message || 'Health check failed',
                    };
                    await this.refreshSyncLogs();
                    return;
                }
                this.connectionHealth = {
                    ...this.connectionHealth,
                    health_status: data.health_status || 'ok',
                    email: data.email || this.connectionHealth.email,
                    last_sync_at: data.last_sync_at || new Date().toISOString(),
                    last_sync_status: 'ok',
                };
                this.showMenuToast(data.message || 'Connection healthy', 'success');
                await this.refreshSyncLogs();
            } catch (e) {
                this.showMenuToast('Health check failed.', 'error');
            }
        },
        get activeDomainStatus() {
            if (!this.selectedDomainId) return null;
            return this.domainConnections.find((d) => String(d.id) === String(this.selectedDomainId)) || null;
        },
        get activeSetupProgress() {
            if (this.selectedDomainId) {
                const steps = this.setupProgressByDomain[String(this.selectedDomainId)];
                if (Array.isArray(steps) && steps.length) return steps;
            }
            return this.setupProgressAll;
        },
        get googleConnected() {
            // OAuth / Ads API only — never infer from tag script.
            return Boolean(this.googleOAuthConnected);
        },
        get googleAdsApiErrored() {
            const status = String(this.connectionHealth.health_status || '').toLowerCase();
            return status === 'error' || status === 'failed';
        },
        get googleAdsApiHealthy() {
            if (!this.googleOAuthConnected || this.googleAdsApiErrored) return false;
            const status = String(this.connectionHealth.health_status || '').toLowerCase();
            if (status === 'pending' || status === '') {
                return Number(this.connectionHealth.accounts || 0) > 0 || Boolean(this.connectionHealth.last_sync_at);
            }
            return true;
        },
        get googleAdsConnected() {
            if (this.activeDomainStatus) return Boolean(this.activeDomainStatus.google_ads_connected);
            return this.domainConnections.some((d) => d.google_ads_connected);
        },
        get requirementSteps() {
            const labels = ['Tag Manager', 'Paid Marketing', 'Bot Protection', 'Google Ads'];
            if (this.activeDomainStatus) {
                return this.activeDomainStatus.steps || [];
            }
            return labels.map((label) => ({
                label,
                done: this.domainConnections.some((d) => (d.steps || []).find((s) => s.label === label)?.done),
            }));
        },
        get requirementRingPct() {
            const steps = this.requirementSteps;
            if (!steps.length) return 0;
            const done = steps.filter((s) => s.done).length;
            return Math.round((done / steps.length) * 100);
        },
        get requirementLive() {
            const steps = this.requirementSteps;
            return steps.length > 0 && steps.every((s) => s.done);
        },
        requireSelectedDomain() {
            if (!this.selectedDomainId) {
                this.showMenuToast('Select a domain from the header first.', 'error');
                return null;
            }
            const domain = this.activeDomainStatus;
            if (!domain) {
                this.showMenuToast('Selected domain not found.', 'error');
                return null;
            }
            return domain;
        },
        wpUrls(hostname) {
            const host = String(hostname || '').replace(/^https?:\/\//i, '').replace(/\/.*$/, '');
            const base = 'https://' + host;
            return {
                admin: base + '/wp-login.php?redirect_to=' + encodeURIComponent(base + '/wp-admin/'),
                settings: base + '/wp-login.php?redirect_to=' + encodeURIComponent(base + '/wp-admin/options-general.php?page=promotix-tag'),
            };
        },
        closeKeysModal() {
            this.keysModal.open = false;
        },
        async openDomainKeys(domain) {
            const wp = this.wpUrls(domain.hostname);
            this.keysModal = {
                open: true,
                id: domain.id,
                hostname: domain.hostname,
                setupUrl: `/domains/${domain.id}/setup`,
                wpAdminUrl: wp.admin,
                wpPluginSettingsUrl: wp.settings,
                rows: [
                    { label: 'Server URL', value: '…' },
                    { label: 'Domain Key', value: '…' },
                    { label: 'Secret key', value: '…' },
                    { label: 'Authentication Key', value: '…' },
                ],
            };
            try {
                const res = await fetch(`/domains/${domain.id}/api-key`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.keysModal.rows = [
                    { label: 'Server URL', value: data.server_url },
                    { label: 'Domain Key', value: data.domain_key },
                    { label: 'Secret key', value: data.secret_key },
                    { label: 'Authentication Key', value: data.authentication_key },
                ];
            } catch (_) {
                this.showMenuToast('Could not load domain keys.', 'error');
            }
        },
        handleRequirementClick(step) {
            const domain = this.requireSelectedDomain();
            if (!domain) return;

            const label = step.label;

            if (label === 'Tag Manager') {
                window.location.href = `/domains/${domain.id}/setup`;
                return;
            }

            if (label === 'Bot Protection') {
                this.openDomainKeys(domain);
                return;
            }

            if (label === 'Paid Marketing' || label === 'Google Ads') {
                const domainStep = (domain.steps || []).find((s) => s.label === label);
                if (domainStep?.done) {
                    this.showMenuToast(`${label} is already connected for ${domain.hostname}.`, 'info');
                    return;
                }
                window.location.href = config.paidMarketingConnectUrl.replace('/domains/0/', `/domains/${domain.id}/`);
            }
        },
        copyKeyText(text) {
            this.copyText(text, 'Key');
        },
        copyAllKeys() {
            const blob = (this.keysModal.rows || []).map((r) => `${r.label}: ${r.value}`).join('\n');
            this.copyText(blob, 'All keys');
        },
        async verifyKeysInstallation() {
            if (!this.keysModal.id) return;
            try {
                const res = await fetch(`/domains/${this.keysModal.id}/verify-wordpress`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                this.showMenuToast(data.verified ? 'Installation verified — reload page' : (data.message || 'Not verified'), data.verified ? 'success' : 'error');
                if (data.verified) {
                    setTimeout(() => window.location.reload(), 1200);
                }
            } catch (_) {
                this.showMenuToast('Verify request failed.', 'error');
            }
        },
        showMenuToast(message, type = 'info') {
            this.menuToast = message;
            this.menuToastType = type;
            clearTimeout(this.menuToastTimer);
            this.menuToastTimer = setTimeout(() => { this.menuToast = ''; }, 3200);
        },
        async copyText(value, label = 'Copied') {
            const text = String(value || '').trim();
            if (!text) {
                this.showMenuToast('Nothing to copy yet.', 'error');
                return false;
            }
            try {
                await navigator.clipboard.writeText(text);
                this.showMenuToast(`${label} copied.`, 'success');
                return true;
            } catch (e) {
                this.showMenuToast('Could not copy to clipboard.', 'error');
                return false;
            }
        },
        scrollToEl(id, focusSelector = null) {
            const el = document.getElementById(id);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (focusSelector) {
                const target = el.querySelector(focusSelector) || el;
                if (target?.focus) setTimeout(() => target.focus(), 400);
            }
        },
        closeAllMenus() {
            Alpine.store('platformCardMenu').open = null;
        },
        emptyAudienceRow() {
            return { conversion_id: '', conversion_label: '', tag: '', domain_id: null };
        },
        closeAudienceModal() {
            this.audienceModal.open = false;
            this.audienceModal.error = '';
            this.audienceModal.saving = false;
        },
        addAudienceRow() {
            this.audienceModal.rows.push(this.emptyAudienceRow());
        },
        removeAudienceRow(idx) {
            if (this.audienceModal.rows.length <= 1) return;
            this.audienceModal.rows.splice(idx, 1);
        },
        onAudienceTagChange(idx, value) {
            const id = value ? Number(value) : null;
            const tag = (this.audienceModal.tags || []).find((t) => Number(t.id) === id);
            if (!this.audienceModal.rows[idx]) return;
            this.audienceModal.rows[idx].domain_id = id;
            this.audienceModal.rows[idx].tag = tag ? (tag.domain_key || tag.hostname || '') : '';
        },
        async openAudienceModal() {
            this.audienceModal.open = true;
            this.audienceModal.loading = true;
            this.audienceModal.error = '';
            try {
                const res = await fetch(this.audienceGetUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || payload.ok === false) {
                    throw new Error(payload.message || 'Could not load Audience Exclusion.');
                }
                this.audienceModal.mapping_id = payload.mapping_id || null;
                this.audienceModal.tags = Array.isArray(payload.tags) ? payload.tags : [];
                this.audienceModal.rows = Array.isArray(payload.audiences) && payload.audiences.length
                    ? payload.audiences.map((r) => ({
                        conversion_id: r.conversion_id || '',
                        conversion_label: r.conversion_label || '',
                        tag: r.tag || '',
                        domain_id: r.domain_id || null,
                    }))
                    : [this.emptyAudienceRow()];
            } catch (error) {
                this.audienceModal.error = error?.message || 'Could not load Audience Exclusion.';
                this.audienceModal.rows = [this.emptyAudienceRow()];
            } finally {
                this.audienceModal.loading = false;
            }
        },
        async saveAudienceExclusion() {
            if (this.audienceModal.saving) return;
            this.audienceModal.saving = true;
            this.audienceModal.error = '';
            try {
                const res = await fetch(this.audienceSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        mapping_id: this.audienceModal.mapping_id,
                        enabled: true,
                        audiences: this.audienceModal.rows,
                    }),
                });
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || payload.ok === false) {
                    throw new Error(payload.message || (payload.errors && payload.errors[0]) || 'Save failed.');
                }
                this.showMenuToast(payload.message || 'Audience Exclusion saved.', 'success');
                this.closeAudienceModal();
            } catch (error) {
                this.audienceModal.error = error?.message || 'Save failed.';
            } finally {
                this.audienceModal.saving = false;
            }
        },
        handlePlatformMenu(detail) {
            const action = detail?.action;
            if (!action) return;
            this.closeAllMenus();
            switch (action) {
                case 'google-details':
                case 'direct-details':
                    this.scrollToEl('connected-platforms');
                    break;
                case 'copy-tracking':
                    this.copyText(config.trackingLink, 'Tracking link');
                    break;
                case 'open-pixel-guard':
                    this.openPixelGuardModal();
                    break;
                case 'open-audience-exclusion':
                    this.openAudienceMethodModal();
                    break;
                case 'manage-ad-account':
                    this.scrollToEl('connected-platforms');
                    break;
                case 'test-google':
                    this.testGoogleConnection();
                    break;
                case 'disconnect-google':
                    this.disconnectGoogle();
                    break;
                case 'edit-direct-id': {
                    const input = document.getElementById('direct-account-id');
                    input?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => input?.focus(), 300);
                    break;
                }
                case 'copy-direct-id': {
                    const row = this.directList[0];
                    const id = row?.account_id || this.directForm.account_id;
                    this.copyText(id, 'Tracking ID');
                    break;
                }
                case 'test-direct':
                    this.testDirectTracking();
                    break;
                case 'regenerate-direct-id': {
                    const next = `AW-${Date.now().toString().slice(-9)}`;
                    this.directForm.tag_id = next;
                    const tagEl = document.getElementById('direct-tag-id');
                    if (tagEl) tagEl.focus();
                    this.showMenuToast('New conversion tag ID generated.');
                    break;
                }
                case 'remove-direct':
                    this.removeDirectPlatform();
                    break;
            }
        },
        async testGoogleConnection() {
            try {
                const res = await fetch(config.statusUrl, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                const g = data?.google || {};
                const msg = g.connected
                    ? `Google connected${g.accounts ? ` · ${g.accounts} ad account(s)` : ''}.`
                    : (g.oauth_configured ? 'Google OAuth ready — connect your account.' : 'Google Ads OAuth is not configured on the server.');
                this.showMenuToast(msg);
            } catch (e) {
                this.showMenuToast('Connection test failed.');
            }
        },
        async testDirectTracking() {
            if (!this.directList.length && !this.directForm.account_id) {
                this.showMenuToast('Add a Direct Ads ID first.');
                return;
            }
            this.showMenuToast(this.directList.length
                ? `Direct Ads active · ${this.directList.length} integration(s).`
                : 'Draft ID ready — click Add to save.');
        },
        disconnectGoogle() {
            if (!config.disconnectUrl) {
                this.showMenuToast('No Google connection to remove.');
                return;
            }
            if (!confirm('Disconnect Google from this workspace?')) return;
            const form = document.getElementById('google-disconnect-form');
            if (form) form.submit();
        },
        async removeDirectPlatform() {
            const row = this.directList[0];
            if (!row?.id) {
                this.directForm = { platform: 'custom', account_label: 'Direct Ads', account_id: '', tag_id: '' };
                this.showMenuToast('Direct Ads cleared.');
                return;
            }
            if (!confirm('Remove this Direct Ads integration?')) return;
            const res = await fetch(`${config.directStoreUrl}/${row.id}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
            });
            if (!res.ok) {
                this.showMenuToast('Could not remove Direct Ads.');
                return;
            }
            this.directList = this.directList.filter((item) => item.id !== row.id);
            this.showMenuToast('Direct Ads removed.');
        },
        async addDirectAds() {
            const response = await fetch(config.directStoreUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify(this.directForm),
            });
            if (!response.ok) return;
            const data = await response.json();
            if (data.integration) this.directList.unshift(data.integration);
            this.directForm = { platform: 'custom', account_label: 'Direct Ads', account_id: '', tag_id: '' };
        },
    };
}
</script>
@endsection
