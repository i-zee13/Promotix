@extends('layouts.super-admin')

@section('title', 'Subscriptions')

@section('content')
@php
    $currentStatus = request('status', '');
    $statusTabs = [
        '' => 'All Subscriptions',
        'active' => 'Active',
        'past_due' => 'On Hold',
        'cancelled' => 'Cancelled',
    ];
    $statusFilterLabel = match ($currentStatus) {
        'active' => 'Active',
        'past_due' => 'On Hold',
        'cancelled' => 'Cancelled',
        'pending' => 'Pending',
        'paused' => 'Paused',
        'trialing' => 'Trialing',
        default => 'All Statuses',
    };
    $planFilterLabel = request('plan_id')
        ? ($plans->firstWhere('id', (int) request('plan_id'))?->name ?? 'All Plans')
        : 'All Plans';
@endphp

<x-super-admin.page title="Subscriptions">
    <div class="figma-sa-subs">
        <div class="figma-sa-subs-top">
            <div class="figma-sa-subs-tabs" role="tablist" aria-label="Subscription status">
                @foreach ($statusTabs as $value => $label)
                    <a
                        href="{{ route('super-admin.subscriptions.index', array_merge(request()->except(['page', 'status']), $value !== '' ? ['status' => $value] : [])) }}"
                        @class(['figma-sa-subs-tab', 'figma-sa-subs-tab--active' => $currentStatus === $value])
                        role="tab"
                        @if ($currentStatus === $value) aria-selected="true" @endif
                    >
                        @if ($currentStatus === $value)
                            <span class="figma-sa-subs-tab-check" aria-hidden="true">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </span>
                        @endif
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="figma-sa-subs-actions">
                <a href="{{ route('super-admin.subscriptions.index', request()->query()) }}" class="figma-sa-subs-export-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 17h8M12 4v9m0 0l-3-3m3 3l3-3M5 19h14a1 1 0 001-1v-4"/></svg>
                    Export
                </a>
                <a href="{{ route('super-admin.plans.index') }}" class="figma-sa-subs-new-plan-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke-width="1.75"/>
                        <path stroke-linecap="round" stroke-width="1.75" d="M12 8v8M8 12h8"/>
                    </svg>
                    New Plan
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('super-admin.subscriptions.index') }}" class="figma-sa-subs-filters" id="subs-filter-form">
            <input type="hidden" name="status" id="filter-subs-status" value="{{ $currentStatus }}">
            <input type="hidden" name="plan_id" id="filter-subs-plan" value="{{ request('plan_id') }}">

            <label class="figma-sa-subs-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search Subscriptions" autocomplete="off">
            </label>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip">
                        <span>{{ $planFilterLabel }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-filter-option" onclick="document.getElementById('filter-subs-plan').value=''; document.getElementById('subs-filter-form').submit();">All Plans</button>
                @foreach ($plans as $plan)
                    <button type="button" class="figma-sa-users-filter-option" onclick="document.getElementById('filter-subs-plan').value='{{ $plan->id }}'; document.getElementById('subs-filter-form').submit();">{{ $plan->name }}</button>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip figma-sa-subs-filter-chip--wide">
                        <span>{{ $statusFilterLabel }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                @foreach ($filterStatuses as $fs)
                    <button type="button"
                        class="figma-sa-users-filter-option"
                        onclick="document.getElementById('filter-subs-status').value='{{ $fs['value'] }}'; document.getElementById('subs-filter-form').submit();">
                        {{ $fs['label'] }}
                    </button>
                @endforeach
            </x-super-admin.dashboard-dropdown>
        </form>

        @foreach ($subscriptions as $subscription)
            <form id="sub-form-{{ $subscription->id }}" method="POST" action="{{ route('super-admin.subscriptions.update', $subscription) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        <div class="figma-sa-subs-panel">
            <div class="figma-sa-subs-table-scroll">
                <table class="figma-sa-subs-table">
                    <thead>
                        <tr>
                            <th class="figma-sa-subs-th-check"><input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select all"></th>
                            <x-sortable-th column="user" label="User" :sort="$sort ?? null" :dir="$dir ?? 'asc'" />
                            <x-sortable-th column="plan" label="Plan" :sort="$sort ?? null" :dir="$dir ?? 'asc'" />
                            <x-sortable-th column="status" label="Status" :sort="$sort ?? null" :dir="$dir ?? 'asc'" />
                            <x-sortable-th column="billing_interval" label="Billing Cycle" :sort="$sort ?? null" :dir="$dir ?? 'asc'" />
                            <x-sortable-th column="next_payment" label="Next Payment" :sort="$sort ?? null" :dir="$dir ?? 'asc'" />
                            <th class="figma-sa-subs-th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            @php
                                $fid = 'sub-form-'.$subscription->id;
                                $plan = $subscription->plan;
                                $price = $plan ? '$'.number_format($plan->price_cents / 100, 0) : null;
                                $intervalShort = $subscription->billing_interval === 'yearly' ? 'yr.' : 'mo.';
                                $billingLine = $plan
                                    ? ucfirst($subscription->billing_interval).' '.$price.' / '.$intervalShort
                                    : '—';
                                $planTier = $plan?->name ?? 'No plan';
                                $planDetail = $plan
                                    ? ucfirst($subscription->billing_interval).' '.$price.' / '.$intervalShort.'.'
                                    : '—';
                                $statusTone = \App\Support\StatusTone::subscription($subscription->status);
                            @endphp
                            <tr class="figma-sa-subs-row">
                                <td class="figma-sa-subs-td-check">
                                    <input form="{{ $fid }}" type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select {{ $subscription->user?->name ?? 'subscription' }}">
                                </td>
                                <td>
                                    <div class="figma-sa-subs-user">
                                        <span class="figma-sa-subs-avatar" aria-hidden="true"></span>
                                        <span class="figma-sa-subs-user-text">
                                            <span class="figma-sa-subs-user-name">{{ $subscription->user?->name ?? 'Deleted user' }}</span>
                                            <span class="figma-sa-subs-user-email">{{ $subscription->user?->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="figma-sa-subs-plan-tier">{{ $planTier }}</span>
                                    <span class="figma-sa-subs-plan-detail">{{ $planDetail }}</span>
                                </td>
                                <td>
                                    <select form="{{ $fid }}" onchange="this.form.submit()" name="status" class="figma-sa-subs-status-pill is-tone-{{ $statusTone }}" aria-label="Change status">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected($subscription->status === $status)>
                                                {{ match ($status) {
                                                    'past_due' => 'Payment Failed',
                                                    default => ucfirst(str_replace('_', ' ', $status)),
                                                } }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><span class="figma-sa-subs-billing">{{ $billingLine }}</span></td>
                                <td><span class="figma-sa-subs-date">{{ $subscription->current_period_ends_at?->format('M d, Y') ?? '—' }}</span></td>
                                <td class="figma-sa-subs-td-action">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" class="figma-sa-subs-kebab" aria-label="Row actions">
                                                <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                            </button>
                                        </x-slot:trigger>
                                        @if ($subscription->user_id)
                                            <a href="{{ route('super-admin.users.show', $subscription->user_id) }}" class="figma-sa-users-action-item">Edit</a>
                                        @endif
                                        <button form="{{ $fid }}" type="submit" class="figma-sa-users-action-item w-full text-left">Save changes</button>
                                        <form method="POST" action="{{ route('super-admin.subscriptions.destroy', $subscription) }}" onsubmit="return confirm('Remove this subscription?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="figma-sa-users-action-item w-full text-left text-rose-300">Remove</button>
                                        </form>
                                    </x-super-admin.dashboard-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="figma-sa-subs-empty">No subscriptions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination">
                <p class="figma-sa-subs-pagination-meta">
                    @if ($subscriptions->total())
                        Showing {{ $subscriptions->firstItem() }}–{{ $subscriptions->lastItem() }} of {{ $subscriptions->total() }}
                    @else
                        Showing 0 of 0
                    @endif
                </p>
                <div class="figma-sa-subs-pagination-controls">
                    <form method="GET" class="figma-sa-subs-perpage-form">
                        @foreach (request()->except(['per_page', 'page']) as $key => $val)
                            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                        @endforeach
                        <label class="sr-only" for="subs-per-page">Rows per page</label>
                        <select id="subs-per-page" name="per_page" class="figma-sa-subs-perpage-select" onchange="this.form.submit()">
                            @foreach ([10, 25, 50] as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($subscriptions->hasPages())
                        <div class="figma-sa-subs-page-btns">
                            @if ($subscriptions->onFirstPage())
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&lt;</span>
                            @else
                                <a href="{{ $subscriptions->previousPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Previous page">&lt;</a>
                            @endif
                            <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--current">{{ $subscriptions->currentPage() }}</span>
                            @if ($subscriptions->hasMorePages())
                                <a href="{{ $subscriptions->nextPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Next page">&gt;</a>
                            @else
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&gt;</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
