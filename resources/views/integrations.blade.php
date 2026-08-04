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
    @include('partials.figma-rightbar-header-actions')

    <div id="right-notifications" class="space-y-[13px] border-b-2 border-[#5a2a99] pb-[18px] text-[10px] text-[#a9a9a9]"></div>
    @include('partials.figma-notifications-script')
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
        'domainConnections' => $domainConnections,
        'connectionHealth' => $connectionHealth ?? [],
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
                    <span class="mb-[3px] text-[8px] font-semibold text-black/70">Landing Page</span>
                    <input placeholder="Landing page" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
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
                    <div class="mt-[10px] space-y-[4px] rounded border border-white/25 bg-black/20 px-[8px] py-[8px] text-[9px] text-white/85">
                        <div class="flex items-center justify-between gap-2">
                            <span>Health</span>
                            <span class="font-semibold uppercase" x-text="connectionHealth.health_status || '—'"></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span>Google account</span>
                            <span class="truncate font-medium" x-text="connectionHealth.email || 'Not connected'"></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span>Last sync</span>
                            <span x-text="formatHealthTime(connectionHealth.last_sync_at)"></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span>Tracking script</span>
                            <span x-text="connectionHealth.tracking_active ? 'Active' : 'Pending'"></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span>Events today</span>
                            <span x-text="connectionHealth.events_today ?? 0"></span>
                        </div>
                        @if ($primaryConnection)
                            <button type="button"
                                    class="mt-[6px] w-full rounded border border-white/40 bg-white/10 px-[8px] py-[5px] text-[9px] font-semibold text-white hover:bg-white/20"
                                    @click="testGoogleHealth()">
                                Test connection health
                            </button>
                        @endif
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
                    <div class="platform-requirement-layout flex items-center justify-center gap-[18px] xl:gap-[24px]">
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
                                    @if ($mapping->account->time_zone)
                                        <span class="mt-[3px] inline-flex items-center gap-[4px] rounded-[4px] bg-[#6706B3]/10 px-[6px] py-[2px] text-[9px] font-medium text-[#4a0088]">
                                            <svg class="h-[10px] w-[10px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ \App\Support\UserTimezone::formatDisplay($mapping->account->time_zone) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="truncate px-[12px] py-[10px] text-[12px]" title="{{ $mapping->domain->hostname }}">
                                    <span class="block truncate">{{ $mapping->domain->hostname }}</span>
                                    @if ($mapping->account?->google_tag_id)
                                        <span class="mt-[2px] block truncate font-mono text-[10px] text-[#121212]/65">Tag: {{ $mapping->account->google_tag_id }}</span>
                                    @endif
                                    @if ($mapping->domain?->tag_connected)
                                        <span class="mt-[2px] inline-flex rounded bg-emerald-500/15 px-[5px] py-[1px] text-[9px] font-semibold text-emerald-700">Tracking verified</span>
                                    @else
                                        <span class="mt-[2px] inline-flex rounded bg-amber-500/15 px-[5px] py-[1px] text-[9px] font-semibold text-amber-800">Verify tracking</span>
                                    @endif
                                </td>
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

        <div class="mt-[16px] grid gap-[12px] xl:grid-cols-2">
            <section class="rounded-[10px] border border-[#6706B3] bg-[#121212] p-[16px]">
                <div class="mb-[12px] flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[18px] font-medium text-white">Tracking ID management</h2>
                        <p class="mt-[3px] text-[12px] text-white/60">Google Ads conversion / tag IDs linked to this account</p>
                    </div>
                </div>
                <div class="space-y-[8px]">
                    @forelse (($trackingIds ?? []) as $row)
                        <div class="flex flex-wrap items-center justify-between gap-[8px] rounded-[8px] border border-white/15 bg-[#6400B2]/25 px-[12px] py-[10px]">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-medium text-white">{{ $row['label'] }}</p>
                                <p class="truncate text-[11px] text-white/55">Customer: {{ $row['customer_id'] }}</p>
                            </div>
                            <div class="flex items-center gap-[8px]">
                                <code class="rounded bg-black/40 px-[8px] py-[4px] font-mono text-[11px] text-white/90">{{ $row['google_tag_id'] ?: '—' }}</code>
                                @if (! empty($row['google_tag_id']))
                                    <button type="button" class="rounded border border-white/25 px-[8px] py-[4px] text-[10px] text-white/85 hover:bg-white/10"
                                            @click="copyKeyText(@js($row['google_tag_id']))">Copy</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="rounded-[8px] border border-white/10 bg-white/5 px-[12px] py-[14px] text-center text-[12px] text-white/55">Connect Google Ads to manage tracking IDs.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[10px] border border-[#6706B3] bg-[#121212] p-[16px]">
                <div class="mb-[12px] flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[18px] font-medium text-white">Sync history &amp; logs</h2>
                        <p class="mt-[3px] text-[12px] text-white/60">OAuth, account sync, domain link, and health checks</p>
                    </div>
                    <button type="button" class="rounded border border-white/25 px-[8px] py-[4px] text-[10px] text-white/85 hover:bg-white/10" @click="refreshSyncLogs()">Refresh</button>
                </div>
                <div class="max-h-[280px] space-y-[6px] overflow-y-auto promotix-slim-scroll">
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
        connectionHealth: config.connectionHealth || {},
        syncLogs: config.syncLogs || [],
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
        formatHealthTime(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '—';
            return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
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
