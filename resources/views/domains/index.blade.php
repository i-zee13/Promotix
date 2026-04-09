@extends('layouts.admin')

@section('title', 'Domain Management')

@section('content')
    <div class="space-y-6" x-data="{ addOpen: false }">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif

        <section class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-400">Connect your domains first. Then set up the tag installation (manual / WP / GTM).</p>
            </div>
            <button type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover"
                    @click="addOpen = true">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Domain
            </button>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-dark-border bg-accent">
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Domain</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Tag Management</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Paid Marketing</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Bot Mitigation</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Mode</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                        @forelse ($domains as $d)
                            <tr class="transition hover:bg-gray-800/50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white">{{ $d->hostname }}</p>
                                    <p class="text-xs text-gray-500">Last seen: {{ $d->last_seen_at?->diffForHumans() ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($d->tag_connected)
                                        <span class="inline-flex rounded-md bg-emerald-500/20 px-2 py-1 text-xs font-medium text-emerald-300">Connected</span>
                                    @else
                                        <a href="{{ route('domains.setup', $d) }}" class="inline-flex rounded-md bg-accent/20 px-2 py-1 text-xs font-medium text-accent hover:bg-accent/30">Set up</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $d->paid_marketing_connected ? 'bg-emerald-500/20 text-emerald-300' : 'bg-gray-700 text-gray-200' }}">
                                        {{ $d->paid_marketing_connected ? 'Connected' : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $d->bot_mitigation_connected ? 'bg-emerald-500/20 text-emerald-300' : 'bg-gray-700 text-gray-200' }}">
                                        {{ $d->bot_mitigation_connected ? 'Connected' : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $d->monitoring_only_mode ? 'bg-amber-500/20 text-amber-300' : 'bg-gray-700 text-gray-200' }}">
                                        {{ $d->monitoring_only_mode ? 'Monitoring only' : 'Active' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('domains.setup', $d) }}" class="rounded-lg px-3 py-1.5 text-sm text-gray-300 hover:bg-dark-border hover:text-white">Setup</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-400">No domains yet. Add your first domain to begin.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($domains->hasPages())
                <div class="border-t border-dark-border px-4 py-3">
                    {{ $domains->links() }}
                </div>
            @endif
        </section>

        {{-- Add Domain Modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" x-show="addOpen" x-cloak x-transition @keydown.escape.window="addOpen = false" @click.self="addOpen = false">
            <div class="w-full max-w-xl rounded-xl border border-dark-border bg-dark-card shadow-xl">
                <div class="flex items-center justify-between border-b border-dark-border px-6 py-4">
                    <h3 class="text-lg font-semibold text-white">Add domain</h3>
                    <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-dark-border hover:text-white" @click="addOpen = false" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('domains.store') }}" class="space-y-4 p-6">
                    @csrf
                    <p class="text-sm text-gray-400">Add domains you would like to protect.</p>
                    <div>
                        <label for="hostname" class="sr-only">Domain</label>
                        <input id="hostname" name="hostname" type="text" required placeholder="Example: www.yourdomainnamehere.com"
                               class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                        @error('hostname')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-xl border border-dark-border px-4 py-2.5 text-sm font-medium text-gray-300 hover:bg-dark-border" @click="addOpen = false">Close</button>
                        <button type="submit" class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

