@extends('layouts.auth')

@section('content')
<x-auth.card innerWidth="max-w-md" minHeight="min-h-[560px]">
    <div class="flex flex-col items-center text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white/25">
            <svg class="h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
        </div>
        <h1 class="mt-4 text-2xl font-bold text-white">Add a payment card</h1>
        <p class="mt-2 max-w-sm text-sm text-white/80">
            @if ($plan)
                Your {{ $plan->name }} trial is ready.
            @endif
            Add a card now so billing continues smoothly when the trial ends — you won’t be charged today
            @if (! empty($stripeEnabled) && ($verifyAmountCents ?? 0) > 0)
                (a ${{ number_format(($verifyAmountCents ?? 100) / 100, 2) }} verification hold may appear, then refund).
            @else
                .
            @endif
        </p>
    </div>

    @include('partials.accepted-card-brands')

    @if ($errors->any())
        <div class="mt-5 rounded-[10px] border border-red-300/50 bg-red-500/15 px-3 py-2 text-sm text-red-100">
            {{ $errors->first() }}
        </div>
    @endif

    @if (! empty($stripeEnabled) && ! empty($stripePublishableKey) && ! empty($setupIntentClientSecret))
        <div class="mt-6 space-y-4" id="stripe-card-form"
             data-pk="{{ $stripePublishableKey }}"
             data-client-secret="{{ $setupIntentClientSecret }}"
             data-confirm-url="{{ route('onboarding.payment.stripe-confirm') }}">
            @csrf
            <div id="stripe-payment-element" class="rounded-[10px] border border-white/30 bg-[#4D008E]/60 p-3"></div>
            <p id="stripe-card-error" class="hidden text-sm text-red-100"></p>
            <button type="button" id="stripe-submit"
                class="mt-2 w-full rounded-[10px] bg-white py-3 text-sm font-semibold text-[color:var(--brand-primary,#6400B3)] transition hover:bg-white/90">
                Verify &amp; save card
            </button>
        </div>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (function () {
                const root = document.getElementById('stripe-card-form');
                if (!root || !window.Stripe) return;
                const stripe = Stripe(root.dataset.pk);
                const elements = stripe.elements({ clientSecret: root.dataset.clientSecret, appearance: { theme: 'night' } });
                const paymentElement = elements.create('payment');
                paymentElement.mount('#stripe-payment-element');
                const errEl = document.getElementById('stripe-card-error');
                const btn = document.getElementById('stripe-submit');
                btn.addEventListener('click', async function () {
                    btn.disabled = true;
                    btn.textContent = 'Verifying…';
                    errEl.classList.add('hidden');
                    const { error, setupIntent } = await stripe.confirmSetup({
                        elements,
                        redirect: 'if_required',
                        confirmParams: { return_url: window.location.href },
                    });
                    if (error) {
                        errEl.textContent = error.message || 'Card verification failed.';
                        errEl.classList.remove('hidden');
                        btn.disabled = false;
                        btn.textContent = 'Verify & save card';
                        return;
                    }
                    const token = root.querySelector('input[name=_token]')?.value;
                    const res = await fetch(root.dataset.confirmUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ setup_intent_id: setupIntent.id }),
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.redirect) {
                        errEl.textContent = data.message || 'Could not save card.';
                        errEl.classList.remove('hidden');
                        btn.disabled = false;
                        btn.textContent = 'Verify & save card';
                        return;
                    }
                    window.location = data.redirect;
                });
            })();
        </script>
    @else
        <form method="POST" action="{{ route('onboarding.payment.store') }}" class="mt-6 space-y-4"
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

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="card_number_display" class="block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Card number</label>
                    <span class="text-xs font-semibold text-white/85" x-text="brand()" x-show="brand()"></span>
                </div>
                <input
                    id="card_number_display"
                    type="text"
                    x-model="number"
                    @input="onNumberInput($event)"
                    inputmode="numeric"
                    autocomplete="cc-number"
                    placeholder="4242 4242 4242 4242"
                    maxlength="19"
                    required
                    class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
                >
                <p class="mt-1.5 text-left text-[11px] text-white/55" x-text="(digits().length || 0) + ' / ' + maxDigits() + ' digits'"></p>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label for="exp_month" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Month</label>
                    <input id="exp_month" type="text" name="exp_month" inputmode="numeric" autocomplete="cc-exp-month" placeholder="MM" maxlength="2" required
                        class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
                </div>
                <div>
                    <label for="exp_year" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Year</label>
                    <input id="exp_year" type="text" name="exp_year" inputmode="numeric" autocomplete="cc-exp-year" placeholder="YY" maxlength="4" required
                        class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
                </div>
                <div>
                    <label for="cvv" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">CVC</label>
                    <input
                        id="cvv"
                        type="text"
                        name="cvv"
                        inputmode="numeric"
                        autocomplete="cc-csc"
                        placeholder="123"
                        maxlength="4"
                        required
                        class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
                        @input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 4)"
                    >
                    <p class="mt-1 text-left text-[10px] text-white/50">Used for verification only — never stored.</p>
                </div>
            </div>

            <div>
                <label for="label" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Label (optional)</label>
                <input id="label" type="text" name="label" placeholder="Primary card"
                    class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
            </div>

            <button type="submit" class="mt-2 w-full rounded-[10px] bg-white py-3 text-sm font-semibold text-[color:var(--brand-primary,#6400B3)] transition hover:bg-white/90">
                Save card &amp; continue
            </button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-white/70 underline-offset-4 hover:text-white hover:underline">Sign out</button>
    </form>
</x-auth.card>
@endsection
