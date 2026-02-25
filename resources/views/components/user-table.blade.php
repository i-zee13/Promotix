@props(['users' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'planClasses' => [], 'statusClasses' => []])
<section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-labelledby="users-table-heading">
    <h2 id="users-table-heading" class="sr-only">Users and teams list</h2>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[800px] text-left text-sm">
            <thead>
                <tr class="border-b border-dark-border bg-accent/20">
                    <th scope="col" class="px-4 py-3">
                        <span class="sr-only">Select</span>
                        <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select all">
                    </th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">User</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Role</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Plan</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Status</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Created Date</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-border">
                @foreach ($users as $index => $user)
                    <tr class="transition hover:bg-gray-800/50">
                        <td class="px-4 py-3">
                            <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select {{ $user['name'] }}" @checked($index === 1)>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-dark-border text-sm font-medium text-gray-400" aria-hidden="true">
                                    {{ strtoupper(mb_substr($user['name'], 0, 1)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">{{ $user['name'] }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $user['email'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-300">{{ $user['role'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $planClasses[$user['plan']] ?? 'bg-gray-700 text-white' }}">
                                {{ $user['plan'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $statusClasses[$user['status']] ?? 'bg-gray-700 text-white' }}">
                                {{ $user['status'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ $user['created'] }}</td>
                        <td class="px-4 py-3">
                            <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-dark-border hover:text-white" aria-label="Actions for {{ $user['name'] }}">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-6 flex flex-col gap-4 rounded-xl border border-dark-border bg-dark-card p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-400">
            Showing <span class="font-medium text-white">{{ $from }}</span>-<span class="font-medium text-white">{{ $to }}</span> of <span class="font-medium text-white">{{ $total }}</span>
        </p>
        <div class="flex items-center gap-3">
            <label for="per-page" class="sr-only">Items per page</label>
            <select
                id="per-page"
                class="rounded-xl border border-dark-border bg-dark py-2 pl-3 pr-8 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
            >
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <nav class="flex items-center gap-1" aria-label="Pagination">
                <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-dark-border hover:text-white" aria-label="Previous page">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button" class="rounded-lg bg-accent px-3 py-2 text-sm font-medium text-white" aria-current="page">1</button>
                <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-dark-border hover:text-white" aria-label="Next page">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </nav>
        </div>
    </div>
</section>
