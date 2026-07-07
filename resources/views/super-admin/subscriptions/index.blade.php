@extends('layouts.super-admin')

@section('title', 'Subscriptions')
@section('content')
<x-super-admin.page title="Subscriptions">
    <div class="space-y-[14px]">
        <form method="GET" class="flex flex-wrap items-center gap-[8px]">
            <a href="{{ route('super-admin.subscriptions.index') }}" @class(['rounded-[6px] px-[16px] py-[8px] text-[16px] font-medium text-white transition', 'bg-white/20' => ! request('status'), 'bg-white/10 hover:bg-white/20' => request('status')])>All Subscriptions</a>
            <a href="{{ route('super-admin.subscriptions.index', ['status' => 'active']) }}" @class(['rounded-[6px] px-[16px] py-[8px] text-[16px] font-medium text-white transition', 'bg-white/20' => request('status') === 'active', 'bg-white/10 hover:bg-white/20' => request('status') !== 'active'])>Active</a>
            <a href="{{ route('super-admin.subscriptions.index', ['status' => 'past_due']) }}" @class(['rounded-[6px] px-[16px] py-[8px] text-[16px] font-medium text-white transition', 'bg-white/20' => request('status') === 'past_due', 'bg-white/10 hover:bg-white/20' => request('status') !== 'past_due'])>On Hold</a>
            <a href="{{ route('super-admin.subscriptions.index', ['status' => 'cancelled']) }}" @class(['rounded-[6px] px-[16px] py-[8px] text-[16px] font-medium text-white transition', 'bg-white/20' => request('status') === 'cancelled', 'bg-white/10 hover:bg-white/20' => request('status') !== 'cancelled'])>Cancelled</a>

            <div class="ml-auto flex flex-wrap items-center gap-[8px]">
                <a href="{{ route('super-admin.subscriptions.index') }}" class="inline-flex h-[43px] items-center gap-[6px] rounded-[6px] bg-white/20 px-[16px] text-[16px] font-medium text-white hover:bg-white/30">
                    <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Export
                </a>
                <a href="{{ route('super-admin.plans.index') }}" class="inline-flex h-[43px] items-center gap-[6px] rounded-[6px] bg-[#6706b3] px-[16px] text-[16px] font-medium text-white hover:bg-[#7a1acc]">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    New Plan
                </a>
            </div>
        </form>

        <form method="GET" class="flex flex-wrap items-center gap-[8px]">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <label class="figma-sa-dash-search !min-w-[220px]">
                <svg class="h-[18px] w-[18px] shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search Subscriptions" class="figma-sa-dash-search-input">
            </label>
            <select name="plan_id" onchange="this.form.submit()" class="figma-select h-[34px] !text-[16px]">
                <option value="">All Plans</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="figma-sa-btn figma-sa-btn-outline !px-4 !py-2 text-[13px]">Search</button>
        </form>

        @foreach ($subscriptions as $subscription)
            <form id="sub-form-{{ $subscription->id }}" method="POST" action="{{ route('super-admin.subscriptions.update', $subscription) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        <div class="figma-sa-subs-panel overflow-hidden rounded-[6px] bg-[#6400b3]">
            <div class="overflow-x-auto">
                <table class="figma-sa-subs-table min-w-[960px] w-full">
                    <thead>
                        <tr>
                            <th class="w-[48px]"></th>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Billing Cycle</th>
                            <th>Next Payment</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $i => $subscription)
                            @php
                                $fid = 'sub-form-'.$subscription->id;
                                $initial = strtoupper(substr($subscription->user?->name ?? '?', 0, 1));
                                $planPrice = $subscription->plan
                                    ? '$'.number_format($subscription->plan->price_cents / 100, 0).' / '.($subscription->plan->billing_interval === 'yearly' ? 'yr.' : 'mo.')
                                    : '—';
                                $statusClass = match ($subscription->status) {
                                    'past_due' => 'is-past_due',
                                    'cancelled' => 'is-cancelled',
                                    'paused' => 'is-paused',
                                    default => 'is-active',
                                };
                            @endphp
                            <tr @class(['figma-sa-subs-row', 'is-alt' => $i % 2 === 1])>
                                <td>
                                    <input form="{{ $fid }}" type="checkbox" class="figma-sa-checkbox rounded" aria-label="Select row">
                                </td>
                                <td>
                                    <div class="flex items-center gap-[10px]">
                                        <span class="figma-sa-subs-avatar">{{ $initial }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate text-[16px] font-medium text-white">{{ $subscription->user?->name ?? 'Deleted user' }}</p>
                                            <p class="truncate text-[13px] font-medium text-white/80">{{ $subscription->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-[16px] font-medium text-white">{{ $subscription->plan?->name ?? 'No plan' }}</td>
                                <td>
                                    <select form="{{ $fid }}" onchange="this.form.submit()" name="status" class="figma-sa-subs-status {{ $statusClass }} border-0">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected($subscription->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-[16px] font-medium text-white">{{ ucfirst($subscription->billing_interval) }} {{ $planPrice }}</td>
                                <td class="text-[14px] font-medium text-white">{{ $subscription->current_period_ends_at?->format('M d,Y') ?? '—' }}</td>
                                <td class="text-right">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" @click="open = !open" class="figma-sa-dash-row-menu" aria-label="Row actions">⋯</button>
                                        </x-slot:trigger>
                                        @if ($subscription->user_id)
                                            <a href="{{ route('super-admin.users.show', $subscription->user_id) }}" class="figma-sa-dash-dropdown-item block text-left">View user</a>
                                        @endif
                                        <button form="{{ $fid }}" type="submit" class="figma-sa-dash-dropdown-item block w-full text-left">Save changes</button>
                                    </x-super-admin.dashboard-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-white/70">No subscriptions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination flex flex-wrap items-center justify-between gap-[10px] px-[24px] py-[16px]">
                <p class="text-[16px] font-medium text-white/90">Showing {{ $subscriptions->firstItem() ?? 0 }}-{{ $subscriptions->lastItem() ?? 0 }} of {{ $subscriptions->total() }}</p>
                <div>{{ $subscriptions->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
