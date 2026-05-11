@extends('layouts.admin')

@section('title', 'Bot Mitigation')
@section('subtitle', 'IP-level threat intelligence and blocking')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="Bot Mitigation" subtitle="IP-level threat intelligence and blocking" />

        @if (session('status'))
            <div class="brand-pill brand-pill-success">{{ session('status') }}</div>
        @endif

        {{-- Filters --}}
        <x-ui.card variant="flat">
            <form method="GET" action="{{ route('ip-logs') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-night-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input id="ip-search" name="search" type="search" value="{{ request('search') }}"
                           placeholder="Search IP, path, referrer, user agent" class="brand-input pl-9">
                </div>
                <select id="ip-blocked" name="blocked" class="brand-select sm:max-w-[180px]">
                    <option value="">All IPs</option>
                    <option value="0" @selected(request('blocked') === '0')>Allowed only</option>
                    <option value="1" @selected(request('blocked') === '1')>Blocked only</option>
                </select>
                <x-ui.button type="submit" variant="primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 4h13M8 12h13M8 20h13M3 4h.01M3 12h.01M3 20h.01"/></svg>
                    Filter
                </x-ui.button>
            </form>
        </x-ui.card>

        {{-- IP logs table --}}
        <x-ui.card title="IP logs" subtitle="Manage IPs detected by your tracking script. Blocked IPs are bounced on future visits.">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[900px]">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Intel</th>
                            <th>Hits</th>
                            <th>Last Seen</th>
                            <th>Last Path</th>
                            <th>Referrer</th>
                            <th>User Agent</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>
                                    <p class="font-semibold text-white">{{ $log->ip }}</p>
                                    <p class="text-xs text-night-400">#{{ $log->id }}</p>
                                </td>
                                <td>
                                    <x-ui.pill :tone="$log->is_blocked ? 'danger' : 'success'">
                                        {{ $log->is_blocked ? 'Blocked' : 'Allowed' }}
                                    </x-ui.pill>
                                </td>
                                <td>
                                    @if ($log->intel_checked_at)
                                        <div class="flex flex-col gap-1">
                                            <div class="flex flex-wrap gap-1">
                                                @if (is_numeric($log->ipdetails_abuser_score))
                                                    @php
                                                        $s = (float) $log->ipdetails_abuser_score;
                                                        $label = $s >= 0.7 ? 'High' : ($s >= 0.2 ? 'Medium' : 'Low');
                                                        $tone  = $s >= 0.7 ? 'danger' : ($s >= 0.2 ? 'warning' : 'success');
                                                    @endphp
                                                    <x-ui.pill :tone="$tone">Abuser {{ $label }} ({{ $log->ipdetails_abuser_score }})</x-ui.pill>
                                                @endif
                                                @if ($log->abuse_is_tor ?? false)
                                                    <x-ui.pill tone="purple">Tor</x-ui.pill>
                                                @endif
                                                @if (is_int($log->abuse_confidence_score) && $log->abuse_confidence_score >= 50)
                                                    <x-ui.pill tone="danger">Abuse {{ $log->abuse_confidence_score }}</x-ui.pill>
                                                @elseif (is_int($log->abuse_confidence_score))
                                                    <x-ui.pill tone="success">Score {{ $log->abuse_confidence_score }}</x-ui.pill>
                                                @endif
                                            </div>
                                            <span class="text-[11px] text-night-400">Checked {{ $log->intel_checked_at->diffForHumans() }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-night-400">Pending</span>
                                    @endif
                                </td>
                                <td><span class="font-semibold text-white">{{ $log->hits }}</span></td>
                                <td class="text-night-200">{{ optional($log->last_seen_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="max-w-xs">
                                    <p class="truncate text-xs text-night-200" title="{{ $log->last_path }}">{{ $log->last_path ?? '—' }}</p>
                                </td>
                                <td class="max-w-xs">
                                    <p class="truncate text-xs text-night-200" title="{{ $log->last_referrer }}">{{ $log->last_referrer ?? '—' }}</p>
                                </td>
                                <td class="max-w-sm">
                                    <p class="truncate text-xs text-night-300" title="{{ $log->user_agent }}">{{ $log->user_agent ?? '—' }}</p>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('ip-logs.toggle-block', $log) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-white transition
                                                        {{ $log->is_blocked ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700' }}">
                                                {{ $log->is_blocked ? 'Unblock' : 'Block' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('ip-logs.destroy', $log) }}" onsubmit="return confirm('Delete this IP log?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="brand-btn-outline px-3 py-1.5 text-xs">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 text-center text-sm text-night-300">
                                    No IP logs found yet. Once your tracking script starts receiving traffic, IPs will appear here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="mt-6 flex flex-col gap-4 rounded-xl border border-night-700 bg-night-900/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-night-300">
                        Showing
                        <span class="font-medium text-white">{{ $logs->firstItem() }}</span>–
                        <span class="font-medium text-white">{{ $logs->lastItem() }}</span>
                        of <span class="font-medium text-white">{{ $logs->total() }}</span> IPs
                    </p>
                    <div class="flex items-center gap-3">
                        {{ $logs->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
