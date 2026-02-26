@extends('layouts.admin')

@section('title', 'Plans & Pricing')

@section('content')
    <div class="space-y-6">
        {{-- Filter / search bar --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                <label for="plans-search" class="sr-only">Select plans</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        id="plans-search"
                        type="search"
                        placeholder="Select plans"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label for="plan-type-filter" class="sr-only">Plan type</label>
                    <select
                        id="plan-type-filter"
                        class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                        <option value="">All Plan Types</option>
                    </select>
                    <label for="plan-status-filter" class="sr-only">Status</label>
                    <select
                        id="plan-status-filter"
                        class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                        <option value="">All Status</option>
                    </select>
                    <button
                        type="button"
                        class="w-full rounded-xl border border-dark-border bg-dark-card px-4 py-2.5 text-sm font-medium text-white transition hover:bg-dark-border sm:w-auto"
                    >
                        SAVE
                    </button>
                </div>
            </div>
            <a
                href="#"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover sm:w-auto"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Plan
            </a>
        </section>

        {{-- Section title --}}
        <h2 class="text-center text-xl font-bold text-white">Click Protect.</h2>

        {{-- Pricing cards grid --}}
        <section class="space-y-6" aria-labelledby="plans-heading">
            <h2 id="plans-heading" class="sr-only">Pricing plans</h2>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <x-plan-card
                    name="Basic"
                    price="$10 / mo."
                    for-text="For small businesses & starters"
                    :features="[
                        'Real-time fake click detection',
                        'Bot & basic VPN traffic blocking',
                        'Google Ads protection',
                        'Single domain tracking',
                        'Essential reports',
                        'Email support',
                    ]"
                    button-text="Active"
                    button-style="secondary"
                />
                <x-plan-card
                    name="Pro"
                    price="$25 / mo."
                    for-text="For growing teams"
                    :features="[
                        'Everything in Basic',
                        'Advanced bot & proxy detection',
                        'Google + Meta Ads protection',
                        'Multiple domains',
                        'Automatic IP exclusions',
                        'Detailed traffic reports',
                        'Priority email support',
                    ]"
                    button-text="Active"
                    button-style="secondary"
                />
                <x-plan-card
                    name="Premium"
                    price="$50 / mo."
                    for-text="For scale & advanced protection"
                    :features="[
                        'Everything in Pro',
                        'Google, Meta & Microsoft Ads',
                        'Behavioral fraud analysis',
                        'Competitor click detection',
                        'Session-level monitoring',
                        'Advanced dashboards & exports',
                        'Faster blocking rules',
                        'Priority support',
                    ]"
                    button-text="Active"
                    button-style="secondary"
                />
            </div>
            <div class="flex flex-col items-center gap-6 md:flex-row md:justify-center md:gap-6">
                <x-plan-card
                    name="Enterprise"
                    price="Contact us"
                    for-text="For large organizations"
                    :features="[
                        'Everything in Premium',
                        'Unlimited domains',
                        'Team & role management',
                        'Custom fraud rules',
                        'API access',
                        'SLA & dedicated support',
                        'White-label reporting',
                    ]"
                    button-text="Active"
                    button-style="secondary"
                    class="w-full md:max-w-sm lg:max-w-xs"
                />
                <x-plan-card
                    name="Custom"
                    price="Custom features"
                    for-text="Tailored to your needs"
                    :features="[
                        'Custom traffic rules',
                        'Custom integrations',
                        'Custom reporting & dashboards',
                        'Custom pricing',
                        'Dedicated onboarding',
                    ]"
                    button-text="Custom Trial"
                    button-style="primary"
                    class="w-full md:max-w-sm lg:max-w-xs"
                />
            </div>
        </section>
    </div>
@endsection
