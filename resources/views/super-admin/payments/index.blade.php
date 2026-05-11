@extends('layouts.super-admin')

@section('title', 'Payments')
@section('subtitle', 'Invoices, successful payments, and failed payment monitoring')

@section('content')
    <div class="space-y-5">
        <x-ui.card>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <select name="status" class="brand-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="brand-btn-primary">Filter</button>
            </form>
        </x-ui.card>

        <x-ui.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[920px]">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Method</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>
                                    <p class="font-semibold text-night-100">{{ $payment->user?->name ?? 'Deleted user' }}</p>
                                    <p class="text-xs text-night-400">{{ $payment->user?->email }}</p>
                                </td>
                                <td>{{ $payment->subscription?->plan?->name ?? '—' }}</td>
                                <td class="font-semibold">{{ strtoupper($payment->currency) }} {{ number_format($payment->amount_cents / 100, 2) }}</td>
                                <td>{{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at?->format('M d, Y') }}</td>
                                <td>
                                    @php
                                        $status = strtolower($payment->status ?? '');
                                        $cls = match (true) {
                                            in_array($status, ['paid','success','succeeded','completed']) => 'brand-pill-success',
                                            in_array($status, ['failed','declined','error']) => 'brand-pill-danger',
                                            in_array($status, ['pending','processing']) => 'brand-pill-warning',
                                            default => 'brand-pill-neutral',
                                        };
                                    @endphp
                                    <span class="brand-pill {{ $cls }}">{{ ucfirst($payment->status) }}</span>
                                </td>
                                <td>{{ $payment->payment_method ?? '—' }} <span class="text-night-400">{{ $payment->masked_payment }}</span></td>
                                <td>{{ $payment->invoice_number ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-night-300">No payments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-night-700/60 px-4 py-3">{{ $payments->links() }}</div>
        </x-ui.card>
    </div>
@endsection
