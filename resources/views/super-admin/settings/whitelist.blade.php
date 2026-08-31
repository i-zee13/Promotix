@extends('layouts.super-admin')

@section('title', 'IP / Provider Whitelist')

@section('content')
@php
    $currentKind = request('kind', '');
    $kindTabs = [
        '' => 'All entries',
        'provider' => 'Providers',
        'cidr' => 'Custom IPs',
    ];
@endphp

<x-super-admin.page title="IP / Provider Whitelist" subtitle="Global allow list — these IPs and providers are never blocked">
    <div class="figma-sa-subs">
        <section class="grid grid-cols-1 gap-[14px] sm:grid-cols-3">
            <x-super-admin.kpi label="Active providers" :value="number_format($stats['providers'])" />
            <x-super-admin.kpi label="Custom IPs / CIDRs" :value="number_format($stats['ips'])" />
            <x-super-admin.kpi label="Disabled" :value="number_format($stats['disabled'])" />
        </section>

        <div class="figma-sa-subs-top">
            <div class="figma-sa-subs-tabs" role="tablist" aria-label="Whitelist type">
                @foreach ($kindTabs as $value => $label)
                    <a
                        href="{{ route('super-admin.settings.whitelist', array_merge(request()->except(['page', 'kind']), $value !== '' ? ['kind' => $value] : [])) }}"
                        @class(['figma-sa-subs-tab', 'figma-sa-subs-tab--active' => $currentKind === $value])
                        role="tab"
                    >{{ $label }}</a>
                @endforeach
            </div>
            <a href="{{ route('super-admin.settings.index') }}" class="figma-sa-subs-export-btn">← System Settings</a>
        </div>

        <form method="POST" action="{{ route('super-admin.settings.whitelist.store') }}" class="figma-sa-subs-filters flex-wrap">
            @csrf
            <input type="hidden" name="kind" value="cidr">
            <label class="figma-sa-subs-search !max-w-none flex-1">
                <span class="sr-only">IP or CIDR</span>
                <input type="text" name="value" value="{{ old('value') }}" placeholder="Add IP or CIDR (66.249.88.8 or 66.249.0.0/16)" required>
            </label>
            <label class="figma-sa-subs-search !max-w-[220px]">
                <span class="sr-only">Label</span>
                <input type="text" name="label" value="{{ old('label') }}" placeholder="Label (optional)">
            </label>
            <div class="figma-sa-subs-actions">
                <button type="submit" class="figma-sa-subs-export-btn" style="background:#FF6600;color:#fff;border-color:#FF6600;">+ Add to whitelist</button>
            </div>
        </form>
        @error('value')
            <p class="text-[12px] text-rose-300">{{ $message }}</p>
        @enderror

        <form method="GET" action="{{ route('super-admin.settings.whitelist') }}" class="figma-sa-subs-filters">
            <input type="hidden" name="kind" value="{{ $currentKind }}">
            <label class="figma-sa-subs-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search provider or IP" autocomplete="off">
            </label>
        </form>

        <div class="figma-sa-subs-table-shell">
            <div class="figma-sa-table-scroll">
                <table class="figma-sa-subs-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Provider / IP</th>
                            <th>Label</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="figma-sa-subs-th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="figma-sa-subs-row">
                                <td>
                                    <span class="figma-sa-subs-plan-tier">{{ $entry->kind === 'provider' ? 'Provider' : 'IP / CIDR' }}</span>
                                </td>
                                <td>
                                    <span class="figma-sa-subs-plan-tier font-mono">{{ $entry->value }}</span>
                                    @if ($entry->kind === 'provider')
                                        <span class="figma-sa-subs-plan-detail">{{ count(\App\Support\GlobalIpAllowlist::providerCidrs()[$entry->provider] ?? []) }} CIDR ranges</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="figma-sa-subs-user-name">{{ $entry->label ?: '—' }}</span>
                                    @if ($entry->notes)
                                        <span class="figma-sa-subs-plan-detail">{{ $entry->notes }}</span>
                                    @endif
                                </td>
                                <td>
                                    <x-super-admin.status-pill
                                        :tone="$entry->enabled ? 'active' : 'suspended'"
                                        :label="$entry->enabled ? 'Whitelisted' : 'Off'" />
                                </td>
                                <td><span class="figma-sa-subs-date">{{ $entry->updated_at?->timezone(config('app.timezone'))->format('M d, Y') ?? '—' }}</span></td>
                                <td class="figma-sa-subs-td-action">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" class="figma-sa-subs-kebab" aria-label="Row actions">
                                                <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                            </button>
                                        </x-slot:trigger>
                                        <button form="allowlist-toggle-{{ $entry->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">
                                            {{ $entry->enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                        @if ($entry->kind !== 'provider')
                                            <button form="allowlist-delete-{{ $entry->id }}" type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--danger w-full text-left" onclick="return confirm('Remove this IP from the whitelist?')">Delete</button>
                                        @endif
                                    </x-super-admin.dashboard-dropdown>
                                    <form id="allowlist-toggle-{{ $entry->id }}" method="POST" action="{{ route('super-admin.settings.whitelist.toggle', $entry) }}" class="hidden">@csrf @method('PATCH')</form>
                                    @if ($entry->kind !== 'provider')
                                        <form id="allowlist-delete-{{ $entry->id }}" method="POST" action="{{ route('super-admin.settings.whitelist.destroy', $entry) }}" class="hidden">@csrf @method('DELETE')</form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="figma-sa-subs-empty">No whitelist entries yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination">
                <p class="figma-sa-subs-pagination-meta">
                    @if ($entries->total())
                        Showing {{ $entries->firstItem() }}–{{ $entries->lastItem() }} of {{ $entries->total() }}
                    @else
                        Showing 0 of 0
                    @endif
                </p>
                <div>{{ $entries->links() }}</div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
