@extends('layouts.admin')

@section('title', 'Users & Teams')

@section('content')
    <div class="space-y-6">
        {{-- Filters row --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                <label for="users-search" class="sr-only">Search users</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        id="users-search"
                        type="search"
                        placeholder="Search users"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label for="plan-filter" class="sr-only">Plan filter</label>
                    <select
                        id="plan-filter"
                        class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                        <option value="">Plan Filter</option>
                        <option value="basic">Basic</option>
                        <option value="trial">Trial</option>
                        <option value="pro">Pro</option>
                        <option value="enterprise">Enterprise</option>
                        <option value="custom">Custom</option>
                    </select>
                    <label for="status-filter" class="sr-only">Status filter</label>
                    <select
                        id="status-filter"
                        class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending">Pending</option>
                        <option value="ban">Ban</option>
                    </select>
                    <label for="date-filter" class="sr-only">Date filter</label>
                    <input
                        id="date-filter"
                        type="text"
                        placeholder="Date"
                        value="12/1/2026"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 px-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent sm:w-36"
                    >
                </div>
            </div>
            <a
                href="#"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover sm:w-auto"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Invite Users
            </a>
        </section>

        <x-user-table :users="$users" :total="$total" :from="$from" :to="$to" :plan-classes="$planClasses" :status-classes="$statusClasses" />
    </div>
@endsection
