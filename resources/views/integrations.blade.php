@extends('layouts.admin')

@section('title', 'Platform Integrate')

@php
    $googleOAuthConnected = $connections->isNotEmpty();
    $directConnected = $directAds->isNotEmpty();
    $googleStatusConnected = $platformReady ?? false;
    $menuDomain = $domains->first();
    $primaryConnection = $connections->first();
@endphp

@section('rightbar')
    <div class="mb-[22px]">
        <p class="text-[18px] font-bold leading-none text-[#a9a9a9]">Digital Promotix</p>
    </div>

    <div class="mb-[24px] grid grid-cols-4 gap-[9px]">
        @foreach (['bell-notification', 'chat', 'share', 'more'] as $icon)
            @if ($icon === 'chat')
                <button type="button" @click="$dispatch('open-live-agent')" class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white hover:bg-[#7B13C8]" aria-label="Live agent chat">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M4 5h16v11H8l-4 4V5z"/></svg>
                </button>
            @else
                <a href="{{ route('integrations') }}" class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white" aria-label="{{ $icon }}">
                    @if ($icon === 'bell-notification')
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"/></svg>
                    @elseif ($icon === 'share')
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M8 12h8M16 12l-4-4m4 4l-4 4"/></svg>
                    @else
                        <svg class="h-[13px] w-[13px]" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z"/></svg>
                    @endif
                </a>
            @endif
        @endforeach
    </div>

    <div id="right-notifications" class="space-y-[13px] border-b-2 border-[#5a2a99] pb-[18px] text-[10px] text-[#a9a9a9]"></div>
    @include('partials.figma-notifications-script')
@endsection

@section('content')
<div
    class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]"
    x-data="platformIntegrations(@js([
        'csrf' => csrf_token(),
        'directStoreUrl' => url('/integrations/direct-ads'),
        'trackingLink' => $menuDomain ? url('/tag/' . $menuDomain->domain_key . '.js') : null,
        'statusUrl' => url('/integrations/status'),
        'disconnectUrl' => $primaryConnection ? route('integrations.google.disconnect', $primaryConnection) : null,
        'paidMarketingConnectUrl' => route('domains.paid-marketing.connect', ['domain' => 0]),
        'domainConnections' => $domainConnections,
        'directInitial' => $directAds->map(fn ($row) => [
            'id' => $row->id,
            'platform' => $row->platform,
            'account_label' => $row->account_label,
            'account_id' => $row->account_id,
            'tag_id' => $row->tag_id,
        ])->values(),
    ]))"
    @platform-menu.window="handlePlatformMenu($event.detail)"
>
    <section class="mx-auto w-full max-w-[1180px] px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Platform</span>
            </div>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                <label class="flex min-w-0 flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/70">Domains</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="selectedDomainId" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All domains</option>
                            @foreach ($manualDomains as $domain)
                                <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Filter by path</span>
                    <input placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[#6400B2]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        <div class="grid gap-[12px] xl:grid-cols-[minmax(0,720px)_minmax(320px,1fr)]">
            <section class="rounded-[10px] border border-white/40 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.35)]">
                <h2 class="mb-[16px] text-[24px] font-medium text-white">Connect Your Platforms</h2>

                <div class="grid gap-[16px] lg:grid-cols-2">
                    <article class="min-h-[232px] rounded-[10px] border border-[#d9d9d9]/60 p-[18px]">
                        <div class="flex items-start justify-between gap-[8px]">
                            <div class="flex min-w-0 flex-1 gap-[18px]">
                                <div class="w-[90px] shrink-0">
                                    <div class="mb-[12px] flex h-[79px] w-[90px] items-center justify-center rounded bg-white">
                                        @include('partials.icons.google', ['class' => 'h-[55px] w-[55px]'])
                                    </div>
                                    <p class="text-center text-[20px] font-medium leading-none text-white">Google</p>
                                    <div class="mx-auto mt-[8px] min-h-[15px] w-[72px] rounded-sm px-[4px] py-[2px] text-center text-[8px] font-semibold {{ $googleStatusConnected ? 'bg-white text-[#6706B3]' : 'bg-black/55 text-white/70' }}">
                                        {{ $googleStatusConnected ? 'Connected' : 'Setup' }}
                                    </div>
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col justify-center gap-[10px]">
                                    <a href="{{ route('domains.index') }}" class="figma-platform-action flex h-[28px] w-full max-w-[152px] items-center gap-[8px] rounded border border-white/95 bg-[#6706B3] px-[9px] text-[11px] leading-none text-white">
                                        @include('partials.sidebar-icon', ['name' => 'tag', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                        <span class="whitespace-nowrap">Tag Manager</span>
                                    </a>
                                    <a href="{{ route('paid-marketing.detection-settings') }}" class="figma-platform-action flex h-[28px] w-full max-w-[152px] items-center gap-[8px] rounded border border-white/95 bg-[#6706B3] px-[9px] text-[11px] leading-none text-white">
                                        @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                        <span class="whitespace-nowrap">Paid Marketing</span>
                                    </a>
                                    <a href="{{ route('bot-protection.dashboard') }}" class="figma-platform-action flex h-[28px] w-full max-w-[152px] items-center gap-[8px] rounded border border-white/95 bg-[#6706B3] px-[9px] text-[11px] leading-none text-white">
                                        @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                        <span class="whitespace-nowrap">Bot Protection</span>
                                    </a>
                                    @if ($googleOAuthConnected && ($primaryConnection = $connections->first()))
                                        <form method="POST" action="{{ route('integrations.google.sync-accounts', $primaryConnection) }}">
                                            @csrf
                                            <button type="submit" class="figma-platform-action flex h-[28px] w-full max-w-[152px] items-center gap-[8px] rounded border border-white bg-white px-[9px] text-[11px] font-normal text-[#6706B3] hover:bg-white/90">
                                                <svg class="h-[14px] w-[14px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                Sync Ads
                                            </button>
                                        </form>
                                        <a href="{{ route('integrations.google.redirect') }}" class="figma-platform-action flex h-[28px] w-full max-w-[152px] items-center gap-[8px] rounded border border-white/60 bg-transparent px-[9px] text-[11px] font-normal text-white/90 hover:border-white">
                                            <span class="text-[12px]">+</span>
                                            Add Google login
                                        </a>
                                    @else
                                        <a href="{{ route('integrations.google.redirect') }}" class="figma-platform-action flex h-[28px] w-full max-w-[152px] items-center gap-[8px] rounded border border-white bg-white px-[9px] text-[11px] font-normal text-[#6706B3]">
                                            <span class="flex h-[17px] w-[17px] items-center justify-center rounded-full border border-[#6706B3] text-[12px]">+</span>
                                            Connect Google
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <x-integrations.google-platform-menu
                                menu-id="google"
                                :google-o-auth-connected="$googleOAuthConnected"
                                :menu-domain="$menuDomain"
                                :primary-connection="$primaryConnection"
                            />
                        </div>
                    </article>

                    <article class="min-h-[232px] rounded-[10px] border border-[#d9d9d9]/60 p-[18px]">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-[20px] pt-[28px]">
                                @include('partials.icons.google-ads', ['class' => 'h-[64px] w-[64px]'])
                                <p class="text-[20px] font-medium leading-none text-white">Direct Ads</p>
                            </div>
                            <x-integrations.direct-ads-platform-menu menu-id="direct" />
                        </div>

                        <form class="mt-[12px] grid gap-[9px] sm:grid-cols-[1fr_auto]" @submit.prevent="addDirectAds()">
                            <input id="direct-account-id" x-model="directForm.account_id" placeholder="Customer ID (e.g. 123-456-7890)" class="h-[26px] rounded border border-white bg-white px-[8px] text-[12px] text-[#6706B3] placeholder:text-[#6706B3]/80 focus:ring-[#6400B2]">
                            <button type="submit" class="h-[26px] rounded border border-white bg-white px-[10px] text-[12px] text-[#6706B3]">Add</button>
                            <input id="direct-tag-id" x-model="directForm.tag_id" placeholder="Conversion tag ID (e.g. AW-123456789)" class="h-[26px] rounded border border-white bg-white px-[8px] text-[12px] text-[#6706B3] placeholder:text-[#6706B3]/80 focus:ring-[#6400B2] sm:col-span-2">
                        </form>
                    </article>
                </div>
            </section>

            <div class="grid gap-[12px] sm:grid-cols-2 xl:grid-cols-1">
                <section class="rounded-[10px] bg-[#6706B3] p-[10px]">
                    <p class="mb-[12px] text-center text-[8px] uppercase text-white">Connection Status</p>
                    <div class="grid grid-cols-2 gap-[6px]">
                        <div class="rounded border border-white p-[8px] text-center" :class="googleConnected ? 'bg-[#606060]/55' : 'bg-white/50'">
                            <div class="mx-auto mb-[8px] flex h-[50px] w-[50px] items-center justify-center rounded bg-white">
                                @include('partials.icons.google', ['class' => 'h-[32px] w-[32px]'])
                            </div>
                            <div class="bg-white px-[4px] py-[2px] text-[8px] font-semibold" :class="googleConnected ? 'text-[#6706B3]' : 'text-[#101010]'" x-text="googleConnected ? 'Connected' : 'Not Connected'"></div>
                        </div>
                        <div class="rounded border border-white p-[8px] text-center" :class="googleAdsConnected ? 'bg-[#606060]/55' : 'bg-white/50'">
                            <div class="mx-auto mb-[8px] flex h-[50px] w-[50px] items-center justify-center">
                                @include('partials.icons.google-ads', ['class' => 'h-[50px] w-[50px]'])
                            </div>
                            <div class="bg-white px-[4px] py-[2px] text-[8px] font-semibold" :class="googleAdsConnected ? 'text-[#6706B3]' : 'text-[#101010]'" x-text="googleAdsConnected ? 'Connected' : 'Not Connected'"></div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[10px] bg-[#3c3c3c] p-[16px]">
                    <div class="mb-[20px] text-center">
                        <h2 class="text-[16px] font-medium text-[#d9d9d9]">Connection Requirement</h2>
                        <div class="mt-[10px] flex items-center justify-center gap-[8px]">
                        <span x-show="requirementLive" class="platform-requirement-status platform-requirement-status--live">
                            <span class="platform-requirement-status__dot"></span>
                            Live
                        </span>
                        <span x-show="!requirementLive" x-cloak class="platform-requirement-status platform-requirement-status--pending">
                            <span class="platform-requirement-status__dot"></span>
                            Setup in progress
                        </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-[84px_1fr] items-center gap-[18px] xl:gap-[24px]">
                        <div class="relative h-[84px] w-[84px] shrink-0">
                            <div class="h-full w-full rounded-full" :style="`background: conic-gradient(#7a56a9 ${requirementRingPct}%, #ffffff 0)`"></div>
                            <div class="absolute inset-[14px] rounded-full bg-[#3c3c3c]"></div>
                        </div>
                        <div class="platform-requirement-steps min-w-0">
                            <template x-for="step in requirementSteps" :key="step.label">
                                <button
                                    type="button"
                                    class="platform-requirement-bar"
                                    :class="step.done ? '' : 'has-setup'"
                                    @click="handleRequirementClick(step)"
                                >
                                    <span class="platform-requirement-bar__fill" :class="step.done ? 'is-done' : ''"></span>
                                    <span class="platform-requirement-bar__label" x-text="step.label"></span>
                                    <span x-show="!step.done" x-cloak class="platform-requirement-bar__setup">Setup</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <section id="connected-platforms" class="mt-[20px] scroll-mt-[80px] rounded-[10px] border border-[#6706B3] p-[16px]">
            <div class="mb-[26px]">
                <h2 class="text-[24px] font-medium text-white">Connected Platforms</h2>
                <p class="mt-[5px] text-[14px] font-medium text-white">Linked accounts &amp; domains</p>
            </div>

            <div>
                <table class="w-full table-fixed border-separate border-spacing-y-[5px] text-left">
                    <colgroup>
                        <col class="w-[11%]">
                        <col class="w-[15%]">
                        <col class="w-[27%]">
                        <col class="w-[23%]">
                        <col class="w-[20%]">
                        <col class="w-[4%]">
                    </colgroup>
                    <thead>
                        <tr class="text-[13px] font-medium text-white">
                            <th class="px-[12px] py-[8px]">Platform</th>
                            <th class="px-[12px] py-[8px]">Protection Type</th>
                            <th class="px-[12px] py-[8px]">Connected Entity ID</th>
                            <th class="px-[12px] py-[8px]">Tag</th>
                            <th class="px-[12px] py-[8px]">Settings</th>
                            <th class="px-[8px] py-[8px]"><span class="sr-only">Options</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr class="rounded-[5px] bg-[#d9d9d9] text-[#121212]">
                                <td class="rounded-l-[5px] px-[12px] py-[10px] text-[14px] font-medium">
                                    <span class="inline-flex min-w-0 items-center gap-[8px]">
                                        @include('partials.icons.google', ['class' => 'h-[20px] w-[20px] shrink-0'])
                                        <span class="truncate">Google</span>
                                    </span>
                                </td>
                                <td class="px-[12px] py-[10px] text-[10px] leading-snug">{{ $mapping->protection_type === 'pixel_guard' ? 'Pixel Guard' : 'Audience Exclusion' }}</td>
                                <td class="px-[12px] py-[10px] text-[12px]">
                                    <span class="block truncate font-medium" title="{{ $mapping->account->displayLabel() }}">{{ $mapping->account->displayLabel() }}</span>
                                    <span class="block truncate text-[10px] text-[#121212]/65">{{ $mapping->account->display_customer_id ?: $mapping->account->customer_id }}</span>
                                </td>
                                <td class="truncate px-[12px] py-[10px] text-[12px]" title="{{ $mapping->domain->hostname }}">{{ $mapping->domain->hostname }}</td>
                                <td class="px-[12px] py-[10px] text-[11px] font-medium text-[#6706B3]">
                                    <a href="{{ route('paid-marketing.detection-settings', ['domain_id' => $mapping->domain_id]) }}" class="inline-flex min-w-0 items-center gap-[5px] hover:underline">
                                        @include('partials.sidebar-icon', ['name' => 'settings', 'class' => 'h-[16px] w-[16px] shrink-0'])
                                        <span class="truncate">Campaign Settings</span>
                                    </a>
                                </td>
                                <td class="rounded-r-[5px] px-[8px] py-[10px] text-right">
                                    <div class="integration-row-menu inline-flex justify-end">
                                        <x-integrations.platform-card-dropdown :menu-id="'mapping-' . $mapping->id" label="Platform row options">
                                            <form method="POST" action="{{ route('integrations.destroy-mapping', $mapping) }}" onsubmit="return confirm('Remove this platform link?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="figma-platform-menu-item figma-platform-menu-item--danger w-full text-left">
                                                    @include('partials.sidebar-icon', ['name' => 'trash', 'class' => 'mr-[8px] inline h-[14px] w-[14px]'])
                                                    Delete
                                                </button>
                                            </form>
                                        </x-integrations.platform-card-dropdown>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="rounded-[5px] bg-[#d9d9d9] px-[12px] py-[20px] text-center text-[13px] text-[#121212]">No platform mappings yet.</td>
                            </tr>
                        @endforelse

                        <template x-for="row in directList" :key="`direct-${row.id}`">
                            <tr class="rounded-[5px] bg-[#d9d9d9] text-[#121212]">
                                <td class="rounded-l-[5px] px-[12px] py-[10px] text-[14px] font-medium">Direct Ads</td>
                                <td class="px-[12px] py-[10px] text-[12px]">ID Tracking</td>
                                <td class="truncate px-[12px] py-[10px] text-[12px]" x-text="row.account_id || 'N/A'"></td>
                                <td class="truncate px-[12px] py-[10px] text-[12px]" x-text="row.tag_id || 'N/A'"></td>
                                <td class="px-[12px] py-[10px] text-[11px] font-medium text-[#6706B3]">Campaign Settings</td>
                                <td class="rounded-r-[5px] px-[8px] py-[10px] text-right"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            @if ($mappings->hasPages())
                <div class="mt-[12px] border-t border-white/20 pt-[10px]">{{ $mappings->links() }}</div>
            @endif
        </section>
    </section>

    @if ($primaryConnection)
        <form id="google-disconnect-form" method="POST" action="{{ route('integrations.google.disconnect', $primaryConnection) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif

    {{-- Domain keys modal (Tag Manager + Bot Protection) --}}
    <div class="fixed inset-0 z-[90] flex items-center justify-center bg-black/70 p-[16px]" x-show="keysModal.open" x-cloak x-transition @click.self="closeKeysModal()" @keydown.escape.window="closeKeysModal()">
        <div class="w-full max-w-[560px] overflow-hidden rounded-[12px] bg-[#6400B2] text-white shadow-2xl" @click.stop>
            <header class="flex items-center justify-between border-b border-white/25 px-[24px] py-[18px]">
                <h2 class="text-[18px] font-semibold">Finish Setup <span class="text-[13px] font-normal text-white/80">(Required For WordPress Domains)</span></h2>
                <button type="button" @click="copyAllKeys()" class="flex items-center gap-[6px] text-[12px] text-white/90 hover:text-white">
                    <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M8 8h8v8H8zM4 4h8v2H6v6H4z"/></svg>
                    Copy all
                </button>
            </header>
            <div class="space-y-[14px] px-[24px] py-[20px]">
                <p class="text-[12px] text-white/85" x-text="'Installation keys for ' + (keysModal.hostname || 'domain')"></p>
                <template x-for="row in keysModal.rows" :key="row.label">
                    <div class="flex flex-col gap-[6px] sm:flex-row sm:items-center sm:gap-[12px]">
                        <span class="w-[130px] shrink-0 text-[12px] font-medium" x-text="row.label"></span>
                        <div class="min-w-0 flex-1 rounded-[4px] border border-dashed border-white/70 bg-[#4a0088]/50 px-[12px] py-[8px] font-mono text-[11px] break-all" x-text="row.value || '…'"></div>
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
                <button type="button" @click="closeKeysModal()" class="rounded-[6px] bg-white px-[22px] py-[8px] text-[13px] font-semibold text-[#6400B2]">Done</button>
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
            'border border-[#6400B2]/40 bg-[#6400B2]/30 text-white': menuToastType === 'info',
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
        selectedDomainId: '',
        keysModal: {
            open: false,
            id: null,
            hostname: '',
            rows: [],
            setupUrl: '#',
            wpAdminUrl: '#',
            wpPluginSettingsUrl: '#',
        },
        directForm: { platform: 'custom', account_label: 'Direct Ads', account_id: '', tag_id: '' },
        menuToast: '',
        menuToastType: 'info',
        menuToastTimer: null,
        get activeDomainStatus() {
            if (!this.selectedDomainId) return null;
            return this.domainConnections.find((d) => String(d.id) === String(this.selectedDomainId)) || null;
        },
        get googleConnected() {
            if (this.activeDomainStatus) return Boolean(this.activeDomainStatus.google_connected);
            return this.domainConnections.some((d) => d.google_connected);
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

            if (label === 'Tag Manager' || label === 'Bot Protection') {
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
                    this.scrollToEl('connected-platforms');
                    this.showMenuToast('Connected platforms below.');
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
