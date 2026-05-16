@extends('layouts.super-admin')

@section('title', 'Subscriptions')
@section('content')
<x-super-admin.page title="Subscriptions">
    <div class="space-y-5">
        <x-super-admin.card>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <select name="status" class="figma-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <button class="figma-sa-btn figma-sa-btn-primary">Filter</button>
            </form>
        </x-super-admin.card>

        @foreach ($subscriptions as $subscription)
            <form id="sub-form-{{ $subscription->id }}" method="POST" action="{{ route('super-admin.subscriptions.update', $subscription) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        <x-super-admin.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="figma-sa-table min-w-[900px]">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Billing</th>
                            <th>Next Payment</th>
                            <th>Status</th>
                            <th>Save</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            @php $fid = 'sub-form-'.$subscription->id; @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-white">{{ $subscription->user?->name ?? 'Deleted user' }}</p>
                                    <p class="text-xs text-[#8c8787]">{{ $subscription->user?->email }}</p>
                                </td>
                                <td>{{ $subscription->plan?->name ?? 'No plan' }}</td>
                                <td>{{ strtoupper($subscription->currency) }} {{ number_format($subscription->amount_cents / 100, 2) }} <span class="text-[#8c8787]">/ {{ $subscription->billing_interval }}</span></td>
                                <td>{{ $subscription->current_period_ends_at?->format('M d, Y') ?? '—' }}</td>
                                <td>
                                    <select form="{{ $fid }}" name="status" class="figma-select">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected($subscription->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <button form="{{ $fid }}" type="submit" class="figma-sa-btn figma-sa-btn-primary !px-3 !py-2 text-xs">Save</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-[#a9a9a9]">No subscriptions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-pagination px-4 py-3">{{ $subscriptions->links() }}</div>
        </x-super-admin.card>
    </div>
</x-super-admin.page>
@endsection
