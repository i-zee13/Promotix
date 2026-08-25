@extends('layouts.admin')

@section('title', 'Billing')

@section('content')
@php
    $bankName = trim((string) ($bank['bank_name'] ?? ''));
    $bankAccount = trim((string) ($bank['account_number'] ?? ''));
    $bankHolder = trim((string) ($bank['account_name'] ?? ''));
    $bankSwift = trim((string) ($bank['swift'] ?? ''));
    $bankInstructions = trim((string) ($bank['instructions'] ?? ''));

    $primaryCard = $paymentMethods->firstWhere('is_primary', true) ?? $paymentMethods->first();
    $cardsForJs = $paymentMethods->map(fn ($c) => [
        'id' => $c->id,
        'label' => $c->maskedLabel(),
        'brand' => $c->brand ?: 'Card',
        'last_four' => $c->last_four ?: '0000',
        'exp_month' => $c->exp_month,
        'exp_year' => strlen((string) $c->exp_year) === 4 ? substr((string) $c->exp_year, -2) : (string) $c->exp_year,
        'is_primary' => (bool) $c->is_primary,
    ])->values();

    $plansForJs = $plans->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'price_cents' => (int) $p->price_cents,
        'currency' => $p->currency,
        'billing_interval' => $p->billing_interval,
        'formatted_price' => $p->is_custom ? 'Custom' : format_money_cents($p->price_cents, $p->currency),
        'is_custom' => (bool) $p->is_custom,
    ])->keyBy('id');
@endphp
<div
    class="brand-page-bg min-h-[calc(100vh-49px)]"
    x-data="{
        showCardModal: false,
        showBankCheckout: false,
        showCoupon: false,
        selectedPlanId: {{ $currentPlan?->id ?? 'null' }},
        billingCycle: 'monthly',
        cardMode: {{ $primaryCard ? "'saved'" : "'new'" }},
        selectedCardId: {{ $primaryCard?->id ?? "'new'" }},
        plans: {{ $plansForJs->toJson() }},
        cards: {{ $cardsForJs->toJson() }},
        form: {
            card_number: '',
            exp_month: '{{ $primaryCard?->exp_month ?? '' }}',
            exp_year: '{{ $primaryCard ? (strlen((string) $primaryCard->exp_year) === 4 ? substr((string) $primaryCard->exp_year, -2) : $primaryCard->exp_year) : '' }}',
            cvv: '',
            cardholder_name: @js($primaryCard?->label ?: ''),
            coupon: ''
        },
        get selectedPlan() {
            return this.plans[this.selectedPlanId] || null;
        },
        get selectedCard() {
            return this.cards.find(c => String(c.id) === String(this.selectedCardId)) || null;
        },
        get displayAmount() {
            const p = this.selectedPlan;
            if (!p || p.is_custom) return '—';
            let cents = p.price_cents;
            if (this.billingCycle === 'yearly' && p.billing_interval === 'monthly') {
                cents = Math.round(cents * 12 * 0.9);
            }
            return (p.currency || 'USD').toUpperCase() + ' ' + (cents / 100).toFixed(2);
        },
        openUpgrade(planId) {
            this.selectedPlanId = planId;
            this.showBankCheckout = false;
            this.showCardModal = true;
            if (this.cards.length) {
                const primary = this.cards.find(c => c.is_primary) || this.cards[0];
                this.selectedCardId = primary.id;
                this.cardMode = 'saved';
                this.applySavedCard(primary);
            } else {
                this.selectedCardId = 'new';
                this.cardMode = 'new';
            }
        },
        applySavedCard(card) {
            if (!card) return;
            this.form.card_number = '•••• •••• •••• ' + card.last_four;
            this.form.exp_month = card.exp_month || '';
            this.form.exp_year = card.exp_year || '';
            this.form.cardholder_name = card.label || '';
            this.form.cvv = '';
        },
        onCardModeChange() {
            if (this.cardMode === 'new') {
                this.selectedCardId = null;
                this.form.card_number = '';
                this.form.exp_month = '';
                this.form.exp_year = '';
                this.form.cvv = '';
                this.form.cardholder_name = '';
            } else if (this.selectedCard) {
                this.applySavedCard(this.selectedCard);
            }
        },
        onSavedCardChange() {
            this.cardMode = 'saved';
            this.applySavedCard(this.selectedCard);
        }
    }"
>
    <section class="mx-auto w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <h1 class="mb-[20px] text-[28px] font-semibold text-[#a9a9a9] sm:text-[36px]">Billing</h1>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[#6400B2]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-[14px] rounded-[8px] border border-rose-400/40 bg-rose-500/15 p-[14px] text-[13px] text-rose-100">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if (session('billing_alert'))
            <div class="mb-[16px] rounded-[10px] border border-amber-400/40 bg-amber-500/15 px-[18px] py-[14px] text-[13px] text-amber-100">{{ session('billing_alert') }}</div>
        @endif

        @if ($billingAlert)
            <div class="mb-[16px] rounded-[10px] border border-amber-400/40 bg-amber-500/15 px-[18px] py-[14px] text-[13px] text-amber-100">
                <p class="font-semibold text-white">{{ $billingAlert['title'] }}</p>
                <p class="mt-[6px] text-amber-100/90">{{ $billingAlert['message'] }}</p>
                @if ($billingAlert['grace_ends'])
                    <p class="mt-[4px] text-[12px] text-amber-200/80">Grace period ends: {{ $billingAlert['grace_ends']->timezone(config('app.timezone'))->format('M j, Y') }}</p>
                @endif
                <a href="#upgrade-plans" class="mt-[10px] inline-block rounded-[6px] bg-white px-[14px] py-[7px] text-[12px] font-semibold text-[#6400B2]">Pay now</a>
            </div>
        @endif

        {{-- Current subscription --}}
        <article class="mb-[16px] rounded-[10px] border border-white/25 bg-[#6400B2] p-[20px] text-white shadow-[0_0_18px_rgba(100,0,179,.3)]">
            <h2 class="mb-[12px] text-[18px] font-semibold">Current subscription</h2>
            <div class="grid gap-[12px] text-[13px] sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-white/70">Plan</p><p class="font-semibold">{{ $currentPlan?->name ?? '—' }}</p></div>
                <div><p class="text-white/70">Status</p><p class="font-semibold">{{ ucfirst($currentSubscription?->status ?? 'none') }}</p></div>
                <div><p class="text-white/70">Billing cycle</p><p class="font-semibold">{{ ucfirst($currentSubscription?->billing_interval ?? '—') }}</p></div>
                <div><p class="text-white/70">Next payment</p><p class="font-semibold">{{ $currentSubscription?->current_period_ends_at?->format('M j, Y') ?? '—' }}</p></div>
            </div>
            <p class="mt-[10px] text-[12px] text-white/75">Domains: {{ $domains_used }} / {{ $domain_limit === INF ? '∞' : (int) $domain_limit }}</p>
        </article>

        {{-- Payment methods --}}
        <article class="mb-[16px] rounded-[10px] border border-white/20 bg-[#1a1024] p-[18px] shadow-[0_0_24px_rgba(100,0,179,.15)]">
            <h2 class="mb-[12px] text-[16px] font-semibold text-white">Payment methods</h2>

            @if ($paymentMethods->isNotEmpty())
                <div class="billing-card-grid mb-[14px]">
                    @foreach ($paymentMethods as $card)
                        <div class="billing-card-chip {{ $card->is_primary ? 'is-primary' : '' }}">
                            @if ($card->is_primary)
                                <span class="billing-card-chip__badge">Primary</span>
                            @endif
                            <div>
                                <p class="billing-card-chip__brand">{{ $card->brand ?: 'Card' }}</p>
                                <p class="billing-card-chip__number">•••• {{ $card->last_four ?: '0000' }}</p>
                                <p class="billing-card-chip__meta">{{ $card->exp_month }}/{{ $card->exp_year }} @if($card->is_temporary)· Temp @endif</p>
                            </div>
                            <div class="billing-card-chip__actions">
                                @unless ($card->is_primary)
                                    <form method="POST" action="{{ route('billing.payment-methods.primary', $card) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="hover:underline">Set primary</button>
                                    </form>
                                @else
                                    <span class="text-[10px] text-[#B893D8]">Active</span>
                                @endunless
                                <form method="POST" action="{{ route('billing.payment-methods.destroy', $card) }}" onsubmit="return confirm('Remove this card?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="is-danger hover:underline">Remove</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mb-[12px] text-[13px] text-[#a9a9a9]">No cards on file yet.</p>
            @endif

            @if (! empty($stripeEnabled))
                <form method="POST" action="{{ route('billing.payment-methods.store') }}" class="space-y-[10px] rounded-[8px] border border-[#6400B2]/40 bg-[#12081c] p-[12px]">
                    @csrf
                    <div class="mb-[4px]">
                        @include('partials.accepted-card-brands')
                    </div>
                    <p class="text-[13px] text-[#d9d9d9]">Add or update your card securely on Stripe’s checkout page.</p>
                    <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[12px] py-[8px] text-[12px] font-semibold text-white">Add card on Stripe</button>
                </form>
            @else
            <form method="POST" action="{{ route('billing.payment-methods.store') }}" class="space-y-[10px] rounded-[8px] border border-[#6400B2]/40 bg-[#12081c] p-[12px]">
                @csrf
                <div class="mb-[4px]">
                    @include('partials.accepted-card-brands')
                </div>
                <div class="grid gap-[10px] sm:grid-cols-2 lg:grid-cols-4">
                    <input
                        name="card_number"
                        placeholder="4242 4242 4242 4242"
                        inputmode="numeric"
                        autocomplete="cc-number"
                        maxlength="19"
                        required
                        class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white"
                        oninput="
                            let d = this.value.replace(/\D/g,'').slice(0,16);
                            this.value = d.replace(/(\d{4})(?=\d)/g,'$1 ').trim();
                        "
                    >
                    <input name="exp_month" placeholder="MM" maxlength="2" class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white" required>
                    <input name="exp_year" placeholder="YY" maxlength="4" class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white" required>
                    <input name="label" placeholder="Label (optional)" class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white">
                </div>
                <div class="flex flex-wrap items-center gap-[16px] text-[12px] text-[#d9d9d9]">
                    <label class="flex items-center gap-[6px]"><input type="radio" name="card_role" value="primary" checked class="text-[#6400B2]"> Primary card (required for billing)</label>
                    <label class="flex items-center gap-[6px]"><input type="radio" name="card_role" value="optional" class="text-[#6400B2]"> Optional backup card</label>
                    <label class="flex items-center gap-[8px]"><x-figma-toggle name="auto_charge" value="1" :show-labels="false" /> Auto-charge on renewal</label>
                    <label class="flex items-center gap-[8px]"><x-figma-toggle name="is_temporary" value="1" :show-labels="false" /> Temporary only</label>
                </div>
                <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[12px] py-[8px] text-[12px] font-semibold text-white">Add card</button>
            </form>
            @endif
        </article>

        {{-- Upgrade plans --}}
        <div id="upgrade-plans" class="mb-[16px]">
            <h2 class="mb-[12px] text-[20px] font-semibold text-[#a9a9a9]">Upgrade your plan</h2>
            <div class="grid gap-[12px] md:grid-cols-3">
                @foreach ($plans as $plan)
                    @php
                        $limits = $plan->feature_limits ?? [];
                        $isCurrent = $currentPlan && $currentPlan->id === $plan->id && in_array($currentSubscription?->status, ['active', 'trialing'], true);
                    @endphp
                    <article class="flex flex-col rounded-[10px] border border-white/30 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.25)] {{ $plan->is_highlighted ? 'border-[#9a1aff] ring-2 ring-[#9a1aff]/50' : '' }}">
                        @if ($plan->is_highlighted)
                            <span class="mb-[8px] inline-block w-fit rounded-full bg-white px-[10px] py-[2px] text-[10px] font-semibold text-[#6400B2]">Recommended</span>
                        @endif
                        <h3 class="text-[18px] font-bold text-white">{{ $plan->name }}</h3>
                        <p class="mt-[4px] text-[24px] font-bold text-white">{{ $plan->is_custom ? 'Custom' : format_money_cents($plan->price_cents, $plan->currency) }}<span class="text-[12px] font-normal text-[#a9a9a9]"> / {{ $plan->billing_interval }}</span></p>
                        @if ($plan->short_description)
                            <p class="mt-[8px] text-[12px] text-[#a9a9a9]">{{ $plan->short_description }}</p>
                        @endif
                        <ul class="mt-[10px] flex-1 space-y-[4px] text-[12px] text-[#d9d9d9]">
                            @if (isset($limits['domain_limit']))
                                <li>{{ $limits['domain_limit'] === -1 ? 'Unlimited' : $limits['domain_limit'] }} domains</li>
                            @endif
                            @foreach (($plan->feature_flags ?? []) as $flag => $on)
                                @if ($on)<li>{{ str($flag)->replace('_', ' ')->title() }}</li>@endif
                            @endforeach
                        </ul>
                        <button
                            type="button"
                            @click="openUpgrade({{ $plan->id }})"
                            class="mt-[12px] w-full rounded-[6px] py-[8px] text-[13px] font-semibold {{ $isCurrent ? 'border border-white/40 bg-transparent text-white' : 'bg-white text-[#6400B2]' }}"
                            @disabled($plan->is_custom)
                        >
                            {{ $isCurrent ? 'Current plan' : ($plan->cta_label ?: 'Upgrade') }}
                        </button>
                    </article>
                @endforeach
            </div>
        </div>

        {{-- Bank transfer fallback --}}
        <div id="billing-checkout" x-show="showBankCheckout" x-cloak class="mb-[16px] rounded-[10px] border border-[#6400B2]/50 bg-[#1a1024] p-[18px] shadow-[0_0_24px_rgba(100,0,179,.2)]">
            <div class="mb-[12px] flex items-center justify-between gap-3">
                <h2 class="text-[16px] font-semibold text-white">Pay by bank transfer</h2>
                <button type="button" class="text-[12px] text-[#c9a8ef] hover:underline" @click="showBankCheckout = false; showCardModal = true">Back to card</button>
            </div>
            <form method="POST" action="{{ route('billing.submit') }}" enctype="multipart/form-data" class="grid gap-[12px] lg:grid-cols-2">
                @csrf
                <input type="hidden" name="plan_id" :value="selectedPlanId">
                <div class="space-y-[10px] rounded-[8px] border border-white/15 bg-[#6400B2]/25 p-[14px] text-[13px] text-[#d9d9d9]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#c9a8ef]">Bank transfer details</p>
                    <p><strong class="text-white">Bank:</strong> {{ $bankName !== '' ? $bankName : '—' }}</p>
                    <p><strong class="text-white">Account name:</strong> {{ $bankHolder !== '' ? $bankHolder : '—' }}</p>
                    <p><strong class="text-white">Account:</strong> {{ $bankAccount !== '' ? $bankAccount : '—' }}</p>
                    @if ($bankSwift !== '')
                        <p><strong class="text-white">SWIFT:</strong> {{ $bankSwift }}</p>
                    @endif
                    @if ($bankInstructions !== '')
                        <p class="text-[12px] text-[#cfcfcf]">{{ $bankInstructions }}</p>
                    @endif
                </div>
                <div class="space-y-[10px]">
                    <input name="bank_reference" placeholder="Bank reference" class="w-full rounded-[6px] border border-white/20 bg-[#101010] px-[12px] py-[8px] text-white">
                    <textarea name="notes" rows="2" placeholder="Notes" class="w-full rounded-[6px] border border-white/20 bg-[#101010] px-[12px] py-[8px] text-white"></textarea>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required class="w-full text-[12px] text-[#a9a9a9]">
                    <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[18px] py-[9px] text-[13px] font-semibold text-white">Upload receipt & pay</button>
                </div>
            </form>
        </div>

        {{-- Invoices --}}
        <article class="rounded-[10px] border border-white/20 bg-[#1a1024] p-[18px] shadow-[0_0_24px_rgba(100,0,179,.15)]">
            <h2 class="mb-[12px] text-[16px] font-semibold text-white">Invoices</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-[12px]">
                    <thead class="border-b border-white/15 text-[#a9a9a9]">
                        <tr><th class="py-[8px] pr-[12px]">Invoice</th><th class="py-[8px]">Amount</th><th class="py-[8px]">Status</th><th class="py-[8px]">Date</th><th class="py-[8px]">Action</th></tr>
                    </thead>
                    <tbody class="text-white">
                        @forelse ($invoices as $inv)
                            <tr class="border-b border-white/10">
                                <td class="py-[10px] font-mono">{{ $inv->invoice_number }}</td>
                                <td class="py-[10px]">{{ format_money_cents($inv->amount_cents, $inv->currency) }}</td>
                                <td class="py-[10px]"><span class="rounded-full bg-[#e8d4f8] px-[8px] py-[2px] text-[11px] text-[#4a0088]">{{ ucfirst($inv->status) }}</span></td>
                                <td class="py-[10px] text-[#a9a9a9]">{{ $inv->created_at->format('M j, Y') }}</td>
                                <td class="py-[10px]">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('billing.invoices.show', $inv) }}" class="text-[#9a1aff] hover:underline">View invoice</a>
                                        @if ($inv->receipt_path)
                                            <a href="{{ route('billing.receipt.download', $inv) }}" class="text-[#a9a9a9] hover:underline">Receipt</a>
                                        @elseif ($inv->status === 'pending')
                                            <span class="text-[#a9a9a9]">Awaiting verification</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-[20px] text-center text-[#a9a9a9]">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    {{-- Credit card upgrade modal --}}
    <div
        x-show="showCardModal"
        x-cloak
        class="billing-card-modal-backdrop"
        @keydown.escape.window="if (showCardModal) showCardModal = false"
        @click.self="showCardModal = false"
    >
        <div class="billing-card-modal" @click.stop>
            <button type="button" class="billing-card-modal__close" @click="showCardModal = false" aria-label="Close">&times;</button>

            <div class="mb-3 text-center">
                <div class="billing-card-modal__icon">
                    <svg class="h-5 w-5 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                </div>
                <p class="text-[13px] text-white/85">
                    @if (! empty($stripeEnabled))
                        Continue to Stripe Checkout
                    @else
                        Insert Your Credit Card Details
                    @endif
                </p>
            </div>

            <form method="POST" action="{{ route('billing.pay-card') }}" class="space-y-3" @submit="if (! {{ !empty($stripeEnabled) ? 'true' : 'false' }} && cardMode === 'saved' && (!selectedCardId || String(selectedCardId) === 'new')) { $event.preventDefault(); }">
                @csrf
                <input type="hidden" name="plan_id" :value="selectedPlanId">
                <input type="hidden" name="billing_cycle" :value="billingCycle">

                <div>
                    <h3 class="text-[15px] font-semibold">{{ ! empty($stripeEnabled) ? 'Pay with Stripe' : 'Credit Card Details' }}</h3>
                    <p class="mt-1 text-[12px] text-white/85">
                        You pay <span class="font-semibold" x-text="displayAmount"></span> now for
                        <span class="font-semibold" x-text="selectedPlan?.name || 'this plan'"></span>.
                        @if (! empty($stripeEnabled))
                            You’ll complete payment on Stripe’s secure page.
                        @endif
                    </p>
                </div>

                @if (empty($stripeEnabled))
                <template x-if="cardMode === 'saved' && selectedCardId && String(selectedCardId) !== 'new'">
                    <input type="hidden" name="payment_method_id" :value="selectedCardId">
                </template>

                <div>
                    <label class="mb-1 block text-[11px] text-white/75">Card</label>
                    <select
                        class="billing-card-modal__field"
                        x-model="selectedCardId"
                        @change="
                            if (String(selectedCardId) === 'new' || selectedCardId === '' || selectedCardId === null) {
                                cardMode = 'new';
                                selectedCardId = 'new';
                                form.card_number = '';
                                form.exp_month = '';
                                form.exp_year = '';
                                form.cvv = '';
                                form.cardholder_name = '';
                            } else {
                                cardMode = 'saved';
                                applySavedCard(selectedCard);
                            }
                        "
                    >
                        <option value="new">Add new card</option>
                        <template x-for="card in cards" :key="card.id">
                            <option :value="card.id" x-text="card.label + (card.is_primary ? ' (Primary)' : '')"></option>
                        </template>
                    </select>
                </div>

                <div class="relative">
                    <input
                        type="text"
                        name="card_number"
                        x-model="form.card_number"
                        @input="
                            if (cardMode === 'new') {
                                let d = form.card_number.replace(/\D/g,'').slice(0,16);
                                form.card_number = d.replace(/(\d{4})(?=\d)/g,'$1 ').trim();
                            }
                        "
                        :readonly="cardMode === 'saved' && selectedCardId && String(selectedCardId) !== 'new'"
                        :required="cardMode === 'new'"
                        :maxlength="cardMode === 'new' ? 19 : 30"
                        inputmode="numeric"
                        autocomplete="cc-number"
                        placeholder="4242 4242 4242 4242"
                        class="billing-card-modal__field"
                        style="padding-right: 56px;"
                    >
                    <span class="billing-card-modal__brand" x-text="selectedCard?.brand || 'CARD'"></span>
                </div>
                @endif
                @if (empty($stripeEnabled))
                <div class="billing-card-modal__grid">
                    <input
                        type="text"
                        name="exp_month"
                        maxlength="2"
                        x-model="form.exp_month"
                        :readonly="cardMode === 'saved' && selectedCardId && String(selectedCardId) !== 'new'"
                        :required="cardMode === 'new'"
                        placeholder="MM"
                        class="billing-card-modal__field"
                    >
                    <input
                        type="text"
                        name="exp_year"
                        maxlength="4"
                        x-model="form.exp_year"
                        :readonly="cardMode === 'saved' && selectedCardId && String(selectedCardId) !== 'new'"
                        :required="cardMode === 'new'"
                        placeholder="YY"
                        class="billing-card-modal__field"
                    >
                </div>

                <div class="billing-card-modal__grid">
                    <input
                        type="text"
                        name="cvv"
                        maxlength="4"
                        x-model="form.cvv"
                        :required="cardMode === 'new'"
                        placeholder="CVV"
                        class="billing-card-modal__field"
                    >
                    <input
                        type="text"
                        name="cardholder_name"
                        x-model="form.cardholder_name"
                        placeholder="Name / ZIP"
                        class="billing-card-modal__field"
                    >
                </div>
                @endif

                <button type="button" class="flex items-center gap-1 text-[12px] text-white/90" style="background:transparent;border:0;padding:0;cursor:pointer;" @click="showCoupon = !showCoupon">
                    Have a coupon?
                    <svg class="h-3 w-3" :style="showCoupon ? 'transform:rotate(180deg)' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="showCoupon" x-cloak>
                    <input type="text" name="coupon" x-model="form.coupon" placeholder="Coupon code" class="billing-card-modal__field">
                </div>

                <div class="billing-card-modal__toggle-wrap">
                    <span :class="billingCycle === 'monthly' ? '' : 'billing-card-modal__muted'">Monthly</span>
                    <button
                        type="button"
                        class="billing-card-modal__toggle"
                        :class="billingCycle === 'yearly' ? 'is-yearly' : ''"
                        @click.prevent.stop="billingCycle = billingCycle === 'monthly' ? 'yearly' : 'monthly'"
                        aria-label="Toggle billing cycle"
                    >
                        <span class="billing-card-modal__toggle-knob"></span>
                    </button>
                    <span :class="billingCycle === 'yearly' ? '' : 'billing-card-modal__muted'">Yearly</span>
                </div>

                <p class="text-center text-[11px] text-white/80">
                    @if (! empty($stripeEnabled))
                        You’ll finish on Stripe — card is charged there.
                    @else
                        Your plan activates after payment verification.
                    @endif
                </p>

                <button type="submit" class="billing-card-modal__pay">{{ ! empty($stripeEnabled) ? 'Continue to Stripe' : 'Pay now' }}</button>

                <button type="button" class="billing-card-modal__bank-link" @click="showCardModal = false; showBankCheckout = true">
                    Or pay by bank transfer
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
