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
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Campaigns</span>
                    <select class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option>All campaigns</option>
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Filter by path</span>
                    <input placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
                <button type="button" class="figma-filter-action flex w-[34px] items-center justify-center bg-[#6400B2] text-white" aria-label="Filter">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8M8 12h8M8 17h8"/></svg>
                </button>
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
                        <div class="rounded border border-white {{ $googleStatusConnected ? 'bg-[#606060]/55' : 'bg-white/50' }} p-[8px] text-center">
                            <div class="mx-auto mb-[8px] flex h-[50px] w-[50px] items-center justify-center rounded bg-white">
                                @include('partials.icons.google', ['class' => 'h-[32px] w-[32px]'])
                            </div>
                            <div class="bg-white px-[4px] py-[2px] text-[8px] font-semibold {{ $googleStatusConnected ? 'text-[#6706B3]' : 'text-[#101010]' }}">{{ $googleStatusConnected ? 'Connected' : 'Not Connected' }}</div>
                        </div>
                        <div class="rounded border border-white bg-white/50 p-[8px] text-center">
                            <div class="mx-auto mb-[8px] flex h-[50px] w-[50px] items-center justify-center">
                                @include('partials.icons.google-ads', ['class' => 'h-[50px] w-[50px]'])
                            </div>
                            <div class="bg-white px-[4px] py-[2px] text-[8px] text-[#101010]">{{ $directConnected ? 'Connected' : 'Not Connected' }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[10px] bg-[#3c3c3c] p-[16px]">
                    <div class="mb-[20px] flex items-center justify-between gap-[8px]">
                        <h2 class="text-[16px] font-medium text-[#d9d9d9]">Connection Requirement</h2>
                        @if (collect($requirementSteps)->every(fn ($s) => $s['done']))
                            <span class="rounded-full bg-emerald-500/20 px-[10px] py-[3px] text-[10px] font-semibold text-emerald-200">Live</span>
                        @else
                            <span class="rounded-full bg-amber-500/20 px-[10px] py-[3px] text-[10px] font-semibold text-amber-100">Setup in progress</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-[84px_1fr] items-center gap-[18px]">
                        @php
                            $stepsDone = collect($requirementSteps)->filter(fn ($s) => $s['done'])->count();
                            $ringPct = (int) round(($stepsDone / max(count($requirementSteps), 1)) * 100);
                        @endphp
                        <div class="relative h-[84px] w-[84px] shrink-0">
                            <div class="h-full w-full rounded-full" style="background: conic-gradient(#7a56a9 {{ $ringPct }}%, #d9d9d9 0)"></div>
                            <div class="absolute inset-[14px] rounded-full bg-[#3c3c3c]"></div>
                        </div>
                        <div class="space-y-[8px]">
                            @foreach ($requirementSteps as $step)
                                <div class="relative h-[15px] overflow-hidden rounded-full bg-[#d9d9d9]">
                                    <div class="absolute inset-y-[2px] left-[2px] rounded-full {{ $step['done'] ? 'w-[calc(100%-4px)] bg-[#7a56a9]' : 'w-0 bg-[#838284]' }}"></div>
                                    <span class="absolute inset-0 flex items-center justify-center text-[8px] text-white/70">{{ $step['label'] }}</span>
                                </div>
                            @endforeach
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

            <div id="mapping-filters" class="mb-[14px] grid gap-[8px] rounded-[8px] border border-white/20 bg-[#6400B2]/35 p-[10px] lg:grid-cols-[1fr_1fr_150px_auto]">
                <label class="flex flex-col gap-[4px]">
                    <span class="text-[9px] font-semibold uppercase text-white/60">Filter domain</span>
                    <select x-model="mappingFilter.domain" class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                        <option value="">All domains</option>
                        @foreach ($paidMarketingDomains as $domain)
                            <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-[4px]">
                    <span class="text-[9px] font-semibold uppercase text-white/60">Filter account</span>
                    <select x-model="mappingFilter.account" class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                        <option value="">All accounts</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->displayLabel() }} ({{ $account->display_customer_id ?: $account->customer_id }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-[4px]">
                    <span class="text-[9px] font-semibold uppercase text-white/60">Protection</span>
                    <select x-model="mappingFilter.protection" class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                        <option value="">All types</option>
                        <option value="ip_blocking">IP Blocking</option>
                        <option value="pixel_guard">Pixel Guard</option>
                    </select>
                </label>
                <button type="button" @click="mappingFilter = { domain: '', account: '', protection: '' }" class="self-end rounded-[5px] border border-white/30 px-[12px] py-[8px] text-[11px] text-white hover:bg-white/10">Clear</button>
            </div>

            <details class="mb-[14px] rounded-[8px] border border-white/15 bg-black/20 p-[10px] text-white">
                <summary class="cursor-pointer text-[12px] font-medium text-white/90">Link new domain to Google Ads</summary>
                <form id="link-domain-form" method="POST" action="{{ route('integrations.store-mapping') }}" class="mt-[10px] grid gap-[8px] lg:grid-cols-[1fr_1fr_150px_130px]">
                    @csrf
                    <select name="domain_id" required class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white">
                        <option value="">Select domain</option>
                        @foreach ($paidMarketingDomains as $domain)
                            <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                        @endforeach
                    </select>
                    <select id="google-ads-account-select" name="google_ads_account_id" required class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white">
                        <option value="">Select account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->displayLabel() }} ({{ $account->display_customer_id ?: $account->customer_id }})</option>
                        @endforeach
                    </select>
                    <select name="protection_type" class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white">
                        <option value="ip_blocking">IP Blocking</option>
                        <option value="pixel_guard">Pixel Guard</option>
                    </select>
                    <button type="submit" class="rounded-[5px] bg-[#6706B3] px-[12px] text-[12px] font-semibold text-white">Link</button>
                </form>
            </details>

            <div class="overflow-x-auto">
                <table class="min-w-[1040px] w-full border-separate border-spacing-y-[5px] text-left">
                    <thead>
                        <tr class="text-[14px] font-medium text-white">
                            <th class="px-[22px] py-[8px]">Platform</th>
                            <th class="px-[22px] py-[8px]">Protection Type</th>
                            <th class="px-[22px] py-[8px]">Connected Entity ID</th>
                            <th class="px-[22px] py-[8px]">Tag</th>
                            <th class="px-[22px] py-[8px]">Settings</th>
                            <th class="px-[22px] py-[8px]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr
                                class="rounded-[5px] bg-[#d9d9d9] text-[#121212]"
                                x-show="mappingRowVisible({{ $mapping->domain_id }}, {{ $mapping->google_ads_account_id }}, @js($mapping->protection_type))"
                                x-cloak
                            >
                                <td class="rounded-l-[5px] px-[22px] py-[10px] text-[16px] font-medium">
                                    <span class="inline-flex items-center gap-[10px]">
                                        @include('partials.icons.google', ['class' => 'h-[22px] w-[22px]'])
                                        Google
                                    </span>
                                </td>
                                <td class="px-[22px] py-[10px] text-[10px]">{{ $mapping->protection_type === 'pixel_guard' ? 'Pixel Guard' : 'Audience Exclusion' }}</td>
                                <td class="px-[22px] py-[10px] text-[12px]">
                                    <span class="block font-medium">{{ $mapping->account->displayLabel() }}</span>
                                    <span class="text-[10px] text-[#121212]/65">{{ $mapping->account->display_customer_id ?: $mapping->account->customer_id }}</span>
                                </td>
                                <td class="px-[22px] py-[10px] text-[12px]">{{ $mapping->domain->hostname }}</td>
                                <td class="px-[22px] py-[10px] text-[12px] font-medium text-[#6706B3]">
                                    <a href="{{ route('paid-marketing.detection-settings', ['domain_id' => $mapping->domain_id]) }}" class="inline-flex items-center gap-[6px] hover:underline">
                                        @include('partials.sidebar-icon', ['name' => 'settings', 'class' => 'h-[18px] w-[18px]'])
                                        Campaign Settings
                                    </a>
                                </td>
                                <td class="rounded-r-[5px] px-[22px] py-[10px] text-right">
                                    <form method="POST" action="{{ route('integrations.destroy-mapping', $mapping) }}" onsubmit="return confirm('Remove this platform link?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-[6px] rounded-[5px] border border-red-400/50 bg-red-500/10 px-[10px] py-[5px] text-[11px] font-semibold text-red-700 hover:bg-red-500/20">
                                            @include('partials.sidebar-icon', ['name' => 'trash', 'class' => 'h-[14px] w-[14px]'])
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="rounded-[5px] bg-[#d9d9d9] px-[22px] py-[20px] text-center text-[13px] text-[#121212]">No platform mappings yet.</td>
                            </tr>
                        @endforelse

                        <template x-for="row in directList" :key="`direct-${row.id}`">
                            <tr class="rounded-[5px] bg-[#d9d9d9] text-[#121212]">
                                <td class="rounded-l-[5px] px-[22px] py-[10px] text-[16px] font-medium">Direct Ads</td>
                                <td class="px-[22px] py-[10px] text-[12px]">ID Tracking</td>
                                <td class="px-[22px] py-[10px] text-[12px]" x-text="row.account_id || 'N/A'"></td>
                                <td class="px-[22px] py-[10px] text-[12px]" x-text="row.tag_id || 'N/A'"></td>
                                <td class="px-[22px] py-[10px] text-[12px] font-medium text-[#6706B3]">Campaign Settings</td>
                                <td class="rounded-r-[5px] px-[22px] py-[10px] text-right"></td>
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

    <div
        x-show="menuToast"
        x-cloak
        x-transition
        class="fixed bottom-[24px] right-[24px] z-[200] max-w-[320px] rounded-[8px] border border-white/25 bg-[#101010] px-[14px] py-[10px] text-[12px] text-white shadow-lg"
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
        directForm: { platform: 'custom', account_label: 'Direct Ads', account_id: '', tag_id: '' },
        mappingFilter: { domain: '', account: '', protection: '' },
        mappingRowVisible(domainId, accountId, protection) {
            const f = this.mappingFilter;
            if (f.domain && String(domainId) !== String(f.domain)) return false;
            if (f.account && String(accountId) !== String(f.account)) return false;
            if (f.protection && protection !== f.protection) return false;
            return true;
        },
        menuToast: '',
        menuToastTimer: null,
        showMenuToast(message) {
            this.menuToast = message;
            clearTimeout(this.menuToastTimer);
            this.menuToastTimer = setTimeout(() => { this.menuToast = ''; }, 3200);
        },
        async copyText(value, label = 'Copied') {
            const text = String(value || '').trim();
            if (!text) {
                this.showMenuToast('Nothing to copy yet.');
                return false;
            }
            try {
                await navigator.clipboard.writeText(text);
                this.showMenuToast(`${label} copied.`);
                return true;
            } catch (e) {
                this.showMenuToast('Could not copy to clipboard.');
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
                case 'open-pixel-guard': {
                    this.scrollToEl('mapping-filters');
                    this.mappingFilter.protection = 'pixel_guard';
                    this.showMenuToast('Filtered to Pixel Guard mappings.');
                    break;
                }
                case 'manage-ad-account':
                    this.scrollToEl('mapping-filters');
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
