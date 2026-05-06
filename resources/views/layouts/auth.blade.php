<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0D0D0D] font-sans text-gray-100 antialiased">
    @if (session('status'))
        <div class="fixed right-4 top-4 z-50 max-w-md rounded-lg border border-emerald-400/40 bg-emerald-500/20 px-4 py-3 text-sm text-emerald-100 shadow-lg">
            {{ session('status') }}
        </div>
    @endif
    @yield('content')
</body>
</html>
