@extends('layouts.super-admin')

@section('title', 'Payments')
@section('content')
<x-super-admin.page title="Payments" subtitle="Verify bank-transfer receipts and activate plans">
<div class="space-y-[16px]">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-super-admin.kpi label="Pending verification" :value="$stats['pending']" hint="Awaiting super-admin review" />
        <x-super-admin.kpi label="Paid payments" :value="$stats['paid']" />
        <x-super-admin.kpi label="Rejected" :value="$stats['rejected']" />
        <x-super-admin.kpi label="Total collected" :value="format_money_cents($stats['total_paid_cents'])" />
    </div>

    <x-super-admin.card>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input name="search" value="{{ request('search') }}" placeholder="Search invoice or user" class="figma-input min-w-64 flex-1">
            <select name="status" class="figma-select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Filter</button>
        </form>
    </x-super-admin.card>

    <x-super-admin.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="figma-sa-table min-w-[1100px]">
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
                                <p class="font-semibold text-white">{{ $payment->user?->name ?? 'Deleted user' }}</p>
                                <p class="text-xs text-[#8c8787]">{{ $payment->user?->email }}</p>
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
                                        in_array($status, ['paid','success','succeeded','completed']) => 'figma-sa-pill-success',
                                        in_array($status, ['failed','declined','error','rejected']) => 'figma-sa-pill-danger',
                                        in_array($status, ['pending','processing']) => 'figma-sa-pill-warning',
                                        default => 'figma-sa-pill-neutral',
                                    };
                                @endphp
                                <span class="figma-sa-pill {{ $cls }}">{{ ucfirst($payment->status) }}</span>
                                @if ($payment->status === 'rejected' && $payment->rejection_reason)
                                    <p class="mt-1 text-xs text-rose-300/80">{{ $payment->rejection_reason }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="font-mono text-xs text-white">{{ $payment->invoice_number ?? '—' }}</p>
                                @if ($payment->bank_reference)
                                    <p class="text-xs text-[#8c8787]">ref: {{ $payment->bank_reference }}</p>
                                @endif
                            </td>
                            <td>
                                @if ($payment->receipt_path)
                                    <a href="{{ asset('storage/'.$payment->receipt_path) }}" target="_blank" class="text-[#c4b5fd] hover:underline text-sm">
                                        View ({{ $payment->receipt_original_name ?? 'receipt' }})
                                    </a>
                                @else
                                    <span class="text-xs text-[#8c8787]">No file</span>
                                @endif
                            </td>
                            <td class="text-right pr-4">
                                @if ($payment->status === 'pending')
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('super-admin.payments.verify', $payment) }}">
                                            @csrf
                                            <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Verify & activate</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.payments.reject', $payment) }}">
                                            @csrf
                                            <input type="hidden" name="rejection_reason" value="Receipt could not be verified.">
                                            <button type="submit" onclick="return confirm('Reject this receipt? The customer will need to re-submit.')" class="figma-sa-btn figma-sa-btn-danger">Reject</button>
                                        </form>
                                        <form method="POST" action="{{ route('super-admin.payments.mark-failed', $payment) }}">
                                            @csrf
                                            <button type="submit" onclick="return confirm('Mark as failed and start grace period?')" class="figma-sa-btn figma-sa-btn-outline text-sm">Mark failed</button>
                                        </form>
                                    </div>
                                @else
                                    @if ($payment->verified_at)
                                        <span class="text-xs text-[#8c8787]">Verified {{ $payment->verified_at->diffForHumans() }}</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-12 text-center text-[#a9a9a9]">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="figma-sa-pagination px-4 py-3">{{ $payments->links() }}</div>
    </x-super-admin.card>
</div>
</x-super-admin.page>
@endsection
