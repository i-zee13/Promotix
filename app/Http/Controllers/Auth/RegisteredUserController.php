<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\Auth\VerificationCodeMailer;
use App\Services\LoginHistoryLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $invite = $this->findValidInvite($request->string('invite')->toString());

        return view('auth.register', [
            'invite' => $invite,
            'inviteToken' => $invite?->token,
            'inviteEmail' => $invite?->email ?: $request->string('email')->toString(),
            'inviteName' => $invite?->name,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name' => ['nullable', 'string', 'max:160'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'invite' => ['nullable', 'string', 'max:80'],
        ]);

        $invite = $this->findValidInvite((string) ($data['invite'] ?? ''));
        if ($invite && strcasecmp($invite->email, $data['email']) !== 0) {
            return back()
                ->withErrors(['email' => 'Use the invited email address to accept this invite.'])
                ->withInput();
        }

        $defaultRole = Role::where('slug', 'default-user')->first();
        $roleId = $invite?->role_id ?: $defaultRole?->id;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company_name' => $data['company_name'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'password' => Hash::make($data['password']),
            'role_id' => $roleId,
            // Customers must complete trial + payment onboarding (admins bypass those gates).
            'is_admin' => false,
        ]);

        if ($invite) {
            $this->acceptInvite($invite, $user);
        }

        Auth::login($user);

        // Send OTP in this same request — before redirect — so the inbox gets it immediately.
        [$devCode, $sent, $mailConfigured] = VerificationCodeMailer::issueAndSend($user);

        LoginHistoryLogger::record($user, $request, 'register');
        event(new Registered($user));

        $redirect = redirect()->route('verification.notice');

        if ($devCode !== null) {
            $redirect->with('dev_code', $devCode);
        }

        if ($sent) {
            $redirect->with('status', "We've sent a 6-digit verification code to {$user->email}.");
        } elseif (! $mailConfigured) {
            $redirect->with('status', 'Mail is not configured — use the dev code below (or configure SMTP).');
        } else {
            $redirect
                ->with('otp_send_failed', true)
                ->withErrors([
                    'email' => 'We could not send the verification email. We will retry automatically — or tap Resend code.',
                ]);
        }

        return $redirect;
    }

    private function findValidInvite(string $token): ?UserInvite
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return UserInvite::query()
            ->where('token', $token)
            ->where('status', 'pending')
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    private function acceptInvite(UserInvite $invite, User $user): void
    {
        DB::transaction(function () use ($invite, $user): void {
            $invite->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            $inviter = $invite->invitedBy;
            if ($invite->team_owner_id && Schema::hasColumn('users', 'team_owner_id')) {
                $user->update([
                    'team_owner_id' => $invite->team_owner_id,
                    'allowed_page_slugs' => $invite->page_slugs,
                    'allowed_domain_ids' => $invite->domain_ids,
                ]);
            } elseif ($inviter && ! $inviter->is_super_admin && ! $inviter->is_admin) {
                $user->update([
                    'team_owner_id' => $inviter->team_owner_id ?: $inviter->id,
                    'allowed_page_slugs' => $invite->page_slugs,
                    'allowed_domain_ids' => $invite->domain_ids,
                ]);
            }

            if ($invite->role_id) {
                $user->update(['role_id' => $invite->role_id]);
            }

            if (! $invite->plan_id) {
                return;
            }

            $plan = Plan::query()->whereKey($invite->plan_id)->where('is_active', true)->first();
            if (! $plan) {
                return;
            }

            $interval = $plan->billing_interval ?: 'monthly';
            $amountCents = $interval === 'yearly' && $plan->price_yearly_cents
                ? (int) round($plan->price_yearly_cents / 12)
                : (int) $plan->price_cents;

            Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'is_trial' => true,
                'amount_cents' => $amountCents,
                'currency' => $plan->currency ?: 'usd',
                'billing_interval' => $interval,
                'started_at' => now(),
                'trial_ends_at' => now()->addDays((int) app_setting('trial.days', 7)),
                'current_period_ends_at' => now()->addDays((int) app_setting('trial.days', 7)),
                'metadata' => ['source' => 'user_invite'],
            ]);
        });
    }
}
