@extends('layouts.super-admin')

@section('title', 'Support System')

@section('content')
<x-super-admin.page title="Support System">
    <div class="figma-sa-subs">
        <div class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-5">
            <article class="figma-sa-traffic-stat">
                <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                    <x-stat-icon name="ticket" class="h-[22px] w-[22px] text-amber-200" />
                </div>
                <p class="figma-sa-traffic-stat-label">Total Tickets</p>
                <p class="figma-sa-traffic-stat-value">{{ number_format($stats['total']) }}</p>
                <span class="figma-sa-traffic-stat-line" aria-hidden="true"></span>
            </article>
            <article class="figma-sa-traffic-stat">
                <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                    <x-stat-icon name="check-badge" class="h-[22px] w-[22px] text-emerald-200" />
                </div>
                <p class="figma-sa-traffic-stat-label">Open</p>
                <p class="figma-sa-traffic-stat-value">{{ number_format($stats['open']) }}</p>
                <span class="figma-sa-traffic-stat-line is-green" aria-hidden="true"></span>
            </article>
            <article class="figma-sa-traffic-stat">
                <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                    <x-stat-icon name="users" class="h-[22px] w-[22px] text-violet-100" />
                </div>
                <p class="figma-sa-traffic-stat-label">Assigned</p>
                <p class="figma-sa-traffic-stat-value">{{ number_format($stats['assigned']) }}</p>
                <span class="figma-sa-traffic-stat-line is-blue" aria-hidden="true"></span>
            </article>
            <article class="figma-sa-traffic-stat">
                <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                    <x-stat-icon name="alert-triangle" class="h-[22px] w-[22px] text-amber-200" />
                </div>
                <p class="figma-sa-traffic-stat-label">SLA Breaches</p>
                <p class="figma-sa-traffic-stat-value">{{ number_format($stats['sla_breached']) }}</p>
                <span class="figma-sa-traffic-stat-line is-red" aria-hidden="true"></span>
            </article>
            <article class="figma-sa-traffic-stat">
                <div class="figma-sa-traffic-stat-icon" aria-hidden="true">
                    <x-stat-icon name="hourglass" class="h-[22px] w-[22px] text-yellow-200" />
                </div>
                <p class="figma-sa-traffic-stat-label">Overdue</p>
                <p class="figma-sa-traffic-stat-value">{{ number_format($stats['overdue']) }}</p>
                <span class="figma-sa-traffic-stat-line is-yellow" aria-hidden="true"></span>
            </article>
        </div>

        <form method="GET" action="{{ route('super-admin.tickets.index') }}" class="figma-sa-subs-filters">
            <label class="figma-sa-subs-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search subject, number, or email" autocomplete="off">
            </label>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip">
                        <span>{{ request('priority') ? ucfirst(request('priority')) : 'All Priorities' }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <a href="{{ route('super-admin.tickets.index', array_merge(request()->except(['priority', 'page']), [])) }}" class="figma-sa-users-action-item">All Priorities</a>
                @foreach ($priorities as $priority)
                    <a href="{{ route('super-admin.tickets.index', array_merge(request()->except(['priority', 'page']), ['priority' => $priority])) }}" class="figma-sa-users-action-item">{{ ucfirst($priority) }}</a>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip figma-sa-subs-filter-chip--wide">
                        <span>{{ collect($filterStatuses)->firstWhere('value', request('status', ''))['label'] ?? 'All Statuses' }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                @foreach ($filterStatuses as $fs)
                    <a href="{{ route('super-admin.tickets.index', array_merge(request()->except(['status', 'page']), $fs['value'] !== '' ? ['status' => $fs['value']] : [])) }}"
                       class="figma-sa-users-filter-option block">
                        {{ $fs['label'] }}
                    </a>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip">
                        <span>{{ request('department') ? ucfirst(request('department')) : 'All Departments' }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <a href="{{ route('super-admin.tickets.index', array_merge(request()->except(['department', 'page']), [])) }}" class="figma-sa-users-action-item">All Departments</a>
                @foreach ($departments as $dept)
                    <a href="{{ route('super-admin.tickets.index', array_merge(request()->except(['department', 'page']), ['department' => $dept])) }}" class="figma-sa-users-action-item">{{ ucfirst($dept) }}</a>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            <button type="submit" class="figma-sa-btn figma-sa-btn-outline !px-4 !py-2 text-[13px]">Filter</button>
            <a href="{{ route('super-admin.tickets.index', array_merge(request()->except(['unassigned', 'page']), request()->boolean('unassigned') ? [] : ['unassigned' => 1])) }}"
               class="figma-sa-btn {{ request()->boolean('unassigned') ? 'figma-sa-btn-primary' : 'figma-sa-btn-outline' }} !px-4 !py-2 text-[13px]">
                Unassigned ({{ number_format($stats['unassigned'] ?? 0) }})
            </a>
        </form>

        <div class="figma-sa-subs-panel">
            <div class="figma-sa-subs-table-scroll">
                <table class="figma-sa-subs-table">
                    <thead>
                        <tr>
                            <th class="figma-sa-subs-th-check"><input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select all"></th>
                            <th>Ticket</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Agent</th>
                            <th>Last Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            @php
                                $pri = strtolower($ticket->priority ?? 'normal');
                                $priTone = \App\Support\StatusTone::ticketPriority($pri);
                                $statusTone = \App\Support\StatusTone::ticket($ticket->status ?? '');
                                $slaBreached = $ticket->sla_due_at && $ticket->sla_due_at->isPast() && ! in_array($ticket->status, ['closed', 'resolved'], true);
                            @endphp
                            <tr class="figma-sa-subs-row">
                                <td class="figma-sa-subs-td-check">
                                    <input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select ticket {{ $ticket->ticket_number ?? $ticket->id }}">
                                </td>
                                <td>
                                    <a href="{{ route('super-admin.tickets.show', $ticket) }}" class="figma-sa-subs-user">
                                        <span class="figma-sa-subs-avatar" aria-hidden="true">
                                            @include('partials.user-avatar', ['avatarUser' => $ticket->requester, 'avatarTextClass' => 'text-[12px] font-semibold leading-none text-[#FF6600]'])
                                        </span>
                                        <span class="figma-sa-subs-user-text">
                                            <span class="figma-sa-subs-user-name">#{{ $ticket->ticket_number ?? $ticket->id }} · {{ $ticket->subject }}</span>
                                            <span class="figma-sa-subs-user-email">{{ $ticket->requester?->name ?? 'Deleted user' }} · {{ $ticket->requester?->email }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td><x-super-admin.status-pill :tone="$priTone" :label="ucfirst($pri)" /></td>
                                <td><x-super-admin.status-pill :tone="$statusTone" :label="ucfirst(str_replace('_', ' ', $ticket->status ?? ''))" /></td>
                                <td>
                                    <span class="figma-sa-subs-plan-tier">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                                </td>
                                <td>
                                    <span class="figma-sa-subs-date">{{ $ticket->updated_at?->diffForHumans() }}</span>
                                    @if ($ticket->sla_due_at)
                                        <span class="figma-sa-subs-plan-detail" style="{{ $slaBreached ? 'color:#ff8686;' : '' }}">
                                            {{ $slaBreached ? 'SLA breached' : 'SLA '.$ticket->sla_due_at->diffForHumans(null, true).' left' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="figma-sa-subs-empty">No tickets yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination">
                <p class="figma-sa-subs-pagination-meta">
                    @if ($tickets->total())
                        Showing {{ $tickets->firstItem() }}–{{ $tickets->lastItem() }} of {{ $tickets->total() }}
                    @else
                        Showing 0 of 0
                    @endif
                </p>
                <div class="figma-sa-subs-pagination-controls">
                    <form method="GET" class="figma-sa-subs-perpage-form">
                        @foreach (request()->except(['per_page', 'page']) as $key => $val)
                            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                        @endforeach
                        <label class="sr-only" for="tickets-per-page">Rows per page</label>
                        <select id="tickets-per-page" name="per_page" class="figma-sa-subs-perpage-select" onchange="this.form.submit()">
                            @foreach ([10, 25, 50] as $n)
                                <option value="{{ $n }}" @selected(request()->integer('per_page', 10) === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($tickets->hasPages())
                        <div class="figma-sa-subs-page-btns">
                            @if ($tickets->onFirstPage())
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&lt;</span>
                            @else
                                <a href="{{ $tickets->previousPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Previous page">&lt;</a>
                            @endif
                            <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--current">{{ $tickets->currentPage() }}</span>
                            @if ($tickets->hasMorePages())
                                <a href="{{ $tickets->nextPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Next page">&gt;</a>
                            @else
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&gt;</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
