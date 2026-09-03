<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Sign in') — {{ \App\Support\PortalBrand::name() }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        {!! \App\Support\Branding::rootStyleBlock() !!}
        :root {
            --auth-bg: #0a0a0f;
            --auth-bg-panel: #0e0e14;
            --auth-bg-input: #12121a;
            --auth-text: #f4f4f5;
            --auth-muted: rgba(255, 255, 255, 0.55);
            --auth-border: rgba(255, 255, 255, 0.1);
            --auth-border-focus: color-mix(in srgb, var(--brand-primary) 65%, transparent);
            --auth-brand-ring-soft: color-mix(in srgb, var(--brand-primary) 35%, transparent);
        }

        .auth-dark-page {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: var(--auth-text);
            background-color: #08080f;
            background-image: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 22px 22px;
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
        }

        .auth-split-shell {
            min-height: calc(100vh - 52px);
            display: flex;
            flex-direction: column;
        }

        .auth-split-hero {
            display: flex;
            position: relative;
            align-items: center;
            justify-content: center;
            padding: 1.75rem 1.25rem 0.5rem;
            background: transparent;
        }

        .auth-split-login {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem 1.25rem 2.5rem;
            background: transparent;
        }

        .auth-split-login-inner {
            width: 100%;
            max-width: 360px;
            margin: 0 auto;
        }

        .auth-hero-visual {
            width: 100%;
            max-width: 520px;
            text-align: center;
        }

        .auth-hero-ring {
            position: relative;
            width: min(400px, 72vw);
            aspect-ratio: 1;
            margin: 0 auto;
        }

        .auth-hero-ring::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 4px solid var(--auth-brand-ring);
            box-shadow:
                0 0 0 10px var(--auth-brand-ring-soft),
                0 0 80px rgba(var(--brand-primary-rgb, 255, 102, 0), 0.45);
        }

        .auth-hero-ring::after {
            content: '';
            position: absolute;
            inset: -8%;
            border-radius: 50%;
            border: 2px solid rgba(var(--brand-primary-rgb, 255, 102, 0), 0.22);
            pointer-events: none;
        }

        .auth-hero-ring-inner {
            position: absolute;
            inset: 16%;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(160deg, rgba(5, 5, 8, 0.92) 0%, rgba(40, 18, 8, 0.88) 55%, rgba(13, 13, 18, 0.9) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 50px rgba(var(--brand-primary-rgb, 255, 102, 0), 0.3);
        }

        .auth-hero-ring-inner img.auth-hero-photo {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.28;
            filter: grayscale(1) brightness(0.55);
        }

        .auth-hero-logo-wrap {
            position: relative;
            z-index: 1;
            padding: 0.5rem;
        }

        .auth-hero-logo-wrap img {
            display: block;
            height: 100px;
            width: auto;
            max-width: min(170px, 48vw);
            margin: 0 auto;
        }

        .auth-hero-tagline {
            margin-top: 2rem;
            font-size: clamp(1.35rem, 2.5vw, 1.85rem);
            font-weight: 300;
            line-height: 1.45;
            color: var(--auth-muted);
        }

        .auth-hero-tagline strong {
            font-weight: 700;
            color: #ffffff;
        }

        @media (max-width: 767px) {
            .auth-split-mobile-brand {
                display: none;
            }

            .auth-hero-ring {
                width: min(280px, 78vw);
            }

            .auth-hero-tagline {
                margin-top: 1.25rem;
                font-size: 1.15rem;
            }
        }

        @media (min-width: 768px) {
            .auth-split-shell {
                flex-direction: row;
                align-items: stretch;
            }

            .auth-split-hero {
                padding: 2.5rem 1.5rem 1.5rem;
                flex: 0 0 66.666667%;
                max-width: 66.666667%;
                min-height: calc(100vh - 52px);
            }

            .auth-split-login {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
                min-height: calc(100vh - 52px);
                padding: 2.5rem 2rem;
                border-left: 1px solid rgba(var(--brand-primary-rgb, 255, 102, 0), 0.25);
                background: rgba(8, 8, 15, 0.35);
                backdrop-filter: blur(2px);
                box-shadow: -12px 0 40px rgba(0, 0, 0, 0.25);
            }
        }

        .auth-split-footer {
            padding: 0.85rem 1rem 1.25rem;
            text-align: center;
            font-size: 0.72rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.35);
            background: transparent;
        }

        .auth-login-title {
            margin: 0 0 1.75rem;
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--auth-text);
        }

        .auth-field {
            position: relative;
            margin-bottom: 1rem;
        }

        .auth-field-icon {
            position: absolute;
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            width: 2.25rem;
            height: 2.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.55rem;
            background: rgba(var(--brand-primary-rgb, 255, 102, 0), 0.22);
            color: var(--brand-primary, #FF6600);
            pointer-events: none;
        }

        .auth-field-icon svg {
            width: 1.05rem;
            height: 1.05rem;
        }

        .auth-field-input {
            width: 100%;
            height: 3rem;
            padding: 0 0.9rem 0 3.35rem;
            border: 1px solid var(--auth-border);
            border-radius: 0.65rem;
            background: var(--auth-bg-input);
            font-family: inherit;
            font-size: 0.92rem;
            color: var(--auth-text);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .auth-field-input::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }

        .auth-field-input:focus {
            border-color: var(--auth-border-focus);
            box-shadow: 0 0 0 3px rgba(var(--brand-primary-rgb, 255, 102, 0), 0.22);
            background: #16161f;
        }

        .auth-field-input.is-invalid {
            border-color: #f87171;
        }

        .auth-field.has-toggle .auth-field-input {
            padding-right: 2.75rem;
        }

        .auth-pwd-toggle {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: 0.45rem;
            background: transparent;
            color: var(--auth-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-pwd-toggle:hover {
            color: var(--brand-primary, #FF6600);
        }

        .auth-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.25rem 0 1.25rem;
            font-size: 0.82rem;
            color: var(--auth-muted);
            cursor: pointer;
            user-select: none;
        }

        .auth-remember input {
            width: 0.95rem;
            height: 0.95rem;
            accent-color: var(--auth-brand);
        }

        .auth-btn-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .auth-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: 0.65rem 1rem;
            border: none;
            border-radius: 0.65rem;
            background: linear-gradient(135deg, var(--auth-brand) 0%, var(--auth-brand-dark) 100%);
            color: #fff;
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            cursor: pointer;
            transition: filter 0.2s, box-shadow 0.2s;
            box-shadow: 0 8px 28px var(--auth-brand-glow);
        }

        .auth-btn-primary:hover {
            filter: brightness(1.08);
            box-shadow: 0 10px 32px rgba(var(--brand-primary-rgb, 255, 102, 0), 0.5);
        }

        .auth-btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: 0.65rem 0.75rem;
            border: 1.5px solid rgba(var(--brand-primary-rgb, 255, 102, 0), 0.5);
            border-radius: 0.65rem;
            background: rgba(var(--brand-primary-rgb, 255, 102, 0), 0.12);
            color: #ffe8d4;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s, border-color 0.2s;
        }

        .auth-btn-outline:hover {
            background: rgba(var(--brand-primary-rgb, 255, 102, 0), 0.28);
            border-color: rgba(var(--brand-primary-rgb, 255, 102, 0), 0.75);
        }

        .auth-forgot {
            display: block;
            margin-bottom: 1rem;
            text-align: right;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--brand-primary, #FF6600);
            text-decoration: none;
        }

        .auth-forgot:hover {
            text-decoration: underline;
        }

        .auth-google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            width: 100%;
            min-height: 2.75rem;
            padding: 0.65rem 1rem;
            border: 1px solid var(--auth-border);
            border-radius: 0.65rem;
            background: rgba(255, 255, 255, 0.04);
            color: rgba(255, 255, 255, 0.88);
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 500;
            text-decoration: none;
            transition: border-color 0.2s, background 0.2s;
        }

        .auth-google-btn:hover {
            border-color: rgba(var(--brand-primary-rgb, 255, 102, 0), 0.45);
            background: rgba(var(--brand-primary-rgb, 255, 102, 0), 0.12);
        }

        .auth-error {
            margin-top: 0.35rem;
            font-size: 0.78rem;
            color: #fca5a5;
        }

        .auth-status-banner {
            position: fixed;
            right: 1rem;
            top: 1rem;
            z-index: 50;
            max-width: 22rem;
            padding: 0.75rem 1rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.15);
            color: #a7f3d0;
            font-size: 0.875rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        .auth-field-input:-webkit-autofill,
        .auth-field-input:-webkit-autofill:hover,
        .auth-field-input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #12121a inset !important;
            box-shadow: 0 0 0 1000px #12121a inset !important;
            -webkit-text-fill-color: #f4f4f5 !important;
            caret-color: #f4f4f5;
        }
    </style>
</head>
<body class="auth-dark-page">
    @if (session('status'))
        <div class="auth-status-banner" role="status">{{ session('status') }}</div>
    @endif

    @php
        $heroPath = public_path('images/login-hero.png');
        $heroUrl = is_file($heroPath)
            ? asset('images/login-hero.png') . '?v=' . filemtime($heroPath)
            : null;
    @endphp

    <div class="auth-split-shell">
        {{-- col-md-8: brand visual (left on desktop) --}}
        <aside class="auth-split-hero" aria-hidden="false">
            <div class="auth-hero-visual">
                <div class="auth-hero-ring">
                    <div class="auth-hero-ring-inner">
                        @if ($heroUrl)
                            <img src="{{ $heroUrl }}" alt="" class="auth-hero-photo" loading="eager" decoding="async" referrerpolicy="no-referrer">
                        @endif
                    </div>
                </div>

                <p class="auth-hero-tagline">
                    Your shield for <strong>protected</strong> ad spend
                </p>
            </div>
        </aside>

        {{-- col-md-4: login (right on desktop) --}}
        <main class="auth-split-login">
            <div class="auth-split-login-inner">
                @yield('content')
            </div>
        </main>
    </div>

    <footer class="auth-split-footer">
        Copyright &copy; {{ date('Y') }} {{ \App\Support\PortalBrand::name() }}. All rights reserved.
    </footer>
</body>
</html>
