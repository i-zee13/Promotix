@extends('layouts.super-admin')

@section('title', 'Users & Teams')
@section('subtitle', 'Tenants, roles, plans, and account actions')

@section('content')
<div class="space-y-5">
    @if (session('status'))
        <div class="rounded-xl2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl2 border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-200">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Filters --}}
    <x-ui.card>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="min-w-64 flex-1">
                <input name="search" value="{{ request('search') }}" placeholder="Search users by name or email" class="brand-input">
            </div>
            <select name="status" class="brand-select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="plan" class="brand-select">
                <option value="">All plans</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->slug }}" @selected(request('plan') === $plan->slug)>{{ $plan->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="brand-btn-primary">Filter</button>
        </form>
    </x-ui.card>

    {{-- Hidden update forms --}}
    @foreach ($users as $user)
        <form id="user-form-{{ $user->id }}" method="POST" action="{{ route('super-admin.users.update', $user) }}" class="hidden">
            @csrf
            @method('PUT')
        </form>
    @endforeach

    <x-ui.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="brand-table min-w-[1180px]">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Domains</th>
                        <th>Permissions</th>
                        <th>Save</th>
                        <th class="text-right pr-4">Actions</th>
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
                                @if ($user->current_plan_name)
                                    <div class="flex flex-col gap-1">
                                        <span class="text-sm font-semibold text-night-100">{{ $user->current_plan_name }}</span>
                                        <div class="flex items-center gap-1">
                                            <span class="brand-pill brand-pill-purple text-[10px]">{{ ucfirst($user->current_plan_tier ?? 'plan') }}</span>
                                            @if ($user->is_trialing)
                                                <span class="brand-pill brand-pill-warning text-[10px]">Trial</span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-night-400">No plan</span>
                                @endif
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
                                <input form="{{ $fid }}" type="hidden" name="is_admin" value="0">
                                <input form="{{ $fid }}" type="hidden" name="is_super_admin" value="0">
                                <div class="flex flex-col gap-1">
                                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                        <input form="{{ $fid }}" type="checkbox" name="is_admin" value="1" @checked($user->is_admin) class="brand-checkbox">
                                        <span class="text-xs text-night-200">Admin</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                        <input form="{{ $fid }}" type="checkbox" name="is_super_admin" value="1" @checked($user->is_super_admin) class="brand-checkbox">
                                        <span class="text-xs text-night-200">Super</span>
                                    </label>
                                </div>
                            </td>
                            <td class="align-middle">
                                <button form="{{ $fid }}" type="submit" class="brand-btn-primary">Save</button>
                            </td>
                            <td class="align-middle text-right pr-4">
                                <details class="group relative inline-block text-left">
                                    <summary class="list-none rounded-lg border border-night-700 px-2 py-1 text-night-200 hover:text-white hover:border-brand-400 cursor-pointer">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                    </summary>
                                    <div class="hidden group-open:block absolute right-0 z-20 mt-1 w-52 origin-top-right rounded-xl border border-night-700 bg-night-900 py-1 text-sm shadow-card-lg">
                                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-night-200 hover:bg-night-800 hover:text-white">View profile</a>
                                        <form method="POST" action="{{ route('super-admin.users.impersonate', $user) }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left block px-4 py-2 text-night-200 hover:bg-night-800 hover:text-white disabled:opacity-40" @disabled($user->is_super_admin)>Login as user</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left block px-4 py-2 text-night-200 hover:bg-night-800 hover:text-white">Reset password</button>
                                        </form>
                                        <a href="{{ route('roles.index') }}" class="block px-4 py-2 text-night-200 hover:bg-night-800 hover:text-white">Roles</a>
                                        <div class="my-1 border-t border-night-700/60"></div>
                                        <form method="POST" action="{{ route('super-admin.users.status', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="w-full text-left block px-4 py-2 text-amber-200 hover:bg-night-800 hover:text-amber-100">Suspend user</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.users.status', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="banned">
                                            <button type="submit" class="w-full text-left block px-4 py-2 text-rose-300 hover:bg-night-800 hover:text-rose-200">Ban user</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Permanently remove {{ $user->email }}?')" class="w-full text-left block px-4 py-2 text-rose-400 hover:bg-night-800 hover:text-rose-300">Remove user</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-night-300">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-night-700/60 px-4 py-3">{{ $users->links() }}</div>
    </x-ui.card>
</div>
@endsection
