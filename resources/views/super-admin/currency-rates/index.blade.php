@extends('layouts.super-admin')

@section('title', 'Currency Rates')

@section('content')
@php
    $statusLabel = match (request('status')) {
        'active' => 'Active',
        'inactive' => 'Inactive',
        default => 'All statuses',
    };
@endphp

<x-super-admin.page title="Currency Rates">
    <p class="mb-4 text-[13px] text-white/55">
        Units per 1 USD (e.g. PKR 278.50 means 1 USD = 278.50 PKR). All Domains waste prevented converts into the viewer’s timezone currency using these rates.
    </p>

    @include('partials.super-admin.flash')

    <div
        class="figma-sa-products"
        x-data="{
            createOpen: false,
            editOpen: false,
            editing: null,
            openEdit(row) {
                this.editing = {...row};
                this.editOpen = true;
            },
        }"
    >
        <form method="GET" action="{{ route('super-admin.currency-rates.index') }}" class="figma-sa-products-toolbar" id="currency-filter-form">
            <input type="hidden" name="status" id="filter-currency-status" value="{{ request('status') }}">
            <div class="figma-sa-products-toolbar-group">
                <label class="figma-sa-products-search-chip">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search code, country…" autocomplete="off">
                </label>
                <x-super-admin.dashboard-dropdown align="left">
                    <x-slot:trigger>
                        <button type="button" @click="open = !open" class="figma-sa-products-filter-chip">
                            <span>{{ $statusLabel }}</span>
                            <span class="figma-sa-products-chip-chevron">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </button>
                    </x-slot:trigger>
                    @foreach ([['value' => '', 'label' => 'All statuses'], ['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive']] as $fs)
                        <button type="button"
                            class="figma-sa-users-filter-option"
                            onclick="document.getElementById('filter-currency-status').value='{{ $fs['value'] }}'; document.getElementById('currency-filter-form').submit();">
                            {{ $fs['label'] }}
                        </button>
                    @endforeach
                </x-super-admin.dashboard-dropdown>
                <button type="submit" class="figma-sa-integration-btn">Filter</button>
            </div>
            <button type="button" class="figma-sa-integration-btn figma-sa-integration-btn--solid" @click="createOpen = true">+ Add currency</button>
        </form>

        <div class="mt-4 overflow-hidden rounded-[12px] border border-white/10">
            <table class="min-w-full text-left text-[13px]">
                <thead class="bg-white/5 text-[11px] uppercase tracking-wide text-white/45">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Country</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Symbol</th>
                        <th class="px-4 py-3">Units / 1 USD</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rates as $rate)
                        <tr class="border-t border-white/8">
                            <td class="px-4 py-3 font-semibold">{{ $rate->code }}</td>
                            <td class="px-4 py-3 text-white/70">{{ $rate->country_code ?: '—' }} · {{ $rate->country_name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $rate->name }}</td>
                            <td class="px-4 py-3 font-mono">{{ $rate->symbol }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $rate->units_per_usd, 6) }}</td>
                            <td class="px-4 py-3">
                                <span class="figma-sa-integration-pill {{ $rate->is_active ? 'is-connected' : 'is-muted' }}">
                                    {{ $rate->is_active ? 'Active' : 'Off' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="figma-sa-integration-btn"
                                    @click="openEdit({{ \Illuminate\Support\Js::from([
                                        'id' => $rate->id,
                                        'code' => $rate->code,
                                        'country_code' => $rate->country_code,
                                        'country_name' => $rate->country_name,
                                        'name' => $rate->name,
                                        'symbol' => $rate->symbol,
                                        'units_per_usd' => $rate->units_per_usd,
                                        'is_active' => (bool) $rate->is_active,
                                        'update_url' => route('super-admin.currency-rates.update', $rate),
                                        'delete_url' => route('super-admin.currency-rates.destroy', $rate),
                                    ]) }})"
                                >Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-white/45">No currency rates yet. Run migrations or add one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $rates->links() }}</div>

        {{-- Create --}}
        <div x-show="createOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4" @keydown.escape.window="createOpen = false">
            <div class="w-full max-w-lg rounded-[12px] border border-white/15 bg-[#121212] p-5 text-white shadow-2xl" @click.stop>
                <h3 class="text-[16px] font-semibold">Add currency rate</h3>
                <form method="POST" action="{{ route('super-admin.currency-rates.store') }}" class="mt-4 grid gap-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-[12px] text-white/60">Code<input name="code" required maxlength="3" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" placeholder="PKR"></label>
                        <label class="text-[12px] text-white/60">Country code<input name="country_code" maxlength="2" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" placeholder="PK"></label>
                    </div>
                    <label class="text-[12px] text-white/60">Country name<input name="country_name" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" placeholder="Pakistan"></label>
                    <label class="text-[12px] text-white/60">Currency name<input name="name" required class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" placeholder="Pakistani Rupee"></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-[12px] text-white/60">Symbol<input name="symbol" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" placeholder="Rs "></label>
                        <label class="text-[12px] text-white/60">Units per 1 USD<input name="units_per_usd" type="number" step="0.000001" min="0.000001" required class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" placeholder="278.5"></label>
                    </div>
                    <label class="inline-flex items-center gap-2 text-[12px] text-white/70"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <div class="mt-2 flex justify-end gap-2">
                        <button type="button" class="figma-sa-integration-btn" @click="createOpen = false">Cancel</button>
                        <button type="submit" class="figma-sa-integration-btn figma-sa-integration-btn--solid">Save</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit --}}
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4" @keydown.escape.window="editOpen = false">
            <div class="w-full max-w-lg rounded-[12px] border border-white/15 bg-[#121212] p-5 text-white shadow-2xl" @click.stop x-show="editing">
                <h3 class="text-[16px] font-semibold">Edit currency rate</h3>
                <form method="POST" :action="editing?.update_url" class="mt-4 grid gap-3">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-[12px] text-white/60">Code<input name="code" required maxlength="3" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" x-model="editing.code"></label>
                        <label class="text-[12px] text-white/60">Country code<input name="country_code" maxlength="2" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" x-model="editing.country_code"></label>
                    </div>
                    <label class="text-[12px] text-white/60">Country name<input name="country_name" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" x-model="editing.country_name"></label>
                    <label class="text-[12px] text-white/60">Currency name<input name="name" required class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" x-model="editing.name"></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-[12px] text-white/60">Symbol<input name="symbol" class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" x-model="editing.symbol"></label>
                        <label class="text-[12px] text-white/60">Units per 1 USD<input name="units_per_usd" type="number" step="0.000001" min="0.000001" required class="mt-1 w-full rounded-md border border-white/15 bg-black/40 px-3 py-2 text-[13px]" x-model="editing.units_per_usd"></label>
                    </div>
                    <label class="inline-flex items-center gap-2 text-[12px] text-white/70">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="editing.is_active"> Active
                    </label>
                    <div class="mt-2 flex justify-between gap-2">
                        <button type="submit" formaction="" formmethod="POST" class="figma-sa-integration-btn text-rose-300"
                            @click.prevent="if (confirm('Delete this currency rate?')) { const f = $el.closest('form'); f.action = editing.delete_url; f.querySelector('[name=_method]').value = 'DELETE'; f.submit(); }">
                            Delete
                        </button>
                        <div class="flex gap-2">
                            <button type="button" class="figma-sa-integration-btn" @click="editOpen = false">Cancel</button>
                            <button type="submit" class="figma-sa-integration-btn figma-sa-integration-btn--solid">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
