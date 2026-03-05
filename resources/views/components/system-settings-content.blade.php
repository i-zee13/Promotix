@props(['rows' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'statusClasses' => []])
<section class="space-y-6" aria-labelledby="system-settings-heading">
    <h2 id="system-settings-heading" class="sr-only">System settings</h2>

    {{-- Action cards --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 text-white" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343L12.657 6h2.343m2.343 0L18.657 6M17.657 6v2.343M17.657 6h-2.343"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-bold text-white">Branding</h3>
                    <p class="mt-1 text-sm text-white/80">Customize logo, colors, and appearance</p>
                    <button type="button" class="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800">Customize</button>
                </div>
            </div>
        </div>
        <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 text-white" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-bold text-white">Domain Templates</h3>
                    <p class="mt-1 text-sm text-white/80">Manage domain and tracker templates</p>
                    <button type="button" class="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800">Manage</button>
                </div>
            </div>
        </div>
        <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 text-white" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-bold text-white">Email Templates</h3>
                    <p class="mt-1 text-sm text-white/80">Create and modify email templates</p>
                    <button type="button" class="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800">Edit Templates</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Search Settings --}}
    <div class="flex justify-end">
        <label for="settings-search" class="sr-only">Search settings</label>
        <div class="relative w-full max-w-xs">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input
                id="settings-search"
                type="search"
                placeholder="Search Settings....."
                class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
            >
        </div>
    </div>

    {{-- Settings activity table --}}
    <div class="rounded-xl border border-dark-border bg-dark-card p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead>
                    <tr class="border-b border-dark-border bg-accent">
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">Select</span>
                            <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select all">
                        </th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Type</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">User/IP</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Details</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">IP Address / Location</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Time</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Status</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-border">
                    @foreach ($rows as $index => $row)
                        <tr class="transition hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select row" @checked($index === 0)>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-800 text-white" aria-hidden="true">
                                        @if ($row['type_icon'] === 'check')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @elseif ($row['type_icon'] === 'info')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @elseif ($row['type_icon'] === 'code')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                        @else
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                        @endif
                                    </span>
                                    <span class="text-white">{{ $row['type'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-white">{{ $row['user'] }}</p>
                                <p class="text-xs text-gray-500">{{ $row['tag'] }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-300">{{ $row['details'] }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-white">{{ $row['ip'] }}</p>
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-gray-800 px-2.5 py-1 text-xs text-white">
                                    <span class="h-3 w-3 shrink-0 rounded-sm bg-gray-600" aria-hidden="true"></span>
                                    {{ $row['country'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-400">{{ $row['time'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $statusClasses[$row['status']] ?? 'bg-gray-700 text-white' }}">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" class="rounded-lg bg-gray-700 p-2 text-gray-400 hover:bg-dark-border hover:text-white" aria-label="Actions">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination strip --}}
        <div class="mt-6 flex flex-col gap-4 rounded-xl border border-dark-border bg-accent p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-300">
                Showing <span class="font-medium text-white">{{ $from }}</span>-<span class="font-medium text-white">{{ $to }}</span> of <span class="font-medium text-white">{{ $total }}</span>
            </p>
            <div class="flex items-center gap-3">
                <label for="settings-per-page" class="sr-only">Items per page</label>
                <select
                    id="settings-per-page"
                    class="rounded-xl border border-dark-border bg-dark py-2 pl-3 pr-8 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                >
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <nav class="flex items-center gap-1" aria-label="Pagination">
                    <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-dark-border hover:text-white" aria-label="Previous page">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="rounded-lg bg-accent px-3 py-2 text-sm font-medium text-white" aria-current="page">1</button>
                    <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-dark-border hover:text-white" aria-label="Next page">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>

    {{-- Save Changes button --}}
    <div class="flex justify-end pt-4">
        <button type="button" class="w-full rounded-lg bg-accent px-8 py-3 text-base font-semibold text-white shadow-lg transition hover:bg-accent-hover sm:w-auto">
            Save Changes
        </button>
    </div>
</section>
