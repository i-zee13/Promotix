@extends('layouts.super-admin')

@section('title', 'Support Tickets')
@section('subtitle', 'All tickets across every tenant — assign, prioritize, and resolve')

@section('content')
<div class="space-y-5">
    @if (session('status'))
        <div class="rounded-xl2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.kpi-card label="Open tickets" :value="$stats['open']" />
        <x-ui.kpi-card label="Waiting on customer" :value="$stats['waiting']" />
        <x-ui.kpi-card label="Closed" :value="$stats['closed']" />
        <x-ui.kpi-card label="SLA breached" :value="$stats['sla_breached']" />
    </div>

    <x-ui.card>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input name="search" value="{{ request('search') }}" placeholder="Search subject, number, or email" class="brand-input min-w-64 flex-1">
            <select name="status" class="brand-select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="priority" class="brand-select">
                <option value="">All priorities</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
            <button class="brand-btn-primary">Filter</button>
        </form>
    </x-ui.card>

    <x-ui.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="brand-table min-w-[1000px]">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Tenant</th>
                        <th>Requester</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>SLA</th>
                        <th class="text-right pr-4">Open</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td>
                                <p class="font-semibold text-night-100">{{ $ticket->subject }}</p>
                                <p class="text-xs text-night-400">#{{ $ticket->ticket_number ?? $ticket->id }} · {{ $ticket->category ?? 'general' }}</p>
                            </td>
                            <td>{{ $ticket->owner?->email ?? '—' }}</td>
                            <td>
                                <p class="text-sm text-night-100">{{ $ticket->requester?->name ?? '—' }}</p>
                                <p class="text-xs text-night-400">{{ $ticket->requester?->email }}</p>
                            </td>
                            <td>
                                @php
                                    $pri = $ticket->priority ?? 'normal';
                                    $priCls = match ($pri) {
                                        'urgent' => 'brand-pill-danger',
                                        'high'   => 'brand-pill-warning',
                                        'low'    => 'brand-pill-neutral',
                                        default  => 'brand-pill-purple',
                                    };
                                @endphp
                                <span class="brand-pill {{ $priCls }}">{{ ucfirst($pri) }}</span>
                            </td>
                            <td>
                                @php
                                    $statusCls = match (strtolower($ticket->status ?? '')) {
                                        'open'     => 'brand-pill-warning',
                                        'waiting'  => 'brand-pill-neutral',
                                        'resolved' => 'brand-pill-success',
                                        'closed'   => 'brand-pill-success',
                                        default    => 'brand-pill-neutral',
                                    };
                                @endphp
                                <span class="brand-pill {{ $statusCls }}">{{ ucfirst($ticket->status) }}</span>
                            </td>
                            <td>
                                @if ($ticket->sla_due_at)
                                    <span class="text-xs {{ $ticket->sla_due_at->isPast() && ! in_array($ticket->status, ['closed','resolved']) ? 'text-rose-300' : 'text-night-200' }}">
                                        {{ $ticket->sla_due_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-xs text-night-400">—</span>
                                @endif
                            </td>
                            <td class="text-right pr-4">
                                <a href="{{ route('super-admin.tickets.show', $ticket) }}" class="brand-btn-secondary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-night-300">No tickets yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-night-700/60 px-4 py-3">{{ $tickets->links() }}</div>
    </x-ui.card>
</div>
@endsection
