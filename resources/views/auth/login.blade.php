@extends('layouts.auth')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <div class="max-w-3xl w-full rounded-2xl p-8 sm:p-12 md:p-16 bg-gradient-to-b from-purple-700 to-purple-900 shadow-2xl border border-purple-400/30">
        <div class="max-w-md mx-auto">
            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-0 top-0 bottom-0 w-12 flex items-center justify-center rounded-l-xl bg-purple-600/50 pointer-events-none">
                            <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998-0A4.5 4.5 0 0 0 16.5 14h-3a4.5 4.5 0 0 0-4.499 6.118Z" />
                            </svg>
                        </div>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="E-mail"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-purple-800/40 border border-purple-300/40 text-white placeholder-purple-300 focus:ring-2 focus:ring-purple-400 focus:border-transparent transition outline-none">
                    </div>
                    @error('email')
                        <p class="mt-1.5 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-0 top-0 bottom-0 w-12 flex items-center justify-center rounded-l-xl bg-purple-600/50 pointer-events-none">
                            <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="password"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-purple-800/40 border border-purple-300/40 text-white placeholder-purple-300 focus:ring-2 focus:ring-purple-400 focus:border-transparent transition outline-none">
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Login & Signup buttons --}}
                <div class="flex gap-4">
                    <button type="submit" class="flex-1 py-3 rounded-lg bg-purple-600 text-white font-medium hover:bg-purple-700 transition">
                        Login
                    </button>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="flex-1 py-3 rounded-lg bg-gray-300 text-gray-800 font-medium text-center hover:bg-gray-400 transition">
                            Signup
                        </a>
                    @endif
                </div>

                {{-- Forgot Password --}}
                @if (Route::has('password.request'))
                    <div class="text-center">
                        <a href="{{ route('password.request') }}" class="text-xs text-purple-200 hover:underline">
                            Forgot Password
                        </a>
                    </div>
                @endif

                {{-- Google sign-in (UI only) --}}
                <a href="{{ route('integrations.google.redirect', ['context' => 'auth']) }}" class="w-full py-3 rounded-lg bg-gray-200 text-gray-800 font-medium flex items-center justify-center gap-3 hover:bg-gray-300 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Account
                </a>
            </form>
        </div>
    </div>
</div>
@endsection
