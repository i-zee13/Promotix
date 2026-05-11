<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::with(['user', 'subscription.plan', 'plan'])
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->string('search')->toString(), function ($q, string $search): void {
                $q->where(function ($qq) use ($search): void {
                    $qq->where('invoice_number', 'like', "%{$search}%")
                       ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'pending'  => Payment::where('status', 'pending')->count(),
            'paid'     => Payment::where('status', 'paid')->count(),
            'rejected' => Payment::where('status', 'rejected')->count(),
            'total_paid_cents' => (int) Payment::where('status', 'paid')->sum('amount_cents'),
        ];

        return view('super-admin.payments.index', [
            'payments' => $payments,
            'statuses' => ['paid', 'pending', 'failed', 'refunded', 'rejected'],
            'stats' => $stats,
        ]);
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        if (! in_array($payment->status, ['pending'], true)) {
            return back()->withErrors(['payment' => 'Only pending payments can be verified.']);
        }

        DB::transaction(function () use ($payment, $request): void {
            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'verified_at' => now(),
                'verified_by_id' => $request->user()->id,
            ])->save();

            $subscription = $payment->subscription ?: Subscription::query()->where('user_id', $payment->user_id)->latest('id')->first();
            if ($subscription) {
                $endsAt = now()->addMonth();
                if ($subscription->billing_interval === 'yearly') {
                    $endsAt = now()->addYear();
                }
                $subscription->forceFill([
                    'status' => 'active',
                    'is_trial' => false,
                    'plan_id' => $payment->plan_id ?? $subscription->plan_id,
                    'started_at' => $subscription->started_at ?: now(),
                    'current_period_ends_at' => $endsAt,
                    'last_payment_id' => $payment->id,
                ])->save();
            }
        });

        return back()->with('status', "Payment {$payment->invoice_number} verified and plan activated.");
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $payment->forceFill([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? 'Receipt did not pass verification.',
        ])->save();

        // Mark the linked subscription as cancelled if still pending.
        if ($payment->subscription && $payment->subscription->status === 'pending') {
            $payment->subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return back()->with('status', "Payment {$payment->invoice_number} rejected.");
    }
}
