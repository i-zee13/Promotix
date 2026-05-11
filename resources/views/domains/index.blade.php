@extends('layouts.admin')

@section('title', 'Domains')
@section('subtitle', 'Connect domains and manage tag installation')

@section('content')
    <div class="space-y-6" x-data="domainsIndex()">
        <x-ui.page-header title="Domains" subtitle="Connect your domains and manage tag installation">
            <x-slot:actions>
                <span class="hidden text-xs text-night-300 sm:inline">{{ $domainCount }} / {{ $domainLimit }} used</span>
                <button type="button" class="brand-btn-primary" @click="addOpen = true" @disabled($domainCount >= $domainLimit)>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Domain
                </button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="brand-pill brand-pill-success">{{ session('status') }}</div>
        @endif

        @if ($domainCount >= $domainLimit)
            <x-ui.card variant="flat">
                <p class="text-sm text-amber-300">Domain limit reached. Remove an existing domain to add more.</p>
            </x-ui.card>
        @endif

        {{-- Search --}}
        <x-ui.card variant="flat">
            <form method="GET" action="{{ route('domains.index') }}" class="flex gap-2">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search existing domains" class="brand-input">
                <x-ui.button type="submit" variant="outline">Search</x-ui.button>
            </form>
        </x-ui.card>

        {{-- Domains table --}}
        <x-ui.card variant="flat">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[900px]">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Tag</th>
                            <th>Paid Marketing</th>
                            <th>Bot Mitigation</th>
                            <th>Mode</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($domains as $d)
                            @php
                                $statusToneMap = [
                                    'pending'   => 'warning',
                                    'connected' => 'success',
                                    'disabled'  => 'danger',
                                ];
                                $status = $d->status ?? 'pending';
                                $statusTone = $statusToneMap[$status] ?? 'neutral';
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-white">{{ $d->hostname }}</p>
                                    <p class="text-xs text-night-400">Last seen: {{ $d->last_seen_at?->diffForHumans() ?? '—' }}</p>
                                </td>
                                <td>
                                    <div class="flex flex-col items-start gap-1.5">
                                        <x-ui.pill :tone="$statusTone">{{ ucfirst($status) }}</x-ui.pill>
                                        <select class="brand-select max-w-[140px] py-1 text-xs"
                                                @change="updateStatus('{{ $d->id }}', $event.target.value)">
                                            <option value="">Change…</option>
                                            <option value="pending">Pending</option>
                                            <option value="connected">Connected</option>
                                            <option value="disabled">Disabled</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    @if ($d->tag_connected)
                                        <x-ui.pill tone="success">Connected</x-ui.pill>
                                    @else
                                        <a href="{{ route('domains.setup', $d) }}" class="brand-pill brand-pill-purple">Set up</a>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.pill :tone="$d->paid_marketing_connected ? 'success' : 'neutral'">
                                        {{ $d->paid_marketing_connected ? 'Connected' : '—' }}
                                    </x-ui.pill>
                                </td>
                                <td>
                                    <x-ui.pill :tone="$d->bot_mitigation_connected ? 'success' : 'neutral'">
                                        {{ $d->bot_mitigation_connected ? 'Connected' : '—' }}
                                    </x-ui.pill>
                                </td>
                                <td>
                                    <x-ui.pill :tone="$d->monitoring_only_mode ? 'warning' : 'neutral'">
                                        {{ $d->monitoring_only_mode ? 'Monitoring only' : 'Active' }}
                                    </x-ui.pill>
                                </td>
                                <td>
                                    <a href="{{ route('domains.setup', $d) }}" class="text-sm font-medium text-brand-200 hover:text-white">Setup</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-night-300">No domains yet. Add your first domain to begin.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($domains->hasPages())
                <div class="mt-4 border-t border-night-700/60 pt-4">
                    {{ $domains->links() }}
                </div>
            @endif
        </x-ui.card>

        {{-- Add Domain modal --}}
        <div class="brand-modal-overlay" x-show="addOpen" x-cloak x-transition
             @keydown.escape.window="addOpen = false" @click.self="addOpen = false">
            <div class="brand-modal max-w-xl">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="brand-modal-title">Add domain</h3>
                    <button type="button" class="rounded-lg p-1.5 text-night-300 hover:bg-night-800 hover:text-white"
                            @click="addOpen = false" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <form method="POST" action="{{ route('domains.store') }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-night-300">Add the domains you'd like to protect.</p>

                    <div>
                        <label for="hostname" class="brand-label mb-1.5">Domain</label>
                        <input id="hostname" name="hostname" type="text" required placeholder="www.yourdomain.com" class="brand-input">
                        @error('hostname')
                            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="bulk-hostnames" class="brand-label mb-1.5">Bulk add (one per line)</label>
                        <textarea id="bulk-hostnames" rows="4" placeholder="example.com&#10;shop.example.com" class="brand-input"></textarea>
                        <button type="button" class="brand-btn-soft mt-2 text-xs"
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
                            Add bulk domains
                        </button>
                    </div>

                    <div class="flex justify-end gap-3">
                        <x-ui.button type="button" variant="ghost" @click="addOpen = false">Close</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Continue</x-ui.button>
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
