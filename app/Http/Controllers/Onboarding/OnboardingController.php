<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Post-signup onboarding: plan selection screen with the optional 7-day trial CTA.
 *
 * Gating:
 *   - User must be authenticated and have a verified email.
 *   - If they already have an active or trialing subscription, send them on to the dashboard.
 */
class OnboardingController extends Controller
{
    public function plans(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->activeSubscription()) {
            return $this->afterPlanRedirect($user);
        }

        $plans = Plan::query()
            ->where('is_active', true)
            ->whereIn('slug', ['starter', 'pro', 'advanced'])
            ->orderByRaw("FIELD(slug,'starter','pro','advanced')")
            ->get();

        $trialDays = (int) app_setting('trial.days', 7);

        return view('onboarding.plans', [
            'plans' => $plans,
            'trialDays' => $trialDays,
            'user' => $user,
        ]);
    }

    public function startTrial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'in:starter,pro,advanced'],
            'billing_interval' => ['nullable', 'in:monthly,yearly'],
        ]);

        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->activeSubscription()) {
            return $this->afterPlanRedirect($user);
        }

        $plan = Plan::query()
            ->where('slug', $data['plan_slug'])
            ->where('is_active', true)
            ->firstOrFail();

        $days = (int) app_setting('trial.days', 7);
        $interval = $data['billing_interval'] ?? $plan->billing_interval ?? 'monthly';

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'is_trial' => true,
            'amount_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'billing_interval' => $interval,
            'started_at' => now(),
            'trial_ends_at' => now()->addDays($days),
            'current_period_ends_at' => now()->addDays($days),
            'metadata' => ['source' => 'onboarding_plan_selection'],
        ]);

        return $this->afterPlanRedirect($user)->with('status', "Your {$days}-day free trial of {$plan->name} has started.");
    }

    private function afterPlanRedirect($user): RedirectResponse
    {
        if ($user->is_super_admin ?? false) {
            return redirect()->route('super-admin.dashboard');
        }

        if ($user->is_admin) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('home');
    }
}
