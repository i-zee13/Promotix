@extends('layouts.super-admin')

@section('title', 'New role')

@section('content')
@php
    $checkedIds = old('permissions', []);
    $grouped = $permissions->groupBy(fn ($p) => \Illuminate\Support\Str::of($p->slug)->before('.')->before('-')->title()->value() ?: 'General');
@endphp
<x-super-admin.page title="New role" subtitle="Define a custom role and assign permissions">
    <div class="max-w-3xl space-y-4">
        <a href="{{ route('super-admin.roles.index') }}" class="figma-sa-btn figma-sa-btn-outline !px-3 !py-2 text-sm">← Back to roles</a>

        <x-super-admin.card>
            <form method="POST" action="{{ route('super-admin.roles.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="name" class="figma-sa-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder="e.g. Support Agent" class="figma-input mt-1">
                    @error('name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="slug" class="figma-sa-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required placeholder="e.g. support-agent" class="figma-input mt-1">
                    @error('slug')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="figma-sa-label">Description</label>
                    <textarea id="description" name="description" rows="2" placeholder="What this role is for" class="figma-input mt-1">{{ old('description') }}</textarea>
                </div>

                <div x-data="{ filter: '' }" class="figma-sa-permissions">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <label class="figma-sa-label">Permissions</label>
                        <input type="search" x-model="filter" placeholder="Filter permissions..." class="figma-input w-full max-w-xs">
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach ($grouped as $groupLabel => $items)
                            <div class="figma-sa-row figma-sa-perm-group p-3" x-data="{ toggleAll(state) { this.$root.querySelectorAll('input[type=checkbox][data-perm]').forEach(el => el.checked = state); } }">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="figma-sa-perm-group__title">{{ $groupLabel }}</p>
                                    <div class="flex gap-2 text-[10px] uppercase">
                                        <button type="button" class="figma-sa-perm-group__action" @click="toggleAll(true)">All</button>
                                        <button type="button" class="figma-sa-perm-group__action figma-sa-perm-group__action--danger" @click="toggleAll(false)">None</button>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    @foreach ($items as $p)
                                        <label class="figma-sa-perm-item flex cursor-pointer items-center gap-2 rounded px-2 py-1.5"
                                            x-show="filter === '' || '{{ strtolower(addslashes($p->name.' '.$p->slug)) }}'.includes(filter.toLowerCase())">
                                            <input type="checkbox" data-perm name="permissions[]" value="{{ $p->id }}" @checked(in_array($p->id, $checkedIds)) class="figma-sa-checkbox rounded">
                                            <span class="figma-sa-perm-item__name">{{ $p->name }}</span>
                                            <span class="figma-sa-perm-item__slug ml-auto font-mono text-[10px]">{{ $p->slug }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Create role</button>
                    <a href="{{ route('super-admin.roles.index') }}" class="figma-sa-btn figma-sa-btn-outline">Cancel</a>
                </div>
            </form>
        </x-super-admin.card>
    </div>
</x-super-admin.page>
@endsection
