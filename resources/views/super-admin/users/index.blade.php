@extends('layouts.super-admin')

@section('title', 'Users & Teams')
@section('content')
<x-super-admin.page title="Users & Teams" subtitle="Tenants, roles, plans, and account actions">
<div class="space-y-[16px]">
    <x-super-admin.card title="Invite user" subtitle="Creates a pending invite for optional role and plan.">
        <h2 class="text-base font-semibold text-white">Invite user</h2>
        <p class="mt-1 text-sm text-[#a9a9a9]">Creates a pending invite for onboarding with optional role and plan.</p>
        <form method="POST" action="{{ route('super-admin.users.invite') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-48 flex-1">
                <label class="figma-sa-label">Email</label>
                <input type="email" name="email" required class="figma-input mt-1" placeholder="user@company.com">
            </div>
            <div class="min-w-40">
                <label class="figma-sa-label">Name</label>
                <input type="text" name="name" class="figma-input mt-1" placeholder="Optional">
            </div>
            <div>
                <label class="figma-sa-label">Role</label>
                <select name="role_id" class="figma-select mt-1">
                    <option value="">—</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="figma-sa-label">Plan</label>
                <select name="plan_id" class="figma-select mt-1">
                    <option value="">—</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Send invite</button>
        </form>
    </x-super-admin.card>

    {{-- Filters --}}
    <x-super-admin.card>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="min-w-64 flex-1">
                <input name="search" value="{{ request('search') }}" placeholder="Search users by name or email" class="figma-input">
            </div>
            <select name="status" class="figma-select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="plan" class="figma-select">
                <option value="">All plans</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->slug }}" @selected(request('plan') === $plan->slug)>{{ $plan->name }}</option>
                @endforeach
            </select>
            <select name="verified" class="figma-select">
                <option value="">Email (any)</option>
                <option value="1" @selected(request('verified') === '1')>Verified</option>
                <option value="0" @selected(request('verified') === '0')>Not verified</option>
            </select>
            <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Filter</button>
        </form>
    </x-super-admin.card>

    {{-- Hidden update forms --}}
    @foreach ($users as $user)
        <form id="user-form-{{ $user->id }}" method="POST" action="{{ route('super-admin.users.update', $user) }}" class="hidden">
            @csrf
            @method('PUT')
        </form>
    @endforeach

    <x-super-admin.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="figma-sa-table min-w-[1320px]">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Verified</th>
                        <th>Role</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Last login</th>
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
                                    <input form="{{ $fid }}" name="name" value="{{ $user->name }}" class="figma-input">
                                    <input form="{{ $fid }}" name="email" value="{{ $user->email }}" class="figma-input">
                                </div>
                            </td>
                            <td class="align-middle">
                                @if ($user->hasVerifiedEmail())
                                    <span class="figma-sa-pill figma-sa-pill-success text-[10px]">Yes</span>
                                @else
                                    <span class="figma-sa-pill figma-sa-pill-warning text-[10px]">No</span>
                                @endif
                            </td>
                            <td class="align-middle">
                                <select form="{{ $fid }}" name="role_id" class="figma-select">
                                    <option value="">No role</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="align-middle">
                                @if ($user->current_plan_name)
                                    <div class="flex flex-col gap-1">
                                        <span class="text-sm font-semibold text-white">{{ $user->current_plan_name }}</span>
                                        <div class="flex flex-wrap items-center gap-1">
                                            <span class="figma-sa-pill figma-sa-pill-purple text-[10px]">{{ ucfirst($user->current_plan_tier ?? 'plan') }}</span>
                                            @if ($user->subscription_status)
                                                <span class="figma-sa-pill figma-sa-pill-neutral text-[10px]">{{ $user->subscription_status }}</span>
                                            @endif
                                            @if ($user->is_trialing)
                                                <span class="figma-sa-pill figma-sa-pill-warning text-[10px]">Trial</span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-[#8c8787]">No plan</span>
                                @endif
                            </td>
                            <td class="align-middle">
                                <select form="{{ $fid }}" name="status" class="figma-select">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(($user->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="align-middle text-xs text-[#a9a9a9]">
                                {{ $user->last_login_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="align-middle">
                                <span class="figma-sa-pill figma-sa-pill-neutral">{{ $user->domains_count ?? $user->domains->count() }}</span>
                            </td>
                            <td class="align-middle">
                                <input form="{{ $fid }}" type="hidden" name="is_admin" value="0">
                                <input form="{{ $fid }}" type="hidden" name="is_super_admin" value="0">
                                <div class="flex flex-col gap-1">
                                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                        <input form="{{ $fid }}" type="checkbox" name="is_admin" value="1" @checked($user->is_admin) class="figma-sa-checkbox rounded">
                                        <span class="text-xs text-[#d9d9d9]">Admin</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                        <input form="{{ $fid }}" type="checkbox" name="is_super_admin" value="1" @checked($user->is_super_admin) class="figma-sa-checkbox rounded">
                                        <span class="text-xs text-[#d9d9d9]">Super</span>
                                    </label>
                                </div>
                            </td>
                            <td class="align-middle">
                                <button form="{{ $fid }}" type="submit" class="figma-sa-btn figma-sa-btn-primary">Save</button>
                            </td>
                            <td class="align-middle text-right pr-4">
                                <details class="group relative inline-block text-left">
                                    <summary class="figma-sa-btn figma-sa-btn-outline list-none !px-2 !py-1 cursor-pointer">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                    </summary>
                                    <div class="figma-sa-actions-menu hidden group-open:block absolute right-0 z-20 mt-1 w-52 origin-top-right py-1 text-sm">
                                        <a href="{{ route('super-admin.users.show', $user) }}">View profile</a>
                                        <form method="POST" action="{{ route('super-admin.users.impersonate', $user) }}">
                                            @csrf
                                            <button type="submit" @disabled($user->is_super_admin)>Login as user</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}">
                                            @csrf
                                            <button type="submit">Reset password</button>
                                        </form>
                                        <a href="{{ route('roles.index') }}">Roles</a>
                                        <div class="my-1 border-t border-white/10"></div>
                                        <form method="POST" action="{{ route('super-admin.users.status', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="!text-amber-200 hover:!text-amber-100">Suspend user</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.users.status', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="banned">
                                            <button type="submit" class="!text-rose-300 hover:!text-rose-200">Ban user</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Permanently remove {{ $user->email }}?')" class="!text-rose-400 hover:!text-rose-300">Remove user</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-[#a9a9a9]">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="figma-sa-pagination px-4 py-3">{{ $users->links() }}</div>
    </x-super-admin.card>
</div>
</x-super-admin.page>
@endsection
