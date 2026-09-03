<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Support\SupportTicketInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TicketsController extends Controller
{
    public function index(Request $request): View
    {
        return $this->inbox($request);
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        return $this->inbox($request, $ticket);
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:40'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'department' => ['nullable', 'string', 'max:64'],
        ]);

        if (array_key_exists('assigned_to_id', $data)) {
            $ticket->assigned_to_id = $data['assigned_to_id'] ?: null;
            if ($ticket->assigned_to_id && in_array($ticket->status, ['open', 'new', null, ''], true)) {
                $ticket->status = 'assigned';
            }
        }
        if (! empty($data['status'])) {
            $ticket->status = $data['status'];
        }
        if (! empty($data['priority'])) {
            $ticket->priority = $data['priority'] === 'emergency' ? 'urgent' : $data['priority'];
        }
        if (Schema::hasColumn('support_tickets', 'department') && array_key_exists('department', $data)) {
            $ticket->department = $data['department'] ?: null;
        }
        if (in_array($ticket->status, ['resolved', 'closed'], true) && Schema::hasColumn('support_tickets', 'closed_at')) {
            $ticket->closed_at = $ticket->closed_at ?: now();
        }
        if (Schema::hasColumn('support_tickets', 'ticket_number') && blank($ticket->ticket_number)) {
            $ticket->ticket_number = 'TKT-'.now()->format('Y').'-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT);
        }

        $ticket->save();

        return redirect()
            ->route('super-admin.tickets.show', $ticket)
            ->with('status', 'Ticket updated.');
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_agent_reply' => true,
        ]);

        if (Schema::hasColumn('support_tickets', 'first_response_at') && ! $ticket->first_response_at) {
            $ticket->first_response_at = now();
        }
        if (in_array($ticket->status, ['open', 'new', 'assigned'], true)) {
            $ticket->status = 'in_progress';
        }
        $ticket->save();

        return redirect()
            ->route('super-admin.tickets.show', $ticket)
            ->with('status', 'Reply sent.');
    }

    private function inbox(Request $request, ?SupportTicket $ticket = null): View
    {
        $tickets = SupportTicket::query()
            ->with(['requester:id,name,email', 'assignee:id,name,email', 'owner:id,name,email'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('priority')->toString(), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->string('department')->toString(), function ($q, $dept): void {
                if (Schema::hasColumn('support_tickets', 'department')) {
                    $q->where('department', $dept);
                } else {
                    $q->where('category', $dept);
                }
            })
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('assigned_to_id'))
            ->when($request->boolean('assigned'), fn ($q) => $q->whereNotNull('assigned_to_id'))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to_id', $request->user()->id))
            ->when($request->string('search')->toString(), function ($q, $term): void {
                $q->where(function ($qq) use ($term): void {
                    $qq->where('subject', 'like', "%{$term}%")
                        ->when(
                            Schema::hasColumn('support_tickets', 'ticket_number'),
                            fn ($q2) => $q2->orWhere('ticket_number', 'like', "%{$term}%")
                        )
                        ->orWhereHas('requester', fn ($u) => $u->where('email', 'like', "%{$term}%"));
                });
            })
            ->latest('updated_at')
            ->limit(300)
            ->get();

        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::whereIn('status', ['open', 'new', 'assigned', 'in_progress'])->count(),
            'unassigned' => SupportTicket::whereNull('assigned_to_id')->whereNotIn('status', ['closed', 'resolved'])->count(),
            'assigned' => SupportTicket::whereNotNull('assigned_to_id')->count(),
        ];

        return view('super-admin.tickets.inbox', [
            'ticketRows' => SupportTicketInbox::rows($tickets, 'super-admin.tickets.show'),
            'selected' => $ticket,
            'thread' => $ticket ? SupportTicketInbox::thread($ticket) : [],
            'assignees' => $this->assignees(),
            'stats' => $stats,
            'statuses' => ['open', 'assigned', 'in_progress', 'waiting', 'waiting_customer', 'escalated', 'resolved', 'closed'],
            'priorities' => ['low', 'normal', 'high', 'urgent', 'emergency'],
            'departments' => ['billing', 'support', 'account', 'verification', 'technical', 'integrations', 'other'],
            'canReply' => (bool) $ticket,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assignees()
    {
        return User::query()
            ->where(function ($q): void {
                $q->where('is_admin', true)->orWhere('is_super_admin', true);
            })
            ->when(Schema::hasTable('team_members'), function ($q): void {
                $teamUserIds = DB::table('team_members')->distinct()->pluck('user_id');
                if ($teamUserIds->isNotEmpty()) {
                    $q->whereIn('id', $teamUserIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
