@extends('layouts.super-admin')

@section('title', 'Billing Automation')
@section('content')
<x-super-admin.page title="Billing Automation" subtitle="Trial, payment, and invoice automation toggles">
<div class="space-y-6">
    <form method="POST" action="{{ route('super-admin.billing-automation.update') }}" class="space-y-6">
        @csrf
        @foreach ($groups as $groupLabel => $keys)
            <x-super-admin.card>
                <h2 class="text-base font-semibold text-white">{{ $groupLabel }}</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($keys as $key)
                        @php $row = $settings->get($key); @endphp
                        <div class="figma-sa-row flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-white">{{ str($key)->replace('_', ' ')->title() }}</p>
                                <p class="text-xs text-[#8c8787]">{{ $key }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-2 text-sm text-[#d9d9d9]">
                                    <input type="hidden" name="settings[{{ $key }}][enabled]" value="0">
                                    <input type="checkbox" name="settings[{{ $key }}][enabled]" value="1" class="rounded border-white/30 text-[#6400B2] focus:ring-[#6400B2]" @checked($row?->is_enabled ?? false)>
                                    Enabled
                                </label>
                                <input type="text" name="settings[{{ $key }}][value]" value="{{ old("settings.{$key}.value", $row?->setting_value) }}" placeholder="Value" class="figma-input w-28 text-sm">
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-super-admin.card>
        @endforeach
        <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save settings</button>
    </form>

</div>
</x-super-admin.page>
@endsection
