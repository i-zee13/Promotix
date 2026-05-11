@extends('layouts.admin')

@section('title', 'Roles & Permissions')
@section('subtitle', 'Define roles and assign feature access')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="Roles & Permissions" subtitle="Create roles and assign permissions to control sidebar access">
            <x-slot:actions>
                <x-ui.button variant="primary" href="{{ route('roles.create') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New role
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="brand-pill brand-pill-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="brand-pill brand-pill-danger">{{ session('error') }}</div>
        @endif

        <x-ui.card variant="flat">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[600px]">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Users</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td>
                                    <p class="font-semibold text-white">{{ $role->name }}</p>
                                    <p class="text-xs text-night-400">{{ $role->slug }}</p>
                                    @if ($role->description)
                                        <p class="mt-1 text-xs text-night-300">{{ Str::limit($role->description, 60) }}</p>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.pill tone="purple">{{ $role->permissions_count }}</x-ui.pill>
                                </td>
                                <td>
                                    <x-ui.pill tone="neutral">{{ $role->users_count }}</x-ui.pill>
                                </td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('roles.edit', $role) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-200 hover:bg-brand-500/10 hover:text-white">Edit</a>
                                        @if ($role->slug !== 'super-admin')
                                            <form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline-block" onsubmit="return confirm('Delete this role? Users with this role will have no role.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-rose-300 hover:bg-rose-500/10">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-night-300">No roles yet. Create one to assign permissions to users.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($roles->hasPages())
                <div class="mt-4 border-t border-night-700/60 pt-4">{{ $roles->links() }}</div>
            @endif
        </x-ui.card>
    </div>
@endsection
