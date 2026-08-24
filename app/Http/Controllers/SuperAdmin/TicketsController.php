<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Support\StatusTone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TicketsController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with(['requester:id,name,email,avatar_path,google_avatar_url', 'assignee:id,name,email', 'owner:id,name,email'])
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
            ->latest('id')
            ->paginate(min(50, max(10, $request->integer('per_page', 10))))
            ->withQueryString();

        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::whereIn('status', ['open', 'new', 'assigned', 'in_progress'])->count(),
            'unassigned' => SupportTicket::whereNull('assigned_to_id')->whereNotIn('status', ['closed', 'resolved'])->count(),
            'assigned' => SupportTicket::whereNotNull('assigned_to_id')->count(),
            'sla_breached' => SupportTicket::where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->count(),
            'overdue' => SupportTicket::where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->whereIn('priority', ['urgent', 'emergency', 'high'])->count(),
        ];

        return view('super-admin.tickets.index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'statuses' => ['open', 'assigned', 'in_progress', 'waiting', 'waiting_customer', 'escalated', 'resolved', 'closed'],
            'filterStatuses' => StatusTone::ticketFilters(),
            'priorities' => ['low', 'normal', 'high', 'urgent', 'emergency'],
            'departments' => ['billing', 'support', 'account', 'verification', 'technical', 'integrations', 'other'],
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['requester', 'assignee', 'owner', 'messages.author']);

        $assignees = User::query()
            ->where(function ($q): void {
                $q->where('is_admin', true)->orWhere('is_super_admin', true);
            })
            ->when(Schema::hasTable('team_members'), function ($q): void {
                // Prefer agents who are on an operational team; still show all admins if none assigned yet.
                $teamUserIds = DB::table('team_members')->distinct()->pluck('user_id');
                if ($teamUserIds->isNotEmpty()) {
                    $q->whereIn('id', $teamUserIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('super-admin.tickets.show', [
            'ticket' => $ticket,
            'assignees' => $assignees,
            'departments' => ['billing', 'support', 'account', 'verification', 'technical', 'integrations', 'other'],
            'statuses' => ['open', 'assigned', 'in_progress', 'waiting', 'waiting_customer', 'escalated', 'resolved', 'closed'],
            'priorities' => ['low', 'normal', 'high', 'urgent', 'emergency'],
        ]);
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

        return back()->with('status', 'Ticket updated.');
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

        return back()->with('status', 'Reply sent.');
    }
}
