@extends('layouts.super-admin')

@section('title', 'Domains & Trackers')
@section('content')
<x-super-admin.page title="Domains & Trackers">
    <div class="space-y-[14px]">
        <form method="GET" class="flex flex-wrap items-center gap-[8px]">
            <label class="figma-sa-dash-search !min-w-[220px]">
                <svg class="h-[18px] w-[18px] shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search domains" class="figma-sa-dash-search-input">
            </label>
            <select name="status" onchange="this.form.submit()" class="figma-select h-[34px] !text-[16px]">
                <option value="">Verification Filter</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="connected" @selected(request('status') === 'connected')>Verified</option>
                <option value="disabled" @selected(request('status') === 'disabled')>Disabled</option>
            </select>
            <select name="tracking" onchange="this.form.submit()" class="figma-select h-[34px] !text-[16px]">
                <option value="">Tracker Statuses</option>
                <option value="enabled" @selected(request('tracking') === 'enabled')>Tracking Enabled</option>
                <option value="disabled" @selected(request('tracking') === 'disabled')>Tracking Disabled</option>
            </select>
            <button type="submit" class="figma-sa-btn figma-sa-btn-outline !px-4 !py-2 text-[13px]">Filter</button>

            <a href="{{ route('super-admin.analytics.index') }}" class="ml-auto inline-flex h-[43px] items-center gap-[6px] rounded-[6px] bg-[#6706b3] px-[16px] text-[16px] font-medium text-white hover:bg-[#7a1acc]">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                View Analytics
            </a>
        </form>

        @foreach ($domains as $domain)
            <form id="domain-tracking-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.toggle-tracking', $domain) }}" class="hidden">@csrf @method('PATCH')</form>
            <form id="domain-verify-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.force-verify', $domain) }}" class="hidden">@csrf @method('PATCH')</form>
            <form id="domain-regen-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.regenerate-tracker', $domain) }}" class="hidden">@csrf @method('PATCH')</form>
            <form id="domain-delete-{{ $domain->id }}" method="POST" action="{{ route('super-admin.domains.destroy', $domain) }}" class="hidden" onsubmit="return confirm('Delete {{ $domain->hostname }}? This cannot be undone.');">@csrf @method('DELETE')</form>
        @endforeach

        <div class="figma-sa-subs-panel overflow-hidden rounded-[6px] bg-[#6400b3]">
            <div class="overflow-x-auto">
                <table class="figma-sa-subs-table min-w-[960px] w-full">
                    <thead>
                        <tr>
                            <th class="w-[48px]"></th>
                            <th>Domain</th>
                            <th>Owner</th>
                            <th>Verification</th>
                            <th>Last Seen</th>
                            <th>Tracking</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($domains as $i => $domain)
                            @php
                                $verifyClass = match ($domain->status) {
                                    'connected' => 'is-active',
                                    'disabled' => 'is-cancelled',
                                    default => 'is-paused',
                                };
                                $verifyLabel = match ($domain->status) {
                                    'connected' => 'Verified',
                                    'disabled' => 'Disabled',
                                    default => 'Pending',
                                };
                            @endphp
                            <tr @class(['figma-sa-subs-row', 'is-alt' => $i % 2 === 1])>
                                <td><input type="checkbox" class="figma-sa-checkbox rounded" aria-label="Select row"></td>
                                <td>
                                    <div class="flex items-center gap-[10px]">
                                        <span class="figma-sa-subs-avatar">{{ strtoupper(substr($domain->hostname, 0, 1)) }}</span>
                                        <p class="truncate text-[16px] font-medium text-white">{{ $domain->hostname }}</p>
                                    </div>
                                </td>
                                <td>
                                    <p class="truncate text-[16px] font-medium text-white">{{ $domain->user?->name ?? 'Deleted user' }}</p>
                                    <p class="truncate text-[13px] font-medium text-white/80">{{ $domain->user?->email }}</p>
                                </td>
                                <td><span class="figma-sa-subs-status {{ $verifyClass }}">{{ $verifyLabel }}</span></td>
                                <td class="text-[14px] font-medium text-white">{{ $domain->last_seen_at?->format('M d,Y') ?? 'Never' }}</td>
                                <td>
                                    <span class="figma-sa-subs-status {{ $domain->tag_connected ? 'is-active' : 'is-paused' }}">{{ $domain->tag_connected ? 'Enabled' : 'Tracking Disabled' }}</span>
                                </td>
                                <td class="text-right">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" @click="open = !open" class="figma-sa-dash-row-menu" aria-label="Row actions">⋯</button>
                                        </x-slot:trigger>
                                        <a href="https://{{ $domain->hostname }}" target="_blank" rel="noopener" class="figma-sa-dash-dropdown-item block text-left">View Domain</a>
                                        <button form="domain-tracking-{{ $domain->id }}" type="submit" class="figma-sa-dash-dropdown-item block w-full text-left">{{ $domain->tag_connected ? 'Disable Tracker' : 'Enable Tracker' }}</button>
                                        <button form="domain-verify-{{ $domain->id }}" type="submit" class="figma-sa-dash-dropdown-item block w-full text-left">Force Verify Domain</button>
                                        <a href="{{ route('super-admin.analytics.index') }}" class="figma-sa-dash-dropdown-item block text-left">View Events</a>
                                        <button form="domain-regen-{{ $domain->id }}" type="submit" class="figma-sa-dash-dropdown-item block w-full text-left">Regenerate Tracker Code</button>
                                        <button form="domain-delete-{{ $domain->id }}" type="submit" class="figma-sa-dash-dropdown-item block w-full text-left text-red-300">Delete Domain</button>
                                    </x-super-admin.dashboard-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-white/70">No domains yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination flex flex-wrap items-center justify-between gap-[10px] px-[24px] py-[16px]">
                <p class="text-[16px] font-medium text-white/90">Showing {{ $domains->firstItem() ?? 0 }}-{{ $domains->lastItem() ?? 0 }} of {{ $domains->total() }}</p>
                <div>{{ $domains->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
