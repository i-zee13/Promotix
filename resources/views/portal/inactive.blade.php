<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $productName }} unavailable — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! \App\Support\Branding::rootStyleBlock() !!}</style>
</head>
<body class="figma-body min-h-screen font-sans antialiased">
    <div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-6 py-16 text-center">
        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl" style="background:var(--brand-primary);box-shadow:0 0 28px color-mix(in srgb, var(--brand-primary) 45%, transparent);">
            <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>

        <p class="mb-2 text-[12px] font-semibold uppercase tracking-[0.14em] text-white/50">Solution unavailable</p>
        <h1 class="mb-3 text-[28px] font-semibold leading-tight text-white">{{ $productName }} is currently inactive</h1>
        <p class="mb-8 text-[15px] leading-relaxed text-white/70">
            Access to the customer portal has been paused by the administrator.
            Please contact support if you believe this is an error.
        </p>

        <div class="flex flex-wrap items-center justify-center gap-3">
            @if (session('impersonator_id'))
                <form method="POST" action="{{ route('impersonate.stop') }}">
                    @csrf
                    <button type="submit" class="rounded-[8px] border border-white/25 px-5 py-2.5 text-[13px] font-semibold text-white hover:bg-white/10">
                        Stop impersonating
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-[8px] px-5 py-2.5 text-[13px] font-semibold text-white" style="background:var(--brand-primary);">
                    Sign out
                </button>
            </form>
        </div>

        @if (app_setting('branding.support_email'))
            <p class="mt-8 text-[12px] text-white/45">
                Support:
                <a class="text-white/75 underline" href="mailto:{{ app_setting('branding.support_email') }}">{{ app_setting('branding.support_email') }}</a>
            </p>
        @endif
    </div>
</body>
</html>
