@extends('layouts.auth')

@section('content')
@php
    $amountCents = (int) ($subscription?->amount_cents ?? 0);
    $currency = strtoupper((string) ($plan?->currency ?? 'USD'));
    $monthly = number_format($amountCents / 100, 2);
    $verifyCents = (int) ($verifyAmountCents ?? 100);
    $isTrialing = ($subscription?->status ?? '') === 'trialing' || ($subscription?->is_trial ?? false);
    $dueToday = $isTrialing ? '0.00' : $monthly;
    $features = collect($plan?->feature_flags ?? [])
        ->filter(fn ($enabled) => (bool) $enabled)
        ->keys()
        ->map(fn ($flag) => str($flag)->replace('_', ' ')->title())
        ->take(4)
        ->values();
    $useStripeElements = ! empty($stripeEnabled) && ! empty($stripePublishableKey);
@endphp

<div class="onboarding-checkout">
    <div class="onboarding-checkout__inner">
        <a href="{{ route('onboarding.plan') }}" class="onboarding-checkout__back">
            <span aria-hidden="true">&larr;</span>
            Configure your plan
        </a>

        @if (! empty($stripeWarning))
            <div class="onboarding-checkout__alert onboarding-checkout__alert--warn">
                {{ $stripeWarning }} Card will be saved locally until Stripe is fixed.
            </div>
        @endif

        <div id="onboarding-payment-error" class="onboarding-checkout__alert onboarding-checkout__alert--error hidden" role="alert"></div>

        @if ($errors->any())
            <div class="onboarding-checkout__alert onboarding-checkout__alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('onboarding.payment.store') }}"
            id="onboarding-payment-form"
            class="onboarding-checkout__grid"
            data-stripe-enabled="{{ $useStripeElements ? '1' : '0' }}"
            data-stripe-pk="{{ $stripePublishableKey ?? '' }}"
            @if (! $useStripeElements)
            x-data="{
                number: '',
                digits() { return (this.number || '').replace(/\D/g, ''); },
                formatNumber(raw) {
                    let d = String(raw || '').replace(/\D/g, '');
                    const amex = /^3[47]/.test(d);
                    d = d.slice(0, amex ? 15 : 16);
                    if (amex) {
                        return [d.slice(0,4), d.slice(4,10), d.slice(10,15)].filter(Boolean).join(' ');
                    }
                    return d.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
                },
                onNumberInput(e) {
                    this.number = this.formatNumber(e.target.value);
                    e.target.value = this.number;
                }
            }"
            @submit="document.getElementById('card_number_raw').value = digits()"
            @endif
        >
            @csrf
            @if ($useStripeElements)
                <input type="hidden" name="payment_method_id" id="stripe_payment_method_id" value="">
            @else
                <input type="hidden" name="card_number" id="card_number_raw" value="">
            @endif

            {{-- Left: payment --}}
            <div class="onboarding-checkout__pay">
                <h2 class="onboarding-checkout__section-title">Pay with</h2>

                <div class="onboarding-checkout__field">
                    <label for="{{ $useStripeElements ? 'stripe-card-number' : 'card_number_display' }}" class="onboarding-checkout__label">Card number</label>
                    <div class="onboarding-checkout__input-wrap onboarding-checkout__input-wrap--card">
                        @if ($useStripeElements)
                            <div id="stripe-card-number" class="onboarding-checkout__stripe-field"></div>
                        @else
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
                                class="onboarding-checkout__input"
                            >
                        @endif
                        <div class="onboarding-checkout__card-icons" aria-hidden="true">
                            @include('partials.accepted-card-brands', ['compact' => true])
                        </div>
                    </div>
                </div>

                <div class="onboarding-checkout__row">
                    <div class="onboarding-checkout__field">
                        <label for="{{ $useStripeElements ? 'stripe-card-expiry' : 'exp_display' }}" class="onboarding-checkout__label">Expiration date</label>
                        @if ($useStripeElements)
                            <div id="stripe-card-expiry" class="onboarding-checkout__stripe-field"></div>
                        @else
                            <input id="exp_display" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM / YY" maxlength="7" required class="onboarding-checkout__input">
                            <input type="hidden" name="exp_month" id="exp_month" value="">
                            <input type="hidden" name="exp_year" id="exp_year_hidden" value="">
                        @endif
                    </div>
                    <div class="onboarding-checkout__field">
                        <label for="{{ $useStripeElements ? 'stripe-card-cvc' : 'cvv' }}" class="onboarding-checkout__label">Security code</label>
                        @if ($useStripeElements)
                            <div id="stripe-card-cvc" class="onboarding-checkout__stripe-field"></div>
                        @else
                            <input
                                id="cvv"
                                type="text"
                                name="cvv"
                                inputmode="numeric"
                                autocomplete="cc-csc"
                                placeholder="CVC"
                                maxlength="4"
                                required
                                class="onboarding-checkout__input"
                                @input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 4)"
                            >
                        @endif
                    </div>
                </div>
                <input type="hidden" name="label" value="Primary card">

                <label class="onboarding-checkout__save">
                    <input type="checkbox" name="save_card" value="1" checked class="onboarding-checkout__checkbox">
                    <span>Save payment information for future purchases</span>
                </label>

                @if ($useStripeElements && $verifyCents > 0)
                    <p class="onboarding-checkout__verify-note">
                        A {{ $currency }} {{ number_format($verifyCents / 100, 2) }} verification charge may appear briefly, then is refunded automatically.
                    </p>
                @endif
            </div>

            {{-- Right: plan summary --}}
            <aside class="onboarding-checkout__summary">
                <div class="onboarding-checkout__summary-card">
                    <h3 class="onboarding-checkout__plan-name">{{ $plan?->name ?? 'Selected' }} plan</h3>

                    <p class="onboarding-checkout__features-title">Top features</p>
                    <ul class="onboarding-checkout__features">
                        @forelse ($features as $feature)
                            <li>
                                <span class="onboarding-checkout__feature-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
                                </span>
                                <span>{{ $feature }}</span>
                            </li>
                        @empty
                            <li><span>Full platform access during your trial</span></li>
                        @endforelse
                    </ul>

                    <div class="onboarding-checkout__divider"></div>

                    <div class="onboarding-checkout__line">
                        <span>Monthly subscription</span>
                        <span>{{ $currency }} {{ $monthly }}</span>
                    </div>
                    <div class="onboarding-checkout__line">
                        <span>Tax (0%)</span>
                        <span>{{ $currency }} 0.00</span>
                    </div>
                    <div class="onboarding-checkout__line onboarding-checkout__line--total">
                        <span>Due today</span>
                        <span>{{ $currency }} {{ $dueToday }}</span>
                    </div>

                    <button type="submit" id="onboarding-payment-submit" class="onboarding-checkout__submit">
                        Subscribe
                    </button>
                </div>

                <p class="onboarding-checkout__legal">
                    By subscribing, you authorize recurring charges after your {{ $trialDays }}-day trial unless you cancel.
                </p>
            </aside>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="onboarding-checkout__signout">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </div>
</div>

@if ($useStripeElements)
<script src="https://js.stripe.com/v3/"></script>
@endif
<script>
    (function () {
        const form = document.getElementById('onboarding-payment-form');
        if (!form) return;

        const errBox = document.getElementById('onboarding-payment-error');
        const submitBtn = document.getElementById('onboarding-payment-submit');

        function showError(msg) {
            if (errBox) {
                errBox.textContent = msg;
                errBox.classList.remove('hidden');
                errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function resetSubmit() {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Subscribe';
            }
        }

        if (form.dataset.stripeEnabled === '1' && window.Stripe) {
            const pk = form.dataset.stripePk || '';
            if (!pk) return;

            const stripe = Stripe(pk);
            const elements = stripe.elements({
                locale: 'en',
            });

            const elementStyle = {
                base: {
                    color: '#ffffff',
                    fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                    fontSize: '15px',
                    fontSmoothing: 'antialiased',
                    '::placeholder': { color: '#6b6b6b' },
                },
                invalid: {
                    color: '#fecaca',
                    iconColor: '#fecaca',
                },
            };

            const cardNumber = elements.create('cardNumber', {
                style: elementStyle,
                showIcon: false,
                placeholder: '1234 1234 1234 1234',
            });
            const cardExpiry = elements.create('cardExpiry', {
                style: elementStyle,
                placeholder: 'MM / YY',
            });
            const cardCvc = elements.create('cardCvc', {
                style: elementStyle,
                placeholder: 'CVC',
            });

            cardNumber.mount('#stripe-card-number');
            cardExpiry.mount('#stripe-card-expiry');
            cardCvc.mount('#stripe-card-cvc');

            [cardNumber, cardExpiry, cardCvc].forEach(function (el) {
                el.on('change', function (event) {
                    if (event.error) {
                        showError(event.error.message);
                    } else if (errBox && !event.empty) {
                        errBox.classList.add('hidden');
                    }
                });
            });

            const pmInput = document.getElementById('stripe_payment_method_id');
            let submittingWithStripe = false;

            form.addEventListener('submit', async function (event) {
                if (submittingWithStripe) return;
                event.preventDefault();

                if (errBox) {
                    errBox.classList.add('hidden');
                }

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Verifying…';
                }

                const result = await stripe.createPaymentMethod({
                    type: 'card',
                    card: cardNumber,
                });

                if (result.error || !result.paymentMethod?.id) {
                    resetSubmit();
                    showError(result.error?.message || 'Card verification failed. Please check your card details.');
                    return;
                }

                pmInput.value = result.paymentMethod.id;
                submittingWithStripe = true;
                form.submit();
            });

            return;
        }

        const expDisplay = document.getElementById('exp_display');
        const expMonthHidden = document.getElementById('exp_month');
        const expYearHidden = document.getElementById('exp_year_hidden');

        if (expDisplay && expMonthHidden && expYearHidden) {
            expDisplay.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').slice(0, 4);
                if (v.length >= 3) {
                    expMonthHidden.value = v.slice(0, 2);
                    expYearHidden.value = v.slice(2, 4);
                    this.value = v.slice(0, 2) + ' / ' + v.slice(2, 4);
                } else {
                    expMonthHidden.value = v.length >= 2 ? v.slice(0, 2) : '';
                    expYearHidden.value = '';
                    this.value = v;
                }
            });
        }
    })();
</script>
@endsection
