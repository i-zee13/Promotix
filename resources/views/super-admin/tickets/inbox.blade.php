@extends('layouts.super-admin')

@section('title', 'Support System')
@section('content')
@php
    $selectedId = $selected?->id;
    $query = request()->except(['page']);
@endphp
<div class="ticket-inbox ticket-inbox--admin min-h-[calc(100vh-49px)]" x-data="{ q: @js(request('search', '')) }">
    <aside class="ticket-inbox__list{{ $selected ? ' ticket-inbox__list--hidden-mobile' : '' }}">
        <div class="ticket-inbox__list-head">
            <div class="ticket-inbox__stats">
                <span>{{ number_format($stats['open']) }} open</span>
                <span>{{ number_format($stats['assigned']) }} assigned</span>
                <span>{{ number_format($stats['unassigned']) }} unassigned</span>
            </div>
            <div class="ticket-inbox__filters">
                <a href="{{ route('super-admin.tickets.index') }}" class="ticket-inbox__filter{{ ! request()->boolean('assigned') && ! request()->boolean('unassigned') && ! request()->boolean('mine') ? ' is-on' : '' }}">All</a>
                <a href="{{ route('super-admin.tickets.index', ['assigned' => 1]) }}" class="ticket-inbox__filter{{ request()->boolean('assigned') ? ' is-on' : '' }}">Assigned</a>
                <a href="{{ route('super-admin.tickets.index', ['unassigned' => 1]) }}" class="ticket-inbox__filter{{ request()->boolean('unassigned') ? ' is-on' : '' }}">Unassigned</a>
                <a href="{{ route('super-admin.tickets.index', ['mine' => 1]) }}" class="ticket-inbox__filter{{ request()->boolean('mine') ? ' is-on' : '' }}">Mine</a>
            </div>
            <label class="sr-only" for="sa-ticket-search">Search tickets</label>
            <input
                id="sa-ticket-search"
                type="search"
                x-model="q"
                placeholder="Search tickets"
                class="ticket-inbox__search"
            >
        </div>

        <div class="ticket-inbox__rows" role="list">
            @forelse ($ticketRows as $row)
                <a
                    href="{{ $row['href'] }}"
                    role="listitem"
                    class="ticket-inbox__row{{ $selectedId === $row['id'] ? ' is-active' : '' }}"
                    x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($row['subject'].' '.$row['number'].' '.$row['preview'].' '.$row['requester'])) }}.includes(q.toLowerCase())"
                >
                    <div class="ticket-inbox__row-top">
                        <p class="ticket-inbox__row-title">{{ $row['subject'] }}</p>
                        <span class="ticket-inbox__row-when">{{ $row['when'] }}</span>
                    </div>
                    <p class="ticket-inbox__row-preview">{{ $row['requester'] ?: ($row['preview'] ?: 'No messages yet') }}</p>
                    <div class="ticket-inbox__row-meta">
                        <span class="ticket-inbox__pill">{{ $row['number'] }}</span>
                        <span class="ticket-inbox__status is-{{ str_replace('_', '-', $row['status']) }}">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span>
                    </div>
                </a>
            @empty
                <p class="ticket-inbox__empty-list">No tickets in this view.</p>
            @endforelse
        </div>
    </aside>

    <section class="ticket-inbox__thread{{ $selected ? '' : ' ticket-inbox__thread--hidden-mobile' }}">
        @if ($selected)
            <header class="ticket-inbox__thread-head">
                <a href="{{ route('super-admin.tickets.index', $query) }}" class="ticket-inbox__back">← Tickets</a>
                <div class="min-w-0 flex-1">
                    <h1>{{ $selected->subject }}</h1>
                    <p>
                        {{ $selected->ticket_number ?: ('#'.$selected->id) }}
                        · {{ $selected->requester?->email ?? $selected->owner?->email ?? 'Customer' }}
                        · {{ ucfirst(str_replace('_', ' ', $selected->status)) }}
                    </p>
                </div>
            </header>

            @if (session('status'))
                <p class="ticket-inbox__flash">{{ session('status') }}</p>
            @endif

            <div class="ticket-inbox__admin-split">
                <div class="ticket-inbox__messages" id="ticket-inbox-messages">
                    @foreach ($thread as $msg)
                        <div class="ticket-inbox__bubble{{ $msg['is_agent'] ? ' is-agent' : ' is-you' }}">
                            <div class="ticket-inbox__bubble-meta">
                                <span>{{ $msg['is_agent'] ? $msg['name'] : ($msg['name'] ?: 'Customer') }}</span>
                                <span>{{ $msg['when'] }}</span>
                            </div>
                            <p>{{ $msg['body'] }}</p>
                        </div>
                    @endforeach
                </div>

                <aside class="ticket-inbox__assign">
                    <p class="ticket-inbox__assign-title">Assignment</p>
                    <form method="POST" action="{{ route('super-admin.tickets.assign', $selected) }}" class="ticket-inbox__assign-form">
                        @csrf
                        <label class="ticket-inbox__label" for="assigned_to_id">Assignee</label>
                        <select id="assigned_to_id" name="assigned_to_id" class="ticket-inbox__input">
                            <option value="">Unassigned</option>
                            @foreach ($assignees as $agent)
                                <option value="{{ $agent->id }}" @selected((int) $selected->assigned_to_id === (int) $agent->id)>
                                    {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                        <label class="ticket-inbox__label" for="department">Department</label>
                        <select id="department" name="department" class="ticket-inbox__input">
                            <option value="">—</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}" @selected(($selected->department ?? $selected->category) === $dept)>{{ ucfirst($dept) }}</option>
                            @endforeach
                        </select>
                        <label class="ticket-inbox__label" for="status">Status</label>
                        <select id="status" name="status" class="ticket-inbox__input">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($selected->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                        <label class="ticket-inbox__label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="ticket-inbox__input">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority }}" @selected($selected->priority === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="ticket-inbox__send w-full">Save</button>
                    </form>
                </aside>
            </div>

            <form method="POST" action="{{ route('super-admin.tickets.reply', $selected) }}" class="ticket-inbox__composer">
                @csrf
                <label class="sr-only" for="ticket-reply">Reply</label>
                <textarea id="ticket-reply" name="body" rows="2" required maxlength="10000" class="ticket-inbox__input ticket-inbox__textarea" placeholder="Reply as support…">{{ old('body') }}</textarea>
                <button type="submit" class="ticket-inbox__send">Send</button>
            </form>
        @else
            <div class="ticket-inbox__placeholder">
                <p>Select a ticket</p>
                <span>Assigned tickets stay in this chat. Open one to reply as support — Copilot is not part of this thread.</span>
            </div>
        @endif
    </section>
</div>
<script>
    (function () {
        const pane = document.getElementById('ticket-inbox-messages');
        if (pane) pane.scrollTop = pane.scrollHeight;
    })();
</script>
@endsection
