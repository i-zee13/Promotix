<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\BillingAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->paginate(min(50, max(10, $request->integer('per_page', 10))))
            ->withQueryString();

        $stats = [
            'pending'  => Payment::where('status', 'pending')->count(),
            'paid'     => Payment::where('status', 'paid')->count(),
            'failed'   => Payment::whereIn('status', ['failed', 'rejected'])->count(),
            'refunded' => Payment::where('status', 'refunded')->count(),
            'total_paid_cents' => (int) Payment::where('status', 'paid')->sum('amount_cents'),
        ];

        return view('super-admin.payments.index', [
            'payments' => $payments,
            'statuses' => ['paid', 'pending', 'failed', 'refunded', 'rejected'],
            'stats' => $stats,
        ]);
    }

    public function downloadReceipt(Payment $payment): StreamedResponse
    {
        abort_unless($payment->receipt_path, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($payment->receipt_path), 404, 'Receipt file is missing from storage.');

        $filename = $payment->receipt_original_name ?: basename($payment->receipt_path);

        return $disk->download($payment->receipt_path, $filename);
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
                BillingAccess::markPaymentRecovered($payment->user, $subscription);
                $subscription->forceFill([
                    'is_trial' => false,
                    'plan_id' => $payment->plan_id ?? $subscription->plan_id,
                    'started_at' => $subscription->started_at ?: now(),
                    'last_payment_id' => $payment->id,
                ])->save();
            }

            if (in_array($payment->user->status, ['suspended'], true)) {
                $payment->user->forceFill(['status' => 'active'])->save();
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

    public function markFailed(Request $request, Payment $payment): RedirectResponse
    {
        if (! in_array($payment->status, ['pending', 'paid'], true)) {
            return back()->withErrors(['payment' => 'This payment cannot be marked failed.']);
        }

        $payment->forceFill([
            'status' => 'failed',
            'rejected_at' => now(),
            'rejection_reason' => $request->input('reason', 'Payment failed.'),
        ])->save();

        $subscription = $payment->subscription
            ?: Subscription::query()->where('user_id', $payment->user_id)->latest('id')->first();

        if ($subscription && $payment->user) {
            BillingAccess::markPastDue($payment->user, $subscription);
        }

        return back()->with('status', "Payment {$payment->invoice_number} marked failed; grace period started.");
    }
}
