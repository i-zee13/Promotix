@extends('layouts.super-admin')

@section('title', 'Roles & Permissions')

@section('content')
<x-super-admin.page title="Roles & Permissions" subtitle="Define roles and assign feature access">
    <div class="space-y-[14px]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="figma-sa-users-search-wrap flex-1 min-w-[220px] max-w-md">
                <svg class="figma-sa-users-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" placeholder="Search roles" class="figma-sa-users-search-input" oninput="document.querySelectorAll('[data-role-row]').forEach(r => r.style.display = r.dataset.search.includes(this.value.toLowerCase()) ? '' : 'none')">
            </div>
            <a href="{{ route('super-admin.roles.create') }}" class="figma-sa-btn figma-sa-btn-primary">New role</a>
        </div>

        <x-super-admin.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="figma-sa-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Users</th>
                            <th class="text-right pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr data-role-row data-search="{{ strtolower($role->name.' '.$role->slug.' '.($role->description ?? '')) }}">
                                <td>
                                    <p class="font-semibold text-white">{{ $role->name }}</p>
                                    <p class="text-xs text-[#8c8787]">{{ $role->slug }}</p>
                                    @if ($role->description)
                                        <p class="mt-1 text-xs text-[#a9a9a9]">{{ Str::limit($role->description, 80) }}</p>
                                    @endif
                                </td>
                                <td><span class="figma-sa-pill figma-sa-pill-purple">{{ $role->permissions_count }}</span></td>
                                <td><span class="figma-sa-pill figma-sa-pill-neutral">{{ $role->users_count }}</span></td>
                                <td class="text-right pr-4">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('super-admin.roles.edit', $role) }}" class="figma-sa-btn figma-sa-btn-outline !px-3 !py-1.5 text-xs">Edit</a>
                                        @if ($role->slug !== 'super-admin')
                                            <form method="POST" action="{{ route('super-admin.roles.destroy', $role) }}" class="inline" onsubmit="return confirm('Delete this role? Users with this role will have no role.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="figma-sa-btn figma-sa-btn-danger !px-3 !py-1.5 text-xs">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-12 text-center text-[#a9a9a9]">No roles yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($roles->hasPages())
                <div class="figma-sa-pagination px-4 py-3">{{ $roles->links() }}</div>
            @endif
        </x-super-admin.card>
    </div>
</x-super-admin.page>
@endsection
