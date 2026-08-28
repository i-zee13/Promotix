{{-- Detection Panel right sidebar — Bot Rules / Session / Audit / Profiles / Geo (not IP Investigation). --}}
@php
    $planDetectionFeatures = $planDetectionFeatures ?? \App\Support\DetectionPlanFeatures::allEnabled();
    $pdf = $pdf ?? static fn (string $key): bool => (bool) ($planDetectionFeatures[$key] ?? true);
    $profileKey = $settings->detection_profile ?? 'standard';
    $retentionDays = (int) ($settings->recording_retention_days ?? 30);
    $geoScope = (string) ($settings->geo_rule_scope ?? 'domain');
    $consentOn = (bool) ($settings->consent_required ?? false);
    $maskOn = (bool) ($settings->recording_mask_passwords ?? true);
    $sessionRecOn = (bool) ($settings->session_recordings ?? false) && $pdf(\App\Support\DetectionPlanFeatures::SESSION_RECORDINGS);
    $botRulesActive = (bool) ($settings->suspicious_enabled ?? true);
    $blockResponse = (string) ($settings->block_response ?? 'hide');
    $challengeOn = $blockResponse === 'challenge';
    $profileCards = [
        'standard' => ['title' => 'Balanced', 'desc' => 'Low false positives.', 'tone' => 'green'],
        'advanced' => ['title' => 'Advanced', 'desc' => 'High-risk campaigns.', 'tone' => 'blue'],
        'extreme' => ['title' => 'Maximum', 'desc' => 'Strict filtering.', 'tone' => 'orange'],
        'marketing' => ['title' => 'Custom', 'desc' => 'Your own rules.', 'tone' => 'purple'],
    ];
    $detectionAudits = $detectionAudits ?? $countryAudits ?? collect();
    $auditFieldLabels = [
        'suspicious_matrix' => 'Updated threat rules',
        'block_response' => 'Updated block response',
        'detection_profile' => 'Updated detection profile',
        'session_recordings' => 'Updated session recording',
        'geo_rule_scope' => 'Updated geo rule scope',
        'allow_list_ips' => 'Updated whitelist IPs',
        'block_list_ips' => 'Updated blacklist IPs',
        'out_of_geo_enabled' => 'Updated geo targeting',
        'google_geo_block_enabled' => 'Updated blocked countries',
    ];
@endphp

<style>
    .detection-rightbar { color: #fff; min-width: 0; }
    .detection-rightbar .ds-rb-stack { display: flex; flex-direction: column; gap: 10px; max-height: calc(100vh - 120px); overflow-y: auto; padding-right: 2px; }
    .detection-rightbar .ds-panel { margin-top: 0; padding: 10px; border-radius: 10px; }
    .detection-rightbar .ds-panel__title { font-size: 12px; }
    .detection-rightbar .ds-panel__sub { font-size: 10px; }
    .detection-rightbar .ds-split { grid-template-columns: 1fr; gap: 8px; }
    .detection-rightbar .ds-box { padding: 8px; }
    .detection-rightbar .ds-box__title { font-size: 10px; margin-bottom: 8px; }
    .detection-rightbar .ds-signal-list,
    .detection-rightbar .ds-risk-list { gap: 6px; }
    .detection-rightbar .ds-signal-list li,
    .detection-rightbar .ds-risk-list li { font-size: 10px; gap: 6px; }
    .detection-rightbar .ds-challenge { margin-top: 8px; padding: 8px; flex-direction: column; align-items: stretch; }
    .detection-rightbar .ds-challenge__title { font-size: 11px; }
    .detection-rightbar .ds-challenge__meta { font-size: 9px; }
    .detection-rightbar .ds-challenge-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
    .detection-rightbar .ds-select { max-width: none; height: 30px; font-size: 10px; margin-bottom: 8px; }
    .detection-rightbar .ds-field-label { font-size: 10px; }
    .detection-rightbar .ds-checkboxes { gap: 6px; }
    .detection-rightbar .ds-checkboxes label { font-size: 10px; }
    .detection-rightbar .ds-consent-btn { min-height: 30px; font-size: 10px; margin-top: 8px; }
    .detection-rightbar .ds-profile-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .detection-rightbar .ds-profile-card { min-height: 0; padding: 8px; gap: 4px; }
    .detection-rightbar .ds-profile-card__title { font-size: 11px; }
    .detection-rightbar .ds-profile-card__desc { font-size: 9px; }
    .detection-rightbar .ds-profile-card__icon { width: 22px; height: 22px; }
    .detection-rightbar .ds-geo-scope { grid-template-columns: 1fr; gap: 6px; }
    .detection-rightbar .ds-geo-btn { min-height: 36px; font-size: 10px; }
    .detection-rightbar .ds-audit-table { min-width: 0; font-size: 9px; }
    .detection-rightbar .ds-audit-table th,
    .detection-rightbar .ds-audit-table td { padding: 6px 4px; }
    .detection-rightbar .ds-audit-table-wrap { overflow-x: auto; }
    .detection-rightbar .ds-rb-save {
        width: 100%;
        margin-top: 4px;
        border-radius: 8px;
        border: 0;
        background: #fff;
        color: #6400B2;
        font-size: 12px;
        font-weight: 700;
        padding: 10px 12px;
        cursor: pointer;
    }
</style>

<div
    class="detection-rightbar"
    x-data="{
        challengeMode: {{ $challengeOn ? 'true' : 'false' }},
        sessionRecording: {{ $sessionRecOn ? 'true' : 'false' }},
        maskPasswords: {{ $maskOn ? 'true' : 'false' }},
        maskPayment: {{ $maskOn ? 'true' : 'false' }},
        maskSensitive: {{ $maskOn ? 'true' : 'false' }},
        consentGdpr: {{ $consentOn ? 'true' : 'false' }},
        consentCcpa: {{ $consentOn ? 'true' : 'false' }},
        consentCookie: {{ $consentOn ? 'true' : 'false' }},
        consentManageOpen: false,
        geoScope: @js($geoScope),
        profileKey: @js($profileKey),
        get maskValue() { return this.maskPasswords || this.maskPayment || this.maskSensitive; },
        get consentValue() { return this.consentGdpr || this.consentCcpa || this.consentCookie; },
        setChallengeMode(on) {
            this.challengeMode = !!on;
            window.dispatchEvent(new CustomEvent('detection:challenge-mode', { detail: { on: this.challengeMode } }));
        },
    }"
>
    <div class="ds-rb-stack promotix-slim-scroll">
        <div class="ds-panel">
            <div class="ds-panel__head">
                <h3 class="ds-panel__title">Bot Detection Rules</h3>
                @if ($botRulesActive)
                    <span class="ds-badge-active">Active</span>
                @endif
            </div>
            <div class="ds-split">
                <div class="ds-box">
                    <h4 class="ds-box__title">Bot Detection Signals</h4>
                    <ul class="ds-signal-list">
                        @foreach ([
                            'Headless Browser',
                            'Automation Framework',
                            'Missing Browser Signals',
                            'Abnormal User Agent',
                            'No Human Interaction',
                            'Repeated Automated Requests',
                        ] as $signal)
                            <li>
                                <span class="ds-check" aria-hidden="true">
                                    <svg class="h-[10px] w-[10px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </span>
                                {{ $signal }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="ds-box">
                    <h4 class="ds-box__title">Action (Based on Risk Score)</h4>
                    <ul class="ds-risk-list">
                        <li><span class="ds-dot ds-dot--allow"></span> Allow (0 - 30)</li>
                        <li><span class="ds-dot ds-dot--monitor"></span> Monitor (31 - 60)</li>
                        <li><span class="ds-dot ds-dot--challenge"></span> Challenge (61 - 80)</li>
                        <li><span class="ds-dot ds-dot--block"></span> Block (81 - 100)</li>
                    </ul>
                </div>
            </div>
            <div class="ds-challenge">
                <div class="ds-challenge-row">
                    <h4 class="ds-challenge__title !mb-0">Challenge Mode (CAPTCHA)</h4>
                    <label class="figma-toggle figma-toggle--sm figma-toggle--no-labels">
                        <input type="checkbox" class="figma-toggle-input" :checked="challengeMode" @change="setChallengeMode($event.target.checked)">
                        <span class="figma-toggle-track pointer-events-none" aria-hidden="true"><span class="figma-toggle-thumb"></span></span>
                    </label>
                </div>
                <p class="ds-challenge__meta"><strong class="text-white/70">Trigger:</strong> Medium Risk (61 - 80)</p>
                <p class="ds-challenge__meta"><strong class="text-white/70">Action:</strong> Show verification challenge.</p>
            </div>
        </div>

        @if ($pdf(\App\Support\DetectionPlanFeatures::SESSION_RECORDINGS))
        <div class="ds-panel">
            <div class="ds-panel__head">
                <h3 class="ds-panel__title">Session Recording &amp; Privacy</h3>
                <span class="ds-badge-active" x-show="sessionRecording" x-cloak>Active</span>
            </div>
            <div class="ds-box">
                <div class="ds-row-toggle">
                    <span class="ds-field-label !mb-0">Session Recording</span>
                    <input type="hidden" form="detection-settings-form" name="session_recordings" value="0">
                    <label class="figma-toggle figma-toggle--sm figma-toggle--no-labels">
                        <input type="checkbox" form="detection-settings-form" name="session_recordings" value="1" class="figma-toggle-input" x-model="sessionRecording">
                        <span class="figma-toggle-track pointer-events-none" aria-hidden="true"><span class="figma-toggle-thumb"></span></span>
                    </label>
                </div>
                <span class="ds-field-label">Retention Period</span>
                <select form="detection-settings-form" name="recording_retention_days" class="ds-select">
                    @foreach ([7, 14, 30, 60, 90, 180, 365] as $days)
                        <option value="{{ $days }}" @selected($retentionDays === $days)>{{ $days }} Days</option>
                    @endforeach
                    @if (! in_array($retentionDays, [7, 14, 30, 60, 90, 180, 365], true))
                        <option value="{{ $retentionDays }}" selected>{{ $retentionDays }} Days</option>
                    @endif
                </select>
                <span class="ds-field-label">Mask Sensitive Data</span>
                <input type="hidden" form="detection-settings-form" name="recording_mask_passwords" value="0">
                <input type="hidden" form="detection-settings-form" name="recording_mask_passwords" :value="maskValue ? 1 : 0">
                <div class="ds-checkboxes">
                    <label><input type="checkbox" x-model="maskPasswords"> Passwords</label>
                    <label><input type="checkbox" x-model="maskPayment"> Payment Fields</label>
                    <label><input type="checkbox" x-model="maskSensitive"> Sensitive Inputs</label>
                </div>
            </div>
            <div class="ds-box" style="margin-top:8px;">
                <h4 class="ds-box__title">Consent Management</h4>
                <input type="hidden" form="detection-settings-form" name="consent_required" value="0">
                <input type="hidden" form="detection-settings-form" name="consent_required" :value="consentValue ? 1 : 0">
                <div class="ds-checkboxes">
                    <label><input type="checkbox" x-model="consentGdpr"> GDPR Consent</label>
                    <label><input type="checkbox" x-model="consentCcpa"> CCPA Consent</label>
                    <label><input type="checkbox" x-model="consentCookie"> Cookie Notice</label>
                </div>
                <button type="button" class="ds-consent-btn" @click="consentManageOpen = !consentManageOpen">Manage Consent Settings</button>
                <div x-show="consentManageOpen" x-cloak class="mt-[8px]">
                    <span class="ds-field-label">Consent regions</span>
                    <input type="text" form="detection-settings-form" name="consent_regions" value="{{ implode(',', (array) ($settings->consent_regions ?? [])) }}" placeholder="DE,FR,GB" class="ds-select">
                </div>
            </div>
        </div>
        @else
            <input type="hidden" form="detection-settings-form" name="session_recordings" value="0">
        @endif

        <div class="ds-panel">
            <div class="ds-panel__head">
                <h3 class="ds-panel__title">Geo Rule Scope</h3>
            </div>
            <input type="hidden" form="detection-settings-form" name="geo_rule_scope" :value="geoScope">
            <div class="ds-geo-scope">
                <button type="button" class="ds-geo-btn" :class="{ 'is-active': geoScope === 'domain' }" @click="geoScope = 'domain'">Current Domain</button>
                <button type="button" class="ds-geo-btn" :class="{ 'is-active': geoScope === 'workspace' }" @click="geoScope = 'workspace'">All Domains</button>
                <button type="button" class="ds-geo-btn" disabled title="Coming soon">Selected Campaigns</button>
            </div>
            <label class="mt-[8px] inline-flex items-center gap-[6px] text-[10px] text-white/70">
                <input type="checkbox" form="detection-settings-form" name="save_workspace_geo" value="1" class="rounded border-white/30 accent-[#6400B2]">
                Save as workspace defaults
            </label>
        </div>

        {{-- Detection Profiles UI hidden for now (keep value so save still works). --}}
        <input type="hidden" form="detection-settings-form" name="detection_profile" :value="profileKey">
        {{--
        <div class="ds-panel">
            <div class="ds-panel__head" style="flex-direction:column;align-items:flex-start;">
                <h3 class="ds-panel__title">Detection Profiles</h3>
                <p class="ds-panel__sub">Choose a protection level.</p>
            </div>
            <div class="ds-profile-grid">
                @foreach ($profileCards as $pkey => $card)
                    <label class="ds-profile-card is-{{ $card['tone'] }}" :class="{ 'is-selected': profileKey === '{{ $pkey }}' }">
                        <input type="radio" form="detection-settings-form" name="detection_profile" value="{{ $pkey }}" x-model="profileKey" @checked($profileKey === $pkey)>
                        <span class="ds-profile-card__icon" aria-hidden="true">
                            @if ($pkey === 'standard')
                                <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif ($pkey === 'advanced')
                                <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            @elseif ($pkey === 'extreme')
                                <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v5c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V6l8-3z"/></svg>
                            @else
                                <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317a1.724 1.724 0 013.35 0 1.724 1.724 0 002.573 1.066 1.724 1.724 0 012.354 2.354 1.724 1.724 0 001.065 2.572 1.724 1.724 0 010 3.35 1.724 1.724 0 00-1.066 2.573 1.724 1.724 0 01-2.354 2.354 1.724 1.724 0 00-2.572 1.065 1.724 1.724 0 01-3.35 0 1.724 1.724 0 00-2.573-1.066 1.724 1.724 0 01-2.354-2.354 1.724 1.724 0 00-1.065-2.572 1.724 1.724 0 010-3.35 1.724 1.724 0 001.066-2.573 1.724 1.724 0 012.354-2.354 1.724 1.724 0 002.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            @endif
                        </span>
                        <p class="ds-profile-card__title">{{ $card['title'] }}</p>
                        <p class="ds-profile-card__desc">{{ $card['desc'] }}</p>
                    </label>
                @endforeach
            </div>
        </div>
        --}}

        <div class="ds-panel">
            <div class="ds-panel__head">
                <h3 class="ds-panel__title">Detection Audit Log</h3>
            </div>
            @if ($detectionAudits instanceof \Illuminate\Support\Collection && $detectionAudits->isNotEmpty())
                <div class="ds-audit-table-wrap">
                    <table class="ds-audit-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Action</th>
                                <th>New</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detectionAudits->take(8) as $audit)
                                @php
                                    $nextRaw = $audit->new_value['value'] ?? $audit->new_value;
                                    $fmt = function ($v) {
                                        if (is_bool($v)) return $v ? 'On' : 'Off';
                                        if (is_array($v)) return \Illuminate\Support\Str::limit(json_encode($v), 28);
                                        if ($v === null || $v === '') return '—';
                                        return \Illuminate\Support\Str::limit((string) $v, 28);
                                    };
                                    $actionLabel = $auditFieldLabels[$audit->field] ?? ('Updated ' . str_replace('_', ' ', (string) $audit->field));
                                @endphp
                                <tr>
                                    <td>{{ optional($audit->created_at)->format('M j g:ia') }}</td>
                                    <td>{{ $actionLabel }}</td>
                                    <td>{{ $fmt($nextRaw) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="ds-audit-empty">No changes logged yet.</p>
            @endif
        </div>

        <button type="submit" form="detection-settings-form" class="ds-rb-save">Save changes</button>
    </div>
</div>
