@extends('layouts.super-admin')

@section('title', 'System Settings')
@section('subtitle', 'Trial, bank details, branding, and feature flags')

@section('content')
<div class="space-y-6"
    x-data="{ tab: 'general' }">

    @if (session('status'))
        <div class="rounded-xl2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- Tab bar --}}
    <div class="brand-tab-bar inline-flex gap-2 rounded-full bg-night-900/60 p-1 text-xs uppercase tracking-wide">
        @foreach (['general' => 'General', 'trial' => 'Free Trial', 'bank' => 'Bank Details', 'branding' => 'Branding', 'flags' => 'Feature Flags'] as $key => $label)
            <button type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'bg-brand-500 text-white shadow-card' : 'text-slate-400 hover:text-white'"
                class="rounded-full px-4 py-2 font-semibold transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Free Trial settings --}}
    <div x-show="tab === 'trial' || tab === 'general'" class="space-y-4">
        <x-ui.card title="Free trial settings" subtitle="Applied automatically on every new tenant signup.">
            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="space-y-4">
                @csrf

                @php $trialSettings = $settingsByGroup->get('trial', collect()); @endphp

                @foreach ($trialSettings as $setting)
                    <div>
                        <label class="brand-label">{{ $setting->label ?? $setting->key }}</label>
                        @if ($setting->key === 'trial.plan_slug')
                            <select name="settings[{{ $setting->key }}]" class="brand-select mt-1">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->slug }}" @selected($setting->value === $plan->slug)>{{ $plan->name }} ({{ $plan->slug }})</option>
                                @endforeach
                            </select>
                        @elseif ($setting->type === 'boolean')
                            <label class="mt-2 inline-flex items-center gap-2">
                                <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                <input type="checkbox" name="settings[{{ $setting->key }}]" value="1" @checked($setting->value === '1') class="brand-checkbox">
                                <span class="text-sm text-night-200">Enabled</span>
                            </label>
                        @elseif ($setting->type === 'integer')
                            <input type="number" min="0" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="brand-input mt-1">
                        @else
                            <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="brand-input mt-1">
                        @endif
                        @if ($setting->description)
                            <p class="mt-1 text-xs text-night-400">{{ $setting->description }}</p>
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="brand-btn-primary">Save trial settings</button>
            </form>
        </x-ui.card>
    </div>

    {{-- Bank settings --}}
    <div x-show="tab === 'bank' || tab === 'general'" class="space-y-4" style="display:none;">
        <x-ui.card title="Bank transfer details" subtitle="Shown to customers on the upgrade plan page.">
            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="space-y-4">
                @csrf
                @php $bankSettings = $settingsByGroup->get('bank', collect()); @endphp
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($bankSettings as $setting)
                        <div @class(['md:col-span-2' => $setting->type === 'text' || $setting->key === 'bank.instructions'])>
                            <label class="brand-label">{{ $setting->label ?? $setting->key }}</label>
                            @if ($setting->type === 'text' || $setting->key === 'bank.instructions')
                                <textarea name="settings[{{ $setting->key }}]" rows="3" class="brand-input mt-1">{{ $setting->value }}</textarea>
                            @else
                                <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="brand-input mt-1">
                            @endif
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="brand-btn-primary">Save bank details</button>
            </form>
        </x-ui.card>
    </div>

    {{-- Branding settings --}}
    <div x-show="tab === 'branding' || tab === 'general'" class="space-y-4" style="display:none;">
        <x-ui.card title="Branding & support" subtitle="Company name and support contact.">
            <form method="POST" action="{{ route('super-admin.settings.save') }}" class="space-y-4">
                @csrf
                @php $brandingSettings = $settingsByGroup->get('branding', collect()); @endphp
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($brandingSettings as $setting)
                        <div>
                            <label class="brand-label">{{ $setting->label ?? $setting->key }}</label>
                            <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}" class="brand-input mt-1">
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="brand-btn-primary">Save branding</button>
            </form>
        </x-ui.card>
    </div>

    {{-- Feature flags --}}
    <div x-show="tab === 'flags' || tab === 'general'" class="grid grid-cols-1 gap-6 xl:grid-cols-3" style="display:none;">
        <x-ui.card title="Create feature flag">
            <form method="POST" action="{{ route('super-admin.feature-flags.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="brand-label">Key</label>
                    <input name="key" required placeholder="feature_key" class="brand-input mt-1">
                </div>
                <div>
                    <label class="brand-label">Name</label>
                    <input name="name" required placeholder="Feature name" class="brand-input mt-1">
                </div>
                <div>
                    <label class="brand-label">Description</label>
                    <textarea name="description" rows="3" placeholder="Description" class="brand-input mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" checked class="brand-checkbox">
                    <span class="text-sm text-night-200">Enabled</span>
                </label>
                <button type="submit" class="brand-btn-primary w-full">Create</button>
            </form>
        </x-ui.card>

        <x-ui.card class="xl:col-span-2" title="Feature flags">
            <div class="space-y-3">
                @forelse ($featureFlags as $flag)
                    <div class="flex items-center justify-between rounded-xl border border-night-700/60 bg-night-800/60 p-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-night-100">{{ $flag->name }}</p>
                            <p class="text-xs text-night-400 truncate">{{ $flag->key }} · {{ $flag->description }}</p>
                        </div>
                        <form method="POST" action="{{ route('super-admin.feature-flags.toggle', $flag) }}" class="shrink-0">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="brand-pill {{ $flag->enabled ? 'brand-pill-success' : 'brand-pill-neutral' }} cursor-pointer hover:opacity-90">
                                {{ $flag->enabled ? 'Enabled' : 'Disabled' }}
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-night-300">No feature flags yet.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
