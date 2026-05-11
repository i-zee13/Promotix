<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UpgradePlanController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('price_cents')
            ->get();

        $currentSubscription = $user->subscriptions()->latest('id')->first();
        $currentPlan = $currentSubscription?->plan;

        $pendingPayment = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        return view('upgrade-plan', [
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'currentSubscription' => $currentSubscription,
            'pendingPayment' => $pendingPayment,
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
            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'billing_interval' => $plan->billing_interval,
                'metadata' => ['source' => 'upgrade_plan'],
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
            ->route('upgrade-plan')
            ->with('status', "Receipt submitted (ref: {$payment->invoice_number}). We'll verify and activate your plan shortly.");
    }
}
