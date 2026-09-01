@extends('layouts.super-admin')

@section('title', 'Users & Teams')

@section('content')
@php
    $query = request()->except(['page']);
    $dateLabel = request('date')
        ? \Illuminate\Support\Carbon::parse(request('date'))->format('n/j/Y')
        : '12/1/2026';
@endphp

@php
    $teamsCatalogJs = ($assignableTeams ?? collect())->map(fn ($t) => [
        'id' => $t->id,
        'name' => $t->name,
        'member_ids' => $t->members->pluck('id')->values()->all(),
    ])->values();
    $assignableUsersJs = ($teamAssignableUsers ?? collect())->map(fn ($u) => [
        'id' => $u->id,
        'name' => $u->name,
        'email' => $u->email,
    ])->values();
@endphp

<x-super-admin.page title="Users & Teams">
    <div class="figma-sa-users" x-data="{
        tab: @js($tab),
        inviteOpen: false,
        createTeamOpen: false,
        createTeamModalOpen: false,
        inviteMode: 'invite',
        teamsCatalog: @js($teamsCatalogJs),
        assignableUsers: @js($assignableUsersJs),
        teamColumnMenuId: null,
        teamAssignModal: { open: false, teamId: null, teamName: '', userId: '' },
        toggleTeamColumnMenu(teamId) {
            this.teamColumnMenuId = this.teamColumnMenuId === teamId ? null : teamId;
        },
        openTeamAssignModal(teamId, teamName) {
            this.teamColumnMenuId = null;
            this.teamAssignModal = { open: true, teamId, teamName, userId: '' };
        },
        usersAvailableForTeam(teamId) {
            const team = this.teamsCatalog.find((t) => t.id === teamId);
            if (!team) return this.assignableUsers;
            return this.assignableUsers.filter((u) => !team.member_ids.includes(u.id));
        },
        teamModal: { open: false, loading: false, ownerName: '', ownerEmail: '', members: [], error: '' },
        async openTeamModal(userId, ownerName, ownerEmail) {
            this.teamModal = { open: true, loading: true, ownerName, ownerEmail, members: [], error: '' };
            try {
                const res = await fetch(@js(url('/super-admin/users')).replace(/\/$/, '') + '/' + userId + '/team-members', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('load failed');
                const data = await res.json();
                this.teamModal.members = data.members || [];
            } catch (e) {
                this.teamModal.error = 'Could not load team members.';
            } finally {
                this.teamModal.loading = false;
            }
        },
    }">
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
                    <button type="button" class="figma-sa-users-filter-option" onclick="document.getElementById('filter-plan').value='{{ $plan->slug }}'; document.getElementById('users-filter-form').submit();">{{ $plan->name }}</button>
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
                        class="figma-sa-users-filter-option"
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

            <div class="figma-sa-users-toolbar-actions">
                @if ($tab === 'teams')
                    <button type="button" @click="createTeamModalOpen = true" class="figma-sa-users-create-team-btn">
                        Create Team
                    </button>
                @endif

                <button type="button" @click="createTeamOpen = true; inviteMode = 'invite'" class="figma-sa-users-invite-btn figma-sa-users-invite-btn--toolbar">
                    <span class="figma-sa-users-invite-icon" aria-hidden="true">+</span>
                    @if ($tab === 'teams')
                        Add portal member
                    @else
                        Invite / Create
                    @endif
                </button>
            </div>
        </form>

        @if ($tab === 'users' || $tab === 'teams')
            <div @class([
                'figma-sa-users-table-shell',
                'figma-sa-users-table-shell--teams' => $tab === 'teams',
                'mb-[20px]' => $tab === 'teams',
            ])>
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
                                <th>Members</th>
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
                                    $memberCount = (int) ($user->team_members_count ?? 0);
                                @endphp
                                <tr>
                                    <td><input type="checkbox" class="figma-sa-users-checkbox" aria-label="Select {{ $user->name }}"></td>
                                    <td>
                                        <div class="figma-sa-users-usercell">
                                            <span class="figma-sa-users-avatar" aria-hidden="true">
                                                @include('partials.user-avatar', ['avatarUser' => $user, 'avatarTextClass' => 'text-[11px] font-semibold leading-none text-[#FF6600]'])
                                            </span>
                                            <span>
                                                <span class="figma-sa-users-name">{{ $user->name }}</span>
                                                <span class="figma-sa-users-subemail">{{ $user->email }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="figma-sa-users-members-col">
                                        @if ($memberCount > 0)
                                            <button
                                                type="button"
                                                class="figma-sa-users-members-count"
                                                @click="openTeamModal({{ $user->id }}, @js($user->name), @js($user->email))"
                                                title="View team members"
                                            >{{ $memberCount }}</button>
                                        @else
                                            <span class="figma-sa-users-members-empty">0</span>
                                        @endif
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
                                        <x-super-admin.status-pill
                                            :tone="\App\Support\StatusTone::user($status)"
                                            :label="ucfirst($status)" />
                                    </td>
                                    <td class="figma-sa-users-date-col">{{ $user->created_at?->format('n/j/Y') ?? '—' }}</td>
                                    <td class="text-right">
                                        <x-super-admin.user-action-menu :user="$user" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="figma-sa-users-empty">
                                        @if ($tab === 'teams')
                                            No workspace owners with invited team members yet.
                                        @else
                                            No users found.
                                        @endif
                                    </td>
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
        @endif

        @if ($tab === 'teams')
            @if (session('status'))
                <div class="mb-3 rounded-[8px] border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Admin-assigned team columns --}}
            <div class="figma-sa-teams-board">
                @forelse ($assignableTeams as $team)
                    <article class="figma-sa-teams-column">
                        <header class="figma-sa-teams-column-head">
                            <div class="figma-sa-teams-column-head-main">
                                <span class="figma-sa-teams-column-title">{{ $team->name }}</span>
                                @if ($team->description)
                                    <span class="figma-sa-teams-column-sub" title="{{ $team->description }}">{{ Str::limit($team->description, 40) }}</span>
                                @endif
                            </div>
                            <div class="figma-sa-teams-column-actions">
                                <button
                                    type="button"
                                    class="figma-sa-teams-kebab"
                                    @click.stop="toggleTeamColumnMenu({{ $team->id }})"
                                    aria-label="Team options for {{ $team->name }}"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
                                    </svg>
                                </button>
                                <div
                                    x-show="teamColumnMenuId === {{ $team->id }}"
                                    x-cloak
                                    @click.outside="teamColumnMenuId = null"
                                    class="figma-sa-teams-column-menu"
                                >
                                    <button
                                        type="button"
                                        class="figma-sa-teams-column-menu-item"
                                        @click="openTeamAssignModal({{ $team->id }}, @js($team->name))"
                                    >
                                        Add user
                                    </button>
                                </div>
                            </div>
                        </header>
                        <div class="figma-sa-teams-column-body">
                            @forelse ($team->members as $member)
                                <div class="figma-sa-teams-card">
                                    <span class="figma-sa-users-avatar figma-sa-users-avatar--sm" aria-hidden="true">
                                        @include('partials.user-avatar', ['avatarUser' => $member, 'avatarTextClass' => 'text-[10px] font-semibold leading-none text-[#FF6600]'])
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('super-admin.users.show', $member) }}" class="figma-sa-teams-card-name">{{ $member->name }}</a>
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
                                <p class="figma-sa-teams-empty">No members yet — use ⋮ Add user.</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <p class="figma-sa-teams-board-empty">No teams yet. Click <strong>Create Team</strong> to add your first column (Sales, Chat Support, etc.).</p>
                @endforelse
            </div>
        @endif

        {{-- Workspace team members modal --}}
        <div
            x-show="teamModal.open"
            x-cloak
            class="figma-sa-users-modal-backdrop"
            @keydown.escape.window="teamModal.open = false"
        >
            <div class="figma-sa-users-modal figma-sa-users-modal--team" @click.outside="teamModal.open = false" role="dialog" aria-labelledby="team-members-title">
                <button type="button" class="figma-sa-users-modal-close" @click="teamModal.open = false" aria-label="Close">&times;</button>
                <h2 id="team-members-title" class="figma-sa-users-modal-title">Team members</h2>
                <p class="figma-sa-users-modal-sub">
                    <span x-text="teamModal.ownerName"></span>
                    <span> · </span>
                    <span x-text="teamModal.ownerEmail"></span>
                </p>

                <div class="figma-sa-users-team-modal-body mt-4">
                    <p x-show="teamModal.loading" class="figma-sa-users-team-modal-hint">Loading members…</p>
                    <p x-show="teamModal.error" x-text="teamModal.error" class="figma-sa-users-team-modal-error" x-cloak></p>
                    <template x-if="!teamModal.loading && !teamModal.error && teamModal.members.length === 0">
                        <p class="figma-sa-users-team-modal-hint">No team members yet.</p>
                    </template>
                    <ul x-show="!teamModal.loading && teamModal.members.length" class="figma-sa-users-team-list" x-cloak>
                        <template x-for="member in teamModal.members" :key="member.id">
                            <li class="figma-sa-users-team-list-item">
                                <div class="min-w-0">
                                    <p class="figma-sa-users-team-list-name" x-text="member.name"></p>
                                    <p class="figma-sa-users-team-list-email" x-text="member.email"></p>
                                </div>
                                <span class="figma-sa-users-team-list-status" x-text="member.status ? member.status.charAt(0).toUpperCase() + member.status.slice(1) : 'Active'"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="button" class="figma-sa-btn figma-sa-btn-primary" @click="teamModal.open = false">Close</button>
                </div>
            </div>
        </div>

        {{-- Invite / Create users modal --}}
        @include('super-admin.users.partials.invite-create-modal', [
            'roles' => $roles,
            'plans' => $plans,
            'workspaceOwner' => null,
        ])

        @if ($tab === 'teams')
            @include('super-admin.users.partials.create-team-modal', ['departments' => $departments])

            {{-- Add user to team column --}}
            <div
                x-show="teamAssignModal.open"
                x-cloak
                class="figma-sa-users-modal-backdrop"
                @keydown.escape.window="teamAssignModal.open = false"
            >
                <div class="figma-sa-users-modal figma-sa-users-modal--team-assign" @click.outside="teamAssignModal.open = false" role="dialog" aria-labelledby="team-assign-title">
                    <button type="button" class="figma-sa-users-modal-close" @click="teamAssignModal.open = false" aria-label="Close">&times;</button>
                    <h2 id="team-assign-title" class="figma-sa-users-modal-title">Add user</h2>
                    <p class="figma-sa-users-modal-sub">
                        Assign to <strong x-text="teamAssignModal.teamName"></strong>
                    </p>
                    <form
                        method="POST"
                        :action="`{{ url('/super-admin/teams') }}/${teamAssignModal.teamId}/assign-member`"
                        class="mt-5 space-y-4"
                    >
                        @csrf
                        <div>
                            <label class="figma-sa-label">Select user</label>
                            <select name="user_id" required class="figma-select mt-1 w-full" x-model="teamAssignModal.userId">
                                <option value="">Select a user…</option>
                                <template x-for="user in usersAvailableForTeam(teamAssignModal.teamId)" :key="user.id">
                                    <option :value="user.id" x-text="`${user.name} (${user.email})`"></option>
                                </template>
                            </select>
                            <p x-show="usersAvailableForTeam(teamAssignModal.teamId).length === 0" class="mt-2 text-xs text-[#8c8787]">
                                All users are already in this team.
                            </p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-3 pt-2">
                            <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="teamAssignModal.open = false">Cancel</button>
                            <button
                                type="submit"
                                class="figma-sa-btn figma-sa-btn-primary"
                                :disabled="!teamAssignModal.userId || usersAvailableForTeam(teamAssignModal.teamId).length === 0"
                            >
                                Add to team
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @include('super-admin.users.partials.invite-create-modal', [
                'roles' => $roles,
                'teamRoles' => $teamRoles,
                'plans' => $plans,
                'workspaceOwner' => null,
                'workspaceOwners' => $workspaceOwners,
                'pickWorkspaceOwner' => true,
                'assignableTeams' => $assignableTeams,
                'modalOpenVar' => 'createTeamOpen',
            ])
        @endif
    </div>
</x-super-admin.page>

@if ($errors->any())
<script>
    document.addEventListener('alpine:init', () => {
        const root = document.querySelector('.figma-sa-users');
        if (root && root._x_dataStack) {
            const isTeamFlow = @json((bool) old('workspace_owner_id') || (bool) old('team_id'));
            root._x_dataStack[0].inviteOpen = !isTeamFlow;
            root._x_dataStack[0].createTeamOpen = isTeamFlow && @json((bool) old('workspace_owner_id'));
            root._x_dataStack[0].createTeamModalOpen = @json($errors->has('name') && ! old('workspace_owner_id'));
            root._x_dataStack[0].inviteMode = @json($errors->has('password') || ($errors->has('name') && ! $errors->has('email')) ? 'create' : 'invite');
        }
    });
</script>
@endif
@endsection
