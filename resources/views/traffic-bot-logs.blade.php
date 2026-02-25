@extends('layouts.admin')

@section('title', 'Traffic & Bot Logs')

@section('content')
    <div class="space-y-6">
        {{-- Date row + Add Tracker --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <label for="traffic-date" class="sr-only">Date</label>
            <div class="relative w-full sm:w-40">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <input
                    id="traffic-date"
                    type="text"
                    value="27/8/2025"
                    class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                >
            </div>
            <a
                href="#"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover sm:w-auto"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Tracker
            </a>
        </section>

        {{-- Stat cards --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-labelledby="traffic-stats-heading">
            <h2 id="traffic-stats-heading" class="sr-only">Traffic & bot summary</h2>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Total Requests</p>
                        <p class="text-2xl font-bold text-white">18,472</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Threat Groups</p>
                        <p class="text-2xl font-bold text-white">32</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Blocked Traffic</p>
                        <p class="text-2xl font-bold text-white">675</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white/90">Allow Lists</p>
                        <p class="text-2xl font-bold text-white">8</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Filter row --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
            <label for="traffic-search" class="sr-only">Search domains</label>
            <div class="relative min-w-0 flex-1 sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input
                    id="traffic-search"
                    type="search"
                    placeholder="Search domains"
                    class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                >
            </div>
            <label for="traffic-trackers" class="sr-only">Trackers</label>
            <select id="traffic-trackers" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                <option value="">All Trackers</option>
            </select>
            <label for="traffic-statuses" class="sr-only">Statuses</label>
            <select id="traffic-statuses" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                <option value="">All Statuses</option>
            </select>
            <label for="traffic-country" class="sr-only">Country / Geo</label>
            <select id="traffic-country" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                <option value="">Country / Geo Filter</option>
            </select>
            <label for="traffic-source" class="sr-only">Source</label>
            <select id="traffic-source" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                <option value="">Source Filter</option>
            </select>
            <label for="traffic-advanced" class="sr-only">Advanced filters</label>
            <select id="traffic-advanced" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                <option value="">Advanced Filters</option>
            </select>
        </section>

        <x-traffic-log-table :rows="$rows" :total="$total" :from="$from" :to="$to" :status-classes="$statusClasses" />
    </div>
@endsection
