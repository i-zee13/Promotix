@extends('layouts.super-admin')

@section('title', 'SaaS Products')

@section('content')
@php
    $query = request()->except(['page']);
    $statusLabel = match (request('status')) {
        'active' => 'Active',
        'inactive' => 'Deactivate',
        default => 'All Products',
    };
    $typeLabel = request('type') ? ucfirst(request('type')) : 'All Types';
    $productsMap = $products->getCollection()->mapWithKeys(fn ($p) => [
        $p->id => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'type' => $p->settings['type'] ?? 'tracking',
            'is_active' => (bool) $p->is_active,
            'usage_limits' => $p->settings['usage_limits'] ?? '',
        ],
    ]);
@endphp

<x-super-admin.page title="SaaS Products">
    <div
        class="figma-sa-products"
        x-data="{
            createOpen: false,
            editOpen: false,
            limitsOpen: false,
            editingId: null,
            products: @js($productsMap),
            get editing() { return this.editingId ? this.products[this.editingId] : null; },
            openEdit(id) { this.editingId = id; this.editOpen = true; },
            openLimits(id) { this.editingId = id; this.limitsOpen = true; },
        }"
        @edit-product.window="openEdit($event.detail.id)"
        @limits-product.window="openLimits($event.detail.id)"
    >
        <form method="GET" action="{{ route('super-admin.products.index') }}" class="figma-sa-products-toolbar" id="products-filter-form">
            <input type="hidden" name="status" id="filter-product-status" value="{{ request('status') }}">
            <input type="hidden" name="type" id="filter-product-type" value="{{ request('type') }}">

            <div class="figma-sa-products-toolbar-group">
                <label class="figma-sa-products-search-chip">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search product" autocomplete="off">
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
                    @foreach (\App\Support\StatusTone::productFilters() as $fs)
                    <button type="button"
                        class="figma-sa-users-filter-option"
                        onclick="document.getElementById('filter-product-status').value='{{ $fs['value'] }}'; document.getElementById('products-filter-form').submit();">
                        {{ $fs['label'] }}
                    </button>
                    @endforeach
                </x-super-admin.dashboard-dropdown>

                <x-super-admin.dashboard-dropdown align="left">
                    <x-slot:trigger>
                        <button type="button" @click="open = !open" class="figma-sa-products-filter-chip">
                            <span>{{ $typeLabel }}</span>
                            <span class="figma-sa-products-chip-chevron">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </button>
                    </x-slot:trigger>
                    <button type="button" class="figma-sa-users-filter-option" onclick="document.getElementById('filter-product-type').value=''; document.getElementById('products-filter-form').submit();">All Types</button>
                    @foreach ($productTypes as $type)
                        <button type="button" class="figma-sa-users-filter-option" onclick="document.getElementById('filter-product-type').value='{{ $type }}'; document.getElementById('products-filter-form').submit();">{{ ucfirst($type) }}</button>
                    @endforeach
                </x-super-admin.dashboard-dropdown>
            </div>

            <button type="button" @click="createOpen = true" class="figma-sa-products-new-bar">
                <svg class="figma-sa-products-new-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke-width="1.75"/>
                    <path stroke-linecap="round" stroke-width="1.75" d="M12 8v8M8 12h8"/>
                </svg>
                New Product
            </button>
        </form>

        @foreach ($products as $product)
            <form id="product-toggle-{{ $product->id }}" method="POST" action="{{ route('super-admin.products.update', $product) }}" class="hidden">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ $product->name }}">
                <input type="hidden" name="description" value="{{ $product->description }}">
                <input type="hidden" name="type" value="{{ $product->settings['type'] ?? 'tracking' }}">
                <input type="hidden" name="is_active" value="0">
            </form>
        @endforeach

        <div class="figma-sa-products-table-shell">
            <div class="figma-sa-table-scroll">
                <table class="figma-sa-products-table">
                    <thead>
                        <tr>
                            <th class="w-10"><input type="checkbox" class="figma-sa-users-checkbox figma-sa-products-checkbox" aria-label="Select all"></th>
                            <th>User</th>
                            <th>Plan Rulers</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $active = (bool) $product->is_active;
                                $subtitle = $product->description ?: $product->slug;
                                $planLines = $product->plans->isNotEmpty()
                                    ? $product->plans->pluck('name')->take(3)
                                    : collect([$product->plans_count.' plan(s)']);
                            @endphp
                            <tr>
                                <td><input type="checkbox" class="figma-sa-users-checkbox figma-sa-products-checkbox" aria-label="Select {{ $product->name }}"></td>
                                <td>
                                    <div class="figma-sa-products-usercell">
                                        <x-super-admin.product-icon />
                                        <span>
                                            <span class="figma-sa-products-name">{{ $product->name }}</span>
                                            @if ($product->gatesCustomerPortal())
                                                <span class="mt-1 inline-flex rounded-full bg-[#6400B2]/25 px-2 py-0.5 text-[10px] font-semibold text-[#c9a8ef]">Gates customer portal</span>
                                            @endif
                                            <span class="figma-sa-products-sub">{{ $subtitle }}</span>
                                            <span class="figma-sa-products-sub">{{ $product->slug }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="figma-sa-products-plans">
                                    @foreach ($planLines as $line)
                                        <span>{{ $line }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    <div class="figma-sa-products-status-cell">
                                        <span @class(['figma-sa-products-status-badge', 'figma-sa-products-status-badge--active' => $active, 'figma-sa-products-status-badge--off' => ! $active])>
                                            <span @class(['figma-sa-products-status-dot', 'figma-sa-products-status-dot--off' => ! $active]) aria-hidden="true"></span>
                                            {{ $active ? 'Active' : 'Deactivate' }}
                                        </span>
                                        <form method="POST" action="{{ route('super-admin.products.update', $product) }}" class="inline-flex">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $product->name }}">
                                            <input type="hidden" name="description" value="{{ $product->description }}">
                                            <input type="hidden" name="type" value="{{ $product->settings['type'] ?? 'tracking' }}">
                                            <input type="hidden" name="is_active" value="{{ $active ? '1' : '0' }}">
                                            <x-figma-toggle
                                                :checked="$active"
                                                label-on="Active"
                                                label-off="Deactivate"
                                                onchange="const f=this.closest('form'); const h=f.querySelector('[name=is_active]'); h.value=this.checked?'1':'0'; f.submit();"
                                            />
                                        </form>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <x-super-admin.product-action-menu :product="$product" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="figma-sa-products-empty">No products yet. Click <strong>New Product</strong> to add one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="figma-sa-users-pagination figma-sa-products-pagination">
                <p class="figma-sa-users-pagination-meta">
                    @if ($products->total())
                        Showing {{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ $products->total() }}
                    @else
                        Showing 0 of 0
                    @endif
                </p>
                <div class="figma-sa-users-pagination-controls">
                    <form method="GET" class="flex items-center gap-2">
                        @foreach (request()->except(['per_page', 'page']) as $key => $val)
                            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                        @endforeach
                        <select name="per_page" class="figma-sa-users-perpage-select" onchange="this.form.submit()">
                            @foreach ([10, 25, 50] as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($products->hasPages())
                        <div class="figma-sa-users-page-btns">
                            @if ($products->onFirstPage())
                                <span class="figma-sa-users-page-btn figma-sa-users-page-btn--disabled">&lt;</span>
                            @else
                                <a href="{{ $products->previousPageUrl() }}" class="figma-sa-users-page-btn">&lt;</a>
                            @endif
                            <span class="figma-sa-users-page-btn figma-sa-users-page-btn--current">{{ $products->currentPage() }}</span>
                            @if ($products->hasMorePages())
                                <a href="{{ $products->nextPageUrl() }}" class="figma-sa-users-page-btn">&gt;</a>
                            @else
                                <span class="figma-sa-users-page-btn figma-sa-users-page-btn--disabled">&gt;</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- New product modal --}}
        <div x-show="createOpen" x-cloak class="figma-sa-users-modal-backdrop" @keydown.escape.window="createOpen = false">
            <div class="figma-sa-users-modal" @click.outside="createOpen = false" role="dialog">
                <button type="button" class="figma-sa-users-modal-close" @click="createOpen = false">&times;</button>
                <h2 class="figma-sa-users-modal-title">New Product</h2>
                <p class="figma-sa-users-modal-sub">Add a SaaS product module (tracking, automation, or analytics).</p>
                <form method="POST" action="{{ route('super-admin.products.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="figma-sa-label">Name</label>
                        <input name="name" required class="figma-input mt-1 w-full" placeholder="Product name">
                    </div>
                    <div>
                        <label class="figma-sa-label">Description</label>
                        <textarea name="description" rows="2" class="figma-input mt-1 w-full" placeholder="Short description"></textarea>
                    </div>
                    <div>
                        <label class="figma-sa-label">Type</label>
                        <select name="type" class="figma-select mt-1 w-full">
                            @foreach ($productTypes as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <x-figma-toggle name="is_active" value="1" checked :show-labels="false" />
                        <span class="text-sm text-[#d9d9d9]">Active on create</span>
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="createOpen = false">Cancel</button>
                        <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Create product</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit product modal --}}
        <div x-show="editOpen" x-cloak class="figma-sa-users-modal-backdrop">
            <div class="figma-sa-users-modal" @click.outside="editOpen = false" role="dialog">
                <button type="button" class="figma-sa-users-modal-close" @click="editOpen = false">&times;</button>
                <h2 class="figma-sa-users-modal-title">View / Edit Product</h2>
                <template x-if="editing">
                    <form :action="`{{ url('super-admin/products') }}/${editing.id}`" method="POST" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="figma-sa-label">Name</label>
                            <input name="name" required class="figma-input mt-1 w-full" x-model="editing.name">
                        </div>
                        <div>
                            <label class="figma-sa-label">Description</label>
                            <textarea name="description" rows="2" class="figma-input mt-1 w-full" x-model="editing.description"></textarea>
                        </div>
                        <div>
                            <label class="figma-sa-label">Type</label>
                            <select name="type" class="figma-select mt-1 w-full" x-model="editing.type">
                                @foreach ($productTypes as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <x-figma-toggle name="is_active" value="1" x-model="editing.is_active" :show-labels="false" />
                            <span class="text-sm text-[#d9d9d9]">Active</span>
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="editOpen = false">Cancel</button>
                            <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save changes</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        {{-- Usage limits modal --}}
        <div x-show="limitsOpen" x-cloak class="figma-sa-users-modal-backdrop">
            <div class="figma-sa-users-modal" @click.outside="limitsOpen = false" role="dialog">
                <button type="button" class="figma-sa-users-modal-close" @click="limitsOpen = false">&times;</button>
                <h2 class="figma-sa-users-modal-title">Edit Usage Limits</h2>
                <p class="figma-sa-users-modal-sub">Define limits for events, domains, or API calls (stored in product settings).</p>
                <template x-if="editing">
                    <form :action="`{{ url('super-admin/products') }}/${editing.id}`" method="POST" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" x-bind:value="editing.name">
                        <input type="hidden" name="description" x-bind:value="editing.description">
                        <input type="hidden" name="type" x-bind:value="editing.type">
                        <input type="hidden" name="is_active" x-bind:value="editing.is_active ? 1 : 0">
                        <div>
                            <label class="figma-sa-label">Usage limits (JSON or notes)</label>
                            <textarea name="usage_limits" rows="5" class="figma-input mt-1 w-full font-mono text-xs" x-model="editing.usage_limits" placeholder='{"events_per_month": 10000}'></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="limitsOpen = false">Cancel</button>
                            <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save limits</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
