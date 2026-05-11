@extends('layouts.admin')

@section('title', 'Edit role')
@section('subtitle', $role->name)

@section('content')
    <div class="max-w-2xl space-y-6">
        <x-ui.page-header :title="'Edit role: '.$role->name" subtitle="Update permissions and metadata for this role">
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" href="{{ route('roles.index') }}">← Back to roles</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card>
            <form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="brand-label mb-1.5">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $role->name) }}" required class="brand-input">
                    @error('name')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="slug" class="brand-label mb-1.5">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $role->slug) }}" required class="brand-input">
                    @error('slug')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="brand-label mb-1.5">Description <span class="font-normal text-night-400">(optional)</span></label>
                    <textarea id="description" name="description" rows="2" class="brand-input">{{ old('description', $role->description) }}</textarea>
                </div>

                @php
                    $checkedIds = old('permissions', $role->permissions->pluck('id')->toArray());
                @endphp
                <div>
                    <label class="brand-label mb-1.5">Permissions</label>
                    <p class="mb-2 text-xs text-night-400">Select multiple permissions (Ctrl/Shift+click).</p>
                    <select name="permissions[]" multiple size="10" class="brand-select">
                        @foreach ($permissions as $p)
                            <option value="{{ $p->id }}" @selected(in_array($p->id, $checkedIds))>
                                {{ $p->name }} ({{ $p->slug }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-3 pt-2">
                    <x-ui.button type="submit" variant="primary">Update role</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('roles.index') }}">Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
