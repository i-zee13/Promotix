@extends('layouts.auth-split')

@section('title', 'Create account')

@section('content')
    <a href="{{ route('login') }}" class="auth-back">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back
    </a>

@php
    $accentLogoDark = \App\Support\Branding::logoAsset('dark');
    $accentLogoLight = \App\Support\Branding::logoAsset('light');
@endphp
<img
    src="{{ $accentLogoDark }}"
    alt="{{ \App\Support\PortalBrand::name() }}"
    class="auth-accent-mark auth-accent-mark--on-light"
    width="120"
    height="40"
>
<img
    src="{{ $accentLogoLight }}"
    alt="{{ \App\Support\PortalBrand::name() }}"
    class="auth-accent-mark auth-accent-mark--on-dark"
    width="120"
    height="40"
>
    <h1 class="auth-login-title">Create your account</h1>
    <p class="auth-login-sub">Enter your personal data to create an account.</p>

    @if (! empty($invite))
        <p class="mb-4 rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-xs opacity-90">
            You’re accepting an invite for <strong>{{ $inviteEmail }}</strong>.
        </p>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-400/40 bg-red-500/10 px-3 py-2 text-sm auth-error" style="margin:0 0 1rem;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" x-data="{ p1: false, p2: false }">
        @csrf
        @if (! empty($inviteToken))
            <input type="hidden" name="invite" value="{{ $inviteToken }}">
        @endif

        <div class="auth-field">
            <label class="auth-field-label" for="name">Full name *</label>
            <input id="name" type="text" name="name" value="{{ old('name', $inviteName ?? '') }}" required autofocus autocomplete="name"
                class="auth-field-input" placeholder="Your full name">
        </div>

        <div class="auth-field">
            <label class="auth-field-label" for="email">Email *</label>
            <input id="email" type="email" name="email" value="{{ old('email', $inviteEmail ?? '') }}" required autocomplete="username"
                @if (! empty($invite)) readonly @endif
                class="auth-field-input" placeholder="you@company.com">
        </div>

        <div class="auth-field">
            <label class="auth-field-label" for="phone">Phone *</label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel"
                class="auth-field-input" placeholder="Phone number">
        </div>

        <div class="auth-field has-toggle">
            <label class="auth-field-label" for="password">Password</label>
            <input id="password" name="password" :type="p1 ? 'text' : 'password'" required autocomplete="new-password"
                class="auth-field-input" placeholder="••••••••">
            <button type="button" class="auth-pwd-toggle" @click="p1 = !p1" aria-label="Toggle password">
                <svg x-show="!p1" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                <svg x-show="p1" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88"/></svg>
            </button>
        </div>

        <div class="auth-field has-toggle">
            <label class="auth-field-label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" :type="p2 ? 'text' : 'password'" required autocomplete="new-password"
                class="auth-field-input" placeholder="••••••••">
            <button type="button" class="auth-pwd-toggle" @click="p2 = !p2" aria-label="Toggle password">
                <svg x-show="!p2" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                <svg x-show="p2" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88"/></svg>
            </button>
        </div>

        <button type="submit" class="auth-btn-primary">Create account</button>

        <div class="auth-divider">Or continue with</div>

        <div class="auth-social-row" style="grid-template-columns: 1fr;">
            <a href="{{ route('integrations.google.redirect', ['context' => 'auth']) }}" class="auth-social-btn" aria-label="Continue with Google">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
            </a>
        </div>

        <p class="auth-footer-link">
            Already have an account?
            <a href="{{ route('login') }}">Log in</a>
        </p>
    </form>
@endsection
