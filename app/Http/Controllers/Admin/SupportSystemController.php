<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportSystemController extends Controller
{
    public function index(Request $request)
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with(['requester:id,name,email', 'assignee:id,name,email'])
            ->latest('id')
            ->paginate(10);

        $rows = $tickets->getCollection()->map(fn (SupportTicket $ticket) => [
            'id' => (string) $ticket->id,
            'href' => route('support-system.show', $ticket),
            'name' => $ticket->subject,
            'email' => $ticket->requester?->email ?? $request->user()->email,
            'status' => ucfirst($ticket->status),
            'priority' => ucfirst($ticket->priority),
            'agent' => $ticket->assignee?->name ?? 'Unassigned',
            'last_update' => $ticket->updated_at?->diffForHumans() ?? '—',
            'sla' => $ticket->sla_due_at ? ($ticket->sla_due_at->isPast() ? 'Breached' : $ticket->sla_due_at->diffForHumans()) : 'No SLA',
        ])->all();

        $total = $tickets->total();
        $from = $tickets->firstItem() ?? 0;
        $to = $tickets->lastItem() ?? 0;
        $stats = [
            'total' => SupportTicket::query()->where('user_id', $request->user()->id)->count(),
            'open' => SupportTicket::query()->where('user_id', $request->user()->id)->where('status', 'open')->count(),
            'assigned' => SupportTicket::query()->where('user_id', $request->user()->id)->whereNotNull('assigned_to_id')->count(),
            'sla_breaches' => SupportTicket::query()->where('user_id', $request->user()->id)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->count(),
            'overdue' => SupportTicket::query()->where('user_id', $request->user()->id)->where('priority', 'urgent')->whereNotIn('status', ['closed', 'resolved'])->count(),
        ];
        $statusClasses = [
            'Open' => 'bg-green-600 text-white',
            'Waiting' => 'bg-yellow-600 text-white',
            'Resolved' => 'bg-gray-600 text-white',
            'Closed' => 'bg-gray-600 text-white',
            'Escalated' => 'bg-red-600 text-white',
        ];
        $priorityClasses = [
            'High' => 'bg-red-600 text-white',
            'Medium' => 'bg-yellow-600 text-white',
            'Low' => 'bg-green-700 text-white',
            'Urgent' => 'bg-red-700 text-white',
        ];

        return view('support-system', compact('rows', 'total', 'from', 'to', 'stats', 'statusClasses', 'priorityClasses'));
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $ticket->load(['messages.user:id,name,email', 'requester:id,name,email', 'assignee:id,name,email']);
        $agents = User::query()
            ->where('is_admin', true)
            ->orWhere('is_super_admin', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('support-ticket-detail', compact('ticket', 'agents'));
    }

    public function create(Request $request): View
    {
        $agents = User::query()
            ->where('is_admin', true)
            ->orWhere('is_super_admin', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('support-ticket-create', compact('agents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'category' => ['nullable', 'string', 'max:80'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'requester_email' => ['nullable', 'email', 'max:200'],
            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $requester = null;
        if (! empty($data['requester_email'])) {
            $requester = User::query()->where('email', $data['requester_email'])->first();
        }

        $ticket = SupportTicket::query()->create([
            'user_id' => $request->user()->id,
            'requester_id' => $requester?->id ?? $request->user()->id,
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => $data['assigned_to_id'] ? 'open' : 'open',
            'priority' => $data['priority'],
            'category' => $data['category'] ?? null,
            'sla_due_at' => isset($data['sla_hours']) ? now()->addHours((int) $data['sla_hours']) : now()->addHours(24),
        ]);

        return redirect()
            ->route('support-system.show', $ticket)
            ->with('status', 'Ticket created.');
    }
}
