<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Support\SupportTicketInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SupportSystemController extends Controller
{
    public function index(Request $request): View
    {
        return $this->inbox($request);
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->assertOwner($request, $ticket);

        return $this->inbox($request, $ticket);
    }

    public function create(Request $request): View
    {
        return $this->inbox($request, composing: true);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
        ]);

        $ticket = SupportTicket::query()->create([
            'user_id' => $request->user()->id,
            'requester_id' => $request->user()->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => 'open',
            'priority' => $data['priority'] ?? 'medium',
            'category' => 'support',
            'sla_due_at' => now()->addHours(24),
        ]);

        if (Schema::hasColumn('support_tickets', 'ticket_number') && blank($ticket->ticket_number)) {
            $ticket->ticket_number = 'TKT-'.now()->format('Y').'-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT);
        }
        if (Schema::hasColumn('support_tickets', 'department')) {
            $ticket->department = 'support';
        }
        if (Schema::hasColumn('support_tickets', 'source')) {
            $ticket->source = 'support_system';
        }
        $ticket->save();

        return redirect()
            ->route('support-system.show', $ticket)
            ->with('status', 'Ticket created.');
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->assertOwner($request, $ticket);

        abort_if(in_array($ticket->status, ['closed', 'resolved'], true), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_agent_reply' => false,
        ]);

        $ticket->forceFill(['status' => 'open'])->save();

        return redirect()
            ->route('support-system.show', $ticket)
            ->with('status', 'Reply sent.');
    }

    private function inbox(Request $request, ?SupportTicket $ticket = null, bool $composing = false): View
    {
        $user = $request->user();

        $tickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->with(['requester:id,name,email', 'owner:id,name,email'])
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return view('support-system', [
            'ticketRows' => SupportTicketInbox::rows($tickets, 'support-system.show'),
            'selected' => $ticket,
            'thread' => $ticket ? SupportTicketInbox::thread($ticket) : [],
            'composing' => $composing,
            'canReply' => $ticket && SupportTicketInbox::canCustomerReply($ticket),
        ]);
    }

    private function assertOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
    }
}
