@extends('layouts.admin')

@section('title', 'Billing')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="{ showCheckout: false, selectedPlanId: {{ $currentPlan?->id ?? 'null' }} }">
    <section class="mx-auto w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <h1 class="mb-[20px] text-[28px] font-semibold text-[#a9a9a9] sm:text-[36px]">Billing</h1>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[#6400B2]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
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
            <div class="grid gap-[12px] sm:grid-cols-2 lg:grid-cols-4 text-[13px]">
                <div><p class="text-white/70">Plan</p><p class="font-semibold">{{ $currentPlan?->name ?? '—' }}</p></div>
                <div><p class="text-white/70">Status</p><p class="font-semibold">{{ ucfirst($currentSubscription?->status ?? 'none') }}</p>
                <div><p class="text-white/70">Billing cycle</p><p class="font-semibold">{{ ucfirst($currentSubscription?->billing_interval ?? '—') }}</p></div>
                <div><p class="text-white/70">Next payment</p><p class="font-semibold">{{ $currentSubscription?->current_period_ends_at?->format('M j, Y') ?? '—' }}</p></div>
            </div>
            <p class="mt-[10px] text-[12px] text-white/75">Domains: {{ $domains_used }} / {{ $domain_limit === INF ? '∞' : (int) $domain_limit }}</p>
        </article>

        {{-- Payment methods --}}
        <article class="mb-[16px] rounded-[10px] border border-white/15 bg-[#151515] p-[18px]">
            <h2 class="mb-[12px] text-[16px] font-semibold text-white">Payment methods</h2>
            <div class="mb-[12px] space-y-[8px]">
                @forelse ($paymentMethods as $card)
                    <div class="flex flex-wrap items-center justify-between gap-[8px] rounded-[8px] border border-white/10 bg-[#101010] px-[12px] py-[10px] text-[13px] text-white">
                        <span>{{ $card->maskedLabel() }} @if($card->is_primary)<span class="ml-[6px] rounded bg-[#6400B2] px-[6px] py-[1px] text-[10px]">Primary</span>@endif @if($card->is_temporary)<span class="ml-[6px] text-[#a9a9a9]">Temporary</span>@endif</span>
                        <form method="POST" action="{{ route('billing.payment-methods.destroy', $card) }}">@csrf @method('DELETE')<button type="submit" class="text-[12px] text-rose-300 hover:underline">Remove</button></form>
                    </div>
                @empty
                    <p class="text-[13px] text-[#a9a9a9]">No cards on file yet.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('billing.payment-methods.store') }}" class="grid gap-[10px] rounded-[8px] border border-white/10 bg-[#101010] p-[12px] sm:grid-cols-2 lg:grid-cols-5">
                @csrf
                <input name="card_number" placeholder="Card number" class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white" required>
                <input name="exp_month" placeholder="MM" maxlength="2" class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white" required>
                <input name="exp_year" placeholder="YY" maxlength="4" class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white" required>
                <input name="label" placeholder="Label" class="rounded-[6px] border border-white/20 bg-[#0d0d0d] px-[10px] py-[8px] text-[13px] text-white">
                <div class="flex items-end gap-[8px]">
                    <label class="flex items-center gap-[6px] text-[12px] text-[#a9a9a9]"><input type="checkbox" name="is_temporary" value="1" class="rounded"> Temp</label>
                    <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[12px] py-[8px] text-[12px] font-semibold text-white">Add card</button>
                </div>
            </form>
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
                    <article class="flex flex-col rounded-[10px] border p-[16px] {{ $plan->is_highlighted ? 'border-[#9a1aff] bg-[#6400B2]/40' : 'border-white/20 bg-[#151515]' }}">
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
                        <button type="button" @click="selectedPlanId = {{ $plan->id }}; showCheckout = true" class="mt-[12px] w-full rounded-[6px] {{ $isCurrent ? 'border border-white/40 bg-transparent text-white' : 'bg-white text-[#6400B2]' }} py-[8px] text-[13px] font-semibold" @disabled($plan->is_custom)>
                            {{ $isCurrent ? 'Current plan' : ($plan->cta_label ?: 'Upgrade') }}
                        </button>
                    </article>
                @endforeach
            </div>
        </div>

        {{-- Checkout --}}
        <div x-show="showCheckout" x-cloak class="mb-[16px] rounded-[10px] border border-white/20 bg-[#151515] p-[18px]">
            <h2 class="mb-[12px] text-[16px] font-semibold text-white">Submit payment</h2>
            <form method="POST" action="{{ route('billing.submit') }}" enctype="multipart/form-data" class="grid gap-[12px] lg:grid-cols-2">
                @csrf
                <input type="hidden" name="plan_id" :value="selectedPlanId">
                <div class="space-y-[10px] text-[13px] text-[#d9d9d9]">
                    <p><strong class="text-white">Bank:</strong> {{ $bank['bank_name'] ?: '—' }}</p>
                    <p><strong class="text-white">Account:</strong> {{ $bank['account_number'] ?: '—' }}</p>
                    <p>{{ $bank['instructions'] }}</p>
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
        <article class="rounded-[10px] border border-white/15 bg-[#151515] p-[18px]">
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
                                    @if ($inv->status === 'pending')
                                        <span class="text-[#a9a9a9]">Awaiting verification</span>
                                    @elseif ($inv->receipt_path)
                                        <a href="{{ asset('storage/'.$inv->receipt_path) }}" target="_blank" class="text-[#9a1aff] hover:underline">Download</a>
                                    @else
                                        —
                                    @endif
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
</div>
@endsection
