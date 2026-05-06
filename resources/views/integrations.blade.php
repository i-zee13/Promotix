@extends('layouts.admin')

@section('title', 'Paid Marketing — Platform Connections')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('paid-marketing.detailed') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detailed View</a>
            <a href="{{ route('integrations') }}" class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">Platform Connections</a>
            <a href="{{ route('paid-marketing.detection-settings') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detection Settings</a>
        </div>

        <section class="rounded-xl border border-dark-border bg-dark-card p-6">
            <h2 class="text-lg font-semibold text-white">Connect Your Platforms</h2>
            <p class="mt-1 text-sm text-gray-400">Connect Google via OAuth, then sync accessible Ads accounts (AW) and map domains.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="space-y-3 rounded-xl border border-dark-border bg-dark p-4 lg:col-span-1">
                    <p class="text-sm font-semibold text-white">Google Account</p>
                    <a href="{{ route('integrations.google.redirect') }}"
                       class="inline-flex w-full items-center justify-center rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">
                        Connect with Google
                    </a>
                    <p class="text-xs text-gray-500">
                        Requires Google OAuth app credentials and Ads API developer token in `.env`.
                    </p>
                    @if ($connections->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach ($connections as $connection)
                                <div class="rounded-lg border border-dark-border bg-dark-card p-3">
                                    <p class="text-sm text-white">{{ $connection->google_email }}</p>
                                    <p class="text-xs text-gray-500">Connected {{ $connection->connected_at?->diffForHumans() ?? '—' }}</p>
                                    <div class="mt-2 flex gap-2">
                                        <form method="POST" action="{{ route('integrations.google.sync-accounts', $connection) }}">
                                            @csrf
                                            <button class="rounded-lg bg-accent px-3 py-1.5 text-xs font-medium text-white hover:bg-accent-hover">Sync Accounts</button>
                                        </form>
                                        <form method="POST" action="{{ route('integrations.google.disconnect', $connection) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-500/20">Disconnect</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('integrations.store-account') }}" class="space-y-3 rounded-xl border border-dark-border bg-dark p-4 lg:col-span-2">
                    @csrf
                    <p class="text-sm font-semibold text-white">Google Ads Account (AW) — Manual fallback</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <select name="google_connection_id" required class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                            <option value="">Select connected Google user</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->google_email }}</option>
                            @endforeach
                        </select>
                        <input name="customer_id" type="text" required placeholder="Customer ID (1234567890)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="display_customer_id" type="text" placeholder="Display ID (AW-1234567890)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="google_tag_id" type="text" placeholder="Google Tag ID (AW-123456...)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="account_name" type="text" placeholder="Account name" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="manager_customer_id" type="text" placeholder="Manager ID (optional)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" name="is_manager" value="1" class="rounded border-dark-border bg-dark-card text-accent focus:ring-accent">
                        Manager account (MCC)
                    </label>
                    <button class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">Save Ads Account</button>
                </form>
            </div>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card p-6">
            <h2 class="text-lg font-semibold text-white">Link Domain to Connected ID</h2>
            <p class="mt-1 text-sm text-gray-400">This creates the domain-specific rule target just like ClickCease Tag + Pixel Guard mapping.</p>

            <form method="POST" action="{{ route('integrations.store-mapping') }}" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-5">
                @csrf
                <select name="domain_id" required class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none lg:col-span-2">
                    <option value="">Select domain</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                    @endforeach
                </select>
                <select name="google_ads_account_id" required class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none lg:col-span-2">
                    <option value="">Select connected account</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->display_customer_id ?: $account->customer_id }} — {{ $account->google_tag_id ?: 'No tag ID' }}
                        </option>
                    @endforeach
                </select>
                <select name="protection_type" class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                    <option value="ip_blocking">IP Blocking</option>
                    <option value="pixel_guard">Pixel Guard</option>
                </select>
                <label class="inline-flex items-center gap-2 text-sm text-gray-300 lg:col-span-2">
                    <input type="checkbox" name="audience_exclusion_enabled" value="1" checked class="rounded border-dark-border bg-dark text-accent focus:ring-accent">
                    Audience exclusion enabled
                </label>
                <button class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover lg:col-span-1">Link Domain</button>
            </form>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead>
                    <tr class="border-b border-dark-border bg-accent">
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Platform</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Protection Type</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Connected ID (AW)</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Tag</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Domain</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Settings</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                    @forelse ($mappings as $mapping)
                        <tr class="hover:bg-gray-800/40">
                            <td class="px-4 py-3 text-gray-200">Google</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->protection_type === 'pixel_guard' ? 'Pixel Guard' : 'IP Blocking' }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->account->display_customer_id ?: $mapping->account->customer_id }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->account->google_tag_id ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->domain->hostname }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('paid-marketing.detection-settings', ['domain_id' => $mapping->domain_id]) }}" class="text-accent hover:underline">Campaign Settings</a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('integrations.destroy-mapping', $mapping) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-500/20">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No platform mappings yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($mappings->hasPages())
                <div class="border-t border-dark-border px-4 py-3">{{ $mappings->links() }}</div>
            @endif
        </section>
    </div>
@endsection
