@extends('layouts.auth')

@section('content')
<x-auth.card innerWidth="max-w-lg" minHeight="min-h-[640px]">
    <div class="mb-5 flex justify-center">
        <x-brand variant="purple" :height="40" />
    </div>

    <div class="text-center">
        <h1 class="text-2xl font-bold text-white">Create your account</h1>
        <p class="mt-1 text-sm text-white/80">
            Already have one?
            <a href="{{ route('login') }}" class="font-semibold text-white underline-offset-4 hover:underline">Log in</a>
        </p>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-[10px] border border-red-300/50 bg-red-500/15 px-3 py-2 text-sm text-red-100">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4" x-data="{ p1: false, p2: false }">
        @csrf

        {{-- Full name --}}
        <div class="relative">
            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A4.5 4.5 0 0 0 16.5 14h-3a4.5 4.5 0 0 0-4.499 6.118Z" />
                </svg>
            </div>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Full name"
                class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
        </div>

        {{-- Email --}}
        <div class="relative">
            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </div>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="E-mail"
                class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
        </div>

        {{-- Phone (required) --}}
        <div class="relative">
            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.93a1.062 1.062 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                </svg>
            </div>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="Phone number"
                class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
        </div>

        {{-- Two-column: password + confirm --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="relative">
                <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                    <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <input id="password" name="password" :type="p1 ? 'text' : 'password'" required autocomplete="new-password" placeholder="Password"
                    class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-12 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
                <button type="button" @click="p1 = !p1" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/85 hover:text-white" aria-label="Toggle password">
                    <svg x-show="!p1" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <svg x-show="p1" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88"/></svg>
                </button>
            </div>
            <div class="relative">
                <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                    <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <input id="password_confirmation" name="password_confirmation" :type="p2 ? 'text' : 'password'" required autocomplete="new-password" placeholder="Confirm password"
                    class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-12 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
                <button type="button" @click="p2 = !p2" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/85 hover:text-white" aria-label="Toggle password">
                    <svg x-show="!p2" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <svg x-show="p2" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88"/></svg>
                </button>
            </div>
        </div>

        {{-- Optional fields toggle --}}
        <details class="group rounded-[10px] border border-white/25 bg-white/5">
            <summary class="cursor-pointer list-none px-3 py-2 text-sm font-medium text-white/90 group-open:border-b group-open:border-white/15">
                + Add company / website (optional)
            </summary>
            <div class="space-y-3 p-3">
                <div class="relative">
                    <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                    </div>
                    <input type="text" name="company_name" value="{{ old('company_name') }}" placeholder="Company name"
                        class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
                </div>
                <div class="relative">
                    <div class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-[10px] bg-white/20 pointer-events-none">
                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                    </div>
                    <input type="url" name="website_url" value="{{ old('website_url') }}" placeholder="https://yourwebsite.com"
                        class="w-full rounded-[10px] border border-white/30 bg-[#4D008E]/60 py-3 pl-14 pr-4 text-white placeholder-white/65 outline-none transition focus:border-white focus:ring-2 focus:ring-white/30">
                </div>
            </div>
        </details>

        <button type="submit"
            class="mt-2 w-full rounded-[10px] bg-white py-3 text-base font-semibold text-[#6400B3] transition hover:bg-white/90">
            Create account
        </button>

        <div class="flex items-center gap-3 text-xs text-white/70">
            <span class="h-px flex-1 bg-white/25"></span>
            <span>or</span>
            <span class="h-px flex-1 bg-white/25"></span>
        </div>

        <a href="{{ route('integrations.google.redirect', ['context' => 'auth']) }}"
            class="flex w-full items-center justify-center gap-3 rounded-[10px] bg-white/85 px-4 py-2.5 text-base font-medium text-[#6400B3]/80 transition hover:bg-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Sign up with Google
        </a>
    </form>
</x-auth.card>
@endsection
