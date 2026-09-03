<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SupportSystemController extends Controller
{
    public function index(): RedirectResponse
    {
        return $this->toCopilot();
    }

    public function show(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->assertOwner($request, $ticket);

        return $this->toCopilot($ticket->id);
    }

    public function create(): RedirectResponse
    {
        return $this->toCopilot();
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

        return $this->toCopilot($ticket->id);
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

        return $this->toCopilot($ticket->id);
    }

    private function toCopilot(?int $ticketId = null): RedirectResponse
    {
        return redirect()->route('dashboard', array_filter([
            'open_copilot' => 1,
            'ticket' => $ticketId,
        ]));
    }

    private function assertOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
    }
}
