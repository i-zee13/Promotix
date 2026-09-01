<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Team;
use App\Support\StatusTone;
use App\Models\RoleChange;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\Mail\AppMailer;
use App\Support\PortalTeamAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsersController extends Controller
{
    /** @var list<string> */
    private const TEAM_COLUMNS = [
        'Chat Support',
        'Support',
        'Sales Team',
        'Marketing',
        'Development',
        'Design Team',
    ];

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50], true)
            ? (int) $request->input('per_page')
            : 10;

        $tab = $request->string('tab')->toString() === 'teams' ? 'teams' : 'users';

        $baseQuery = User::query()
            ->with(['role', 'domains'])
            ->withCount('teamMembers')
            ->when(
                Schema::hasColumn('users', 'team_owner_id'),
                fn ($query) => $query->whereNull('team_owner_id')
            )
            ->when(
                $tab === 'teams',
                fn ($query) => $query->has('teamMembers')
            )
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), function ($query, string $status): void {
                $query->where('status', $this->resolveStatusFilter($status));
            })
            ->when($request->filled('verified'), function ($query) use ($request): void {
                if ($request->boolean('verified')) {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            })
            ->when($request->string('plan')->toString(), function ($query, string $planSlug): void {
                $query->whereHas('subscriptions', function ($q) use ($planSlug): void {
                    $q->whereIn('status', ['active', 'trialing'])
                        ->whereHas('plan', fn ($pq) => $pq->where('slug', $planSlug));
                });
            })
            ->when($request->string('role')->toString(), function ($query, string $role): void {
                if ($role === 'admin') {
                    $query->where('is_admin', true);

                    return;
                }
                if ($role === 'super-admin') {
                    $query->where('is_super_admin', true);

                    return;
                }
                $query->whereHas('role', fn ($rq) => $rq->where('slug', $role)->orWhere('id', $role));
            })
            ->when($request->filled('date'), fn ($query) => $query->whereDate('created_at', $request->date('date')));

        $users = (clone $baseQuery)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $userIds = $users->pluck('id')->all();
        $subscriptions = Subscription::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', ['active', 'trialing'])
            ->with('plan:id,name,slug,tier')
            ->orderByDesc('id')
            ->get()
            ->keyBy('user_id');

        $users->getCollection()->transform(function (User $user) use ($subscriptions) {
            $sub = $subscriptions->get($user->id);
            $user->current_plan_name = $sub?->plan?->name;
            $user->current_plan_tier = $sub?->plan?->tier;
            $user->current_plan_slug = $sub?->plan?->slug;
            $user->subscription_status = $sub?->status;
            $user->is_trialing = (bool) ($sub?->is_trial && $sub->trial_ends_at && $sub->trial_ends_at->isFuture());

            return $user;
        });

        // Teams board: ONLY admin-assigned team_members. Never auto-bucket users by id/role.
        $teamsBoard = collect();
        $teamColumns = self::TEAM_COLUMNS;
        $assignableTeams = collect();
        if (Schema::hasTable('teams')) {
            $teams = Team::query()
                ->with(['members' => fn ($q) => $q->with('role')->orderBy('name')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
            $assignableTeams = $teams;
            $teamsBoard = $teams->mapWithKeys(fn (Team $team) => [
                $team->name => $team->members,
            ]);
            $teamColumns = $teams->pluck('name')->all();
            if ($teamColumns === []) {
                $teamColumns = self::TEAM_COLUMNS;
                $teamsBoard = collect($teamColumns)->mapWithKeys(fn (string $c) => [$c => collect()]);
            }
        } else {
            $teamsBoard = collect(self::TEAM_COLUMNS)->mapWithKeys(fn (string $c) => [$c => collect()]);
        }

        $filterStatuses = StatusTone::userFilters();

        $workspaceOwners = Schema::hasColumn('users', 'team_owner_id')
            ? User::query()
                ->whereNull('team_owner_id')
                ->where('is_super_admin', false)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();

        return view('super-admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'teamRoles' => PortalTeamAccess::teamRoles(),
            'statuses' => ['active', 'suspended', 'pending', 'banned'],
            'filterStatuses' => $filterStatuses,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'tab' => $tab,
            'teamsBoard' => $teamsBoard,
            'teamColumns' => $teamColumns,
            'assignableTeams' => $assignableTeams,
            'workspaceOwners' => $workspaceOwners,
            'perPage' => $perPage,
        ]);
    }

    private function resolveStatusFilter(string $status): string
    {
        return match ($status) {
            'blocked' => 'banned',
            'deactivated' => 'suspended',
            'expiry' => 'pending',
            default => $status,
        };
    }

    public function teamMembersJson(User $user): JsonResponse
    {
        $members = User::query()
            ->where('team_owner_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return response()->json([
            'owner' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'count' => $members->count(),
            'members' => $members->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'status' => $member->status ?? 'active',
            ])->values(),
        ]);
    }

    public function assignTeam(Request $request, User $user): RedirectResponse
    {
        abort_unless(Schema::hasTable('team_members') && Schema::hasTable('teams'), 404);

        $data = $request->validate([
            'team_id' => ['nullable', 'exists:teams,id'],
            'action' => ['nullable', 'in:assign,remove'],
        ]);

        $action = $data['action'] ?? 'assign';
        if ($action === 'remove' || empty($data['team_id'])) {
            if (! empty($data['team_id'])) {
                DB::table('team_members')->where('team_id', $data['team_id'])->where('user_id', $user->id)->delete();
            } else {
                DB::table('team_members')->where('user_id', $user->id)->delete();
            }

            return back()->with('status', 'Team assignment removed. User stays unassigned until admin assigns again.');
        }

        DB::table('team_members')->updateOrInsert(
            ['team_id' => (int) $data['team_id'], 'user_id' => $user->id],
            [
                'assigned_by' => $request->user()->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('status', 'User assigned to team by admin.');
    }

    public function updateWorkspaceRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->team_owner_id !== null, 403, 'Only workspace owners can have their role set here.');

        $data = $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $oldRoleId = $user->role_id;
        $user->update(['role_id' => $data['role_id'] ?? null]);

        if ($oldRoleId !== $user->role_id) {
            RoleChange::query()->create([
                'user_id' => $user->id,
                'old_role_id' => $oldRoleId,
                'new_role_id' => $user->role_id,
                'changed_by_id' => $request->user()->id,
            ]);
        }

        return back()->with('status', 'Workspace owner role updated.');
    }

    public function updatePortalMemberRole(Request $request, User $user, User $member): RedirectResponse
    {
        abort_unless(
            Schema::hasColumn('users', 'team_owner_id') && $member->team_owner_id === $user->id,
            404
        );

        $teamRoleIds = PortalTeamAccess::teamRoles()->pluck('id')->all();

        $data = $request->validate([
            'role_id' => ['required', Rule::in($teamRoleIds)],
        ]);

        $oldRoleId = $member->role_id;
        $member->update(['role_id' => $data['role_id']]);

        if ($oldRoleId !== $member->role_id) {
            RoleChange::query()->create([
                'user_id' => $member->id,
                'old_role_id' => $oldRoleId,
                'new_role_id' => $member->role_id,
                'changed_by_id' => $request->user()->id,
            ]);
        }

        return back()->with('status', "Role updated for {$member->email}.");
    }

    public function removePortalMember(Request $request, User $user, User $member): RedirectResponse
    {
        abort_unless(
            Schema::hasColumn('users', 'team_owner_id') && $member->team_owner_id === $user->id,
            404
        );

        if ($member->is_super_admin) {
            return back()->withErrors(['member' => 'Cannot remove a super admin from a workspace.']);
        }

        $email = $member->email;
        $member->delete();

        return back()->with('status', "Removed portal user {$email} from this workspace.");
    }

    public function transferOwnership(Request $request, User $user): RedirectResponse
    {
        abort_unless(
            Schema::hasColumn('users', 'team_owner_id') && $user->team_owner_id === null,
            403,
            'Only workspace owners can transfer ownership.'
        );

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'exists:users,email'],
        ]);

        $newOwner = User::query()->where('email', $data['email'])->firstOrFail();

        if ($newOwner->id === $user->id) {
            return back()->withErrors(['email' => 'That email already belongs to the current owner.']);
        }

        if ($newOwner->is_super_admin) {
            return back()->withErrors(['email' => 'Cannot transfer ownership to a super admin account.']);
        }

        if ($newOwner->team_owner_id === null && $newOwner->teamMembers()->exists()) {
            return back()->withErrors(['email' => 'That user already owns another workspace with members.']);
        }

        DB::transaction(function () use ($user, $newOwner): void {
            User::query()
                ->where('team_owner_id', $user->id)
                ->update(['team_owner_id' => $newOwner->id]);

            $user->update(['team_owner_id' => $newOwner->id]);

            $newOwner->update(['team_owner_id' => null]);

            if (Schema::hasTable('domains')) {
                DB::table('domains')->where('user_id', $user->id)->update(['user_id' => $newOwner->id]);
            }

            if (Schema::hasTable('subscriptions')) {
                DB::table('subscriptions')->where('user_id', $user->id)->update(['user_id' => $newOwner->id]);
            }

            if (Schema::hasTable('google_connections')) {
                DB::table('google_connections')->where('user_id', $user->id)->update(['user_id' => $newOwner->id]);
            }

            if (! $newOwner->stripe_customer_id && $user->stripe_customer_id) {
                $newOwner->update(['stripe_customer_id' => $user->stripe_customer_id]);
                $user->update(['stripe_customer_id' => null]);
            }
        });

        return redirect()
            ->route('super-admin.users.show', $newOwner)
            ->with('status', "Workspace ownership transferred to {$newOwner->email}. Domains, subscription, and members moved to the new owner.");
    }

    public function show(User $user): View
    {
        $user->load([
            'role',
            'domains',
            'paymentMethods',
            'roleChanges.oldRole',
            'roleChanges.newRole',
            'roleChanges.changedBy',
            'loginHistories' => fn ($q) => $q->limit(25),
            'teams' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
        ]);

        $assignablePlans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $pendingInvites = UserInvite::query()
            ->where('status', 'pending')
            ->when(
                Schema::hasColumn('user_invites', 'invited_by_id'),
                fn ($q) => $q->where(function ($qq) use ($user): void {
                    $qq->where('invited_by_id', $user->id)->orWhereNull('invited_by_id');
                })
            )
            ->latest('id')
            ->limit(20)
            ->get();

        $portalUsers = User::query()
            ->when(
                Schema::hasColumn('users', 'team_owner_id'),
                fn ($q) => $q->where('team_owner_id', $user->id),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->with(['role:id,name,slug', 'teams' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'status', 'created_at', 'last_login_at']);

        $userTeams = $user->teams;
        $assignedByIds = $userTeams->pluck('pivot.assigned_by')->filter()->unique()->values()->all();
        $assignedByUsers = $assignedByIds !== []
            ? User::query()->whereIn('id', $assignedByIds)->get()->keyBy('id')
            : collect();

        $assignableTeams = Schema::hasTable('teams')
            ? Team::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $primaryPaymentMethod = $user->paymentMethods->firstWhere('is_primary', true)
            ?? $user->paymentMethods->first();

        $activeSubscription = $user->activeSubscription();

        return view('super-admin.users.show', [
            'user' => $user,
            'assignablePlans' => $assignablePlans,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'roles' => Role::orderBy('name')->get(),
            'teamRoles' => PortalTeamAccess::teamRoles(),
            'pendingInvites' => $pendingInvites,
            'portalUsers' => $portalUsers,
            'userTeams' => $userTeams,
            'assignedByUsers' => $assignedByUsers,
            'assignableTeams' => $assignableTeams,
            'primaryPaymentMethod' => $primaryPaymentMethod,
            'activeSubscription' => $activeSubscription,
            'isWorkspaceOwner' => Schema::hasColumn('users', 'team_owner_id') && $user->team_owner_id === null,
            'profileLanguage' => data_get($user->ui_preferences, 'language', 'English'),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $workspaceOwner = $this->resolveWorkspaceOwner($request->input('workspace_owner_id'));
        $teamRoleIds = PortalTeamAccess::teamRoles()->pluck('id')->all();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'workspace_owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($request->filled('workspace_owner_id') && ! $workspaceOwner) {
            return back()->withErrors(['workspace_owner_id' => 'Choose a valid workspace owner.']);
        }

        if ($workspaceOwner && isset($data['role_id']) && ! in_array((int) $data['role_id'], $teamRoleIds, true)) {
            return back()->withErrors(['role_id' => 'Choose a valid portal team role.']);
        }

        if (User::query()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'A user with this email already exists.']);
        }

        $token = Str::random(48);
        $expiresAt = now()->addDays(14);
        $defaultTeamRoleId = $workspaceOwner ? (PortalTeamAccess::teamRoles()->first()?->id) : null;

        $invitePayload = [
            'invited_by_id' => $request->user()->id,
            'name' => $data['name'] ?? null,
            'role_id' => $data['role_id'] ?? $defaultTeamRoleId,
            'plan_id' => $workspaceOwner ? null : ($data['plan_id'] ?? null),
            'token' => $token,
            'expires_at' => $expiresAt,
        ];

        if (Schema::hasColumn('user_invites', 'team_owner_id') && $workspaceOwner) {
            $invitePayload['team_owner_id'] = $workspaceOwner->id;
        }

        $invite = UserInvite::query()->updateOrCreate(
            ['email' => $data['email'], 'status' => 'pending'],
            $invitePayload
        );

        $inviteUrl = route('register', [
            'invite' => $invite->token,
            'email' => $invite->email,
        ]);

        // Invite email is link-only — no subscription / plan card.
        $sent = AppMailer::sendTemplate('user_invite_email', $invite->email, [
            '{{user_name}}' => $invite->name ?: 'there',
            '{{invite_url}}' => $inviteUrl,
            '{{invite_expires}}' => $expiresAt->format('M j, Y'),
        ]);

        if (! AppMailer::mailIsConfigured()) {
            $redirect = $workspaceOwner
                ? redirect()->route('super-admin.users.index', ['tab' => 'teams'])
                : back();

            return $redirect->with('status', "Invite created for {$invite->email}, but mail is not configured. Share this link: {$inviteUrl}");
        }

        if (! $sent) {
            return back()
                ->withErrors([
                    'email' => 'Invite saved, but the email could not be sent. Gmail SMTP returned BadCredentials — use a Google App Password (not your normal password) in MAIL_PASSWORD, then run: php artisan config:clear',
                ])
                ->with('status', "Backup invite link (share manually): {$inviteUrl}");
        }

        $successRedirect = $workspaceOwner
            ? redirect()->route('super-admin.users.index', ['tab' => 'teams'])
            : back();

        return $successRedirect->with('status', "Invite email sent to {$invite->email}. If it is not in the inbox, check Spam/Promotions. Link: {$inviteUrl}");
    }

    private function resolveWorkspaceOwner(mixed $workspaceOwnerId): ?User
    {
        if (! $workspaceOwnerId || ! Schema::hasColumn('users', 'team_owner_id')) {
            return null;
        }

        $owner = User::query()->find((int) $workspaceOwnerId);

        if (! $owner || $owner->team_owner_id !== null) {
            return null;
        }

        return $owner;
    }

    /**
     * Create a user immediately (no invite email / no subscription card required).
     */
    public function store(Request $request): RedirectResponse
    {
        $workspaceOwner = $this->resolveWorkspaceOwner($request->input('workspace_owner_id'));
        $teamRoleIds = PortalTeamAccess::teamRoles()->pluck('id')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'status' => ['nullable', 'in:active,pending,suspended'],
            'workspace_owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($request->filled('workspace_owner_id') && ! $workspaceOwner) {
            return back()->withErrors(['workspace_owner_id' => 'Choose a valid workspace owner.']);
        }

        if ($workspaceOwner && isset($data['role_id']) && ! in_array((int) $data['role_id'], $teamRoleIds, true)) {
            return back()->withErrors(['role_id' => 'Choose a valid portal team role.']);
        }

        $defaultRole = $workspaceOwner
            ? PortalTeamAccess::teamRoles()->first()
            : Role::query()->where('slug', 'default-user')->first();
        $roleId = $data['role_id'] ?? $defaultRole?->id;

        $user = DB::transaction(function () use ($data, $roleId, $workspaceOwner) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => $roleId,
                'status' => $data['status'] ?? 'active',
                'is_admin' => false,
                'is_super_admin' => false,
            ];

            if ($workspaceOwner && Schema::hasColumn('users', 'team_owner_id')) {
                $payload['team_owner_id'] = $workspaceOwner->id;
            }

            $user = User::query()->create($payload);
            $user->forceFill(['email_verified_at' => now()])->save();

            // Optional plan attach — portal members inherit owner billing; skip when adding to workspace.
            if (! $workspaceOwner && ! empty($data['plan_id'])) {
                $plan = Plan::query()->whereKey($data['plan_id'])->where('is_active', true)->first();
                if ($plan) {
                    Subscription::query()->create([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'is_trial' => false,
                        'amount_cents' => (int) $plan->price_cents,
                        'currency' => $plan->currency,
                        'billing_interval' => $plan->billing_interval ?? 'monthly',
                        'started_at' => now(),
                        'trial_ends_at' => null,
                        'current_period_ends_at' => now()->addMonth(),
                        'metadata' => ['source' => 'super_admin_create_user'],
                    ]);
                }
            }

            return $user;
        });

        if ($workspaceOwner) {
            return redirect()
                ->route('super-admin.users.index', ['tab' => 'teams'])
                ->with('status', "Portal member {$user->email} created under {$workspaceOwner->name}.");
        }

        return redirect()
            ->route('super-admin.users.show', $user)
            ->with('status', "User {$user->email} created. They can sign in with the password you set (no invite / subscription email sent).");
    }

    public function assignPlan(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_interval' => ['nullable', 'in:monthly,yearly'],
        ]);

        $plan = Plan::query()->whereKey($data['plan_id'])->where('is_active', true)->firstOrFail();
        $interval = $data['billing_interval'] ?? $plan->billing_interval ?? 'monthly';

        $amountCents = match ($interval) {
            'yearly' => $plan->price_yearly_cents
                ? (int) round($plan->price_yearly_cents / 12)
                : (int) round($plan->price_cents * (1 - 0.15)),
            default => $plan->price_cents,
        };

        DB::transaction(function () use ($user, $plan, $interval, $amountCents): void {
            Subscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'is_trial' => false,
                'amount_cents' => $amountCents,
                'currency' => $plan->currency,
                'billing_interval' => $interval,
                'started_at' => now(),
                'trial_ends_at' => null,
                'current_period_ends_at' => $interval === 'yearly' ? now()->addYear() : now()->addMonth(),
                'metadata' => ['source' => 'super_admin_assign_plan'],
            ]);
        });

        return redirect()
            ->route('super-admin.users.show', $user)
            ->with('status', "Assigned plan “{$plan->name}”.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'status' => ['required', 'in:active,suspended,pending,banned'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'is_admin' => ['nullable', 'boolean'],
            'is_super_admin' => ['nullable', 'boolean'],
        ]);

        $oldRoleId = $user->role_id;

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
            'role_id' => $data['role_id'] ?? null,
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'is_super_admin' => (bool) ($data['is_super_admin'] ?? false),
        ]);

        $newRoleId = $user->role_id;
        if ($oldRoleId !== $newRoleId) {
            RoleChange::query()->create([
                'user_id' => $user->id,
                'old_role_id' => $oldRoleId,
                'new_role_id' => $newRoleId,
                'changed_by_id' => $request->user()->id,
            ]);
        }

        return back()->with('status', 'User updated.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended,pending,banned']]);
        $user->update(['status' => $data['status']]);

        return back()->with('status', 'User status updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $newPassword = Str::random(14);
        $user->update(['password' => Hash::make($newPassword)]);

        return back()->with('status', "Password reset for {$user->email}. Temporary password: {$newPassword}");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot remove yourself.']);
        }
        $user->delete();

        return back()->with('status', 'User removed.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You are already this user.']);
        }
        if ($user->is_super_admin) {
            return back()->withErrors(['user' => 'You cannot impersonate another super admin.']);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);
        $request->session()->put('auth.two_factor_passed', true);

        return redirect()
            ->route($user->homeRouteName())
            ->with('status', "Now signed in as {$user->email}.");
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull('impersonator_id');
        if (! $originalId) {
            return redirect()->route('dashboard');
        }
        $original = User::query()->find($originalId);
        if (! $original) {
            return redirect()->route('dashboard');
        }
        Auth::login($original);
        $request->session()->put('auth.two_factor_passed', true);

        return redirect()->route('super-admin.users.index')->with('status', 'Stopped impersonating.');
    }
}
