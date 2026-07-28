@extends('layouts.super-admin')

@section('title', 'System Settings')
@section('content')
@php
    $brandingSettings = $settingsByGroup->get('branding', collect())->keyBy('key');
    $brandingLogo = $brandingSettings->get('branding.logo_url');
    $brandingFavicon = $brandingSettings->get('branding.favicon_url');
    $brandingFontFamily = $brandingSettings->get('branding.font_family');
    $brandingFontSize = $brandingSettings->get('branding.font_size_base');
    $brandingPrimary = $brandingSettings->get('branding.color_primary');
    $brandingSecondary = $brandingSettings->get('branding.color_secondary');
    $brandingBackground = $brandingSettings->get('branding.color_background');
    $brandingCompany = $brandingSettings->get('branding.company_name');
    $brandingSupport = $brandingSettings->get('branding.support_email');

    $domainSettings = $settingsByGroup->get('domain_defaults', collect())->keyBy('key');
    $autoVerifyRules = json_decode($domainSettings->get('domain_defaults.auto_verify_rules')?->value ?? '[]', true) ?: [];

    $trialSettings = $settingsByGroup->get('trial', collect());
    $bankSettings = $settingsByGroup->get('bank', collect());

    $flagsByKey = $featureFlags->keyBy('key');
    $suggestedFlags = [
        'country_based_blocking' => 'Enable Country-Based Blocking',
        'ip_intelligence_scoring' => 'Enable IP Intelligence Scoring',
        'vpn_proxy_detection' => 'Enable VPN / Proxy Detection',
        'rate_limit_protection' => 'Enable Rate-Limit Protection',
        'smart_auto_whitelist' => 'Enable Smart Auto-Whitelist',
        'advanced_traffic_analytics' => 'Enable Advanced Traffic Analytics',
    ];
    $selectedPlan = $plans->first();
@endphp
<x-super-admin.page>
<div x-data="{
        modal: @js(session('open_modal')),
        brandingTab: 'logo',
        emailTab: 'welcome_email',
        search: '',
        matches(label) {
            const q = this.search.trim().toLowerCase();
            return !q || String(label).toLowerCase().includes(q);
        }
    }" class="space-y-6">

    <div class="mb-[6px] flex flex-wrap items-center justify-between gap-[14px]">
        <h1 class="text-[24px] font-semibold leading-none text-[#d9d9d9] sm:text-[32px]">System Settings</h1>
        <div class="figma-sa-users-search-wrap !flex-none !min-w-[271px]">
            <svg class="figma-sa-users-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" placeholder="Search Settings....." class="figma-sa-users-search-input" x-model="search">
        </div>
    </div>

    {{-- Launcher cards --}}
    <div class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-3">
        <article class="figma-sa-settings-card" x-show="matches('Branding')">
            <span class="figma-sa-settings-card-icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h14a2 2 0 012 2v6.5M7 21c2.5 0 4-1.5 4-4M7 21c-1.5-1-2-2.5-2-4M12 9h6M12 13h4"/></svg>
            </span>
            <h3 class="figma-sa-settings-card-title">Branding</h3>
            <p class="figma-sa-settings-card-desc">Customize logo, colors, and appearance</p>
            <button type="button" class="figma-sa-settings-card-btn" @click="modal = 'branding'">Customize</button>
        </article>

        <article class="figma-sa-settings-card" x-show="matches('Domain Templates')">
            <span class="figma-sa-settings-card-icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 014 9 15 15 0 01-4 9 15 15 0 01-4-9 15 15 0 014-9z"/></svg>
            </span>
            <h3 class="figma-sa-settings-card-title">Domain Templates</h3>
            <p class="figma-sa-settings-card-desc">Manage domain and tracker templates</p>
            <button type="button" class="figma-sa-settings-card-btn" @click="modal = 'domain'">Manage</button>
        </article>

        <article class="figma-sa-settings-card" x-show="matches('Email Templates')">
            <span class="figma-sa-settings-card-icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </span>
            <h3 class="figma-sa-settings-card-title">Email Templates</h3>
            <p class="figma-sa-settings-card-desc">Create and modify email templates</p>
            <button type="button" class="figma-sa-settings-card-btn" @click="modal = 'email'">Edit Templates</button>
        </article>

        <article class="figma-sa-settings-card" x-show="matches('Free Trial')">
            <span class="figma-sa-settings-card-icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <h3 class="figma-sa-settings-card-title">Free Trial</h3>
            <p class="figma-sa-settings-card-desc">Trial duration and default plan on signup</p>
            <button type="button" class="figma-sa-settings-card-btn" @click="modal = 'trial'">Configure</button>
        </article>

        <article class="figma-sa-settings-card" x-show="matches('Bank Details')">
            <span class="figma-sa-settings-card-icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M4 10h16M4 10l8-6 8 6M6 10v9m4-9v9m4-9v9m4-9v9"/></svg>
            </span>
            <h3 class="figma-sa-settings-card-title">Bank Details</h3>
            <p class="figma-sa-settings-card-desc">Shown to customers on manual bank transfer</p>
            @php
                $bankNamePreview = $bankSettings->firstWhere('key', 'bank.bank_name')?->value;
                $bankAccountPreview = $bankSettings->firstWhere('key', 'bank.account_number')?->value;
            @endphp
            <p class="mt-2 text-[12px] leading-snug text-white/65">
                Bank: {{ $bankNamePreview !== null && $bankNamePreview !== '' ? $bankNamePreview : '—' }}
                · Account: {{ $bankAccountPreview !== null && $bankAccountPreview !== '' ? $bankAccountPreview : '—' }}
            </p>
            <button type="button" class="figma-sa-settings-card-btn" @click="modal = 'bank'">Configure</button>
        </article>

        <article class="figma-sa-settings-card" x-show="matches('Feature Flags')">
            <span class="figma-sa-settings-card-icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 21V4m0 3h11l-2 4 2 4H5"/></svg>
            </span>
            <h3 class="figma-sa-settings-card-title">Feature Flags</h3>
            <p class="figma-sa-settings-card-desc">Toggle platform features on/off</p>
            <button type="button" class="figma-sa-settings-card-btn" @click="modal = 'flags'">Manage</button>
        </article>
    </div>

    {{-- Branding modal --}}
    <div x-show="modal === 'branding'" x-cloak class="figma-sa-settings-modal-backdrop" @keydown.escape.window="modal = null">
        <div class="figma-sa-settings-modal" style="max-width:920px;" @click.outside="modal = null">
            <button type="button" class="figma-sa-settings-modal-close" @click="modal = null">&times;</button>
            <h2 class="figma-sa-settings-modal-title">Branding &amp; Customization</h2>
            <div class="figma-sa-settings-modal-tabs">
                @foreach (['logo' => 'Logo', 'colors' => 'Colors', 'typography' => 'Typography', 'preview' => 'Live Preview', 'favicon' => 'Favicon', 'support' => 'Support'] as $key => $label)
                    <button type="button" @click="brandingTab = '{{ $key }}'" :class="brandingTab === '{{ $key }}' ? 'is-active' : ''" class="figma-sa-settings-modal-tab">{{ $label }}</button>
                @endforeach
            </div>

            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="figma-sa-settings-panel space-y-4">
                @csrf

                <div x-show="brandingTab === 'logo'" class="space-y-4">
                    @if ($brandingLogo)
                        <div>
                            <label class="figma-sa-settings-row-label">{{ $brandingLogo->label }}</label>
                            <input type="text" name="settings[branding.logo_url]" value="{{ $brandingLogo->value }}" class="figma-sa-settings-input mt-1">
                        </div>
                    @endif
                    @if ($brandingCompany)
                        <div>
                            <label class="figma-sa-settings-row-label">{{ $brandingCompany->label }}</label>
                            <input type="text" name="settings[branding.company_name]" value="{{ $brandingCompany->value }}" class="figma-sa-settings-input mt-1">
                        </div>
                    @endif
                </div>

                <div x-show="brandingTab === 'colors'" class="space-y-3">
                    <p class="text-[12px] leading-relaxed text-white/65">
                        These colors apply to the customer portal (dashboard, billing, onboarding payment, profile, etc.).
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($brandingSettings->filter(fn ($s) => str_starts_with((string) $s->key, 'branding.color_'))->sortBy('key') as $setting)
                            <div>
                                <label class="figma-sa-settings-row-label">{{ $setting->label }}</label>
                                <input type="color" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="mt-2 h-10 w-full cursor-pointer rounded border-0 bg-transparent">
                                <input type="text" value="{{ $setting->value }}" class="figma-sa-settings-input mt-2" readonly>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div x-show="brandingTab === 'typography'" class="grid gap-4 md:grid-cols-2">
                    @if ($brandingFontFamily)
                        <div>
                            <label class="figma-sa-settings-row-label">{{ $brandingFontFamily->label }}</label>
                            <input type="text" name="settings[branding.font_family]" value="{{ $brandingFontFamily->value }}" class="figma-sa-settings-input mt-1">
                        </div>
                    @endif
                    @if ($brandingFontSize)
                        <div>
                            <label class="figma-sa-settings-row-label">{{ $brandingFontSize->label }}</label>
                            <input type="number" min="10" max="24" name="settings[branding.font_size_base]" value="{{ $brandingFontSize->value }}" class="figma-sa-settings-input mt-1">
                        </div>
                    @endif
                </div>

                <div x-show="brandingTab === 'favicon'">
                    @if ($brandingFavicon)
                        <label class="figma-sa-settings-row-label">{{ $brandingFavicon->label }}</label>
                        <input type="text" name="settings[branding.favicon_url]" value="{{ $brandingFavicon->value }}" class="figma-sa-settings-input mt-1">
                    @endif
                </div>

                <div x-show="brandingTab === 'support'" class="grid gap-4 md:grid-cols-2">
                    @if ($brandingSupport)
                        <div>
                            <label class="figma-sa-settings-row-label">{{ $brandingSupport->label }}</label>
                            <input type="email" name="settings[branding.support_email]" value="{{ $brandingSupport->value }}" class="figma-sa-settings-input mt-1">
                        </div>
                    @endif
                </div>

                <div x-show="brandingTab === 'preview'">
                    @php
                        $previewSurface = $brandingSettings->get('branding.color_surface')?->value ?? '#1E1033';
                        $previewOutline = $brandingSettings->get('branding.color_outline')?->value ?? '#4A2D6E';
                        $previewCta = $brandingSettings->get('branding.color_cta')?->value ?? '#FFFFFF';
                        $previewCtaText = $brandingSettings->get('branding.color_cta_text')?->value ?? '#111111';
                        $previewTextMuted = $brandingSettings->get('branding.color_text_muted')?->value ?? '#B8A4D4';
                    @endphp
                    <div class="figma-sa-brand-preview"
                        style="--preview-bg: {{ $brandingBackground->value ?? '#0D0D0D' }}; --preview-primary: {{ $brandingPrimary->value ?? '#6400B2' }}; --preview-surface: {{ $previewSurface }}; --preview-outline: {{ $previewOutline }}; --preview-cta: {{ $previewCta }}; --preview-cta-text: {{ $previewCtaText }}; --preview-muted: {{ $previewTextMuted }}; --preview-font: {{ $brandingFontFamily->value ?? 'Inter' }}; --preview-size: {{ ($brandingFontSize->value ?? 16).'px' }};">
                        <div class="figma-sa-brand-preview-header">
                            @if ($brandingLogo?->value)
                                <img src="{{ $brandingLogo->value }}" alt="Logo" class="h-8 max-w-[140px] object-contain" onerror="this.style.display='none'">
                            @endif
                            <span>{{ $brandingCompany->value ?? 'Promotix' }}</span>
                        </div>
                        <div class="figma-sa-brand-preview-body">
                            <p class="figma-sa-brand-preview-title">Customer portal preview</p>
                            <div class="figma-sa-brand-preview-card">
                                <p class="figma-sa-brand-preview-card-title">Plan summary card</p>
                                <p class="figma-sa-brand-preview-muted">Surface, outline, and CTA colors</p>
                            </div>
                            <button type="button" class="figma-sa-brand-preview-btn">Primary action</button>
                            <button type="button" class="figma-sa-brand-preview-btn figma-sa-brand-preview-btn--cta">Subscribe (CTA)</button>
                            <p class="figma-sa-brand-preview-muted">Support: {{ $brandingSupport->value ?? 'support@promotix.local' }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2" x-show="brandingTab !== 'preview'">
                    <button type="button" class="figma-sa-settings-btn figma-sa-settings-btn--outline" @click="modal = null">Cancel</button>
                    <button type="submit" class="figma-sa-settings-btn figma-sa-settings-btn--primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Domain Templates modal --}}
    <div x-show="modal === 'domain'" x-cloak class="figma-sa-settings-modal-backdrop" @keydown.escape.window="modal = null">
        <div class="figma-sa-settings-modal" @click.outside="modal = null">
            <button type="button" class="figma-sa-settings-modal-close" @click="modal = null">&times;</button>
            <h2 class="figma-sa-settings-modal-title">Domain Templates</h2>

            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                @csrf
                <div class="figma-sa-settings-panel">
                    <h3 class="figma-sa-settings-modal-title" style="font-size:15px;">Default Protection Settings</h3>
                    <div class="mt-2">
                        @foreach ([
                            'domain_defaults.bot_protection_enabled' => 'Enable bot protection by default',
                            'domain_defaults.paid_ads_protection_enabled' => 'Enable paid ads protection',
                            'domain_defaults.auto_block_enabled' => 'Auto-block enabled',
                            'domain_defaults.block_data_centers' => 'Block data centers',
                            'domain_defaults.block_proxies' => 'Block proxies',
                            'domain_defaults.allow_known_crawlers' => 'Allow known crawlers',
                        ] as $key => $label)
                            @php $setting = $domainSettings->get($key); @endphp
                            <div class="figma-sa-settings-row">
                                <span class="figma-sa-settings-row-label">{{ $label }}</span>
                                <input type="hidden" name="settings[{{ $key }}]" value="0">
                                <x-figma-toggle name="settings[{{ $key }}]" value="1" :checked="$setting?->value === '1'" :show-labels="false" />
                            </div>
                        @endforeach
                    </div>

                    <h3 class="figma-sa-settings-modal-title mt-4" style="font-size:15px;">Default Thresholds</h3>
                    <div class="mt-2 space-y-3">
                        @foreach ([
                            'domain_defaults.risk_score_threshold' => ['label' => 'Risk Score Threshold', 'min' => 0, 'max' => 100],
                            'domain_defaults.frequency_cap' => ['label' => 'Frequency Cap (clicks/min)', 'min' => 1, 'max' => 10],
                            'domain_defaults.requests_per_minute' => ['label' => 'Requests per Minute', 'min' => 20, 'max' => 500],
                        ] as $key => $cfg)
                            @php $setting = $domainSettings->get($key); @endphp
                            @php $refName = 'range_'.str_replace(['.', '_'], '-', $key); @endphp
                            <div>
                                <div class="figma-sa-settings-row" style="border-bottom:0;padding-bottom:2px;">
                                    <span class="figma-sa-settings-row-label">{{ $cfg['label'] }}</span>
                                    <span class="figma-sa-settings-slider-value" x-text="$refs['{{ $refName }}']?.value ?? '{{ $setting?->value }}'"></span>
                                </div>
                                <input type="range" x-ref="{{ $refName }}" name="settings[{{ $key }}]" min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}" value="{{ $setting?->value }}" class="figma-sa-settings-range">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="figma-sa-settings-panel">
                    <h3 class="figma-sa-settings-modal-title" style="font-size:15px;">Auto-Verify Rules</h3>
                    <div class="mt-2">
                        @foreach ($autoVerifyRules as $i => $rule)
                            <div class="figma-sa-settings-verify-rule">
                                <input type="text" name="settings[domain_defaults.auto_verify_rules][{{ $i }}][host]" value="{{ $rule['host'] ?? '' }}" placeholder="host.domain.com" class="figma-sa-settings-input">
                                <input type="text" name="settings[domain_defaults.auto_verify_rules][{{ $i }}][record]" value="{{ $rule['record'] ?? '' }}" placeholder="_domainkey.domain.com" class="figma-sa-settings-input">
                            </div>
                        @endforeach
                    </div>

                    <h3 class="figma-sa-settings-modal-title mt-4" style="font-size:15px;">Tracker Script</h3>
                    <textarea name="settings[domain_defaults.tracker_script]" rows="5" class="figma-sa-settings-textarea mt-2">{{ $domainSettings->get('domain_defaults.tracker_script')?->value }}</textarea>
                    <p class="mt-1 text-[11px] text-white/60">This script will automatically be added to new domains when they onboard.</p>
                </div>

                <div class="lg:col-span-2 flex justify-end gap-2">
                    <button type="button" class="figma-sa-settings-btn figma-sa-settings-btn--outline" @click="modal = null">Cancel</button>
                    <button type="submit" class="figma-sa-settings-btn figma-sa-settings-btn--primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Email Templates modal --}}
    <div x-show="modal === 'email'" x-cloak class="figma-sa-settings-modal-backdrop" @keydown.escape.window="modal = null">
        <div class="figma-sa-settings-modal" @click.outside="modal = null">
            <button type="button" class="figma-sa-settings-modal-close" @click="modal = null">&times;</button>
            <h2 class="figma-sa-settings-modal-title">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Email Template
            </h2>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[200px_minmax(0,1fr)_220px]">
                <div class="figma-sa-settings-panel figma-sa-settings-template-list">
                    @foreach ($emailTemplates as $tpl)
                        <button type="button" @click="emailTab = '{{ $tpl->key }}'" :class="emailTab === '{{ $tpl->key }}' ? 'is-active' : ''" class="figma-sa-settings-template-item">{{ $tpl->name }}</button>
                    @endforeach
                </div>

                @foreach ($emailTemplates as $tpl)
                    <form method="POST" action="{{ route('super-admin.email-templates.send-test', $tpl) }}" class="hidden" id="email-test-form-{{ $tpl->key }}">@csrf</form>
                    <form method="POST" action="{{ route('super-admin.email-templates.restore', $tpl) }}" class="hidden" id="email-restore-form-{{ $tpl->key }}">@csrf</form>

                    <form x-show="emailTab === '{{ $tpl->key }}'" method="POST" action="{{ route('super-admin.email-templates.update', $tpl) }}" class="figma-sa-settings-panel space-y-3" id="email-form-{{ $tpl->key }}">
                        @csrf
                        @method('PUT')
                        <div class="flex items-center justify-between">
                            <span class="figma-sa-settings-row-label">{{ $tpl->name }}</span>
                            <input type="hidden" name="is_active" value="0">
                            <x-figma-toggle name="is_active" value="1" :checked="$tpl->is_active" :show-labels="false" />
                        </div>
                        <div>
                            <label class="figma-sa-settings-row-label" style="font-size:11px;opacity:.8;">Subject</label>
                            <input type="text" name="subject" value="{{ $tpl->subject }}" class="figma-sa-settings-input mt-1" id="email-subject-{{ $tpl->key }}">
                        </div>
                        <div>
                            <label class="figma-sa-settings-row-label" style="font-size:11px;opacity:.8;">Body</label>
                            <textarea name="body" rows="10" class="figma-sa-settings-textarea mt-1" id="email-body-{{ $tpl->key }}">{{ $tpl->body }}</textarea>
                        </div>
                        <p class="text-[11px] text-white/60">{{ $tpl->description }}</p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" class="figma-sa-settings-btn figma-sa-settings-btn--outline" onclick="alert(document.getElementById('email-subject-{{ $tpl->key }}').value + '\n\n' + document.getElementById('email-body-{{ $tpl->key }}').value)">Preview</button>
                            <button type="submit" form="email-test-form-{{ $tpl->key }}" class="figma-sa-settings-btn figma-sa-settings-btn--outline">Send Test Email</button>
                            <button type="submit" form="email-restore-form-{{ $tpl->key }}" class="figma-sa-settings-btn figma-sa-settings-btn--outline">Restore Default</button>
                            <button type="submit" class="figma-sa-settings-btn figma-sa-settings-btn--primary">Save Template</button>
                        </div>
                    </form>
                @endforeach

                <div class="figma-sa-settings-panel">
                    <h3 class="figma-sa-settings-modal-title" style="font-size:13px;">Variables Helper</h3>
                    <div class="mt-2">
                        @foreach ($emailTemplates as $tpl)
                            <template x-if="emailTab === '{{ $tpl->key }}'">
                                <div>
                                    @foreach (($tpl->variables ?? []) as $var)
                                        @php $placeholder = '{{'.$var.'}}'; @endphp
                                        <button type="button" class="figma-sa-settings-var-chip" @click="
                                            const el = document.getElementById('email-body-{{ $tpl->key }}');
                                            el.value += {{ Illuminate\Support\Js::from($placeholder) }};
                                        ">{{ $placeholder }}</button>
                                    @endforeach
                                </div>
                            </template>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Free Trial modal --}}
    <div x-show="modal === 'trial'" x-cloak class="figma-sa-settings-modal-backdrop" @keydown.escape.window="modal = null">
        <div class="figma-sa-settings-modal" style="max-width:640px;" @click.outside="modal = null">
            <button type="button" class="figma-sa-settings-modal-close" @click="modal = null">&times;</button>
            <h2 class="figma-sa-settings-modal-title">Free Trial Settings</h2>
            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="figma-sa-settings-panel space-y-4">
                @csrf
                @foreach ($trialSettings as $setting)
                    <div>
                        <label class="figma-sa-settings-row-label">{{ $setting->label ?? $setting->key }}</label>
                        @if ($setting->key === 'trial.plan_slug')
                            <select name="settings[{{ $setting->key }}]" class="figma-sa-settings-input mt-1">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->slug }}" @selected($setting->value === $plan->slug)>{{ $plan->name }} ({{ $plan->slug }})</option>
                                @endforeach
                            </select>
                        @elseif ($setting->type === 'boolean')
                            <div class="mt-2">
                                <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                <x-figma-toggle name="settings[{{ $setting->key }}]" value="1" :checked="$setting->value === '1'" :show-labels="false" />
                            </div>
                        @elseif ($setting->type === 'integer')
                            <input type="number" min="0" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="figma-sa-settings-input mt-1">
                        @else
                            <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="figma-sa-settings-input mt-1">
                        @endif
                    </div>
                @endforeach
                <div class="flex justify-end gap-2">
                    <button type="button" class="figma-sa-settings-btn figma-sa-settings-btn--outline" @click="modal = null">Cancel</button>
                    <button type="submit" class="figma-sa-settings-btn figma-sa-settings-btn--primary">Save trial settings</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Bank Details modal --}}
    <div x-show="modal === 'bank'" x-cloak class="figma-sa-settings-modal-backdrop" @click.self="modal = null" @keydown.escape.window="if (modal === 'bank') modal = null">
        <div class="figma-sa-settings-modal" style="max-width:720px;" @click.stop>
            <button type="button" class="figma-sa-settings-modal-close" @click="modal = null">&times;</button>
            <h2 class="figma-sa-settings-modal-title">Bank Transfer Details</h2>
            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="figma-sa-settings-panel grid gap-4 md:grid-cols-2">
                @csrf
                <input type="hidden" name="return_modal" value="bank">
                @forelse ($bankSettings as $index => $setting)
                    <div @class(['md:col-span-2' => $setting->type === 'text' || $setting->key === 'bank.instructions'])>
                        <label class="figma-sa-settings-row-label">{{ $setting->label ?? $setting->key }}</label>
                        <input type="hidden" name="setting_rows[{{ $index }}][key]" value="{{ $setting->key }}">
                        @if ($setting->type === 'text' || $setting->key === 'bank.instructions')
                            <textarea name="setting_rows[{{ $index }}][value]" rows="3" class="figma-sa-settings-textarea mt-1">{{ $setting->value }}</textarea>
                        @else
                            <input type="text" name="setting_rows[{{ $index }}][value]" value="{{ $setting->value }}" class="figma-sa-settings-input mt-1" autocomplete="off">
                        @endif
                    </div>
                @empty
                    <p class="md:col-span-2 text-[13px] text-white/75">No bank settings found. Save once to create them.</p>
                    @foreach ([
                        'bank.bank_name' => 'Bank name',
                        'bank.account_name' => 'Bank account holder name',
                        'bank.account_number' => 'Bank account number / IBAN',
                        'bank.swift' => 'SWIFT / BIC',
                        'bank.instructions' => 'Payment instructions',
                    ] as $fallbackKey => $fallbackLabel)
                        <div @class(['md:col-span-2' => $fallbackKey === 'bank.instructions'])>
                            <label class="figma-sa-settings-row-label">{{ $fallbackLabel }}</label>
                            <input type="hidden" name="setting_rows[{{ $loop->index }}][key]" value="{{ $fallbackKey }}">
                            @if ($fallbackKey === 'bank.instructions')
                                <textarea name="setting_rows[{{ $loop->index }}][value]" rows="3" class="figma-sa-settings-textarea mt-1">Please use your registered email as the payment reference.</textarea>
                            @else
                                <input type="text" name="setting_rows[{{ $loop->index }}][value]" value="" class="figma-sa-settings-input mt-1" autocomplete="off">
                            @endif
                        </div>
                    @endforeach
                @endforelse
                <div class="md:col-span-2 flex justify-end gap-2">
                    <button type="button" class="figma-sa-settings-btn figma-sa-settings-btn--outline" @click="modal = null">Cancel</button>
                    <button type="submit" class="figma-sa-settings-btn figma-sa-settings-btn--primary">Save bank details</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Feature Toggles modal --}}
    <div x-show="modal === 'flags'" x-cloak class="figma-sa-settings-modal-backdrop" @keydown.escape.window="modal = null" x-data="{ planId: '{{ $selectedPlan?->id }}' }">
        <div class="figma-sa-settings-modal" @click.outside="modal = null">
            <button type="button" class="figma-sa-settings-modal-close" @click="modal = null">&times;</button>
            <h2 class="figma-sa-settings-modal-title">
                <x-figma-toggle checked :show-labels="false" />
                Feature Toggles
            </h2>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-4">
                    <form method="POST" action="{{ route('super-admin.settings.save') }}" class="figma-sa-settings-panel">
                        <h3 class="figma-sa-settings-modal-title" style="font-size:15px;">Toggles</h3>
                        @csrf
                        <div class="mt-2">
                            @foreach ([
                                'domain_defaults.bot_protection_enabled' => ['label' => 'Enable Bot Protection', 'desc' => 'Detect and block bots from accessing your platform.'],
                                'domain_defaults.paid_ads_protection_enabled' => ['label' => 'Enable Paid Ads Protection', 'desc' => 'Block fraud in campaigns and paid ad sources.'],
                                'domain_defaults.auto_block_enabled' => ['label' => 'Enable Auto-Blocking', 'desc' => 'Automatically block suspicious visitors based on risk.'],
                            ] as $key => $cfg)
                                @php $setting = $domainSettings->get($key); @endphp
                                <div class="figma-sa-settings-row">
                                    <div>
                                        <p class="figma-sa-settings-row-label">{{ $cfg['label'] }}</p>
                                        <p class="text-[12px] text-white/65">{{ $cfg['desc'] }}</p>
                                    </div>
                                    <input type="hidden" name="settings[{{ $key }}]" value="0">
                                    <x-figma-toggle name="settings[{{ $key }}]" value="1" :checked="$setting?->value === '1'" :show-labels="false" />
                                </div>
                            @endforeach

                            @php $googleFlag = $flagsByKey->get('google_ads_integration'); @endphp
                            @if ($googleFlag)
                                <div class="figma-sa-settings-row">
                                    <div>
                                        <p class="figma-sa-settings-row-label">{{ $googleFlag->name }}</p>
                                        <p class="text-[12px] text-white/65">{{ $googleFlag->description }}</p>
                                    </div>
                                    <x-figma-toggle :checked="$googleFlag->enabled" :show-labels="false" onclick="document.getElementById('google-flag-toggle-form').requestSubmit();" />
                                </div>
                            @endif

                            @php $sessionFlag = $flagsByKey->get('session_recording'); @endphp
                            @if ($sessionFlag)
                                <div class="figma-sa-settings-row">
                                    <div>
                                        <p class="figma-sa-settings-row-label">{{ $sessionFlag->name }}</p>
                                        <p class="text-[12px] text-white/65">{{ $sessionFlag->description }}</p>
                                    </div>
                                    <x-figma-toggle :checked="false" disabled :show-labels="false" />
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 flex justify-end gap-2">
                            <button type="submit" form="toggles-rollback-form" onclick="return confirm('Reset protection toggles to defaults?');" class="figma-sa-settings-btn figma-sa-settings-btn--outline">Rollbacks</button>
                            <button type="submit" class="figma-sa-settings-btn figma-sa-settings-btn--primary">Save Toggles</button>
                        </div>
                    </form>

                    <form id="toggles-rollback-form" method="POST" action="{{ route('super-admin.settings.save') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="settings[domain_defaults.bot_protection_enabled]" value="1">
                        <input type="hidden" name="settings[domain_defaults.paid_ads_protection_enabled]" value="1">
                        <input type="hidden" name="settings[domain_defaults.auto_block_enabled]" value="1">
                    </form>

                    @if ($googleFlag)
                        <form id="google-flag-toggle-form" method="POST" action="{{ route('super-admin.feature-flags.toggle', $googleFlag) }}" class="hidden">@csrf @method('PATCH')</form>
                    @endif

                    <div class="figma-sa-settings-panel">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3M3 12a9 9 0 1018 0 9 9 0 00-18 0z"/></svg>
                            <h3 class="figma-sa-settings-modal-title" style="font-size:15px;">Plan-Based Toggles</h3>
                        </div>

                        <select class="figma-sa-settings-input mt-3" x-model="planId">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>

                        @foreach ($plans as $plan)
                            @php $flags = $plan->feature_flags ?? []; $limits = $plan->feature_limits ?? []; @endphp
                            <form x-show="planId == '{{ $plan->id }}'" method="POST" action="{{ route('super-admin.plans.toggles', $plan) }}" class="mt-3">
                                @csrf
                                <div class="figma-sa-settings-row">
                                    <span class="figma-sa-settings-row-label">Auto-Block Allowed</span>
                                    <input type="hidden" name="auto_block_allowed" value="0">
                                    <x-figma-toggle name="auto_block_allowed" value="1" :checked="$flags['auto_block_allowed'] ?? false" :show-labels="false" />
                                </div>
                                <div class="figma-sa-settings-row">
                                    <span class="figma-sa-settings-row-label">Export Enabled</span>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="export_days" value="{{ $limits['export_days'] ?? 30 }}" min="1" max="365" class="figma-sa-settings-input" style="width:80px;padding:6px 8px;">
                                        <span class="text-[12px] text-white/70">days</span>
                                        <input type="hidden" name="export_enabled" value="0">
                                        <x-figma-toggle name="export_enabled" value="1" :checked="$flags['export_enabled'] ?? false" :show-labels="false" />
                                    </div>
                                </div>
                                <div class="figma-sa-settings-row">
                                    <span class="figma-sa-settings-row-label">Advanced Filters Enabled</span>
                                    <input type="hidden" name="advanced_filters_enabled" value="0">
                                    <x-figma-toggle name="advanced_filters_enabled" value="1" :checked="$flags['advanced_filters_enabled'] ?? false" :show-labels="false" />
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="submit" class="figma-sa-settings-btn figma-sa-settings-btn--primary">Save Plan Toggles</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="figma-sa-settings-panel">
                        <h3 class="figma-sa-settings-modal-title" style="font-size:14px;">Variables Helper</h3>
                        <p class="mt-1 text-[11px] text-white/60">Click to add a suggested toggle.</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($suggestedFlags as $flagKey => $label)
                                @unless ($flagsByKey->has($flagKey))
                                    <form method="POST" action="{{ route('super-admin.feature-flags.store') }}">
                                        @csrf
                                        <input type="hidden" name="key" value="{{ $flagKey }}">
                                        <input type="hidden" name="name" value="{{ $label }}">
                                        <input type="hidden" name="enabled" value="1">
                                        <button type="submit" class="figma-sa-settings-var-chip">{{ $label }}</button>
                                    </form>
                                @endunless
                            @endforeach
                        </div>
                    </div>

                    <div class="figma-sa-settings-panel">
                        <h3 class="figma-sa-settings-modal-title" style="font-size:13px;">Add Toggles</h3>
                        <p class="mt-1 text-[11px] text-white/60">Test new feature and stay ahead.</p>
                        <div class="mt-2 space-y-2">
                            @forelse ($featureFlags as $flag)
                                <form method="POST" action="{{ route('super-admin.feature-flags.toggle', $flag) }}" class="flex items-center justify-between gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <span class="figma-sa-settings-row-label" style="font-size:12px;">{{ $flag->name }}</span>
                                    <button type="submit" class="figma-sa-settings-var-chip" style="width:auto;margin-bottom:0;">{{ $flag->enabled ? 'Active' : 'Unactive' }}</button>
                                </form>
                            @empty
                                <p class="text-[12px] text-white/60">No toggles yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</x-super-admin.page>
@endsection
