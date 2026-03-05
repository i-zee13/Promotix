@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        {{-- Stat cards --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-labelledby="dashboard-stats-heading">
            <h2 id="dashboard-stats-heading" class="sr-only">Dashboard overview</h2>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Active Subscriptions</p>
                        <p class="text-2xl font-bold text-white">{{ count($activeSubscriptions) }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Failed Payments</p>
                        <p class="text-2xl font-bold text-white">{{ count($failedPayments) }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-dark-border bg-dark-card p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent/20 text-accent">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Total Revenue</p>
                        <p class="text-2xl font-bold text-white">$5,840</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-dark-border bg-dark-card p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent/20 text-accent">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Users</p>
                        <p class="text-2xl font-bold text-white">—</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Failed payments --}}
        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden" aria-labelledby="failed-payments-heading">
            <h2 id="failed-payments-heading" class="sr-only">Failed payments</h2>
            <div class="border-b border-dark-border px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Failed Payments</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-border">
                    <thead class="bg-accent">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                        @forelse($failedPayments as $row)
                            <tr class="text-white">
                                <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $row['date'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $row['email'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $row['time'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-400">No failed payments</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Active subscriptions --}}
        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden" aria-labelledby="active-subscriptions-heading">
            <h2 id="active-subscriptions-heading" class="sr-only">Active subscriptions</h2>
            <div class="border-b border-dark-border px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Active Subscriptions</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-border">
                    <thead class="bg-accent">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Price</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                        @forelse($activeSubscriptions as $row)
                            <tr class="text-white">
                                <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $row['name'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $row['email'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $row['date'] }} {{ $row['time'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $row['price'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full bg-green-500/20 px-2.5 py-0.5 text-xs font-medium text-green-400">{{ $row['status'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">No active subscriptions</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
