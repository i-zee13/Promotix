@extends('layouts.auth')

@section('content')
<x-auth.card innerWidth="max-w-md" minHeight="min-h-[520px]">
    <div class="mb-6 flex justify-center">
        <x-brand variant="purple" :height="44" />
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPwd: false }">
        @csrf

        {{-- E-mail --}}
        <div>
            <div class="relative">
                <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                    <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A4.5 4.5 0 0 0 16.5 14h-3a4.5 4.5 0 0 0-4.499 6.118Z" />
                    </svg>
                </div>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="E-mail"
                    class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
            </div>
            @error('email')
                <p class="mt-1.5 text-sm text-red-200">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password (with eye toggle) --}}
        <div>
            <div class="relative">
                <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                    <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <input id="password" name="password" :type="showPwd ? 'text' : 'password'" required autocomplete="current-password" placeholder="password"
                    class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-12 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
                <button type="button" @click="showPwd = !showPwd"
                    class="absolute right-3 top-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-md text-white/85 hover:text-white"
                    :aria-label="showPwd ? 'Hide password' : 'Show password'">
                    <svg x-show="!showPwd" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg x-show="showPwd" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-sm text-red-200">{{ $message }}</p>
            @enderror
        </div>

        {{-- Login / Signup --}}
        <div class="grid grid-cols-2 gap-3 pt-1">
            <button type="submit"
                class="rounded-[10px] border border-white/40 bg-[#4D008E]/60 py-2.5 text-base font-semibold text-white transition hover:bg-[#4D008E]/80">
                Login
            </button>
            @if (Route::has('register'))
                <a href="{{ route('register') }}"
                    class="rounded-[10px] bg-white py-2.5 text-center text-base font-semibold text-[#6400B3] transition hover:bg-white">
                    Signup
                </a>
            @endif
        </div>

        {{-- Forgot password --}}
        @if (Route::has('password.request'))
            <div class="text-center -mt-2">
                <a href="{{ route('password.request') }}" class="text-xs font-medium text-white/85 underline-offset-4 hover:underline">
                    Forgot Password
                </a>
            </div>
        @endif

        {{-- Google Account --}}
        <a href="{{ route('integrations.google.redirect', ['context' => 'auth']) }}"
            class="flex w-full items-center gap-3 rounded-[10px] bg-white px-4 py-2.5 text-base font-medium text-[#6400B3]/80 transition hover:bg-white">
            <span class="flex h-7 w-7 items-center justify-center">
                <svg class="h-6 w-6" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
            </span>
            Account
        </a>
    </form>
</x-auth.card>
@endsection
