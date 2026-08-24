@extends('layouts.super-admin')

@section('title', $user->name)

@section('content')
@php
    $status = $user->status ?? 'active';
    $currentPlan = $user->currentPlan();
    $activeSubscription = $user->activeSubscription();
    $isTrialing = $activeSubscription
        && ($activeSubscription->status === 'trialing'
            || ($activeSubscription->is_trial && $activeSubscription->trial_ends_at?->isFuture()));
    $planLabel = $currentPlan?->name ?? ($isTrialing ? 'Trial' : 'No plan');
@endphp

<x-super-admin.page :title="$user->name" subtitle="Profile, plan, and role history">
    <div class="figma-sa-user-detail">
        <div class="figma-sa-user-detail-toolbar">
            <a href="{{ route('super-admin.users.index') }}" class="figma-sa-user-detail-back">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Users
            </a>

            <div class="figma-sa-user-detail-toolbar-actions">
                <form method="POST" action="{{ route('super-admin.users.impersonate', $user) }}">
                    @csrf
                    <button type="submit" class="figma-sa-user-detail-action-btn" @disabled($user->is_super_admin)>Login as user</button>
                </form>
                <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}" onsubmit="return confirm('Reset password for this user?')">
                    @csrf
                    <button type="submit" class="figma-sa-user-detail-action-btn">Reset password</button>
                </form>
            </div>
        </div>

        <div class="figma-sa-user-detail-hero">
            <span class="figma-sa-users-avatar figma-sa-user-detail-avatar" aria-hidden="true">
                @include('partials.user-avatar', ['avatarUser' => $user, 'avatarTextClass' => 'text-[18px] font-semibold leading-none text-[#6400b2]'])
            </span>
            <div class="figma-sa-user-detail-hero-main">
                <div class="figma-sa-user-detail-hero-top">
                    <div>
                        <h2 class="figma-sa-user-detail-name">{{ $user->name }}</h2>
                        <p class="figma-sa-user-detail-email">{{ $user->email }}</p>
                    </div>
                    <div class="figma-sa-user-detail-badges">
                        <x-super-admin.status-pill
                            :tone="\App\Support\StatusTone::user($status)"
                            :label="ucfirst($status)" />
                        @if ($planLabel !== 'No plan')
                            <span class="figma-sa-users-plan-tag">{{ $planLabel }}</span>
                        @endif
                    </div>
                </div>
                <dl class="figma-sa-user-detail-meta">
                    <div>
                        <dt>Role</dt>
                        <dd>{{ $user->role?->name ?? '—' }}</dd>
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
                </dl>
            </div>
        </div>

        <div class="figma-sa-user-detail-grid">
            <section class="figma-sa-user-detail-panel">
                <div class="figma-sa-user-detail-panel-head">
                    <h2>Account</h2>
                    <p>Core profile and access details.</p>
                </div>
                <dl class="figma-sa-user-detail-fields">
                    <div>
                        <dt>Name</dt>
                        <dd>{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $user->email }}</dd>
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
                        <dt>Role</dt>
                        <dd>{{ $user->role?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Current plan</dt>
                        <dd>
                            @if ($currentPlan)
                                {{ $currentPlan->name }}
                                @if ($isTrialing)
                                    <span class="figma-sa-user-detail-hint">(trialing)</span>
                                @endif
                            @else
                                <span class="figma-sa-user-detail-muted">No active plan</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Domains</dt>
                        <dd>{{ $user->domains->count() }}</dd>
                    </div>
                </dl>
            </section>

            <section class="figma-sa-user-detail-panel" id="assign-plan">
                <div class="figma-sa-user-detail-panel-head">
                    <h2>Assign plan</h2>
                    <p>Replaces any active or trialing subscription with a new active subscription.</p>
                </div>

                <form method="POST" action="{{ route('super-admin.users.assign-plan', $user) }}" class="figma-sa-user-detail-plan-form">
                    @csrf
                    @if ($assignablePlans->isEmpty())
                        <p class="figma-sa-user-detail-muted">No active plans. Create one under Plans &amp; Pricing first.</p>
                    @else
                        <div class="figma-sa-user-detail-field">
                            <label class="figma-sa-label" for="assign-plan-id">Plan</label>
                            <div class="figma-sa-user-detail-select-wrap">
                                <select name="plan_id" id="assign-plan-id" required class="figma-sa-user-detail-select">
                                    @foreach ($assignablePlans as $plan)
                                        <option value="{{ $plan->id }}" @selected($currentPlan?->id === $plan->id)>
                                            {{ $plan->name }} ({{ strtoupper($plan->currency) }} {{ number_format($plan->price_cents / 100, 2) }}/mo)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="figma-sa-user-detail-field figma-sa-user-detail-field--compact">
                            <label class="figma-sa-label" for="assign-billing-interval">Billing</label>
                            <div class="figma-sa-user-detail-select-wrap">
                                <select name="billing_interval" id="assign-billing-interval" class="figma-sa-user-detail-select">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="figma-sa-users-invite-btn figma-sa-user-detail-submit">Assign plan</button>
                    @endif
                </form>
            </section>

            <section class="figma-sa-user-detail-panel" id="assign-team">
                <div class="figma-sa-user-detail-panel-head">
                    <h2>Team assignment</h2>
                    <p>Users are never auto-added to teams. Only Super Admin can assign.</p>
                </div>
                @if (($userTeams ?? collect())->isNotEmpty())
                    <ul class="mb-3 space-y-1 text-[13px] text-white/80">
                        @foreach ($userTeams as $team)
                            <li class="flex items-center justify-between gap-2 rounded border border-white/10 px-3 py-2">
                                <span>{{ $team->name }}</span>
                                <form method="POST" action="{{ route('super-admin.users.assign-team', $user) }}">
                                    @csrf
                                    <input type="hidden" name="team_id" value="{{ $team->id }}">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="text-[11px] text-rose-300 hover:text-rose-200">Remove</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="figma-sa-user-detail-muted mb-3">Unassigned — not on any team.</p>
                @endif
                <form method="POST" action="{{ route('super-admin.users.assign-team', $user) }}" class="figma-sa-user-detail-plan-form">
                    @csrf
                    <input type="hidden" name="action" value="assign">
                    @if (($assignableTeams ?? collect())->isEmpty())
                        <p class="figma-sa-user-detail-muted">No teams yet. Run AdminPanelBootstrapSeeder or create teams.</p>
                    @else
                        <div class="figma-sa-user-detail-field">
                            <label class="figma-sa-label" for="assign-team-id">Assign to team</label>
                            <div class="figma-sa-user-detail-select-wrap">
                                <select name="team_id" id="assign-team-id" required class="figma-sa-user-detail-select">
                                    @foreach ($assignableTeams as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="figma-sa-users-invite-btn figma-sa-user-detail-submit">Assign team</button>
                    @endif
                </form>
            </section>
        </div>

        <section class="figma-sa-user-detail-panel figma-sa-user-detail-panel--table" id="portal-users">
            <div class="figma-sa-user-detail-panel-head">
                <h2>Portal users</h2>
                <p>Seat users under this account owner (not the same as operational Teams).</p>
            </div>
            <div class="figma-sa-products-table-shell figma-sa-user-detail-table-shell">
                <div class="figma-sa-table-scroll">
                    <table class="figma-sa-products-table figma-sa-user-detail-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($portalUsers ?? [] as $portal)
                                <tr>
                                    <td>
                                        <a href="{{ route('super-admin.users.show', $portal) }}" class="text-[#FFB380] hover:underline">{{ $portal->name }}</a>
                                    </td>
                                    <td>{{ $portal->email }}</td>
                                    <td>{{ $portal->role?->name ?? '—' }}</td>
                                    <td>
                                        <x-super-admin.status-pill
                                            :tone="\App\Support\StatusTone::user($portal->status ?? 'active')"
                                            :label="ucfirst($portal->status ?? 'active')" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="figma-sa-products-empty">No portal users under this owner.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="figma-sa-user-detail-panel figma-sa-user-detail-panel--table" id="roles">
            <div class="figma-sa-user-detail-panel-head">
                <h2>Role change history</h2>
            </div>
            <div class="figma-sa-products-table-shell figma-sa-user-detail-table-shell">
                <div class="figma-sa-table-scroll">
                    <table class="figma-sa-products-table figma-sa-user-detail-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>From</th>
                                <th>To</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($user->roleChanges as $change)
                                <tr>
                                    <td>{{ $change->created_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                                    <td>{{ $change->oldRole?->name ?? '—' }}</td>
                                    <td>{{ $change->newRole?->name ?? '—' }}</td>
                                    <td>{{ $change->changedBy?->email ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="figma-sa-products-empty">No role changes recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="figma-sa-user-detail-panel figma-sa-user-detail-panel--table">
            <div class="figma-sa-user-detail-panel-head">
                <h2>Login history</h2>
            </div>
            <div class="figma-sa-products-table-shell figma-sa-user-detail-table-shell">
                <div class="figma-sa-table-scroll">
                    <table class="figma-sa-products-table figma-sa-user-detail-table figma-sa-user-detail-table--logins">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>IP</th>
                                <th>Device</th>
                                <th>Browser</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($user->loginHistories as $entry)
                                <tr>
                                    <td>{{ $entry->created_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                                    <td>
                                        <span class="figma-sa-user-detail-ip" title="{{ $entry->ip_address ?? '' }}">{{ $entry->ip_address ?? '—' }}</span>
                                    </td>
                                    <td>{{ $entry->device ?? '—' }}</td>
                                    <td>{{ $entry->browser ?? '—' }}</td>
                                    <td>
                                        <x-super-admin.status-pill
                                            :tone="\App\Support\StatusTone::user($entry->status)"
                                            :label="ucfirst($entry->status)" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="figma-sa-products-empty">No logins recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-super-admin.page>
@endsection
