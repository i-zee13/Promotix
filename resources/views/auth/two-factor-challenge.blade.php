@extends('layouts.auth-split')

@section('title', 'Two-factor authentication')

@section('content')
    <h1 class="auth-login-title">Two-<span style="font-weight:300;color:rgba(255,255,255,.65)">factor</span></h1>
    <p class="mb-4 text-[13px] text-white/55">Enter the 6-digit code from your authenticator app, or a recovery code.</p>

    <form method="POST" action="{{ route('two-factor.login.store') }}">
        @csrf
        <div class="auth-field">
            <span class="auth-field-icon" aria-hidden="true">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </span>
            <input
                id="code"
                type="text"
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                class="auth-field-input @error('code') is-invalid @enderror"
                placeholder="6-digit code"
                required
                autofocus
            >
            @error('code')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="auth-submit">Verify & continue</button>
        <p class="mt-3 text-center text-[12px] text-white/45">
            <a href="{{ route('login') }}" class="text-[#c4a0e8] hover:underline">Back to login</a>
        </p>
    </form>
@endsection
