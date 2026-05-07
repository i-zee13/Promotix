@extends('layouts.admin')

@section('title', 'Paid Marketing — Detection Settings')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('paid-marketing.detailed') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detailed View</a>
            <a href="{{ route('integrations') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Platform Connections</a>
            <a href="{{ route('paid-marketing.detection-settings') }}" class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">Detection Settings</a>
        </div>

        <section class="rounded-xl border border-dark-border bg-dark-card p-6">
            <form method="GET" action="{{ route('paid-marketing.detection-settings') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[260px]">
                    <label class="mb-1 block text-xs text-gray-400">Domain</label>
                    <select name="domain_id" onchange="this.form.submit()" class="w-full rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}" @selected($domain && $domain->id === $d->id)>{{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </section>

        @if ($domain && $settings)
            <form method="POST" action="{{ route('paid-marketing.detection-settings.update', $domain) }}" class="space-y-6">
                @csrf
                <section class="rounded-xl border border-dark-border bg-dark-card p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-semibold text-white">Invalid Bot Activity</h2>
                        <p class="mt-1 text-sm text-gray-400">Choose how invalid bot activity is handled.</p>
                        <select name="invalid_bot_action" class="mt-3 rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                            <option value="block" @selected($settings->invalid_bot_action === 'block')>Block</option>
                            <option value="flag" @selected($settings->invalid_bot_action === 'flag')>Flag</option>
                            <option value="allow" @selected($settings->invalid_bot_action === 'allow')>Allow</option>
                        </select>
                    </div>

                    <div>
                        <h2 class="text-base font-semibold text-white">Invalid Malicious Activity</h2>
                        <p class="mt-1 text-sm text-gray-400">Choose how invalid malicious traffic is handled.</p>
                        <select name="invalid_malicious_action" class="mt-3 rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                            <option value="block" @selected($settings->invalid_malicious_action === 'block')>Block</option>
                            <option value="flag" @selected($settings->invalid_malicious_action === 'flag')>Flag</option>
                            <option value="allow" @selected($settings->invalid_malicious_action === 'allow')>Allow</option>
                        </select>
                    </div>

                    @php $matrix = $settings->suspicious_matrix ?? []; @endphp
                    <div class="space-y-3">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-200">
                            <input type="checkbox" name="suspicious_enabled" value="1" @checked($settings->suspicious_enabled) class="rounded border-dark-border bg-dark text-accent focus:ring-accent">
                            Suspicious Activity enabled
                        </label>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-xs text-gray-400">VPN</label>
                                <select name="suspicious_vpn" class="w-full rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                                    <option value="allow" @selected(($matrix['vpn'] ?? 'allow') === 'allow')>Allow</option>
                                    <option value="flag" @selected(($matrix['vpn'] ?? '') === 'flag')>Flag</option>
                                    <option value="block" @selected(($matrix['vpn'] ?? '') === 'block')>Block</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-400">Proxy</label>
                                <select name="suspicious_proxy" class="w-full rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                                    <option value="allow" @selected(($matrix['proxy'] ?? '') === 'allow')>Allow</option>
                                    <option value="flag" @selected(($matrix['proxy'] ?? '') === 'flag')>Flag</option>
                                    <option value="block" @selected(($matrix['proxy'] ?? 'block') === 'block')>Block</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-400">Data Center</label>
                                <select name="suspicious_data_center" class="w-full rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                                    <option value="allow" @selected(($matrix['data_center'] ?? '') === 'allow')>Allow</option>
                                    <option value="flag" @selected(($matrix['data_center'] ?? '') === 'flag')>Flag</option>
                                    <option value="block" @selected(($matrix['data_center'] ?? 'block') === 'block')>Block</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-400">Abnormal Rate Limit</label>
                                <select name="suspicious_abnormal_rate_limit" class="w-full rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                                    <option value="allow" @selected(($matrix['abnormal_rate_limit'] ?? 'allow') === 'allow')>Allow</option>
                                    <option value="flag" @selected(($matrix['abnormal_rate_limit'] ?? '') === 'flag')>Flag</option>
                                    <option value="block" @selected(($matrix['abnormal_rate_limit'] ?? '') === 'block')>Block</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-dark-border bg-dark-card p-6 space-y-4">
                    <h2 class="text-base font-semibold text-white">Marketing Optimization Rules</h2>

                    <label class="flex items-center justify-between rounded-xl border border-dark-border bg-dark p-3 text-sm text-gray-200">
                        <span>Session recordings</span>
                        <input type="checkbox" name="session_recordings" value="1" @checked($settings->session_recordings) class="rounded border-dark-border bg-dark text-accent focus:ring-accent">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-dark-border bg-dark p-3 text-sm text-gray-200">
                        <span>Frequency capping</span>
                        <input type="checkbox" name="frequency_capping" value="1" @checked($settings->frequency_capping) class="rounded border-dark-border bg-dark text-accent focus:ring-accent">
                    </label>

                    <div class="rounded-xl border border-dark-border bg-dark p-3">
                        <label class="flex items-center justify-between text-sm text-gray-200">
                            <span>Out of Geo</span>
                            <input type="checkbox" name="out_of_geo_enabled" value="1" @checked($settings->out_of_geo_enabled) class="rounded border-dark-border bg-dark text-accent focus:ring-accent">
                        </label>
                        <input
                            type="text"
                            name="out_of_geo_countries"
                            value="{{ implode(', ', $settings->out_of_geo_countries ?? []) }}"
                            placeholder="Allowed countries (comma separated), e.g. US, UK, AE"
                            class="mt-2 w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none"
                        >
                    </div>

                    <div class="rounded-xl border border-dark-border bg-dark p-3">
                        <label class="flex items-center justify-between text-sm text-gray-200">
                            <span>Manually Defined Allow List</span>
                            <input type="checkbox" name="allow_list_enabled" value="1" @checked($settings->allow_list_enabled) class="rounded border-dark-border bg-dark text-accent focus:ring-accent">
                        </label>
                        <textarea
                            name="allow_list_ips"
                            rows="3"
                            placeholder="Add up to 5 IPs or ranges, separated by spaces or commas"
                            class="mt-2 w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none"
                        >{{ $settings->allow_list_ips }}</textarea>
                    </div>
                </section>

                <section class="rounded-xl border border-dark-border bg-dark-card p-6">
                    <h2 class="text-base font-semibold text-white">Audience Exclusion - Event Settings</h2>
                    <p class="mt-1 text-sm text-gray-400">Choose when auto-exclusions are triggered based on detected threat groups.</p>
                    <select name="audience_exclusion_event" class="mt-3 w-full rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <option value="exclude_all_threat_groups_auto" @selected($settings->audience_exclusion_event === 'exclude_all_threat_groups_auto')>[Default] Exclude all Threat Groups automatically</option>
                        <option value="exclude_bot_malicious_only" @selected($settings->audience_exclusion_event === 'exclude_bot_malicious_only')>Exclude only Bot & Malicious Threat Groups</option>
                        <option value="disable_auto_exclusions" @selected($settings->audience_exclusion_event === 'disable_auto_exclusions')>Disable automatic exclusions</option>
                    </select>
                </section>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-accent px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-dark min-w-[120px]">
                        Save
                    </button>
                </div>
            </form>
        @else
            <div class="rounded-xl border border-dark-border bg-dark-card px-4 py-8 text-center text-sm text-gray-400">No domain found. Add a domain first.</div>
        @endif
    </div>
@endsection

