@extends('layouts.admin')

@section('title', 'Detection Panel')
@section('subtitle', 'Per-domain detection rules and audience exclusions')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="Detection Panel" subtitle="Per-domain rules for bot, malicious, and suspicious traffic">
            <x-slot:actions>
                <x-ui.button variant="outline" size="sm" href="{{ route('paid-marketing.dashboard') }}">Dashboard</x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('integrations') }}">Platform Integrate</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="brand-pill brand-pill-success">{{ session('status') }}</div>
        @endif

        {{-- Domain selector --}}
        <x-ui.card>
            <form method="GET" action="{{ route('paid-marketing.detection-settings') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[260px] flex-1">
                    <label class="brand-label mb-1.5">Domain</label>
                    <select name="domain_id" onchange="this.form.submit()" class="brand-select">
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}" @selected($domain && $domain->id === $d->id)>{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </x-ui.card>

        @if ($domain && $settings)
            <form method="POST" action="{{ route('paid-marketing.detection-settings.update', $domain) }}" class="space-y-6">
                @csrf

                {{-- Bot + Malicious --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-ui.card title="Invalid Bot Activity" subtitle="How known bots are handled">
                        <select name="invalid_bot_action" class="brand-select">
                            <option value="block" @selected($settings->invalid_bot_action === 'block')>Block</option>
                            <option value="flag" @selected($settings->invalid_bot_action === 'flag')>Flag</option>
                            <option value="allow" @selected($settings->invalid_bot_action === 'allow')>Allow</option>
                        </select>
                    </x-ui.card>

                    <x-ui.card title="Invalid Malicious Activity" subtitle="How malicious traffic is handled">
                        <select name="invalid_malicious_action" class="brand-select">
                            <option value="block" @selected($settings->invalid_malicious_action === 'block')>Block</option>
                            <option value="flag" @selected($settings->invalid_malicious_action === 'flag')>Flag</option>
                            <option value="allow" @selected($settings->invalid_malicious_action === 'allow')>Allow</option>
                        </select>
                    </x-ui.card>
                </div>

                {{-- Suspicious matrix --}}
                @php $matrix = $settings->suspicious_matrix ?? []; @endphp
                <x-ui.card title="Suspicious Activity Matrix" subtitle="Allow / flag / block based on traffic source signals">
                    <label class="mb-4 inline-flex items-center gap-2 text-sm text-night-100">
                        <input type="checkbox" name="suspicious_enabled" value="1" @checked($settings->suspicious_enabled)
                               class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                        Suspicious Activity enabled
                    </label>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        @php
                            $rules = [
                                ['vpn',                  'VPN',                  $matrix['vpn'] ?? 'allow', 'allow'],
                                ['proxy',                'Proxy',                $matrix['proxy'] ?? 'block', 'block'],
                                ['data_center',          'Data Center',          $matrix['data_center'] ?? 'block', 'block'],
                                ['abnormal_rate_limit',  'Abnormal Rate Limit',  $matrix['abnormal_rate_limit'] ?? 'allow', 'allow'],
                            ];
                        @endphp
                        @foreach ($rules as [$key, $label, $current, $default])
                            <div>
                                <label class="brand-label mb-1.5">{{ $label }}</label>
                                <select name="suspicious_{{ $key }}" class="brand-select">
                                    <option value="allow" @selected($current === 'allow')>Allow</option>
                                    <option value="flag"  @selected($current === 'flag')>Flag</option>
                                    <option value="block" @selected($current === 'block')>Block</option>
                                </select>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                {{-- Marketing optimization --}}
                <x-ui.card title="Marketing Optimization Rules" subtitle="Session capture, geo-fencing, and allow lists">
                    <div class="space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-night-700 bg-night-900/60 px-4 py-3 text-sm text-night-100">
                            <span>Session recordings</span>
                            <input type="checkbox" name="session_recordings" value="1" @checked($settings->session_recordings)
                                   class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-night-700 bg-night-900/60 px-4 py-3 text-sm text-night-100">
                            <span>Frequency capping</span>
                            <input type="checkbox" name="frequency_capping" value="1" @checked($settings->frequency_capping)
                                   class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                        </label>

                        <div class="rounded-xl border border-night-700 bg-night-900/60 p-4">
                            <label class="flex items-center justify-between text-sm text-night-100">
                                <span class="font-medium">Out of Geo</span>
                                <input type="checkbox" name="out_of_geo_enabled" value="1" @checked($settings->out_of_geo_enabled)
                                       class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                            </label>
                            <input type="text" name="out_of_geo_countries"
                                   value="{{ implode(', ', $settings->out_of_geo_countries ?? []) }}"
                                   placeholder="Allowed countries (comma separated), e.g. US, UK, AE"
                                   class="brand-input mt-3">
                        </div>

                        <div class="rounded-xl border border-night-700 bg-night-900/60 p-4">
                            <label class="flex items-center justify-between text-sm text-night-100">
                                <span class="font-medium">Manually Defined Allow List</span>
                                <input type="checkbox" name="allow_list_enabled" value="1" @checked($settings->allow_list_enabled)
                                       class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                            </label>
                            <textarea name="allow_list_ips" rows="3"
                                      placeholder="Add up to 5 IPs or ranges, separated by spaces or commas"
                                      class="brand-input mt-3">{{ $settings->allow_list_ips }}</textarea>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Audience exclusion --}}
                <x-ui.card title="Audience Exclusion — Event Settings"
                           subtitle="When auto-exclusions are triggered based on detected threat groups">
                    <select name="audience_exclusion_event" class="brand-select">
                        <option value="exclude_all_threat_groups_auto" @selected($settings->audience_exclusion_event === 'exclude_all_threat_groups_auto')>[Default] Exclude all Threat Groups automatically</option>
                        <option value="exclude_bot_malicious_only" @selected($settings->audience_exclusion_event === 'exclude_bot_malicious_only')>Exclude only Bot & Malicious Threat Groups</option>
                        <option value="disable_auto_exclusions" @selected($settings->audience_exclusion_event === 'disable_auto_exclusions')>Disable automatic exclusions</option>
                    </select>
                </x-ui.card>

                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="primary">Save changes</x-ui.button>
                </div>
            </form>
        @else
            <x-ui.card>
                <p class="py-6 text-center text-sm text-night-300">No domain found. Add a domain first.</p>
            </x-ui.card>
        @endif
    </div>
@endsection
