@props(['rows' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'statusClasses' => [], 'priorityClasses' => []])
<section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-labelledby="support-ticket-table-heading">
    <h2 id="support-ticket-table-heading" class="sr-only">Support tickets</h2>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead>
                <tr class="border-b border-dark-border bg-accent">
                    <th scope="col" class="px-4 py-3">
                        <span class="sr-only">Select</span>
                        <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select all">
                    </th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">ID</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Subject</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Status</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Priority</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Agent</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Last Update</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">SLA</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-border">
                @foreach ($rows as $index => $row)
                    <tr class="transition hover:bg-gray-800/50">
                        <td class="px-4 py-3">
                            <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select ticket #{{ $row['id'] }}" @checked($index === 1)>
                        </td>
                        <td class="px-4 py-3 font-medium text-white">#{{ $row['id'] }}</td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-white">{{ $row['name'] }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $row['email'] }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $statusClasses[$row['status']] ?? 'bg-gray-700 text-white' }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $priorityClasses[$row['priority']] ?? 'bg-gray-700 text-white' }}">
                                {{ $row['priority'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-dark-border text-gray-400" aria-hidden="true">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </span>
                                <span class="text-white">{{ $row['agent'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ $row['last_update'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-md bg-gray-800 px-2 py-1 text-xs text-white">
                                {{ $row['sla'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <button type="button" class="rounded-lg bg-gray-700 p-2 text-gray-400 hover:bg-dark-border hover:text-white" aria-label="Actions for ticket #{{ $row['id'] }}">
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
            <label for="support-per-page" class="sr-only">Items per page</label>
            <select
                id="support-per-page"
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
