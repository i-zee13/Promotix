@extends('layouts.super-admin')

@section('title', 'Ticket '.($ticket->ticket_number ?? $ticket->id))
@section('content')
<x-super-admin.page :title="'Ticket #'.($ticket->ticket_number ?? $ticket->id)" :subtitle="$ticket->subject">
<div class="space-y-[16px]">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card class="lg:col-span-2" title="Conversation">
            <article class="figma-sa-msg">
                <header class="mb-2 flex items-center justify-between text-xs text-[#a9a9a9]">
                    <span>{{ $ticket->requester?->name ?? 'Anonymous' }} ({{ $ticket->requester?->email }})</span>
                    <time>{{ $ticket->created_at?->diffForHumans() }}</time>
                </header>
                <p class="whitespace-pre-line text-sm text-white">{{ $ticket->body }}</p>
            </article>

            @foreach ($ticket->messages as $message)
                <article class="figma-sa-msg mt-3 {{ $message->author?->is_admin ? 'figma-sa-msg-admin' : '' }}">
                    <header class="mb-2 flex items-center justify-between text-xs text-[#a9a9a9]">
                        <span>{{ $message->author?->name ?? 'System' }} ({{ $message->author?->email }})</span>
                        <time>{{ $message->created_at?->diffForHumans() }}</time>
                    </header>
                    <p class="whitespace-pre-line text-sm text-white">{{ $message->body }}</p>
                </article>
            @endforeach
        </x-super-admin.card>

        <x-super-admin.card title="Assignment & SLA">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">Tenant</dt>
                    <dd class="text-white">{{ $ticket->owner?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">Assigned to</dt>
                    <dd class="text-white">{{ $ticket->assignee?->name ?? 'Unassigned' }} <span class="text-[#8c8787]">{{ $ticket->assignee?->email }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">SLA due</dt>
                    <dd class="text-white">{{ $ticket->sla_due_at?->toDayDateTimeString() ?? '—' }}</dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('super-admin.tickets.assign', $ticket) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="figma-sa-label">Status</label>
                    <select name="status" class="figma-select mt-1">
                        @foreach (['open','waiting','resolved','closed'] as $status)
                            <option value="{{ $status }}" @selected($ticket->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="figma-sa-label">Priority</label>
                    <select name="priority" class="figma-select mt-1">
                        @foreach (['low','normal','high','urgent'] as $priority)
                            <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="figma-sa-btn figma-sa-btn-primary w-full">Save changes</button>
            </form>
        </x-super-admin.card>
    </div>
</div>
</x-super-admin.page>
@endsection
