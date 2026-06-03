@extends('layouts.admin')

@section('title', 'Paid Marketing | Detection')

@section('content')
@php
    $matrix = $settings?->suspicious_matrix ?? [];
    $matrixRows = [
        ['vpn', 'VPN', $matrix['vpn'] ?? 'allow'],
        ['proxy', 'Proxy', $matrix['proxy'] ?? 'block'],
        ['data_center', 'Data Center', $matrix['data_center'] ?? 'block'],
        ['abnormal_rate_limit', 'Abnormal Rate Limit', $matrix['abnormal_rate_limit'] ?? 'block'],
    ];
@endphp

<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]">
    <section class="mx-auto w-full max-w-[1120px] px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px]">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Detection</span>
            </div>

            @if ($domain)
                <form method="GET" action="{{ route('paid-marketing.detection-settings') }}" class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                    <input type="hidden" name="domain_id" value="{{ $domain->id }}">
                    <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/70">Campaigns</span>
                        <select name="campaign" onchange="this.form.submit()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0" aria-label="Campaigns">
                            <option value="">All campaigns</option>
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign }}" @selected($selectedCampaign === $campaign)>{{ $campaign }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex w-[178px] flex-col justify-center px-[12px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/70">Filter by path</span>
                        <input type="search" name="path" value="{{ request('path') }}" placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0" aria-label="Filter by path" onkeydown="if(event.key==='Enter'){event.preventDefault();this.form.submit();}">
                    </label>
                    <button type="submit" class="figma-filter-action flex w-[34px] shrink-0 items-center justify-center text-white" aria-label="Apply filters">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                </form>
            @endif
        </div>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[#6400B2]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        @if ($domains->isEmpty())
            <div class="rounded-[10px] border border-[#6400B2] p-[28px] text-center text-[#a9a9a9]">No domain found. Add a domain first.</div>
        @else
            <form method="GET" action="{{ route('paid-marketing.detection-settings') }}" class="figma-detection-domain-bar">
                <label class="flex flex-wrap items-center gap-[12px]">
                    <span>Domain</span>
                    <select name="domain_id" onchange="this.form.submit()" class="figma-detection-domain-select">
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}" @selected($domain && $domain->id === $d->id)>{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($domain)
                    <span class="figma-detection-domain-host">{{ $domain->hostname }}</span>
                @endif
            </form>

            @if ($domain && $settings)
                <form method="POST" action="{{ route('paid-marketing.detection-settings.update', $domain) }}">
                    @csrf

                    <div class="figma-detection-layout">
                        {{-- Left: threat groups (Figma gray panel) --}}
                        <div class="figma-detection-left">
                            <div class="figma-detection-section">
                                <h2 class="figma-detection-section-title">Invalid Bot Activity</h2>
                                <div class="figma-detection-card figma-detection-card--row">
                                    <p class="figma-detection-card-text">Block non-Human tools that can be for malicious purpose such as false-clicks or other type of fraud or fake</p>
                                    <select name="invalid_bot_action" class="figma-detection-inline-select" aria-label="Invalid Bot Activity action">
                                        <option value="block" @selected($settings->invalid_bot_action === 'block')>Block</option>
                                        <option value="flag" @selected($settings->invalid_bot_action === 'flag')>Flag</option>
                                        <option value="allow" @selected($settings->invalid_bot_action === 'allow')>Allow</option>
                                    </select>
                                </div>
                            </div>

                            <div class="figma-detection-section">
                                <h2 class="figma-detection-section-title">Invalid Malicious Activity</h2>
                                <div class="figma-detection-card figma-detection-card--row figma-detection-card--row-tall">
                                    <p class="figma-detection-card-text">Block action performed by users with malicious intent Actions can include a set of excessive, non standard or under false identity</p>
                                    <select name="invalid_malicious_action" class="figma-detection-inline-select" aria-label="Invalid Malicious Activity action">
                                        <option value="block" @selected($settings->invalid_malicious_action === 'block')>Block</option>
                                        <option value="flag" @selected($settings->invalid_malicious_action === 'flag')>Flag</option>
                                        <option value="allow" @selected($settings->invalid_malicious_action === 'allow')>Allow</option>
                                    </select>
                                </div>
                            </div>

                            <div class="figma-detection-section">
                                <h2 class="figma-detection-section-title">Suspicious Activity</h2>
                                <div class="figma-detection-card figma-detection-card--suspicious">
                                    <div class="figma-detection-suspicious-intro">
                                        <p>Block Activity From User with Abnormal repetition or an activity which originates from suspicious source or routes such as visits generated by data center or user with VPN-based location spoofing</p>
                                        <x-figma-toggle
                                            variant="on-light"
                                            name="suspicious_enabled"
                                            value="1"
                                            :checked="$settings->suspicious_enabled"
                                            label-on="On"
                                            label-off="Off"
                                        />
                                    </div>
                                    <div class="figma-detection-matrix-divider" aria-hidden="true"></div>
                                    <div class="figma-detection-matrix-head">
                                        <span>Activity</span>
                                        <span>Audience Exclusion</span>
                                        <span>Edit</span>
                                    </div>
                                    @foreach ($matrixRows as [$key, $label, $current])
                                        <div class="figma-detection-matrix-row">
                                            <span>{{ $label }}</span>
                                            <select name="suspicious_{{ $key }}" class="figma-detection-inline-select w-full max-w-[140px]" aria-label="{{ $label }} exclusion">
                                                <option value="allow" @selected($current === 'allow')>Allow</option>
                                                <option value="flag" @selected($current === 'flag')>Flag</option>
                                                <option value="block" @selected($current === 'block')>Block</option>
                                            </select>
                                            <span class="figma-detection-matrix-edit">Edit</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="figma-detection-section">
                                <h2 class="figma-detection-section-title">Session recordings</h2>
                                <div class="figma-detection-card figma-detection-session">
                                    <p class="figma-detection-card-text">Allow session recordings to capture and review mouse movements for detailed analysis and observation. Currently available for Invalid Malicious Activity Threat Group only</p>
                                    <x-figma-toggle
                                        name="session_recordings"
                                        value="1"
                                        :checked="$settings->session_recordings"
                                        label-on="On"
                                        label-off="Off"
                                    />
                                </div>
                            </div>

                            <a href="{{ route('domains.index', ['add' => 1]) }}" class="figma-detection-add-domain">ADD DOMAIN</a>
                        </div>

                        {{-- Right: marketing optimization (Figma purple panel) --}}
                        <section class="figma-detection-right">
                            <div class="figma-detection-right-inner space-y-[40px]">
                                <div>
                                    <h2 class="figma-detection-right-title">Marketing Optimization Rules</h2>
                                    <div class="figma-detection-right-card">
                                        <p class="mb-[10px]">Ad fatigue occurs when your audience sees your ads too often which causes your campaigns to become less effective and prevents users from moving down the sales funnel. Using Frequency capping you can limit the number of times your ads appear to the same user</p>
                                        <div class="flex justify-end">
                                            <x-figma-toggle
                                                name="frequency_capping"
                                                value="1"
                                                :checked="$settings->frequency_capping"
                                                label-on="On"
                                                label-off="Off"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h2 class="figma-detection-right-title">Marketing Optimization Rules</h2>
                                    <div class="figma-detection-right-row">
                                        <span>Only allow click coming from the following Countries</span>
                                        <x-figma-toggle
                                            name="out_of_geo_enabled"
                                            value="1"
                                            :checked="$settings->out_of_geo_enabled"
                                            label-on="On"
                                            label-off="Off"
                                        />
                                    </div>
                                    <input name="out_of_geo_countries" value="{{ implode(', ', $settings->out_of_geo_countries ?? []) }}" placeholder="US, UK, AE" class="figma-input mt-[8px] h-[28px] text-[11px]">
                                </div>

                                <div>
                                    <div class="figma-detection-right-row">
                                        <span>Ensure predefined IPs will always be able to see your ads</span>
                                        <x-figma-toggle
                                            name="allow_list_enabled"
                                            value="1"
                                            :checked="$settings->allow_list_enabled"
                                            label-on="On"
                                            label-off="Off"
                                        />
                                    </div>
                                    <textarea name="allow_list_ips" rows="3" placeholder="Add IPs or ranges" class="figma-textarea mt-[8px] text-[11px]">{{ $settings->allow_list_ips }}</textarea>
                                </div>

                                <div>
                                    <h2 class="figma-detection-right-title">Audience Exclusion Event Settings</h2>
                                    <select name="audience_exclusion_event" class="figma-panel-select figma-panel-select-lg w-full">
                                        <option value="exclude_all_threat_groups_auto" @selected($settings->audience_exclusion_event === 'exclude_all_threat_groups_auto')>Exclude all Threat Groups automatically</option>
                                        <option value="exclude_bot_malicious_only" @selected($settings->audience_exclusion_event === 'exclude_bot_malicious_only')>Exclude only Bot and Malicious Threat Groups</option>
                                        <option value="disable_auto_exclusions" @selected($settings->audience_exclusion_event === 'disable_auto_exclusions')>Disable automatic exclusions</option>
                                    </select>
                                </div>

                                <div class="figma-detection-save-row flex justify-end pt-[4px]">
                                    <button type="submit" class="rounded-[6px] bg-white px-[22px] py-[9px] text-[13px] font-semibold text-[#6400B2] shadow-[0_8px_20px_rgba(0,0,0,.25)]">Save changes</button>
                                </div>
                            </div>
                        </section>
                    </div>
                </form>
            @endif
        @endif
    </section>
</div>
@endsection
