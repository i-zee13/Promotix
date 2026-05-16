@extends('layouts.admin')

@section('title', 'Platform Integrate')

@php
    $googleConnected = $connections->isNotEmpty();
    $directConnected = $directAds->isNotEmpty();
    $requirementSteps = [
        ['label' => 'Step 1', 'done' => $googleConnected],
        ['label' => 'Step 2', 'done' => $accounts->isNotEmpty()],
        ['label' => 'Step 3', 'done' => $mappings->count() > 0],
        ['label' => 'Step 4', 'done' => $directConnected],
    ];
@endphp

@section('rightbar')
    <div class="mb-[22px] flex items-center justify-between">
        <p class="text-[18px] font-bold leading-none text-[#a9a9a9]">Digital Promotix</p>
        <button class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white">
            <svg class="h-[13px] w-[13px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 17a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
        </button>
    </div>

    <div class="mb-[24px] grid grid-cols-4 gap-[9px]">
        @foreach (['bell-notification', 'chat', 'share', 'more'] as $icon)
            <a href="{{ route('integrations') }}" class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white" aria-label="{{ $icon }}">
                @if ($icon === 'bell-notification')
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"/></svg>
                @elseif ($icon === 'chat')
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M4 5h16v11H8l-4 4V5z"/></svg>
                @elseif ($icon === 'share')
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M8 12h8M16 12l-4-4m4 4l-4 4"/></svg>
                @else
                    <svg class="h-[13px] w-[13px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 17a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
                @endif
            </a>
        @endforeach
    </div>

    <div class="space-y-[13px] border-b-2 border-[#5a2a99] pb-[18px] text-[10px] text-[#a9a9a9]">
        @foreach (['1 m paid traffic reached', '20 k block detections', 'Countries IP reviewed', 'Account is connected', 'Campaigns is live'] as $notice)
            <div class="flex items-center gap-[10px] border-b border-[#a9a9a9]/70 pb-[8px] last:border-b-0">
                <svg class="h-[14px] w-[14px] shrink-0 text-white/85" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M4 6h16v12H4z"/><path stroke-width="1.7" d="M4 7l8 6 8-6"/></svg>
                <span>{{ $notice }}</span>
            </div>
        @endforeach
    </div>
@endsection

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="platformIntegrations(@js([
    'csrf' => csrf_token(),
    'directStoreUrl' => url('/admin/integrations/direct-ads'),
    'directInitial' => $directAds->map(fn ($row) => [
        'id' => $row->id,
        'platform' => $row->platform,
        'account_label' => $row->account_label,
        'account_id' => $row->account_id,
        'tag_id' => $row->tag_id,
    ])->values(),
]))">
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
                        <div class="flex items-start justify-between">
                            <div class="flex gap-[18px]">
                                <div class="w-[90px] shrink-0">
                                    <div class="mb-[12px] flex h-[79px] w-[90px] items-center justify-center rounded bg-white">
                                        <svg class="h-[55px] w-[55px]" viewBox="0 0 48 48" aria-hidden="true">
                                            <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20c10 0 19-7.3 19-20 0-1.3-.1-2.3-.4-3.5z"/>
                                            <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                                            <path fill="#4CAF50" d="M24 44c5.1 0 9.8-1.9 13.3-5.2l-6.2-5.2C29.1 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                                            <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.6l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.3-.4-3.5z"/>
                                        </svg>
                                    </div>
                                    <p class="text-center text-[20px] font-medium leading-none text-white">Google</p>
                                    <div class="mx-auto mt-[8px] h-[15px] w-[72px] rounded-sm bg-black/55"></div>
                                </div>

                                <div class="space-y-[16px] pt-[8px]">
                                    <a href="{{ route('integrations.google.redirect') }}" class="flex h-[26px] w-[142px] items-center gap-[8px] rounded border border-white/95 bg-[#6706B3] px-[9px] text-[12px] font-normal text-white">
                                        @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[15px] w-[15px]'])
                                        Pixel Guard
                                    </a>
                                    <a href="{{ route('paid-marketing.detection-settings') }}" class="flex h-[26px] w-[142px] items-center gap-[8px] rounded border border-white/95 bg-[#6706B3] px-[9px] text-[12px] font-normal text-white">
                                        @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[15px] w-[15px]'])
                                        Audience Exclusion
                                    </a>
                                    <a href="{{ route('integrations.google.redirect') }}" class="flex h-[26px] w-[142px] items-center gap-[8px] rounded border border-white bg-white px-[9px] text-[12px] font-normal text-[#6706B3]">
                                        <span class="flex h-[17px] w-[17px] items-center justify-center rounded-full border border-[#6706B3] text-[12px]">+</span>
                                        Acc Account
                                    </a>
                                </div>
                            </div>
                            <span class="text-[24px] leading-none text-white">...</span>
                        </div>
                    </article>

                    <article class="min-h-[232px] rounded-[10px] border border-[#d9d9d9]/60 p-[18px]">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-[24px] pt-[34px]">
                                <svg class="h-[96px] w-[96px] shrink-0" viewBox="0 0 96 96" aria-hidden="true">
                                    <path fill="#4285F4" d="M52 14c5.2-3 11.9-1.2 14.9 4l22 38.1c3 5.2 1.2 11.9-4 14.9s-11.9 1.2-14.9-4L48 28.9c-3-5.2-1.2-11.9 4-14.9z"/>
                                    <path fill="#34A853" d="M8.4 67.4 30.5 29c3-5.2 9.7-7 14.9-4s7 9.7 4 14.9L27.3 78.2c-3 5.2-9.7 7-14.9 4s-7-9.6-4-14.8z"/>
                                    <circle cx="18" cy="73" r="13" fill="#FBBC04"/>
                                </svg>
                                <p class="text-[20px] font-medium leading-none text-white">Direct Ads</p>
                            </div>
                            <span class="text-[24px] leading-none text-white">...</span>
                        </div>

                        <form class="mt-[12px] grid gap-[9px] sm:grid-cols-[1fr_auto]" @submit.prevent="addDirectAds()">
                            <input x-model="directForm.account_id" placeholder="ID Tracking" class="h-[26px] rounded border border-white bg-white px-[8px] text-[12px] text-[#6706B3] placeholder:text-[#6706B3] focus:ring-[#6400B2]">
                            <button class="h-[26px] rounded border border-white bg-white px-[10px] text-[12px] text-[#6706B3]">Add</button>
                            <input x-model="directForm.tag_id" placeholder="sadsadsadsadsad" class="h-[26px] rounded border border-white bg-white px-[8px] text-[12px] text-[#6706B3] placeholder:text-[#6706B3] focus:ring-[#6400B2] sm:col-span-2">
                        </form>
                    </article>
                </div>
            </section>

            <div class="grid gap-[12px] sm:grid-cols-2 xl:grid-cols-1">
                <section class="rounded-[10px] bg-[#6706B3] p-[10px]">
                    <p class="mb-[12px] text-center text-[8px] uppercase text-white">Connection Status</p>
                    <div class="grid grid-cols-2 gap-[6px]">
                        <div class="rounded border border-white bg-[#606060]/55 p-[8px] text-center">
                            <div class="mx-auto mb-[8px] flex h-[50px] w-[50px] items-center justify-center rounded bg-white">
                                <svg class="h-[32px] w-[32px]" viewBox="0 0 48 48" aria-hidden="true">
                                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20c10 0 19-7.3 19-20 0-1.3-.1-2.3-.4-3.5z"/>
                                    <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                                    <path fill="#4CAF50" d="M24 44c5.1 0 9.8-1.9 13.3-5.2l-6.2-5.2C29.1 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.6l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.3-.4-3.5z"/>
                                </svg>
                            </div>
                            <div class="bg-white px-[4px] py-[2px] text-[8px] text-[#6706B3]">{{ $googleConnected ? 'Connected' : 'Not Connected' }}</div>
                        </div>
                        <div class="rounded border border-white bg-white/50 p-[8px] text-center">
                            <svg class="mx-auto mb-[8px] h-[50px] w-[50px]" viewBox="0 0 96 96" aria-hidden="true">
                                <path fill="#4285F4" d="M52 14c5.2-3 11.9-1.2 14.9 4l22 38.1c3 5.2 1.2 11.9-4 14.9s-11.9 1.2-14.9-4L48 28.9c-3-5.2-1.2-11.9 4-14.9z"/>
                                <path fill="#34A853" d="M8.4 67.4 30.5 29c3-5.2 9.7-7 14.9-4s7 9.7 4 14.9L27.3 78.2c-3 5.2-9.7 7-14.9 4s-7-9.6-4-14.8z"/>
                                <circle cx="18" cy="73" r="13" fill="#FBBC04"/>
                            </svg>
                            <div class="bg-white px-[4px] py-[2px] text-[8px] text-[#101010]">{{ $directConnected ? 'Connected' : 'Not Connected' }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[10px] bg-[#3c3c3c] p-[16px]">
                    <h2 class="mb-[20px] text-[16px] font-medium text-[#d9d9d9]">Connection Requirement</h2>
                    <div class="grid grid-cols-[84px_1fr] items-center gap-[18px]">
                        <div class="relative h-[84px] w-[84px] rounded-full border-[14px] border-[#d9d9d9] border-l-[#7a56a9] border-t-[#7a56a9]"></div>
                        <div class="space-y-[8px]">
                            @foreach ($requirementSteps as $step)
                                <div class="relative h-[15px] overflow-hidden rounded-full bg-[#d9d9d9]">
                                    <div class="absolute inset-y-[2px] left-[2px] rounded-full {{ $step['done'] ? 'w-[calc(100%-4px)] bg-[#7a56a9]' : 'w-[calc(62%-4px)] bg-[#838284]' }}"></div>
                                    <span class="absolute inset-0 flex items-center justify-center text-[8px] text-white/70">{{ $step['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <section class="mt-[20px] rounded-[10px] border border-[#6706B3] p-[16px]">
            <div class="mb-[26px]">
                <h2 class="text-[24px] font-medium text-white">Connected Platforms</h2>
                <p class="mt-[5px] text-[14px] font-medium text-white">All Accounts</p>
            </div>

            <form method="POST" action="{{ route('integrations.store-mapping') }}" class="mb-[14px] grid gap-[8px] rounded-[8px] border border-white/20 bg-[#6400B2]/35 p-[10px] lg:grid-cols-[1fr_1fr_150px_130px]">
                @csrf
                <select name="domain_id" required class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                    <option value="">Select domain</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                    @endforeach
                </select>
                <select name="google_ads_account_id" required class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                    <option value="">Select connected account</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->display_customer_id ?: $account->customer_id }} - {{ $account->google_tag_id ?: 'No tag ID' }}</option>
                    @endforeach
                </select>
                <select name="protection_type" class="figma-select h-[34px] rounded-[5px] border border-white/25 bg-[#101010] px-[8px] text-[12px] text-white focus:ring-[#6400B2]">
                    <option value="ip_blocking">IP Blocking</option>
                    <option value="pixel_guard">Pixel Guard</option>
                </select>
                <button class="rounded-[5px] bg-[#6706B3] px-[12px] text-[12px] font-semibold text-white">Link domain</button>
            </form>

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
                            <tr class="rounded-[5px] bg-[#d9d9d9] text-[#121212]">
                                <td class="rounded-l-[5px] px-[22px] py-[10px] text-[16px] font-medium">
                                    <span class="inline-flex items-center gap-[10px]">
                                        <svg class="h-[22px] w-[22px]" viewBox="0 0 48 48" aria-hidden="true">
                                            <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20c10 0 19-7.3 19-20 0-1.3-.1-2.3-.4-3.5z"/>
                                            <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                                            <path fill="#4CAF50" d="M24 44c5.1 0 9.8-1.9 13.3-5.2l-6.2-5.2C29.1 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                                            <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.6l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.3-.4-3.5z"/>
                                        </svg>
                                        Google
                                    </span>
                                </td>
                                <td class="px-[22px] py-[10px] text-[12px]">{{ $mapping->protection_type === 'pixel_guard' ? 'Pixel Guard' : 'Audience Exclusion' }}</td>
                                <td class="px-[22px] py-[10px] text-[12px]">{{ $mapping->account->display_customer_id ?: $mapping->account->customer_id }}</td>
                                <td class="px-[22px] py-[10px] text-[12px]">{{ $mapping->domain->hostname }}</td>
                                <td class="px-[22px] py-[10px] text-[12px] font-medium text-[#6706B3]">
                                    <a href="{{ route('paid-marketing.detection-settings', ['domain_id' => $mapping->domain_id]) }}" class="inline-flex items-center gap-[6px] hover:underline">
                                        @include('partials.sidebar-icon', ['name' => 'settings', 'class' => 'h-[18px] w-[18px]'])
                                        Campaign Settings
                                    </a>
                                </td>
                                <td class="rounded-r-[5px] px-[22px] py-[10px] text-right">
                                    <form method="POST" action="{{ route('integrations.destroy-mapping', $mapping) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-[#121212]/70 hover:text-[#6706B3]" aria-label="Remove">
                                            @include('partials.sidebar-icon', ['name' => 'trash', 'class' => 'h-[20px] w-[20px]'])
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
</div>

<script>
function platformIntegrations(config) {
    return {
        directList: config.directInitial || [],
        directForm: { platform: 'custom', account_label: 'Direct Ads', account_id: '', tag_id: '' },
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
