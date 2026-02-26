@extends('layouts.admin')

@section('title', 'Analytics')

@section('content')
    <div class="space-y-6">
        {{-- Filter row --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                <label for="analytics-search" class="sr-only">Search domain</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        id="analytics-search"
                        type="search"
                        placeholder="Search Domain"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>
                <label for="analytics-time" class="sr-only">Time range</label>
                <select id="analytics-time" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    <option value="">All Time</option>
                </select>
                <label for="analytics-status" class="sr-only">Status</label>
                <select id="analytics-status" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    <option value="">All Statuses</option>
                </select>
            </div>
            <div class="flex w-full items-center gap-2 sm:w-auto">
                <div class="relative flex-1 sm:flex-initial">
                    <select class="w-full appearance-none rounded-xl border border-transparent bg-accent py-2 pl-4 pr-10 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-white/30 sm:w-auto">
                        <option>Last 30 Days</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-white/90">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
                <button type="button" class="rounded-xl border border-dark-border bg-gray-800 p-2.5 text-gray-400 transition hover:bg-dark-border hover:text-white" aria-label="Export">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </button>
                <button type="button" class="rounded-xl border border-dark-border bg-gray-800 p-2.5 text-gray-400 transition hover:bg-dark-border hover:text-white" aria-label="More options">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>
        </section>

        <x-analytics-dashboard :usage-rows="$usageRows" />
    </div>
@endsection
