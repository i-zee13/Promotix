@extends('layouts.admin')

@section('title', 'New role')

@section('content')
    <div class="max-w-2xl space-y-6">
        <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-400 hover:text-white">&larr; Back to roles</a>

        <form method="POST" action="{{ route('roles.store') }}" class="space-y-6 rounded-xl border border-dark-border bg-dark-card p-6">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required
                    class="mt-1 w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    placeholder="e.g. Support Agent">
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-gray-300">Slug</label>
                <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required
                    class="mt-1 w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    placeholder="e.g. support-agent">
                @error('slug')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-300">Description (optional)</label>
                <textarea id="description" name="description" rows="2"
                    class="mt-1 w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    placeholder="What this role is for">{{ old('description') }}</textarea>
            </div>

            <div>
                <p class="block text-sm font-medium text-gray-300 mb-2">Permissions (which menu items / routes this role can access)</p>
                <p class="mb-2 text-xs text-gray-500">Select multiple permissions (Ctrl/Shift).</p>
                <select
                    name="permissions[]"
                    multiple
                    size="10"
                    class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                >
                    @foreach ($permissions as $p)
                        <option
                            value="{{ $p->id }}"
                            @selected(in_array($p->id, old('permissions', [])))
                        >
                            {{ $p->name }} ({{ $p->slug }})
                        </option>
                    @endforeach
                </select>
                @error('permissions.*')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">Create role</button>
                <a href="{{ route('roles.index') }}" class="rounded-xl border border-dark-border px-4 py-2.5 text-sm font-medium text-gray-300 hover:bg-dark-border">Cancel</a>
            </div>
        </form>
    </div>
@endsection
