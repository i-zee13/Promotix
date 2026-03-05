@props(['items' => [], 'total' => 0, 'from' => 0, 'to' => 0])
<section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-labelledby="automation-list-heading">
    <h2 id="automation-list-heading" class="sr-only">Automation list</h2>
    <div class="space-y-4">
        @foreach ($items as $item)
            <div class="grid grid-cols-12 gap-4">
                {{-- Main left card --}}
                <div class="col-span-12 rounded-lg bg-gray-200 p-5 text-gray-900 lg:col-span-7">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-accent text-white">
                                @if ($item['icon'] === 'exclamation')
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @elseif ($item['icon'] === 'trash')
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4m1 4h.01M12 4h.01M16 4h.01M8 4h.01"/></svg>
                                @elseif ($item['icon'] === 'google')
                                    <span class="text-lg font-bold">A</span>
                                @else
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-900">{{ $item['title'] }}</h3>
                                <p class="text-sm text-gray-600">{{ $item['description'] }}</p>
                                <p class="mt-1 flex items-center gap-1.5 text-sm text-gray-600">
                                    <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-gray-500" aria-hidden="true"></span>
                                    {{ $item['schedule'] }}
                                </p>
                                <span class="mt-2 inline-block rounded-md px-2 py-0.5 text-xs font-medium {{ $item['queue_healthy'] ? 'bg-purple-600 text-white' : 'bg-gray-600 text-white' }}">
                                    {{ $item['queue_badge'] }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-3 sm:flex-col sm:items-end">
                            <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition {{ $item['status'] === 'Active' ? 'bg-accent' : 'bg-gray-400' }}" role="presentation" aria-hidden="true">
                                <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow transition {{ $item['status'] === 'Active' ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </span>
                            <span class="rounded-md px-2 py-1 text-xs font-medium {{ $item['status'] === 'Active' ? 'bg-purple-600 text-white' : 'bg-yellow-500 text-black' }}">
                                {{ $item['status'] }}
                            </span>
                        </div>
                    </div>
                </div>
                {{-- Middle card --}}
                <div class="col-span-12 rounded-lg bg-gray-200 p-4 text-gray-900 lg:col-span-3">
                    @if ($item['middle_title'])
                        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-600">{{ $item['middle_title'] }}</p>
                    @endif
                    @if ($item['middle_bars'])
                        <div class="space-y-2">
                            <div class="h-2 w-full rounded bg-gray-300"></div>
                            <div class="h-2 w-4/5 rounded bg-gray-300"></div>
                            <div class="h-2 w-3/4 rounded bg-gray-300"></div>
                        </div>
                    @endif
                    @if ($item['middle_badges'])
                        <div class="flex flex-wrap gap-2">
                            @foreach ($item['middle_badges'] as $badge)
                                @if (str_contains($badge, 'Retrying'))
                                    <span class="rounded-md bg-red-100 px-2 py-1 text-xs font-medium text-red-700">{{ $badge }}</span>
                                @elseif (str_contains($badge, 'Phase'))
                                    <span class="rounded-md bg-purple-600 px-2 py-1 text-xs font-medium text-white">{{ $badge }}</span>
                                @else
                                    <span class="rounded-md bg-gray-600 px-2 py-1 text-xs font-medium text-white">{{ $badge }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                {{-- Right card --}}
                <div class="col-span-12 rounded-lg bg-gray-200 p-4 text-gray-900 lg:col-span-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            @if ($item['right_title'])
                                <p class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-600">{{ $item['right_title'] }}</p>
                            @endif
                            @if ($item['right_pills'])
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="rounded-md bg-purple-600 px-2 py-1 text-xs font-medium text-white">Active</span>
                                    <span class="rounded-md bg-purple-600 px-2 py-1 text-xs font-medium text-white">Active</span>
                                </div>
                                <div class="mt-2 space-y-2">
                                    <div class="h-2 w-full rounded bg-gray-300"></div>
                                    <div class="h-2 w-2/3 rounded bg-gray-300"></div>
                                </div>
                            @endif
                            @if ($item['right_grid'])
                                <div class="grid grid-cols-2 gap-1">
                                    <span class="h-8 rounded bg-gray-300"></span>
                                    <span class="h-8 rounded bg-gray-300"></span>
                                    <span class="h-8 rounded bg-gray-300"></span>
                                    <span class="h-8 rounded bg-gray-300"></span>
                                </div>
                            @endif
                        </div>
                        <button type="button" class="rounded-lg bg-gray-700 p-1.5 text-white hover:bg-gray-600" aria-label="Actions">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pagination strip --}}
    <div class="mt-6 flex flex-col gap-4 rounded-xl border border-dark-border bg-accent p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-300">
            Showing <span class="font-medium text-white">{{ $from }}</span>-<span class="font-medium text-white">{{ $to }}</span> of <span class="font-medium text-white">{{ $total }}</span>
        </p>
        <div class="flex items-center gap-3">
            <label for="automation-per-page" class="sr-only">Items per page</label>
            <select
                id="automation-per-page"
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
