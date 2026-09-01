@extends('layouts.super-admin')

@section('title', $user->name)

@section('content')
@php
    $status = $user->status ?? 'active';
    $currentPlan = $user->currentPlan();
    $isTrialing = $activeSubscription
        && ($activeSubscription->status === 'trialing'
            || ($activeSubscription->is_trial && $activeSubscription->trial_ends_at?->isFuture()));
    $planLabel = $currentPlan?->name ?? ($isTrialing ? 'Trial' : 'No plan');
    $accessLevel = static function (?string $roleSlug): array {
        return match ($roleSlug) {
            'team-managing' => ['label' => 'Full access', 'tone' => 'full'],
            'team-editor' => ['label' => 'Limited access', 'tone' => 'limited'],
            'team-viewable' => ['label' => 'View only', 'tone' => 'view'],
            default => ['label' => 'Full access', 'tone' => 'full'],
        };
    };
@endphp

<x-super-admin.page :title="$user->name" subtitle="Profile, plan, and role history">
    <div class="figma-sa-user-detail" x-data="{ editAccount: false, advancedOpen: false }">
        <nav class="figma-sa-user-detail-crumb" aria-label="Breadcrumb">
            <a href="{{ route('super-admin.users.index') }}">Users</a>
            <span aria-hidden="true">&gt;</span>
            <span>{{ $user->name }}</span>
        </nav>

        <div class="figma-sa-user-detail-topbar">
            <div class="figma-sa-user-detail-topbar-actions">
                <form method="POST" action="{{ route('super-admin.users.impersonate', $user) }}">
                    @csrf
                    <button type="submit" class="figma-sa-user-detail-top-btn" @disabled($user->is_super_admin)>Login as user</button>
                </form>
                <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}" onsubmit="return confirm('Reset password for this user?')">
                    @csrf
                    <button type="submit" class="figma-sa-user-detail-top-btn">Reset password</button>
                </form>
                <form method="POST" action="{{ route('super-admin.users.status', $user) }}" onsubmit="return confirm('Deactivate this user?')">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="suspended">
                    <button type="submit" class="figma-sa-user-detail-top-btn figma-sa-user-detail-top-btn--danger">Deactivate user</button>
                </form>
            </div>
        </div>

        {{-- Summary card --}}
        <section class="figma-sa-user-detail-summary">
            <div class="figma-sa-user-detail-summary-main">
                <span class="figma-sa-users-avatar figma-sa-user-detail-avatar" aria-hidden="true">
                    @include('partials.user-avatar', ['avatarUser' => $user, 'avatarTextClass' => 'text-[20px] font-semibold leading-none text-[#FF6600]'])
                </span>
                <div class="figma-sa-user-detail-summary-copy">
                    <h2 class="figma-sa-user-detail-name">{{ $user->name }}</h2>
                    <p class="figma-sa-user-detail-email">{{ $user->email }}</p>
                </div>
            </div>
            <div class="figma-sa-user-detail-summary-actions">
                <button type="button" class="figma-sa-user-detail-outline-btn" @click="editAccount = !editAccount; if(editAccount) { document.getElementById('account-details')?.scrollIntoView({ behavior: 'smooth' }) }">Edit user</button>
                <button type="button" class="figma-sa-user-detail-primary-btn" @click="advancedOpen = !advancedOpen">Advanced</button>
            </div>
            <dl class="figma-sa-user-detail-meta-strip">
                <div>
                    <dt>Role</dt>
                    <dd>{{ $user->role?->name ?? ($user->is_super_admin ? 'Super Admin' : '—') }}</dd>
                </div>
                <div>
                    <dt>Verified</dt>
                    <dd>{{ $user->hasVerifiedEmail() ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt>Last login</dt>
                    <dd>{{ $user->last_login_at?->timezone(config('app.timezone'))->format('M j, Y g:i a') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Member since</dt>
                    <dd>{{ $user->created_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>
                        <x-super-admin.status-pill
                            :tone="\App\Support\StatusTone::user($status)"
                            :label="ucfirst($status)" />
                    </dd>
                </div>
                <div>
                    <dt>Current plan</dt>
                    <dd>{{ $planLabel }}</dd>
                </div>
            </dl>
        </section>

        {{-- Three info cards --}}
        <div class="figma-sa-user-detail-cards-row figma-sa-user-detail-cards-row--triple">
            <section class="figma-sa-user-detail-card" id="account-details">
                <header class="figma-sa-user-detail-card-head">
                    <h3>Account details</h3>
                    <button type="button" class="figma-sa-user-detail-card-edit" @click="editAccount = !editAccount" aria-label="Edit account">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                </header>
                <div x-show="!editAccount" x-cloak>
                    <dl class="figma-sa-user-detail-kv">
                        <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
                        <div><dt>Phone</dt><dd>{{ $user->phone ?: '—' }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                        <div><dt>Language</dt><dd>{{ $profileLanguage ?? 'English' }}</dd></div>
                        <div><dt>Role</dt><dd>{{ $user->role?->name ?? '—' }}</dd></div>
                        <div><dt>Two-factor auth</dt><dd><span class="figma-sa-user-detail-access-pill figma-sa-user-detail-access-pill--{{ $user->hasTwoFactorEnabled() ? 'full' : 'view' }}">{{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Disabled' }}</span></dd></div>
                        <div><dt>Status</dt><dd><x-super-admin.status-pill :tone="\App\Support\StatusTone::user($status)" :label="ucfirst($status)" /></dd></div>
                        <div><dt>Domains assigned</dt><dd>{{ $user->domains->count() }}</dd></div>
                    </dl>
                </div>
                <form x-show="editAccount" x-cloak method="POST" action="{{ route('super-admin.users.update', $user) }}" class="figma-sa-user-detail-plan-form">
                    @csrf
                    @method('PUT')
                    <div class="figma-sa-user-detail-field figma-sa-user-detail-field--compact">
                        <label class="figma-sa-label" for="edit-name">Name</label>
                        <input type="text" name="name" id="edit-name" value="{{ old('name', $user->name) }}" required class="figma-sa-user-detail-input">
                    </div>
                    <div class="figma-sa-user-detail-field figma-sa-user-detail-field--compact">
                        <label class="figma-sa-label" for="edit-email">Email</label>
                        <input type="email" name="email" id="edit-email" value="{{ old('email', $user->email) }}" required class="figma-sa-user-detail-input">
                    </div>
                    <div class="figma-sa-user-detail-field figma-sa-user-detail-field--compact">
                        <label class="figma-sa-label" for="edit-status">Status</label>
                        <select name="status" id="edit-status" class="figma-sa-user-detail-select">
                            @foreach (['active', 'suspended', 'pending', 'banned'] as $s)
                                <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($isWorkspaceOwner ?? false)
                        <div class="figma-sa-user-detail-field figma-sa-user-detail-field--compact">
                            <label class="figma-sa-label" for="edit-role-id">Role</label>
                            <select name="role_id" id="edit-role-id" class="figma-sa-user-detail-select">
                                <option value="">— No role —</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <input type="hidden" name="is_admin" value="{{ $user->is_admin ? '1' : '0' }}">
                    <input type="hidden" name="is_super_admin" value="{{ $user->is_super_admin ? '1' : '0' }}">
                    <button type="submit" class="figma-sa-user-detail-primary-btn figma-sa-user-detail-submit">Save changes</button>
                </form>
            </section>

            <section class="figma-sa-user-detail-card" id="assign-plan">
                <header class="figma-sa-user-detail-card-head">
                    <h3>Plan &amp; billing</h3>
                </header>
                <dl class="figma-sa-user-detail-kv">
                    <div><dt>Plan type</dt><dd>{{ $planLabel }}</dd></div>
                    <div><dt>Payment method</dt><dd>{{ $primaryPaymentMethod?->maskedLabel() ?? '—' }}</dd></div>
                    <div><dt>Billing cycle</dt><dd>{{ $activeSubscription?->billing_interval ? ucfirst($activeSubscription->billing_interval) : '—' }}</dd></div>
                    <div><dt>Status</dt><dd><span class="figma-sa-user-detail-access-pill figma-sa-user-detail-access-pill--full">{{ $activeSubscription ? ucfirst($activeSubscription->status) : 'None' }}</span></dd></div>
                    <div><dt>Next billing date</dt><dd>{{ $activeSubscription?->current_period_ends_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</dd></div>
                </dl>
                @if (Route::has('super-admin.payments.index'))
                    <a href="{{ route('super-admin.payments.index', ['search' => $user->email]) }}" class="figma-sa-user-detail-card-link">View invoices</a>
                @endif
            </section>

            <section class="figma-sa-user-detail-card">
                <header class="figma-sa-user-detail-card-head">
                    <h3>Quick actions</h3>
                </header>
                <div class="figma-sa-user-detail-quick-grid">
                    <form method="POST" action="{{ route('super-admin.users.impersonate', $user) }}">
                        @csrf
                        <button type="submit" class="figma-sa-user-detail-quick-btn" @disabled($user->is_super_admin)>Login as user</button>
                    </form>
                    <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}" onsubmit="return confirm('Reset password?')">
                        @csrf
                        <button type="submit" class="figma-sa-user-detail-quick-btn">Reset password</button>
                    </form>
                    <form method="POST" action="{{ route('super-admin.users.status', $user) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="suspended">
                        <button type="submit" class="figma-sa-user-detail-quick-btn figma-sa-user-detail-quick-btn--danger">Deactivate user</button>
                    </form>
                    <a href="#assign-plan" class="figma-sa-user-detail-quick-btn figma-sa-user-detail-quick-btn--accent">Manage plan</a>
                    <a href="#login-history" class="figma-sa-user-detail-quick-btn">View activity logs</a>
                    <a href="#login-history" class="figma-sa-user-detail-quick-btn">Impersonation history</a>
                </div>
            </section>
        </div>

        {{-- Assignments row --}}
        <div class="figma-sa-user-detail-cards-row figma-sa-user-detail-cards-row--duo">
            <section class="figma-sa-user-detail-card figma-sa-user-detail-card--table" id="assign-team">
                <header class="figma-sa-user-detail-card-head">
                    <div>
                        <h3>Department / Team assignments</h3>
                    </div>
                </header>
                <div class="figma-sa-user-detail-assign-shell">
                    <table class="figma-sa-user-detail-assign-table">
                        <thead>
                            <tr>
                                <th>Department / Team</th>
                                <th>Role</th>
                                <th>Access level</th>
                                <th>Assigned by</th>
                                <th>Assigned on</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($userTeams as $team)
                                @php $access = $accessLevel($user->role?->slug); @endphp
                                <tr>
                                    <td>{{ $team->name }}</td>
                                    <td>{{ $user->role?->name ?? '—' }}</td>
                                    <td><span class="figma-sa-user-detail-access-pill figma-sa-user-detail-access-pill--{{ $access['tone'] }}">{{ $access['label'] }}</span></td>
                                    <td>{{ $assignedByUsers[$team->pivot->assigned_by ?? 0]?->name ?? 'Super Admin' }}</td>
                                    <td>{{ $team->pivot->created_at ? \Illuminate\Support\Carbon::parse($team->pivot->created_at)->format('M j, Y') : '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('super-admin.users.assign-team', $user) }}" class="figma-sa-user-detail-inline-form">
                                            @csrf
                                            <input type="hidden" name="team_id" value="{{ $team->id }}">
                                            <input type="hidden" name="action" value="remove">
                                            <button type="submit" class="figma-sa-user-detail-link-btn figma-sa-user-detail-link-btn--danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="figma-sa-user-detail-assign-empty">Not assigned to any operational team.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('super-admin.users.assign-team', $user) }}" class="figma-sa-user-detail-inline-assign">
                    @csrf
                    <input type="hidden" name="action" value="assign">
                    @if (($assignableTeams ?? collect())->isNotEmpty())
                        <select name="team_id" required class="figma-sa-user-detail-select figma-sa-user-detail-select--compact">
                            @foreach ($assignableTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="figma-sa-user-detail-primary-btn figma-sa-user-detail-primary-btn--sm">Assign department</button>
                    @endif
                </form>
            </section>

            <section class="figma-sa-user-detail-card figma-sa-user-detail-card--table">
                <header class="figma-sa-user-detail-card-head">
                    <h3>Assigned domains</h3>
                </header>
                <div class="figma-sa-user-detail-assign-shell">
                    <table class="figma-sa-user-detail-assign-table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Access level</th>
                                <th>Assigned by</th>
                                <th>Assigned on</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($user->domains as $domain)
                                <tr>
                                    <td>{{ $domain->hostname }}</td>
                                    <td><span class="figma-sa-user-detail-access-pill figma-sa-user-detail-access-pill--full">Full access</span></td>
                                    <td>Super Admin</td>
                                    <td>{{ $domain->created_at?->format('M j, Y') ?? '—' }}</td>
                                    <td><span class="figma-sa-user-detail-muted">—</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="figma-sa-user-detail-assign-empty">No domains assigned.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- Portal members --}}
        @if ($isWorkspaceOwner ?? false)
            <section class="figma-sa-user-detail-portal" id="portal-users">
                <header class="figma-sa-user-detail-portal-head">
                    <div>
                        <h3>Portal members</h3>
                        <p>These are members added by {{ $user->name }} in their portal.</p>
                    </div>
                    <div class="figma-sa-user-detail-portal-head-actions">
                        <a href="{{ route('super-admin.users.index', ['tab' => 'teams', 'search' => $user->email]) }}" class="figma-sa-user-detail-primary-btn figma-sa-user-detail-primary-btn--sm">Add member</a>
                    </div>
                </header>

                <div class="figma-sa-user-detail-portal-shell">
                    <div class="figma-sa-table-scroll">
                        <table class="figma-sa-user-detail-portal-table">
                            <thead>
                                <tr>
                                    <th class="w-10"><input type="checkbox" class="figma-sa-users-checkbox" aria-label="Select all members"></th>
                                    <th>Member</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department / Team</th>
                                    <th>Status</th>
                                    <th>Added on</th>
                                    <th>Last active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($portalUsers as $portal)
                                    @php
                                        $portalAccess = $accessLevel($portal->role?->slug);
                                        $portalTeam = $portal->teams->first();
                                    @endphp
                                    <tr>
                                        <td><input type="checkbox" class="figma-sa-users-checkbox" aria-label="Select {{ $portal->name }}"></td>
                                        <td>
                                            <div class="figma-sa-users-usercell">
                                                <span class="figma-sa-users-avatar figma-sa-users-avatar--sm" aria-hidden="true">
                                                    @include('partials.user-avatar', ['avatarUser' => $portal, 'avatarTextClass' => 'text-[10px] font-semibold leading-none text-[#FF6600]'])
                                                </span>
                                                <a href="{{ route('super-admin.users.show', $portal) }}" class="figma-sa-user-detail-portal-name">{{ $portal->name }}</a>
                                            </div>
                                        </td>
                                        <td>{{ $portal->email }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('super-admin.users.portal-members.update-role', [$user, $portal]) }}" class="figma-sa-user-detail-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <select name="role_id" class="figma-sa-user-detail-select figma-sa-user-detail-select--table" onchange="this.form.submit()">
                                                    @foreach ($teamRoles as $role)
                                                        <option value="{{ $role->id }}" @selected($portal->role_id === $role->id)>{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            @if (($assignableTeams ?? collect())->isNotEmpty())
                                                <form method="POST" action="{{ route('super-admin.users.assign-team', $portal) }}" class="figma-sa-user-detail-inline-form">
                                                    @csrf
                                                    <input type="hidden" name="action" value="assign">
                                                    <select name="team_id" class="figma-sa-user-detail-select figma-sa-user-detail-select--table" onchange="this.form.submit()">
                                                        <option value="">—</option>
                                                        @foreach ($assignableTeams as $team)
                                                            <option value="{{ $team->id }}" @selected($portalTeam?->id === $team->id)>{{ $team->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                            @else
                                                {{ $portalTeam?->name ?? '—' }}
                                            @endif
                                        </td>
                                        <td>
                                            <x-super-admin.status-pill
                                                :tone="\App\Support\StatusTone::user($portal->status ?? 'active')"
                                                :label="ucfirst($portal->status ?? 'active')" />
                                        </td>
                                        <td>{{ $portal->created_at?->format('M j, Y') ?? '—' }}</td>
                                        <td>{{ $portal->last_login_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                                        <td>
                                            <div class="figma-sa-user-detail-row-actions">
                                                <a href="{{ route('super-admin.users.show', $portal) }}#account-details" class="figma-sa-user-detail-link-btn">Edit role</a>
                                                <form method="POST" action="{{ route('super-admin.users.portal-members.destroy', [$user, $portal]) }}" class="figma-sa-user-detail-inline-form" onsubmit="return confirm('Remove {{ $portal->email }}? This deletes their account.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="figma-sa-user-detail-link-btn figma-sa-user-detail-link-btn--danger">Remove</button>
                                                </form>
                                                <x-super-admin.user-action-menu :user="$portal" />
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="figma-sa-user-detail-portal-empty">No portal members under this owner yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if (($portalUsers ?? collect())->isNotEmpty())
                    <form method="POST" action="{{ route('super-admin.users.transfer-ownership', $user) }}" class="figma-sa-user-detail-transfer" onsubmit="return confirm('Transfer workspace ownership to this email?')">
                        @csrf
                        <label class="figma-sa-label" for="transfer-owner-email">Transfer ownership to</label>
                        <div class="figma-sa-user-detail-transfer-row">
                            <input type="email" name="email" id="transfer-owner-email" required class="figma-sa-user-detail-input" placeholder="member@example.com" list="portal-member-emails">
                            <button type="submit" class="figma-sa-user-detail-primary-btn">Transfer ownership</button>
                        </div>
                        <datalist id="portal-member-emails">
                            @foreach ($portalUsers as $portal)
                                <option value="{{ $portal->email }}"></option>
                            @endforeach
                        </datalist>
                    </form>
                @endif
            </section>
        @endif

        {{-- Advanced / history --}}
        <div x-show="advancedOpen" x-cloak class="figma-sa-user-detail-advanced">
            <section class="figma-sa-user-detail-card" id="assign-plan-form">
                <header class="figma-sa-user-detail-card-head"><h3>Assign plan</h3></header>
                <form method="POST" action="{{ route('super-admin.users.assign-plan', $user) }}" class="figma-sa-user-detail-plan-form">
                    @csrf
                    @if ($assignablePlans->isEmpty())
                        <p class="figma-sa-user-detail-muted">No active plans.</p>
                    @else
                        <div class="figma-sa-user-detail-field figma-sa-user-detail-field--compact">
                            <label class="figma-sa-label" for="assign-plan-id">Plan</label>
                            <select name="plan_id" id="assign-plan-id" required class="figma-sa-user-detail-select">
                                @foreach ($assignablePlans as $plan)
                                    <option value="{{ $plan->id }}" @selected($currentPlan?->id === $plan->id)>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="figma-sa-user-detail-field figma-sa-user-detail-field--compact">
                            <label class="figma-sa-label" for="assign-billing-interval">Billing</label>
                            <select name="billing_interval" id="assign-billing-interval" class="figma-sa-user-detail-select">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <button type="submit" class="figma-sa-user-detail-primary-btn figma-sa-user-detail-submit">Assign plan</button>
                    @endif
                </form>
            </section>

            <section class="figma-sa-user-detail-card figma-sa-user-detail-card--table" id="roles">
                <header class="figma-sa-user-detail-card-head"><h3>Role change history</h3></header>
                <div class="figma-sa-user-detail-assign-shell">
                    <table class="figma-sa-user-detail-assign-table">
                        <thead><tr><th>When</th><th>From</th><th>To</th><th>By</th></tr></thead>
                        <tbody>
                            @forelse ($user->roleChanges as $change)
                                <tr>
                                    <td>{{ $change->created_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                                    <td>{{ $change->oldRole?->name ?? '—' }}</td>
                                    <td>{{ $change->newRole?->name ?? '—' }}</td>
                                    <td>{{ $change->changedBy?->email ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="figma-sa-user-detail-assign-empty">No role changes recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="figma-sa-user-detail-card figma-sa-user-detail-card--table" id="login-history">
                <header class="figma-sa-user-detail-card-head"><h3>Login history</h3></header>
                <div class="figma-sa-user-detail-assign-shell">
                    <table class="figma-sa-user-detail-assign-table">
                        <thead><tr><th>When</th><th>IP</th><th>Device</th><th>Browser</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($user->loginHistories as $entry)
                                <tr>
                                    <td>{{ $entry->created_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                                    <td>{{ $entry->ip_address ?? '—' }}</td>
                                    <td>{{ $entry->device ?? '—' }}</td>
                                    <td>{{ $entry->browser ?? '—' }}</td>
                                    <td><x-super-admin.status-pill :tone="\App\Support\StatusTone::user($entry->status)" :label="ucfirst($entry->status)" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="figma-sa-user-detail-assign-empty">No logins recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-super-admin.page>
@endsection
