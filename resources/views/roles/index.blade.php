@extends('layouts.admin')

@section('title', 'Roles & Permissions')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl border border-red-500 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-400">Create roles and assign permissions. Users with a role see only the menu items they have permission for.</p>
            <a href="{{ route('roles.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New role
            </a>
        </div>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-dark-border bg-accent">
                            <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Role</th>
                            <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Permissions</th>
                            <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Users</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                        @forelse ($roles as $role)
                            <tr class="transition hover:bg-gray-800/50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white">{{ $role->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $role->slug }}</p>
                                    @if ($role->description)
                                        <p class="mt-1 text-xs text-gray-400">{{ Str::limit($role->description, 60) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ $role->permissions_count }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $role->users_count }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('roles.edit', $role) }}" class="rounded-lg px-3 py-1.5 text-sm text-accent hover:bg-accent/10">Edit</a>
                                    @if ($role->slug !== 'super-admin')
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline-block" onsubmit="return confirm('Delete this role? Users with this role will have no role.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg px-3 py-1.5 text-sm text-red-400 hover:bg-red-500/10">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-400">No roles yet. Create one to assign permissions to users.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($roles->hasPages())
                <div class="border-t border-dark-border px-4 py-3">
                    {{ $roles->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
