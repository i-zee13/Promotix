@extends('layouts.super-admin')

@section('title', 'Payments')
@section('subtitle', 'Verify bank-transfer receipts, monitor invoices, and activate plans')

@section('content')
<div class="space-y-5">
    @if (session('status'))
        <div class="rounded-xl2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl2 border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-200">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.kpi-card label="Pending verification" :value="$stats['pending']" hint="Awaiting super-admin review" />
        <x-ui.kpi-card label="Paid payments" :value="$stats['paid']" />
        <x-ui.kpi-card label="Rejected" :value="$stats['rejected']" />
        <x-ui.kpi-card label="Total collected" :value="format_money_cents($stats['total_paid_cents'])" />
    </div>

    <x-ui.card>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input name="search" value="{{ request('search') }}" placeholder="Search invoice or user" class="brand-input min-w-64 flex-1">
            <select name="status" class="brand-select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button type="submit" class="brand-btn-primary">Filter</button>
        </form>
    </x-ui.card>

    <x-ui.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="brand-table min-w-[1100px]">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Receipt</th>
                        <th class="text-right pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>
                                <p class="font-semibold text-night-100">{{ $payment->user?->name ?? 'Deleted user' }}</p>
                                <p class="text-xs text-night-400">{{ $payment->user?->email }}</p>
                            </td>
                            <td>
                                {{ $payment->plan?->name ?? $payment->subscription?->plan?->name ?? '—' }}
                            </td>
                            <td class="font-semibold">{{ format_money_cents($payment->amount_cents, $payment->currency) }}</td>
                            <td>{{ optional($payment->created_at)->format('M d, Y H:i') }}</td>
                            <td>
                                @php
                                    $status = strtolower($payment->status ?? '');
                                    $cls = match (true) {
                                        in_array($status, ['paid','success','succeeded','completed']) => 'brand-pill-success',
                                        in_array($status, ['failed','declined','error','rejected']) => 'brand-pill-danger',
                                        in_array($status, ['pending','processing']) => 'brand-pill-warning',
                                        default => 'brand-pill-neutral',
                                    };
                                @endphp
                                <span class="brand-pill {{ $cls }}">{{ ucfirst($payment->status) }}</span>
                                @if ($payment->status === 'rejected' && $payment->rejection_reason)
                                    <p class="mt-1 text-xs text-rose-300/80">{{ $payment->rejection_reason }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="font-mono text-xs text-night-100">{{ $payment->invoice_number ?? '—' }}</p>
                                @if ($payment->bank_reference)
                                    <p class="text-xs text-night-400">ref: {{ $payment->bank_reference }}</p>
                                @endif
                            </td>
                            <td>
                                @if ($payment->receipt_path)
                                    <a href="{{ asset('storage/'.$payment->receipt_path) }}" target="_blank" class="text-brand-300 hover:underline text-sm">
                                        View ({{ $payment->receipt_original_name ?? 'receipt' }})
                                    </a>
                                @else
                                    <span class="text-xs text-night-400">No file</span>
                                @endif
                            </td>
                            <td class="text-right pr-4">
                                @if ($payment->status === 'pending')
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('super-admin.payments.verify', $payment) }}">
                                            @csrf
                                            <button type="submit" class="brand-btn-primary">Verify & activate</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.payments.reject', $payment) }}">
                                            @csrf
                                            <input type="hidden" name="rejection_reason" value="Receipt could not be verified.">
                                            <button type="submit" onclick="return confirm('Reject this receipt? The customer will need to re-submit.')" class="brand-btn-danger">Reject</button>
                                        </form>
                                    </div>
                                @else
                                    @if ($payment->verified_at)
                                        <span class="text-xs text-night-400">Verified {{ $payment->verified_at->diffForHumans() }}</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-12 text-center text-night-300">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-night-700/60 px-4 py-3">{{ $payments->links() }}</div>
    </x-ui.card>
</div>
@endsection
