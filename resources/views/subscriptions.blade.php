@extends('layouts.admin')

@section('title', 'Subscriptions')

@section('content')
    <div class="space-y-6">
        {{-- Tabs and filter row --}}
        <section class="flex flex-col gap-4">
            {{-- Tabs (segmented buttons) --}}
            <div class="flex flex-wrap gap-2">
                <a href="#" class="inline-flex items-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    All Subscriptions
                </a>
                <a href="#" class="inline-flex items-center rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-300 transition hover:bg-gray-700 hover:text-white">Active</a>
                <a href="#" class="inline-flex items-center rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-300 transition hover:bg-gray-700 hover:text-white">On Hold</a>
                <a href="#" class="inline-flex items-center rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-300 transition hover:bg-gray-700 hover:text-white">Cancelled</a>
            </div>

            {{-- Search, dropdowns, actions --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                    <label for="subscriptions-search" class="sr-only">Search subscriptions</label>
                    <div class="relative min-w-0 flex-1 sm:max-w-xs">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input
                            id="subscriptions-search"
                            type="search"
                            placeholder="Search Subscriptions"
                            class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        >
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <label for="subscriptions-role" class="sr-only">Role</label>
                        <select
                            id="subscriptions-role"
                            class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        >
                            <option value="">All Roles</option>
                        </select>
                        <label for="subscriptions-status" class="sr-only">Status</label>
                        <select
                            id="subscriptions-status"
                            class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        >
                            <option value="">All Statuses</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-dark-border bg-gray-800 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-gray-700 sm:w-auto"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export
                    </button>
                    <a
                        href="#"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover sm:w-auto"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New Plan
                    </a>
                </div>
            </div>
        </section>

        <x-subscription-table :subscriptions="$subscriptions" :total="$total" :from="$from" :to="$to" :status-classes="$statusClasses" />
    </div>
@endsection
