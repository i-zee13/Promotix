@extends('layouts.admin')

@section('title', 'Support')
@section('figma_shell_class', 'figma-hide-rightbar')
@section('rightbar')
@endsection

@section('content')
@php
    $selectedId = $selected?->id;
    $showThread = $selected || $composing;
@endphp
<div
    class="ticket-inbox min-h-[calc(100vh-49px)]"
    x-data="{ q: '' }"
>
    <aside class="ticket-inbox__list{{ $showThread ? ' ticket-inbox__list--hidden-mobile' : '' }}">
        <div class="ticket-inbox__list-head">
            <a href="{{ route('support-system.create') }}" class="ticket-inbox__new">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New ticket
            </a>
            <label class="sr-only" for="ticket-inbox-search">Search tickets</label>
            <input
                id="ticket-inbox-search"
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
                    x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($row['subject'].' '.$row['number'].' '.$row['preview'])) }}.includes(q.toLowerCase())"
                >
                    <div class="ticket-inbox__row-top">
                        <p class="ticket-inbox__row-title">{{ $row['subject'] }}</p>
                        <span class="ticket-inbox__row-when">{{ $row['when'] }}</span>
                    </div>
                    <p class="ticket-inbox__row-preview">{{ $row['preview'] ?: 'No messages yet' }}</p>
                    <div class="ticket-inbox__row-meta">
                        <span class="ticket-inbox__pill">{{ $row['number'] }}</span>
                        <span class="ticket-inbox__status is-{{ str_replace('_', '-', $row['status']) }}">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span>
                    </div>
                </a>
            @empty
                <p class="ticket-inbox__empty-list">No tickets yet. Start a new one.</p>
            @endforelse
        </div>
    </aside>

    <section class="ticket-inbox__thread{{ $showThread ? '' : ' ticket-inbox__thread--hidden-mobile' }}">
        @if ($composing)
            <header class="ticket-inbox__thread-head">
                <a href="{{ route('support-system') }}" class="ticket-inbox__back">← Tickets</a>
                <div>
                    <h1>New ticket</h1>
                    <p>Describe the issue. Replies from support will stay in this chat.</p>
                </div>
            </header>
            <form method="POST" action="{{ route('support-system.store') }}" class="ticket-inbox__compose-form">
                @csrf
                @if ($errors->any())
                    <div class="ticket-inbox__alert">
                        {{ $errors->first() }}
                    </div>
                @endif
                <label class="ticket-inbox__label" for="ticket-subject">Subject</label>
                <input id="ticket-subject" name="subject" type="text" required maxlength="200" value="{{ old('subject') }}" class="ticket-inbox__input" placeholder="Short summary">
                <label class="ticket-inbox__label" for="ticket-priority">Priority</label>
                <select id="ticket-priority" name="priority" class="ticket-inbox__input">
                    @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="ticket-inbox__label" for="ticket-body">Message</label>
                <textarea id="ticket-body" name="body" rows="8" required maxlength="10000" class="ticket-inbox__input ticket-inbox__textarea" placeholder="Write the first message…">{{ old('body') }}</textarea>
                <div class="ticket-inbox__compose-actions">
                    <a href="{{ route('support-system') }}" class="ticket-inbox__cancel">Cancel</a>
                    <button type="submit" class="ticket-inbox__send">Start ticket</button>
                </div>
            </form>
        @elseif ($selected)
            <header class="ticket-inbox__thread-head">
                <a href="{{ route('support-system') }}" class="ticket-inbox__back">← Tickets</a>
                <div class="min-w-0 flex-1">
                    <h1>{{ $selected->subject }}</h1>
                    <p>
                        {{ $selected->ticket_number ?: ('#'.$selected->id) }}
                        · {{ ucfirst(str_replace('_', ' ', $selected->status)) }}
                        · {{ ucfirst($selected->priority) }}
                    </p>
                </div>
            </header>

            @if (session('status'))
                <p class="ticket-inbox__flash">{{ session('status') }}</p>
            @endif

            <div class="ticket-inbox__messages" id="ticket-inbox-messages">
                @foreach ($thread as $msg)
                    <div class="ticket-inbox__bubble{{ $msg['is_agent'] ? ' is-agent' : ' is-you' }}">
                        <div class="ticket-inbox__bubble-meta">
                            <span>{{ $msg['is_agent'] ? $msg['name'] : 'You' }}</span>
                            <span>{{ $msg['when'] }}</span>
                        </div>
                        <p>{{ $msg['body'] }}</p>
                    </div>
                @endforeach
            </div>

            @if ($canReply)
                <form method="POST" action="{{ route('support-system.reply', $selected) }}" class="ticket-inbox__composer">
                    @csrf
                    <label class="sr-only" for="ticket-reply">Reply</label>
                    <textarea id="ticket-reply" name="body" rows="2" required maxlength="10000" class="ticket-inbox__input ticket-inbox__textarea" placeholder="Message support…">{{ old('body') }}</textarea>
                    <button type="submit" class="ticket-inbox__send">Send</button>
                </form>
            @else
                <p class="ticket-inbox__closed">This ticket is {{ $selected->status }}. Open a new ticket if you still need help.</p>
            @endif
        @else
            <div class="ticket-inbox__placeholder">
                <p>Select a ticket</p>
                <span>Your tickets stay on the left. Open one to see every reply in the same chat.</span>
                <a href="{{ route('support-system.create') }}">New ticket</a>
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
