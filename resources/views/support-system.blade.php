@extends('layouts.admin')

@section('title', 'Support System')

@section('content')
    <div class="space-y-6">
        {{-- Filter row + buttons --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                <label for="support-search" class="sr-only">Search tickets</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        id="support-search"
                        type="search"
                        placeholder="Search Tickets"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>
                <label for="support-priority" class="sr-only">Priority</label>
                <select id="support-priority" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    <option value="">All Priorities</option>
                </select>
                <label for="support-status" class="sr-only">Status</label>
                <select id="support-status" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    <option value="">All Statuses</option>
                </select>
            </div>
            <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                <a href="#" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover sm:flex-initial">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Ticket
                </a>
                <button type="button" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-dark-border bg-gray-800 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-gray-700 sm:flex-initial">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
            </div>
        </section>

        {{-- Stat cards --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-labelledby="support-stats-heading">
            <h2 id="support-stats-heading" class="sr-only">Support summary</h2>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div>
                        <p class="text-2xl font-bold text-white">175</p>
                        <p class="text-sm font-medium text-white/90">Total Tickets</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-2xl font-bold text-white">42</p>
                        <p class="text-sm font-medium text-white/90">Open</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-2xl font-bold text-white">8</p>
                        <p class="text-sm font-medium text-white/90">Assigned</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <p class="text-2xl font-bold text-white">5</p>
                        <p class="text-sm font-medium text-white/90">SLA Breaches</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-2xl font-bold text-white">12</p>
                        <p class="text-sm font-medium text-white/90">Overdue</p>
                    </div>
                </div>
            </div>
        </section>

        <x-support-ticket-table :rows="$rows" :total="$total" :from="$from" :to="$to" :status-classes="$statusClasses" :priority-classes="$priorityClasses" />
    </div>
@endsection
