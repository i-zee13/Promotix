@props([
    // Visual context the brand mark is rendered against.
    //   'dark'   → original purple logo (default — fits dark / black backgrounds)
    //   'purple' → inverted to white so it stays readable on the purple #6400B3 cards
    //   'light'  → original purple logo (also fits white / light backgrounds)
    'variant' => 'dark',
    // Height in pixels. Width is auto.
    'height' => 40,
])

@php
    $logoPath = public_path('images/logo.png');
    $logoUrl = url('/images/logo.png') . (is_file($logoPath) ? '?v=' . filemtime($logoPath) : '');
    // Logo file includes its own black canvas — never invert on auth/purple backgrounds.
    $filterClass = '';
@endphp

<a href="{{ url('/') }}" {{ $attributes->class(['inline-flex items-center gap-2']) }} aria-label="{{ config('app.name', 'Promotix') }}">
    <img
        src="{{ $logoUrl }}"
        alt="{{ config('app.name', 'Promotix') }}"
        class="block w-auto {{ $filterClass }}"
        style="height: {{ (int) $height }}px;"
        loading="eager"
        decoding="async"
        referrerpolicy="no-referrer"
    >
</a>
