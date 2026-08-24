@extends('layouts.super-admin')

@section('title', 'Ticket '.($ticket->ticket_number ?? $ticket->id))
@section('content')
<x-super-admin.page :title="'Ticket #'.($ticket->ticket_number ?? $ticket->id)" :subtitle="$ticket->subject">
@include('partials.super-admin.flash')
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
                <article class="figma-sa-msg mt-3 {{ $message->is_agent_reply ? 'figma-sa-msg-admin' : '' }}">
                    <header class="mb-2 flex items-center justify-between text-xs text-[#a9a9a9]">
                        <span>{{ $message->author?->name ?? 'System' }} ({{ $message->author?->email }})</span>
                        <time>{{ $message->created_at?->diffForHumans() }}</time>
                    </header>
                    <p class="whitespace-pre-line text-sm text-white">{{ $message->body }}</p>
                </article>
            @endforeach

            <form method="POST" action="{{ route('super-admin.tickets.reply', $ticket) }}" class="mt-4 space-y-3 border-t border-white/10 pt-4">
                @csrf
                <label class="figma-sa-label">Reply</label>
                <textarea name="body" rows="4" required class="figma-select mt-1 w-full" placeholder="Write a reply to the requester…"></textarea>
                <button class="figma-sa-btn figma-sa-btn-primary">Send reply</button>
            </form>
        </x-super-admin.card>

        <x-super-admin.card title="Assignment & SLA">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">Tenant</dt>
                    <dd class="text-white">{{ $ticket->owner?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">Source</dt>
                    <dd class="text-white">{{ $ticket->source ? str_replace('_', ' ', $ticket->source) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">Assigned to</dt>
                    <dd class="text-white">{{ $ticket->assignee?->name ?? 'Unassigned' }} <span class="text-[#8c8787]">{{ $ticket->assignee?->email }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">SLA due</dt>
                    <dd class="text-white">{{ $ticket->sla_due_at?->toDayDateTimeString() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-[#8c8787]">First response</dt>
                    <dd class="text-white">{{ $ticket->first_response_at?->diffForHumans() ?? 'Not yet' }}</dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('super-admin.tickets.assign', $ticket) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="figma-sa-label">Assignee</label>
                    <select name="assigned_to_id" class="figma-select mt-1">
                        <option value="">Unassigned</option>
                        @foreach ($assignees as $agent)
                            <option value="{{ $agent->id }}" @selected((int) $ticket->assigned_to_id === (int) $agent->id)>
                                {{ $agent->name }} ({{ $agent->email }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-white/45">Assign only if this agent is on the correct team queue.</p>
                </div>
                <div>
                    <label class="figma-sa-label">Department</label>
                    <select name="department" class="figma-select mt-1">
                        <option value="">—</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}" @selected(($ticket->department ?? $ticket->category) === $dept)>{{ ucfirst($dept) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="figma-sa-label">Status</label>
                    <select name="status" class="figma-select mt-1">
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($ticket->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="figma-sa-label">Priority</label>
                    <select name="priority" class="figma-select mt-1">
                        @foreach ($priorities as $priority)
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
