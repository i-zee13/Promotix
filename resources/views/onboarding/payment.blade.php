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
            Add a card now so billing continues smoothly when the trial ends — you won’t be charged today.
        </p>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-[10px] border border-red-300/50 bg-red-500/15 px-3 py-2 text-sm text-red-100">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('onboarding.payment.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="card_number" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Card number</label>
            <input
                id="card_number"
                type="text"
                name="card_number"
                inputmode="numeric"
                autocomplete="cc-number"
                placeholder="4242 4242 4242 4242"
                required
                class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
            >
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="exp_month" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Month</label>
                <input
                    id="exp_month"
                    type="text"
                    name="exp_month"
                    inputmode="numeric"
                    autocomplete="cc-exp-month"
                    placeholder="MM"
                    maxlength="2"
                    required
                    class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
                >
            </div>
            <div>
                <label for="exp_year" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Year</label>
                <input
                    id="exp_year"
                    type="text"
                    name="exp_year"
                    inputmode="numeric"
                    autocomplete="cc-exp-year"
                    placeholder="YY"
                    maxlength="4"
                    required
                    class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
                >
            </div>
        </div>

        <div>
            <label for="label" class="mb-1.5 block text-left text-xs font-semibold uppercase tracking-wide text-white/70">Label (optional)</label>
            <input
                id="label"
                type="text"
                name="label"
                placeholder="Primary card"
                class="auth-field w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 px-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
            >
        </div>

        <button type="submit" class="mt-2 w-full rounded-[10px] bg-white py-3 text-sm font-semibold text-[color:var(--brand-primary,#6400B3)] transition hover:bg-white/90">
            Save card &amp; continue
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-white/70 underline-offset-4 hover:text-white hover:underline">Sign out</button>
    </form>
</x-auth.card>
@endsection
