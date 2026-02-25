@extends('layouts.admin')

@section('title', 'Payments')

@section('content')
    <div class="space-y-6">
        {{-- Filter row --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                <label for="payments-search" class="sr-only">Search subscriptions</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        id="payments-search"
                        type="search"
                        placeholder="Search Subscriptions"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>
                <label for="payments-date" class="sr-only">Date range</label>
                <div class="relative w-full sm:w-40">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </span>
                    <input
                        id="payments-date"
                        type="text"
                        placeholder="Date Range"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>
                <label for="payments-status" class="sr-only">Status</label>
                <select
                    id="payments-status"
                    class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                >
                    <option value="">All Statuses</option>
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-gray-700 sm:w-auto"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
                <button
                    type="button"
                    class="inline-flex w-full flex-col items-center rounded-xl bg-gray-800 px-4 py-2.5 text-left sm:w-auto"
                >
                    <span class="text-xs text-gray-500">Stripe</span>
                    <span class="text-sm font-medium text-white">Synced 5 mins ago</span>
                </button>
            </div>
        </section>

        {{-- Summary stat cards --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-labelledby="payments-stats-heading">
            <h2 id="payments-stats-heading" class="sr-only">Payment summary</h2>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Successful Payments</p>
                        <p class="text-2xl font-bold text-white">116</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Failed Payments</p>
                        <p class="text-2xl font-bold text-white">16</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Refunds</p>
                        <p class="text-2xl font-bold text-white">6</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Total Revenue</p>
                        <p class="text-2xl font-bold text-white">$5,840</p>
                    </div>
                </div>
            </div>
        </section>

        <x-payment-table :payments="$payments" :total="$total" :from="$from" :to="$to" :status-classes="$statusClasses" />
    </div>
@endsection
