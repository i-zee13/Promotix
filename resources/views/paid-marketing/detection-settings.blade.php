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
                $googleGeoBlockRules = $settings->google_geo_block_audience['rules'] ?? [];
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
                if (! is_array($googleGeoBlockRules)) {
                    $googleGeoBlockRules = [];
                }
            @endphp
                <form method="POST" action="{{ route('paid-marketing.detection-settings.update', $domain) }}"
                      x-data="{ mode: @js($settings->control_mode ?? 'mixed') }">
                    @csrf
                    <input type="hidden" name="control_mode" :value="mode">

                    <div class="mb-[18px] grid grid-cols-1 gap-[10px] sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ([
                            'allow_countries' => ['Allow Countries', 'Only selected countries can open the landing page. Everyone else is denied.'],
                            'block_countries' => ['Block Countries', 'Selected countries cannot open the site and are pushed to Google Ads location exclusions.'],
                            'allow_ips' => ['Allow IPs', 'Listed IPs always bypass automated blocks (activity still logged).'],
                            'block_ips' => ['Block IPs', 'Listed IPs are denied before protected page content loads.'],
                        ] as $modeKey => [$modeTitle, $modeHelp])
                            <button type="button"
                                    @click="mode = '{{ $modeKey }}'"
                                    class="rounded-[10px] border px-[14px] py-[12px] text-left transition"
                                    :class="mode === '{{ $modeKey }}' ? 'border-[#6400B2] bg-[#6400B2]/25' : 'border-white/20 bg-[#101010] hover:border-white/40'">
                                <p class="text-[13px] font-semibold text-white">{{ $modeTitle }}</p>
                                <p class="mt-[6px] text-[10px] leading-snug text-white/55">{{ $modeHelp }}</p>
                                <p class="mt-[8px] text-[9px] font-semibold uppercase tracking-wide"
                                   :class="mode === '{{ $modeKey }}' ? 'text-[#c084fc]' : 'text-white/35'"
                                   x-text="mode === '{{ $modeKey }}' ? 'Selected mode' : 'Click to select'"></p>
                            </button>
                        @endforeach
                    </div>
                    <p class="mb-[16px] text-[11px] text-[#a9a9a9]">
                        Active mode: <span class="font-semibold text-white" x-text="mode.replace('_', ' ')"></span>.
                        Choose one primary control. You can still fine-tune the related lists below, then Save changes.
                    </p>

                    @php
                        $profileKey = $settings->detection_profile ?? 'standard';
                        $thr = $settings->detection_thresholds ?? [];
                        $profiles = $detectionProfiles ?? \App\Support\DetectionProfiles::catalog();
                    @endphp
                    <div class="mb-[18px] rounded-[10px] border border-amber-400/40 bg-amber-500/10 px-[14px] py-[12px] text-[11px] leading-relaxed text-amber-100/90">
                        <p class="font-semibold text-amber-100">Ad-platform limitation</p>
                        <p class="mt-[4px]">Website blocking can stop future site access, but it cannot guarantee Google Ads will not count the original ad click. Prefer Google Ads IP / location exclusions where eligible, and treat platform block stats separately from Google-reported invalid clicks.</p>
                    </div>

                    <div class="mb-[18px] space-y-[10px]">
                        <h2 class="figma-detection-section-title">Detection profile</h2>
                        <div class="grid gap-[10px] sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($profiles as $pkey => $pinfo)
                                <label class="cursor-pointer rounded-[10px] border px-[12px] py-[12px] transition {{ $profileKey === $pkey ? 'border-[#6400B2] bg-[#6400B2]/25' : 'border-white/20 bg-[#101010]' }}">
                                    <div class="flex items-start gap-[8px]">
                                        <input type="radio" name="detection_profile" value="{{ $pkey }}" class="mt-[3px]" @checked($profileKey === $pkey)>
                                        <div>
                                            <p class="text-[13px] font-semibold text-white">{{ $pinfo['label'] }}</p>
                                            <p class="mt-[4px] text-[10px] text-white/55">{{ $pinfo['summary'] }}</p>
                                            <p class="mt-[6px] text-[9px] text-white/40">FP risk: {{ $pinfo['false_positive_risk'] }} · {{ $pinfo['recommended'] }}</p>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <div class="grid gap-[10px] rounded-[10px] border border-white/15 bg-[#101010] p-[12px] sm:grid-cols-4">
                            <label class="text-[10px] text-white/60">
                                Rapid window (sec)
                                <input type="number" name="rapid_window_seconds" min="30" max="600" value="{{ $thr['rapid_window_seconds'] ?? 120 }}" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                            </label>
                            <label class="text-[10px] text-white/60">
                                Flag at prior clicks
                                <input type="number" name="rapid_flag_at" min="1" max="10" value="{{ $thr['rapid_flag_at'] ?? 1 }}" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                            </label>
                            <label class="text-[10px] text-white/60">
                                Block at prior clicks
                                <input type="number" name="rapid_block_at" min="1" max="20" value="{{ $thr['rapid_block_at'] ?? 2 }}" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                            </label>
                            <label class="text-[10px] text-white/60">
                                Daily valid click limit
                                <input type="number" name="daily_valid_click_limit" min="1" max="20" value="{{ $thr['daily_valid_click_limit'] ?? 2 }}" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                            </label>
                        </div>
                        <label class="flex items-center gap-[10px] text-[11px] text-white/70">
                            Fail-safe when detection is unavailable
                            <select name="fail_mode" class="rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                                <option value="open" @selected(($settings->fail_mode ?? 'open') === 'open')>Fail open (allow)</option>
                                <option value="closed" @selected(($settings->fail_mode ?? 'open') === 'closed')>Fail closed (block)</option>
                            </select>
                        </label>
                        <div class="grid gap-[10px] rounded-[10px] border border-white/15 bg-[#101010] p-[12px] sm:grid-cols-2">
                            <label class="text-[10px] text-white/60">
                                Block response (BL-02)
                                <select name="block_response" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                                    <option value="hide" @selected(($settings->block_response ?? 'hide') === 'hide')>Hide page</option>
                                    <option value="blank" @selected(($settings->block_response ?? '') === 'blank')>Blank page</option>
                                    <option value="forbid" @selected(($settings->block_response ?? '') === 'forbid')>403 Forbidden screen</option>
                                    <option value="challenge" @selected(($settings->block_response ?? '') === 'challenge')>Challenge / CAPTCHA</option>
                                    <option value="redirect" @selected(($settings->block_response ?? '') === 'redirect')>Safe redirect</option>
                                </select>
                            </label>
                            <label class="text-[10px] text-white/60">
                                Redirect URL (when redirect selected)
                                <input type="url" name="block_redirect_url" value="{{ $settings->block_redirect_url }}" placeholder="https://example.com/safe" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                            </label>
                            <label class="text-[10px] text-white/60 sm:col-span-2">
                                Session recording retention (days)
                                <input type="number" name="recording_retention_days" min="1" max="3650" value="{{ $settings->recording_retention_days ?? 30 }}" class="mt-[4px] w-full max-w-[160px] rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                            </label>
                            <label class="text-[10px] text-white/60">
                                Geo rule scope
                                <select name="geo_rule_scope" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                                    <option value="domain" @selected(($settings->geo_rule_scope ?? 'domain') === 'domain')>This domain only</option>
                                    <option value="workspace" @selected(($settings->geo_rule_scope ?? '') === 'workspace')>Workspace defaults</option>
                                </select>
                            </label>
                            <label class="text-[10px] text-white/60 flex items-end gap-[8px]">
                                <input type="checkbox" name="save_workspace_geo" value="1" class="rounded border-white/30">
                                Save current geo rules as workspace defaults
                            </label>
                            <label class="text-[10px] text-white/60 flex items-center gap-[8px] sm:col-span-2">
                                <input type="hidden" name="consent_required" value="0">
                                <input type="checkbox" name="consent_required" value="1" @checked($settings->consent_required ?? false) class="rounded border-white/30">
                                Require consent banner before tracking (GDPR/CCPA)
                            </label>
                            <label class="text-[10px] text-white/60 sm:col-span-2">
                                Consent regions (ISO country codes, comma-separated; empty = all)
                                <input type="text" name="consent_regions" value="{{ implode(',', (array) ($settings->consent_regions ?? [])) }}" placeholder="DE,FR,GB" class="mt-[4px] w-full rounded border border-white/20 bg-black/40 px-[8px] py-[6px] text-[12px] text-white">
                            </label>
                            <label class="text-[10px] text-white/60 flex items-center gap-[8px] sm:col-span-2">
                                <input type="hidden" name="recording_mask_passwords" value="0">
                                <input type="checkbox" name="recording_mask_passwords" value="1" @checked($settings->recording_mask_passwords ?? true) class="rounded border-white/30">
                                Mask password and sensitive inputs in session recordings
                            </label>
                        </div>
                    </div>

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

                            <div class="figma-detection-section" x-data="ipListFileUpload('block_list_ips')">
                                <h2 class="figma-detection-section-title">Block IP addresses</h2>
                                <div class="figma-detection-card figma-detection-block-ips">
                                    <div class="figma-detection-block-ips-header">
                                        <p class="figma-detection-card-text">Always block these IP addresses from seeing your site and ads</p>
                                        <div class="figma-detection-block-ips-actions">
                                            <label class="figma-detection-block-ips-file">
                                                <input type="file" class="sr-only" accept=".txt,.csv,text/plain,text/csv" @change="onFile($event)">
                                                Choose file
                                            </label>
                                            <span class="figma-detection-block-ips-filename" x-text="fileName || ''"></span>
                                            <x-figma-toggle
                                                name="block_list_enabled"
                                                value="1"
                                                :checked="$settings->block_list_enabled"
                                                label-on="On"
                                                label-off="Off"
                                            />
                                        </div>
                                    </div>
                                    <p class="figma-detection-block-ips-hint">Upload .txt / .csv (one IP per line). Optional duration: <code>1.2.3.4 | 2m</code>, <code>1h</code>, <code>24h</code>, <code>7d</code>, or <code>permanent</code>. Expired entries are ignored automatically.</p>
                                    <div class="flex flex-wrap gap-[6px] mb-[8px]" x-data="{
                                        append(duration) {
                                            const el = document.getElementById('block_list_ips');
                                            if (!el) return;
                                            const lines = (el.value || '').split(/\r?\n/).map(l => l.trim()).filter(Boolean);
                                            const last = lines[lines.length - 1] || '';
                                            if (!last || last.includes('|') || last.includes('#expires=')) {
                                                el.focus();
                                                return;
                                            }
                                            lines[lines.length - 1] = last + ' | ' + duration;
                                            el.value = lines.join('\n');
                                            el.dispatchEvent(new Event('input'));
                                        }
                                    }">
                                        <span class="text-[10px] text-white/50 self-center">Set last IP duration:</span>
                                        <template x-for="d in ['2m','1h','24h','7d','permanent']" :key="d">
                                            <button type="button" class="rounded border border-white/20 px-[6px] py-[2px] text-[10px] text-white/80 hover:bg-white/10" @click="append(d)" x-text="d"></button>
                                        </template>
                                    </div>
                                    <textarea id="block_list_ips" name="block_list_ips" rows="3" placeholder="Add IPs (e.g. 103.207.87.2 | 24h or 216.67.176.*)" class="figma-detection-block-ips-textarea">{{ $settings->block_list_ips }}</textarea>
                                </div>
                            </div>

                            <div class="figma-detection-section" x-data="geoAudiencePicker({{ json_encode(['rules' => $googleGeoBlockRules, 'countries' => $geoCountries, 'endpoints' => $geoEndpoints]) }})" x-init="init()">
                                <h2 class="figma-detection-section-title">Block countries from Google Ads</h2>
                                <div class="figma-detection-card space-y-[10px]">
                                    <div class="flex flex-wrap items-start justify-between gap-[10px]">
                                        <p class="figma-detection-card-text max-w-[520px]">Selected countries / regions / cities are pushed as Google Ads location exclusions so ads do not show in those geos.</p>
                                        <x-figma-toggle
                                            variant="on-light"
                                            name="google_geo_block_enabled"
                                            value="1"
                                            :checked="$settings->google_geo_block_enabled"
                                            label-on="On"
                                            label-off="Off"
                                        />
                                    </div>
                                    <input type="hidden" name="google_geo_block_audience" :value="jsonValue">
                                    <div class="space-y-[8px] rounded-[8px] border border-white/15 bg-black/20 p-[10px]">
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
                                        <p x-show="!rules.length" class="text-[10px] text-white/50">No blocked locations added yet. Add a country (optional region/city), then Save changes.</p>
                                    </div>
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

                                <div x-data="ipListFileUpload('allow_list_ips')">
                                    <div class="figma-detection-right-row flex-wrap gap-[8px]">
                                        <span class="min-w-0 flex-1">Ensure predefined IPs will always be able to see your ads</span>
                                        <div class="flex shrink-0 flex-wrap items-center gap-[8px]">
                                            <label class="cursor-pointer rounded-[6px] border border-white/30 px-[10px] py-[6px] text-[10px] text-white hover:bg-white/10">
                                                <input type="file" class="sr-only" accept=".txt,.csv,text/plain,text/csv" @change="onFile($event)">
                                                Choose file
                                            </label>
                                            <span class="max-w-[120px] truncate text-[9px] text-white/45" x-text="fileName || ''"></span>
                                            <x-figma-toggle
                                                name="allow_list_enabled"
                                                value="1"
                                                :checked="$settings->allow_list_enabled"
                                                label-on="On"
                                                label-off="Off"
                                            />
                                        </div>
                                    </div>
                                    <p class="mt-[6px] text-[9px] text-white/45">Upload .txt / .csv (one IP per line). IPs are merged into the list below — click Save changes to apply.</p>
                                    <textarea id="allow_list_ips" name="allow_list_ips" rows="3" placeholder="Add IPs or ranges (e.g. 103.207.87.2 or 216.67.176.*)" class="figma-textarea mt-[8px] text-[11px]">{{ $settings->allow_list_ips }}</textarea>
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
                                        <p class="text-[10px] text-white/50">When a paid visit is blocked, matching checked types are sent to Google Ads campaign IP exclusions. Unchecked types are blocked on-site only.</p>
                                    </div>

                                    <div
                                        class="mt-[12px] rounded-[8px] border border-white/15 bg-black/20 p-[12px] space-y-[10px]"
                                        x-data="googleExclusionPanel(@js([
                                            'pushUrl' => route('paid-marketing.detection-settings.google-exclusion.push', $domain),
                                            'pushRowUrl' => route('paid-marketing.detection-settings.google-exclusion.push-row', $domain),
                                            'toggleRowUrl' => route('paid-marketing.detection-settings.google-exclusion.toggle-row', $domain),
                                            'bulkUrl' => route('paid-marketing.detection-settings.google-exclusion.push-bulk', $domain),
                                            'syncUrl' => route('paid-marketing.detection-settings.google-exclusion.sync', $domain),
                                            'csrf' => csrf_token(),
                                            'rows' => $ipExclusions,
                                        ]))"
                                    >
                                        <h3 class="text-[12px] font-semibold text-white">Google Ads IP exclusion (manual test)</h3>
                                        <p class="text-[10px] text-white/55">Add an IP, CIDR range, or wildcard (e.g. 216.67.176.*) to campaign exclusions. Wildcards are converted to CIDR for Google Ads.</p>
                                        <div class="figma-google-exclusion-actions">
                                            <label class="figma-google-exclusion-field">
                                                <span class="figma-google-exclusion-label">IP address</span>
                                                <input
                                                    type="text"
                                                    x-model="ip"
                                                    placeholder="203.0.113.50"
                                                    class="figma-textarea figma-google-exclusion-input"
                                                    @keydown.enter.prevent="pushIp()"
                                                >
                                            </label>
                                            <button
                                                type="button"
                                                class="figma-google-exclusion-btn figma-google-exclusion-btn--primary"
                                                :disabled="loading || !ip.trim()"
                                                @click="pushIp()"
                                            >
                                                Add to campaigns
                                            </button>
                                            <button
                                                type="button"
                                                class="figma-google-exclusion-btn figma-google-exclusion-btn--ghost"
                                                :disabled="loading"
                                                @click="syncPending()"
                                            >
                                                Push all pending
                                            </button>
                                        </div>
                                        <div class="rounded-[6px] border border-white/10 bg-black/25 p-[10px] space-y-[8px]">
                                            <p class="text-[11px] font-semibold text-white">Bulk IP upload</p>
                                            <p class="text-[10px] text-white/50">One per line or comma-separated. Supports single IP, CIDR (13.0.0.0/8), and wildcards (216.67.176.*). Max 200 per upload.</p>
                                            <textarea
                                                x-model="bulkIps"
                                                rows="4"
                                                placeholder="216.67.176.*&#10;54.202.0.0/15&#10;74.7.229.22"
                                                class="figma-textarea w-full text-[11px]"
                                            ></textarea>
                                            <div class="flex flex-wrap items-center gap-[8px]">
                                                <label class="cursor-pointer rounded-[6px] border border-white/30 px-[12px] py-[8px] text-[11px] text-white hover:bg-white/10">
                                                    <input type="file" class="sr-only" accept=".txt,.csv,text/plain,text/csv" @change="onBulkFile($event)">
                                                    Choose file
                                                </label>
                                                <span class="text-[10px] text-white/50" x-text="bulkFileName || 'No file chosen'"></span>
                                                <button
                                                    type="button"
                                                    class="rounded-[6px] bg-white px-[14px] py-[8px] text-[11px] font-semibold text-[#6400B2] disabled:opacity-50"
                                                    :disabled="loading || (!bulkIps.trim() && !bulkFileName)"
                                                    @click="pushBulk()"
                                                >
                                                    Upload &amp; add to campaigns
                                                </button>
                                            </div>
                                        </div>
                                        <p x-show="message" x-text="message" class="text-[11px]" :class="ok ? 'text-emerald-300' : 'text-rose-300'"></p>
                                        <div class="max-h-[160px] overflow-y-auto rounded-[6px] border border-white/10">
                                            <table class="w-full text-left text-[10px] text-white/85">
                                                <thead class="sticky top-0 bg-[#101010] text-white/60">
                                                    <tr>
                                                        <th class="px-[8px] py-[6px] font-normal">IP</th>
                                                        <th class="px-[8px] py-[6px] font-normal">Type</th>
                                                        <th class="px-[8px] py-[6px] font-normal">Status</th>
                                                        <th class="px-[8px] py-[6px] font-normal text-right">Blocked</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-if="!rows.length">
                                                        <tr><td colspan="4" class="px-[8px] py-[8px] text-white/45">No blocked IPs queued yet.</td></tr>
                                                    </template>
                                                    <template x-for="row in rows" :key="row.ip + row.updated_at">
                                                        <tr class="border-t border-white/10">
                                                            <td class="px-[8px] py-[6px] font-mono" x-text="row.ip"></td>
                                                            <td class="px-[8px] py-[6px] capitalize" x-text="row.threat_group || '—'"></td>
                                                            <td class="px-[8px] py-[6px]">
                                                                <span
                                                                    class="rounded-[3px] px-[6px] py-[2px] text-[9px] uppercase"
                                                                    :class="{
                                                                        'bg-emerald-500/20 text-emerald-300': row.sync_status === 'synced' && row.is_active !== false,
                                                                        'bg-amber-500/20 text-amber-200': row.sync_status === 'pending',
                                                                        'bg-rose-500/20 text-rose-300': row.sync_status === 'failed',
                                                                        'bg-white/10 text-white/50': row.sync_status === 'disabled' || row.is_active === false,
                                                                        'bg-white/10 text-white/60': row.sync_status === 'skipped',
                                                                    }"
                                                                    x-text="row.sync_status === 'disabled' || row.is_active === false ? 'off' : row.sync_status"
                                                                ></span>
                                                            </td>
                                                            <td class="px-[8px] py-[6px] text-right">
                                                                <label
                                                                    class="figma-toggle figma-toggle--sm figma-toggle--no-labels ml-auto inline-flex"
                                                                    :class="{ 'figma-toggle--disabled': loading || togglingIp === row.ip }"
                                                                    :title="row.is_active === false ? 'Enable Google Ads block' : 'Disable Google Ads block'"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        class="figma-toggle-input"
                                                                        :checked="row.is_active !== false"
                                                                        :disabled="loading || togglingIp === row.ip"
                                                                        @change="toggleRow(row, $event.target.checked)"
                                                                    >
                                                                    <span class="figma-toggle-track pointer-events-none" aria-hidden="true">
                                                                        <span class="figma-toggle-thumb"></span>
                                                                    </span>
                                                                </label>
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

                                @if (!empty($countryAudits) && $countryAudits->isNotEmpty())
                                    <div class="figma-detection-section mt-[18px]">
                                        <h2 class="figma-detection-section-title">Country rule audit log</h2>
                                        <div class="figma-detection-card overflow-x-auto">
                                            <table class="w-full text-left text-[11px] text-white/80">
                                                <thead>
                                                    <tr class="border-b border-white/15 text-white/50">
                                                        <th class="py-[6px] pr-[8px] font-medium">When</th>
                                                        <th class="py-[6px] pr-[8px] font-medium">Admin</th>
                                                        <th class="py-[6px] pr-[8px] font-medium">Action</th>
                                                        <th class="py-[6px] pr-[8px] font-medium">Field</th>
                                                        <th class="py-[6px] pr-[8px] font-medium">Scope</th>
                                                        <th class="py-[6px] font-medium">Change</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($countryAudits as $audit)
                                                        <tr class="border-b border-white/10 align-top">
                                                            <td class="py-[6px] pr-[8px] whitespace-nowrap">{{ optional($audit->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                                            <td class="py-[6px] pr-[8px]">{{ $audit->user?->email ?? $audit->user?->name ?? ('#'.$audit->user_id) }}</td>
                                                            <td class="py-[6px] pr-[8px]">{{ $audit->action }}</td>
                                                            <td class="py-[6px] pr-[8px]">{{ $audit->field }}</td>
                                                            <td class="py-[6px] pr-[8px]">{{ $audit->scope }}</td>
                                                            <td class="py-[6px] max-w-[280px] break-all text-white/60">
                                                                <span class="text-white/40">from</span>
                                                                {{ \Illuminate\Support\Str::limit(json_encode($audit->previous_value['value'] ?? null), 80) }}
                                                                <span class="text-white/40">→</span>
                                                                {{ \Illuminate\Support\Str::limit(json_encode($audit->new_value['value'] ?? null), 80) }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </section>
                    </div>
                </form>
            @endif
        @endif
    </section>
</div>

<script>
function ipListFileUpload(textareaId) {
    return {
        fileName: '',
        async onFile(event) {
            const file = event.target.files?.[0];
            if (!file) {
                this.fileName = '';
                return;
            }
            this.fileName = file.name;
            try {
                const text = await file.text();
                const textarea = document.getElementById(textareaId);
                if (!textarea) return;
                const existing = textarea.value.trim();
                const incoming = text.trim();
                if (!incoming) return;
                textarea.value = existing ? existing + '\n' + incoming : incoming;
            } catch (e) {
                this.fileName = 'Read failed';
            }
            event.target.value = '';
        },
    };
}

function googleExclusionPanel(config) {
    return {
        ip: '',
        bulkIps: '',
        bulkFile: null,
        bulkFileName: '',
        rows: config.rows || [],
        pushUrl: config.pushUrl,
        pushRowUrl: config.pushRowUrl,
        toggleRowUrl: config.toggleRowUrl,
        bulkUrl: config.bulkUrl,
        syncUrl: config.syncUrl,
        csrf: config.csrf,
        loading: false,
        pushingIp: '',
        togglingIp: '',
        message: '',
        ok: true,
        onBulkFile(event) {
            const file = event.target.files?.[0];
            this.bulkFile = file || null;
            this.bulkFileName = file ? file.name : '';
        },
        async pushBulk() {
            if (this.loading || (!this.bulkIps.trim() && !this.bulkFile)) return;
            this.loading = true;
            this.message = '';
            try {
                const form = new FormData();
                if (this.bulkIps.trim()) {
                    form.append('ips', this.bulkIps.trim());
                }
                if (this.bulkFile) {
                    form.append('file', this.bulkFile);
                }
                const res = await fetch(this.bulkUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || (this.ok ? 'Bulk upload finished.' : 'Bulk upload failed.');
                if (Array.isArray(data.rows)) this.rows = data.rows;
                if (this.ok) {
                    this.bulkIps = '';
                    this.bulkFile = null;
                    this.bulkFileName = '';
                }
            } catch (e) {
                this.ok = false;
                this.message = 'Bulk upload request failed.';
            } finally {
                this.loading = false;
            }
        },
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
        async pushRow(ip) {
            if (!ip || this.loading) return;
            this.loading = true;
            this.pushingIp = ip;
            this.message = '';
            try {
                const res = await fetch(this.pushRowUrl, {
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
                this.message = data.message || (this.ok ? `IP ${ip} pushed to Google Ads.` : 'Push failed.');
                if (Array.isArray(data.rows)) this.rows = data.rows;
            } catch (e) {
                this.ok = false;
                this.message = 'Push request failed.';
            } finally {
                this.loading = false;
                this.pushingIp = '';
            }
        },
        async toggleRow(row, active) {
            const ip = row?.ip;
            if (!ip || this.loading) return;
            this.loading = true;
            this.togglingIp = ip;
            this.message = '';
            const previous = row.is_active !== false;
            row.is_active = active;
            try {
                const res = await fetch(this.toggleRowUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ip, active }),
                });
                const data = await res.json().catch(() => ({}));
                this.ok = !!data.ok;
                this.message = data.message || (this.ok
                    ? (active ? `IP ${ip} block enabled in Google Ads.` : `IP ${ip} block removed from Google Ads.`)
                    : 'Toggle failed.');
                if (Array.isArray(data.rows)) {
                    this.rows = data.rows;
                } else if (data.row) {
                    const idx = this.rows.findIndex(r => r.ip === ip);
                    if (idx >= 0) this.rows[idx] = data.row;
                }
                if (!this.ok) row.is_active = previous;
            } catch (e) {
                this.ok = false;
                this.message = 'Toggle request failed.';
                row.is_active = previous;
            } finally {
                this.loading = false;
                this.togglingIp = '';
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
