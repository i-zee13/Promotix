@props(['usageRows' => []])
<section class="space-y-6" aria-labelledby="analytics-dashboard-heading">
    <h2 id="analytics-dashboard-heading" class="sr-only">Analytics dashboard</h2>

    <div class="grid grid-cols-12 gap-6">
        {{-- Top row: MRR Growth (large) --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-white">MRR Growth</h3>
                    <p class="text-3xl font-bold text-white">$24,540</p>
                    <p class="text-sm text-white/80">Last 30 Days</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-medium text-white">+6.2%</span>
                </div>
            </div>
            <div class="mt-4 h-56">
                <canvas id="mrrChart" role="img" aria-label="MRR growth line chart"></canvas>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="button" class="rounded-lg bg-gray-900/60 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-900/80">MRR</button>
                <button type="button" class="rounded-lg bg-gray-900/60 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-900/80">ARR</button>
            </div>
        </div>

        {{-- Right stack: Churn Rate, LTV, Active Subscriptions --}}
        <div class="col-span-12 grid grid-rows-3 gap-6 lg:col-span-4">
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white">
                <h3 class="text-sm font-bold text-white">Churn Rate</h3>
                <p class="mt-1 text-2xl font-bold text-white">3.4%</p>
                <div class="mt-2 flex gap-2">
                    <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">• 1.40%</span>
                    <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">+1.0%</span>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white">
                <div class="flex items-start justify-between">
                    <h3 class="text-sm font-bold text-white">LTV</h3>
                    <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-medium text-white">+5.9%</span>
                </div>
                <p class="mt-1 text-2xl font-bold text-white">$285</p>
                <div class="mt-2 flex gap-2">
                    <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">+3.28%</span>
                    <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">+1.0%</span>
                </div>
            </div>
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white">
                <div class="flex items-start justify-between">
                    <h3 class="text-sm font-bold text-white">Active Subscriptions</h3>
                    <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-medium text-white">+5.8%</span>
                </div>
                <p class="mt-1 text-2xl font-bold text-white">$24,540</p>
                <div class="mt-2 flex gap-2">
                    <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">+3.9%</span>
                    <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">+13.0%</span>
                </div>
                <div class="mt-3 h-16">
                    <canvas id="activeSubChart" role="img" aria-label="Active subscriptions bar chart"></canvas>
                </div>
                <div class="mt-3 flex gap-2">
                    <span class="rounded-lg bg-gray-900/60 px-2 py-1 text-xs text-white">Last 30 days</span>
                    <span class="rounded-lg bg-gray-900/60 px-2 py-1 text-xs text-white/70">Trends</span>
                    <span class="rounded-lg bg-gray-900/60 px-2 py-1 text-xs text-white/70">Present Time</span>
                </div>
            </div>
        </div>

        {{-- Bottom row: Customer Churn --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-white">
                Customer Churn
                <span class="text-white/70" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </h3>
            <p class="mt-1 text-2xl font-bold text-white">3.4%</p>
            <div class="mt-2 flex gap-2">
                <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">• 1.0%</span>
                <span class="rounded-full bg-gray-900 px-3 py-1 text-xs text-white">• 1.0%</span>
            </div>
            <div class="mt-4 flex justify-center">
                <div class="h-40 w-40">
                    <canvas id="churnDonut" role="img" aria-label="Customer churn donut chart"></canvas>
                </div>
            </div>
            <p class="mt-2 text-center text-sm text-white/90">Churned Customers: 24 Customer</p>
            <p class="mt-1 flex items-center justify-center gap-2 text-sm">
                <span class="text-red-300">Contraction MRR: -$610 / mo.</span>
                <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs text-white">+1.0%</span>
            </p>
        </div>

        {{-- LTV & Conversion Rate --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-white">
                LTV &amp; Conversion Rate
                <span class="rounded-full bg-blue-500/80 px-2 py-0.5 text-xs font-medium text-white">6.1%</span>
            </h3>
            <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:justify-around">
                <div class="h-32 w-32 shrink-0">
                    <canvas id="conversionDonut" role="img" aria-label="Conversion rate donut chart"></canvas>
                </div>
                <div class="text-sm">
                    <p class="font-medium text-white">Customers: $285</p>
                    <p class="mt-1 text-white/90">Trail Conversions 22.4%</p>
                    <p class="mt-2 text-white/80">New Trails: 5.2 <span class="ml-1 rounded bg-gray-900 px-2 py-0.5 text-xs">New</span></p>
                </div>
            </div>
        </div>

        {{-- Usage Analytics table --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-white">
                Usage Analytics
                <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium text-white">Synced just new</span>
            </h3>
            <div class="mt-4 max-h-48 overflow-y-auto rounded-lg border border-white/10">
                <table class="w-full min-w-[200px] text-left text-sm">
                    <thead class="sticky top-0 bg-accent">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-xs font-medium uppercase tracking-wider text-white">Date</th>
                            <th scope="col" class="px-3 py-2 text-xs font-medium uppercase tracking-wider text-white">Active Users</th>
                            <th scope="col" class="px-3 py-2 text-xs font-medium uppercase tracking-wider text-white">Events Logged</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @foreach ($usageRows as $row)
                            <tr class="bg-white/5">
                                <td class="px-3 py-2 text-white">{{ $row['date'] }}</td>
                                <td class="px-3 py-2 text-white">{{ $row['active_users'] }}</td>
                                <td class="px-3 py-2 text-white">{{ $row['events'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination strip --}}
    <div class="flex flex-col gap-4 rounded-xl border border-dark-border bg-accent p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-300">
            Showing <span class="font-medium text-white">1</span>-<span class="font-medium text-white">5</span> of <span class="font-medium text-white">213</span>
        </p>
        <div class="flex items-center gap-2">
            <button type="button" class="rounded-xl border border-dark-border bg-dark p-2 text-gray-400 hover:bg-dark-border hover:text-white" aria-label="More pages">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
    </div>
</section>
