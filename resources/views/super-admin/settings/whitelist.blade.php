@extends('layouts.super-admin')

@section('title', 'IP / Provider Lists')

@section('content')
@php
    $currentKind = request('kind', '');
    $currentList = request('list', '');
    $kindTabs = [
        '' => 'All entries',
        'provider' => 'Providers',
        'cidr' => 'Custom IPs',
    ];
    $listTabs = [
        '' => 'Allow + Block',
        'allow' => 'Whitelisted',
        'block' => 'Blocklisted',
    ];
@endphp

<x-super-admin.page title="IP / Provider Lists" subtitle="Whitelist providers/IPs to always allow, or blocklist them to block every matching IP across all domains">
    <div class="figma-sa-subs">
        <section class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 lg:grid-cols-5">
            <x-super-admin.kpi label="Whitelisted providers" :value="number_format($stats['allow_providers'])" />
            <x-super-admin.kpi label="Blocklisted providers" :value="number_format($stats['block_providers'])" />
            <x-super-admin.kpi label="Whitelisted IPs" :value="number_format($stats['allow_ips'])" />
            <x-super-admin.kpi label="Blocklisted IPs" :value="number_format($stats['block_ips'])" />
            <x-super-admin.kpi label="Disabled / Off" :value="number_format($stats['disabled'])" />
        </section>

        <div class="figma-sa-subs-top">
            <div class="figma-sa-subs-tabs" role="tablist" aria-label="Entry type">
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

        <div class="figma-sa-subs-tabs" role="tablist" aria-label="List type">
            @foreach ($listTabs as $value => $label)
                <a
                    href="{{ route('super-admin.settings.whitelist', array_merge(request()->except(['page', 'list']), $value !== '' ? ['list' => $value] : [])) }}"
                    @class(['figma-sa-subs-tab', 'figma-sa-subs-tab--active' => $currentList === $value])
                    role="tab"
                >{{ $label }}</a>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <form method="POST" action="{{ route('super-admin.settings.whitelist.store') }}" class="figma-sa-subs-filters flex-wrap !items-end">
                @csrf
                <input type="hidden" name="kind" value="cidr">
                <input type="hidden" name="list_type" value="allow">
                <label class="figma-sa-subs-search !max-w-none flex-1">
                    <span class="sr-only">IP or CIDR</span>
                    <input type="text" name="value" value="{{ old('list_type') === 'allow' ? old('value') : '' }}" placeholder="Whitelist IP / CIDR" required>
                </label>
                <div class="figma-sa-subs-actions">
                    <button type="submit" class="figma-sa-subs-export-btn" style="background:#16a34a;color:#fff;border-color:#16a34a;">+ Whitelist IP</button>
                </div>
            </form>

            <form method="POST" action="{{ route('super-admin.settings.whitelist.store') }}" class="figma-sa-subs-filters flex-wrap !items-end">
                @csrf
                <input type="hidden" name="kind" value="cidr">
                <input type="hidden" name="list_type" value="block">
                <label class="figma-sa-subs-search !max-w-none flex-1">
                    <span class="sr-only">IP or CIDR</span>
                    <input type="text" name="value" value="{{ old('list_type') === 'block' ? old('value') : '' }}" placeholder="Blocklist IP / CIDR" required>
                </label>
                <div class="figma-sa-subs-actions">
                    <button type="submit" class="figma-sa-subs-export-btn" style="background:#dc2626;color:#fff;border-color:#dc2626;">+ Blocklist IP</button>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('super-admin.settings.whitelist.store') }}" class="figma-sa-subs-filters flex-wrap !items-end" id="provider-list-form">
            @csrf
            <input type="hidden" name="kind" value="provider">
            <input type="hidden" name="value" id="provider-value-field" value="google">
            <label class="figma-sa-subs-search !max-w-[200px]">
                <span class="sr-only">Provider</span>
                <select name="provider" id="provider-select" class="w-full rounded-[6px] border border-white/10 bg-[#101010] px-3 py-2 text-[12px] text-white" required>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider }}">{{ ucfirst($provider) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="figma-sa-subs-search !max-w-[180px]">
                <span class="sr-only">Mode</span>
                <select name="list_type" class="w-full rounded-[6px] border border-white/10 bg-[#101010] px-3 py-2 text-[12px] text-white" required>
                    <option value="allow">Whitelist provider</option>
                    <option value="block">Blocklist provider</option>
                </select>
            </label>
            <div class="figma-sa-subs-actions">
                <button type="submit" class="figma-sa-subs-export-btn" style="background:#FF6600;color:#fff;border-color:#FF6600;">Apply provider</button>
            </div>
        </form>
        @error('value')
            <p class="text-[12px] text-rose-300">{{ $message }}</p>
        @enderror
        @error('provider')
            <p class="text-[12px] text-rose-300">{{ $message }}</p>
        @enderror

        <p class="text-[11px] text-[#a9a9a9]">
            Example: put <strong class="text-white/80">Google</strong> on Whitelist → every Google IP on user domains shows as whitelisted / never blocked.
            Put Google on Blocklist → every Google CIDR/ASN match is blocked platform-wide.
        </p>

        <form method="GET" action="{{ route('super-admin.settings.whitelist') }}" class="figma-sa-subs-filters">
            <input type="hidden" name="kind" value="{{ $currentKind }}">
            <input type="hidden" name="list" value="{{ $currentList }}">
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
                            <th>List</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="figma-sa-subs-th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            @php
                                $isBlock = ($entry->list_type ?: 'allow') === 'block';
                                $listTone = ! $entry->enabled ? 'suspended' : ($isBlock ? 'blocked' : 'active');
                                $listLabel = ! $entry->enabled ? 'Off' : ($isBlock ? 'Blocklisted' : 'Whitelisted');
                            @endphp
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
                                        :tone="$listTone"
                                        :label="$listLabel" />
                                </td>
                                <td>
                                    <span class="figma-sa-subs-plan-detail">{{ $entry->enabled ? 'Active' : 'Disabled' }}</span>
                                </td>
                                <td><span class="figma-sa-subs-date">{{ $entry->updated_at?->timezone(config('app.timezone'))->format('M d, Y') ?? '—' }}</span></td>
                                <td class="figma-sa-subs-td-action">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" class="figma-sa-subs-kebab" aria-label="Row actions">
                                                <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                            </button>
                                        </x-slot:trigger>
                                        <button form="allowlist-mode-allow-{{ $entry->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">Whitelist</button>
                                        <button form="allowlist-mode-block-{{ $entry->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">Blocklist</button>
                                        <button form="allowlist-mode-off-{{ $entry->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">Turn off</button>
                                        @if ($entry->kind !== 'provider')
                                            <button form="allowlist-delete-{{ $entry->id }}" type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--danger w-full text-left" onclick="return confirm('Remove this IP entry?')">Delete</button>
                                        @endif
                                    </x-super-admin.dashboard-dropdown>
                                    <form id="allowlist-mode-allow-{{ $entry->id }}" method="POST" action="{{ route('super-admin.settings.whitelist.mode', $entry) }}" class="hidden">@csrf @method('PATCH')<input type="hidden" name="mode" value="allow"></form>
                                    <form id="allowlist-mode-block-{{ $entry->id }}" method="POST" action="{{ route('super-admin.settings.whitelist.mode', $entry) }}" class="hidden">@csrf @method('PATCH')<input type="hidden" name="mode" value="block"></form>
                                    <form id="allowlist-mode-off-{{ $entry->id }}" method="POST" action="{{ route('super-admin.settings.whitelist.mode', $entry) }}" class="hidden">@csrf @method('PATCH')<input type="hidden" name="mode" value="off"></form>
                                    @if ($entry->kind !== 'provider')
                                        <form id="allowlist-delete-{{ $entry->id }}" method="POST" action="{{ route('super-admin.settings.whitelist.destroy', $entry) }}" class="hidden">@csrf @method('DELETE')</form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="figma-sa-subs-empty">No entries yet.</td>
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

<script>
(() => {
    const select = document.getElementById('provider-select');
    const hidden = document.getElementById('provider-value-field');
    if (!select || !hidden) return;
    const sync = () => { hidden.value = select.value; };
    select.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
