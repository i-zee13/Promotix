@extends('layouts.super-admin')

@section('title', 'Domains & Trackers')

@section('content')
@php
    $currentStatus = request('status', '');
    $statusTabs = [
        '' => 'All Domains',
        'connected' => 'Verified',
        'pending' => 'Pending',
        'disabled' => 'Disabled',
    ];
    $trackingFilterLabel = match (request('tracking')) {
        'enabled' => 'Tracking Enabled',
        'disabled' => 'Tracking Disabled',
        default => 'Tracker Statuses',
    };
@endphp

<x-super-admin.page title="Domains & Trackers">
    <div class="figma-sa-subs">
        <div class="figma-sa-subs-top">
            <div class="figma-sa-subs-tabs" role="tablist" aria-label="Domain verification">
                @foreach ($statusTabs as $value => $label)
                    <a
                        href="{{ route('super-admin.domains.index', array_merge(request()->except(['page', 'status']), $value !== '' ? ['status' => $value] : [])) }}"
                        @class(['figma-sa-subs-tab', 'figma-sa-subs-tab--active' => $currentStatus === $value])
                        role="tab"
                        @if ($currentStatus === $value) aria-selected="true" @endif
                    >
                        @if ($currentStatus === $value)
                            <span class="figma-sa-subs-tab-check" aria-hidden="true">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </span>
                        @endif
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="figma-sa-subs-actions">
                <a href="{{ route('super-admin.analytics.index') }}" class="figma-sa-subs-export-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2M13 3h6m0 0v6m0-6L10 12"/></svg>
                    View Analytics
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('super-admin.domains.index') }}" class="figma-sa-subs-filters" id="domains-filter-form">
            <input type="hidden" name="status" id="filter-domains-status" value="{{ $currentStatus }}">
            <input type="hidden" name="tracking" id="filter-domains-tracking" value="{{ request('tracking') }}">

            <label class="figma-sa-subs-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search domains" autocomplete="off">
            </label>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip figma-sa-subs-filter-chip--wide">
                        <span>{{ $trackingFilterLabel }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-domains-tracking').value=''; document.getElementById('domains-filter-form').submit();">Tracker Statuses</button>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-domains-tracking').value='enabled'; document.getElementById('domains-filter-form').submit();">Tracking Enabled</button>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-domains-tracking').value='disabled'; document.getElementById('domains-filter-form').submit();">Tracking Disabled</button>
            </x-super-admin.dashboard-dropdown>
        </form>

        @foreach ($domains as $domain)
            <form id="domain-tracking-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.toggle-tracking', $domain) }}" class="hidden">@csrf @method('PATCH')</form>
            <form id="domain-verify-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.force-verify', $domain) }}" class="hidden">@csrf @method('PATCH')</form>
            <form id="domain-regen-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.regenerate-tracker', $domain) }}" class="hidden">@csrf @method('PATCH')</form>
            <form id="domain-delete-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.destroy', $domain) }}" class="hidden" onsubmit="return confirm('Delete {{ $domain->hostname }}? This cannot be undone.');">@csrf @method('DELETE')</form>
        @endforeach

        <div class="figma-sa-subs-panel">
            <div class="figma-sa-subs-table-scroll">
                <table class="figma-sa-subs-table">
                    <thead>
                        <tr>
                            <th class="figma-sa-subs-th-check"><input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select all"></th>
                            <th>Domain</th>
                            <th>Owner</th>
                            <th>Verification</th>
                            <th>Last Seen</th>
                            <th>Tracking</th>
                            <th class="figma-sa-subs-th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($domains as $domain)
                            @php
                                $verifyTone = \App\Support\StatusTone::domainVerification($domain->status);
                                $verifyLabel = match ($domain->status) {
                                    'connected' => 'Verified',
                                    'disabled' => 'Disabled',
                                    default => 'Pending',
                                };
                                $trackingTone = $domain->tag_connected ? 'active' : 'suspended';
                            @endphp
                            <tr class="figma-sa-subs-row">
                                <td class="figma-sa-subs-td-check">
                                    <input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select {{ $domain->hostname }}">
                                </td>
                                <td>
                                    <div class="figma-sa-subs-user">
                                        <span class="figma-sa-subs-avatar" aria-hidden="true"></span>
                                        <span class="figma-sa-subs-user-text">
                                            <span class="figma-sa-subs-user-name">{{ $domain->hostname }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="figma-sa-subs-plan-tier">{{ $domain->user?->name ?? 'Deleted user' }}</span>
                                    <span class="figma-sa-subs-plan-detail">{{ $domain->user?->email }}</span>
                                </td>
                                <td><x-super-admin.status-pill :tone="$verifyTone" :label="$verifyLabel" /></td>
                                <td><span class="figma-sa-subs-date">{{ $domain->last_seen_at?->format('M d, Y') ?? 'Never' }}</span></td>
                                <td><x-super-admin.status-pill :tone="$trackingTone" :label="$domain->tag_connected ? 'Enabled' : 'Disabled'" /></td>
                                <td class="figma-sa-subs-td-action">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" class="figma-sa-subs-kebab" aria-label="Row actions">
                                                <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                            </button>
                                        </x-slot:trigger>
                                        <a href="https://{{ $domain->hostname }}" target="_blank" rel="noopener" class="figma-sa-users-action-item">View Domain</a>
                                        <button form="domain-tracking-{{ $domain->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">{{ $domain->tag_connected ? 'Disable Tracker' : 'Enable Tracker' }}</button>
                                        <button form="domain-verify-{{ $domain->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">Force Verify Domain</button>
                                        <a href="{{ route('super-admin.analytics.index') }}" class="figma-sa-users-action-item">View Events</a>
                                        <button form="domain-regen-{{ $domain->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">Regenerate Tracker Code</button>
                                        <button form="domain-delete-{{ $domain->id }}" type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--danger w-full text-left">Delete Domain</button>
                                    </x-super-admin.dashboard-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="figma-sa-subs-empty">No domains yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination">
                <p class="figma-sa-subs-pagination-meta">
                    @if ($domains->total())
                        Showing {{ $domains->firstItem() }}–{{ $domains->lastItem() }} of {{ $domains->total() }}
                    @else
                        Showing 0 of 0
                    @endif
                </p>
                <div class="figma-sa-subs-pagination-controls">
                    @if ($domains->hasPages())
                        <div class="figma-sa-subs-page-btns">
                            @if ($domains->onFirstPage())
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&lt;</span>
                            @else
                                <a href="{{ $domains->previousPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Previous page">&lt;</a>
                            @endif
                            <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--current">{{ $domains->currentPage() }}</span>
                            @if ($domains->hasMorePages())
                                <a href="{{ $domains->nextPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Next page">&gt;</a>
                            @else
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&gt;</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
