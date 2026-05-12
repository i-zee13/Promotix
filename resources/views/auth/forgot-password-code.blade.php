@extends('layouts.auth')

@section('content')
<x-auth.card innerWidth="max-w-md" minHeight="min-h-[520px]">
    <div class="mb-6 flex justify-center">
        <x-brand variant="purple" :height="40" />
    </div>

    <div class="text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/25">
            <svg class="h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
        </div>
        <h1 class="mt-4 text-2xl font-bold text-white">Check your email</h1>
        <p class="mt-2 text-sm text-white/80">
            Enter the code sent to
            <span class="font-semibold text-white">{{ $email ?? '—' }}</span>
        </p>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-[10px] border border-red-300/50 bg-red-500/15 px-3 py-2 text-sm text-red-100">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('status'))
        <div class="mt-5 rounded-[10px] border border-emerald-200/40 bg-emerald-500/15 px-3 py-2 text-sm text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <form id="codeForm" method="POST" action="{{ route('password.code.verify') }}" class="mt-6 space-y-5"
        x-data="{ digits: ['','','','','',''] }"
        x-init="$nextTick(() => $refs.d0 && $refs.d0.focus())">
        @csrf
        <input type="hidden" name="email" value="{{ $email ?? old('email') }}">
        <input type="hidden" name="code" :value="digits.join('')">

        <div class="flex justify-center gap-2 sm:gap-3">
            @for ($i = 0; $i < 6; $i++)
                <input
                    x-ref="d{{ $i }}"
                    x-model="digits[{{ $i }}]"
                    @input="
                        digits[{{ $i }}] = $event.target.value.replace(/\D/g,'').slice(-1);
                        if (digits[{{ $i }}] && {{ $i }} < 5) $refs.d{{ $i + 1 }}.focus();
                    "
                    @keydown.backspace="
                        if (!digits[{{ $i }}] && {{ $i }} > 0) $refs.d{{ $i - 1 }}.focus();
                    "
                    @paste.prevent="
                        const raw = ($event.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
                        for (let i = 0; i < raw.length; i++) { digits[i] = raw[i]; }
                        const next = Math.min(raw.length, 5);
                        $refs['d' + next].focus();
                    "
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    autocomplete="one-time-code"
                    aria-label="Digit {{ $i + 1 }}"
                    class="h-12 w-10 sm:h-14 sm:w-12 rounded-[10px] border border-white/30 bg-[#4D008E]/60 text-center text-xl font-bold text-white outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
                >
            @endfor
        </div>

        <div class="flex justify-center">
            <button type="submit"
                class="rounded-[10px] bg-white px-10 py-2.5 text-base font-semibold text-[#6400B3] transition hover:bg-white/90">
                Verify
            </button>
        </div>

        <p class="text-center text-xs text-white/80">
            Didn't get the code? Check your spam folder.
        </p>
    </form>

    <form method="POST" action="{{ route('password.email') }}" class="mt-2 text-center">
        @csrf
        <input type="hidden" name="email" value="{{ $email ?? '' }}">
        <button type="submit" class="text-sm font-semibold text-white underline-offset-4 hover:underline">
            Resend code
        </button>
    </form>

    @if (session('dev_code'))
        <div class="mt-4 rounded-[10px] border border-white/30 bg-black/20 px-3 py-2 text-center text-xs text-white/80">
            Dev preview · code:
            <span class="font-mono text-base font-bold tracking-widest text-white">{{ session('dev_code') }}</span>
        </div>
    @endif
</x-auth.card>
@endsection
