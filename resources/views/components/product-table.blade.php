@props(['products' => [], 'total' => 0, 'from' => 0, 'to' => 0])
<section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-labelledby="products-table-heading">
    <h2 id="products-table-heading" class="sr-only">SaaS products list</h2>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-left text-sm">
            <thead>
                <tr class="border-b border-dark-border bg-accent">
                    <th scope="col" class="px-4 py-3">
                        <span class="sr-only">Select</span>
                        <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select all">
                    </th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Product</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Plan Rulers</th>
                    <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Status</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-border">
                @foreach ($products as $product)
                    <tr class="transition hover:bg-gray-800/50">
                        <td class="px-4 py-4">
                            <input type="checkbox" class="h-4 w-4 rounded border-dark-border bg-dark text-accent focus:ring-accent" aria-label="Select {{ $product['name'] }}">
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-start gap-3">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-700 text-white" aria-hidden="true">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">{{ $product['name'] }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $product['email1'] }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $product['email2'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-xs text-gray-500">{{ $product['plan_rulers'] }}</p>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium {{ $product['status'] === 'Active' ? 'bg-green-600 text-white' : 'bg-gray-600 text-white' }}">
                                    @if ($product['status'] === 'Active')
                                        <span class="h-1.5 w-1.5 rounded-full bg-white" aria-hidden="true"></span>
                                    @endif
                                    {{ $product['status'] }}
                                </span>
                                {{-- Toggle switch (UI only) --}}
                                <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full {{ $product['status'] === 'Active' ? 'bg-accent' : 'bg-gray-600' }}" role="presentation">
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $product['status'] === 'Active' ? 'translate-x-5' : 'translate-x-1' }}"></span>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <button type="button" class="rounded-lg bg-gray-700 p-2 text-gray-400 hover:bg-dark-border hover:text-white" aria-label="Actions for {{ $product['name'] }}">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-6 flex flex-col gap-4 rounded-xl border border-dark-border bg-accent p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-300">
            Showing <span class="font-medium text-white">{{ $from }}</span>-<span class="font-medium text-white">{{ $to }}</span> of <span class="font-medium text-white">{{ $total }}</span>
        </p>
        <div class="flex items-center gap-3">
            <label for="products-per-page" class="sr-only">Items per page</label>
            <select
                id="products-per-page"
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
