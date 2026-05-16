@extends('layouts.super-admin')

@section('title', $user->name)
@section('content')
<x-super-admin.page :title="$user->name" subtitle="Profile, plan, and role history">
<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('super-admin.users.index') }}" class="figma-sa-btn figma-sa-btn-outline !px-3 !py-2 text-sm">← Users</a>
    </div>

    <x-super-admin.card>
        <h2 class="text-base font-semibold text-white">Account</h2>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-[#8c8787]">Name</dt>
                <dd class="font-medium text-white">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-[#8c8787]">Email</dt>
                <dd class="font-medium text-white">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-[#8c8787]">Verified</dt>
                <dd class="text-white">{{ $user->hasVerifiedEmail() ? 'Yes' : 'No' }}</dd>
            </div>
            <div>
                <dt class="text-[#8c8787]">Last login</dt>
                <dd class="text-white">{{ $user->last_login_at?->timezone(config('app.timezone'))->format('M j, Y g:i a') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[#8c8787]">Status</dt>
                <dd><span class="figma-sa-pill figma-sa-pill-neutral">{{ ucfirst($user->status ?? 'active') }}</span></dd>
            </div>
            <div>
                <dt class="text-[#8c8787]">Role</dt>
                <dd class="text-white">{{ $user->role?->name ?? '—' }}</dd>
            </div>
        </dl>
    </x-super-admin.card>

    <x-super-admin.card>
        <h2 class="text-base font-semibold text-white">Assign plan</h2>
        <p class="mt-1 text-sm text-[#a9a9a9]">Replaces any active or trialing subscription with a new active subscription.</p>
        <form method="POST" action="{{ route('super-admin.users.assign-plan', $user) }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            @if ($assignablePlans->isEmpty())
                <p class="text-sm text-[#8c8787]">No active plans. Create one under Plans &amp; Pricing first.</p>
            @else
            <div class="min-w-48 flex-1">
                <label class="figma-sa-label">Plan</label>
                <select name="plan_id" required class="figma-select mt-1">
                    @foreach ($assignablePlans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }} ({{ strtoupper($plan->currency) }} {{ number_format($plan->price_cents / 100, 2) }}/mo)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="figma-sa-label">Billing</label>
                <select name="billing_interval" class="figma-select mt-1">
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Assign</button>
            @endif
        </form>
    </x-super-admin.card>

    <x-super-admin.card class="!p-0 overflow-hidden">
        <div class="border-b border-white/10 px-4 py-3">
            <h2 class="text-base font-semibold text-white">Role change history</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="figma-sa-table min-w-[720px]">
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
                            <td class="text-[#d9d9d9]">{{ $change->created_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                            <td>{{ $change->oldRole?->name ?? '—' }}</td>
                            <td>{{ $change->newRole?->name ?? '—' }}</td>
                            <td>{{ $change->changedBy?->email ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-[#8c8787]">No role changes recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-super-admin.card>

    <x-super-admin.card class="!p-0 overflow-hidden">
        <div class="border-b border-white/10 px-4 py-3">
            <h2 class="text-base font-semibold text-white">Login history</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="figma-sa-table min-w-[720px]">
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
                            <td class="text-[#d9d9d9]">{{ $entry->created_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                            <td class="font-mono text-xs">{{ $entry->ip_address ?? '—' }}</td>
                            <td>{{ $entry->device ?? '—' }}</td>
                            <td>{{ $entry->browser ?? '—' }}</td>
                            <td><span class="figma-sa-pill figma-sa-pill-neutral">{{ ucfirst($entry->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-[#8c8787]">No logins recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        
        </div>
    </x-super-admin.card>

    <x-super-admin.card>
        <h2 class="text-base font-semibold text-white">Quick actions</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('super-admin.users.impersonate', $user) }}">
                @csrf
                <button type="submit" class="figma-sa-btn figma-sa-btn-outline text-sm" @disabled($user->is_super_admin)>Login as user</button>
            </form>
            <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}" onsubmit="return confirm('Reset password for this user?')">
                @csrf
                <button type="submit" class="figma-sa-btn figma-sa-btn-outline text-sm">Reset password</button>
            </form>
        </div>
    </x-super-admin.card>
</div>
</x-super-admin.page>
@endsection
