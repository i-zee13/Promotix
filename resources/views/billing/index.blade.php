@extends('layouts.admin')

@section('title', 'Billing')

@section('content')
@php
    $bankName = trim((string) ($bank['bank_name'] ?? ''));
    $bankAccount = trim((string) ($bank['account_number'] ?? ''));
    $bankHolder = trim((string) ($bank['account_name'] ?? ''));
    $bankSwift = trim((string) ($bank['swift'] ?? ''));
    $bankInstructions = trim((string) ($bank['instructions'] ?? ''));
@endphp
<div class="billing-page min-h-[calc(100vh-49px)]" x-data="{ showCheckout: false, selectedPlanId: {{ $currentPlan?->id ?? 'null' }} }">
    <section class="mx-auto w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <h1 class="mb-[20px] text-[28px] font-semibold text-[#d9d9d9] sm:text-[36px]">Billing</h1>

        @if (session('status'))
            <div class="billing-glass billing-glass--success mb-[14px] px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        @if (session('billing_alert'))
            <div class="billing-glass billing-glass--warn mb-[16px] px-[18px] py-[14px] text-[13px] text-amber-100">{{ session('billing_alert') }}</div>
        @endif

        @if ($billingAlert)
            <div class="billing-glass billing-glass--warn mb-[16px] px-[18px] py-[14px] text-[13px] text-amber-100">
                <p class="font-semibold text-white">{{ $billingAlert['title'] }}</p>
                <p class="mt-[6px] text-amber-100/90">{{ $billingAlert['message'] }}</p>
                @if ($billingAlert['grace_ends'])
                    <p class="mt-[4px] text-[12px] text-amber-200/80">Grace period ends: {{ $billingAlert['grace_ends']->timezone(config('app.timezone'))->format('M j, Y') }}</p>
                @endif
                <a href="#upgrade-plans" class="mt-[10px] inline-block rounded-[6px] bg-white px-[14px] py-[7px] text-[12px] font-semibold text-[#6400B2]">Pay now</a>
            </div>
        @endif

        {{-- Current subscription --}}
        <article class="billing-glass billing-glass--accent mb-[16px] p-[20px] text-white">
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
        <article class="billing-glass mb-[16px] p-[18px]">
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
                <p class="mb-[12px] text-[13px] text-[#c4b8d4]">No cards on file yet.</p>
            @endif

            <form method="POST" action="{{ route('billing.payment-methods.store') }}" class="billing-glass billing-glass--inset space-y-[10px] p-[12px]">
                @csrf
                <div class="grid gap-[10px] sm:grid-cols-2 lg:grid-cols-4">
                    <input name="card_number" placeholder="Card number" class="billing-input" required>
                    <input name="exp_month" placeholder="MM" maxlength="2" class="billing-input" required>
                    <input name="exp_year" placeholder="YY" maxlength="4" class="billing-input" required>
                    <input name="label" placeholder="Label (optional)" class="billing-input">
                </div>
                <div class="flex flex-wrap items-center gap-[16px] text-[12px] text-[#e8dff3]">
                    <label class="flex items-center gap-[6px]"><input type="radio" name="card_role" value="primary" checked class="text-[#6400B2]"> Primary card (required for billing)</label>
                    <label class="flex items-center gap-[6px]"><input type="radio" name="card_role" value="optional" class="text-[#6400B2]"> Optional backup card</label>
                    <label class="flex items-center gap-[8px]"><x-figma-toggle name="auto_charge" value="1" :show-labels="false" /> Auto-charge on renewal</label>
                    <label class="flex items-center gap-[8px]"><x-figma-toggle name="is_temporary" value="1" :show-labels="false" /> Temporary only</label>
                </div>
                <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[12px] py-[8px] text-[12px] font-semibold text-white">Add card</button>
            </form>
        </article>

        {{-- Upgrade plans --}}
        <div id="upgrade-plans" class="mb-[16px]">
            <h2 class="mb-[12px] text-[20px] font-semibold text-[#d9d9d9]">Upgrade your plan</h2>
            <div class="grid gap-[12px] md:grid-cols-3">
                @foreach ($plans as $plan)
                    @php
                        $limits = $plan->feature_limits ?? [];
                        $isCurrent = $currentPlan && $currentPlan->id === $plan->id && in_array($currentSubscription?->status, ['active', 'trialing'], true);
                    @endphp
                    <article class="billing-plan-card flex flex-col p-[16px] {{ $plan->is_highlighted ? 'is-highlighted' : '' }}">
                        @if ($plan->is_highlighted)
                            <span class="mb-[8px] inline-block w-fit rounded-full bg-white px-[10px] py-[2px] text-[10px] font-semibold text-[#6400B2]">Recommended</span>
                        @endif
                        <h3 class="text-[18px] font-bold text-white">{{ $plan->name }}</h3>
                        <p class="mt-[4px] text-[24px] font-bold text-white">{{ $plan->is_custom ? 'Custom' : format_money_cents($plan->price_cents, $plan->currency) }}<span class="text-[12px] font-normal text-white/65"> / {{ $plan->billing_interval }}</span></p>
                        @if ($plan->short_description)
                            <p class="mt-[8px] text-[12px] text-white/70">{{ $plan->short_description }}</p>
                        @endif
                        <ul class="mt-[10px] flex-1 space-y-[4px] text-[12px] text-[#efe6f8]">
                            @if (isset($limits['domain_limit']))
                                <li>{{ $limits['domain_limit'] === -1 ? 'Unlimited' : $limits['domain_limit'] }} domains</li>
                            @endif
                            @foreach (($plan->feature_flags ?? []) as $flag => $on)
                                @if ($on)<li>{{ str($flag)->replace('_', ' ')->title() }}</li>@endif
                            @endforeach
                        </ul>
                        <button type="button" @click="selectedPlanId = {{ $plan->id }}; showCheckout = true; $nextTick(() => document.getElementById('billing-checkout')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="mt-[12px] w-full rounded-[6px] {{ $isCurrent ? 'border border-white/40 bg-transparent text-white' : 'bg-white text-[#6400B2]' }} py-[8px] text-[13px] font-semibold" @disabled($plan->is_custom)>
                            {{ $isCurrent ? 'Current plan' : ($plan->cta_label ?: 'Upgrade') }}
                        </button>
                    </article>
                @endforeach
            </div>
        </div>

        {{-- Checkout --}}
        <div id="billing-checkout" x-show="showCheckout" x-cloak class="billing-glass billing-glass--checkout mb-[16px] p-[18px]">
            <h2 class="mb-[12px] text-[16px] font-semibold text-white">Submit payment</h2>
            <form method="POST" action="{{ route('billing.submit') }}" enctype="multipart/form-data" class="grid gap-[14px] lg:grid-cols-2">
                @csrf
                <input type="hidden" name="plan_id" :value="selectedPlanId">
                <div class="billing-glass billing-glass--inset space-y-[10px] p-[14px] text-[13px] text-[#e8dff3]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#c9a8ef]">Bank transfer details</p>
                    <p><strong class="text-white">Bank:</strong> {{ $bankName !== '' ? $bankName : '—' }}</p>
                    <p><strong class="text-white">Account name:</strong> {{ $bankHolder !== '' ? $bankHolder : '—' }}</p>
                    <p><strong class="text-white">Account:</strong> {{ $bankAccount !== '' ? $bankAccount : '—' }}</p>
                    @if ($bankSwift !== '')
                        <p><strong class="text-white">SWIFT:</strong> {{ $bankSwift }}</p>
                    @endif
                    @if ($bankInstructions !== '')
                        <p class="rounded-[8px] border border-white/10 bg-white/5 px-[10px] py-[8px] text-[12px] text-[#d9cceb]">{{ $bankInstructions }}</p>
                    @endif
                    @if ($bankName === '' && $bankAccount === '')
                        <p class="text-[12px] text-amber-200">Bank details are not configured yet. Ask super admin to set them in System Settings → Bank Details.</p>
                    @endif
                </div>
                <div class="space-y-[10px]">
                    <input name="bank_reference" placeholder="Bank reference" class="billing-input w-full">
                    <textarea name="notes" rows="2" placeholder="Notes" class="billing-input w-full"></textarea>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required class="w-full text-[12px] text-[#c4b8d4]">
                    <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[18px] py-[9px] text-[13px] font-semibold text-white shadow-[0_8px_24px_rgba(100,0,178,.45)]">Upload receipt & pay</button>
                </div>
            </form>
        </div>

        {{-- Invoices --}}
        <article class="billing-glass p-[18px]">
            <h2 class="mb-[12px] text-[16px] font-semibold text-white">Invoices</h2>
            <div class="overflow-x-auto rounded-[8px] border border-white/10 bg-black/20">
                <table class="w-full min-w-[640px] text-left text-[12px]">
                    <thead class="border-b border-white/15 text-[#c4b8d4]">
                        <tr><th class="px-[12px] py-[10px]">Invoice</th><th class="py-[10px]">Amount</th><th class="py-[10px]">Status</th><th class="py-[10px]">Date</th><th class="py-[10px]">Action</th></tr>
                    </thead>
                    <tbody class="text-white">
                        @forelse ($invoices as $inv)
                            <tr class="border-b border-white/10">
                                <td class="px-[12px] py-[10px] font-mono">{{ $inv->invoice_number }}</td>
                                <td class="py-[10px]">{{ format_money_cents($inv->amount_cents, $inv->currency) }}</td>
                                <td class="py-[10px]"><span class="rounded-full bg-[#e8d4f8] px-[8px] py-[2px] text-[11px] text-[#4a0088]">{{ ucfirst($inv->status) }}</span></td>
                                <td class="py-[10px] text-[#c4b8d4]">{{ $inv->created_at->format('M j, Y') }}</td>
                                <td class="py-[10px]">
                                    @if ($inv->status === 'pending')
                                        <span class="text-[#c4b8d4]">Awaiting verification</span>
                                    @elseif ($inv->receipt_path)
                                        <a href="{{ route('billing.receipt.download', $inv) }}" class="text-[#c9a8ef] hover:underline">Download receipt</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-[12px] py-[20px] text-center text-[#c4b8d4]">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</div>
@endsection
