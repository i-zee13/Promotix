<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $defaultRole = Role::where('slug', 'default-user')->first();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $defaultRole?->id,
            'is_admin' => true,
        ]);

        $this->startTrialIfEnabled($user);

        event(new Registered($user));

        Auth::login($user);

        return redirect('/');
    }

    private function startTrialIfEnabled(User $user): void
    {
        if (! app_setting('trial.enabled', true)) {
            return;
        }

        $days = (int) app_setting('trial.days', 14);
        if ($days <= 0) {
            return;
        }

        $planSlug = (string) app_setting('trial.plan_slug', 'starter');
        $plan = Plan::query()->where('slug', $planSlug)->where('is_active', true)->first();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan?->id,
            'status' => 'trialing',
            'is_trial' => true,
            'amount_cents' => $plan?->price_cents ?? 0,
            'currency' => $plan?->currency ?? 'USD',
            'billing_interval' => $plan?->billing_interval ?? 'monthly',
            'started_at' => now(),
            'trial_ends_at' => now()->addDays($days),
            'current_period_ends_at' => now()->addDays($days),
            'metadata' => ['source' => 'auto_trial_on_signup'],
        ]);
    }
}
