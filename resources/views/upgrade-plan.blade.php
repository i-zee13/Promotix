@extends('layouts.admin')

@section('title', 'Upgrade plan')

@php
    $plansForJs = $plans->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'formatted_price' => $p->formatted_price,
    ])->keyBy('id');
@endphp

@section('content')
<div class="space-y-6"
    x-data="{
        plans: {{ $plansForJs->toJson() }},
        selectedPlanId: {{ $currentPlan?->id ?? 'null' }},
        showCheckout: false,
        get selectedPlanLabel() {
            const p = this.plans[this.selectedPlanId];
            return p ? `${p.name} (${p.formatted_price})` : '—';
        }
    }">
    <x-ui.page-header
        title="Upgrade plan"
        subtitle="Pick the plan that fits, upload a proof of payment, and we'll activate it after verification.">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="brand-btn-secondary">Back to dashboard</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <div class="rounded-xl2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl2 border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-200">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Current usage summary --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.kpi-card label="Current plan" :value="$currentPlan?->name ?? 'No active plan'"
            :hint="$currentSubscription?->status ? ucfirst($currentSubscription->status) : 'Trial / free tier'">
        </x-ui.kpi-card>
        <x-ui.kpi-card label="Domains used" :value="$domains_used.' / '.($domain_limit === INF ? '∞' : (int) $domain_limit)"
            hint="Connect more domains by upgrading your plan.">
        </x-ui.kpi-card>
        <x-ui.kpi-card label="Renewal" :value="$currentSubscription?->current_period_ends_at?->toFormattedDateString() ?? '—'"
            :hint="$currentSubscription?->trial_ends_at ? 'Trial ends '.$currentSubscription->trial_ends_at->diffForHumans() : 'No renewal scheduled.'">
        </x-ui.kpi-card>
    </div>

    @if ($pendingPayment)
        <x-ui.card class="border-amber-500/40 bg-amber-500/10">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-amber-200">Payment pending verification</p>
                    <p class="text-xs text-amber-100/80">
                        Invoice <span class="font-mono">{{ $pendingPayment->invoice_number }}</span>
                        · {{ format_money_cents($pendingPayment->amount_cents, $pendingPayment->currency) }}
                        · submitted {{ $pendingPayment->created_at->diffForHumans() }}.
                    </p>
                </div>
                <span class="brand-pill brand-pill-warning">Pending</span>
            </div>
        </x-ui.card>
    @endif

    {{-- Plan grid --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($plans as $plan)
            @php
                $limits = $plan->feature_limits ?? [];
                $isCurrent = $currentPlan && $currentPlan->id === $plan->id && in_array($currentSubscription?->status, ['active', 'trialing'], true);
            @endphp
            <article class="brand-card flex flex-col p-6"
                :class="selectedPlanId === {{ $plan->id }} ? 'ring-2 ring-brand-500 shadow-card-lg' : 'ring-1 ring-night-700'">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-brand-300">{{ ucfirst($plan->tier) }}</p>
                        <h3 class="mt-1 text-xl font-bold text-white">{{ $plan->name }}</h3>
                    </div>
                    @if ($isCurrent)
                        <span class="brand-pill brand-pill-success">Current</span>
                    @endif
                </div>

                <p class="mt-4 text-3xl font-extrabold text-white">
                    @if ($plan->is_custom)
                        Custom
                    @else
                        {{ format_money_cents($plan->price_cents, $plan->currency) }}
                        <span class="text-sm font-medium text-night-300">/ {{ $plan->billing_interval }}</span>
                    @endif
                </p>

                <ul class="mt-4 flex-1 space-y-2 text-sm text-night-200">
                    @if (isset($limits['domain_limit']))
                        <li class="flex items-start gap-2">
                            <span class="text-brand-300">✓</span>
                            {{ $limits['domain_limit'] === -1 ? 'Unlimited' : $limits['domain_limit'] }} connected domain{{ $limits['domain_limit'] === 1 ? '' : 's' }}
                        </li>
                    @endif
                    @if (isset($limits['users_limit']))
                        <li class="flex items-start gap-2">
                            <span class="text-brand-300">✓</span>
                            {{ $limits['users_limit'] === -1 ? 'Unlimited' : $limits['users_limit'] }} team member{{ $limits['users_limit'] === 1 ? '' : 's' }}
                        </li>
                    @endif
                    @if (isset($limits['visit_retention_days']))
                        <li class="flex items-start gap-2">
                            <span class="text-brand-300">✓</span>
                            {{ $limits['visit_retention_days'] }} days of visit history
                        </li>
                    @endif
                    @foreach (($plan->feature_flags ?? []) as $flag => $enabled)
                        @if ($enabled)
                            <li class="flex items-start gap-2">
                                <span class="text-brand-300">✓</span>
                                {{ str($flag)->replace('_', ' ')->title() }}
                            </li>
                        @endif
                    @endforeach
                </ul>

                <button type="button" class="brand-btn-primary mt-6 w-full"
                    @click="selectedPlanId = {{ $plan->id }}; showCheckout = true; window.scrollTo({ top: document.getElementById('checkout-anchor').offsetTop - 80, behavior: 'smooth' });"
                    @if ($plan->is_custom) disabled @endif>
                    {{ $isCurrent ? 'Renew this plan' : 'Choose '.$plan->name }}
                </button>
            </article>
        @endforeach
    </div>

    {{-- Checkout block --}}
    <div id="checkout-anchor"></div>
    <div x-show="showCheckout" x-transition class="grid grid-cols-1 gap-6 lg:grid-cols-3" style="display: none;">
        <x-ui.card title="Bank transfer details" subtitle="Use these to send the payment." class="lg:col-span-1">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-night-400">Account name</dt>
                    <dd class="font-semibold text-white">{{ $bank['account_name'] ?: '— configure in super admin —' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-night-400">Account / IBAN</dt>
                    <dd class="font-mono text-white">{{ $bank['account_number'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-night-400">Bank</dt>
                    <dd class="text-white">{{ $bank['bank_name'] ?: '—' }}</dd>
                </div>
                @if ($bank['swift'])
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-night-400">SWIFT / BIC</dt>
                        <dd class="font-mono text-white">{{ $bank['swift'] }}</dd>
                    </div>
                @endif
                @if ($bank['instructions'])
                    <div class="rounded-xl2 border border-night-700 bg-night-900/40 p-3 text-xs text-night-200">
                        {{ $bank['instructions'] }}
                    </div>
                @endif
                <p class="text-xs text-night-400">
                    Need help? Contact <a class="text-brand-300 hover:underline" href="mailto:{{ $support_email }}">{{ $support_email }}</a>.
                </p>
            </dl>
        </x-ui.card>

        <x-ui.card title="Upload payment receipt" subtitle="We'll verify and activate your plan after the receipt is reviewed." class="lg:col-span-2">
            <form method="POST" action="{{ route('upgrade-plan.submit') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="plan_id" :value="selectedPlanId">

                <p class="text-sm text-night-200">
                    Submitting receipt for plan:
                    <span class="font-semibold text-white" x-text="selectedPlanLabel"></span>
                </p>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="bank_reference" class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Bank reference (optional)</label>
                        <input id="bank_reference" name="bank_reference" type="text" maxlength="120" class="brand-input mt-1 w-full" placeholder="e.g. INV-2026-001">
                    </div>
                    <div>
                        <label for="receipt" class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Receipt (JPG, PNG, PDF · ≤ 5 MB)</label>
                        <input id="receipt" name="receipt" type="file" accept=".jpg,.jpeg,.png,.pdf" required class="brand-input mt-1 w-full file:mr-3 file:rounded-md file:border-0 file:bg-brand-500 file:px-3 file:py-1 file:text-white">
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000" class="brand-input mt-1 w-full" placeholder="Anything we should know about this payment..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="brand-btn-secondary" @click="showCheckout = false">Cancel</button>
                    <button type="submit" class="brand-btn-primary">Submit for verification</button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
