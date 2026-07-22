@extends('layouts.super-admin')

@section('title', 'Payments')

@section('content')
<x-super-admin.page title="Payments">
    <div class="figma-sa-subs">
        <section class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-4">
            <x-super-admin.kpi label="Successful Payments" :value="number_format($stats['paid'])" />
            <x-super-admin.kpi label="Failed Payments" :value="number_format($stats['failed'])" />
            <x-super-admin.kpi label="Refunds" :value="number_format($stats['refunded'])" />
            <x-super-admin.kpi label="Total Revenue" :value="format_money_cents($stats['total_paid_cents'])" />
        </section>

        <form method="GET" action="{{ route('super-admin.payments.index') }}" class="figma-sa-subs-filters" id="payments-filter-form">
            <input type="hidden" name="status" id="filter-payments-status" value="{{ request('status') }}">

            <label class="figma-sa-subs-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search invoice or user" autocomplete="off">
            </label>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip figma-sa-subs-filter-chip--wide">
                        <span>{{ request('status') ? ucfirst(request('status')) : 'All Statuses' }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                @foreach ($filterStatuses as $fs)
                    <button type="button"
                        class="figma-sa-users-filter-option"
                        onclick="document.getElementById('filter-payments-status').value='{{ $fs['value'] }}'; document.getElementById('payments-filter-form').submit();">
                        {{ $fs['label'] }}
                    </button>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            <div class="figma-sa-subs-actions">
                @if ($stats['pending'] > 0)
                    <span class="figma-sa-subs-status-pill is-tone-expiry" style="min-width:auto;cursor:default;">{{ $stats['pending'] }} awaiting verification</span>
                @endif
                <a href="{{ route('super-admin.payments.index', request()->query()) }}" class="figma-sa-subs-export-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 17h8M12 4v9m0 0l-3-3m3 3l3-3M5 19h14a1 1 0 001-1v-4"/></svg>
                    Export
                </a>
            </div>
        </form>

        @foreach ($payments as $payment)
            <form id="pay-verify-{{ $payment->id }}" method="POST" action="{{ route('super-admin.payments.verify', $payment) }}" class="hidden">@csrf</form>
            <form id="pay-reject-{{ $payment->id }}" method="POST" action="{{ route('super-admin.payments.reject', $payment) }}" class="hidden" onsubmit="return confirm('Reject this receipt? The customer will need to re-submit.');">
                @csrf
                <input type="hidden" name="rejection_reason" value="Receipt could not be verified.">
            </form>
            <form id="pay-failed-{{ $payment->id }}" method="POST" action="{{ route('super-admin.payments.mark-failed', $payment) }}" class="hidden" onsubmit="return confirm('Mark as failed and start grace period?');">@csrf</form>
        @endforeach

        <div class="figma-sa-subs-panel">
            <div class="figma-sa-subs-table-scroll">
                <table class="figma-sa-subs-table">
                    <thead>
                        <tr>
                            <th class="figma-sa-subs-th-check"><input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select all"></th>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th class="figma-sa-subs-th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            @php
                                $status = strtolower($payment->status ?? '');
                                $statusTone = \App\Support\StatusTone::payment($status);
                            @endphp
                            <tr class="figma-sa-subs-row">
                                <td class="figma-sa-subs-td-check">
                                    <input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select row">
                                </td>
                                <td>
                                    <div class="figma-sa-subs-user">
                                        <span class="figma-sa-subs-avatar" aria-hidden="true"></span>
                                        <span class="figma-sa-subs-user-text">
                                            <span class="figma-sa-subs-user-name">{{ $payment->user?->name ?? 'Deleted user' }}</span>
                                            <span class="figma-sa-subs-user-email">{{ $payment->user?->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="figma-sa-subs-plan-tier">{{ $payment->plan?->name ?? $payment->subscription?->plan?->name ?? '—' }}</span>
                                    <span class="figma-sa-subs-plan-detail">{{ format_money_cents($payment->amount_cents, $payment->currency) }}</span>
                                </td>
                                <td>
                                    <x-super-admin.status-pill :tone="$statusTone" :label="ucfirst($payment->status)" />
                                    @if ($status === 'rejected' && $payment->rejection_reason)
                                        <span class="figma-sa-subs-plan-detail">{{ $payment->rejection_reason }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="figma-sa-subs-plan-tier">{{ $payment->invoice_number ?? '—' }}</span>
                                    <span class="figma-sa-subs-plan-detail">{{ $payment->bank_reference ? 'Bank •••• '.substr($payment->bank_reference, -4) : 'Bank Transfer' }}</span>
                                </td>
                                <td><span class="figma-sa-subs-date">{{ optional($payment->created_at)->format('M d, Y') }}</span></td>
                                <td class="figma-sa-subs-td-action">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" class="figma-sa-subs-kebab" aria-label="Row actions">
                                                <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                            </button>
                                        </x-slot:trigger>
                                        @if ($payment->receipt_path)
                                            <a href="{{ route('super-admin.payments.receipt', $payment) }}" class="figma-sa-users-action-item">Download receipt</a>
                                        @endif
                                        @if ($status === 'pending')
                                            <button form="pay-verify-{{ $payment->id }}" type="submit" class="figma-sa-users-action-item w-full text-left">Verify &amp; activate</button>
                                            <button form="pay-reject-{{ $payment->id }}" type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--danger w-full text-left">Reject</button>
                                        @endif
                                        @if (in_array($status, ['pending', 'paid'], true))
                                            <button form="pay-failed-{{ $payment->id }}" type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--warn w-full text-left">Mark failed</button>
                                        @endif
                                        @if ($payment->verified_at)
                                            <span class="figma-sa-users-action-item" style="cursor:default;opacity:.6;">Verified {{ $payment->verified_at->diffForHumans() }}</span>
                                        @endif
                                    </x-super-admin.dashboard-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="figma-sa-subs-empty">No payments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination">
                <p class="figma-sa-subs-pagination-meta">
                    @if ($payments->total())
                        Showing {{ $payments->firstItem() }}–{{ $payments->lastItem() }} of {{ $payments->total() }}
                    @else
                        Showing 0 of 0
                    @endif
                </p>
                <div class="figma-sa-subs-pagination-controls">
                    <form method="GET" class="figma-sa-subs-perpage-form">
                        @foreach (request()->except(['per_page', 'page']) as $key => $val)
                            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                        @endforeach
                        <label class="sr-only" for="payments-per-page">Rows per page</label>
                        <select id="payments-per-page" name="per_page" class="figma-sa-subs-perpage-select" onchange="this.form.submit()">
                            @foreach ([10, 25, 50] as $n)
                                <option value="{{ $n }}" @selected(request()->integer('per_page', 10) === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($payments->hasPages())
                        <div class="figma-sa-subs-page-btns">
                            @if ($payments->onFirstPage())
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&lt;</span>
                            @else
                                <a href="{{ $payments->previousPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Previous page">&lt;</a>
                            @endif
                            <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--current">{{ $payments->currentPage() }}</span>
                            @if ($payments->hasMorePages())
                                <a href="{{ $payments->nextPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Next page">&gt;</a>
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
