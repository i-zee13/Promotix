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

        <form method="GET" action="{{ route('paid-marketing.detection-settings') }}" class="mb-[12px] rounded-[10px] border border-white/25 bg-[#6400B2] px-[28px] py-[23px]">
            <label class="flex flex-col gap-[10px] text-[15px] font-semibold text-white sm:flex-row sm:items-center">
                <span>Domain</span>
                <select name="domain_id" onchange="this.form.submit()" class="figma-select h-[32px] w-full max-w-[278px] appearance-none rounded-[4px] border border-white/20 bg-[#f4eefb] px-[14px] py-[5px] text-[14px] font-normal leading-normal text-[#6400B2] focus:ring-[#9a1aff]/40">
                    @foreach ($domains as $d)
                        <option value="{{ $d->id }}" @selected($domain && $domain->id === $d->id)>{{ $d->hostname }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        @if ($domain && $settings)
            <form method="POST" action="{{ route('paid-marketing.detection-settings.update', $domain) }}">
                @csrf

                <div class="grid gap-[10px] xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <section class="max-h-[590px] overflow-y-auto rounded-[8px] bg-[#d9d9d9] p-[36px] pr-[26px] text-[#101010]">
                        <div class="mx-auto max-w-[390px] space-y-[16px]">
                            <div>
                                <h2 class="mb-[8px] text-center text-[13px] font-bold">Invalid Bot Activity</h2>
                                <div class="rounded-[6px] bg-[#101010] px-[14px] py-[12px] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <p class="mb-[10px]">Block non-Human tools that can be for malicious purpose such as false-clicks or other type of fraud or fake</p>
                                    <select name="invalid_bot_action" class="h-[26px] w-full rounded-[3px] border border-white/20 bg-black px-[8px] text-[11px] text-white focus:ring-[#6400B2]">
                                        <option value="block" @selected($settings->invalid_bot_action === 'block')>Block</option>
                                        <option value="flag" @selected($settings->invalid_bot_action === 'flag')>Flag</option>
                                        <option value="allow" @selected($settings->invalid_bot_action === 'allow')>Allow</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <h2 class="mb-[8px] text-center text-[13px] font-bold">Invalid Malicious Activity</h2>
                                <div class="rounded-[6px] bg-[#101010] px-[14px] py-[12px] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <p class="mb-[10px]">Block action performed by users with malicious intent Actions can include a set of excessive, non standard or under false identity</p>
                                    <select name="invalid_malicious_action" class="h-[26px] w-full rounded-[3px] border border-white/20 bg-black px-[8px] text-[11px] text-white focus:ring-[#6400B2]">
                                        <option value="block" @selected($settings->invalid_malicious_action === 'block')>Block</option>
                                        <option value="flag" @selected($settings->invalid_malicious_action === 'flag')>Flag</option>
                                        <option value="allow" @selected($settings->invalid_malicious_action === 'allow')>Allow</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <h2 class="mb-[8px] text-center text-[13px] font-bold">Suspicious Activity</h2>
                                <div class="rounded-[6px] bg-[#101010] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <div class="px-[14px] py-[10px]">
                                        <p>Block Activity From User with Abnormal repetition or an activity which originates from suspicious source or routes such as visits generated by data center or user with VPN-based location spoofing</p>
                                        <label class="mt-[8px] flex items-center justify-between">
                                            <span>Suspicious Activity enabled</span>
                                            <input type="checkbox" name="suspicious_enabled" value="1" @checked($settings->suspicious_enabled) class="rounded border-white/30 bg-black text-[#6400B2] focus:ring-[#6400B2]">
                                        </label>
                                    </div>
                                    <div class="border-t border-white/20">
                                        <div class="grid grid-cols-[1fr_1fr_58px] px-[10px] py-[6px] text-[12px] text-white">
                                            <span>Activity</span>
                                            <span>Audience Exclusion</span>
                                            <span>Edit</span>
                                        </div>
                                        @foreach ($matrixRows as [$key, $label, $current])
                                            <div class="grid grid-cols-[1fr_1fr_58px] items-center px-[10px] py-[6px] text-[12px]">
                                                <span class="font-semibold text-white">{{ $label }}</span>
                                                <select name="suspicious_{{ $key }}" class="h-[24px] rounded border-0 bg-transparent p-0 text-[12px] font-semibold text-[#d9d9d9] focus:ring-0">
                                                    <option class="text-black" value="allow" @selected($current === 'allow')>Allow</option>
                                                    <option class="text-black" value="flag" @selected($current === 'flag')>Flag</option>
                                                    <option class="text-black" value="block" @selected($current === 'block')>Block</option>
                                                </select>
                                                <span class="text-right text-white/80">Edit</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h2 class="mb-[8px] text-center text-[13px] font-bold">Session recordings</h2>
                                <label class="flex items-center justify-between rounded-[6px] bg-[#101010] px-[14px] py-[12px] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <span>Allow session recordings to capture and review mouse movements for detailed analysis and observation. Currently available for Invalid Malicious Activity Threat Group only</span>
                                    <input type="checkbox" name="session_recordings" value="1" @checked($settings->session_recordings) class="ml-[8px] rounded border-white/30 bg-black text-[#6400B2] focus:ring-[#6400B2]">
                                </label>
                            </div>

                            <div>
                                <h2 class="mb-[8px] text-center text-[13px] font-bold">Frequency capping</h2>
                                <label class="flex items-center justify-between rounded-[6px] bg-[#101010] px-[14px] py-[12px] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <span>Control repeated exposure to suspicious visitors across your campaigns.</span>
                                    <input type="checkbox" name="frequency_capping" value="1" @checked($settings->frequency_capping) class="ml-[8px] rounded border-white/30 bg-black text-[#6400B2] focus:ring-[#6400B2]">
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[8px] border border-white/25 bg-[#6400B2] p-[42px] text-white">
                        <div class="mx-auto max-w-[392px] space-y-[48px] pt-[6px]">
                            <div>
                                <h2 class="mb-[12px] text-center text-[13px] font-medium">Marketing Optimization Rules</h2>
                                <label class="flex items-center justify-between rounded-[8px] bg-[#101010] px-[14px] py-[12px] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <span>Ad fatigue occurs when your audience sees your ads too often which causes your campaigns to become less effective and prevents users from moving down the sales funnel. Using Frequency capping you can limit the number of times your ads appear to the same user</span>
                                    <span class="ml-[10px] flex h-[12px] w-[30px] items-center rounded-full bg-[#d9d9d9] p-[2px]"><span class="h-[8px] w-[8px] rounded-full bg-[#101010] {{ $settings->frequency_capping ? 'translate-x-[18px]' : '' }}"></span></span>
                                </label>
                            </div>

                            <div>
                                <h2 class="mb-[12px] text-center text-[13px] font-medium">Marketing Optimization Rules</h2>
                                <label class="flex items-center justify-between rounded-[8px] bg-[#101010] px-[14px] py-[10px] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <span>Only allow click coming from the following Countries</span>
                                    <input type="checkbox" name="out_of_geo_enabled" value="1" @checked($settings->out_of_geo_enabled) class="ml-[10px] rounded border-white/30 bg-black text-white focus:ring-white">
                                </label>
                                <input name="out_of_geo_countries" value="{{ implode(', ', $settings->out_of_geo_countries ?? []) }}" placeholder="US, UK, AE" class="mt-[8px] h-[28px] w-full rounded-[5px] border border-white/25 bg-[#101010] px-[10px] text-[11px] text-white placeholder:text-white/40 focus:ring-white/40">
                            </div>

                            <div>
                                <label class="flex items-center justify-between rounded-[8px] bg-[#101010] px-[14px] py-[12px] text-[11px] leading-[1.25] text-[#d9d9d9]">
                                    <span>Ensure predefined IPs will always be able to see your ads</span>
                                    <input type="checkbox" name="allow_list_enabled" value="1" @checked($settings->allow_list_enabled) class="ml-[10px] rounded border-white/30 bg-black text-white focus:ring-white">
                                </label>
                                <textarea name="allow_list_ips" rows="3" placeholder="Add IPs or ranges" class="mt-[8px] w-full rounded-[5px] border border-white/25 bg-[#101010] px-[10px] py-[8px] text-[11px] text-white placeholder:text-white/40 focus:ring-white/40">{{ $settings->allow_list_ips }}</textarea>
                            </div>

                            <div>
                                <h2 class="mb-[12px] text-center text-[13px] font-medium">Audience Exclusion Event Settings</h2>
                                <select name="audience_exclusion_event" class="h-[34px] w-full rounded-[5px] border border-white/25 bg-[#101010] px-[10px] text-[12px] text-white focus:ring-white/40">
                                    <option value="exclude_all_threat_groups_auto" @selected($settings->audience_exclusion_event === 'exclude_all_threat_groups_auto')>Exclude all Threat Groups automatically</option>
                                    <option value="exclude_bot_malicious_only" @selected($settings->audience_exclusion_event === 'exclude_bot_malicious_only')>Exclude only Bot and Malicious Threat Groups</option>
                                    <option value="disable_auto_exclusions" @selected($settings->audience_exclusion_event === 'disable_auto_exclusions')>Disable automatic exclusions</option>
                                </select>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-[6px] bg-white px-[22px] py-[9px] text-[13px] font-semibold text-[#6400B2] shadow-[0_8px_20px_rgba(0,0,0,.25)]">Save changes</button>
                            </div>
                        </div>
                    </section>
                </div>
            </form>
        @else
            <div class="rounded-[10px] border border-[#6400B2] p-[28px] text-center text-[#a9a9a9]">No domain found. Add a domain first.</div>
        @endif
    </section>
</div>
@endsection
