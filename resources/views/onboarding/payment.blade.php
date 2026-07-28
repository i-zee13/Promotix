@extends('layouts.auth')

@section('content')
@php
    $amountCents = (int) ($subscription?->amount_cents ?? 0);
    $currency = strtoupper((string) ($plan?->currency ?? 'USD'));
    $monthly = number_format($amountCents / 100, 2);
    $features = collect($plan?->feature_flags ?? [])
        ->filter(fn ($enabled) => (bool) $enabled)
        ->keys()
        ->map(fn ($flag) => str($flag)->replace('_', ' ')->title())
        ->take(4)
        ->values();
@endphp

<div class="min-h-screen bg-[#0d0d0d] px-4 py-8 text-white sm:px-6 lg:px-10">
    <div class="mx-auto w-full max-w-[1180px]">
        <a href="{{ route('onboarding.plan') }}" class="inline-flex items-center gap-2 text-[15px] font-semibold text-white/90 hover:text-white">
            <span aria-hidden="true">&larr;</span>
            Configure your plan
        </a>

        @if ($errors->any())
            <div class="mt-4 rounded-[10px] border border-red-300/50 bg-red-500/15 px-4 py-3 text-sm text-red-100">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <h2 class="mb-3 text-[30px] font-bold leading-tight">Payment method</h2>
                <p class="mb-5 text-sm text-white/70">
                    Add your card to continue. Trial starts now, recurring billing begins after trial.
                </p>

                @include('partials.accepted-card-brands')

                <form method="POST" action="{{ route('onboarding.payment.store') }}" class="mt-5 space-y-4" id="onboarding-payment-form"
                    data-stripe-enabled="{{ ! empty($stripeEnabled) && ! empty($stripePublishableKey) ? '1' : '0' }}"
                    data-stripe-pk="{{ $stripePublishableKey ?? '' }}"
                    x-data="{
                        number: '',
                        digits() {
                            return (this.number || '').replace(/\D/g, '');
                        },
                        brand() {
                            const d = this.digits();
                            if (/^3[47]/.test(d)) return 'Amex';
                            if (/^(6011|65|64[4-9])/.test(d)) return 'Discover';
                            if (/^62/.test(d)) return 'UnionPay';
                            if (/^5[1-5]/.test(d)) return 'Mastercard';
                            if (d.length >= 4) {
                                const bin = parseInt(d.slice(0, 4), 10);
                                if (bin >= 2221 && bin <= 2720) return 'Mastercard';
                            }
                            if (/^4/.test(d)) return 'Visa';
                            return d.length ? 'Card' : '';
                        },
                        maxDigits() {
                            return this.brand() === 'Amex' ? 15 : 16;
                        },
                        formatNumber(raw) {
                            let d = String(raw || '').replace(/\D/g, '');
                            const amex = /^3[47]/.test(d);
                            d = d.slice(0, amex ? 15 : 16);
                            if (amex) {
                                const a = d.slice(0, 4);
                                const b = d.slice(4, 10);
                                const c = d.slice(10, 15);
                                return [a, b, c].filter(Boolean).join(' ');
                            }
                            return d.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
                        },
                        onNumberInput(e) {
                            this.number = this.formatNumber(e.target.value);
                            e.target.value = this.number;
                        }
                    }"
                    @submit="document.getElementById('card_number_raw').value = digits()">
                    @csrf
                    <input type="hidden" name="card_number" id="card_number_raw" value="">
                    <input type="hidden" name="payment_method_id" id="stripe_payment_method_id" value="">

                    <div class="rounded-[16px] border border-white/20 bg-[#2c2c2c] p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <label for="card_number_display" class="text-xs font-semibold uppercase tracking-wide text-white/75">Card number</label>
                            <span class="text-xs font-semibold text-white/85" x-text="brand()" x-show="brand()"></span>
                        </div>
                        <input
                            id="card_number_display"
                            type="text"
                            x-model="number"
                            @input="onNumberInput($event)"
                            inputmode="numeric"
                            autocomplete="cc-number"
                            placeholder="1234 1234 1234 1234"
                            maxlength="19"
                            required
                            class="w-full rounded-[12px] border border-white/20 bg-[#3a3a3a] px-4 py-3 text-[20px] tracking-wide text-white placeholder-white/40 outline-none transition focus:border-white/70"
                        >
                        <p class="mt-2 text-[11px] text-white/50" x-text="(digits().length || 0) + ' / ' + maxDigits() + ' digits'"></p>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-[14px] border border-white/20 bg-[#2c2c2c] p-3">
                            <label for="exp_month" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-white/70">Month</label>
                            <input id="exp_month" type="text" name="exp_month" inputmode="numeric" autocomplete="cc-exp-month" placeholder="MM" maxlength="2" required
                                class="w-full rounded-[10px] border border-white/20 bg-[#3a3a3a] px-3 py-2 text-white placeholder-white/50 outline-none focus:border-white/70">
                        </div>
                        <div class="rounded-[14px] border border-white/20 bg-[#2c2c2c] p-3">
                            <label for="exp_year" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-white/70">Year</label>
                            <input id="exp_year" type="text" name="exp_year" inputmode="numeric" autocomplete="cc-exp-year" placeholder="YY" maxlength="4" required
                                class="w-full rounded-[10px] border border-white/20 bg-[#3a3a3a] px-3 py-2 text-white placeholder-white/50 outline-none focus:border-white/70">
                        </div>
                        <div class="rounded-[14px] border border-white/20 bg-[#2c2c2c] p-3">
                            <label for="cvv" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-white/70">CVC</label>
                            <input
                                id="cvv"
                                type="text"
                                name="cvv"
                                inputmode="numeric"
                                autocomplete="cc-csc"
                                placeholder="123"
                                maxlength="4"
                                required
                                class="w-full rounded-[10px] border border-white/20 bg-[#3a3a3a] px-3 py-2 text-white placeholder-white/50 outline-none focus:border-white/70"
                                @input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 4)"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="label" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/70">Card label (optional)</label>
                        <input id="label" type="text" name="label" placeholder="Primary card"
                            class="w-full rounded-[12px] border border-white/20 bg-[#2c2c2c] px-4 py-3 text-white placeholder-white/45 outline-none focus:border-white/70">
                    </div>

                    <button type="submit" id="onboarding-payment-submit" class="mt-2 w-full rounded-full bg-white py-3 text-base font-semibold text-[#202020] transition hover:bg-white/90">
                        Subscribe
                    </button>
                </form>
            </div>

            <aside class="lg:col-span-5">
                <div class="rounded-[28px] border border-white/20 bg-[#2e2e2e] p-6">
                    <h3 class="text-[42px] font-semibold leading-none">{{ $plan?->name ?? 'Selected' }} plan</h3>
                    <p class="mt-4 text-[24px] font-semibold text-white/90">Top features</p>
                    <ul class="mt-3 space-y-2 text-[20px] text-white/90">
                        @forelse ($features as $feature)
                            <li class="flex items-start gap-2">
                                <span class="mt-1 text-white/70">✦</span>
                                <span>{{ $feature }}</span>
                            </li>
                        @empty
                            <li class="text-white/75">Plan features will be shown here.</li>
                        @endforelse
                    </ul>

                    <div class="my-6 h-px bg-white/20"></div>

                    <div class="space-y-2 text-[20px] text-white/90">
                        <div class="flex items-center justify-between">
                            <span>Monthly subscription</span>
                            <span>{{ $currency }} {{ $monthly }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Tax (0%)</span>
                            <span>{{ $currency }} 0.00</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[26px] font-bold text-white">
                            <span>Due today</span>
                            <span>{{ $currency }} {{ $monthly }}</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-5 text-left">
                    @csrf
                    <button type="submit" class="text-sm text-white/70 underline-offset-4 hover:text-white hover:underline">Sign out</button>
                </form>
            </aside>
        </div>
    </div>
</div>
<script src="https://js.stripe.com/v3/"></script>
<script>
    (function () {
        const form = document.getElementById('onboarding-payment-form');
        if (!form) return;

        if (form.dataset.stripeEnabled !== '1') return;
        if (!window.Stripe) return;

        const pk = form.dataset.stripePk || '';
        if (!pk) return;

        const stripe = Stripe(pk);
        const submitBtn = document.getElementById('onboarding-payment-submit');
        const pmInput = document.getElementById('stripe_payment_method_id');
        let submittingWithStripe = false;

        form.addEventListener('submit', async function (event) {
            if (submittingWithStripe) return;

            event.preventDefault();

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Verifying...';
            }

            const cardNumber = (document.getElementById('card_number_raw')?.value || '').trim();
            const expMonth = (document.getElementById('exp_month')?.value || '').trim();
            const expYear = (document.getElementById('exp_year')?.value || '').trim();
            const cvc = (document.getElementById('cvv')?.value || '').trim();

            const result = await stripe.createPaymentMethod({
                type: 'card',
                card: {
                    number: cardNumber,
                    exp_month: expMonth,
                    exp_year: expYear,
                    cvc: cvc,
                },
                billing_details: {
                    name: (document.getElementById('label')?.value || '').trim() || undefined,
                },
            });

            if (result.error || !result.paymentMethod?.id) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Subscribe';
                }
                alert(result.error?.message || 'Card verification failed. Please check card details.');
                return;
            }

            pmInput.value = result.paymentMethod.id;
            submittingWithStripe = true;
            form.submit();
        });
    })();
</script>
@endsection
