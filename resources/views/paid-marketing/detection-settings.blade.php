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

            @if ($domains->isNotEmpty())
                <form method="GET" action="{{ route('paid-marketing.detection-settings') }}" class="figma-filter-bar flex h-[54px] w-full max-w-[336px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                    <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/70">Domains</span>
                        <select name="domain_id" onchange="this.form.submit()" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0" aria-label="Domains">
                            @foreach ($domains as $d)
                                <option value="{{ $d->id }}" @selected($domain && $domain->id === $d->id)>#{{ $d->id }} · {{ $d->hostname }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex w-[178px] flex-col justify-center px-[12px]">
                        <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/70">Filter by path</span>
                        <input type="search" name="path" value="{{ request('path') }}" placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0" aria-label="Filter by path" onkeydown="if(event.key==='Enter'){event.preventDefault();this.form.submit();}">
                    </label>
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
                            <option value="{{ $d->id }}" @selected($domain && $domain->id === $d->id)>#{{ $d->id }} · {{ $d->hostname }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($domain)
                    <span class="figma-detection-domain-host">#{{ $domain->id }} · {{ $domain->hostname }}</span>
                @endif
            </form>

            @if ($domain && $settings)
            @php
                $geoAudienceRules = $settings->out_of_geo_audience['rules'] ?? null;
                $exclusionRules = array_merge(
                    (new \App\Services\GoogleAudienceExclusionService())->defaultRules(),
                    is_array($settings->google_exclusion_rules) ? $settings->google_exclusion_rules : []
                );
                if (! is_array($geoAudienceRules) || $geoAudienceRules === []) {
                    $geoAudienceRules = collect($settings->out_of_geo_countries ?? [])
                        ->map(fn ($c) => ['country' => $c, 'state' => null, 'city' => null])
                        ->values()
                        ->all();
                }
            @endphp
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
                                    </div>
                                    @foreach ($matrixRows as [$key, $label, $current])
                                        <div class="figma-detection-matrix-row">
                                            <span>{{ $label }}</span>
                                            <select name="suspicious_{{ $key }}" class="figma-detection-inline-select w-full max-w-[140px]" aria-label="{{ $label }} exclusion">
                                                <option value="allow" @selected($current === 'allow')>Allow</option>
                                                <option value="flag" @selected($current === 'flag')>Flag</option>
                                                <option value="block" @selected($current === 'block')>Block</option>
                                            </select>
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
                                    <div x-data="geoAudiencePicker({{ json_encode(['rules' => $geoAudienceRules, 'countries' => $geoCountries, 'endpoints' => $geoEndpoints]) }})" x-init="init()">
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
                                    <input type="hidden" name="out_of_geo_audience" :value="jsonValue">
                                    <div class="mt-[8px] space-y-[8px] rounded-[8px] border border-white/15 bg-black/20 p-[10px]">
                                        <div class="flex flex-wrap items-end gap-[8px]">
                                        @include('paid-marketing.partials.geo-audience-comboboxes')
                                            <button type="button" @click="addRule()" class="h-[32px] rounded-[6px] bg-white px-[12px] text-[11px] font-semibold text-[#6400B2]">Add</button>
                                        </div>
                                        <template x-if="rules.length">
                                            <div class="space-y-[4px]">
                                                <template x-for="(rule, idx) in rules" :key="idx">
                                                    <div class="flex items-center justify-between rounded-[6px] bg-white/10 px-[8px] py-[6px] text-[11px] text-white">
                                                        <span x-text="ruleLabel(rule)"></span>
                                                        <button type="button" class="text-white/60 hover:text-white" @click="removeRule(idx)">×</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <p x-show="!rules.length" class="text-[10px] text-white/50">No audience locations added yet.</p>
                                    </div>
                                    </div>
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

                                    <div class="mt-[12px] rounded-[8px] border border-white/15 bg-black/20 p-[12px] space-y-[10px]" x-data="{ open: {{ $settings->audience_exclusion_event !== 'disable_auto_exclusions' ? 'true' : 'false' }} }">
                                        <div class="flex items-center justify-between gap-[8px]">
                                            <span class="text-[12px] text-white">Google Ads exclusion filters</span>
                                            <x-figma-toggle name="google_exclusion_enabled" value="1" :checked="$exclusionRules['enabled'] ?? true" label-on="On" label-off="Off" />
                                        </div>
                                        <div class="grid grid-cols-1 gap-[8px] sm:grid-cols-2">
                                            <label class="flex items-center gap-[8px] text-[11px] text-white/90">
                                                <input type="hidden" name="google_exclude_invalid" value="0">
                                                <input type="checkbox" name="google_exclude_invalid" value="1" class="rounded border-white/30" @checked($exclusionRules['exclude_invalid'] ?? true)>
                                                Exclude invalid IPs
                                            </label>
                                            <label class="flex items-center gap-[8px] text-[11px] text-white/90">
                                                <input type="hidden" name="google_exclude_malicious" value="0">
                                                <input type="checkbox" name="google_exclude_malicious" value="1" class="rounded border-white/30" @checked($exclusionRules['exclude_malicious'] ?? true)>
                                                Exclude malicious IPs
                                            </label>
                                            <label class="flex items-center gap-[8px] text-[11px] text-white/90">
                                                <input type="hidden" name="google_exclude_vpn" value="0">
                                                <input type="checkbox" name="google_exclude_vpn" value="1" class="rounded border-white/30" @checked($exclusionRules['exclude_vpn'] ?? true)>
                                                Exclude VPN
                                            </label>
                                            <label class="flex items-center gap-[8px] text-[11px] text-white/90">
                                                <input type="hidden" name="google_exclude_data_center" value="0">
                                                <input type="checkbox" name="google_exclude_data_center" value="1" class="rounded border-white/30" @checked($exclusionRules['exclude_data_center'] ?? true)>
                                                Exclude data center
                                            </label>
                                            <label class="flex items-center gap-[8px] text-[11px] text-white/90">
                                                <input type="hidden" name="google_exclude_proxy" value="0">
                                                <input type="checkbox" name="google_exclude_proxy" value="1" class="rounded border-white/30" @checked($exclusionRules['exclude_proxy'] ?? true)>
                                                Exclude proxy
                                            </label>
                                            <label class="flex items-center gap-[8px] text-[11px] text-white/90">
                                                <input type="hidden" name="google_exclude_rate_limit" value="0">
                                                <input type="checkbox" name="google_exclude_rate_limit" value="1" class="rounded border-white/30" @checked($exclusionRules['exclude_rate_limit'] ?? true)>
                                                Exclude rate limit
                                            </label>
                                            <label class="flex items-center gap-[8px] text-[11px] text-white/90 sm:col-span-2">
                                                <input type="hidden" name="google_exclude_out_of_geo" value="0">
                                                <input type="checkbox" name="google_exclude_out_of_geo" value="1" class="rounded border-white/30" @checked($exclusionRules['exclude_out_of_geo'] ?? true)>
                                                Exclude out-of-geo
                                            </label>
                                        </div>
                                        <p class="text-[10px] text-white/50">Checked threat types are queued to your Google Ads IP exclusion list when a visit is blocked.</p>
                                    </div>

                                    <div
                                        class="mt-[12px] rounded-[8px] border border-white/15 bg-black/20 p-[12px] space-y-[10px]"
                                        x-data="googleExclusionPanel(@js([
                                            'pushUrl' => route('paid-marketing.detection-settings.google-exclusion.push', $domain),
                                            'syncUrl' => route('paid-marketing.detection-settings.google-exclusion.sync', $domain),
                                            'csrf' => csrf_token(),
                                            'rows' => $ipExclusions,
                                        ]))"
                                    >
                                        <h3 class="text-[12px] font-semibold text-white">Google Ads IP exclusion (manual test)</h3>
                                        <p class="text-[10px] text-white/55">Add an IP directly to this domain's Google Ads campaign exclusion list. Use this to verify the connection before automatic blocking runs.</p>
                                        <div class="flex flex-wrap items-end gap-[8px]">
                                            <label class="min-w-[180px] flex-1">
                                                <span class="mb-[4px] block text-[10px] text-white/70">IP address</span>
                                                <input
                                                    type="text"
                                                    x-model="ip"
                                                    placeholder="203.0.113.50"
                                                    class="figma-textarea !mt-0 h-[36px] !py-[8px] text-[12px]"
                                                    @keydown.enter.prevent="pushIp()"
                                                >
                                            </label>
                                            <button
                                                type="button"
                                                class="rounded-[6px] bg-white px-[16px] py-[9px] text-[12px] font-semibold text-[#6400B2] disabled:opacity-50"
                                                :disabled="loading || !ip.trim()"
                                                @click="pushIp()"
                                            >
                                                Add to campaigns
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-[6px] border border-white/30 px-[14px] py-[9px] text-[12px] text-white disabled:opacity-50"
                                                :disabled="loading"
                                                @click="syncPending()"
                                            >
                                                Push all pending
                                            </button>
                                        </div>
                                        <p x-show="message" x-text="message" class="text-[11px]" :class="ok ? 'text-emerald-300' : 'text-rose-300'"></p>
                                        <div class="max-h-[160px] overflow-y-auto rounded-[6px] border border-white/10">
                                            <table class="w-full text-left text-[10px] text-white/85">
                                                <thead class="sticky top-0 bg-[#101010] text-white/60">
                                                    <tr>
                                                        <th class="px-[8px] py-[6px] font-normal">IP</th>
                                                        <th class="px-[8px] py-[6px] font-normal">Type</th>
                                                        <th class="px-[8px] py-[6px] font-normal">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-if="!rows.length">
                                                        <tr><td colspan="3" class="px-[8px] py-[8px] text-white/45">No blocked IPs queued yet.</td></tr>
                                                    </template>
                                                    <template x-for="row in rows" :key="row.ip + row.updated_at">
                                                        <tr class="border-t border-white/10">
                                                            <td class="px-[8px] py-[6px] font-mono" x-text="row.ip"></td>
                                                            <td class="px-[8px] py-[6px] capitalize" x-text="row.threat_group || '—'"></td>
                                                            <td class="px-[8px] py-[6px]">
                                                                <span
                                                                    class="rounded-[3px] px-[6px] py-[2px] text-[9px] uppercase"
                                                                    :class="{
                                                                        'bg-emerald-500/20 text-emerald-300': row.sync_status === 'synced',
                                                                        'bg-amber-500/20 text-amber-200': row.sync_status === 'pending',
                                                                        'bg-rose-500/20 text-rose-300': row.sync_status === 'failed',
                                                                        'bg-white/10 text-white/60': row.sync_status === 'skipped',
                                                                    }"
                                                                    x-text="row.sync_status"
                                                                ></span>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p class="text-[9px] text-white/40">CLI: <code class="text-white/60">php artisan google-ads:sync-ip-exclusions --list</code> · <code class="text-white/60">php artisan google-ads:sync-ip-exclusions --retry-failed</code></p>
                                    </div>
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

<script>
function googleExclusionPanel(config) {
    return {
        ip: '',
        rows: config.rows || [],
        pushUrl: config.pushUrl,
        syncUrl: config.syncUrl,
        csrf: config.csrf,
        loading: false,
        message: '',
        ok: true,
        async pushIp() {
            const ip = this.ip.trim();
            if (!ip || this.loading) return;
            this.loading = true;
            this.message = '';
            try {
                const res = await fetch(this.pushUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ip }),
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || (this.ok ? 'IP pushed.' : 'Push failed.');
                if (Array.isArray(data.rows)) this.rows = data.rows;
                if (this.ok) this.ip = '';
            } catch (e) {
                this.ok = false;
                this.message = 'Request failed. Check console or try again.';
            } finally {
                this.loading = false;
            }
        },
        async syncPending() {
            if (this.loading) return;
            this.loading = true;
            this.message = '';
            try {
                const res = await fetch(this.syncUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ limit: 100 }),
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || 'Sync finished.';
                if (Array.isArray(data.rows)) this.rows = data.rows;
            } catch (e) {
                this.ok = false;
                this.message = 'Sync request failed.';
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endsection
