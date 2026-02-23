@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        {{-- Stats grid --}}
        <section aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">Key metrics</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-stat-card title="Total users" value="12,450" />
                <x-stat-card title="Active Subscriptions" value="3,280" />
                <x-stat-card title="Monthly Revenue" value="$24,750" />
                <x-stat-card title="Failed Payments" value="15" />
                <x-stat-card title="Active Domains" value="42" />
                <x-stat-card title="Total Events Today" value="675" />
            </div>
        </section>

        {{-- Top row: charts + failed payments --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-[20px] border border-dark-border bg-dark-card p-6 lg:col-span-2" aria-labelledby="revenue-chart-heading">
                <h2 id="revenue-chart-heading" class="flex items-center gap-2 text-lg font-semibold text-white">
                    <span class="flex h-2 w-2 rounded-full bg-accent" aria-hidden="true"></span>
                    Revenue trend
                </h2>
                <div class="mt-4 h-64">
                    <canvas id="revenueChart" role="img" aria-label="Revenue trend line chart"></canvas>
                </div>
            </section>

            <section class="rounded-[20px] border border-dark-border bg-dark-card p-6" aria-labelledby="failed-payments-heading">
                <div class="flex items-center justify-between">
                    <h2 id="failed-payments-heading" class="text-lg font-semibold text-white">Failed payments</h2>
                    <a href="#" class="text-sm font-medium text-accent hover:text-accent-light">Load More</a>
                </div>
                <ul class="mt-4 space-y-2" role="list">
                    @foreach ([['date' => 'Dec 12, 2025', 'email' => 'example@gmail.com', 'time' => '12:20 Pm'], ['date' => 'Dec 11, 2025', 'email' => 'user@example.com', 'time' => '09:15 Am'], ['date' => 'Dec 10, 2025', 'email' => 'contact@test.com', 'time' => '14:45 Pm']] as $item)
                        <li>
                            <a href="#" class="flex items-center gap-3 rounded-[20px] bg-accent/20 px-4 py-3 text-sm transition hover:bg-accent/30">
                                <span class="text-accent" aria-hidden="true">&rsaquo;</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-white">{{ $item['date'] }}</p>
                                    <p class="truncate text-gray-400">{{ $item['email'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $item['time'] }}</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        {{-- User growth chart --}}
        <section class="rounded-[20px] border border-dark-border bg-dark-card p-6" aria-labelledby="growth-chart-heading">
            <h2 id="growth-chart-heading" class="flex items-center gap-2 text-lg font-semibold text-white">
                <span class="flex h-2 w-2 rounded-full bg-accent" aria-hidden="true"></span>
                User Growth
            </h2>
            <div class="mt-4 h-64">
                <canvas id="userGrowthChart" role="img" aria-label="User growth bar chart"></canvas>
            </div>
        </section>

        {{-- Active subscriptions table --}}
        <section class="rounded-[20px] border border-dark-border bg-dark-card p-6" aria-labelledby="subscriptions-heading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 id="subscriptions-heading" class="text-lg font-semibold text-white">Active subscriptions</h2>
                <div class="flex items-center gap-2">
                    <label for="table-search" class="sr-only">Search</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input
                            id="table-search"
                            type="search"
                            placeholder="Search"
                            class="w-full min-w-[180px] rounded-[20px] border border-dark-border bg-dark py-2 pl-9 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent sm:w-auto"
                        >
                    </div>
                    <button type="button" class="flex items-center gap-2 rounded-[20px] border border-dark-border bg-dark px-4 py-2 text-sm font-medium text-gray-300 transition hover:bg-dark-border hover:text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filters
                    </button>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto rounded-[20px] border border-dark-border">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead class="bg-dark border-b border-dark-border">
                        <tr>
                            <th scope="col" class="px-4 py-3">
                                <span class="sr-only">Active</span>
                                <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select all">
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-400">Mail</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-400">Date</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-400">Time</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-400">Price</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-400">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                        <tr class="transition hover:bg-dark/50">
                            <td class="px-4 py-3">
                                <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select row">
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-white">Sarah Collins</p>
                                <p class="text-gray-500">Example@gmail.com</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-lg bg-accent/20 px-2 py-1 text-accent-light">8/1/2026</span>
                            </td>
                            <td class="px-4 py-3 text-gray-300">12:30 pm</td>
                            <td class="px-4 py-3 font-medium text-white">$200</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-green-500/20 px-2 py-1 text-xs font-medium text-green-400">Active</span>
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" class="rounded p-1 text-gray-500 hover:bg-dark-border hover:text-white" aria-label="Row actions">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </button>
                            </td>
                        </tr>
                        <tr class="transition hover:bg-dark/50">
                            <td class="px-4 py-3">
                                <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select row">
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-white">John Doe</p>
                                <p class="text-gray-500">john@example.com</p>
                            </td>
                            <td class="px-4 py-3 text-gray-300">7/28/2026</td>
                            <td class="px-4 py-3 text-gray-300">09:15 am</td>
                            <td class="px-4 py-3 font-medium text-white">$150</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-green-500/20 px-2 py-1 text-xs font-medium text-green-400">Active</span>
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" class="rounded p-1 text-gray-500 hover:bg-dark-border hover:text-white" aria-label="Row actions">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
