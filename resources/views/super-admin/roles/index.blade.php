@extends('layouts.super-admin')

@section('title', 'Roles & Permissions')

@section('content')
<x-super-admin.page title="Roles & Permissions" subtitle="Define roles and assign feature access.">
    <div class="figma-sa-products figma-sa-roles">
        <div class="figma-sa-products-toolbar">
            <div class="figma-sa-products-toolbar-group">
                <label class="figma-sa-products-search-chip">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input
                        type="search"
                        placeholder="Search roles"
                        autocomplete="off"
                        oninput="document.querySelectorAll('[data-role-row]').forEach(r => r.style.display = r.dataset.search.includes(this.value.toLowerCase()) ? '' : 'none')"
                    >
                </label>
            </div>

            <a href="{{ route('super-admin.roles.create') }}" class="figma-sa-products-new-bar">
                <svg class="figma-sa-products-new-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke-width="1.75"/>
                    <path stroke-linecap="round" stroke-width="1.75" d="M12 8v8M8 12h8"/>
                </svg>
                New role
            </a>
        </div>

        <div class="figma-sa-products-table-shell">
            <div class="figma-sa-table-scroll">
                <table class="figma-sa-products-table figma-sa-roles-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Users</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr data-role-row data-search="{{ strtolower($role->name.' '.$role->slug.' '.($role->description ?? '')) }}">
                                <td>
                                    <div class="figma-sa-products-usercell">
                                        <span class="figma-sa-roles-icon" aria-hidden="true">
                                            <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="40" height="40" rx="8" fill="#f3ecff"/>
                                                <path d="M12 28c0-4.4 3.6-8 8-8s8 3.6 8 8" stroke="#6400B2" stroke-width="2" stroke-linecap="round"/>
                                                <circle cx="20" cy="14" r="5" stroke="#6400B2" stroke-width="2"/>
                                                <path d="M26 16l4-2v6l-4-2" stroke="#9A1AFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span>
                                            <span class="figma-sa-products-name">{{ $role->name }}</span>
                                            <span class="figma-sa-products-sub">{{ $role->slug }}</span>
                                            @if ($role->description)
                                                <span class="figma-sa-products-sub">{{ Str::limit($role->description, 90) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="figma-sa-roles-count-badge">{{ $role->permissions_count }}</span>
                                </td>
                                <td>
                                    <span class="figma-sa-roles-count-badge figma-sa-roles-count-badge--muted">{{ $role->users_count }}</span>
                                </td>
                                <td class="text-right">
                                    <div class="figma-sa-roles-actions">
                                        <a href="{{ route('super-admin.roles.edit', $role) }}" class="figma-sa-roles-action-btn">Edit</a>
                                        @if ($role->slug !== 'super-admin')
                                            <form method="POST" action="{{ route('super-admin.roles.destroy', $role) }}" class="inline" onsubmit="return confirm('Delete this role? Users with this role will have no role.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="figma-sa-roles-action-btn figma-sa-roles-action-btn--danger">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="figma-sa-products-empty">No roles yet. Click <strong>New role</strong> to add one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($roles->hasPages())
                <div class="figma-sa-users-pagination figma-sa-products-pagination">
                    <p class="figma-sa-users-pagination-meta">
                        Showing {{ $roles->firstItem() }}–{{ $roles->lastItem() }} of {{ $roles->total() }}
                    </p>
                    <div class="figma-sa-users-pagination-controls">
                        {{ $roles->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-super-admin.page>
@endsection
