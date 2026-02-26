@extends('layouts.admin')

@section('title', 'Integrations')

@section('content')
    <div class="space-y-6">
        {{-- Filter row + New Automation button --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                <label for="integrations-search" class="sr-only">Search domains</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        id="integrations-search"
                        type="search"
                        placeholder="Search domains"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>
                <label for="integrations-trackers" class="sr-only">Trackers</label>
                <select id="integrations-trackers" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    <option value="">All Trackers</option>
                </select>
                <label for="integrations-statuses" class="sr-only">Statuses</label>
                <select id="integrations-statuses" class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                    <option value="">All Statuses</option>
                </select>
            </div>
            <a href="#" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover sm:w-auto">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Automation
            </a>
        </section>

        <x-integration-grid :total="$total" :from="$from" :to="$to" />
    </div>
@endsection
