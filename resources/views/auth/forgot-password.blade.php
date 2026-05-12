@extends('layouts.auth')

@section('content')
<x-auth.card innerWidth="max-w-md" minHeight="min-h-[520px]">
    <div class="mb-6 flex justify-center">
        <x-brand variant="purple" :height="40" />
    </div>

    <div class="flex flex-col items-center text-center">
        {{-- Lock badge --}}
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white/25">
            <svg class="h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
        </div>

        <h1 class="mt-4 text-2xl font-bold text-white">Forgot password?</h1>
        <p class="mt-2 max-w-sm text-sm text-white/80">
            Enter the email tied to your account and we'll send you a 6-digit code to reset your password.
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

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf

        <div class="relative">
            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </div>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                placeholder="E-mail"
                class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30"
            >
        </div>

        <button type="submit"
            class="w-full rounded-[10px] bg-white py-3 text-base font-semibold text-[#6400B3] transition hover:bg-white/90">
            Send reset code
        </button>

        <p class="text-center text-xs text-white/85">
            Remembered it?
            <a href="{{ route('login') }}" class="font-semibold text-white underline-offset-4 hover:underline">Back to login</a>
        </p>
    </form>
</x-auth.card>
@endsection
