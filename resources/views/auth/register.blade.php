@extends('layouts.auth')

@section('content')
<div class="min-h-screen flex flex-col md:flex-row items-center justify-center p-4 sm:p-6 lg:p-8 bg-[#0D0D0D] relative">
    {{-- Background: login purple card (visual only) --}}
    <div class="w-full md:max-w-3xl md:flex-shrink-0 flex items-center justify-center md:justify-end order-2 md:order-1">
        <div class="w-full max-w-3xl rounded-2xl p-8 sm:p-12 md:p-16 bg-gradient-to-b from-purple-700 to-purple-900 shadow-2xl border border-purple-400/30">
            <div class="max-w-md mx-auto space-y-5 opacity-90">
                <div class="relative">
                    <div class="absolute left-0 top-0 bottom-0 w-12 flex items-center justify-center rounded-l-xl bg-purple-600/50">
                        <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998-0A4.5 4.5 0 0 0 16.5 14h-3a4.5 4.5 0 0 0-4.499 6.118Z" />
                        </svg>
                    </div>
                    <div class="w-full pl-12 pr-4 py-3 rounded-xl bg-purple-800/40 border border-purple-300/40 text-white placeholder-purple-300">E-mail</div>
                </div>
                <div class="relative">
                    <div class="absolute left-0 top-0 bottom-0 w-12 flex items-center justify-center rounded-l-xl bg-purple-600/50">
                        <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <div class="w-full pl-12 pr-4 py-3 rounded-xl bg-purple-800/40 border border-purple-300/40 text-white placeholder-purple-300">password</div>
                </div>
                <div class="w-full py-3 rounded-lg bg-purple-600 text-white text-center font-medium">Login</div>
                <div class="w-full py-3 rounded-lg bg-gray-200 text-gray-800 font-medium flex items-center justify-center gap-3">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Account
                </div>
            </div>
        </div>
    </div>

    {{-- Signup modal overlay --}}
    <div class="w-full max-w-md mt-6 md:mt-0 md:ml-[-80px] md:flex-shrink-0 order-1 md:order-2 relative z-10">
        <div class="bg-gray-200 rounded-2xl p-8 shadow-2xl">
            {{-- Back button & title --}}
            <div class="flex items-center gap-3 mb-6">
                <a href="{{ route('login') }}" class="rounded-full bg-gray-300 p-2 hover:bg-gray-400 transition flex-shrink-0" aria-label="Back to login">
                    <svg class="w-5 h-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">SignUp Form</h1>
            </div>

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                {{-- Full name --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-md bg-gray-300 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998-0A4.5 4.5 0 0 0 16.5 14h-3a4.5 4.5 0 0 0-4.499 6.118Z" />
                                </svg>
                        </div>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Full name"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-gray-400 text-gray-800 placeholder-gray-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition outline-none border border-transparent">
                    </div>
                    @error('name')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- E-mail --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-md bg-gray-300 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                        </div>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="E-mail"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-gray-400 text-gray-800 placeholder-gray-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition outline-none border border-transparent">
                    </div>
                    @error('email')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-md bg-gray-300 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                        </div>
                        <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Password"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-gray-400 text-gray-800 placeholder-gray-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition outline-none border border-transparent">
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password confirmation --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-md bg-gray-300 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                        </div>
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm password"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-gray-400 text-gray-800 placeholder-gray-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition outline-none border border-transparent">
                    </div>
                    @error('password_confirmation')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Phone (UI only) --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-md bg-gray-300 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.93a1.062 1.062 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                </svg>
                        </div>
                        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone" autocomplete="tel"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-gray-400 text-gray-800 placeholder-gray-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition outline-none border border-transparent">
                    </div>
                </div>

                {{-- Country (UI only) --}}
                <div>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-md bg-gray-300 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                        </div>
                        <select name="country" class="w-full pl-12 pr-10 py-3 rounded-xl bg-gray-400 text-gray-800 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition outline-none border border-transparent appearance-none cursor-pointer">
                            <option value="">Country</option>
                            <option value="US" {{ old('country') === 'US' ? 'selected' : '' }}>United States</option>
                            <option value="CA" {{ old('country') === 'CA' ? 'selected' : '' }}>Canada</option>
                            <option value="GB" {{ old('country') === 'GB' ? 'selected' : '' }}>United Kingdom</option>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-600">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Google box (UI only) --}}
                <button type="button" class="w-full py-3 rounded-xl bg-gray-300 text-gray-800 font-medium flex items-center justify-center gap-3 hover:bg-gray-400 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Google
                </button>

                {{-- Create button --}}
                <div class="flex justify-center pt-2">
                    <button type="submit" class="rounded-full bg-purple-600 px-6 py-2 text-white font-medium hover:bg-purple-700 transition">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
