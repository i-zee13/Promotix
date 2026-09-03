<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
            ->latest('updated_at')
            ->limit(200)
            ->get();

        $lastBodies = SupportTicketMessage::query()
            ->select('support_ticket_id', 'body')
            ->whereIn('support_ticket_id', $tickets->pluck('id')->filter())
            ->whereIn('id', function ($query): void {
                $query->selectRaw('max(id)')
                    ->from('support_ticket_messages')
                    ->groupBy('support_ticket_id');
            })
            ->pluck('body', 'support_ticket_id');

        $ticketRows = $tickets->map(function (SupportTicket $item) use ($lastBodies) {
            $preview = $lastBodies[$item->id] ?? $item->body ?? '';

            return [
                'id' => $item->id,
                'href' => route('support-system.show', $item),
                'subject' => $item->subject,
                'number' => $item->ticket_number ?: ('#'.$item->id),
                'preview' => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $preview) ?? ''), 80),
                'status' => $item->status,
                'when' => ($item->updated_at ?? $item->created_at)?->diffForHumans() ?? '',
            ];
        })->all();

        $thread = [];
        if ($ticket) {
            $ticket->load(['messages.user:id,name,email', 'requester:id,name,email']);

            if (filled($ticket->body)) {
                $thread[] = [
                    'id' => 'opening',
                    'body' => $ticket->body,
                    'name' => $ticket->requester?->name ?? $user->name,
                    'when' => $ticket->created_at?->diffForHumans() ?? '',
                    'is_agent' => false,
                ];
            }

            foreach ($ticket->messages as $message) {
                $thread[] = [
                    'id' => $message->id,
                    'body' => $message->body,
                    'name' => $message->user?->name ?? ($message->is_agent_reply ? 'Support' : $user->name),
                    'when' => $message->created_at?->diffForHumans() ?? '',
                    'is_agent' => (bool) $message->is_agent_reply,
                ];
            }
        }

        return view('support-system', [
            'ticketRows' => $ticketRows,
            'selected' => $ticket,
            'thread' => $thread,
            'composing' => $composing,
            'canReply' => $ticket && ! in_array($ticket->status, ['closed', 'resolved'], true),
        ]);
    }

    private function assertOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
    }
}
