@extends('layouts.admin')

@section('title', 'IP Logs')

@section('content')
    <div class="space-y-6">
        {{-- Filters --}}
        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
            <form method="GET" action="{{ route('ip-logs') }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <label for="ip-search" class="sr-only">Search IPs</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input
                        id="ip-search"
                        name="search"
                        type="search"
                        value="{{ request('search') }}"
                        placeholder="Search IP, path, referrer, user agent"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                </div>

                <label for="ip-blocked" class="sr-only">Blocked status</label>
                <select
                    id="ip-blocked"
                    name="blocked"
                    class="rounded-xl border border-dark-border bg-dark-card py-2 pl-4 pr-10 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                >
                    <option value="">All IPs</option>
                    <option value="0" @selected(request('blocked') === '0')>Allowed only</option>
                    <option value="1" @selected(request('blocked') === '1')>Blocked only</option>
                </select>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:bg-accent-hover"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4h13M8 12h13M8 20h13M3 4h.01M3 12h.01M3 20h.01"/></svg>
                    Filter
                </button>
            </form>
        </section>

        {{-- Flash message --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        {{-- IP logs table --}}
        <section class="rounded-xl border border-dark-border bg-dark-card p-6" aria-labelledby="ip-logs-heading">
            <h2 id="ip-logs-heading" class="text-lg font-semibold text-white">IP Logs</h2>
            <p class="mt-1 text-sm text-gray-400">
                Manage IPs detected by your tracking script. Blocked IPs will be bounced on future visits.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead>
                    <tr class="border-b border-dark-border bg-accent">
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">IP Address</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Status</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Intel</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Hits</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Last Seen</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Last Path</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Referrer</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">User Agent</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white"><span class="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                    @forelse ($logs as $log)
                        <tr class="transition hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-semibold text-white">{{ $log->ip }}</span>
                                    <span class="text-xs text-gray-500">#{{ $log->id }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($log->is_blocked)
                                    <span class="inline-flex rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white">
                                        Blocked
                                    </span>
                                @else
                                    <span class="inline-flex rounded-md bg-green-600 px-2 py-1 text-xs font-medium text-white">
                                        Allowed
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($log->intel_checked_at)
                                    <div class="flex flex-col gap-1">
                                        <div class="flex flex-wrap gap-1">
                                            @if (is_numeric($log->ipdetails_abuser_score))
                                                @php
                                                    $s = (float) $log->ipdetails_abuser_score;
                                                    $label = $s >= 0.7 ? 'High' : ($s >= 0.2 ? 'Medium' : 'Low');
                                                    $cls = $s >= 0.7 ? 'bg-red-500/20 text-red-300' : ($s >= 0.2 ? 'bg-amber-500/20 text-amber-300' : 'bg-emerald-500/20 text-emerald-300');
                                                @endphp
                                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium {{ $cls }}">Abuser {{ $label }} ({{ $log->ipdetails_abuser_score }})</span>
                                            @endif
                                            @if ($log->abuse_is_tor ?? false)
                                                <span class="inline-flex rounded-md bg-purple-500/20 px-2 py-0.5 text-xs font-medium text-purple-300">Tor</span>
                                            @endif
                                            @if (is_int($log->abuse_confidence_score) && $log->abuse_confidence_score >= 50)
                                                <span class="inline-flex rounded-md bg-red-500/20 px-2 py-0.5 text-xs font-medium text-red-300">Abuse {{ $log->abuse_confidence_score }}</span>
                                            @elseif (is_int($log->abuse_confidence_score))
                                                <span class="inline-flex rounded-md bg-emerald-500/20 px-2 py-0.5 text-xs font-medium text-emerald-300">Score {{ $log->abuse_confidence_score }}</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-gray-500">Checked {{ $log->intel_checked_at->diffForHumans() }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-500">Pending</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-white">{{ $log->hits }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-300">
                                {{ optional($log->last_seen_at)->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="truncate text-xs text-gray-300" title="{{ $log->last_path }}">
                                    {{ $log->last_path ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="truncate text-xs text-gray-300" title="{{ $log->last_referrer }}">
                                    {{ $log->last_referrer ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 max-w-sm">
                                <p class="truncate text-xs text-gray-400" title="{{ $log->user_agent }}">
                                    {{ $log->user_agent ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('ip-logs.toggle-block', $log) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-white transition
                                                {{ $log->is_blocked ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }}"
                                        >
                                            {{ $log->is_blocked ? 'Unblock' : 'Block' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('ip-logs.destroy', $log) }}" onsubmit="return confirm('Delete this IP log?');">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-200 hover:bg-dark-border"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-sm text-gray-400">
                                No IP logs found yet. Once your tracking script starts receiving traffic, IPs will appear here.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="mt-6 flex flex-col gap-4 rounded-xl border border-dark-border bg-accent p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-300">
                        Showing
                        <span class="font-medium text-white">{{ $logs->firstItem() }}</span>–
                        <span class="font-medium text-white">{{ $logs->lastItem() }}</span>
                        of
                        <span class="font-medium text-white">{{ $logs->total() }}</span>
                        IPs
                    </p>
                    <div class="flex items-center gap-3">
                        {{ $logs->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection

