<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Support\StatusTone;
use App\Support\SupportTicketInbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TicketsController extends Controller
{
    /** @var list<string> */
    private array $departments = ['billing', 'support', 'account', 'verification', 'technical', 'integrations', 'other'];

    public function index(Request $request): RedirectResponse|View
    {
        // Support System default landing = Assignment board (not chat inbox).
        if (! $request->boolean('inbox')) {
            return redirect()->route('super-admin.tickets.queue', $request->query());
        }

        return $this->inbox($request);
    }

    public function queue(Request $request): View
    {
        $user = $request->user();
        $tickets = $this->baseTicketQuery($user)
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

        return view('super-admin.tickets.index', [
            'tickets' => $tickets,
            'stats' => $this->statsFor($user),
            'statuses' => ['open', 'assigned', 'in_progress', 'waiting', 'waiting_customer', 'escalated', 'resolved', 'closed'],
            'filterStatuses' => StatusTone::ticketFilters(),
            'priorities' => ['low', 'normal', 'high', 'urgent', 'emergency'],
            'departments' => $this->departmentsFor($user),
            'canClaimBalance' => true,
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->assertCanViewTicket($request->user(), $ticket);

        return $this->inbox($request, $ticket);
    }

    public function queueShow(Request $request, SupportTicket $ticket): View
    {
        $this->assertCanViewTicket($request->user(), $ticket);
        $ticket->load(['requester', 'assignee', 'owner', 'messages.author']);

        return view('super-admin.tickets.show', [
            'ticket' => $ticket,
            'assignees' => $this->assignees($request->user(), $ticket->department ?? $ticket->category),
            'departments' => $this->departmentsFor($request->user()),
            'statuses' => ['open', 'assigned', 'in_progress', 'waiting', 'waiting_customer', 'escalated', 'resolved', 'closed'],
            'priorities' => ['low', 'normal', 'high', 'urgent', 'emergency'],
            'canClaim' => $ticket->assigned_to_id === null && $this->agentCanWorkTicket($request->user(), $ticket),
        ]);
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanViewTicket($user, $ticket);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:40'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'department' => ['nullable', 'string', 'max:64'],
        ]);

        if (array_key_exists('assigned_to_id', $data)) {
            $assigneeId = $data['assigned_to_id'] ? (int) $data['assigned_to_id'] : null;
            if ($assigneeId !== null) {
                $allowed = $this->assignees($user, $data['department'] ?? $ticket->department ?? $ticket->category)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);
                abort_unless($allowed->contains($assigneeId), 422, 'Assignee must be on the matching team/department (or a super admin).');
            }
            $ticket->assigned_to_id = $assigneeId;
            if ($ticket->assigned_to_id && in_array($ticket->status, ['open', 'new', null, ''], true)) {
                $ticket->status = 'assigned';
            }
        }
        if (! empty($data['status'])) {
            abort_unless($this->agentCanEditFields($user), 403);
            $ticket->status = $data['status'];
        }
        if (! empty($data['priority'])) {
            abort_unless($this->agentCanEditFields($user), 403);
            $ticket->priority = $data['priority'] === 'emergency' ? 'urgent' : $data['priority'];
        }
        if (Schema::hasColumn('support_tickets', 'department') && array_key_exists('department', $data)) {
            $dept = $data['department'] ?: null;
            if ($dept !== null && ! ($user->is_super_admin ?? false)) {
                abort_unless(in_array($dept, $user->supportDepartmentSlugs(), true), 422, 'You can only set departments for your team.');
            }
            $ticket->department = $dept;
        }
        if (in_array($ticket->status, ['resolved', 'closed'], true) && Schema::hasColumn('support_tickets', 'closed_at')) {
            $ticket->closed_at = $ticket->closed_at ?: now();
        }
        if (Schema::hasColumn('support_tickets', 'ticket_number') && blank($ticket->ticket_number)) {
            $ticket->ticket_number = 'TKT-'.now()->format('Y').'-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT);
        }

        $ticket->save();

        return redirect()
            ->route($this->ticketReturnRoute($request, 'show'), $ticket)
            ->with('status', 'Ticket updated.');
    }

    /**
     * Claim an unassigned ticket from the department balance onto the current agent.
     */
    public function claim(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanViewTicket($user, $ticket);
        abort_unless($ticket->assigned_to_id === null, 422, 'Ticket is already assigned.');
        abort_unless($this->agentCanWorkTicket($user, $ticket), 403, 'This ticket is outside your team/department.');

        if (Schema::hasColumn('support_tickets', 'department')
            && blank($ticket->department)
            && ($slug = $user->supportDepartmentSlugs()[0] ?? null)) {
            $ticket->department = $slug;
        }

        $ticket->assigned_to_id = $user->id;
        if (in_array($ticket->status, ['open', 'new', null, ''], true)) {
            $ticket->status = 'assigned';
        }
        $ticket->save();

        return redirect()
            ->route($this->ticketReturnRoute($request, 'show'), $ticket)
            ->with('status', 'Ticket claimed from the unassigned balance.');
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->assertCanViewTicket($request->user(), $ticket);

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
        // Auto-claim when an unassigned agent replies from their department balance.
        if ($ticket->assigned_to_id === null && $this->agentCanWorkTicket($request->user(), $ticket)) {
            $ticket->assigned_to_id = $request->user()->id;
        }
        $ticket->save();

        return redirect()
            ->route($this->ticketReturnRoute($request, 'show'), $ticket)
            ->with('status', 'Reply sent.');
    }

    private function inbox(Request $request, ?SupportTicket $ticket = null): View
    {
        $user = $request->user();
        $tickets = $this->baseTicketQuery($user)
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
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to_id', $user->id))
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

        return view('super-admin.tickets.inbox', [
            'ticketRows' => SupportTicketInbox::rows($tickets, 'super-admin.tickets.show'),
            'selected' => $ticket,
            'thread' => $ticket ? SupportTicketInbox::thread($ticket) : [],
            'assignees' => $this->assignees($user, $ticket?->department ?? $ticket?->category),
            'stats' => $this->statsFor($user),
            'statuses' => ['open', 'assigned', 'in_progress', 'waiting', 'waiting_customer', 'escalated', 'resolved', 'closed'],
            'priorities' => ['low', 'normal', 'high', 'urgent', 'emergency'],
            'departments' => $this->departmentsFor($user),
            'canReply' => (bool) $ticket,
            'canClaim' => $ticket && $ticket->assigned_to_id === null && $this->agentCanWorkTicket($user, $ticket),
        ]);
    }

    private function ticketReturnRoute(Request $request, string $action): string
    {
        if ($request->input('return') === 'queue' || str_contains((string) $request->headers->get('referer'), '/tickets/queue')) {
            return $action === 'show' ? 'super-admin.tickets.queue.show' : 'super-admin.tickets.queue';
        }

        return $action === 'show' ? 'super-admin.tickets.show' : 'super-admin.tickets.index';
    }

    /**
     * Assignees = team/department staff (+ super admins). Optionally filter to a ticket department.
     *
     * @return Collection<int, User>
     */
    private function assignees(User $actor, ?string $department = null): Collection
    {
        $department = is_string($department) && $department !== '' ? $department : null;

        if (! Schema::hasTable('team_members')) {
            return User::query()
                ->where('is_super_admin', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $teamUserIds = DB::table('team_members')->distinct()->pluck('user_id');

        if ($department && Schema::hasTable('teams') && Schema::hasTable('departments')) {
            $deptIds = DB::table('team_members')
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->leftJoin('departments', 'departments.id', '=', 'teams.department_id')
                ->where(function ($q) use ($department): void {
                    $q->where('departments.slug', $department)
                        ->orWhereNull('teams.department_id');
                })
                ->when(
                    Schema::hasColumn('teams', 'is_active'),
                    fn ($q) => $q->where(function ($qq): void {
                        $qq->where('teams.is_active', true)->orWhereNull('teams.is_active');
                    })
                )
                ->distinct()
                ->pluck('team_members.user_id');

            // Prefer department teammates; if none, fall back to all team staff.
            if ($deptIds->isNotEmpty()) {
                $teamUserIds = $deptIds;
            }
        }

        // Non–super-admin agents may only assign within their own departments' staff (+ themselves).
        if (! ($actor->is_super_admin ?? false)) {
            $ownDepts = $actor->supportDepartmentSlugs();
            if ($ownDepts !== [] && Schema::hasTable('teams') && Schema::hasTable('departments')) {
                $scoped = DB::table('team_members')
                    ->join('teams', 'teams.id', '=', 'team_members.team_id')
                    ->join('departments', 'departments.id', '=', 'teams.department_id')
                    ->whereIn('departments.slug', $ownDepts)
                    ->distinct()
                    ->pluck('team_members.user_id');
                $teamUserIds = $scoped->isNotEmpty() ? $scoped : collect([$actor->id]);
            } else {
                $teamUserIds = collect([$actor->id])->merge(
                    DB::table('team_members')->where('user_id', $actor->id)->pluck('user_id')
                )->unique();
            }
        }

        return User::query()
            ->where(function ($q) use ($teamUserIds): void {
                $q->where('is_super_admin', true);
                if ($teamUserIds->isNotEmpty()) {
                    $q->orWhereIn('id', $teamUserIds);
                }
            })
            ->when(Schema::hasColumn('users', 'status'), function ($q): void {
                $q->where(function ($qq): void {
                    $qq->whereNull('status')->orWhereNotIn('status', ['suspended', 'banned']);
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function baseTicketQuery(User $user): Builder
    {
        $query = SupportTicket::query();

        if ($user->is_super_admin ?? false) {
            return $query;
        }

        $depts = $user->supportDepartmentSlugs();

        return $query->where(function (Builder $q) use ($user, $depts): void {
            $q->where('assigned_to_id', $user->id);

            if ($depts === []) {
                // Team without department mapping — still allow unassigned balance.
                $q->orWhereNull('assigned_to_id');

                return;
            }

            if (Schema::hasColumn('support_tickets', 'department')) {
                $q->orWhereIn('department', $depts)
                    ->orWhere(function (Builder $qq) use ($depts): void {
                        // Unassigned with no department yet — claimable by any desk agent.
                        $qq->whereNull('assigned_to_id')
                            ->where(function (Builder $q3) use ($depts): void {
                                $q3->whereNull('department')->orWhereIn('department', $depts);
                            });
                    });
            } else {
                $q->orWhereIn('category', $depts)
                    ->orWhereNull('assigned_to_id');
            }
        });
    }

    /**
     * @return array{total: int, open: int, unassigned: int, assigned: int, sla_breached?: int, overdue?: int}
     */
    private function statsFor(User $user): array
    {
        $base = $this->baseTicketQuery($user);

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereIn('status', ['open', 'new', 'assigned', 'in_progress'])->count(),
            'unassigned' => (clone $base)->whereNull('assigned_to_id')->whereNotIn('status', ['closed', 'resolved'])->count(),
            'assigned' => (clone $base)->whereNotNull('assigned_to_id')->count(),
            'sla_breached' => (clone $base)->where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->count(),
            'overdue' => (clone $base)->where('sla_due_at', '<', now())->whereNotIn('status', ['closed', 'resolved'])->whereIn('priority', ['urgent', 'emergency', 'high'])->count(),
        ];
    }

    /** @return list<string> */
    private function departmentsFor(User $user): array
    {
        if ($user->is_super_admin ?? false) {
            return $this->departments;
        }

        $own = $user->supportDepartmentSlugs();

        return $own !== [] ? array_values(array_intersect($this->departments, $own)) : $this->departments;
    }

    private function assertCanViewTicket(User $user, SupportTicket $ticket): void
    {
        if ($user->is_super_admin ?? false) {
            return;
        }

        abort_unless($this->agentCanWorkTicket($user, $ticket) || (int) $ticket->assigned_to_id === (int) $user->id, 403);
    }

    private function agentCanWorkTicket(User $user, SupportTicket $ticket): bool
    {
        if ($user->is_super_admin ?? false) {
            return true;
        }

        if (! $user->isSupportDeskStaff()) {
            return false;
        }

        if ((int) $ticket->assigned_to_id === (int) $user->id) {
            return true;
        }

        $depts = $user->supportDepartmentSlugs();
        $ticketDept = (string) ($ticket->department ?? $ticket->category ?? '');

        if ($ticketDept === '') {
            return $ticket->assigned_to_id === null;
        }

        return $depts === [] || in_array($ticketDept, $depts, true);
    }

    private function agentCanEditFields(User $user): bool
    {
        return (bool) ($user->is_super_admin ?? false) || $user->isSupportDeskStaff();
    }
}
