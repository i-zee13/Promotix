@extends('layouts.super-admin')

@section('title', 'Users & Teams')

@section('content')
@php
    $query = request()->except(['page']);
    $dateLabel = request('date')
        ? \Illuminate\Support\Carbon::parse(request('date'))->format('n/j/Y')
        : '12/1/2026';
@endphp

<x-super-admin.page title="Users & Teams">
    <div class="figma-sa-users" x-data="{ tab: @js($tab), inviteOpen: false }">
        {{-- View tabs: Users list vs Teams board --}}
        <div class="figma-sa-users-view-tabs mb-[14px]">
            <a href="{{ route('super-admin.users.index', array_merge($query, ['tab' => 'users'])) }}"
               @class(['figma-sa-users-view-tab', 'figma-sa-users-view-tab--active' => $tab === 'users'])>
                Users
            </a>
            <a href="{{ route('super-admin.users.index', array_merge($query, ['tab' => 'teams'])) }}"
               @class(['figma-sa-users-view-tab', 'figma-sa-users-view-tab--active' => $tab === 'teams'])>
                Teams
            </a>
        </div>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('super-admin.users.index') }}" class="figma-sa-users-toolbar" id="users-filter-form" @if($tab === 'users') data-users-only @endif>
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="status" id="filter-status" value="{{ request('status') }}">
            <input type="hidden" name="plan" id="filter-plan" value="{{ request('plan') }}">

            <div class="figma-sa-users-search-wrap">
                <svg class="figma-sa-users-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search users" class="figma-sa-users-search-input" autocomplete="off">
            </div>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                        <span>{{ request('plan') ? $plans->firstWhere('slug', request('plan'))?->name ?? 'Plan Filter' : 'Plan Filter' }}</span>
                        <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-filter-option" onclick="document.getElementById('filter-plan').value=''; document.getElementById('users-filter-form').submit();">All plans</button>
                @foreach ($plans as $plan)
                    <button type="button" class="figma-sa-users-filter-option figma-sa-users-plan-chip" onclick="document.getElementById('filter-plan').value='{{ $plan->slug }}'; document.getElementById('users-filter-form').submit();">{{ $plan->name }}</button>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                        <span>{{ collect($filterStatuses)->firstWhere('value', request('status', ''))['label'] ?? 'All Statuses' }}</span>
                        <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </x-slot:trigger>
                @foreach ($filterStatuses as $fs)
                    <button type="button"
                        class="figma-sa-users-status-chip figma-sa-users-status-chip--{{ $fs['tone'] }}"
                        onclick="document.getElementById('filter-status').value='{{ $fs['value'] }}'; document.getElementById('users-filter-form').submit();">
                        {{ $fs['label'] }}
                    </button>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            @if ($tab === 'teams')
                <x-super-admin.dashboard-dropdown align="left">
                    <x-slot:trigger>
                        <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                            <span>{{ request('role') ? $roles->firstWhere('slug', request('role'))?->name ?? 'All Roles' : 'All Roles' }}</span>
                            <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </x-slot:trigger>
                    <button type="button" class="figma-sa-users-filter-option" onclick="document.querySelector('#users-filter-form [name=role]').value=''; document.getElementById('users-filter-form').submit();">All Roles</button>
                    @foreach ($roles as $role)
                        <button type="button" class="figma-sa-users-filter-option" onclick="document.querySelector('#users-filter-form [name=role]').value='{{ $role->slug }}'; document.getElementById('users-filter-form').submit();">{{ $role->name }}</button>
                    @endforeach
                </x-super-admin.dashboard-dropdown>
                <input type="hidden" name="role" value="{{ request('role') }}">
            @endif

            <div class="figma-sa-users-date-wrap">
                <label class="figma-sa-users-filter-btn figma-sa-users-date-label cursor-pointer">
                    <span>Date {{ $dateLabel }}</span>
                    <input type="date" name="date" value="{{ request('date') }}" class="figma-sa-users-date-input" onchange="this.form.submit()">
                </label>
            </div>

            <button type="button" @click="inviteOpen = true" class="figma-sa-users-invite-btn">
                <span class="figma-sa-users-invite-icon" aria-hidden="true">+</span>
                Invite Users
            </button>
        </form>

        @if ($tab === 'users')
            <div class="figma-sa-users-table-shell">
                <div class="figma-sa-table-scroll">
                    <table class="figma-sa-users-table">
                        <thead>
                            <tr>
                                <th class="w-10"><input type="checkbox" class="figma-sa-users-checkbox" aria-label="Select all"></th>
                                <th>
                                    <span class="inline-flex items-center gap-1">User
                                        <svg class="h-3 w-3 opacity-60" fill="currentColor" viewBox="0 0 20 20"><path d="M5 12l5-5 5 5H5z"/></svg>
                                    </span>
                                </th>
                                <th>Email</th>
                                <th>Plan Filter</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                @php
                                    $status = $user->status ?? 'active';
                                    $planLabel = $user->current_plan_name ?? ($user->is_trialing ? 'Trial' : '—');
                                @endphp
                                <tr>
                                    <td><input type="checkbox" class="figma-sa-users-checkbox" aria-label="Select {{ $user->name }}"></td>
                                    <td>
                                        <div class="figma-sa-users-usercell">
                                            <span class="figma-sa-users-avatar" aria-hidden="true"></span>
                                            <span>
                                                <span class="figma-sa-users-name">{{ $user->name }}</span>
                                                <span class="figma-sa-users-subemail">{{ $user->email }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="figma-sa-users-role-col">{{ $user->role?->name ?? ($user->is_admin ? 'Admin' : 'Member') }}</td>
                                    <td>
                                        @if ($planLabel !== '—')
                                            <span class="figma-sa-users-plan-tag">{{ $planLabel }}</span>
                                        @else
                                            <span class="text-[#8c8787] text-xs">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="figma-sa-users-status-tag figma-sa-users-status-tag--{{ $status }}">{{ ucfirst($status) }}</span>
                                    </td>
                                    <td class="figma-sa-users-date-col">{{ $user->created_at?->format('n/j/Y') ?? '—' }}</td>
                                    <td class="text-right">
                                        <x-super-admin.user-action-menu :user="$user" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="figma-sa-users-empty">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="figma-sa-users-pagination">
                    <p class="figma-sa-users-pagination-meta">
                        @if ($users->total())
                            Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}
                        @else
                            Showing 0 of 0
                        @endif
                    </p>
                    <div class="figma-sa-users-pagination-controls">
                        <form method="GET" class="flex items-center gap-2">
                            @foreach (request()->except(['per_page', 'page']) as $key => $val)
                                <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                            @endforeach
                            <label class="figma-sa-users-perpage-label">
                                <select name="per_page" class="figma-sa-users-perpage-select" onchange="this.form.submit()">
                                    @foreach ([10, 25, 50] as $n)
                                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </form>
                        @if ($users->hasPages())
                            <div class="figma-sa-users-page-btns">
                                @if ($users->onFirstPage())
                                    <span class="figma-sa-users-page-btn figma-sa-users-page-btn--disabled">&lt;</span>
                                @else
                                    <a href="{{ $users->previousPageUrl() }}" class="figma-sa-users-page-btn">&lt;</a>
                                @endif
                                <span class="figma-sa-users-page-btn figma-sa-users-page-btn--current">{{ $users->currentPage() }}</span>
                                @if ($users->hasMorePages())
                                    <a href="{{ $users->nextPageUrl() }}" class="figma-sa-users-page-btn">&gt;</a>
                                @else
                                    <span class="figma-sa-users-page-btn figma-sa-users-page-btn--disabled">&gt;</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- Teams board (Figma second screen) --}}
            <div class="figma-sa-teams-tabs">
                @foreach (range(1, 6) as $i)
                    <button type="button" @class(['figma-sa-teams-tab', 'figma-sa-teams-tab--active' => $i === 1])>Team {{ $i }}</button>
                @endforeach
            </div>

            <div class="figma-sa-teams-board">
                @foreach ($teamsBoard as $column => $members)
                    <article class="figma-sa-teams-column">
                        <header class="figma-sa-teams-column-head">{{ $column }}</header>
                        <div class="figma-sa-teams-column-body">
                            @forelse ($members as $member)
                                <div class="figma-sa-teams-card">
                                    <span class="figma-sa-users-avatar figma-sa-users-avatar--sm" aria-hidden="true"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="figma-sa-teams-card-name">{{ $member->name }}</p>
                                        <p class="figma-sa-teams-card-email">{{ $member->email }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('super-admin.users.status', $member) }}" class="shrink-0">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $member->status ?? 'active' }}">
                                        <x-figma-toggle
                                            :checked="($member->status ?? 'active') === 'active'"
                                            label-on="Active"
                                            label-off="Off"
                                            onchange="const f=this.closest('form'); f.querySelector('[name=status]').value=this.checked?'active':'suspended'; f.submit();"
                                            title="Toggle active"
                                        />
                                    </form>
                                </div>
                            @empty
                                <p class="figma-sa-teams-empty">No members</p>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        {{-- Invite users modal --}}
        <div
            x-show="inviteOpen"
            x-cloak
            class="figma-sa-users-modal-backdrop"
            @keydown.escape.window="inviteOpen = false"
        >
            <div class="figma-sa-users-modal" @click.outside="inviteOpen = false" role="dialog" aria-labelledby="invite-users-title">
                <button type="button" class="figma-sa-users-modal-close" @click="inviteOpen = false" aria-label="Close">&times;</button>
                <h2 id="invite-users-title" class="figma-sa-users-modal-title">Invite Users</h2>
                <p class="figma-sa-users-modal-sub">Send an invite link. The user can register with the role and plan you choose.</p>

                <form method="POST" action="{{ route('super-admin.users.invite') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="figma-sa-label">Email <span class="text-rose-400">*</span></label>
                        <input type="email" name="email" required class="figma-input mt-1 w-full" placeholder="user@company.com" value="{{ old('email') }}">
                        @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="figma-sa-label">Name</label>
                            <input type="text" name="name" class="figma-input mt-1 w-full" placeholder="Optional" value="{{ old('name') }}">
                        </div>
                        <div>
                            <label class="figma-sa-label">Role</label>
                            <select name="role_id" class="figma-select mt-1 w-full">
                                <option value="">— Default —</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="figma-sa-label">Plan</label>
                        <select name="plan_id" class="figma-select mt-1 w-full">
                            <option value="">— None —</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap justify-end gap-3 pt-2">
                        <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="inviteOpen = false">Cancel</button>
                        <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Send invite</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-super-admin.page>

@if ($errors->has('email'))
<script>
    document.addEventListener('alpine:init', () => {
        const root = document.querySelector('.figma-sa-users');
        if (root && root._x_dataStack) root._x_dataStack[0].inviteOpen = true;
    });
</script>
@endif
@endsection
