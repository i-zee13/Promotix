@props(['rows' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'statusClasses' => []])
<section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-labelledby="security-log-table-heading">
    <h2 id="security-log-table-heading" class="sr-only">Security logs</h2>
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
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-border">
                @foreach ($rows as $index => $row)
                    <tr class="transition hover:bg-gray-800/50">
                        <td class="px-4 py-3">
                            <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select log entry" @checked($index === 1)>
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
                                    @elseif ($row['type_icon'] === 'folder')
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                    @else
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
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
            <label for="security-per-page" class="sr-only">Items per page</label>
            <select
                id="security-per-page"
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
</section>
