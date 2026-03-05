@props(['total' => 56, 'from' => 1, 'to' => 10])
<section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-labelledby="integration-grid-heading">
    <h2 id="integration-grid-heading" class="sr-only">Integrations</h2>
    <div class="grid grid-cols-12 gap-6">
        {{-- Row 1: Stripe --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                    <span class="text-lg font-bold">S</span>
                </span>
                <div class="min-w-0">
                    <h3 class="font-bold text-white">Stripe</h3>
                    <p class="text-sm text-white/90">suspend users with unpaid</p>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-900 px-3 py-1 text-xs text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Connected
                </span>
            </div>
            <p class="mt-3 text-xs text-white/80">API keys last updated: 5 days ago</p>
            <div class="mt-4 space-y-2">
                <div class="flex gap-2">
                    <input type="text" value="423423sdfsd2wrwew-adsdsfdss-fsfsdf js" readonly class="min-w-0 flex-1 rounded-lg border-0 bg-gray-900/50 px-3 py-2 text-sm text-white/90">
                    <button type="button" class="shrink-0 rounded-lg bg-gray-900 p-2 text-white hover:opacity-90" aria-label="Copy">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>
                <div class="flex gap-2">
                    <input type="text" value="sk_live_••••••••••••••••" readonly class="min-w-0 flex-1 rounded-lg border-0 bg-gray-900/50 px-3 py-2 text-sm text-white/90">
                    <button type="button" class="shrink-0 rounded-lg bg-gray-900 p-2 text-white hover:opacity-90" aria-label="Copy">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Manage Keys
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Rotate Keys
                </button>
            </div>
        </div>

        {{-- Row 1: Google Cloud --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="font-bold text-white">Google Cloud</h3>
                    <p class="text-sm text-white/90">API Keys updated: 5 days ago</p>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-900 px-3 py-1 text-xs text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Connected
                </span>
            </div>
            <p class="mt-3 text-xs text-white/80">API keys last updated: 5 days ago</p>
            <div class="mt-4 space-y-2">
                <div class="relative">
                    <select class="w-full appearance-none rounded-lg border-0 bg-gray-900/50 py-2 pl-3 pr-10 text-sm text-white focus:ring-2 focus:ring-white/30">
                        <option>Google Cloud Map API Key</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-white/70">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
                <div class="relative">
                    <select class="w-full appearance-none rounded-lg border-0 bg-gray-900/50 py-2 pl-3 pr-10 text-sm text-white focus:ring-2 focus:ring-white/30">
                        <option>Google Cloud Analytics Key</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-white/70">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="mt-4">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Manage Keys
                </button>
            </div>
        </div>

        {{-- Row 1: SMTP --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="font-bold text-white">SMTP</h3>
                    <p class="text-sm text-white/90">Connected</p>
                </div>
            </div>
            <hr class="my-4 border-white/20">
            <p class="text-xs font-medium uppercase tracking-wider text-white/70">Usage</p>
            <div class="mt-3">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Setup SMTP
                </button>
            </div>
        </div>

        {{-- Row 2: Webhooks 1 --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="font-bold text-white">Webhooks</h3>
                    <p class="text-sm text-white/90">Not with Google Ads Account</p>
                </div>
            </div>
            <p class="mt-2 text-xs text-white/70">Integrating extensions</p>
            <div class="mt-4">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    Setup Keys
                </button>
            </div>
        </div>

        {{-- Row 2: Webhooks 2 --}}
        <div class="col-span-12 rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white lg:col-span-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="font-bold text-white">Webhooks</h3>
                    <p class="text-sm text-white/90">Not Setup</p>
                </div>
            </div>
            <p class="mt-2 text-sm text-white/80">configure sending dat to extranal URLs.</p>
            <div class="mt-4">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    Setup Webhooks
                </button>
            </div>
        </div>

        {{-- Row 2: Right column — OAuth + Google tile stacked --}}
        <div class="col-span-12 flex flex-col gap-6 lg:col-span-4">
            {{-- OAuth --}}
            <div class="rounded-xl bg-gradient-to-br from-accent to-accent-hover p-6 shadow-lg text-white">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-bold text-white">OAuth</h3>
                        <p class="text-sm text-white/90">2 applications authenticated</p>
                    </div>
                </div>
                <hr class="my-4 border-white/20">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    View Apps
                </button>
            </div>

            {{-- Google tile --}}
            <div class="flex flex-1 items-center justify-center rounded-xl border border-dark-border bg-gray-200 p-8 text-gray-900">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white text-2xl font-bold shadow-sm" aria-hidden="true">G</span>
            </div>
        </div>
    </div>

    {{-- Pagination strip --}}
    <div class="mt-6 flex flex-col gap-4 rounded-xl border border-dark-border bg-accent p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-300">
            Showing <span class="font-medium text-white">{{ $from }}</span>-<span class="font-medium text-white">{{ $to }}</span> of <span class="font-medium text-white">{{ $total }}</span>
        </p>
        <div class="flex items-center gap-3">
            <label for="integrations-per-page" class="sr-only">Items per page</label>
            <select
                id="integrations-per-page"
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
