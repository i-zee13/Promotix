<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\BillingAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        BillingAccess::applyGraceExpiryIfNeeded($user);

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get();

        $currentSubscription = $user->subscriptions()->with('plan')->latest('id')->first();
        $currentPlan = $currentSubscription?->plan;

        $invoices = Payment::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();

        return view('billing.index', [
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'currentSubscription' => $currentSubscription,
            'invoices' => $invoices,
            'paymentMethods' => $paymentMethods,
            'billingAlert' => BillingAccess::billingAlert($user),
            'bank' => [
                'account_name' => app_setting('bank.account_name'),
                'account_number' => app_setting('bank.account_number'),
                'bank_name' => app_setting('bank.bank_name'),
                'swift' => app_setting('bank.swift'),
                'instructions' => app_setting('bank.instructions'),
            ],
            'support_email' => app_setting('branding.support_email'),
            'company_name' => app_setting('branding.company_name', config('app.name')),
            'domains_used' => $user->domainsUsed(),
            'domain_limit' => $user->domainLimit(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'bank_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $user = $request->user();
        $plan = Plan::query()->where('id', $data['plan_id'])->where('is_active', true)->firstOrFail();

        if ($plan->is_custom) {
            return back()->withErrors([
                'plan_id' => 'Custom plans require manual quote. Please contact support.',
            ]);
        }

        $path = $request->file('receipt')->store('payment-receipts', 'public');

        $payment = DB::transaction(function () use ($user, $plan, $data, $path, $request) {
            Subscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'billing_interval' => $plan->billing_interval,
                'metadata' => ['source' => 'billing'],
            ]);

            return Payment::query()->create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'invoice_number' => 'INV-' . strtoupper(uniqid()),
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'status' => 'pending',
                'payment_method' => 'bank_transfer',
                'receipt_path' => $path,
                'receipt_original_name' => $request->file('receipt')->getClientOriginalName(),
                'bank_reference' => $data['bank_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'plan_slug' => $plan->slug,
                    'submitted_at' => now()->toIso8601String(),
                ],
            ]);
        });

        return redirect()
            ->route('billing.index')
            ->with('status', "Receipt submitted (ref: {$payment->invoice_number}). We'll verify and activate your plan shortly.");
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'card_number' => ['required', 'string', 'min:12', 'max:19'],
            'exp_month' => ['required', 'string', 'size:2'],
            'exp_year' => ['required', 'string', 'min:2', 'max:4'],
            'label' => ['nullable', 'string', 'max:80'],
            'is_temporary' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $digits = preg_replace('/\D/', '', $data['card_number']);
        $lastFour = substr($digits, -4);

        if (! $data['is_temporary']) {
            PaymentMethod::query()->where('user_id', $user->id)->update(['is_primary' => false]);
        }

        PaymentMethod::query()->create([
            'user_id' => $user->id,
            'label' => $data['label'] ?? 'Card',
            'brand' => str_starts_with($digits, '4') ? 'Visa' : (str_starts_with($digits, '5') ? 'Mastercard' : 'Card'),
            'last_four' => $lastFour,
            'exp_month' => $data['exp_month'],
            'exp_year' => $data['exp_year'],
            'is_primary' => ! $data['is_temporary'],
            'is_temporary' => (bool) ($data['is_temporary'] ?? false),
        ]);

        return back()->with('status', 'Payment method saved.');
    }

    public function destroyPaymentMethod(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        abort_unless($paymentMethod->user_id === $request->user()->id, 403);
        $paymentMethod->delete();

        return back()->with('status', 'Payment method removed.');
    }
}
