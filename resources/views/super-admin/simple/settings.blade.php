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
@endphp
<x-super-admin.page title="System Settings">
<div class="space-y-6"
    x-data="{
        tab: 'general',
        brandingTab: 'logo',
        search: '',
        matches(label) {
            const q = this.search.trim().toLowerCase();
            return !q || String(label).toLowerCase().includes(q);
        }
    }">

    <div class="figma-sa-users-toolbar">
        <div class="figma-sa-users-search-wrap flex-1">
            <svg class="figma-sa-users-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" placeholder="Search settings" class="figma-sa-users-search-input" x-model="search">
        </div>
    </div>

    <div class="figma-sa-tabs">
        @foreach (['general' => 'General', 'trial' => 'Free Trial', 'bank' => 'Bank Details', 'branding' => 'Branding', 'flags' => 'Feature Flags'] as $key => $label)
            <button type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'is-active' : ''"
                class="figma-sa-tab"
                x-show="matches('{{ $label }}')">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Free Trial settings --}}
    <div x-show="(tab === 'trial' || tab === 'general') && matches('Free trial settings')" class="space-y-4">
        <x-super-admin.card title="Free trial settings" subtitle="Applied automatically on every new tenant signup.">
            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="space-y-4">
                @csrf

                @php $trialSettings = $settingsByGroup->get('trial', collect()); @endphp

                @foreach ($trialSettings as $setting)
                    <div x-show="matches('{{ $setting->label ?? $setting->key }}')">
                        <label class="figma-sa-label">{{ $setting->label ?? $setting->key }}</label>
                        @if ($setting->key === 'trial.plan_slug')
                            <select name="settings[{{ $setting->key }}]" class="figma-select mt-1">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->slug }}" @selected($setting->value === $plan->slug)>{{ $plan->name }} ({{ $plan->slug }})</option>
                                @endforeach
                            </select>
                        @elseif ($setting->type === 'boolean')
                            <label class="mt-2 inline-flex items-center gap-2">
                                <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                <x-figma-toggle name="settings[{{ $setting->key }}]" value="1" :checked="$setting->value === '1'" :show-labels="false" />
                                <span class="text-sm text-[#d9d9d9]">Enabled</span>
                            </label>
                        @elseif ($setting->type === 'integer')
                            <input type="number" min="0" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="figma-input mt-1">
                        @else
                            <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="figma-input mt-1">
                        @endif
                        @if ($setting->description)
                            <p class="mt-1 text-xs text-[#8c8787]">{{ $setting->description }}</p>
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save trial settings</button>
            </form>
        </x-super-admin.card>
    </div>

    {{-- Bank settings --}}
    <div x-show="tab === 'bank' || tab === 'general'" class="space-y-4" style="display:none;">
        <x-super-admin.card title="Bank transfer details" subtitle="Shown to customers on the upgrade plan page.">
            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="space-y-4">
                @csrf
                @php $bankSettings = $settingsByGroup->get('bank', collect()); @endphp
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($bankSettings as $setting)
                        <div @class(['md:col-span-2' => $setting->type === 'text' || $setting->key === 'bank.instructions']) x-show="matches('{{ $setting->label ?? $setting->key }}')">
                            <label class="figma-sa-label">{{ $setting->label ?? $setting->key }}</label>
                            @if ($setting->type === 'text' || $setting->key === 'bank.instructions')
                                <textarea name="settings[{{ $setting->key }}]" rows="3" class="figma-input mt-1">{{ $setting->value }}</textarea>
                            @else
                                <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="figma-input mt-1">
                            @endif
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save bank details</button>
            </form>
        </x-super-admin.card>
    </div>

    {{-- Branding settings --}}
    <div x-show="tab === 'branding' || tab === 'general'" class="space-y-4" style="display:none;">
        <div class="figma-sa-tabs figma-sa-tabs--sub">
            @foreach (['logo' => 'Logo', 'typography' => 'Typography', 'colors' => 'Colors', 'preview' => 'Live Preview', 'favicon' => 'Favicon', 'support' => 'Support'] as $key => $label)
                <button type="button" @click="brandingTab = '{{ $key }}'" :class="brandingTab === '{{ $key }}' ? 'is-active' : ''" class="figma-sa-tab">{{ $label }}</button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <x-super-admin.card class="xl:col-span-2" title="Branding configuration">
                <form method="POST" action="{{ route('super-admin.settings.save') }}" class="space-y-4" id="branding-form">
                    @csrf

                    <div x-show="brandingTab === 'logo'" class="space-y-4">
                        @if ($brandingLogo)
                            <div>
                                <label class="figma-sa-label">{{ $brandingLogo->label }}</label>
                                <input type="text" name="settings[branding.logo_url]" value="{{ $brandingLogo->value }}" class="figma-input mt-1">
                            </div>
                        @endif
                        @if ($brandingCompany)
                            <div>
                                <label class="figma-sa-label">{{ $brandingCompany->label }}</label>
                                <input type="text" name="settings[branding.company_name]" value="{{ $brandingCompany->value }}" class="figma-input mt-1">
                            </div>
                        @endif
                    </div>

                    <div x-show="brandingTab === 'typography'" class="grid gap-4 md:grid-cols-2">
                        @if ($brandingFontFamily)
                            <div>
                                <label class="figma-sa-label">{{ $brandingFontFamily->label }}</label>
                                <input type="text" name="settings[branding.font_family]" value="{{ $brandingFontFamily->value }}" class="figma-input mt-1">
                            </div>
                        @endif
                        @if ($brandingFontSize)
                            <div>
                                <label class="figma-sa-label">{{ $brandingFontSize->label }}</label>
                                <input type="number" min="10" max="24" name="settings[branding.font_size_base]" value="{{ $brandingFontSize->value }}" class="figma-input mt-1">
                            </div>
                        @endif
                    </div>

                    <div x-show="brandingTab === 'colors'" class="grid gap-4 md:grid-cols-3">
                        @foreach ([$brandingPrimary, $brandingSecondary, $brandingBackground] as $setting)
                            @if ($setting)
                                <div>
                                    <label class="figma-sa-label">{{ $setting->label }}</label>
                                    <input type="color" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="mt-2 h-10 w-full cursor-pointer rounded border-0 bg-transparent">
                                    <input type="text" value="{{ $setting->value }}" class="figma-input mt-2" readonly>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div x-show="brandingTab === 'favicon'">
                        @if ($brandingFavicon)
                            <label class="figma-sa-label">{{ $brandingFavicon->label }}</label>
                            <input type="text" name="settings[branding.favicon_url]" value="{{ $brandingFavicon->value }}" class="figma-input mt-1">
                        @endif
                    </div>

                    <div x-show="brandingTab === 'support'" class="grid gap-4 md:grid-cols-2">
                        @if ($brandingSupport)
                            <div>
                                <label class="figma-sa-label">{{ $brandingSupport->label }}</label>
                                <input type="email" name="settings[branding.support_email]" value="{{ $brandingSupport->value }}" class="figma-input mt-1">
                            </div>
                        @endif
                    </div>

                    <div x-show="brandingTab !== 'preview'">
                        <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save branding</button>
                    </div>
                </form>
            </x-super-admin.card>

            <div class="xl:col-span-1">
            <x-super-admin.card title="Live preview">
                <div class="figma-sa-brand-preview"
                    style="--preview-bg: {{ $brandingBackground->value ?? '#0D0D0D' }}; --preview-primary: {{ $brandingPrimary->value ?? '#6400B2' }}; --preview-font: {{ $brandingFontFamily->value ?? 'Inter' }}; --preview-size: {{ ($brandingFontSize->value ?? 16).'px' }};">
                    <div class="figma-sa-brand-preview-header">
                        @if ($brandingLogo?->value)
                            <img src="{{ $brandingLogo->value }}" alt="Logo" class="h-8 max-w-[140px] object-contain" onerror="this.style.display='none'">
                        @endif
                        <span>{{ $brandingCompany->value ?? 'Promotix' }}</span>
                    </div>
                    <div class="figma-sa-brand-preview-body">
                        <p class="figma-sa-brand-preview-title">Customer portal preview</p>
                        <button type="button" class="figma-sa-brand-preview-btn">Primary action</button>
                        <p class="figma-sa-brand-preview-muted">Support: {{ $brandingSupport->value ?? 'support@promotix.local' }}</p>
                    </div>
                </div>
            </x-super-admin.card>
            </div>
        </div>
    </div>

    {{-- Feature flags --}}
    <div x-show="tab === 'flags' || tab === 'general'" class="grid grid-cols-1 gap-6 xl:grid-cols-3" style="display:none;">
        <x-super-admin.card title="Create feature flag">
            <form method="POST" action="{{ route('super-admin.feature-flags.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="figma-sa-label">Key</label>
                    <input name="key" required placeholder="feature_key" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Name</label>
                    <input name="name" required placeholder="Feature name" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Description</label>
                    <textarea name="description" rows="3" placeholder="Description" class="figma-input mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <x-figma-toggle name="enabled" value="1" checked :show-labels="false" />
                    <span class="text-sm text-[#d9d9d9]">Enabled</span>
                </label>
                <button type="submit" class="figma-sa-btn figma-sa-btn-primary w-full">Create</button>
            </form>
        </x-super-admin.card>

        <x-super-admin.card class="xl:col-span-2" title="Feature flags">
            <div class="space-y-3">
                @forelse ($featureFlags as $flag)
                    <div class="figma-sa-row flex items-center justify-between p-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-white">{{ $flag->name }}</p>
                            <p class="text-xs text-[#8c8787] truncate">{{ $flag->key }} · {{ $flag->description }}</p>
                        </div>
                        <form method="POST" action="{{ route('super-admin.feature-flags.toggle', $flag) }}" class="shrink-0">
                            @csrf
                            @method('PATCH')
                            <x-figma-toggle
                                :checked="$flag->enabled"
                                label-on="On"
                                label-off="Off"
                                onchange="this.closest('form').submit()"
                            />
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-[#a9a9a9]">No feature flags yet.</p>
                @endforelse
            </div>
        </x-super-admin.card>
    </div>
</div>
</x-super-admin.page>
@endsection
