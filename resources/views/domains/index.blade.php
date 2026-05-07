@extends('layouts.admin')

@section('title', 'Domain Management')

@section('content')
    <div class="space-y-6" x-data="domainsIndex()">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif

        <section class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-400">Connect your domains first. Then set up the tag installation (manual / WP / GTM).</p>
                <p class="mt-1 text-xs text-gray-500">Domains: {{ $domainCount }} / {{ $domainLimit }}</p>
                @if ($domainCount >= $domainLimit)
                    <p class="mt-1 text-xs text-amber-400">Domain limit reached. Remove an existing domain to add more.</p>
                @endif
            </div>
            <button type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover"
                    @if ($domainCount >= $domainLimit) disabled @endif
                    @click="addOpen = true">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Domain
            </button>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card p-4">
            <form method="GET" action="{{ route('domains.index') }}" class="flex gap-2">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search existing domains"
                       class="w-full rounded-xl border border-dark-border bg-dark px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                <button type="submit" class="rounded-xl border border-dark-border px-4 py-2.5 text-sm text-gray-200 hover:bg-dark-border">Search</button>
            </form>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-dark-border bg-accent">
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Domain</th>
                            <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Status</th>
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
                                    @php
                                        $statusMap = [
                                            'pending' => 'bg-amber-500/20 text-amber-300',
                                            'connected' => 'bg-emerald-500/20 text-emerald-300',
                                            'disabled' => 'bg-rose-500/20 text-rose-300',
                                        ];
                                        $status = $d->status ?? 'pending';
                                    @endphp
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $statusMap[$status] ?? 'bg-gray-700 text-gray-200' }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                    <div class="mt-2">
                                        <select class="rounded-lg border border-dark-border bg-dark px-2 py-1 text-xs text-gray-200" @change="updateStatus('{{ $d->id }}', $event.target.value)">
                                            <option value="">Change...</option>
                                            <option value="pending">Pending</option>
                                            <option value="connected">Connected</option>
                                            <option value="disabled">Disabled</option>
                                        </select>
                                    </div>
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
                                <td colspan="7" class="px-4 py-10 text-center text-gray-400">No domains yet. Add your first domain to begin.</td>
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
                    <div>
                        <label for="bulk-hostnames" class="block text-xs text-gray-500 mb-2">Bulk add (one domain per line)</label>
                        <textarea id="bulk-hostnames" rows="4" placeholder="example.com&#10;shop.example.com" class="w-full rounded-xl border border-dark-border bg-dark py-2.5 px-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"></textarea>
                        <button type="button" class="mt-2 rounded-lg border border-dark-border px-3 py-2 text-xs text-gray-300 hover:bg-dark-border"
                                @click="(async () => {
                                    const lines = ($el.previousElementSibling.value || '').split('\n').map(v => v.trim()).filter(Boolean);
                                    if (!lines.length) return;
                                    const res = await fetch('/domains/bulk-add', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
                                        body: JSON.stringify({hostnames: lines})
                                    });
                                    const data = await res.json();
                                    alert(`Added: ${(data.added || []).length}, Skipped: ${(data.skipped || []).length}`);
                                    window.location.reload();
                                })();">
                            Add selected domains
                        </button>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-xl border border-dark-border px-4 py-2.5 text-sm font-medium text-gray-300 hover:bg-dark-border" @click="addOpen = false">Close</button>
                        <button type="submit" class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function domainsIndex() {
            return {
                addOpen: false,
                async updateStatus(domainId, status) {
                    if (!status) return;
                    const res = await fetch(`/domains/${domainId}/status`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ status }),
                    });
                    if (res.ok) window.location.reload();
                },
            };
        }
    </script>
@endsection

