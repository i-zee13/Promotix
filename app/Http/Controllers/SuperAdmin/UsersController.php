<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Role;
use App\Support\StatusTone;
use App\Models\RoleChange;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\Mail\AppMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $teamUsers = (clone $baseQuery)->latest('id')->limit(120)->get();
        $teamsBoard = collect(self::TEAM_COLUMNS)->mapWithKeys(function (string $column) use ($teamUsers) {
            return [
                $column => $teamUsers->filter(fn (User $user) => $this->resolveTeamColumn($user) === $column)->values(),
            ];
        });

        $filterStatuses = StatusTone::userFilters();

        return view('super-admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'statuses' => ['active', 'suspended', 'pending', 'banned'],
            'filterStatuses' => $filterStatuses,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'tab' => $tab,
            'teamsBoard' => $teamsBoard,
            'teamColumns' => self::TEAM_COLUMNS,
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

    private function resolveTeamColumn(User $user): string
    {
        $roleName = strtolower((string) ($user->role?->name ?? ''));

        foreach (self::TEAM_COLUMNS as $column) {
            $needle = strtolower(str_replace(' ', '', $column));
            if ($roleName !== '' && str_contains(str_replace(' ', '', $roleName), $needle)) {
                return $column;
            }
        }

        return self::TEAM_COLUMNS[$user->id % count(self::TEAM_COLUMNS)];
    }

    public function show(User $user): View
    {
        $user->load([
            'role',
            'domains',
            'roleChanges.oldRole',
            'roleChanges.newRole',
            'roleChanges.changedBy',
            'loginHistories' => fn ($q) => $q->limit(25),
        ]);

        $assignablePlans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $pendingInvites = UserInvite::query()
            ->where('status', 'pending')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('super-admin.users.show', [
            'user' => $user,
            'assignablePlans' => $assignablePlans,
            'roles' => Role::orderBy('name')->get(),
            'pendingInvites' => $pendingInvites,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ]);

        if (User::query()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'A user with this email already exists.']);
        }

        $token = Str::random(48);
        $expiresAt = now()->addDays(14);

        $invite = UserInvite::query()->updateOrCreate(
            ['email' => $data['email'], 'status' => 'pending'],
            [
                'invited_by_id' => $request->user()->id,
                'name' => $data['name'] ?? null,
                'role_id' => $data['role_id'] ?? null,
                'plan_id' => $data['plan_id'] ?? null,
                'token' => $token,
                'expires_at' => $expiresAt,
            ]
        );

        $inviteUrl = route('register', [
            'invite' => $invite->token,
            'email' => $invite->email,
        ]);

        $sent = AppMailer::sendTemplate('user_invite_email', $invite->email, [
            '{{user_name}}' => $invite->name ?: 'there',
            '{{invite_url}}' => $inviteUrl,
            '{{invite_expires}}' => $expiresAt->format('M j, Y'),
        ]);

        if (! AppMailer::mailIsConfigured()) {
            return back()->with('status', "Invite created for {$invite->email}, but mail is not configured. Share this link: {$inviteUrl}");
        }

        if (! $sent) {
            return back()
                ->withErrors([
                    'email' => 'Invite saved, but the email could not be sent. Gmail SMTP returned BadCredentials — use a Google App Password (not your normal password) in MAIL_PASSWORD, then run: php artisan config:clear',
                ])
                ->with('status', "Backup invite link (share manually): {$inviteUrl}");
        }

        return back()->with('status', "Invite email sent to {$invite->email}. If it is not in the inbox, check Spam/Promotions. Link: {$inviteUrl}");
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

        return redirect()->route('super-admin.users.index')->with('status', 'Stopped impersonating.');
    }
}
