<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AutomationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingAutomationController extends Controller
{
    public function index(): View
    {
        $settings = AutomationSetting::query()
            ->orderBy('setting_key')
            ->get()
            ->keyBy('setting_key');

        $groups = [
            'Trial' => [
                'trial_start_automation',
                'trial_expiry_automation',
                'auto_disable_after_trial',
                'trial_ending_email',
                'trial_days_default',
            ],
            'Payments' => [
                'auto_charge_payment',
                'payment_reminder_email',
                'failed_payment_email',
                'auto_disable_after_failed_payment',
                'auto_reactivate_after_payment',
                'payment_grace_days',
                'payment_retry_attempts',
            ],
            'Invoices' => [
                'auto_generate_invoice',
                'auto_send_invoice_email',
                'auto_mark_invoice_paid',
                'auto_mark_invoice_failed',
            ],
        ];

        return view('super-admin.billing-automation.index', [
            'settings' => $settings,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.enabled' => ['nullable', 'boolean'],
            'settings.*.value' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($payload['settings'] as $key => $row) {
            AutomationSetting::upsert(
                (string) $key,
                isset($row['value']) ? (string) $row['value'] : null,
                (bool) ($row['enabled'] ?? false)
            );
        }

        return back()->with('status', 'Billing automation settings saved.');
    }
}
