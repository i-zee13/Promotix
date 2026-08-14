@extends('layouts.super-admin')

@section('title', 'Edit role')

@section('content')
@php
    $checkedIds = old('permissions', $role->permissions->pluck('id')->all());
@endphp
<x-super-admin.page :title="'Edit '.$role->name" subtitle="Update role details and permissions">
    <div class="max-w-3xl space-y-4">
        <a href="{{ route('super-admin.roles.index') }}" class="figma-sa-btn figma-sa-btn-outline !px-3 !py-2 text-sm">← Back to roles</a>

        <x-super-admin.card>
            <form method="POST" action="{{ route('super-admin.roles.update', $role) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="name" class="figma-sa-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $role->name) }}" required class="figma-input mt-1">
                    @error('name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="slug" class="figma-sa-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $role->slug) }}" required class="figma-input mt-1" @disabled($role->slug === 'super-admin')>
                    @error('slug')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="figma-sa-label">Description</label>
                    <textarea id="description" name="description" rows="2" class="figma-input mt-1">{{ old('description', $role->description) }}</textarea>
                </div>

                @include('super-admin.roles.partials.permission-picker', [
                    'groupedPermissions' => $groupedPermissions,
                    'checkedIds' => $checkedIds,
                ])

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Save role</button>
                    <a href="{{ route('super-admin.roles.index') }}" class="figma-sa-btn figma-sa-btn-outline">Cancel</a>
                </div>
            </form>
        </x-super-admin.card>
    </div>
</x-super-admin.page>
@endsection
