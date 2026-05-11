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
                    $grouped = $permissions->groupBy(fn ($p) => \Illuminate\Support\Str::of($p->slug)->before('.')->before('-')->title()->value() ?: 'General');
                @endphp
                <div x-data="{ filter: '' }">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <label class="brand-label">Permissions</label>
                        <input type="text" x-model="filter" placeholder="Filter permissions..." class="brand-input w-56">
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach ($grouped as $groupLabel => $items)
                            <div class="rounded-xl2 border border-night-700/60 bg-night-900/40 p-3"
                                x-data="{
                                    toggleAll(state) {
                                        this.$root.querySelectorAll('input[type=checkbox][data-perm]').forEach(el => el.checked = state);
                                    }
                                }">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-widest text-brand-300">{{ $groupLabel }}</p>
                                    <div class="flex gap-2 text-[10px] uppercase">
                                        <button type="button" class="text-night-300 hover:text-brand-300" @click="toggleAll(true)">All</button>
                                        <button type="button" class="text-night-300 hover:text-rose-300" @click="toggleAll(false)">None</button>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    @foreach ($items as $p)
                                        <label class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-night-800/60 cursor-pointer"
                                            x-show="filter === '' || '{{ strtolower(addslashes($p->name.' '.$p->slug)) }}'.includes(filter.toLowerCase())">
                                            <input type="checkbox" data-perm name="permissions[]" value="{{ $p->id }}" @checked(in_array($p->id, $checkedIds)) class="brand-checkbox">
                                            <span class="text-sm text-night-100">{{ $p->name }}</span>
                                            <span class="ml-auto text-[10px] text-night-400 font-mono">{{ $p->slug }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <x-ui.button type="submit" variant="primary">Update role</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('roles.index') }}">Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
