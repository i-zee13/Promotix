<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\Mail\AppMailer;
use App\Support\PortalTeamAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with('role')->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(10)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return view('users', compact('users', 'roles'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $user->update(['role_id' => $request->input('role_id')]);

        return back()->with('status', 'Role updated for ' . $user->email . '.');
    }

    public function invite(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canInviteTeamMembers(), 403);

        $teamRoleIds = PortalTeamAccess::teamRoles()->pluck('id')->all();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', Rule::in($teamRoleIds)],
            'page_slugs' => ['nullable', 'array'],
            'page_slugs.*' => ['string', 'max:120'],
            'domain_ids' => ['nullable', 'array'],
            'domain_ids.*' => ['integer'],
        ]);

        if (User::query()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'A user with this email already exists.']);
        }

        $owner = PortalTeamAccess::workspaceOwner($request->user());
        $pageSlugs = PortalTeamAccess::normalizePageSlugs($data['page_slugs'] ?? null);
        $domainIds = PortalTeamAccess::normalizeDomainIds($owner, $data['domain_ids'] ?? null);
        $roleId = $data['role_id'] ?? PortalTeamAccess::teamRoles()->first()?->id;

        $token = Str::random(48);
        $expiresAt = now()->addDays(14);

        $invite = UserInvite::query()->updateOrCreate(
            ['email' => $data['email'], 'status' => 'pending'],
            [
                'invited_by_id' => $request->user()->id,
                'name' => $data['name'] ?? null,
                'role_id' => $roleId,
                'plan_id' => null,
                'page_slugs' => $pageSlugs,
                'domain_ids' => $domainIds,
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

        if (! AppMailer::mailIsConfigured() || ! $sent) {
            return back()->with('status', "Invite created for {$invite->email}. Share this link: {$inviteUrl}");
        }

        return back()->with('status', "Invite email sent to {$invite->email}.");
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canInviteTeamMembers(), 403);

        $teamRoleIds = PortalTeamAccess::teamRoles()->pluck('id')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role_id' => ['nullable', Rule::in($teamRoleIds)],
            'page_slugs' => ['nullable', 'array'],
            'page_slugs.*' => ['string', 'max:120'],
            'domain_ids' => ['nullable', 'array'],
            'domain_ids.*' => ['integer'],
        ]);

        $owner = PortalTeamAccess::workspaceOwner($request->user());
        $roleId = $data['role_id'] ?? PortalTeamAccess::teamRoles()->first()?->id;
        $pageSlugs = PortalTeamAccess::normalizePageSlugs($data['page_slugs'] ?? null);
        $domainIds = PortalTeamAccess::normalizeDomainIds($owner, $data['domain_ids'] ?? null);

        DB::transaction(function () use ($data, $request, $owner, $roleId, $pageSlugs, $domainIds): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => $roleId,
                'status' => 'active',
                'is_admin' => false,
                'is_super_admin' => false,
                'team_owner_id' => $owner->id,
                'allowed_page_slugs' => $pageSlugs,
                'allowed_domain_ids' => $domainIds,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        });

        return back()->with('status', "User {$data['email']} created and added to your workspace.");
    }
}
