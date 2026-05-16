@extends('layouts.super-admin')

@section('title', 'Support Tickets')
@section('content')
<x-super-admin.page title="Support Tickets">
<div class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-super-admin.kpi label="Open tickets" :value="$stats['open']" />
        <x-super-admin.kpi label="Waiting on customer" :value="$stats['waiting']" />
        <x-super-admin.kpi label="Closed" :value="$stats['closed']" />
        <x-super-admin.kpi label="SLA breached" :value="$stats['sla_breached']" />
    </div>

    <x-super-admin.card>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input name="search" value="{{ request('search') }}" placeholder="Search subject, number, or email" class="figma-input min-w-64 flex-1">
            <select name="status" class="figma-select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="priority" class="figma-select">
                <option value="">All priorities</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
            <button class="figma-sa-btn figma-sa-btn-primary">Filter</button>
        </form>
    </x-super-admin.card>

    <x-super-admin.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="figma-sa-table min-w-[1000px]">
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
                                <p class="font-semibold text-white">{{ $ticket->subject }}</p>
                                <p class="text-xs text-[#8c8787]">#{{ $ticket->ticket_number ?? $ticket->id }} · {{ $ticket->category ?? 'general' }}</p>
                            </td>
                            <td>{{ $ticket->owner?->email ?? '—' }}</td>
                            <td>
                                <p class="text-sm text-white">{{ $ticket->requester?->name ?? '—' }}</p>
                                <p class="text-xs text-[#8c8787]">{{ $ticket->requester?->email }}</p>
                            </td>
                            <td>
                                @php
                                    $pri = $ticket->priority ?? 'normal';
                                    $priCls = match ($pri) {
                                        'urgent' => 'figma-sa-pill-danger',
                                        'high'   => 'figma-sa-pill-warning',
                                        'low'    => 'figma-sa-pill-neutral',
                                        default  => 'figma-sa-pill-purple',
                                    };
                                @endphp
                                <span class="figma-sa-pill {{ $priCls }}">{{ ucfirst($pri) }}</span>
                            </td>
                            <td>
                                @php
                                    $statusCls = match (strtolower($ticket->status ?? '')) {
                                        'open'     => 'figma-sa-pill-warning',
                                        'waiting'  => 'figma-sa-pill-neutral',
                                        'resolved' => 'figma-sa-pill-success',
                                        'closed'   => 'figma-sa-pill-success',
                                        default    => 'figma-sa-pill-neutral',
                                    };
                                @endphp
                                <span class="figma-sa-pill {{ $statusCls }}">{{ ucfirst($ticket->status) }}</span>
                            </td>
                            <td>
                                @if ($ticket->sla_due_at)
                                    <span class="text-xs {{ $ticket->sla_due_at->isPast() && ! in_array($ticket->status, ['closed','resolved']) ? 'text-rose-300' : 'text-[#d9d9d9]' }}">
                                        {{ $ticket->sla_due_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-xs text-[#8c8787]">—</span>
                                @endif
                            </td>
                            <td class="text-right pr-4">
                                <a href="{{ route('super-admin.tickets.show', $ticket) }}" class="figma-sa-btn figma-sa-btn-outline">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-[#a9a9a9]">No tickets yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="figma-sa-pagination px-4 py-3">{{ $tickets->links() }}</div>
    </x-super-admin.card>
</div>
</x-super-admin.page>
@endsection
