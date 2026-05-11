@extends('layouts.super-admin')

@section('title', 'System Settings')
@section('subtitle', 'Feature flags and plan-scoped capability switches')

@section('content')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card>
            <h2 class="text-base font-semibold text-night-100">Create Feature Flag</h2>
            <form method="POST" action="{{ route('super-admin.feature-flags.store') }}" class="mt-4 space-y-3">
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
                <button class="brand-btn-primary w-full">Create</button>
            </form>
        </x-ui.card>

        <x-ui.card class="xl:col-span-2">
            <h2 class="text-base font-semibold text-night-100">Feature Flags</h2>
            <div class="mt-4 space-y-3">
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
@endsection
