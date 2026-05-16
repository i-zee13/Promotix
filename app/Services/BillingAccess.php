<?php

namespace App\Services;

use App\Models\AutomationSetting;
use App\Models\Subscription;
use App\Models\User;

class BillingAccess
{
    public static function hasProtectionAccess(User $user): bool
    {
        if ($user->is_super_admin || $user->is_admin) {
            return true;
        }

        if ($user->activeSubscription()) {
            return true;
        }

        $pastDue = self::pastDueSubscription($user);
        if ($pastDue && ! $pastDue->protection_paused_at) {
            if (! $pastDue->grace_period_ends_at || $pastDue->grace_period_ends_at->isFuture()) {
                return true;
            }
        }

        return false;
    }

    public static function pastDueSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->where('status', 'past_due')
            ->latest('id')
            ->first();
    }

    public static function markPastDue(User $user, ?Subscription $subscription = null): void
    {
        $subscription ??= $user->subscriptions()->latest('id')->first();
        if (! $subscription) {
            return;
        }

        $graceDays = AutomationSetting::intValue('payment_grace_days', 7);
        $graceEnds = now()->addDays(max(1, $graceDays));

        $subscription->forceFill([
            'status' => 'past_due',
            'grace_period_ends_at' => $graceEnds,
            'protection_paused_at' => null,
        ])->save();
    }

    public static function markPaymentRecovered(User $user, Subscription $subscription): void
    {
        $subscription->forceFill([
            'status' => 'active',
            'grace_period_ends_at' => null,
            'protection_paused_at' => null,
            'current_period_ends_at' => $subscription->billing_interval === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
        ])->save();
    }

    public static function applyGraceExpiryIfNeeded(User $user): void
    {
        if (! AutomationSetting::isEnabled('auto_disable_after_failed_payment', true)) {
            return;
        }

        $sub = self::pastDueSubscription($user);
        if (! $sub || ! $sub->grace_period_ends_at?->isPast()) {
            return;
        }

        if (! $sub->protection_paused_at) {
            $sub->forceFill(['protection_paused_at' => now()])->save();
        }

        if (in_array($user->status, ['active', 'pending'], true)) {
            $user->forceFill(['status' => 'suspended'])->save();
        }
    }

    public static function billingAlert(User $user): ?array
    {
        $pastDue = self::pastDueSubscription($user);
        if (! $pastDue) {
            return null;
        }

        return [
            'title' => 'Subscription payment is past due',
            'message' => 'Tracking and protection are paused until your payment is resolved.',
            'amount_due_cents' => $pastDue->amount_cents,
            'grace_ends' => $pastDue->grace_period_ends_at,
            'currency' => $pastDue->currency,
        ];
    }
}
