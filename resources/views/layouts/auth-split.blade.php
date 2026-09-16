<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="auth-html">
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

    <script>
        (function () {
            try {
                var saved = localStorage.getItem('promotix-auth-theme');
                var light = saved === 'light' || (!saved && window.matchMedia('(prefers-color-scheme: light)').matches);
                document.documentElement.classList.toggle('light-mode', light);
            } catch (e) {}
        })();
    </script>

    <style>
        {!! \App\Support\Branding::rootStyleBlock() !!}

        /* ——— Page chrome (minimal; only the card is designed) ——— */
        .auth-page {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            font-family: 'Poppins', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #0b0b0d;
        }
        html.light-mode .auth-page {
            background: #e8e8ea;
        }

        /* ——— Inner card only ——— */
        .auth-card {
            width: 100%;
            max-width: 920px;
            min-height: min(560px, calc(100vh - 2.5rem));
            display: grid;
            grid-template-columns: 1fr;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
        }
        @media (min-width: 860px) {
            .auth-card {
                grid-template-columns: 1fr 1fr;
                min-height: 580px;
            }
        }

        /* Left visual panel — dark */
        .auth-card-visual {
            position: relative;
            display: none;
            flex-direction: column;
            justify-content: flex-end;
            padding: 2rem 2.25rem 2.5rem;
            background:
                radial-gradient(ellipse 90% 70% at 50% -10%, #f5c451 0%, transparent 55%),
                linear-gradient(165deg, #5c2e0a 0%, #2a1408 42%, #140a06 100%);
            color: #fff;
        }
        @media (min-width: 860px) {
            .auth-card-visual { display: flex; }
        }
        html.light-mode .auth-card-visual {
            background:
                radial-gradient(ellipse 80% 60% at 30% 20%, rgba(255, 200, 160, 0.95) 0%, transparent 50%),
                linear-gradient(145deg, #ffe8d6 0%, #f5c4a8 45%, #efd5c8 100%);
            color: #111;
        }

        .auth-card-brand {
            position: absolute;
            top: 1.75rem;
            left: 2rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }
        .auth-card-brand img {
            height: 28px;
            width: auto;
        }
        .auth-brand-logo--on-light { display: none !important; }
        html.light-mode .auth-brand-logo--on-dark { display: none !important; }
        html.light-mode .auth-brand-logo--on-light { display: block !important; }
        html.light-mode .auth-card-brand { color: #111; }
        .auth-card-visual .auth-card-brand { color: #fff; }
        html.light-mode .auth-card-visual .auth-card-brand { color: #111; }

        .auth-card-visual-copy {
            position: relative;
            z-index: 1;
            max-width: 20rem;
        }
        .auth-card-visual-eyebrow {
            margin: 0 0 0.4rem;
            font-size: 0.85rem;
            font-weight: 400;
            opacity: 0.75;
        }
        .auth-card-visual-title {
            margin: 0;
            font-size: clamp(1.45rem, 2.4vw, 1.85rem);
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: -0.02em;
        }
        .auth-visual-title-light { display: none; }
        html.light-mode .auth-visual-title-dark { display: none; }
        html.light-mode .auth-visual-title-light { display: inline; }

        .auth-card-visual-sub {
            margin: 0.55rem 0 0;
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.7;
            line-height: 1.45;
        }
        html.light-mode .auth-card-visual-eyebrow { display: block; opacity: 0.65; }
        .auth-card-visual-eyebrow { display: none; }
        html.light-mode .auth-card-visual-sub { display: none; }

        /* Right form panel — dark */
        .auth-card-form {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem 1.5rem 2.25rem;
            background: #121214;
            color: #f4f4f5;
        }
        @media (min-width: 860px) {
            .auth-card-form { padding: 2.5rem 2.75rem; }
        }
        html.light-mode .auth-card-form {
            background: #ffffff;
            color: #111111;
        }

        .auth-card-form-inner {
            width: 100%;
            max-width: 360px;
            margin: 0 auto;
        }

        .auth-theme-toggle {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
        }
        html.light-mode .auth-theme-toggle {
            border-color: rgba(0, 0, 0, 0.12);
            background: #f4f4f5;
            color: #555;
        }
        .auth-theme-toggle:hover { opacity: 0.9; }

        .auth-back {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 1.25rem;
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.55);
            text-decoration: none;
        }
        html.light-mode .auth-back { color: #777; }
        .auth-back:hover { color: var(--brand-primary, #FF6600); }

        .auth-accent-mark {
            display: block;
            height: 36px;
            width: auto;
            max-width: 140px;
            margin: 0 auto 0.95rem;
            object-fit: contain;
        }
        .auth-accent-mark--on-dark { display: block; }
        .auth-accent-mark--on-light { display: none; }
        html.light-mode .auth-accent-mark--on-dark { display: none; }
        html.light-mode .auth-accent-mark--on-light { display: block; }

        .auth-login-title {
            margin: 0 0 0.4rem;
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: inherit;
            text-align: left;
            text-transform: none;
        }
        html.light-mode .auth-login-title { text-align: center; }

        .auth-login-sub {
            margin: 0 0 1.5rem;
            font-size: 0.82rem;
            line-height: 1.45;
            color: rgba(255, 255, 255, 0.5);
        }
        html.light-mode .auth-login-sub {
            text-align: center;
            color: #8a8a8a;
        }

        .auth-field {
            position: relative;
            margin-bottom: 0.95rem;
        }
        .auth-field-label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
        }
        html.light-mode .auth-field-label { color: #222; }

        .auth-field-input {
            width: 100%;
            height: 2.85rem;
            padding: 0 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 0.65rem;
            background: #1a1a1d;
            font-family: inherit;
            font-size: 0.9rem;
            color: #f4f4f5;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        html.light-mode .auth-field-input {
            background: #fff;
            border-color: #d8d8d8;
            color: #111;
        }
        .auth-field-input::placeholder { color: rgba(255, 255, 255, 0.28); }
        html.light-mode .auth-field-input::placeholder { color: #b0b0b0; }

        .auth-field-input:focus {
            border-color: var(--brand-primary, #FF6600);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-primary, #FF6600) 22%, transparent);
        }
        .auth-field-input.is-invalid { border-color: #f87171; }

        .auth-field.has-toggle .auth-field-input { padding-right: 2.75rem; }

        .auth-pwd-toggle {
            position: absolute;
            right: 0.45rem;
            top: auto;
            bottom: 0.42rem;
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: 0.4rem;
            background: transparent;
            color: rgba(255, 255, 255, 0.45);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        html.light-mode .auth-pwd-toggle { color: #999; }
        .auth-pwd-toggle:hover { color: var(--brand-primary, #FF6600); }

        .auth-remember {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin: 0.15rem 0 0.85rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.55);
            cursor: pointer;
        }
        html.light-mode .auth-remember { color: #666; }
        .auth-remember input { accent-color: var(--brand-primary, #FF6600); }

        .auth-forgot {
            display: block;
            margin-bottom: 1rem;
            text-align: right;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--brand-primary, #FF6600);
            text-decoration: none;
        }
        .auth-forgot:hover { text-decoration: underline; }

        .auth-btn-primary {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            min-height: 2.85rem;
            margin-top: 0.25rem;
            border: none;
            border-radius: 0.7rem;
            background: var(--brand-primary, #FF6600);
            color: #fff;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 10px 28px color-mix(in srgb, var(--brand-primary, #FF6600) 35%, transparent);
            transition: filter 0.15s;
        }
        html.light-mode .auth-btn-primary {
            background: #111111;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
        }
        .auth-btn-primary:hover { filter: brightness(1.06); }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.15rem 0;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
        }
        html.light-mode .auth-divider { color: #9a9a9a; }
        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.12);
        }
        html.light-mode .auth-divider::before,
        html.light-mode .auth-divider::after { background: #e5e5e5; }

        .auth-social-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
        }
        .auth-social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 2.65rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: #1a1a1d;
            text-decoration: none;
            transition: border-color 0.15s, background 0.15s;
        }
        html.light-mode .auth-social-btn {
            background: #fff;
            border-color: #d8d8d8;
        }
        .auth-social-btn:hover {
            border-color: var(--brand-primary, #FF6600);
        }
        .auth-social-btn svg { width: 1.25rem; height: 1.25rem; }

        .auth-footer-link {
            margin-top: 1.35rem;
            text-align: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }
        html.light-mode .auth-footer-link { color: #777; }
        .auth-footer-link a {
            font-weight: 600;
            color: var(--brand-primary, #FF6600);
            text-decoration: none;
        }
        .auth-footer-link a:hover { text-decoration: underline; }

        .auth-error {
            margin-top: 0.3rem;
            font-size: 0.75rem;
            color: #fca5a5;
        }
        html.light-mode .auth-error { color: #dc2626; }

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
        }

        .auth-field-input:-webkit-autofill,
        .auth-field-input:-webkit-autofill:hover,
        .auth-field-input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #1a1a1d inset !important;
            box-shadow: 0 0 0 1000px #1a1a1d inset !important;
            -webkit-text-fill-color: #f4f4f5 !important;
            caret-color: #f4f4f5;
        }
        html.light-mode .auth-field-input:-webkit-autofill,
        html.light-mode .auth-field-input:-webkit-autofill:hover,
        html.light-mode .auth-field-input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            box-shadow: 0 0 0 1000px #ffffff inset !important;
            -webkit-text-fill-color: #111 !important;
            caret-color: #111;
        }
    </style>
</head>
<body class="auth-page">
    @if (session('status'))
        <div class="auth-status-banner" role="status">{{ session('status') }}</div>
    @endif

    @php
        $brandName = \App\Support\PortalBrand::name();
        $logoDarkPanel = \App\Support\Branding::logoAsset('light'); // light logo on dark/amber panel
        $logoLightPanel = \App\Support\Branding::logoAsset('dark');
    @endphp

    <div class="auth-card">
        <aside class="auth-card-visual" aria-hidden="false">
            <div class="auth-card-brand">
                <img src="{{ $logoDarkPanel }}" alt="" class="auth-brand-logo auth-brand-logo--on-dark">
                <img src="{{ $logoLightPanel }}" alt="" class="auth-brand-logo auth-brand-logo--on-light" hidden>
                <span>{{ $brandName }}</span>
            </div>
            <div class="auth-card-visual-copy">
                <p class="auth-card-visual-eyebrow">You can easily</p>
                <h2 class="auth-card-visual-title">
                    <span class="auth-visual-title-dark">Where Ideas Take Off</span>
                    <span class="auth-visual-title-light" hidden>Get access to your personal hub for clarity and productivity.</span>
                </h2>
                <p class="auth-card-visual-sub">We're waiting for your breakthroughs.</p>
            </div>
        </aside>

        <main class="auth-card-form">
            <button type="button" class="auth-theme-toggle" id="auth-theme-toggle" aria-label="Toggle light and dark">
                <span id="auth-theme-label">Light</span>
            </button>
            <div class="auth-card-form-inner">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('auth-theme-toggle');
            var label = document.getElementById('auth-theme-label');
            function sync() {
                var light = document.documentElement.classList.contains('light-mode');
                if (label) label.textContent = light ? 'Dark' : 'Light';
            }
            sync();
            btn?.addEventListener('click', function () {
                var next = !document.documentElement.classList.contains('light-mode');
                document.documentElement.classList.toggle('light-mode', next);
                try { localStorage.setItem('promotix-auth-theme', next ? 'light' : 'dark'); } catch (e) {}
                sync();
            });
        })();
    </script>
</body>
</html>
