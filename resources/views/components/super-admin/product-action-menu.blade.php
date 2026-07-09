@props(['product'])

@php
    $type = $product->settings['type'] ?? 'tracking';
@endphp

<x-super-admin.dashboard-dropdown align="right">
    <x-slot:trigger>
        <button type="button" @click="open = !open" class="figma-sa-products-kebab" aria-label="Product actions">
            <svg class="h-[18px] w-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
        </button>
    </x-slot:trigger>

    <button type="button" class="figma-sa-users-action-item w-full text-left" @click="$dispatch('edit-product', { id: {{ $product->id }} }); open = false">
        <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        View / Edit Product
    </button>

    <button type="button" class="figma-sa-users-action-item w-full text-left" @click="$dispatch('limits-product', { id: {{ $product->id }} }); open = false">
        <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Edit Usage Limits
    </button>

    <a href="{{ route('super-admin.plans.index', ['saas_product_id' => $product->id]) }}" class="figma-sa-users-action-item">
        <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        Assign to Plans
    </a>

    <form method="POST" action="{{ route('super-admin.products.duplicate', $product) }}">
        @csrf
        <button type="submit" class="figma-sa-users-action-item w-full">
            <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Duplicate Product
        </button>
    </form>

    <form method="POST" action="{{ route('super-admin.products.destroy', $product) }}" onsubmit="return confirm('Delete {{ $product->name }}?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--danger figma-sa-products-action-danger w-full">
            <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete Product
        </button>
    </form>
</x-super-admin.dashboard-dropdown>
