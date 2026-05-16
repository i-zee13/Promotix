@extends('layouts.super-admin')

@section('title', 'SaaS Products')
@section('content')
<x-super-admin.page title="SaaS Products">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-super-admin.card>
            <h2 class="text-base font-semibold text-white">Create Product</h2>
            <form method="POST" action="{{ route('super-admin.products.store') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="figma-sa-label">Name</label>
                    <input name="name" required placeholder="Product name" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Description</label>
                    <textarea name="description" rows="3" placeholder="Description" class="figma-input mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="figma-sa-checkbox rounded">
                    <span class="text-sm text-[#d9d9d9]">Active</span>
                </label>
                <button class="figma-sa-btn figma-sa-btn-primary w-full">Create</button>
            </form>
        </x-super-admin.card>

        {{-- Forms outside the table --}}
        @foreach ($products as $product)
            <form id="product-form-{{ $product->id }}" method="POST" action="{{ route('super-admin.products.update', $product) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
            <form id="product-archive-{{ $product->id }}" method="POST" action="{{ route('super-admin.products.destroy', $product) }}" class="hidden" onsubmit="return confirm('Archive this product?')">
                @csrf
                @method('DELETE')
            </form>
        @endforeach

        <x-super-admin.card class="!p-0 overflow-hidden xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="figma-sa-table min-w-full">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Plans</th>
                            <th>Visible</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php $fid = 'product-form-'.$product->id; $aid = 'product-archive-'.$product->id; @endphp
                            <tr>
                                <td class="align-middle">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="name" value="{{ $product->name }}" class="figma-input">
                                        <input form="{{ $fid }}" name="description" value="{{ $product->description }}" class="figma-input">
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <span class="figma-sa-pill figma-sa-pill-neutral">{{ $product->plans_count }}</span>
                                </td>
                                <td class="align-middle">
                                    <input form="{{ $fid }}" type="hidden" name="is_active" value="0">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input form="{{ $fid }}" type="checkbox" name="is_active" value="1" @checked($product->is_active) class="figma-sa-checkbox rounded">
                                        <span class="text-sm text-[#d9d9d9]">Active</span>
                                    </label>
                                </td>
                                <td class="align-middle">
                                    <div class="flex items-center gap-2">
                                        <button form="{{ $fid }}" type="submit" class="figma-sa-btn figma-sa-btn-primary !px-3 !py-2 text-xs">Save</button>
                                        <button form="{{ $aid }}" type="submit" class="figma-sa-btn figma-sa-btn-danger !px-3 !py-2 text-xs">Archive</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-12 text-center text-[#a9a9a9]">No products yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-pagination px-4 py-3">{{ $products->links() }}</div>
        </x-super-admin.card>
    </div>
</x-super-admin.page>
@endsection
