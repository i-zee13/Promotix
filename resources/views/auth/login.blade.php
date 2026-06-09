<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --login-primary: #6400B3;
            --login-primary-dark: #4D008E;
            --login-primary-hover: #7a1acc;
            --login-bg: #0D0D0D;
            --login-text: #ffffff;
            --login-muted: rgba(255, 255, 255, 0.75);
            --login-border: rgba(255, 255, 255, 0.35);
            --login-input-bg: rgba(77, 0, 142, 0.6);
            --login-radius: 15px;
            --login-field-radius: 10px;
        }

        * { box-sizing: border-box; }

        body.login-page {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: var(--login-text);
            background: var(--login-bg);
        }

        .login-page #wrapper {
            min-height: 100vh;
            background:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(100, 0, 179, 0.35) 0%, transparent 55%),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(77, 0, 142, 0.25) 0%, transparent 50%),
                var(--login-bg);
        }

        .login-page .log_con {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }

        .login-page .container {
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .login-page .topheading {
            text-align: center;
            margin-bottom: 28px;
            padding-top: 12px;
        }

        .login-page .table-struct {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .login-page .auth-form-wrap {
            width: 100%;
        }

        .login-page .auth-form {
            background: var(--login-primary);
            border: 1px solid var(--login-border);
            border-radius: var(--login-radius);
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.25),
                0 25px 60px -20px rgba(100, 0, 179, 0.55);
            padding: 40px 32px 36px;
        }

        @media (min-width: 480px) {
            .login-page .auth-form {
                padding: 48px 44px 40px;
            }
        }

        .login-page .log-form h3 {
            margin: 0 0 28px;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-align: center;
            color: var(--login-text);
        }

        .login-page .log-form h3 span {
            font-weight: 300;
        }

        .login-page .form-group {
            margin-bottom: 18px;
        }

        .login-page .field-wrap {
            position: relative;
        }

        .login-page .field-wrap .field-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--login-field-radius);
            background: rgba(255, 255, 255, 0.2);
            color: var(--login-text);
            pointer-events: none;
        }

        .login-page .field-wrap .field-icon svg {
            width: 18px;
            height: 18px;
        }

        .login-page .form-control {
            width: 100%;
            height: 48px;
            padding: 0 16px 0 58px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--login-field-radius);
            background: var(--login-input-bg);
            color: var(--login-text);
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .login-page .form-control::placeholder {
            color: rgba(255, 255, 255, 0.65);
        }

        .login-page .form-control:focus {
            border-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
        }

        .login-page .form-control.is-invalid {
            border-color: #fda4af;
        }

        .login-page .field-wrap.has-toggle .form-control {
            padding-right: 48px;
        }

        .login-page .pwd-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: rgba(255, 255, 255, 0.85);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .login-page .pwd-toggle:hover {
            color: #ffffff;
        }

        .login-page .pwd-toggle svg {
            width: 20px;
            height: 20px;
        }

        .login-page .invalid-feedback {
            display: block;
            margin-top: 6px;
            font-size: 0.82rem;
            color: #fecdd3;
        }

        .login-page .f_pass {
            display: inline-block;
            margin-bottom: 20px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--login-muted);
            text-decoration: none;
        }

        .login-page .f_pass:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        .login-page .btn-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .login-page .btn-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 44px;
            padding: 10px 20px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: var(--login-field-radius);
            background: var(--login-input-bg);
            color: var(--login-text);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: background 0.2s;
        }

        .login-page .btn-login:hover {
            background: rgba(77, 0, 142, 0.85);
        }

        .login-page .btn-signup {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 20px;
            border-radius: var(--login-field-radius);
            background: #ffffff;
            color: var(--login-primary);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }

        .login-page .btn-signup:hover {
            background: rgba(255, 255, 255, 0.92);
        }

        .login-page .btn-google {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            min-height: 44px;
            padding: 10px 16px;
            border: none;
            border-radius: var(--login-field-radius);
            background: #ffffff;
            color: rgba(100, 0, 179, 0.8);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s;
        }

        .login-page .btn-google:hover {
            background: rgba(255, 255, 255, 0.92);
        }

        .login-page .btn-google svg {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .login-page .forgot-wrap {
            text-align: center;
            margin-top: -4px;
            margin-bottom: 16px;
        }

        .login-page .Log_footer {
            margin-top: 28px;
            padding-top: 8px;
            text-align: center;
            font-size: 0.78rem;
            line-height: 1.6;
            color: var(--login-muted);
        }

        .login-page .Log_footer a {
            color: var(--login-text);
            text-decoration: none;
        }

        .login-page .Log_footer a:hover {
            text-decoration: underline;
        }

        .login-page .status-banner {
            position: fixed;
            right: 16px;
            top: 16px;
            z-index: 50;
            max-width: 360px;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid rgba(52, 211, 153, 0.4);
            background: rgba(16, 185, 129, 0.2);
            color: #d1fae5;
            font-size: 0.875rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        .login-page .form-control:-webkit-autofill,
        .login-page .form-control:-webkit-autofill:hover,
        .login-page .form-control:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px rgba(77, 0, 142, 0.6) inset !important;
            box-shadow: 0 0 0 1000px rgba(77, 0, 142, 0.6) inset !important;
            -webkit-text-fill-color: #ffffff !important;
            caret-color: #ffffff;
        }
    </style>
</head>
<body class="login-page bg_main">

    @if (session('status'))
        <div class="status-banner" role="status">{{ session('status') }}</div>
    @endif

    <div id="wrapper" class="EbobBGImg">
        <div class="log_con">
            <div class="container">
                <div class="topheading">
                    <x-brand :height="44" variant="purple" />
                </div>

                <div class="table-struct full-width">
                    <div class="table-cell vertical-align-middle auth-form-wrap">
                        <div class="auth-form">
                            <div class="login-right">
                                <div class="log-form">
                                    <h3 class="mb-20">LOG <span>IN</span></h3>

                                    <form method="POST" action="{{ route('login') }}" x-data="{ showPwd: false }">
                                        @csrf

                                        <div class="form-group">
                                            <div class="field-wrap user">
                                                <span class="field-icon" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A4.5 4.5 0 0 0 16.5 14h-3a4.5 4.5 0 0 0-4.499 6.118Z" />
                                                    </svg>
                                                </span>
                                                <input
                                                    id="email"
                                                    type="email"
                                                    class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                                                    name="email"
                                                    value="{{ old('email') }}"
                                                    placeholder="E-mail"
                                                    required
                                                    autofocus
                                                    autocomplete="username"
                                                >
                                                @error('email')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="field-wrap pass has-toggle">
                                                <span class="field-icon" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                    </svg>
                                                </span>
                                                <input
                                                    id="password"
                                                    :type="showPwd ? 'text' : 'password'"
                                                    class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}"
                                                    name="password"
                                                    placeholder="password"
                                                    required
                                                    autocomplete="current-password"
                                                >
                                                <button
                                                    type="button"
                                                    class="pwd-toggle"
                                                    @click="showPwd = !showPwd"
                                                    :aria-label="showPwd ? 'Hide password' : 'Show password'"
                                                >
                                                    <svg x-show="!showPwd" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    <svg x-show="showPwd" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88" />
                                                    </svg>
                                                </button>
                                                @error('password')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="btn-row">
                                            <button type="submit" class="btn-login">LOG IN</button>
                                            @if (Route::has('register'))
                                                <a href="{{ route('register') }}" class="btn-signup">Signup</a>
                                            @endif
                                        </div>

                                        @if (Route::has('password.request'))
                                            <div class="forgot-wrap">
                                                <a class="f_pass" href="{{ route('password.request') }}">Forgot password?</a>
                                            </div>
                                        @endif

                                        <a href="{{ route('integrations.google.redirect', ['context' => 'auth']) }}" class="btn-google">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
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
                    </div>
                </div>

                <div class="Log_footer">
                    Copyright &copy; {{ date('Y') }} {{ config('app.name') }} All rights reserved.
                </div>
            </div>
        </div>
    </div>

</body>
</html>
