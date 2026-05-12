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
    $filterClass = $variant === 'purple' ? 'brightness-0 invert' : '';
@endphp

<a href="{{ url('/') }}" {{ $attributes->class(['inline-flex items-center gap-2']) }} aria-label="{{ config('app.name', 'Promotix') }}">
    <img
        src="{{ asset('images/logo.png') }}"
        alt="{{ config('app.name', 'Promotix') }}"
        class="block w-auto {{ $filterClass }}"
        style="height: 100px;"
        loading="eager"
        decoding="async"
    >
</a>
