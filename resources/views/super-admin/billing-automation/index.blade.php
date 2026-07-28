@extends('layouts.super-admin')

@section('title', 'Billing Automation')
@section('content')
<x-super-admin.page title="Billing Automation" subtitle="Trial, payment, and invoice automation toggles">
<div class="figma-sa-billing-auto space-y-6">
    <form method="POST" action="{{ route('super-admin.billing-automation.update') }}" class="space-y-6">
        @csrf
        @foreach ($groups as $groupLabel => $keys)
            <section class="figma-sa-billing-auto-card">
                <header class="figma-sa-billing-auto-card__head">
                    <h2>{{ $groupLabel }}</h2>
                </header>
                <div class="figma-sa-billing-auto-card__body">
                    @foreach ($keys as $key)
                        @php $row = $settings->get($key); @endphp
                        <div class="figma-sa-billing-auto-row">
                            <div>
                                <p class="figma-sa-billing-auto-row__title">{{ str($key)->replace('_', ' ')->title() }}</p>
                                <p class="figma-sa-billing-auto-row__key">{{ $key }}</p>
                            </div>
                            <div class="figma-sa-billing-auto-row__controls">
                                <label class="figma-sa-billing-auto-row__enabled">
                                    <input type="hidden" name="settings[{{ $key }}][enabled]" value="0">
                                    <x-figma-toggle
                                        name="settings[{{ $key }}][enabled]"
                                        value="1"
                                        :checked="$row?->is_enabled ?? false"
                                        label-on="On"
                                        label-off="Off"
                                    />
                                    Enabled
                                </label>
                                <input type="text" name="settings[{{ $key }}][value]" value="{{ old("settings.{$key}.value", $row?->setting_value) }}" placeholder="Value" class="figma-input figma-sa-billing-auto-input">
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save settings</button>
    </form>
</div>
</x-super-admin.page>
@endsection
