<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\Mail\AppMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        if (User::query()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'A user with this email already exists.']);
        }

        $token = Str::random(48);
        $expiresAt = now()->addDays(14);
        $defaultRole = Role::query()->where('slug', 'default-user')->first();

        $invite = UserInvite::query()->updateOrCreate(
            ['email' => $data['email'], 'status' => 'pending'],
            [
                'invited_by_id' => $request->user()->id,
                'name' => $data['name'] ?? null,
                'role_id' => $defaultRole?->id,
                'plan_id' => null,
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
}
