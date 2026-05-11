@extends('layouts.super-admin')

@section('title', 'Ticket '.($ticket->ticket_number ?? $ticket->id))
@section('subtitle', $ticket->subject)

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Conversation">
            <article class="rounded-xl2 border border-night-700/60 bg-night-900/40 p-4">
                <header class="mb-2 flex items-center justify-between text-xs text-night-300">
                    <span>{{ $ticket->requester?->name ?? 'Anonymous' }} ({{ $ticket->requester?->email }})</span>
                    <time>{{ $ticket->created_at?->diffForHumans() }}</time>
                </header>
                <p class="whitespace-pre-line text-sm text-night-100">{{ $ticket->body }}</p>
            </article>

            @foreach ($ticket->messages as $message)
                <article class="mt-3 rounded-xl2 border border-night-700/60 p-4 {{ $message->author?->is_admin ? 'bg-brand-500/10' : 'bg-night-900/40' }}">
                    <header class="mb-2 flex items-center justify-between text-xs text-night-300">
                        <span>{{ $message->author?->name ?? 'System' }} ({{ $message->author?->email }})</span>
                        <time>{{ $message->created_at?->diffForHumans() }}</time>
                    </header>
                    <p class="whitespace-pre-line text-sm text-night-100">{{ $message->body }}</p>
                </article>
            @endforeach
        </x-ui.card>

        <x-ui.card title="Assignment & SLA">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-night-400">Tenant</dt>
                    <dd class="text-night-100">{{ $ticket->owner?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-night-400">Assigned to</dt>
                    <dd class="text-night-100">{{ $ticket->assignee?->name ?? 'Unassigned' }} <span class="text-night-400">{{ $ticket->assignee?->email }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-night-400">SLA due</dt>
                    <dd class="text-night-100">{{ $ticket->sla_due_at?->toDayDateTimeString() ?? '—' }}</dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('super-admin.tickets.assign', $ticket) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="brand-label">Status</label>
                    <select name="status" class="brand-select mt-1">
                        @foreach (['open','waiting','resolved','closed'] as $status)
                            <option value="{{ $status }}" @selected($ticket->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="brand-label">Priority</label>
                    <select name="priority" class="brand-select mt-1">
                        @foreach (['low','normal','high','urgent'] as $priority)
                            <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="brand-btn-primary w-full">Save changes</button>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
