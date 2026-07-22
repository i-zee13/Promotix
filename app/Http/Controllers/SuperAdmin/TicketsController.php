<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Support\StatusTone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketsController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with(['requester:id,name,email', 'assignee:id,name,email', 'owner:id,name,email'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('priority')->toString(), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->string('search')->toString(), function ($q, $term): void {
                $q->where(function ($qq) use ($term): void {
                    $qq->where('subject', 'like', "%{$term}%")
                       ->orWhere('ticket_number', 'like', "%{$term}%")
                       ->orWhereHas('requester', fn ($u) => $u->where('email', 'like', "%{$term}%"));
                });
            })
            ->latest('id')
            ->paginate(min(50, max(10, $request->integer('per_page', 10))))
            ->withQueryString();

        $stats = [
            'total'    => SupportTicket::count(),
            'open'     => SupportTicket::where('status', 'open')->count(),
            'assigned' => SupportTicket::whereNotNull('assigned_to_id')->count(),
            'sla_breached' => SupportTicket::where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->count(),
            'overdue'  => SupportTicket::where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->where('priority', 'urgent')->count(),
        ];

        $filterStatuses = StatusTone::ticketFilters();

        return view('super-admin.tickets.index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'statuses' => ['open', 'in_progress', 'waiting', 'resolved', 'closed'],
            'filterStatuses' => $filterStatuses,
            'priorities' => ['low', 'normal', 'high', 'urgent'],
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['requester', 'assignee', 'owner', 'messages.author']);

        return view('super-admin.tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status'   => ['nullable', 'in:open,waiting,resolved,closed'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        $ticket->fill(array_filter($data, fn ($v) => $v !== null && $v !== ''))->save();

        return back()->with('status', 'Ticket updated.');
    }
}
