<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
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
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'open'    => SupportTicket::where('status', 'open')->count(),
            'waiting' => SupportTicket::where('status', 'waiting')->count(),
            'closed'  => SupportTicket::where('status', 'closed')->count(),
            'sla_breached' => SupportTicket::where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->count(),
        ];

        return view('super-admin.tickets.index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'statuses' => ['open', 'waiting', 'resolved', 'closed'],
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
