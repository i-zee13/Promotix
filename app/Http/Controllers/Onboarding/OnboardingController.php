<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripeService;
use App\Services\Mail\AppMailer;
use App\Support\CardBrand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Post-signup onboarding: plan selection → add payment card → dashboard.
 */
class OnboardingController extends Controller
{
    public function plans(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->bypassesOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->activeSubscription()) {
            return $this->afterPlanRedirect($user);
        }

        $plans = Plan::query()
            ->where('is_active', true)
            ->whereIn('slug', ['starter', 'pro', 'advanced'])
            ->orderBy('sort_order')
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

        if ($user->bypassesOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

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

        $amountCents = match ($interval) {
            'yearly' => $plan->price_yearly_cents
                ? (int) round($plan->price_yearly_cents / 12)
                : (int) round($plan->price_cents * (1 - 0.15)),
            default => $plan->price_cents,
        };

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'is_trial' => true,
            'amount_cents' => $amountCents,
            'currency' => $plan->currency,
            'billing_interval' => $interval,
            'started_at' => now(),
            'trial_ends_at' => now()->addDays($days),
            'current_period_ends_at' => now()->addDays($days),
            'metadata' => ['source' => 'onboarding_plan_selection'],
        ]);

        return redirect()
            ->route('onboarding.payment')
            ->with('status', "Your {$days}-day free trial of {$plan->name} has started. Add a payment card to continue.");
    }

    public function payment(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->bypassesOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->activeSubscription()) {
            return redirect()->route('onboarding.plan');
        }

        if ($user->hasPaymentMethodOnFile()) {
            return redirect()->route('dashboard');
        }

        $subscription = $user->activeSubscription();
        $plan = $subscription?->plan;

        $stripeEnabled = StripeService::isConfigured();
        $setup = $stripeEnabled ? StripeService::createSetupIntent($user) : null;

        return view('onboarding.payment', [
            'user' => $user,
            'subscription' => $subscription,
            'plan' => $plan,
            'trialDays' => (int) app_setting('trial.days', 7),
            'stripeEnabled' => $stripeEnabled && $setup,
            'stripePublishableKey' => StripeService::publishableKey(),
            'setupIntentClientSecret' => $setup['client_secret'] ?? null,
            'verifyAmountCents' => StripeService::verifyAmountCents(),
        ]);
    }

    public function confirmStripePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'setup_intent_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->bypassesOnboarding()) {
            return response()->json(['redirect' => route($user->homeRouteName())]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email not verified.'], 422);
        }

        if (! $user->activeSubscription()) {
            return response()->json(['message' => 'Start a trial first.'], 422);
        }

        if ($user->hasPaymentMethodOnFile()) {
            return response()->json(['redirect' => route('dashboard')]);
        }

        $result = StripeService::attachAndVerify($user, $data['setup_intent_id']);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Card verification failed.'], 422);
        }

        PaymentMethod::query()->where('user_id', $user->id)->update(['is_primary' => false]);

        PaymentMethod::query()->create([
            'user_id' => $user->id,
            'label' => 'Primary card',
            'brand' => $result['brand'],
            'last_four' => $result['last_four'],
            'exp_month' => $result['exp_month'] ?? null,
            'exp_year' => $result['exp_year'] ?? null,
            'is_primary' => true,
            'is_temporary' => false,
            'stripe_payment_method_id' => $result['payment_method_id'],
            'verification_status' => 'verified_refunded',
            'verification_charge_id' => $result['charge_id'],
        ]);

        AppMailer::sendTemplate('payment_method_saved_email', $user->email, [
            '{{user_name}}' => $user->name ?: 'there',
            '{{card_brand}}' => $result['brand'],
            '{{last_four}}' => $result['last_four'],
            '{{billing_url}}' => url('/admin/billing'),
        ]);

        AppMailer::sendTemplate('welcome_email', $user->email, [
            '{{user_name}}' => $user->name ?: 'there',
        ]);

        return response()->json([
            'redirect' => route('dashboard'),
            'status' => "{$result['brand']} card •••• {$result['last_four']} verified.",
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'card_number' => ['required', 'string', 'min:12', 'max:19'],
            'exp_month' => ['required', 'string', 'size:2'],
            'exp_year' => ['required', 'string', 'min:2', 'max:4'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();

        if ($user->bypassesOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->activeSubscription()) {
            return redirect()->route('onboarding.plan');
        }

        if ($user->hasPaymentMethodOnFile()) {
            return redirect()->route('dashboard');
        }

        $digits = preg_replace('/\D/', '', $data['card_number']) ?: '';
        $lastFour = substr($digits, -4);
        $brand = CardBrand::detect($digits);

        PaymentMethod::query()->where('user_id', $user->id)->update(['is_primary' => false]);

        PaymentMethod::query()->create([
            'user_id' => $user->id,
            'label' => $data['label'] ?? 'Primary card',
            'brand' => $brand,
            'last_four' => $lastFour,
            'exp_month' => $data['exp_month'],
            'exp_year' => strlen($data['exp_year']) === 2 ? '20'.$data['exp_year'] : $data['exp_year'],
            'is_primary' => true,
            'is_temporary' => false,
            'verification_status' => 'saved_local',
        ]);

        AppMailer::sendTemplate('payment_method_saved_email', $user->email, [
            '{{user_name}}' => $user->name ?: 'there',
            '{{card_brand}}' => $brand,
            '{{last_four}}' => $lastFour,
            '{{billing_url}}' => url('/admin/billing'),
        ]);

        AppMailer::sendTemplate('welcome_email', $user->email, [
            '{{user_name}}' => $user->name ?: 'there',
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', "{$brand} card •••• {$lastFour} saved. Welcome to your dashboard.");
    }

    private function afterPlanRedirect($user): RedirectResponse
    {
        if ($user->bypassesOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

        if (! $user->hasPaymentMethodOnFile()) {
            return redirect()->route('onboarding.payment');
        }

        return redirect()->route('dashboard');
    }
}
