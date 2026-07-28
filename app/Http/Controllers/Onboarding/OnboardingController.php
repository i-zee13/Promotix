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
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Post-signup onboarding: plan selection → Stripe Checkout (or local card) → dashboard.
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $trialDays = (int) app_setting('trial.days', 7);

        return view('onboarding.plans', [
            'plans' => $plans,
            'trialDays' => $trialDays,
            'user' => $user,
            'stripeEnabled' => StripeService::isConfigured(),
        ]);
    }

    public function startTrial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_slug' => [
                'required',
                'string',
                Rule::exists('plans', 'slug')->where(fn ($q) => $q->where('is_active', true)),
            ],
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
            'metadata' => [
                'source' => 'onboarding_plan_selection',
            ],
        ]);

        $status = "Your {$days}-day free trial of {$plan->name} has started. Add a payment card to continue.";

        return redirect()
            ->route('onboarding.payment')
            ->with('status', $status);
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

        return view('onboarding.payment', [
            'user' => $user,
            'subscription' => $subscription,
            'plan' => $plan,
            'trialDays' => (int) app_setting('trial.days', 7),
            'stripeEnabled' => StripeService::isConfigured(),
            'stripePublishableKey' => StripeService::publishableKey(),
            'setupIntentClientSecret' => null,
            'verifyAmountCents' => StripeService::verifyAmountCents(),
        ]);
    }

    public function stripeSuccess(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->bypassesOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->hasPaymentMethodOnFile() && $user->activeSubscription()) {
            return redirect()->route('dashboard');
        }

        $result = StripeService::completeCheckoutSetup($user, $data['session_id']);
        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('onboarding.plan')
                ->withErrors(['plan_slug' => $result['message'] ?? 'Stripe Checkout failed. Please try again.']);
        }

        $meta = $result['metadata'] ?? [];
        $planSlug = (string) ($meta['plan_slug'] ?? '');
        $interval = in_array(($meta['billing_interval'] ?? ''), ['monthly', 'yearly'], true)
            ? $meta['billing_interval']
            : 'monthly';
        $days = max(1, (int) ($meta['trial_days'] ?? app_setting('trial.days', 7)));

        if (! $user->activeSubscription()) {
            if ($planSlug === '') {
                return redirect()
                    ->route('onboarding.plan')
                    ->withErrors(['plan_slug' => 'Plan missing from Stripe session. Please select a plan again.']);
            }

            $plan = Plan::query()
                ->where('slug', $planSlug)
                ->where('is_active', true)
                ->first();

            if (! $plan) {
                return redirect()
                    ->route('onboarding.plan')
                    ->withErrors(['plan_slug' => 'Selected plan is no longer available.']);
            }

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
                'metadata' => [
                    'source' => 'stripe_checkout',
                    'stripe_checkout_session_id' => $data['session_id'],
                ],
            ]);
        }

        if (! $user->hasPaymentMethodOnFile()) {
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
        }

        return redirect()
            ->route('dashboard')
            ->with('status', "{$result['brand']} card •••• {$result['last_four']} saved via Stripe. Your trial is active.");
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
            'card_number' => ['required', 'string', 'min:13', 'max:23'],
            'exp_month' => ['required', 'string', 'size:2'],
            'exp_year' => ['required', 'string', 'min:2', 'max:4'],
            'cvv' => ['required', 'string', 'min:3', 'max:4'],
            'payment_method_id' => ['nullable', 'string', 'max:255'],
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
        if (strlen($digits) < 13 || strlen($digits) > 16) {
            return back()->withErrors(['card_number' => 'Enter a valid card number (13–16 digits).'])->withInput();
        }
        $lastFour = substr($digits, -4);
        $brand = CardBrand::detect($digits);
        $expYear = strlen($data['exp_year']) === 2 ? '20'.$data['exp_year'] : $data['exp_year'];
        $verificationStatus = 'saved_local';
        $verificationChargeId = null;
        $stripePaymentMethodId = null;

        if (StripeService::isConfigured()) {
            $pmId = trim((string) ($data['payment_method_id'] ?? ''));
            if ($pmId === '') {
                return back()->withErrors([
                    'card_number' => 'Card verification failed to initialize. Refresh and try again.',
                ])->withInput();
            }

            $verified = StripeService::verifyPaymentMethod($user, $pmId);
            if (! ($verified['ok'] ?? false)) {
                return back()->withErrors([
                    'card_number' => $verified['message'] ?? 'Card verification failed. Please try another card.',
                ])->withInput();
            }

            $brand = (string) ($verified['brand'] ?? $brand);
            $lastFour = (string) ($verified['last_four'] ?? $lastFour);
            $expYear = (string) ($verified['exp_year'] ?? $expYear);
            $data['exp_month'] = (string) ($verified['exp_month'] ?? $data['exp_month']);
            $verificationStatus = 'verified_refunded';
            $verificationChargeId = $verified['charge_id'] ?? null;
            $stripePaymentMethodId = (string) ($verified['payment_method_id'] ?? '');
        }

        PaymentMethod::query()->where('user_id', $user->id)->update(['is_primary' => false]);

        PaymentMethod::query()->create([
            'user_id' => $user->id,
            'label' => $data['label'] ?? 'Primary card',
            'brand' => $brand,
            'last_four' => $lastFour,
            'exp_month' => $data['exp_month'],
            'exp_year' => $expYear,
            'is_primary' => true,
            'is_temporary' => false,
            'stripe_payment_method_id' => $stripePaymentMethodId,
            'verification_status' => $verificationStatus,
            'verification_charge_id' => $verificationChargeId,
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
