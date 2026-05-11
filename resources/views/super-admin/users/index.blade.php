@extends('layouts.super-admin')

@section('title', 'Users & Teams')
@section('subtitle', 'View users, assign roles, and control account status')

@section('content')
    <div class="space-y-5">
        {{-- Filters --}}
        <x-ui.card>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <div class="min-w-64 flex-1">
                    <input name="search"
                           value="{{ request('search') }}"
                           placeholder="Search users by name or email"
                           class="brand-input">
                </div>
                <div>
                    <select name="status" class="brand-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="brand-btn-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4h18M6 12h12M10 20h4"/></svg>
                    Filter
                </button>
            </form>
        </x-ui.card>

        {{-- Forms (rendered outside the table to avoid invalid HTML nesting) --}}
        @foreach ($users as $user)
            <form id="user-form-{{ $user->id }}" method="POST" action="{{ route('super-admin.users.update', $user) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        {{-- Users table --}}
        <x-ui.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[1000px]">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Domains</th>
                            <th>Permissions</th>
                            <th class="text-right pr-4">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php $fid = 'user-form-'.$user->id; @endphp
                            <tr>
                                <td class="align-middle">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="name" value="{{ $user->name }}" class="brand-input">
                                        <input form="{{ $fid }}" name="email" value="{{ $user->email }}" class="brand-input">
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <select form="{{ $fid }}" name="role_id" class="brand-select">
                                        <option value="">No role</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <select form="{{ $fid }}" name="status" class="brand-select">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected(($user->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <span class="brand-pill brand-pill-neutral">{{ $user->domains_count ?? $user->domains->count() }}</span>
                                </td>
                                <td class="align-middle">
                                    {{-- Always send 0 first; checkbox overrides to 1 if checked. --}}
                                    <input form="{{ $fid }}" type="hidden" name="is_admin" value="0">
                                    <input form="{{ $fid }}" type="hidden" name="is_super_admin" value="0">

                                    <div class="flex flex-col gap-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                            <input form="{{ $fid }}" type="checkbox" name="is_admin" value="1"
                                                   @checked($user->is_admin)
                                                   class="brand-checkbox">
                                            <span class="text-sm text-night-200">Admin</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                            <input form="{{ $fid }}" type="checkbox" name="is_super_admin" value="1"
                                                   @checked($user->is_super_admin)
                                                   class="brand-checkbox">
                                            <span class="text-sm text-night-200">Super</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="align-middle text-right pr-4">
                                    <button form="{{ $fid }}" type="submit" class="brand-btn-primary">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                                        Save
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-night-300">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-night-700/60 px-4 py-3">{{ $users->links() }}</div>
        </x-ui.card>
    </div>
@endsection
