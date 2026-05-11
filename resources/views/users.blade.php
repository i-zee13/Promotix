@extends('layouts.admin')

@section('title', 'Users & Teams')
@section('subtitle', 'Manage team access and roles')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="Users & Teams" subtitle="Manage team access and roles" />

        @if (session('status'))
            <div class="brand-pill brand-pill-success">{{ session('status') }}</div>
        @endif

        {{-- Search --}}
        <x-ui.card variant="flat">
            <form method="GET" action="{{ route('users') }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-night-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input id="users-search" name="search" type="search" value="{{ request('search') }}"
                           placeholder="Search by name or email" class="brand-input pl-9">
                </div>
                <x-ui.button type="submit" variant="primary">Search</x-ui.button>
            </form>
        </x-ui.card>

        {{-- Users table --}}
        <x-ui.card variant="flat">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[700px]">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-500/15 text-sm font-semibold text-brand-200">
                                            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                        </span>
                                        <div>
                                            <p class="font-semibold text-white">{{ $user->name }}</p>
                                            <p class="text-xs text-night-400">{{ $user->email }}</p>
                                            @if ($user->is_admin)
                                                <x-ui.pill tone="warning" class="mt-1">Super Admin</x-ui.pill>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('users.update-role', $user) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role_id" onchange="this.form.submit()" class="brand-select max-w-[180px] py-1.5 text-sm">
                                            <option value="">— No role —</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="text-night-300">{{ $user->created_at?->format('M j, Y') }}</td>
                                <td class="text-night-400">—</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-night-300">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="mt-4 border-t border-night-700/60 pt-4">
                    {{ $users->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
